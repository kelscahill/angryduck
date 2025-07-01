import { getDate, getSettings } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { sprintf } from '@wordpress/i18n';

const DATE_FORMAT_OPTIONS = { month: 'long', day: 'numeric' } as const;

/**
 * Get a date object from a date string
 *  getDate() without params returns current date
 *
 * the original getDate doctype requires a dateString, but it's not used if not provided
 * @see @wordpress/date
 * @param dateString          - The date string to get the date object from
 * @param normalizeToMidnight - Whether to normalize the date to midnight
 * @return The date object
 */
export const getDateTS = (
	dateString?: string | null,
	normalizeToMidnight = false
) => {
	const date = getDate( dateString ?? null );
	if ( normalizeToMidnight ) {
		date.setHours( 0, 0, 0, 0 );
	}
	return date;
};

/**
 * Get the display date for the shipping date
 *
 * @param date - The date to get the display date for
 * @return The display date in the format of 'Today (25 Feb)' or 'Tomorrow (26 Feb)' or the date in the format of '25 Feb'
 */
export const getDisplayDate = ( date: Date ) => {
	// Check if date is today or tomorrow
	const today = getDateTS();
	today.setHours( 0, 0, 0, 0 );

	const tomorrow = getDateTS();
	tomorrow.setDate( tomorrow.getDate() + 1 );
	tomorrow.setHours( 0, 0, 0, 0 );

	const dateOnly = getDateTS( date.toISOString() );
	dateOnly.setHours( 0, 0, 0, 0 );

	const formattedDate = date.toLocaleDateString(
		getSettings().l10n.locale.replace( '_', '-' ),
		DATE_FORMAT_OPTIONS
	);

	if ( dateOnly.getTime() === today.getTime() ) {
		return sprintf(
			// translators: %s is the formatted date
			__( 'Today (%s)', 'woocommerce-shipping' ),
			formattedDate
		);
	} else if ( dateOnly.getTime() === tomorrow.getTime() ) {
		return sprintf(
			// translators: %s is the formatted date
			__( 'Tomorrow (%s)', 'woocommerce-shipping' ),
			formattedDate
		);
	}
	return formattedDate;
};

/**
 * Check if a date is valid
 *
 */
export const isDateValid = ( date: string ): boolean => {
	const dateObject = new Date( date );
	return ! isNaN( dateObject.getTime() );
};
