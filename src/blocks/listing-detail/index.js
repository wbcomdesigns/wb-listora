/**
 * Listing Detail Block — Editor registration.
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
import metadata from '../../../blocks/listing-detail/block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes, clientId } ) {
		const blockProps = useBlockProps();
		useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Content', 'wb-listora' ) }>
						<SelectControl
							label={ __( 'Layout', 'wb-listora' ) }
							value={ attributes.layout }
							options={ [
								{ label: __( 'Tabbed', 'wb-listora' ), value: 'tabbed' },
								{ label: __( 'Accordion', 'wb-listora' ), value: 'accordion' },
							] }
							onChange={ ( layout ) => setAttributes( { layout } ) }
						/>
						<NumberControl
							label={ __( 'Related Listings Count', 'wb-listora' ) }
							value={ attributes.relatedCount }
							onChange={ ( relatedCount ) => setAttributes( { relatedCount: Number( relatedCount ) } ) }
							min={ 1 }
							max={ 12 }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Sections', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Gallery', 'wb-listora' ) }
							checked={ attributes.showGallery }
							onChange={ ( showGallery ) => setAttributes( { showGallery } ) }
						/>
						<ToggleControl
							label={ __( 'Show Map', 'wb-listora' ) }
							checked={ attributes.showMap }
							onChange={ ( showMap ) => setAttributes( { showMap } ) }
						/>
						<ToggleControl
							label={ __( 'Show Reviews', 'wb-listora' ) }
							checked={ attributes.showReviews }
							onChange={ ( showReviews ) => setAttributes( { showReviews } ) }
						/>
						<ToggleControl
							label={ __( 'Show Related Listings', 'wb-listora' ) }
							checked={ attributes.showRelated }
							onChange={ ( showRelated ) => setAttributes( { showRelated } ) }
						/>
						<ToggleControl
							label={ __( 'Show Share Buttons', 'wb-listora' ) }
							checked={ attributes.showShare }
							onChange={ ( showShare ) => setAttributes( { showShare } ) }
						/>
						<ToggleControl
							label={ __( 'Show Claim Button', 'wb-listora' ) }
							checked={ attributes.showClaim }
							onChange={ ( showClaim ) => setAttributes( { showClaim } ) }
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
