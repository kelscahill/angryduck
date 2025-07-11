export interface BadgeProps {
	/**
	 * Badge variant.
	 *
	 * @default 'default'
	 */
	intent?: 'default' | 'info' | 'success' | 'warning' | 'error';
	/**
	 * Text to display inside the badge.
	 */
	children: React.ReactNode;
}
