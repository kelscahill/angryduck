import { Button, CheckboxControl, Dropdown } from '@wordpress/components';
import { chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Carrier } from 'types';
import { CarrierIcon } from 'components/carrier-icon';

interface CarrierFilterProps {
	carriers: {
		id: Carrier;
		label: string;
	}[];
	selectedCarriers: Carrier[];
	filterToCarriers: ( carriers: Carrier[] ) => void;
}

export const CarrierFilter = ( {
	carriers,
	selectedCarriers,
	filterToCarriers,
}: CarrierFilterProps ) => {
	const [ selections, setSelections ] = useState( selectedCarriers );
	const toggle = ( carrier: Carrier ) => ( select: boolean ) => {
		if ( selections.length === 1 && ! select ) {
			return;
		}
		setSelections( ( currentSelections ) =>
			select
				? [ ...currentSelections, carrier ]
				: currentSelections.filter( ( c ) => c !== carrier )
		);
	};
	return (
		<Dropdown
			popoverProps={ {
				placement: 'bottom-end',
				resize: true,
				shift: true,
				inline: true,
				noArrow: false,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="tertiary"
					className="shipping-filter_carrier"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					icon={ isOpen ? chevronUp : chevronDown }
				>
					{ __( 'Carriers', 'woocommerce-shipping' ) }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<>
					{ carriers.map( ( { id, label } ) => (
						<CheckboxControl
							key={ id }
							id={ id }
							onChange={ toggle( id ) }
							checked={ selections.includes( id ) }
							// @ts-ignore
							label={
								<>
									<CarrierIcon
										carrier={ id }
										key={ id }
										size="small"
										positionX={ 'center' }
										positionY={ 'center' }
									/>
									{ label }
								</>
							}
							__nextHasNoMarginBottom={ true }
							disabled={
								selections.length === 1 &&
								selections.includes( id )
							}
						/>
					) ) }
					<Button
						variant="secondary"
						onClick={ () => {
							filterToCarriers( selections );
							onClose();
						} }
					>
						{ __( 'Apply', 'woocommerce-shipping' ) }
					</Button>
				</>
			) }
		/>
	);
};
