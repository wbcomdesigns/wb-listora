/**
 * Pick a listing type, instead of typing its slug.
 *
 * Every type-aware block asked the editor to hand-type a slug into a plain
 * text field, with no validation and no list of what exists. A single
 * transposed letter rendered an empty grid on the front end with the message
 * "No listings found. Try adjusting your filters, or be the first to add a
 * listing." — the visitor blamed for the owner's typo, and the owner given no
 * warning at all (card 10217484053).
 *
 * The types come from `GET /listora/v1/listing-types`, which is public and has
 * shipped for releases. Falls back to a text field if that request fails, so a
 * site with a blocked REST API can still set a type.
 */

import { useEffect, useState } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ListingTypeControl( {
	value = '',
	onChange,
	label = __( 'Listing Type', 'wb-listora' ),
	help = __( 'Leave as All types to show every listing.', 'wb-listora' ),
} ) {
	const [ types, setTypes ] = useState( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: '/listora/v1/listing-types' } )
			.then( ( result ) => {
				if ( cancelled ) {
					return;
				}
				setTypes( Array.isArray( result ) ? result : [] );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setFailed( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	// The REST call is unreachable: keep the old control rather than leaving
	// the editor with no way to set a type at all.
	if ( failed ) {
		return (
			<TextControl
				label={ label }
				help={ __( 'Could not load your listing types, so enter the slug by hand — for example "restaurant".', 'wb-listora' ) }
				value={ value }
				onChange={ onChange }
				__nextHasNoMarginBottom
			/>
		);
	}

	const options = [ { label: __( 'All types', 'wb-listora' ), value: '' } ];

	( types || [] ).forEach( ( type ) => {
		if ( type && type.slug ) {
			options.push( { label: type.name || type.slug, value: type.slug } );
		}
	} );

	// A slug saved before this control existed, or one whose type has since
	// been deleted, would silently reset to "All types" the moment the editor
	// touched anything else. Keep it selectable and say it is unknown.
	if ( value && ! options.some( ( option ) => option.value === value ) ) {
		options.push( {
			/* translators: %s: the listing type slug saved on the block. */
			label: sprintfSlug( __( '%s (not a listing type on this site)', 'wb-listora' ), value ),
			value,
		} );
	}

	return (
		<SelectControl
			label={ label }
			help={ null === types && ! failed ? __( 'Loading your listing types…', 'wb-listora' ) : help }
			value={ value }
			options={ options }
			onChange={ onChange }
			__nextHasNoMarginBottom
		/>
	);
}

/**
 * Minimal %s substitution — @wordpress/i18n's sprintf is not imported here to
 * keep this component's dependencies to what every block already loads.
 *
 * @param {string} template Translated string containing one %s.
 * @param {string} slug     Replacement.
 * @return {string} Interpolated string.
 */
function sprintfSlug( template, slug ) {
	return template.replace( '%s', slug );
}
