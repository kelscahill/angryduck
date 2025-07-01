import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';
import { Label, Rate } from 'types';
import { canRefundLabel } from 'utils';
import { RefundConfirmation } from './refund-confirmation';

interface RefundShipmentProps {
	label?: Label;
	selectedRate: { rate: Rate; parent: Rate | null } | null | undefined;
	isBusy: boolean;
	isDisabled: boolean;
}

export const RefundShipment = ( {
	label,
	selectedRate,
	isBusy,
	isDisabled,
}: RefundShipmentProps ) => {
	const [ isRefunding, setIsRefunding ] = useState( false );
	const toggleRefund = useCallback(
		( open: boolean ) => () => {
			setIsRefunding( open );
		},
		[ setIsRefunding ]
	);

	const caveats = selectedRate?.rate.caveats ?? [];

	if ( caveats.includes( 'non-refundable' ) ) {
		return <>{ __( 'Label is non-refundable', 'woocommerce-shipping' ) }</>;
	}

	if ( ! canRefundLabel( label ) ) {
		return null;
	}
	return (
		<>
			{ isRefunding && (
				<RefundConfirmation close={ toggleRefund( false ) } />
			) }
			<Button
				variant="tertiary"
				onClick={ toggleRefund( true ) }
				isBusy={ isBusy }
				aria-busy={ isBusy }
				disabled={ isDisabled }
				aria-disabled={ isDisabled }
			>
				{ __( 'Request refund', 'woocommerce-shipping' ) }
			</Button>
		</>
	);
};
