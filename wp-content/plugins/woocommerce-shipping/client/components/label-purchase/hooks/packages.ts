import { isEmpty } from 'lodash';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { labelPurchaseStore } from 'data/label-purchase';
import {
	getAccountSettings,
	getAvailableCarrierPackages,
	getAvailablePackagesById,
	getPackageDimensions,
} from 'utils';
import { CustomPackage, Package, ShipmentItem } from 'types';
import { defaultCustomPackageData } from '../constants';
import {
	CUSTOM_BOX_ID_PREFIX,
	CUSTOM_PACKAGE_TYPES,
	TAB_NAMES,
} from '../packages';

export const getInitialPackageAndTab = (
	savedPackages: Package[]
): {
	initialTab: string;
	initialPackage: Package | null;
} => {
	const isUseLastBoxEnabled =
		getAccountSettings()?.purchaseSettings?.use_last_package;
	const lastBoxId = getAccountSettings()?.userMeta?.last_box_id;

	if ( isUseLastBoxEnabled && lastBoxId ) {
		const matchingSavedPackage = savedPackages.find(
			( { id } ) => id === lastBoxId
		);

		if ( matchingSavedPackage ) {
			return {
				initialTab: TAB_NAMES.SAVED_TEMPLATES,
				initialPackage: {
					...matchingSavedPackage,
					...( getPackageDimensions( matchingSavedPackage ) || {} ),
				},
			};
		}

		if ( getAvailableCarrierPackages() ) {
			const allCarrierPackagesById = getAvailablePackagesById();
			if ( Object.keys( allCarrierPackagesById ).includes( lastBoxId ) ) {
				return {
					initialTab: TAB_NAMES.CARRIER_PACKAGE,
					initialPackage: {
						...allCarrierPackagesById[ lastBoxId ],
						...( getPackageDimensions(
							allCarrierPackagesById[ lastBoxId ]
						) || {} ),
					},
				};
			}
		}
	}

	return {
		initialTab: TAB_NAMES.CUSTOM_PACKAGE,
		initialPackage: null,
	};
};

export function usePackageState(
	currentShipmentId: string,
	shipments: Record< string, ShipmentItem[] >,
	totalWeight: number
) {
	const savedPackages = useSelect(
		( select ) => select( labelPurchaseStore ).getSavedPackages(),
		// eslint-disable-next-line react-hooks/exhaustive-deps -- we want this to update when the shipmentId changes
		[ currentShipmentId ]
	);
	const { initialTab, initialPackage } = useMemo(
		() => getInitialPackageAndTab( savedPackages ),
		[ savedPackages ]
	);
	const [ initialCarrierTab, setInitialCarrierTab ] = useState< string >();

	const [ currentPackageTab, setCurrentPackageTab ] = useState( initialTab );
	const [ customPackageData, setCustomPackageData ] = useState<
		Record< string, CustomPackage >
	>( {
		[ currentShipmentId ]: defaultCustomPackageData,
	} );
	const initialPackages = Object.keys( shipments ).reduce(
		( packages: Record< string, Package | null >, id: string ) => {
			packages[ id ] = initialPackage;
			return packages;
		},
		{}
	);
	const [ selectedPackage, setSelected ] =
		useState< Record< string, Package | CustomPackage | null > >(
			initialPackages
		);

	const setCustomPackage = useCallback(
		( data: CustomPackage ) => {
			setCustomPackageData( ( prev ) => ( {
				...( prev || {} ),
				[ currentShipmentId ]: data,
			} ) );
		},
		[ currentShipmentId ]
	);

	const hasDistinctDimensions = (
		pkg: CustomPackage | Package
	): pkg is CustomPackage =>
		Object.hasOwn( pkg, 'length' ) &&
		Object.hasOwn( pkg, 'width' ) &&
		Object.hasOwn( pkg, 'height' );

	const setSelectedPackage = useCallback(
		( pkg: Package | CustomPackage ) => {
			setSelected( ( prev ) => ( {
				...( prev || {} ),
				[ currentShipmentId ]: {
					...pkg,
					...( hasDistinctDimensions( pkg )
						? pkg
						: getPackageDimensions( pkg ) || {} ),
				},
			} ) );
		},
		[ currentShipmentId ]
	);

	const getCustomPackage = useCallback( () => {
		if ( customPackageData[ currentShipmentId ] ) {
			return {
				...customPackageData[ currentShipmentId ],
				isLetter:
					customPackageData[ currentShipmentId ].type ===
					CUSTOM_PACKAGE_TYPES.ENVELOPE,
			};
		}
		return defaultCustomPackageData;
	}, [ currentShipmentId, customPackageData ] );

	const getSelectedPackage = useCallback( () => {
		let shipmentPackage = selectedPackage[ currentShipmentId ];
		if ( ! shipmentPackage ) {
			shipmentPackage = initialPackage;
		}
		return shipmentPackage;
	}, [ selectedPackage, currentShipmentId, initialPackage ] );

	const isCustomPackageTab = useCallback(
		() => currentPackageTab === TAB_NAMES.CUSTOM_PACKAGE,
		[ currentPackageTab ]
	);
	const getPackageForRequest = useCallback(
		() =>
			isCustomPackageTab()
				? getCustomPackage()
				: ( getSelectedPackage() as Package ),
		[ getCustomPackage, getSelectedPackage, isCustomPackageTab ]
	);

	const isSelectedASavedPackage = useCallback( () => {
		return savedPackages.some( ( p ) => p.id === getSelectedPackage()?.id );
	}, [ savedPackages, getSelectedPackage ] );

	const isPackageSpecified = () => {
		if ( totalWeight === 0 ) return false;

		if ( currentPackageTab === TAB_NAMES.CUSTOM_PACKAGE ) {
			const { width, height, length } = getCustomPackage();
			return [ width, height, length ]
				.map( parseFloat )
				.every( ( dimension ) => dimension > 0 );
		}
		if ( currentPackageTab === TAB_NAMES.CARRIER_PACKAGE ) {
			return (
				! isEmpty( getSelectedPackage() ) &&
				! getSelectedPackage()?.id.includes( CUSTOM_BOX_ID_PREFIX )
			);
		}

		// currentPackageTab === TAB_NAMES.SAVED_TEMPLATES
		return ! isEmpty( getSelectedPackage() ) && isSelectedASavedPackage();
	};

	return {
		getCustomPackage,
		setCustomPackage,
		getSelectedPackage,
		setSelectedPackage,
		currentPackageTab,
		setCurrentPackageTab,
		getPackageForRequest,
		isPackageSpecified,
		isSelectedASavedPackage,
		isCustomPackageTab,
		initialCarrierTab,
		setInitialCarrierTab,
	};
}
