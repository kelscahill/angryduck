import upsLogo from './logos/ups.png';
import uspsLogo from './logos/usps.png';
import dhlLogo from './logos/dhlExpress.png';
import fedexLogo from './logos/fedex.png';

const carrierLogos = {
	ups: upsLogo,
	upsdap: upsLogo,
	usps: uspsLogo,
	dhl: dhlLogo,
	dhlexpress: dhlLogo,
	dhlecommerce: dhlLogo,
	dhlecommerceasia: dhlLogo,
	fedex: fedexLogo,
};

const sizeToPixels = {
	small: '24px',
	medium: '30px',
	null: '30px',
	big: '40px',
	xLarge: '54px',
};

/**
 *
 * @param {Object} props             - props
 * @param {string} props.carrier     - carrier name
 * @param {string} [props.size]      - small, medium, big
 * @param {string} [props.positionX] - CSS background position-x
 * @param {string} [props.positionY] - CSS background position-y
 * @return {JSX.Element} - Carrier icon
 */
export const CarrierIcon = ( {
	carrier,
	size = 'small',
	positionX = 'center',
	positionY = 'center',
} ) => {
	if ( ! carrier || ! carrierLogos[ carrier.toLowerCase() ] ) {
		return <span />;
	}

	const dimensions = sizeToPixels[ size ?? 'small' ];
	return (
		<div
			style={ {
				width: dimensions,
				maxWidth: dimensions,
				background: `url(${
					carrierLogos[ carrier.toLowerCase() ]
				}) no-repeat ${ positionX } ${ positionY }`,
				height: '100%',
				minHeight: dimensions,
				backgroundSize: 'contain',
			} }
			className="carrier-icon"
		></div>
	);
};
