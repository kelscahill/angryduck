import {
	__experimentalText as Text,
	CheckboxControl,
	Flex,
	Icon,
} from '@wordpress/components';
import { check } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { LABEL_RATE_OPTION } from 'data/constants';

export const RowExtras = ( {
	extrasText,
	signatureRequiredRate,
	adultSignatureRequiredRate,
	carbonNeutralRate,
	additionalHandlingRate,
	saturdayDeliveryRate,
	rate,
	formatAmount,
	selectedRateOptions,
	selectRateOption,
	setSelected,
	selected,
} ) => (
	<Flex direction="column" className="rate-extras">
		{ extrasText.map( ( text ) => (
			<Flex key={ text } justify="flex-start" gap={ 2 }>
				<Icon icon={ check } size={ 20 } />
				<Text key={ text } weight={ 400 }>
					{ text }
				</Text>
			</Flex>
		) ) }
		{ signatureRequiredRate && (
			<Flex>
				<CheckboxControl
					label={ sprintf(
						// translators: %s the cost of the additional service.
						__(
							'Signature Required ( +%s )',
							'woocommerce-shipping'
						),
						formatAmount( signatureRequiredRate.rate - rate.rate )
					) }
					onChange={ ( checked ) => {
						setSelected( signatureRequiredRate, rate )( checked );
						selectRateOption(
							LABEL_RATE_OPTION.SIGNATURE,
							checked ? 'yes' : 'no',
							signatureRequiredRate.rate - rate.rate
						);
					} }
					checked={
						signatureRequiredRate.rateId === selected?.rate?.rateId
					}
					// Opting into the new styles for margin bottom
					__nextHasNoMarginBottom={ true }
				/>
			</Flex>
		) }
		{ adultSignatureRequiredRate && (
			<Flex>
				<CheckboxControl
					label={ sprintf(
						// translators: %s the cost of the additional service.
						__(
							'Adult Signature Required ( +%s )',
							'woocommerce-shipping'
						),
						formatAmount(
							adultSignatureRequiredRate.rate - rate.rate
						)
					) }
					onChange={ ( checked ) => {
						setSelected(
							adultSignatureRequiredRate,
							rate
						)( checked );
						selectRateOption(
							LABEL_RATE_OPTION.SIGNATURE,
							checked ? 'adult' : 'no',
							adultSignatureRequiredRate.rate - rate.rate
						);
					} }
					checked={
						adultSignatureRequiredRate.rateId ===
						selected?.rate?.rateId
					}
					// Opting into the new styles for margin bottom
					__nextHasNoMarginBottom={ true }
				/>
			</Flex>
		) }
		{ carbonNeutralRate && (
			<Flex>
				<CheckboxControl
					label={ sprintf(
						// translators: %s the cost of the additional service.
						__( 'Carbon Neutral ( +%s )', 'woocommerce-shipping' ),
						formatAmount( carbonNeutralRate.rate - rate.rate )
					) }
					onChange={ ( checked ) => {
						selectRateOption(
							LABEL_RATE_OPTION.CARBON_NEUTRAL,
							checked,
							carbonNeutralRate.rate - rate.rate
						);
					} }
					checked={ Boolean(
						selectedRateOptions[ LABEL_RATE_OPTION.CARBON_NEUTRAL ]
					) }
					__nextHasNoMarginBottom={ true }
				/>
			</Flex>
		) }
		{ additionalHandlingRate && (
			<Flex>
				<CheckboxControl
					label={ sprintf(
						// translators: %s the cost of the additional service.
						__(
							'Additional Handling ( +%s )',
							'woocommerce-shipping'
						),
						formatAmount( additionalHandlingRate.rate - rate.rate )
					) }
					onChange={ ( checked ) => {
						selectRateOption(
							LABEL_RATE_OPTION.ADDITIONAL_HANDLING,
							checked,
							additionalHandlingRate.rate - rate.rate
						);
					} }
					checked={ Boolean(
						selectedRateOptions[
							LABEL_RATE_OPTION.ADDITIONAL_HANDLING
						]
					) }
					__nextHasNoMarginBottom={ true }
				/>
			</Flex>
		) }
		{ saturdayDeliveryRate && (
			<Flex>
				<CheckboxControl
					label={ sprintf(
						// translators: %s the cost of the additional service.
						__(
							'Saturday Delivery ( %s )',
							'woocommerce-shipping'
						),
						saturdayDeliveryRate.rate - rate.rate > 0
							? `+${ formatAmount(
									saturdayDeliveryRate.rate - rate.rate
							  ) }`
							: __( 'Free', 'woocommerce-shipping' )
					) }
					onChange={ ( checked ) =>
						selectRateOption(
							LABEL_RATE_OPTION.SATURDAY_DELIVERY,
							checked,
							saturdayDeliveryRate.rate - rate.rate
						)
					}
					checked={ Boolean(
						selectedRateOptions[
							LABEL_RATE_OPTION.SATURDAY_DELIVERY
						]
					) }
					__nextHasNoMarginBottom={ true }
				/>
			</Flex>
		) }
	</Flex>
);
