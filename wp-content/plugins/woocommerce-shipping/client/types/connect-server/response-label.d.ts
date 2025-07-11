import { LABEL_PURCHASE_STATUS } from 'data/constants';
import { RecordValues } from '../helpers';
import { Carrier } from '../carrier';

export interface ResponseLabel {
	label_id: number;
	id: string;
	tracking: null | string;
	refundable_amount: number;
	created: number;
	carrier_id: null | Carrier;
	service_name: string;
	status: RecordValues< LABEL_PURCHASE_STATUS >;
	commercial_invoice_url: string;
	is_commercial_invoice_submitted_electronically: string | boolean;
	package_name: string;
	is_letter: boolean;
	product_names: string[];
	product_ids: number[];
	rate: number;
	receipt_item_id: number;
	created_date: number;
	currency: string;
	expiry_date: number;
	label_cached: number;
	main_receipt_id: number;
	used_date?: number;
	refund?: {
		is_manual: boolean;
		requested_date: number;
		status: 'pending' | 'complete' | 'rejected' | 'unknown';
	};
	error?: string;
	// Is the label migrated from the legacy plugin?
	is_legacy?: boolean;
	promo_id?: string;
	promo_discount?: number;
}
