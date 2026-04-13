/**
 * Listing Map Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { SpacingControl, BoxShadowControl, BorderRadiusControl, DeviceVisibility } from '../../shared/components';
import { useUniqueId } from '../../shared/hooks';
import metadata from '../../../blocks/listing-map/block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes, clientId } ) {
		const blockProps = useBlockProps();
		useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Content', 'wb-listora' ) }>
						<TextControl
							label={ __( 'Listing Type', 'wb-listora' ) }
							help={ __( 'Enter slug like "restaurant". Leave empty for all types.', 'wb-listora' ) }
							value={ attributes.listingType }
							onChange={ ( listingType ) => setAttributes( { listingType } ) }
						/>
						<TextControl
							label={ __( 'Map Height', 'wb-listora' ) }
							help={ __( 'e.g. 400px', 'wb-listora' ) }
							value={ attributes.height }
							onChange={ ( height ) => setAttributes( { height } ) }
						/>
						<NumberControl
							label={ __( 'Default Zoom', 'wb-listora' ) }
							value={ attributes.defaultZoom }
							onChange={ ( defaultZoom ) => setAttributes( { defaultZoom: Number( defaultZoom ) } ) }
							min={ 1 }
							max={ 20 }
						/>
						<NumberControl
							label={ __( 'Center Latitude', 'wb-listora' ) }
							value={ attributes.centerLat }
							onChange={ ( centerLat ) => setAttributes( { centerLat: Number( centerLat ) } ) }
							step={ 0.001 }
						/>
						<NumberControl
							label={ __( 'Center Longitude', 'wb-listora' ) }
							value={ attributes.centerLng }
							onChange={ ( centerLng ) => setAttributes( { centerLng: Number( centerLng ) } ) }
							step={ 0.001 }
						/>
						<NumberControl
							label={ __( 'Max Markers', 'wb-listora' ) }
							value={ attributes.maxMarkers }
							onChange={ ( maxMarkers ) => setAttributes( { maxMarkers: Number( maxMarkers ) } ) }
							min={ 10 }
							max={ 500 }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Map Controls', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Clustering', 'wb-listora' ) }
							checked={ attributes.showClustering }
							onChange={ ( showClustering ) => setAttributes( { showClustering } ) }
						/>
						<ToggleControl
							label={ __( 'Show Near Me', 'wb-listora' ) }
							checked={ attributes.showNearMe }
							onChange={ ( showNearMe ) => setAttributes( { showNearMe } ) }
						/>
						<ToggleControl
							label={ __( 'Show Fullscreen', 'wb-listora' ) }
							checked={ attributes.showFullscreen }
							onChange={ ( showFullscreen ) => setAttributes( { showFullscreen } ) }
						/>
						<ToggleControl
							label={ __( 'Search on Drag', 'wb-listora' ) }
							checked={ attributes.searchOnDrag }
							onChange={ ( searchOnDrag ) => setAttributes( { searchOnDrag } ) }
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
