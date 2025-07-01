import { isEmpty } from 'lodash';
import {
	createSubItemOfCount,
	getParentIdFromSubItemId,
	getSubItemId,
	getSubItems,
	isSubItem,
} from 'utils/order-items';
import { getConfig } from 'utils/config';

const removeShipmentsWithNoMatchingItems = ( shipments, orderItems ) =>
	Object.entries( shipments ).reduce( ( acc, [ key, shipmentItems ] ) => {
		const items = shipmentItems.filter( ( { id, parentId } ) =>
			orderItems.find( ( orderItem ) =>
				[ id, parentId ].includes( orderItem.id )
			)
		);

		return items.length
			? {
					...acc,
					[ key ]: items,
			  }
			: acc;
	}, {} );

export const getCurrentOrderShipments = ( config = getConfig() ) => {
	const {
		shipments,
		order: { line_items: orderItems },
	} = config;
	let storedShipments = {};
	try {
		storedShipments = JSON.parse( shipments );
	} catch ( e ) {
		// eslint-disable-next-line no-console
		console.warn( e );
		// Add more logging here if needed
	}

	if ( isEmpty( storedShipments ) ) {
		return {
			0: orderItems.map( ( orderItem ) => ( {
				...orderItem,
				subItems: getSubItems( orderItem ),
			} ) ),
		};
	}

	// remove items from shipments that are not in orderItems anymore
	const shipmentsFilteredToAvailableOrderItems =
		removeShipmentsWithNoMatchingItems( storedShipments, orderItems );

	// create shipments with items as objects
	const shipmentsAugmentedWithDetails = Object.entries(
		shipmentsFilteredToAvailableOrderItems
	).reduce(
		( acc, [ key, shipmentItems ] ) => ( {
			...acc,
			[ key ]: shipmentItems.map( ( shipmentItem ) => {
				const item = orderItems.find(
					( orderItem ) =>
						orderItem.id ===
						getParentIdFromSubItemId( shipmentItem.id )
				);
				if ( isSubItem( { id: shipmentItem.id } ) ) {
					return {
						...item,
						id: shipmentItem.id, // setting id from shipmentItem to keep subItem id intact
						subItems: [],
						quantity: 1,
						parentId: getParentIdFromSubItemId( item.id ),
					};
				}

				const subItems = shipmentItem.subItems?.map( ( { id } ) => ( {
					...orderItems.find(
						( orderItem ) =>
							orderItem.id === getParentIdFromSubItemId( id )
					),
					id,
					parentId: getParentIdFromSubItemId( id ),
					subItems: [],
					quantity: 1,
				} ) );

				return {
					...item,
					subItems,
					quantity: Math.max( 1, subItems?.length ),
				};
			} ),
		} ),
		{}
	);

	const itemsFromShipments = Object.values(
		shipmentsAugmentedWithDetails
	).flat();
	const itemsNotInShipments = orderItems.filter(
		( orderItem ) =>
			! itemsFromShipments.find( ( item ) => item.id === orderItem.id )
	);

	shipmentsAugmentedWithDetails[ '0' ] = [
		/**
		 * Make sure a malformed shipments object doesn't throw an error
		 * if shipmentsAugmentedWithDetails the returned value will look like the early return when isEmpty( storedShipments ) is true
		 */
		...( shipmentsAugmentedWithDetails[ '0' ] ?? [] ),
		...itemsNotInShipments.map( ( item ) => ( {
			...item,
			subItems: getSubItems( item ),
		} ) ),
	];

	return shipmentsAugmentedWithDetails;
};

export const getNoneSelectedShipmentItems = ( shipments, selections ) =>
	Object.entries( shipments ).reduce(
		( acc, [ key, shipmentItems ] ) => ( {
			...acc,
			[ key ]: shipmentItems
				.filter(
					( { id } ) =>
						! selections[ key ]?.find(
							( { id: itemId } ) => id === itemId
						)
				)
				.filter( ( { id, quantity } ) => {
					if ( selections[ key ] ) {
						const subItems = selections[ key ].filter(
							( maybeSubItems ) =>
								isSubItem( maybeSubItems ) &&
								getParentIdFromSubItemId( maybeSubItems.id ) ===
									id
						);

						return subItems.length < quantity;
					}

					return true;
				} )
				.map( ( item ) => {
					const subItems = item.subItems.filter(
						( { id: subItemId } ) =>
							! selections[ key ]?.find(
								( { id } ) => id === subItemId
							)
					);
					return {
						...item,
						subItems,
						quantity: Math.max(
							1,
							Math.min( item.subItems.length, subItems.length )
						),
					};
				} ),
		} ),
		{}
	);

/**
 * Merges subItems with their parent items if a subItem has a sibling in the same shipment
 *
 * @param {Object} shipments
 * @return {Object} normalizedShipments
 */
export const normalizeSubItems = ( shipments ) => {
	const allItems = Object.values( shipments ).flat();
	return Object.entries( shipments ).reduce(
		( acc, [ key, shipmentItems ] ) => {
			const items = shipmentItems.reduce( ( mergeAcc, item ) => {
				if ( isSubItem( item ) ) {
					const parentId = getParentIdFromSubItemId( item.id );
					const siblings = shipmentItems.filter( ( { id } ) =>
						isSubItem( { id } )
							? getParentIdFromSubItemId( id ) === parentId
							: false
					);

					let parent = shipmentItems.find(
						( { id } ) => id === parentId
					);

					let subItems = siblings;

					if ( ! parent ) {
						parent = allItems.find( ( { id } ) => id === parentId );
					} else {
						const subItemsFromParent =
							parent.subItems.length > 0
								? parent.subItems
								: createSubItemOfCount(
										parent.quantity,
										parent
								  );
						subItems = [ ...subItemsFromParent, ...siblings ].map(
							( sI, index ) => ( {
								...sI,
								id: getSubItemId( parent, index ),
							} )
						);
					}

					return [
						...mergeAcc.filter( ( { id } ) => id !== parentId ),
						{
							...( parent || item ),
							id: parentId,
							subItems,
							quantity: subItems.length,
						},
					];
				}

				const existingItem = mergeAcc.find(
					( { id } ) => id === item.id
				);
				const quantity =
					item.quantity + ( existingItem?.quantity ?? 0 );
				return [
					...mergeAcc.filter( ( { id } ) => id !== item.id ),
					{
						...item,
						quantity,
						subItems: getSubItems( {
							...( existingItem || item ),
							quantity,
							parentId: item.id,
						} ),
					},
				];
			}, [] );

			return {
				...acc,
				[ key ]: items,
			};
		},
		{}
	);
};
export const removeEmptyShipments = ( shipments ) => {
	const { normalizedIndices } = Object.entries( shipments ).reduce(
		( acc, [ , shipmentItems ] ) => {
			if ( shipmentItems.length > 0 ) {
				acc.normalizedIndices[ acc.previousIndex ] = shipmentItems;
				acc.previousIndex += 1;
			}

			return acc;
		},
		{
			normalizedIndices: {},
			previousIndex: 0,
		}
	);

	return normalizedIndices;
};
/**
 * Normalizes shipment indices to be sequential by removing empty shipments
 * also normalizes subItems by merging them with their parent items if
 * a subItem has a sibling in the same shipment
 *
 * @param {Object} shipments
 * @return {Object} normalizedShipments
 */
export const normalizeShipments = ( shipments ) =>
	normalizeSubItems( removeEmptyShipments( shipments ) );
