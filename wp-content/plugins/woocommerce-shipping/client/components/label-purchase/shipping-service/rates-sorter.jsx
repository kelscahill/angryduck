import { Button, Dropdown, MenuItem } from '@wordpress/components';
import { chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { SORT_BY } from './constants';

export const RatesSorter = ( { setSortBy, sortingBy, canSortByDelivery } ) => (
	<Dropdown
		popoverProps={ {
			placement: 'bottom-end',
			resize: true,
			shift: true,
			inline: true,
		} }
		renderToggle={ ( { isOpen, onToggle } ) => {
			return (
				<Button
					isTertiary
					className="shipping-rates__sort"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					icon={ isOpen ? chevronUp : chevronDown }
				>
					{ __( 'Sort by', 'woocommerce-shipping' ) }
				</Button>
			);
		} }
		renderContent={ ( { onClose } ) => (
			<>
				<MenuItem
					onClick={ () => {
						setSortBy( SORT_BY.CHEAPEST );
						onClose();
					} }
					role="menuitemradio"
					isSelected={ sortingBy === SORT_BY.CHEAPEST }
				>
					{ __( 'Cheapest', 'woocommerce-shipping' ) }
				</MenuItem>

				{ canSortByDelivery && (
					<MenuItem
						onClick={ () => {
							setSortBy( SORT_BY.FASTEST );
							onClose();
						} }
						role="menuitemradio"
						isSelected={ sortingBy === SORT_BY.FASTEST }
					>
						{ __( 'Fastest', 'woocommerce-shipping' ) }
					</MenuItem>
				) }
			</>
		) }
	/>
);
