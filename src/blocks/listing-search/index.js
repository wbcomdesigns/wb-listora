/**
 * Listing Search Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { SpacingControl, BoxShadowControl, BorderRadiusControl, DeviceVisibility } from '../../shared/components';
import { useUniqueId } from '../../shared/hooks';

import metadata from '../../../blocks/listing-search/block.json';

registerBlockType(
	metadata.name,
	{
		edit( { attributes, setAttributes, clientId } ) {
			const blockProps = useBlockProps(
				{
					className: 'listora-search listora-search--' + attributes.layout,
				}
			);
			useUniqueId( clientId, attributes.uniqueId, setAttributes );

		return (
			< >
				< InspectorControls >
					< PanelBody title = { __( 'Search Settings', 'wb-listora' ) } >
						< SelectControl
							label     = { __( 'Layout', 'wb-listora' ) }
							value     = { attributes.layout }
							options   = { [
								{ label: __( 'Horizontal Bar', 'wb-listora' ), value: 'horizontal' },
								{ label: __( 'Stacked', 'wb-listora' ), value: 'stacked' },
								] }
							onChange  = { ( layout ) => setAttributes( { layout } ) }
						/ >
						< TextControl
							label     = { __( 'Pre-filter by Listing Type', 'wb-listora' ) }
							help      = { __( 'Leave empty to show all types. Enter a type slug (e.g., "restaurant") to pre-filter.', 'wb-listora' ) }
							value     = { attributes.listingType }
							onChange  = { ( listingType ) => setAttributes( { listingType } ) }
						/ >
						< TextControl
							label     = { __( 'Placeholder Text', 'wb-listora' ) }
							value     = { attributes.placeholder }
							onChange  = { ( placeholder ) => setAttributes( { placeholder } ) }
						/ >
						< SelectControl
							label     = { __( 'Default Sort', 'wb-listora' ) }
							value     = { attributes.defaultSort }
							options   = { [
								{ label: __( 'Featured', 'wb-listora' ), value: 'featured' },
								{ label: __( 'Newest', 'wb-listora' ), value: 'newest' },
								{ label: __( 'Rating', 'wb-listora' ), value: 'rating' },
								{ label: __( 'Distance', 'wb-listora' ), value: 'distance' },
								{ label: __( 'Relevance', 'wb-listora' ), value: 'relevance' },
								] }
							onChange  = { ( defaultSort ) => setAttributes( { defaultSort } ) }
						/ >
					< / PanelBody >
					< PanelBody title = { __( 'Visibility', 'wb-listora' ) } initialOpen = { false } >
						< ToggleControl
							label     = { __( 'Show Keyword Search', 'wb-listora' ) }
							checked   = { attributes.showKeyword }
							onChange  = { ( showKeyword ) => setAttributes( { showKeyword } ) }
						/ >
						< ToggleControl
							label     = { __( 'Show Location Search', 'wb-listora' ) }
							checked   = { attributes.showLocation }
							onChange  = { ( showLocation ) => setAttributes( { showLocation } ) }
						/ >
						< ToggleControl
							label     = { __( 'Show Type Filter', 'wb-listora' ) }
							checked   = { attributes.showTypeFilter }
							onChange  = { ( showTypeFilter ) => setAttributes( { showTypeFilter } ) }
						/ >
						< ToggleControl
							label     = { __( 'Show More Filters', 'wb-listora' ) }
							checked   = { attributes.showMoreFilters }
							onChange  = { ( showMoreFilters ) => setAttributes( { showMoreFilters } ) }
						/ >
						< ToggleControl
							label     = { __( 'Show Near Me Button', 'wb-listora' ) }
							checked   = { attributes.showNearMe }
							onChange  = { ( showNearMe ) => setAttributes( { showNearMe } ) }
						/ >
					< / PanelBody >
					< PanelBody title = { __( 'Layout', 'wb-listora' ) } initialOpen = { false } >
						< SpacingControl
							label = { __( 'Padding', 'wb-listora' ) }
							values = { attributes.padding }
							unit = { attributes.paddingUnit }
							onChange = { ( padding ) => setAttributes( { padding } ) }
							onUnitChange = { ( paddingUnit ) => setAttributes( { paddingUnit } ) }
						/ >
						< SpacingControl
							label = { __( 'Margin', 'wb-listora' ) }
							values = { attributes.margin }
							unit = { attributes.marginUnit }
							onChange = { ( margin ) => setAttributes( { margin } ) }
							onUnitChange = { ( marginUnit ) => setAttributes( { marginUnit } ) }
						/ >
					< / PanelBody >
					< PanelBody title = { __( 'Style', 'wb-listora' ) } initialOpen = { false } >
						< BoxShadowControl
							enabled = { attributes.boxShadow }
							horizontal = { attributes.shadowHorizontal }
							vertical = { attributes.shadowVertical }
							blur = { attributes.shadowBlur }
							spread = { attributes.shadowSpread }
							color = { attributes.shadowColor }
							onToggle = { ( boxShadow ) => setAttributes( { boxShadow } ) }
							onChangeHorizontal = { ( shadowHorizontal ) => setAttributes( { shadowHorizontal } ) }
							onChangeVertical = { ( shadowVertical ) => setAttributes( { shadowVertical } ) }
							onChangeBlur = { ( shadowBlur ) => setAttributes( { shadowBlur } ) }
							onChangeSpread = { ( shadowSpread ) => setAttributes( { shadowSpread } ) }
							onChangeColor = { ( shadowColor ) => setAttributes( { shadowColor } ) }
						/ >
						< BorderRadiusControl
							values = { attributes.borderRadius }
							unit = { attributes.borderRadiusUnit }
							onChange = { ( borderRadius ) => setAttributes( { borderRadius } ) }
							onUnitChange = { ( borderRadiusUnit ) => setAttributes( { borderRadiusUnit } ) }
						/ >
					< / PanelBody >
					< PanelBody title = { __( 'Advanced', 'wb-listora' ) } initialOpen = { false } >
						< DeviceVisibility
							hideOnDesktop = { attributes.hideOnDesktop }
							hideOnTablet = { attributes.hideOnTablet }
							hideOnMobile = { attributes.hideOnMobile }
							onChange = { ( vals ) => setAttributes( vals ) }
						/ >
					< / PanelBody >
				< / InspectorControls >

				< div { ...blockProps } >
					{ attributes.showKeyword && (
						< div className     = "listora-search__bar" >
							< div className     = "listora-search__field listora-search__field--keyword" >
								< input
									type        = "search"
									className   = "listora-input listora-search__input"
									placeholder = { attributes.placeholder || __( 'Search listings...', 'wb-listora' ) }
									disabled
									style       = { { paddingInlineStart: '2.2rem' } }
								/ >
							< / div >
						{ attributes.showLocation && (
							< div className     = "listora-search__field listora-search__field--location" >
								< input
									type        = "text"
									className   = "listora-input listora-search__input"
									placeholder = { __( 'Location...', 'wb-listora' ) }
									disabled
									style       = { { paddingInlineStart: '2.2rem' } }
								/ >
							< / div >
						) }
						< button className   = "listora-btn listora-btn--primary listora-search__submit" disabled >
							{ __( 'Search', 'wb-listora' ) }
						< / button >
						< / div >
					) }
					{ ! attributes.showKeyword && attributes.showLocation && (
						< div className     = "listora-search__bar" >
							< div className     = "listora-search__field listora-search__field--location" >
								< input
									type        = "text"
									className   = "listora-input listora-search__input"
									placeholder = { __( 'Location...', 'wb-listora' ) }
									disabled
									style       = { { paddingInlineStart: '2.2rem' } }
								/ >
							< / div >
							< button className   = "listora-btn listora-btn--primary listora-search__submit" disabled >
								{ __( 'Search', 'wb-listora' ) }
							< / button >
						< / div >
					) }
					{ attributes.showTypeFilter && ! attributes.listingType && (
						< div className      = "listora-search__type-tabs" style = { { marginBlockStart: '0.75rem' } } >
							< span className = "listora-search__type-tab is-active" > { __( 'All', 'wb-listora' ) } < / span >
							< span className = "listora-search__type-tab" > { __( 'Business', 'wb-listora' ) } < / span >
							< span className = "listora-search__type-tab" > { __( 'Restaurant', 'wb-listora' ) } < / span >
							< span className = "listora-search__type-tab" > { __( 'Real Estate', 'wb-listora' ) } < / span >
						< / div >
					) }
					{ attributes.showMoreFilters && (
						< div style = { { marginBlockStart: '0.5rem', fontSize: '0.85rem', color: '#666' } } >
							{ __( '▼ More Filters (expand on frontend)', 'wb-listora' ) }
						< / div >
					) }
				< / div >
			< / >
		);
		},
		save() {
			// Dynamic block — rendered via render.php.
			return null;
		},
	}
);
