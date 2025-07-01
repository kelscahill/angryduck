import {
	CUSTOM_BOX_ID_PREFIX,
	CUSTOM_PACKAGE_TYPES,
} from './packages/constants';
import { CustomPackage } from 'types';

export const mainModalContentSelector =
	'.label-purchase-modal > .components-modal__content';

export const defaultCustomPackageData: CustomPackage & { isLetter: boolean } = {
	name: '',
	length: '',
	width: '',
	height: '',
	boxWeight: 0,
	id: CUSTOM_BOX_ID_PREFIX,
	type: CUSTOM_PACKAGE_TYPES.BOX,
	isLetter: false,
	dimensions: '10 x 10 x 10',
	isUserDefined: true,
};

export const settingsPageUrl =
	'admin.php?page=wc-settings&tab=shipping&section=woocommerce-shipping-settings';

export const TIME_TO_WAIT_TO_CHECK_PURCHASED_LABEL_STATUS_MS = 10000;
