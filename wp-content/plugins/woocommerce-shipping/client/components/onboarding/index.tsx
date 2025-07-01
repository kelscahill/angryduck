import React from 'react';
import Container from './container';
import Connect from './connect';
import {
	Card,
	CardBody,
	CardDivider,
	__experimentalHeading as Heading,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.scss';

interface ShippingSettingsProps {
	authReturnUrl: string;
	isCountrySupported: boolean;
	isCurrencySupported: boolean;
	storeCountryName: string;
	storeCurrency: string;
}

const Onboarding = ( {
	authReturnUrl,
	isCountrySupported,
	isCurrencySupported,
	storeCountryName,
	storeCurrency,
}: ShippingSettingsProps ) => {
	return (
		<Container>
			<Card
				className="wcshipping-onboarding"
				isBorderless={ true }
				size="large"
			>
				<CardBody className="wcshipping-onboarding__content">
					<Heading
						className="wcshipping-onboarding__title"
						level={ 2 }
					>
						{ __(
							'Connect your store to WordPress.com',
							'woocommerce-shipping'
						) }
					</Heading>

					<CardDivider />

					<p>
						{ __(
							'Save time and money with WooCommerce Shipping by printing discounted shipping labels with just a few clicks from your WooCommerce dashboard.',
							'woocommerce-shipping'
						) }
					</p>
					<p>
						{ __(
							'With WooCommerce Shipping, critical services are hosted on Automattic’s best-in-class infrastructure, rather than relying on your store’s hosting. That means your store will be more stable and faster.',
							'woocommerce-shipping'
						) }
					</p>

					<Connect
						authReturnUrl={ authReturnUrl }
						isCountrySupported={ isCountrySupported }
						isCurrencySupported={ isCurrencySupported }
						countryName={ storeCountryName }
						currency={ storeCurrency }
					/>
				</CardBody>
			</Card>
		</Container>
	);
};

export default Onboarding;
