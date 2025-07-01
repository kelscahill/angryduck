import { useThrottledStateChange } from '../utils';
import { LabelPurchaseContextType } from 'context/label-purchase';

export const useRatesEffects = ( {
	rates: { updateRates },
	customs: { isCustomsNeeded },
	shipment: { getCurrentShipmentDate },
}: LabelPurchaseContextType ) => {
	// Update rates when isCustomsNeeded changes
	useThrottledStateChange( isCustomsNeeded(), updateRates );

	// Update rates when the shipment dates changes
	useThrottledStateChange( getCurrentShipmentDate(), updateRates );
};
