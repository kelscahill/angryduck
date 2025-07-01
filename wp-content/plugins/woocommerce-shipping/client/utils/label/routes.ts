import { addQueryArgs } from '@wordpress/url';
import {
	getLabelsPrintPath,
	getLabelTestPrintPath,
	getPackingSlipPrintPath,
} from 'data/routes';
import { Label } from 'types';
import { getPaperSizeWithKey } from 'components/label-purchase/label/utils';

const getPDFURL = (
	paperSize: string,
	labelId: Label[ 'labelId' ],
	baseUrl: string
) => {
	const selectedPaperSize = getPaperSizeWithKey( paperSize );
	if ( ! selectedPaperSize ) {
		throw new Error( `Invalid paper size: ${ paperSize }` );
	}
	const params = {
		paper_size: paperSize, // send params as a CSV to avoid conflicts with some plugins out there (woocommerce-services #1111)
		label_id_csv: labelId,
		json: true,
	};
	return addQueryArgs( baseUrl, params );
};

export const getPrintURL = ( paperSize: string, labelId: Label[ 'labelId' ] ) =>
	getPDFURL( paperSize, labelId, getLabelsPrintPath() );

export const getPreviewURL = (
	paperSize: string,
	labelId: Label[ 'labelId' ]
) => getPDFURL( paperSize, labelId, getLabelTestPrintPath() );

export const getPackingSlipPrintURL = ( labelId: number, orderId: number ) =>
	getPackingSlipPrintPath( labelId, orderId );
