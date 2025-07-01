import React from 'react';
import { useState } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { ConfirmModal } from 'components/confirm-modal';
import { Notice } from '@wordpress/components';
import { getRefundDuration } from 'utils';
import { useLabelPurchaseContext } from 'context/label-purchase';

interface RefundConfirmationProps {
	close: () => void;
}

export const RefundConfirmation = ( { close }: RefundConfirmationProps ) => {
	const {
		labels: {
			getCurrentShipmentLabel,
			hasMissingPurchase,
			refundLabel,
			isRefunding,
		},
		rates: { removeSelectedRate },
		shipment: { resetShipmentAndSelection },
		storeCurrency: { formatAmount },
	} = useLabelPurchaseContext();

	const [ error, setError ] = useState< string | null >( null );

	const label = getCurrentShipmentLabel();
	const refund = async () => {
		setError( null );
		try {
			await refundLabel();
			removeSelectedRate();
			if ( ! hasMissingPurchase() ) {
				resetShipmentAndSelection();
			}

			close();
		} catch ( err ) {
			setError(
				'message' in ( err as Error )
					? ( err as Error )?.message
					: __(
							'There was a problem requesting a refund, please try again later',
							'woocommerce-shipping'
					  )
			);
		}
	};
	return (
		<ConfirmModal
			title={ __(
				'Request a shipping label refund',
				'woocommerce-shipping'
			) }
			onClose={ close }
			acceptButton={ {
				text: sprintf(
					// translators: %s: refundable amount
					__( 'Refund label (-%s)', 'woocommerce-shipping' ),
					formatAmount( label?.refundableAmount ?? 0 )
				),
				onClick: refund,
				isBusy: isRefunding,
				disabled: isRefunding || error !== null || ! label,
			} }
			cancelButton={ {
				text: __( 'Cancel', 'woocommerce-shipping' ),
				onClick: close,
			} }
			hideFooter={ error !== null }
			modalProps={ { className: 'wcs-refund-confirmation' } }
		>
			{ label && (
				<>
					<p>
						{ sprintf(
							// translators: %s: number of days
							__(
								'Request a refund for your unused shipping label. The refund process for the shipping label will begin immediately and is typically completed within %s business days.',
								'woocommerce-shipping'
							),
							getRefundDuration( label )
						) }
					</p>
					<dl>
						{ Boolean( label.createdDate ) && (
							<>
								<dt>
									{ __(
										'Purchase date',
										'woocommerce-shipping'
									) }
								</dt>
								<dd>
									{ dateI18n(
										'M d, Y',
										new Date( label.createdDate ),
										false
									) }
								</dd>
							</>
						) }

						<dt>
							{ __(
								'Amount eligible for refund',
								'woocommerce-shipping'
							) }
						</dt>
						<dd>{ formatAmount( label.refundableAmount ) }</dd>
					</dl>
					<i className="description">
						{ __(
							'Please note that this refund request applies only to the unused shipping label and will not affect the order itself.',
							'woocommerce-shipping'
						) }
					</i>
				</>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</ConfirmModal>
	);
};
