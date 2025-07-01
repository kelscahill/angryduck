import React, { MouseEvent, MouseEventHandler } from 'react';
import { useSelect } from '@wordpress/data';
import { addressStore } from 'data/address';
import { Button, Icon } from '@wordpress/components';
import { check, info } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import './styles.scss';
import { AddressTypes } from 'types';

interface AddressVerifiedIconProps {
	isVerified: boolean;
	isFormChanged?: boolean;
	isFormValid?: boolean;
	errorMessage?: string;
	onClick?: MouseEventHandler;
	message?: string;
	addressType: AddressTypes;
}

export const AddressVerifiedIcon = ( {
	isVerified,
	isFormChanged = false,
	isFormValid = true,
	errorMessage = __( 'Unverified address', 'woocommerce-shipping' ),
	onClick,
	message = __( 'Address verified', 'woocommerce-shipping' ),
	addressType,
}: AddressVerifiedIconProps ) => {
	const isBusyVerifying = useSelect(
		( select ) =>
			select( addressStore ).getIsAddressVerificationInProgress(
				addressType
			),
		[ addressType ]
	);

	if ( isVerified && ! isFormChanged && isFormValid && ! isBusyVerifying ) {
		return (
			<span className="verification">
				<Icon icon={ check } size={ 16 } />
				{ message }
			</span>
		);
	}

	if ( isBusyVerifying ) {
		return (
			<span className="verification in-progress">
				<Icon icon={ check } size={ 16 } />
				{ __( 'Verifying address…', 'woocommerce-shipping' ) }
			</span>
		);
	}

	return (
		<span className="verification not-verified">
			{ /* If onclick is provided, it will be a button */ }
			{ onClick ? (
				<Button
					variant="link"
					icon={ info }
					onClick={ ( e: MouseEvent ) => onClick?.( e ) }
					title={ errorMessage }
				>
					{ errorMessage }
				</Button>
			) : (
				<>
					{ /* based on how the svg is implemented, 22 yields ~16 */ }
					<Icon icon={ info } size={ 22 } />
					{ errorMessage }
				</>
			) }
		</span>
	);
};
