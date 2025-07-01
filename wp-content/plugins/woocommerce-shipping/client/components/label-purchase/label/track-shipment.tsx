import { external } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { Link } from '@woocommerce/components';
import { Label } from 'types';
import { trackingUrls } from './constants';
import { Conditional } from '../../HOC';

interface TrackShipmentProps {
	label?: Label;
}

export const TrackShipment = Conditional(
	( { label }: TrackShipmentProps ) => {
		const trackingUrl =
			label?.carrierId && label?.tracking
				? trackingUrls[ label.carrierId ]?.( label.tracking )
				: null;
		const render =
			label &&
			Boolean( label.tracking ) &&
			Boolean( label.carrierId ) &&
			Boolean( trackingUrl );
		return {
			render,
			props: {
				trackingUrl,
			},
		};
	},
	// @ts-expect-error // Conditional is written in js
	( { trackingUrl }: { isBusy: boolean; trackingUrl: string } ) => (
		<Link
			href={ trackingUrl }
			type="external"
			target="_blank"
			rel="noopener noreferrer"
		>
			{ __( 'Track shipment', 'woocommerce-shipping' ) }
			<Icon icon={ external } />
		</Link>
	),
	() => null
);
