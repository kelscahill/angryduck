import { __ } from '@wordpress/i18n';
import { createDeferred } from 'utils';
import { UPSDAPTos } from 'components/carrier';
import {
	CarrierStrategy,
	CarrierStrategyProps,
	LabelPurchaseError,
} from 'types';
import { select } from '@wordpress/data';
import { carrierStrategyStore } from 'data/carrier-strategy';

export const UPSDAPStrategy = ( {
	deferred,
	show,
	setDeferred,
	setShow,
	shipmentOrigin,
}: CarrierStrategyProps ): CarrierStrategy => {
	const onClose = () => {
		setShow( false );
		deferred?.reject( {
			cause: 'carrier_error',
			message: [
				__(
					'You must agree to the UPS® Terms and Conditions to purchase a UPS® label.',
					'woocommerce-shipping'
				),
			],
		} as LabelPurchaseError );
	};

	const onConfirm = ( confirm: boolean ) => {
		setShow( false );
		deferred?.resolve( confirm );
	};

	return {
		canPurchase: () => {
			const { hasAgreedToTos } = select(
				carrierStrategyStore
			).getUPSDAPCarrierStrategyForAddressId( shipmentOrigin.id );
			const newDeferred = createDeferred< boolean, string >();
			setDeferred( newDeferred );
			if ( hasAgreedToTos ) {
				newDeferred?.resolve( true );
				return newDeferred.promise;
			}

			setShow( true );
			return newDeferred?.promise;
		},
		render: () =>
			show && (
				<UPSDAPTos
					close={ onClose }
					confirm={ onConfirm }
					shipmentOrigin={ shipmentOrigin }
					acceptedVersions={ [] } // The strategy is not used anymore, but I'm adding it here to avoid breaking changes.
				/>
			),
	};
};
