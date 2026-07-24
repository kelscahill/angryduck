import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { Label } from 'types';

interface CommercialInvoiceProps {
	label?: Label;
	className?: string;
	children?: ReactNode;
}

export const CommercialInvoice = ( {
	label,
	className,
	children,
}: CommercialInvoiceProps ) => {
	const commercialInvoiceUrl = label?.commercialInvoiceUrl;
	return (
		commercialInvoiceUrl && (
			<ExternalLink href={ commercialInvoiceUrl } className={ className }>
				{ children ??
					__( 'Print customs form', 'woocommerce-shipping' ) }
			</ExternalLink>
		)
	);
};
