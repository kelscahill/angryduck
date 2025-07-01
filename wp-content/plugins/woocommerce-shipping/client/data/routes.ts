import { NAMESPACE, WC_NAMESPACE } from './constants';
import { Carrier, CustomPackageType } from 'types';

export const getRatesPath = () => `${ NAMESPACE }/label/rate`;

export const getUpdateOriginPath = () => `${ NAMESPACE }/address/update_origin`;

export const getUpdateDestinationPath = ( orderId: string ) =>
	`${ NAMESPACE }/address/${ orderId }/update_destination`;

export const getAddressNormalizationPath = () =>
	`${ NAMESPACE }/address/normalize`;

export const getVerifyOrderShippingAddressPath = ( orderId: string ) =>
	`${ NAMESPACE }/address/${ orderId }/verify_order`;

export const getPackagesPath = () => `${ NAMESPACE }/packages`;
export const getShipmentsPath = ( orderId: string ) =>
	`${ NAMESPACE }/shipments/${ orderId }`;

export const getLabelPurchasePath = ( orderId: number ) =>
	`${ NAMESPACE }/label/purchase/${ orderId }`;

export const getAccountSettingsPath = () => `${ NAMESPACE }/account/settings`;

export const getLabelsStatusPath = ( orderId: number, labelId: number ) =>
	`${ NAMESPACE }/label/status/${ orderId }/${ labelId }`;

export const getLabelsPrintPath = () => `${ NAMESPACE }/label/print`;
export const getLabelTestPrintPath = () => `${ NAMESPACE }/label/preview`;

export const getPackingSlipPrintPath = ( labelId: number, orderId: number ) =>
	`${ NAMESPACE }/label/print/packing-list/${ labelId }/${ orderId }`;

export const getWCOrdersPath = ( orderId: string ) =>
	`${ WC_NAMESPACE }/orders/${ orderId }`;

export const getLabelRefundPath = ( orderId: number, labelId: number ) =>
	`${ NAMESPACE }/label/refund/${ orderId }/${ labelId }`;

export const getDeleteOriginAddressPath = ( id: string ) =>
	`${ NAMESPACE }/address/${ id }`;

export const getWPCOMConnectionPath = () => `${ NAMESPACE }/wpcom-connection`;

export const getDeletePackagePath = ( type: CustomPackageType, id: string ) =>
	`${ NAMESPACE }/packages/${ type }/${ id }`;

export const getCarrierStrategyPath = ( carrierId: Carrier ) =>
	`${ NAMESPACE }/carrier-strategy/${ carrierId }`;

export const getLabelsReportPath = () => `${ NAMESPACE }/reports/labels`;
