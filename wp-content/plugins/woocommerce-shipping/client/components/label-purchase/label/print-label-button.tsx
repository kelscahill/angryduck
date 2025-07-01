import { Button, MenuGroup, MenuItem, Dropdown } from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';
import { useLabelPurchaseContext } from 'context/label-purchase';
import { useCallback, forwardRef, useState } from '@wordpress/element';
import { PaperSize } from 'types';
import { recordEvent } from 'utils';

export const PrintLabelButton = forwardRef( ( props, ref ) => {
	const {
		labels: {
			selectedLabelSize,
			paperSizes,
			printLabel,
			isPurchasing,
			isUpdatingStatus,
			isPrinting,
		},
	} = useLabelPurchaseContext();

	const [ labelSize, setLabelSize ] =
		useState< PaperSize >( selectedLabelSize );

	const onPrintClick = async () => {
		const tracksProperties = {
			selected_label_size: labelSize.key,
			default_label_size: selectedLabelSize.key,
		};
		recordEvent( 'label_print_button_clicked', tracksProperties );
		await printLabel( true, labelSize );
	};

	const handleSizeSelect = useCallback(
		async ( size: PaperSize, onClose: () => void ) => {
			const tracksProperties = {
				selected_label_size: size.key,
				default_label_size: selectedLabelSize.key,
			};
			recordEvent(
				'label_print_size_dropdown_selected',
				tracksProperties
			);
			setLabelSize( size );
			await printLabel( true, size );
			onClose();
		},
		[ printLabel, selectedLabelSize ]
	);

	const handleChevronClick = useCallback( ( onToggle: () => void ) => {
		recordEvent( 'label_print_size_dropdown_clicked' );
		onToggle();
	}, [] );

	return (
		<Dropdown
			ref={ ref }
			className="print-label-button"
			popoverProps={ {
				placement: 'bottom-end',
				noArrow: false,
				resize: true,
				shift: true,
				inline: true,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<div style={ { display: 'flex' } }>
					<Button
						onClick={ onPrintClick }
						isBusy={ isPrinting }
						disabled={
							isPurchasing || isUpdatingStatus || isPrinting
						}
						variant="primary"
						style={ {
							borderTopRightRadius: 0,
							borderBottomRightRadius: 0,
							borderRight: '1px solid rgba(255, 255, 255, 0.4)',
						} }
					>
						Print label ({ labelSize.size })
					</Button>
					<Button
						disabled={
							isPurchasing || isUpdatingStatus || isPrinting
						}
						onClick={ () => handleChevronClick( onToggle ) }
						icon={ chevronDown }
						variant="primary"
						aria-expanded={ isOpen }
						aria-label="Select label size"
						style={ {
							borderTopLeftRadius: 0,
							borderBottomLeftRadius: 0,
							padding: '0 6px',
						} }
					/>
				</div>
			) }
			renderContent={ ( { onClose } ) => (
				<MenuGroup label="Select Label Size">
					{ paperSizes.map( ( size ) => (
						<MenuItem
							key={ size.key }
							isSelected={ labelSize.key === size.key }
							onClick={ () => handleSizeSelect( size, onClose ) }
						>
							{ size.name }
						</MenuItem>
					) ) }
				</MenuGroup>
			) }
		/>
	);
} );
