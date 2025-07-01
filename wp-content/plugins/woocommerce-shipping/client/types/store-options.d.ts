import { WeightUnit } from './weight-unit.d';

export interface StoreOptions {
	weight_unit: WeightUnit;
	currency_symbol: string;
	dimension_unit: string;
	origin_country: string;
}
