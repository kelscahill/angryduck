import React from 'react';
import { Button, Dropdown, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { chevronDown } from '@wordpress/icons';
import { getNoneSelectedShipmentItems, normalizeShipments } from 'utils';
import { ShipmentItem } from 'types';
import { getShipmentTitle } from '../utils';
import { useLabelPurchaseContext } from 'context/label-purchase';

interface SplitHeaderProps {
	isDisabled: () => boolean;
}

export const MoveTo = ( { isDisabled }: SplitHeaderProps ) => {
	const {
		shipment: {
			shipments,
			setShipments,
			selections,
			resetSelections,
			setCurrentShipmentId,
		},
		labels: { hasPurchasedLabel },
	} = useLabelPurchaseContext();

	const moveToShipment = ( destinationKey: string ) => {
		// Selected items from other shipments
		const itemsToAdd = Object.entries( selections ).reduce(
			( acc, [ key, items ] ) =>
				key !== destinationKey ? [ ...acc, ...items ] : acc,
			[] as ShipmentItem[]
		);

		const otherShipments = getNoneSelectedShipmentItems(
			shipments,
			selections
		);

		const newShipments = {
			...otherShipments,
			[ destinationKey ]:
				shipments[ destinationKey ].concat( itemsToAdd ),
		};

		/**
		 * We need to reset the current shipment id to the first shipment
		 * to prevent the app from using a shipmentId that no longer exists.
		 */
		setCurrentShipmentId( '0' );

		setShipments(
			normalizeShipments( newShipments ) as Record<
				string,
				ShipmentItem[]
			>
		);
		resetSelections( Object.keys( newShipments ) );
	};

	return (
		<Dropdown
			popoverProps={ {
				placement: 'bottom-start',
				noArrow: false,
				resize: true,
				shift: true,
				inline: true,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="secondary"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					disabled={ isDisabled() }
				>
					{ __( 'Move To', 'woocommerce-shipping' ) }{ ' ' }
					<Icon icon={ chevronDown } />
				</Button>
			) }
			renderContent={ ( { onClose } ) =>
				Object.entries( shipments ).map( ( [ key ] ) => {
					const preventMoveToShipment = hasPurchasedLabel(
						true,
						true,
						key
					);
					return (
						<Button
							key={ key }
							onClick={ () => {
								moveToShipment( key );
								onClose();
							} }
							disabled={ preventMoveToShipment }
							aria-disabled={ preventMoveToShipment }
						>
							{ getShipmentTitle(
								key,
								Object.values( shipments ).length
							) }
						</Button>
					);
				} )
			}
		/>
	);
};
