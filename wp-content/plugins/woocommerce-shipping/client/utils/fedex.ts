import { LabelPurchaseError } from 'types';

export const isFedExTosError = ( error: LabelPurchaseError | null ): boolean =>
	error?.code === 'missing_fedex_terms_of_service_acceptance';
