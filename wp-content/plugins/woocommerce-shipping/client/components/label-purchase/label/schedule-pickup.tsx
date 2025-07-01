import { Icon } from '@wordpress/components';
import { external } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';
import { Link } from '@woocommerce/components';
import { Label } from 'types';
import { pickupUrls } from './constants';

interface SchedulePickupProps {
	selectedLabel?: Label;
}

export const SchedulePickup = ( { selectedLabel }: SchedulePickupProps ) => {
	const canScheduleRefund = useCallback(
		( label: Label | undefined ): label is Label =>
			Boolean( label?.carrierId && pickupUrls[ label?.carrierId ] ),
		[]
	);

	if (
		! selectedLabel ||
		! canScheduleRefund( selectedLabel ) ||
		! selectedLabel.carrierId ||
		! pickupUrls[ selectedLabel.carrierId ]
	) {
		return null;
	}
	return (
		<Link
			href={ pickupUrls[ selectedLabel.carrierId ] }
			type="external"
			target="_blank"
			rel="noopener noreferrer"
		>
			{ __( 'Schedule pickup', 'woocommerce-shipping' ) }
			<Icon icon={ external } />
		</Link>
	);
};
