/**
 * Listing Card Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { SpacingControl, BoxShadowControl, BorderRadiusControl, DeviceVisibility } from '../../shared/components';
import { useUniqueId } from '../../shared/hooks';
import metadata from '../../../blocks/listing-card/block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes, clientId } ) {
		const blockProps = useBlockProps();
		useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Content', 'wb-listora' ) }>
						<NumberControl
							label={ __( 'Listing ID', 'wb-listora' ) }
							value={ attributes.listingId }
							onChange={ ( listingId ) => setAttributes( { listingId: Number( listingId ) } ) }
							min={ 0 }
						/>
						<SelectControl
							label={ __( 'Layout', 'wb-listora' ) }
							value={ attributes.layout }
							options={ [
								{ label: __( 'Standard', 'wb-listora' ), value: 'standard' },
								{ label: __( 'Compact', 'wb-listora' ), value: 'compact' },
								{ label: __( 'Detailed', 'wb-listora' ), value: 'detailed' },
							] }
							onChange={ ( layout ) => setAttributes( { layout } ) }
						/>
						<NumberControl
							label={ __( 'Max Meta Fields', 'wb-listora' ) }
							value={ attributes.maxMetaFields }
							onChange={ ( maxMetaFields ) => setAttributes( { maxMetaFields: Number( maxMetaFields ) } ) }
							min={ 1 }
							max={ 10 }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Display', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Rating', 'wb-listora' ) }
							checked={ attributes.showRating }
							onChange={ ( showRating ) => setAttributes( { showRating } ) }
						/>
						<ToggleControl
							label={ __( 'Show Favorite', 'wb-listora' ) }
							checked={ attributes.showFavorite }
							onChange={ ( showFavorite ) => setAttributes( { showFavorite } ) }
						/>
						<ToggleControl
							label={ __( 'Show Type', 'wb-listora' ) }
							checked={ attributes.showType }
							onChange={ ( showType ) => setAttributes( { showType } ) }
						/>
						<ToggleControl
							label={ __( 'Show Features', 'wb-listora' ) }
							checked={ attributes.showFeatures }
							onChange={ ( showFeatures ) => setAttributes( { showFeatures } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Layout', 'wb-listora' ) } initialOpen={ false }>
						<SpacingControl
							label={ __( 'Padding', 'wb-listora' ) }
							values={ attributes.padding }
							unit={ attributes.paddingUnit }
							onChange={ ( padding ) => setAttributes( { padding } ) }
							onUnitChange={ ( paddingUnit ) => setAttributes( { paddingUnit } ) }
						/>
						<SpacingControl
							label={ __( 'Margin', 'wb-listora' ) }
							values={ attributes.margin }
							unit={ attributes.marginUnit }
							onChange={ ( margin ) => setAttributes( { margin } ) }
							onUnitChange={ ( marginUnit ) => setAttributes( { marginUnit } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Style', 'wb-listora' ) } initialOpen={ false }>
						<BoxShadowControl
							enabled={ attributes.boxShadow }
							horizontal={ attributes.shadowHorizontal }
							vertical={ attributes.shadowVertical }
							blur={ attributes.shadowBlur }
							spread={ attributes.shadowSpread }
							color={ attributes.shadowColor }
							onToggle={ ( boxShadow ) => setAttributes( { boxShadow } ) }
							onChangeHorizontal={ ( shadowHorizontal ) => setAttributes( { shadowHorizontal } ) }
							onChangeVertical={ ( shadowVertical ) => setAttributes( { shadowVertical } ) }
							onChangeBlur={ ( shadowBlur ) => setAttributes( { shadowBlur } ) }
							onChangeSpread={ ( shadowSpread ) => setAttributes( { shadowSpread } ) }
							onChangeColor={ ( shadowColor ) => setAttributes( { shadowColor } ) }
						/>
						<BorderRadiusControl
							values={ attributes.borderRadius }
							unit={ attributes.borderRadiusUnit }
							onChange={ ( borderRadius ) => setAttributes( { borderRadius } ) }
							onUnitChange={ ( borderRadiusUnit ) => setAttributes( { borderRadiusUnit } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Advanced', 'wb-listora' ) } initialOpen={ false }>
						<DeviceVisibility
							hideOnDesktop={ attributes.hideOnDesktop }
							hideOnTablet={ attributes.hideOnTablet }
							hideOnMobile={ attributes.hideOnMobile }
							onChange={ ( vals ) => setAttributes( vals ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},
	save() {
		return null;
	},
} );
