import { NoticeAction } from '@wordpress/components/build-types/notice/types';

export interface LabelPurchaseError {
	cause: 'purchase_error' | 'print_error' | 'status_error' | 'carrier_error';
	code?: string;
	message: string[];
	actions?: NoticeAction[];
	data?: {
		acceptedVersions: string[];
	};
}
