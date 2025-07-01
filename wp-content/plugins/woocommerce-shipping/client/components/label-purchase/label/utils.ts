import { __ } from '@wordpress/i18n';
import { PaperSize } from 'types';

export const getPaperSizes = ( country: string ): PaperSize[] => [
	...( [ 'US', 'CA', 'MX', 'DO' ].includes( country.toUpperCase() )
		? []
		: [
				{
					key: 'a4',
					name: __( 'A4', 'woocommerce-shipping' ),
					size: __( '210x297mm', 'woocommerce-shipping' ),
				},
		  ] ),
	{
		key: 'label',
		name: __( 'Label (4"x6")', 'woocommerce-shipping' ),
		size: __( '4"x6"', 'woocommerce-shipping' ),
	},
	{
		key: 'letter',
		name: __( 'Letter (8.5"x11")', 'woocommerce-shipping' ),
		size: __( '8.5"x11"', 'woocommerce-shipping' ),
	},
];

export const getPaperSizeWithKey = (
	paperSize: string,
	country = 'US'
): PaperSize | undefined =>
	getPaperSizes( country ).find( ( { key } ) => key === paperSize );
