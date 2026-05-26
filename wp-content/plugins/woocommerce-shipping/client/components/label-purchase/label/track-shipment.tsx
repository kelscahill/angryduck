import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
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
		<ExternalLink href={ trackingUrl }>
			{ __( 'Track shipment', 'woocommerce-shipping' ) }
		</ExternalLink>
	),
	() => null
);
