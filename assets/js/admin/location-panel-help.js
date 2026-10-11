/**
 * Block editor: one line under the Locations panel saying what it drives.
 *
 * The address shown on a listing comes from its Address field; the Locations
 * terms only drive Browse by Location and the location filter. Other code can
 * wrap or replace this through the same `editor.PostTaxonomyType` filter.
 */
( function ( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	wp.hooks.addFilter( 'editor.PostTaxonomyType', 'wbListora/locationHelp', ( OriginalComponent ) => ( props ) => {
		if ( props.slug !== 'listora_listing_location' ) {
			return el( OriginalComponent, props );
		}
		return el(
			Fragment,
			null,
			el( 'p', { className: 'components-base-control__help', style: { marginTop: 0 } }, __( 'Controls where this listing appears in Browse by Location and location filters. The address shown on the listing comes from its Address field.', 'wb-listora' ) ),
			el( OriginalComponent, props )
		);
	} );
}( window.wp ) );
