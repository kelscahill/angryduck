import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Label } from 'types';
import { pickupUrls } from './constants';

interface SchedulePickupProps {
	selectedLabel?: Label;
	className?: string;
	children?: ReactNode;
}

export const SchedulePickup = ( {
	selectedLabel,
	className,
	children,
}: SchedulePickupProps ) => {
	const canScheduleRefund = useCallback(
		( label: Label | undefined ): label is Label =>
			Boolean( label?.carrierId && pickupUrls[ label?.carrierId ] ),
		[]
	);

	// All hooks called first, then conditional logic
	const shouldShowPickup = Boolean(
		selectedLabel &&
			canScheduleRefund( selectedLabel ) &&
			selectedLabel.carrierId &&
			pickupUrls[ selectedLabel.carrierId ]
	);

	if ( ! shouldShowPickup || ! selectedLabel ) {
		return null;
	}

	return (
		<ExternalLink
			href={ pickupUrls[ selectedLabel.carrierId! ] }
			className={ className }
		>
			{ children ?? __( 'Schedule pickup', 'woocommerce-shipping' ) }
		</ExternalLink>
	);
};
