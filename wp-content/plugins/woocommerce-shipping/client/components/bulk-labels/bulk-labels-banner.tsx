import {
	Button,
	CheckboxControl,
	Flex,
	FlexItem,
	Notice,
} from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { envelope } from '@wordpress/icons';
import { getBulkLabelsMaxOrders } from 'data/bulk-labels';
import type { ReactNode } from 'react';
import './style.scss';

interface StatusTextArgs {
	hasSelection: boolean;
	exceedsBulkCap: boolean;
	eligible: number;
	needsAttention: number;
	summary: string;
}

const getStatusText = ( {
	hasSelection,
	exceedsBulkCap,
	eligible,
	needsAttention,
	summary,
}: StatusTextArgs ): ReactNode => {
	if ( ! hasSelection ) {
		return __(
			'Select unfulfilled orders to start purchasing shipping labels',
			'woocommerce-shipping'
		);
	}

	if ( exceedsBulkCap ) {
		return sprintf(
			/* translators: %d: maximum number of orders that can be processed at once */
			__(
				'Up to %d orders can be processed at a time. Deselect some to continue.',
				'woocommerce-shipping'
			),
			getBulkLabelsMaxOrders()
		);
	}

	const ready = __( 'Ready to fulfill in bulk', 'woocommerce-shipping' );
	if ( eligible === 0 && needsAttention === 0 ) {
		return ready;
	}

	return `${ ready } — ${ summary }.`;
};

const ORDER_CHECKBOX_SELECTOR =
	'#the-list input[type="checkbox"][name="id[]"], #the-list input[type="checkbox"][name="post[]"]';

const getOrderCheckboxes = (): HTMLInputElement[] =>
	Array.from(
		document.querySelectorAll< HTMLInputElement >( ORDER_CHECKBOX_SELECTOR )
	);

const getSelectedOrderIds = (): string[] => {
	const checkboxes = getOrderCheckboxes();
	return Array.from( checkboxes )
		.filter( ( cb ) => cb.checked )
		.map( ( cb ) => cb.value );
};

const getOrderStatusFromRow = ( orderId: string ): string | null => {
	const checkbox = document.querySelector< HTMLInputElement >(
		`#the-list input[type="checkbox"][value="${ orderId }"]`
	);
	const row = checkbox?.closest( 'tr' );
	if ( ! row ) {
		return null;
	}

	const statusMark = row.querySelector< HTMLElement >(
		'.order-status .order-label, mark.order-status'
	);
	if ( statusMark ) {
		const classList = Array.from( statusMark.classList );
		const statusClass = classList.find( ( c ) =>
			c.startsWith( 'status-' )
		);
		if ( statusClass ) {
			return statusClass.replace( 'status-', '' );
		}
	}

	return null;
};

const ELIGIBLE_STATUSES = new Set( [ 'processing', 'on-hold', 'pending' ] );

const isOrderEligible = ( orderId: string ): boolean => {
	const status = getOrderStatusFromRow( orderId );
	if ( ! status ) {
		return true;
	}
	return ELIGIBLE_STATUSES.has( status );
};

const deselectAllOrders = () => {
	const uncheck = ( cb: HTMLInputElement ) => {
		if ( ! cb.checked ) {
			return;
		}
		cb.checked = false;
		cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	getOrderCheckboxes().forEach( uncheck );

	document
		.querySelectorAll< HTMLInputElement >(
			'#cb-select-all-1, #cb-select-all-2'
		)
		.forEach( uncheck );
};

interface BulkLabelsBannerProps {
	onCreateLabels?: ( orderIds: number[] ) => void;
}

export const BulkLabelsBanner = ( {
	onCreateLabels,
}: BulkLabelsBannerProps = {} ) => {
	const [ selectedIds, setSelectedIds ] = useState< string[] >( [] );
	const [ orderCount, setOrderCount ] = useState( 0 );

	const syncSelection = useCallback( () => {
		setOrderCount( getOrderCheckboxes().length );
		setSelectedIds( getSelectedOrderIds() );
	}, [] );

	useEffect( () => {
		const table = document.querySelector( '.wp-list-table' );
		if ( ! table ) {
			return;
		}

		// Listen for clicks on checkboxes within the orders table. Defer
		// to next tick so WP core's "select all" handler can toggle the
		// individual checkboxes before we read state.
		const onClick = ( event: Event ) => {
			const target = event.target as HTMLElement | null;
			if (
				target?.tagName !== 'INPUT' ||
				( target as HTMLInputElement ).type !== 'checkbox'
			) {
				return;
			}
			window.setTimeout( syncSelection, 0 );
		};

		table.addEventListener( 'click', onClick );
		syncSelection();

		return () => {
			table.removeEventListener( 'click', onClick );
		};
	}, [ syncSelection ] );

	const eligible = selectedIds.filter( isOrderEligible );
	const needsAttention = selectedIds.length - eligible.length;

	const getSummary = (): string => {
		const parts: string[] = [];

		if ( eligible.length > 0 ) {
			parts.push(
				sprintf(
					/* translators: %d: number of eligible orders */
					_n(
						'%d eligible for shipping labels',
						'%d eligible for shipping labels',
						eligible.length,
						'woocommerce-shipping'
					),
					eligible.length
				)
			);
		}

		if ( needsAttention > 0 ) {
			parts.push(
				sprintf(
					/* translators: %d: number of orders needing attention */
					_n(
						'%d needs attention',
						'%d need attention',
						needsAttention,
						'woocommerce-shipping'
					),
					needsAttention
				)
			);
		}

		return parts.join( ', ' );
	};

	const handleDeselectAll = () => {
		deselectAllOrders();
		setSelectedIds( [] );
	};

	const hasSelection = selectedIds.length > 0;
	const hasPartialSelection = hasSelection && selectedIds.length < orderCount;
	const hasFullSelection = hasSelection && selectedIds.length === orderCount;
	const exceedsBulkCap = selectedIds.length > getBulkLabelsMaxOrders();

	return (
		<Notice
			className="bulk-labels-banner"
			status="info"
			isDismissible={ false }
			politeness="polite"
			spokenMessage={
				/**
				 * We need to override the default spoken message so that the children are not conditionally rendered via a hook.
				 * Conditional hooks are not allowed in React and cause an error.
				 */
				__(
					'Unfulfilled orders selected and ready to fulfill in bulk',
					'woocommerce-shipping'
				)
			}
		>
			<Flex justify="space-between" align="center" gap={ 4 }>
				<FlexItem>
					<Flex align="center" gap={ 2 }>
						<FlexItem>
							{ hasSelection ? (
								<CheckboxControl
									checked={ hasFullSelection }
									indeterminate={ hasPartialSelection }
									onChange={ () => {
										handleDeselectAll();
									} }
									__nextHasNoMarginBottom={ true }
									// @ts-ignore - label can't use an Element
									label={
										<>
											&nbsp;
											<strong>
												{ sprintf(
													/* translators: %d: number of selected orders */
													_n(
														'%d order selected.',
														'%d orders selected.',
														selectedIds.length,
														'woocommerce-shipping'
													),
													selectedIds.length
												) }
											</strong>{ ' ' }
										</>
									}
								/>
							) : null }
						</FlexItem>
						<FlexItem>
							{ getStatusText( {
								hasSelection,
								exceedsBulkCap,
								eligible: eligible.length,
								needsAttention,
								summary: getSummary(),
							} ) }
						</FlexItem>
					</Flex>
				</FlexItem>
				<FlexItem>
					<Flex align="center" gap={ 2 }>
						{ hasSelection && (
							<Button
								variant="primary"
								icon={ envelope }
								disabled={ exceedsBulkCap }
								onClick={ () =>
									onCreateLabels?.(
										selectedIds.map( Number )
									)
								}
							>
								{ sprintf(
									/* translators: %d: number of shipping labels to create */
									_n(
										'Create %d shipping label',
										'Create %d shipping labels',
										selectedIds.length,
										'woocommerce-shipping'
									),
									selectedIds.length
								) }
							</Button>
						) }
					</Flex>
				</FlexItem>
			</Flex>
		</Notice>
	);
};
