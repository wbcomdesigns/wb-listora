/**
 * Listing Reviews Block — Editor registration.
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
import metadata from '../../../blocks/listing-reviews/block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes, clientId } ) {
		const blockProps = useBlockProps();
		useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Content', 'wb-listora' ) }>
						<NumberControl
							label={ __( 'Per Page', 'wb-listora' ) }
							value={ attributes.perPage }
							onChange={ ( perPage ) => setAttributes( { perPage: Number( perPage ) } ) }
							min={ 1 }
							max={ 50 }
						/>
						<SelectControl
							label={ __( 'Default Sort', 'wb-listora' ) }
							value={ attributes.defaultSort }
							options={ [
								{ label: __( 'Newest', 'wb-listora' ), value: 'newest' },
								{ label: __( 'Oldest', 'wb-listora' ), value: 'oldest' },
								{ label: __( 'Highest Rated', 'wb-listora' ), value: 'highest' },
								{ label: __( 'Lowest Rated', 'wb-listora' ), value: 'lowest' },
							] }
							onChange={ ( defaultSort ) => setAttributes( { defaultSort } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Display', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Summary', 'wb-listora' ) }
							checked={ attributes.showSummary }
							onChange={ ( showSummary ) => setAttributes( { showSummary } ) }
						/>
						<ToggleControl
							label={ __( 'Show Review Form', 'wb-listora' ) }
							checked={ attributes.showForm }
							onChange={ ( showForm ) => setAttributes( { showForm } ) }
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
