import { WEIGHT_UNITS } from 'util';

export type WeightUnit = (typeof WEIGHT_UNITS)[keyof typeof WEIGHT_UNITS];
