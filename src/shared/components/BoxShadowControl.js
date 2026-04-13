import { __ } from '@wordpress/i18n';
import {
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	ColorPalette,
	BaseControl,
	Flex,
	FlexItem,
} from '@wordpress/components';

export default function BoxShadowControl( {
	enabled = false,
	horizontal = 0,
	vertical = 4,
	blur = 8,
	spread = 0,
	color = 'rgba(0, 0, 0, 0.12)',
	onToggle,
	onChangeHorizontal,
	onChangeVertical,
	onChangeBlur,
	onChangeSpread,
	onChangeColor,
} ) {
	return (
		<BaseControl className="listora-box-shadow-control">
			<ToggleControl
				label={ __( 'Box Shadow', 'wb-listora' ) }
				checked={ enabled }
				onChange={ onToggle }
				__nextHasNoMarginBottom
			/>
			{ enabled && (
				<>
					<Flex gap={ 2 }>
						<FlexItem isBlock>
							<NumberControl
								label={ __( 'X', 'wb-listora' ) }
								value={ horizontal }
								onChange={ ( val ) => onChangeHorizontal( Number( val ) ) }
								min={ -50 }
								max={ 50 }
							/>
						</FlexItem>
						<FlexItem isBlock>
							<NumberControl
								label={ __( 'Y', 'wb-listora' ) }
								value={ vertical }
								onChange={ ( val ) => onChangeVertical( Number( val ) ) }
								min={ -50 }
								max={ 50 }
							/>
						</FlexItem>
					</Flex>
					<Flex gap={ 2 }>
						<FlexItem isBlock>
							<NumberControl
								label={ __( 'Blur', 'wb-listora' ) }
								value={ blur }
								onChange={ ( val ) => onChangeBlur( Number( val ) ) }
								min={ 0 }
								max={ 100 }
							/>
						</FlexItem>
						<FlexItem isBlock>
							<NumberControl
								label={ __( 'Spread', 'wb-listora' ) }
								value={ spread }
								onChange={ ( val ) => onChangeSpread( Number( val ) ) }
								min={ -50 }
								max={ 50 }
							/>
						</FlexItem>
					</Flex>
					<BaseControl label={ __( 'Shadow Color', 'wb-listora' ) }>
						<ColorPalette
							value={ color }
							onChange={ onChangeColor }
							clearable={ false }
						/>
					</BaseControl>
				</>
			) }
		</BaseControl>
	);
}
