import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Label } from 'types';

interface CommercialInvoiceProps {
	label?: Label;
}

export const CommercialInvoice = ( { label }: CommercialInvoiceProps ) => {
	const commercialInvoiceUrl = label?.commercialInvoiceUrl;
	return (
		commercialInvoiceUrl && (
			<ExternalLink href={ commercialInvoiceUrl }>
				{ __( 'Print customs form', 'woocommerce-shipping' ) }
			</ExternalLink>
		)
	);
};
