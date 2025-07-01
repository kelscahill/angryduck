import React from 'react';
import { Link } from '@woocommerce/components';
import {
	__experimentalText as Text,
	Button,
	Icon,
	Notice,
} from '@wordpress/components';
import {
	createInterpolateElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { WPCOMConnectionStore } from 'data/wpcom-connection';
import { recordEvent } from 'utils/tracks';

import './style.scss';
import { StoreSettingsRequirements } from '../store-settings-requirements';

interface ContainerProps {
	authReturnUrl: string;
	countryName: string;
	currency: string;
	isCountrySupported: boolean;
	isCurrencySupported: boolean;
}

const Connect: React.FC< ContainerProps > = ( {
	authReturnUrl,
	countryName,
	currency,
	isCountrySupported,
	isCurrencySupported,
} ) => {
	const [ isConnecting, setIsConnecting ] = useState( false );
	const canConnect = ! ( isCountrySupported && isCurrencySupported );
	const { redirectUrl, error } = select( WPCOMConnectionStore ).getState();

	useEffect( () => {
		if ( redirectUrl ) {
			window.location.href = redirectUrl;
		}
	}, [ redirectUrl ] );

	useEffect( () => {
		recordEvent( 'onboarding_connect_component_viewed', {
			can_connect: canConnect,
			is_country_supported: isCountrySupported,
			is_currency_supported: isCurrencySupported,
		} );
	}, [ canConnect, isCountrySupported, isCurrencySupported ] );

	useEffect( () => {
		if ( error ) {
			recordEvent( 'onboarding_connect_component_connect_error_viewed', {
				error,
			} );
		}
	}, [ error ] );

	const handleOnClick = useCallback( async () => {
		setIsConnecting( true );

		recordEvent( 'onboarding_connect_component_connect_button_clicked' );

		await dispatch( WPCOMConnectionStore ).createConnection( {
			payload: {
				returnUrl: authReturnUrl,
				source: 'onboarding-connect-button',
			},
		} );

		setIsConnecting( false );
	}, [ authReturnUrl ] );

	return (
		<div className="wcshipping-onboarding-connect">
			{ ( ! isCountrySupported || ! isCurrencySupported ) && (
				<StoreSettingsRequirements
					isCountrySupported={ isCountrySupported }
					isCurrencySupported={ isCurrencySupported }
					countryName={ countryName }
					currency={ currency }
				/>
			) }

			<Button
				className="wcshipping-onboarding-connect__button"
				variant="primary"
				disabled={ canConnect || isConnecting }
				onClick={ handleOnClick }
				isBusy={ isConnecting || !! redirectUrl }
			>
				{ __( 'Connect your store', 'woocommerce-shipping' ) }
				<Icon icon="external" />
			</Button>

			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="wcshipping-onboarding-connect__error"
				>
					{ error }
				</Notice>
			) }

			<Text
				className="wcshipping-onboarding-connect__footnote"
				size="footnote"
			>
				{ createInterpolateElement(
					__(
						'By clicking Connect your store, you agree to the <tos>Terms of Service<icon /></tos> and have read our <privacy_policy>Privacy Policy<icon /></privacy_policy>.',
						'woocommerce-shipping'
					),
					{
						tos: (
							<Link
								href="https://wordpress.com/tos/"
								target="_blank"
								rel="noopener noreferrer"
								type="external"
							>
								{ ' ' }
							</Link>
						),
						privacy_policy: (
							<Link
								href="https://automattic.com/privacy/"
								target="_blank"
								rel="noopener noreferrer"
								type="external"
							>
								{ ' ' }
							</Link>
						),
						icon: <Icon icon="external" />,
					}
				) }
			</Text>
		</div>
	);
};

export default Connect;
