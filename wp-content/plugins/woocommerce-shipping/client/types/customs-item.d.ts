import { OrderItem } from './order-item';

export interface CustomsItem extends Omit<OrderItem, 'name'> {
	description: string;
	hsTariffNumber: string;
	originCountry: string;
}
