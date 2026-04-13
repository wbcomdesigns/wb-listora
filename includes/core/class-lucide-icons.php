<?php
/**
 * Lucide icon renderer.
 *
 * Provides inline SVG output for Lucide icons used by listing types and features.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders inline Lucide SVG icons.
 */
class Lucide_Icons {

	/**
	 * SVG path data keyed by icon name.
	 *
	 * @var array<string, string>
	 */
	private static $icons = array(
		'building-2'      => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
		'utensils'        => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
		'home'            => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'bed'             => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
		'calendar'        => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
		'briefcase'       => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
		'heart-pulse'     => '<path d="M19.5 12.572l-7.5 7.428l-7.5-7.428A5 5 0 0 1 7.5 5c1.54 0 3.04.5 4.5 2c1.46-1.5 2.96-2 4.5-2a5 5 0 0 1 2.5 9.572z"/><path d="M12 6l1 4h2l1.5-2L18 12"/>',
		'graduation-cap'  => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
		'map-pin'         => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'tag'             => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
		'phone'           => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'info'            => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'images'          => '<path d="M18 22H4a2 2 0 0 1-2-2V6"/><rect width="16" height="16" x="6" y="2" rx="2"/><circle cx="14" cy="8" r="2"/><path d="m6 16.5 2-2a1 1 0 0 1 1.5.06L12 18l3-4a1 1 0 0 1 1.6.06L22 18"/>',
		'stethoscope'     => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>',
		'school'          => '<path d="M14 22v-4a2 2 0 1 0-4 0v4"/><path d="m18 10 3.5 3"/><path d="M18 5v17h-2"/><path d="m2.5 13 3.5-3"/><path d="M6 5v17h2"/><circle cx="12" cy="9" r="2"/><path d="M12 2C6.5 2 2 6.5 2 12"/><path d="M12 2c5.5 0 10 4.5 10 10"/>',
		'book'            => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
		'user'            => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'repeat'          => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
		'clipboard'       => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
		'building'        => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
		// Alias: 'location' maps to map-pin paths.
		'location'        => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
	);

	/**
	 * Render an inline SVG for the given Lucide icon name.
	 *
	 * @param string $name Icon name (e.g. 'building-2', 'map-pin').
	 * @param int    $size Width and height in pixels. Default 24.
	 * @return string Inline SVG string, or empty string if icon is unknown.
	 */
	public static function render( $name, $size = 24 ) {
		$name = (string) $name;

		if ( ! isset( self::$icons[ $name ] ) ) {
			return '';
		}

		$size  = (int) $size;
		$paths = self::$icons[ $name ];

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			$size,
			$paths
		);
	}
}
