/**
 * Listing Grid Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { SpacingControl, BoxShadowControl, BorderRadiusControl, DeviceVisibility } from '../../shared/components';
import { useUniqueId } from '../../shared/hooks';
import metadata from '../../../blocks/listing-grid/block.json';

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
						<NumberControl
							label={ __( 'Per Page', 'wb-listora' ) }
							value={ attributes.perPage }
							onChange={ ( perPage ) => setAttributes( { perPage: Number( perPage ) } ) }
							min={ 1 }
							max={ 100 }
						/>
						<NumberControl
							label={ __( 'Columns', 'wb-listora' ) }
							value={ attributes.columns }
							onChange={ ( columns ) => setAttributes( { columns: Number( columns ) } ) }
							min={ 1 }
							max={ 6 }
						/>
						<SelectControl
							label={ __( 'Default View', 'wb-listora' ) }
							value={ attributes.defaultView }
							options={ [
								{ label: __( 'Grid', 'wb-listora' ), value: 'grid' },
								{ label: __( 'List', 'wb-listora' ), value: 'list' },
							] }
							onChange={ ( defaultView ) => setAttributes( { defaultView } ) }
						/>
						<SelectControl
							label={ __( 'Card Layout', 'wb-listora' ) }
							value={ attributes.cardLayout }
							options={ [
								{ label: __( 'Standard', 'wb-listora' ), value: 'standard' },
								{ label: __( 'Compact', 'wb-listora' ), value: 'compact' },
								{ label: __( 'Overlay', 'wb-listora' ), value: 'overlay' },
							] }
							onChange={ ( cardLayout ) => setAttributes( { cardLayout } ) }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Display', 'wb-listora' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show View Toggle', 'wb-listora' ) }
							checked={ attributes.showViewToggle }
							onChange={ ( showViewToggle ) => setAttributes( { showViewToggle } ) }
						/>
						<ToggleControl
							label={ __( 'Show Result Count', 'wb-listora' ) }
							checked={ attributes.showResultCount }
							onChange={ ( showResultCount ) => setAttributes( { showResultCount } ) }
						/>
						<ToggleControl
							label={ __( 'Show Sort', 'wb-listora' ) }
							checked={ attributes.showSort }
							onChange={ ( showSort ) => setAttributes( { showSort } ) }
						/>
						<ToggleControl
							label={ __( 'Show Pagination', 'wb-listora' ) }
							checked={ attributes.showPagination }
							onChange={ ( showPagination ) => setAttributes( { showPagination } ) }
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
