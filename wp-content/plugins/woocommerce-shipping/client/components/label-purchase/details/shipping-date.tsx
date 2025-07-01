import {
	useState,
	useRef,
	useLayoutEffect,
	useCallback,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isDate, uniqueId } from 'lodash';
import {
	DatePicker,
	Popover,
	Button,
	BaseControl,
	Flex,
} from '@wordpress/components';
import clsx from 'clsx';
import { getDateTS, getDisplayDate } from 'utils';
import { ControlledPopover } from 'components/controlled-popover';
import { ShippingDateSpotlight } from './shipping-date-spotlight';

interface ShippingDateProps {
	canSelectDate?: boolean;
	shippingDate?: Date;
	setShippingDate: ( date: Date ) => void;
}

export const ShippingDate = ( {
	canSelectDate = true,
	shippingDate,
	setShippingDate,
}: ShippingDateProps ) => {
	const calendarButtonRef = useRef< HTMLButtonElement >( null );
	const [ isPopoverOpen, setIsPopoverOpen ] = useState( false );

	// Use ISO string format for date storage
	const today = getDateTS( null, true );

	const datePickerRef = useRef< HTMLDivElement >( null );

	// Validation state
	const [ validationError, setValidationError ] = useState< string | null >(
		null
	);

	const togglePopover = () => {
		// Only open the popover if it's currently closed
		if ( ! isPopoverOpen ) {
			setIsPopoverOpen( true );
		} else {
			setIsPopoverOpen( false );
		}
	};

	const validateDate = (
		dateToValidate: typeof shippingDate = shippingDate
	) => {
		if ( dateToValidate ) {
			try {
				// Check if date is valid
				if ( isNaN( dateToValidate.getTime() ) ) {
					setValidationError(
						__(
							'Please enter a valid date.',
							'woocommerce-shipping'
						)
					);
					return;
				}

				// Check if date is in the past
				const todayForValidation = getDateTS( null, true );

				if ( dateToValidate < todayForValidation ) {
					setValidationError(
						__(
							'Shipping date cannot be in the past.',
							'woocommerce-shipping'
						)
					);
					return;
				}
			} catch {
				setValidationError(
					__( 'Please enter a valid date.', 'woocommerce-shipping' )
				);
				return;
			}
		}

		setValidationError( null );
	};

	const handleDateChange = ( newDate: string ) => {
		// Convert string date to Date object using getDateTS
		const normalizedDate = getDateTS( newDate, true );

		// Check if date is in the past
		const todayForComparison = getDateTS( null, true ); // Get current date

		if ( normalizedDate < todayForComparison ) {
			setValidationError(
				__(
					'Shipping date cannot be in the past.',
					'woocommerce-shipping'
				)
			);
			return;
		}

		const dateObj = getDateTS( newDate );

		validateDate( dateObj );

		setShippingDate( dateObj );

		setValidationError( null );

		// Close the popover after selecting a date
		setIsPopoverOpen( false );
	};

	const disablePastDayButtons = useCallback( () => {
		if ( ! datePickerRef.current ) {
			return;
		}

		// Get all day buttons in the calendar
		const allDayButtons =
			datePickerRef.current.querySelectorAll< HTMLButtonElement >(
				'button'
			) ?? [];

		// Loop through each button and check its date
		allDayButtons.forEach( ( button ) => {
			const buttonLabel = button.getAttribute( 'aria-label' );

			if ( buttonLabel ) {
				// Extract the date from the aria-label
				try {
					// The aria-label format is like "January 1, 2023"
					const buttonDate = getDateTS( buttonLabel, true );

					// Only disable the button if its date is before today
					// and is a valid date (not NaN)
					if ( isDate( buttonDate ) && buttonDate < today ) {
						button.disabled = true;
					}
				} catch {
					// If we can't parse the date, just continue
				}
			}
		} );
	}, [ today ] );

	useLayoutEffect( () => {
		if ( ! isPopoverOpen || ! datePickerRef.current ) {
			return;
		}

		disablePastDayButtons();
	}, [ isPopoverOpen, disablePastDayButtons ] );

	return (
		<>
			{ canSelectDate && (
				<ShippingDateSpotlight
					referenceSelector=".shipping-date-control"
					focusSelector=".shipping-date-display"
				/>
			) }
			<BaseControl
				// Defining label need an id for the control
				id={ uniqueId( 'shipping-date-control-' ) }
				label={
					<>
						{ __( 'Ship date', 'woocommerce-shipping' ) }{ ' ' }
						<ControlledPopover
							icon="info-outline"
							withArrow={ false }
							popoverOptions={ {
								className: 'shipping-date-info-popover',
							} }
						>
							{ __(
								'The date your package will be picked up by the shipping carrier.',
								'woocommerce-shipping'
							) }
						</ControlledPopover>
					</>
				}
				help={ validationError }
				className={ clsx(
					'shipping-date-control',
					validationError ? 'has-error' : ''
				) }
				__nextHasNoMarginBottom={ true }
			>
				<Flex className="shipping-date-field-wrapper" as="section">
					<Button
						className="shipping-date-display"
						ref={ calendarButtonRef }
						variant="link"
						aria-expanded={ isPopoverOpen }
						aria-haspopup="true"
						/**
						 * Disable pointer events when the popover is open
						 * to prevent the user from clicking on the button while the popover is open.
						 * This will make sure the popover is not randomly opening and closing on second click.
						 */
						style={ {
							pointerEvents:
								isPopoverOpen || ! canSelectDate
									? 'none'
									: 'auto',
						} }
						onClick={ togglePopover }
						icon={ canSelectDate ? 'calendar-alt' : null }
						disabled={ ! canSelectDate }
					>
						{ getDisplayDate( shippingDate ?? today ) }
					</Button>

					{ isPopoverOpen && (
						<Popover
							anchor={ calendarButtonRef.current }
							onClose={ () => setIsPopoverOpen( false ) }
							onFocusOutside={ () => setIsPopoverOpen( false ) }
							position="bottom center"
							className="shipping-date-popover"
							ref={ datePickerRef }
							focusOnMount={ false }
						>
							<DatePicker
								currentDate={
									shippingDate?.toISOString() ??
									today.toISOString()
								}
								onChange={ handleDateChange }
								onMonthPreviewed={ () => {
									// Defer the disablePastDayButtons call to allow the calendar to render
									setTimeout( disablePastDayButtons, 1 );
								} }
							/>
						</Popover>
					) }
				</Flex>
			</BaseControl>
		</>
	);
};
