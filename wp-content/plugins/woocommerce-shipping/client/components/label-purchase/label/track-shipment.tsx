import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import type { ComponentType, ReactNode } from 'react';
import { Label } from 'types';
import { trackingUrls } from './constants';
import { Conditional } from '../../HOC';

interface TrackShipmentProps {
	label?: Label;
	className?: string;
	children?: ReactNode;
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
	( {
		trackingUrl,
		className,
		children,
	}: {
		isBusy: boolean;
		trackingUrl: string;
		className?: string;
		children?: ReactNode;
	} ) => (
		<ExternalLink href={ trackingUrl } className={ className }>
			{ children ?? __( 'Track shipment', 'woocommerce-shipping' ) }
		</ExternalLink>
	),
	() => null
) as ComponentType< TrackShipmentProps >;
