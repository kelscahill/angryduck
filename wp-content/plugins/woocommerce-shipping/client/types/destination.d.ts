import { LocationResponse } from './connect-server';
import { CamelCaseType } from './helpers';

export interface Destination
	extends Omit<
		CamelCaseType< LocationResponse >,
		'id' | 'address1' | 'address2'
	> {
	address1?: string;
	address2?: string;
}
