import { Button, Dropdown, MenuItem } from '@wordpress/components';
import { chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';
import { DEFAULT_SORT_BY, DELIVERY_PROPERTIES } from './constants';

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
						setSortBy( DEFAULT_SORT_BY );
						onClose();
					} }
					role="menuitemradio"
					isSelected={ sortingBy === DEFAULT_SORT_BY }
				>
					{ __( 'Cheapest', 'woocommerce-shipping' ) }
				</MenuItem>

				{ canSortByDelivery && (
					<MenuItem
						onClick={ () => {
							setSortBy( DELIVERY_PROPERTIES );
							onClose();
						} }
						role="menuitemradio"
						isSelected={ isEqual( sortingBy, DELIVERY_PROPERTIES ) }
					>
						{ __( 'Fastest', 'woocommerce-shipping' ) }
					</MenuItem>
				) }
			</>
		) }
	/>
);
