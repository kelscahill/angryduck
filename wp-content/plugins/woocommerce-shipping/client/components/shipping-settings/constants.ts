import { OriginAddress } from 'types';

export const emptyOriginAddress: OriginAddress = {
	id: '',
	name: '',
	firstName: '',
	lastName: '',
	email: '',
	phone: '',
	company: '',
	address: '',
	city: '',
	state: '',
	country: 'US',
	postcode: '',
	defaultAddress: false,
	isVerified: false,
};

export const SETTINGS_KEYS = {
	PAPER_SIZE: 'paper_size',
	EMAIL_RECEIPTS: 'email_receipts',
	USE_LAST_SERVICE: 'use_last_service',
	USE_LAST_PACKAGE: 'use_last_package',
	CHECKOUT_ADDRESS_VALIDATION: 'checkout_address_validation',
	AUTOMATICALLY_OPEN_PRINT_DIALOG: 'automatically_open_print_dialog',
	TAX_IDENTIFIER_IOSS: 'tax_identifier_ioss',
	TAX_IDENTIFIER_VOEC: 'tax_identifier_voec',
	TAX_IDENTIFIER_PVA: 'tax_identifier_pva',
	REMEMBER_LAST_USED_SHIPPING_DATE: 'remember_last_used_shipping_date',
} as const;
