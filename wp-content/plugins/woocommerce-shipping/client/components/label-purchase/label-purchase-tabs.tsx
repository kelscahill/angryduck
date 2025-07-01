import { TabPanel } from '@wordpress/components';
import { forwardRef } from '@wordpress/element';
import { getSubItems } from 'utils/order-items';
import { useLabelPurchaseContext } from 'context/label-purchase';
import { ShipmentItem } from 'types';
import { ShipmentContent } from './shipment-content';
import { getCurrentOrder, getCurrentOrderItems } from 'utils';
import { getShipmentTitle } from './utils';
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';
import { Icon } from '@wordpress/components';

interface LabelPurchaseTabsProps {
	setStartSplitShipment: ( startSplitShipment: boolean ) => void;
}
export const LabelPurchaseTabs = forwardRef(
	( { setStartSplitShipment }: LabelPurchaseTabsProps, ref ) => {
		const orderItems = getCurrentOrderItems();
		const order = getCurrentOrder();
		const count = order.total_line_items_quantity;
		const {
			shipment: {
				shipments,
				setShipments,
				selections,
				setSelection,
				currentShipmentId,
				setCurrentShipmentId,
			},
			packages,
			customs: { updateCustomsItems },
			labels: {
				hasMissingPurchase,
				hasUnfinishedShipment,
				isPurchasing,
				isUpdatingStatus,
				getShipmentsWithoutLabel,
				hasPurchasedLabel,
			},
		} = useLabelPurchaseContext();
		const orderFulfilled = ! hasMissingPurchase();

		const tabs = () => {
			let extraTabs: { name: string; title: string }[] = [];
			if (
				! orderFulfilled &&
				! isPurchasing &&
				! isUpdatingStatus &&
				count > 1
			) {
				extraTabs = [
					{
						name: 'edit',
						title: __( 'Split shipment', 'woocommerce-shipping' ),
					},
				];
			} else if ( hasUnfinishedShipment() ) {
				extraTabs = [];
			}
			if (
				getShipmentsWithoutLabel()?.length === 0 &&
				! isPurchasing &&
				! isUpdatingStatus
			) {
				extraTabs = [
					{
						name: 'new-shipment',
						title: __( 'Add shipment', 'woocommerce-shipping' ),
					},
				];
			}
			return [
				...Object.keys( shipments ).map( ( name ) => ( {
					name,
					title: getShipmentTitle(
						name,
						Object.keys( shipments ).length
					),
					icon: (
						<>
							{ getShipmentTitle(
								name,
								Object.keys( shipments ).length
							) }
							{ hasPurchasedLabel( true, true, name ) && (
								<Icon icon={ check } />
							) }
						</>
					),
					className: `shipment-tab-${ name }`,
				} ) ),
				...extraTabs,
			];
		};

		const createShipmentForExtraLabel = async () => {
			const newShipmentId = Object.keys( shipments ).length;
			const newShipment = orderItems.map( ( orderItem ) => ( {
				...orderItem,
				subItems: getSubItems( orderItem as ShipmentItem ),
			} ) );
			const updatedShipments = {
				...shipments,
				[ newShipmentId ]: newShipment,
			};

			setShipments( updatedShipments );
			setSelection( {
				...selections,
				[ newShipmentId ]: newShipment,
			} );
			setCurrentShipmentId( `${ newShipmentId }` );

			const selectedPackage = packages.getSelectedPackage();
			if ( selectedPackage ) {
				packages.setSelectedPackage( selectedPackage );
			}

			updateCustomsItems();
		};
		return (
			<TabPanel
				ref={ ref }
				selectOnMove={ true }
				className="shipment-tabs"
				tabs={ tabs() }
				initialTabName={ currentShipmentId }
				onSelect={ ( tabName ) => {
					/**
					 * storing the previous tab name to prevent jumping to a new tab
					 * when the user clicks on the "Edit shipments" tab
					 */
					if ( tabName === 'edit' ) {
						setStartSplitShipment( true );
					} else if ( tabName === 'new-shipment' ) {
						createShipmentForExtraLabel();
					} else {
						setCurrentShipmentId( tabName );
					}
				} }
				children={ () => (
					<ShipmentContent items={ shipments[ currentShipmentId ] } />
				) }
			/>
		);
	}
);
