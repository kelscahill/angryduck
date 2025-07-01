import React from 'react';
import { useState } from '@wordpress/element';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Button,
	Flex,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Destination, OriginAddress } from 'types';
import { addressToString } from 'utils';
import { withBoundary } from 'components/HOC';

interface AddressSuggestionProps {
	originalAddress: OriginAddress | Destination;
	normalizedAddress: OriginAddress | Destination;
	editAddress: () => void;
	confirmAddress: ( arg: boolean ) => void;
	errors: Record< string, string >;
}

export const AddressSuggestion = withBoundary(
	( {
		originalAddress,
		normalizedAddress,
		editAddress,
		confirmAddress,
		errors,
	}: AddressSuggestionProps ) => {
		const [ selectedAddress, setSelectedAddress ] = useState(
			normalizedAddress ? 'normalized' : 'original'
		);
		const notice = normalizedAddress
			? __(
					'We have slightly modified the address entered. If correct, please use the suggested address to ensure accurate delivery.',
					'woocommerce-shipping'
			  )
			: __(
					'We were unable to verify the address entered. It may still be a valid address, but we cannot ensure accurate delivery to this address as entered. Please confirm if you would like to continue with the address as entered or return to edit the address.',
					'woocommerce-shipping'
			  );
		const confirmButtonMessage = normalizedAddress
			? __( 'Use selected address', 'woocommerce-shipping' )
			: __( 'Confirm unverified address', 'woocommerce-shipping' );
		return (
			<div>
				{ errors.general && (
					<Notice status="error" isDismissible={ false }>
						{ errors.general }
					</Notice>
				) }
				<p>{ notice }</p>
				<ToggleGroupControl
					// @ts-ignore
					onChange={ setSelectedAddress }
					value={ selectedAddress }
					justify="space-between"
					gap={ 4 }
				>
					<Flex direction="column" gap={ 2 }>
						<strong>
							{ __( 'What you entered', 'woocommerce-shipping' ) }
						</strong>
						<ToggleGroupControlOption
							value="original"
							label={ addressToString( originalAddress ) }
						></ToggleGroupControlOption>
					</Flex>
					<Flex direction="column" gap={ 2 }>
						{ normalizedAddress && (
							<>
								<strong>
									{ __(
										'Suggested',
										'woocommerce-shipping'
									) }
								</strong>
								<ToggleGroupControlOption
									value="normalized"
									label={ addressToString(
										normalizedAddress
									) }
								></ToggleGroupControlOption>
							</>
						) }
					</Flex>
				</ToggleGroupControl>
				<Flex justify="flex-end" as="footer">
					<Button onClick={ editAddress } variant="tertiary">
						{ __( 'Edit address', 'woocommerce-shipping' ) }
					</Button>
					<Button
						onClick={ () =>
							confirmAddress( selectedAddress === 'normalized' )
						}
						variant="primary"
					>
						{ confirmButtonMessage }
					</Button>
				</Flex>
			</div>
		);
	}
)( 'AddressSuggestion' );
