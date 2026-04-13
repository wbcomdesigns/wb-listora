import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	ColorPalette,
	ButtonGroup,
	Button,
	BaseControl,
} from '@wordpress/components';

export default function ColorHoverControl( {
	label,
	color,
	hoverColor,
	onChangeColor,
	onChangeHoverColor,
} ) {
	const [ tab, setTab ] = useState( 'normal' );

	return (
		<BaseControl label={ label } className="listora-color-hover-control">
			<ButtonGroup className="listora-color-hover-control__tabs">
				<Button
					isPressed={ tab === 'normal' }
					onClick={ () => setTab( 'normal' ) }
					size="small"
				>
					{ __( 'Normal', 'wb-listora' ) }
				</Button>
				<Button
					isPressed={ tab === 'hover' }
					onClick={ () => setTab( 'hover' ) }
					size="small"
				>
					{ __( 'Hover', 'wb-listora' ) }
				</Button>
			</ButtonGroup>
			<div className="listora-color-hover-control__picker">
				{ tab === 'normal' ? (
					<ColorPalette
						value={ color }
						onChange={ onChangeColor }
						clearable
					/>
				) : (
					<ColorPalette
						value={ hoverColor }
						onChange={ onChangeHoverColor }
						clearable
					/>
				) }
			</div>
		</BaseControl>
	);
}
