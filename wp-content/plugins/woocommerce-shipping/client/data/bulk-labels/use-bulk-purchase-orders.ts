import { useMemo } from '@wordpress/element';
import { useEntityRecords, store as coreDataStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	buildBatchSummary,
	buildBulkPurchaseOrders,
	findLargestAddressGroup,
} from './display';
import { ORDERS_SHIPPING_CONTEXT_ENTITY } from './constants';
import { useAutoAssignedPackages } from './use-auto-assigned-packages';
import type {
	AddressGrouping,
	BatchSummary,
	BulkPurchaseOrder,
	ManualPackageSelections,
	OrderShippingContextRecord,
} from './types';

const EMPTY_MANUAL_SELECTIONS: ManualPackageSelections = {};

interface UseBulkPurchaseOrdersResult {
	isResolving: boolean;
	error: Error | null;
	records: OrderShippingContextRecord[];
	orders: BulkPurchaseOrder[];
	summary: BatchSummary;
	grouping: AddressGrouping | null;
}

const EMPTY_RECORDS: OrderShippingContextRecord[] = [];

/**
 * Reactive hook that pulls orders shipping context via core-data's
 * useEntityRecords, then layers on the WOOSHIP-2133 fields the
 * endpoint doesn't yet provide (service, cost, status, note) so the
 * modal can render every column. Components reading this hook won't
 * have to change once those fields land for real.
 */
export const useBulkPurchaseOrders = (
	orderIds: number[],
	manualSelections: ManualPackageSelections = EMPTY_MANUAL_SELECTIONS
): UseBulkPurchaseOrdersResult => {
	// Stringify so a fresh array reference with the same IDs doesn't
	// re-trigger the fetch.
	const idsKey = orderIds.join( ',' );
	const hasIds = orderIds.length > 0;

	// Pass an empty query when there are no IDs. core-data will resolve
	// with an empty record set rather than leaving the resolver pending
	// forever (which would happen if we passed `null`).
	const query = useMemo(
		() => ( hasIds ? { ids: orderIds } : {} ),
		// eslint-disable-next-line react-hooks/exhaustive-deps -- key covers the array contents
		[ idsKey, hasIds ]
	);

	const { records, hasResolved } =
		useEntityRecords< OrderShippingContextRecord >(
			ORDERS_SHIPPING_CONTEXT_ENTITY.kind,
			ORDERS_SHIPPING_CONTEXT_ENTITY.name,
			query
		);

	// Read the real resolver error (cap exceeded, network failure, etc.)
	// rather than a generic 'ERROR' status string. The modal needs the
	// message and the code to render something useful.
	const error = useSelect(
		( select ) => {
			if ( ! hasIds ) {
				return null;
			}
			const err = (
				select as ( store: typeof coreDataStore ) => {
					getResolutionError: (
						name: string,
						args: unknown[]
					) => Error | unknown;
				}
			 )( coreDataStore ).getResolutionError( 'getEntityRecords', [
				ORDERS_SHIPPING_CONTEXT_ENTITY.kind,
				ORDERS_SHIPPING_CONTEXT_ENTITY.name,
				query,
			] );
			return err instanceof Error ? err : null;
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps -- key covers the array contents
		[ idsKey, hasIds ]
	);

	const safeRecords = useMemo( () => records ?? EMPTY_RECORDS, [ records ] );
	const usableRecords = useMemo(
		() => safeRecords.filter( ( record ) => ! record.error ),
		[ safeRecords ]
	);

	// Only ask the box-packer for orders that actually loaded — orders that
	// errored out of the shipping-context fetch are skipped entirely so we
	// don't waste a batch slot on rows the modal will never render.
	const assignableOrderIds = useMemo(
		() => usableRecords.map( ( record ) => record.order_id ),
		[ usableRecords ]
	);

	const {
		isResolving: isAutoAssignResolving,
		results: autoAssignedPackages,
		error: autoAssignError,
	} = useAutoAssignedPackages( assignableOrderIds );

	const orders = useMemo(
		() =>
			buildBulkPurchaseOrders(
				usableRecords,
				autoAssignedPackages,
				manualSelections
			),
		[ usableRecords, autoAssignedPackages, manualSelections ]
	);
	const summary = useMemo( () => buildBatchSummary( orders ), [ orders ] );
	const grouping = useMemo(
		() => findLargestAddressGroup( usableRecords ),
		[ usableRecords ]
	);

	return {
		// Hold the modal in its loading state until both the shipping-context
		// fetch and the box-packer auto-assign call have settled, so the
		// table doesn't flash placeholder packages before the suggestion
		// arrives.
		isResolving: hasIds && ( ! hasResolved || isAutoAssignResolving ),
		// Surface a failed auto-assign as a modal-level error rather than
		// letting rows fall back to placeholder packages and read as
		// "ready". The shipping-context error takes precedence since
		// without records there's nothing to suggest packages for.
		error: error ?? autoAssignError,
		records: safeRecords,
		orders,
		summary,
		grouping,
	};
};
