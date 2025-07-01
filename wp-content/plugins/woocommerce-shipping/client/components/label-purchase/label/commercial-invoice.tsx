import React from 'react';
import { Icon } from '@wordpress/components';
import { external } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { Label } from 'types';

interface CommercialInvoiceProps {
	label?: Label;
}

export const CommercialInvoice = ( { label }: CommercialInvoiceProps ) => {
	const commercialInvoiceUrl = label?.commercialInvoiceUrl;
	return (
		commercialInvoiceUrl && (
			<Link
				href={ commercialInvoiceUrl }
				type="external"
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Print customs form', 'woocommerce-shipping' ) }
				<Icon icon={ external } />
			</Link>
		)
	);
};
