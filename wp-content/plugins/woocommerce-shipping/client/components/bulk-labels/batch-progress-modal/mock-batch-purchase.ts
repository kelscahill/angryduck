/**
 * TEMPORARY: delete when WOOSHIP-2137 service wiring lands.
 *
 * Client-side wiring stub for the batch shipment-create flow.
 *
 * The PHP endpoint at `POST /wcshipping/v1/label/purchase/batch`
 * already exists (see `LabelPurchaseRESTController::purchase_labels_batch`).
 * What this file mocks is the *client-side* wiring: the rate-quote and
 * service-layer plumbing that will eventually feed each shipment into
 * `apiFetch` and stream per-order outcomes into the modal.
 *
 * This stub:
 *   - resolves each order on its own short timer so the progress UI
 *     advances order-by-order instead of flipping the whole batch at
 *     once, and
 *   - returns the same response shape the real endpoint emits, keyed
 *     `order_<id>` and carrying either `{ labels: [...], success: true }`
 *     or `{ error: { code, message } }` entries.
 *
 * Once the service-layer ticket lands, the consumer will swap this for
 * an `apiFetch` POST + `AbortController.signal` plumbing (already
 * threaded through). The on-the-wire `BatchPurchaseResponse` shape
 * stays identical, so the consumer's parser doesn't change.
 */

import type { BulkPurchaseOrder } from 'data/bulk-labels';
import type { BatchPurchaseEntry, BatchPurchaseResponse } from './types';

/**
 * Resolution of the mock dispatch. Either every order settled (or was
 * cancelled with the partial response we have so far), or the dispatch
 * itself blew up before any settle could happen. The `transport_error`
 * branch is what the real `apiFetch` integration will produce on a
 * network drop or 5xx response; the mock surfaces it via the
 * `?wcshipping_bulk_mock=transport_error` switch so the consumer's
 * catch path is exercisable today.
 */
export type MockBatchOutcome =
	| { kind: 'settled'; response: BatchPurchaseResponse }
	| { kind: 'transport_error'; error: Error };

export interface MockBatchPurchaseHandle {
	cancel: () => void;
	/**
	 * Resolves when every order has settled (or the run was cancelled).
	 * Rejects when the dispatch itself fails (transport error). The
	 * consumer awaits this in its try/catch so any synchronous throw
	 * from the future `apiFetch` call lands in the catch instead of
	 * escaping the effect uncaught.
	 */
	promise: Promise< MockBatchOutcome >;
}

export interface RunMockBatchPurchaseArgs {
	orders: BulkPurchaseOrder[];
	/**
	 * Fires once per order as it settles. The wire shape is the same
	 * `order_<id>` entry the consumer would parse out of the real
	 * batch response.
	 */
	onOrderSettled: ( orderId: number, entry: BatchPurchaseEntry ) => void;
	/**
	 * Optional forced outcome per order ID. Used by the
	 * `?wcshipping_bulk_mock=` query-string switch in the entrypoint to
	 * let merchants exercise the "all-failed", "mixed", and
	 * "transport-error" paths without touching code.
	 *
	 * Returning `'transport_error'` here makes the dispatch itself
	 * reject with a synthetic transport error before any per-order
	 * settle happens, so the modal's catch path runs.
	 */
	forceOutcome?: (
		orderId: number
	) => 'success' | 'failure' | 'transport_error' | null;
	/**
	 * AbortSignal for the modal-close cancel path. Mock ignores the
	 * signal beyond aborting the staggered timeouts so the real
	 * integration can swap in `apiFetch({ signal })` without changing
	 * the consumer.
	 */
	signal?: AbortSignal;
}

const FAILURE_TEMPLATES: readonly { code: string; message: string }[] = [
	{
		code: 'address_validation_failed',
		message:
			'Destination address could not be validated. Verify the recipient address and try again.',
	},
	{
		code: 'rate_unavailable',
		message:
			'The selected service is no longer available for this shipment. Pick a different rate and retry.',
	},
	{
		code: 'package_dimensions_invalid',
		message:
			'Package dimensions are missing or invalid for this carrier. Update the package and retry.',
	},
];

const pickFailureTemplate = ( orderId: number ) =>
	FAILURE_TEMPLATES[ orderId % FAILURE_TEMPLATES.length ];

/**
 * Decide whether a given mock order should "succeed" or "fail" when no
 * explicit override is provided. Orders whose ID ends in 3 or 7 fail by
 * default so a typical selection produces a mix.
 */
const defaultOutcome = ( orderId: number ): 'success' | 'failure' => {
	const lastDigit = Math.abs( orderId ) % 10;
	return lastDigit === 3 || lastDigit === 7 ? 'failure' : 'success';
};

const buildEntry = (
	order: BulkPurchaseOrder,
	outcome: 'success' | 'failure'
): BatchPurchaseEntry => {
	if ( outcome === 'success' ) {
		return {
			labels: [
				{
					label_id: 1000000 + order.order_id,
					fulfillment_id: 2000000 + order.order_id,
					rate: order.cost,
				},
			],
			success: true,
		};
	}

	const template = pickFailureTemplate( order.order_id );
	return {
		error: {
			code: template.code,
			message: template.message,
		},
	};
};

/**
 * Synthetic error used by the `transport_error` force-outcome path so
 * the consumer's catch handler can capture a message with a known shape.
 */
class MockBatchTransportError extends Error {
	constructor() {
		super( 'Mock transport error (forced via forceOutcome).' );
		this.name = 'MockBatchTransportError';
	}
}

export const runMockBatchPurchase = ( {
	orders,
	onOrderSettled,
	forceOutcome,
	signal,
}: RunMockBatchPurchaseArgs ): MockBatchPurchaseHandle => {
	let cancelled = false;
	let finished = false;
	const timers: ReturnType< typeof setTimeout >[] = [];
	const response: BatchPurchaseResponse = {};
	let remaining = orders.length;

	let resolvePromise: ( ( value: MockBatchOutcome ) => void ) | null = null;
	let rejectPromise: ( ( reason: Error ) => void ) | null = null;
	const promise = new Promise< MockBatchOutcome >( ( resolve, reject ) => {
		resolvePromise = resolve;
		rejectPromise = reject;
	} );

	// Listener wired below if a `signal` is provided. Store the handle
	// so terminal paths can remove it explicitly: `{ once: true }` only
	// auto-removes after the listener fires, so a run that finishes
	// without ever aborting would keep the closure alive on the
	// AbortSignal until the controller itself is GC'd. Removing it in
	// `finish`/`fail`/`cancel` keeps the listener lifetime bounded by
	// the run.
	let abortHandler: ( () => void ) | null = null;
	const removeAbortListener = () => {
		if ( signal && abortHandler ) {
			signal.removeEventListener( 'abort', abortHandler );
			abortHandler = null;
		}
	};

	const finish = () => {
		if ( finished ) {
			return;
		}
		finished = true;
		removeAbortListener();
		resolvePromise?.( { kind: 'settled', response } );
	};

	const fail = ( err: Error ) => {
		if ( finished ) {
			return;
		}
		finished = true;
		timers.forEach( clearTimeout );
		removeAbortListener();
		rejectPromise?.( err );
	};

	const cancel = () => {
		if ( cancelled || finished ) {
			return;
		}
		cancelled = true;
		timers.forEach( clearTimeout );
		// Hand the partial response back so the consumer's results phase
		// gets the orders that did settle. Any rows still pending at
		// this point stay pending in the consumer's row list and will
		// be promoted by the consumer's interrupted-state logic.
		finish();
	};

	// Surface the forced transport-error outcome as a synchronous
	// rejection so the consumer's catch runs without needing to wait
	// for every order timer to fire. Take the first order id and ask
	// the override; it's good enough for the mock since the merchant
	// only sets `?wcshipping_bulk_mock=transport_error` at the URL
	// level (it applies to the whole batch).
	const firstId = orders[ 0 ]?.order_id;
	if (
		typeof firstId === 'number' &&
		forceOutcome?.( firstId ) === 'transport_error'
	) {
		// Defer to the next tick so the consumer has a chance to
		// register the await before the rejection lands.
		const timer = setTimeout( () => {
			fail( new MockBatchTransportError() );
		}, 50 );
		timers.push( timer );
		return { cancel, promise };
	}

	if ( signal ) {
		if ( signal.aborted ) {
			cancelled = true;
			finish();
			return { cancel, promise };
		}
		abortHandler = () => cancel();
		signal.addEventListener( 'abort', abortHandler, { once: true } );
	}

	if ( remaining === 0 ) {
		finish();
		return { cancel, promise };
	}

	orders.forEach( ( order, index ) => {
		const delay = 500 + index * 350;
		const timer = setTimeout( () => {
			if ( cancelled ) {
				return;
			}

			const forced = forceOutcome?.( order.order_id );
			// `transport_error` is a batch-level outcome (handled near
			// the top of the function before we get here). If a per-order
			// `forceOutcome` returns it after dispatch has started, we
			// fall through to the default success/failure rule rather
			// than producing a synthetic per-row error. Callers who want
			// a specific row to fail should return `'failure'` explicitly.
			const outcome =
				forced === 'transport_error' ||
				forced === undefined ||
				forced === null
					? defaultOutcome( order.order_id )
					: forced;
			const entry = buildEntry( order, outcome );
			response[ `order_${ order.order_id }` ] = entry;
			onOrderSettled( order.order_id, entry );

			remaining -= 1;
			if ( remaining === 0 ) {
				finish();
			}
		}, delay );

		timers.push( timer );
	} );

	return { cancel, promise };
};
