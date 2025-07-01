export interface LocationResponse {
	id: string;
	address_1?: string;
	address_2?: string;
	city: string;
	company?: string;
	email: string;
	first_name: string;
	last_name: string;
	phone: string;
	postcode: string;
	state: string;
	country: string;
	name?: string;
	address?: string;
	is_verified?: bool;
	default_address?: bool;
}
