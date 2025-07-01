import React from 'react';
import { Button } from '@wordpress/components';
import { useLabelPurchaseContext } from 'context/label-purchase';
import { useCallback, useRef, useState } from '@wordpress/element';

interface CreditCardButtonProps {
	url: string;
	disabled?: boolean;
	buttonLabel: string;
	buttonDescription: ( onChooseCard: () => void ) => React.JSX.Element;
}

export const CreditCardButton = ( {
	url,
	disabled,
	buttonLabel,
	buttonDescription,
}: CreditCardButtonProps ) => {
	const {
		account: { refreshSettings },
	} = useLabelPurchaseContext();
	const creditCardWindow = useRef< Window | null >( null );
	const [ isRefreshing, updateIsRefreshing ] = useState( false );

	const onVisibilityChange = useCallback( async () => {
		if ( ! document.hidden ) {
			/**
			 * One a refresh request is sent, the button should stay in loading
			 * state and isRefreshing should never be set to false as the UI logic
			 * will remove the button completely when the refresh is done
			 */
			updateIsRefreshing( true );
			await refreshSettings();
		}

		if ( creditCardWindow?.current?.closed ) {
			document.removeEventListener(
				'visibilitychange',
				onVisibilityChange
			);
		}
	}, [ refreshSettings ] );

	const onChooseCard = useCallback( () => {
		creditCardWindow.current = window.open( url );
		document.addEventListener( 'visibilitychange', onVisibilityChange );
	}, [ url, onVisibilityChange ] );

	return (
		<>
			<Button
				onClick={ onChooseCard }
				disabled={ isRefreshing || disabled }
				aria-disabled={ isRefreshing || disabled }
				variant="primary"
				icon="external"
				isBusy={ isRefreshing }
			>
				{ buttonLabel }
			</Button>
			<div className="purchase-section__explanation">
				{ buttonDescription( onChooseCard ) }
			</div>
		</>
	);
};
