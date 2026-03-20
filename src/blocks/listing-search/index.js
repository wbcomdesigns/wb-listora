/**
 * Listing Search Block — Editor registration.
 *
 * @package WBListora
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../../../blocks/listing-search/block.json';

registerBlockType(
	metadata.name,
	{
		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps(
				{
					className: 'listora-search listora-search--' + attributes.layout,
				}
			);

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
				< / InspectorControls >

				< div { ...blockProps } >
					< div className             = "listora-search__bar" >
						{ attributes.showKeyword && (
							< div className     = "listora-search__field listora-search__field--keyword" >
								< input
									type        = "search"
									className   = "listora-input listora-search__input"
									placeholder = { attributes.placeholder || __( 'Search listings...', 'wb-listora' ) }
									disabled
									style       = { { paddingInlineStart: '2.2rem' } }
								/ >
							< / div >
						) }
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
