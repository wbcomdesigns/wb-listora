/**
 * User Dashboard Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { SpacingControl, BoxShadowControl, BorderRadiusControl, DeviceVisibility } from '../../shared/components';
import { useUniqueId } from '../../shared/hooks';
import metadata from '../../../blocks/user-dashboard/block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes, clientId } ) {
		const blockProps = useBlockProps();
		useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Content', 'wb-listora' ) }>
						<SelectControl
							label={ __( 'Default Tab', 'wb-listora' ) }
							value={ attributes.defaultTab }
							options={ [
								{ label: __( 'Listings', 'wb-listora' ), value: 'listings' },
								{ label: __( 'Reviews', 'wb-listora' ), value: 'reviews' },
								{ label: __( 'Favorites', 'wb-listora' ), value: 'favorites' },
								{ label: __( 'Profile', 'wb-listora' ), value: 'profile' },
							] }
							onChange={ ( defaultTab ) => setAttributes( { defaultTab } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Display', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Listings', 'wb-listora' ) }
							checked={ attributes.showListings }
							onChange={ ( showListings ) => setAttributes( { showListings } ) }
						/>
						<ToggleControl
							label={ __( 'Show Reviews', 'wb-listora' ) }
							checked={ attributes.showReviews }
							onChange={ ( showReviews ) => setAttributes( { showReviews } ) }
						/>
						<ToggleControl
							label={ __( 'Show Favorites', 'wb-listora' ) }
							checked={ attributes.showFavorites }
							onChange={ ( showFavorites ) => setAttributes( { showFavorites } ) }
						/>
						<ToggleControl
							label={ __( 'Show Profile', 'wb-listora' ) }
							checked={ attributes.showProfile }
							onChange={ ( showProfile ) => setAttributes( { showProfile } ) }
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
