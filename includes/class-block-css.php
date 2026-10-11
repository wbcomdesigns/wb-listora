<?php
/**
 * Block CSS — generates per-instance scoped CSS for dynamic blocks.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Generates responsive, scoped CSS from block attributes.
 */
class Block_CSS {

	/**
	 * Render a <style> tag with per-instance CSS.
	 *
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @return string HTML <style> tag (empty string if no custom styles).
	 */
	public static function render( $unique_id, $attributes ) {
		$css = self::generate( $unique_id, $attributes );
		if ( empty( $css ) ) {
			return '';
		}
		// On the frontend Listora's styles sit in the listora layer
		// (Theme_Defenses), so this has to as well or the isolation rule
		// reverts it. The editor preview arrives over REST with unlayered
		// styles, so it stays unlayered there.
		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$css = '@layer listora{' . $css . '}';
		}
		// Attribute values land in the CSS, so a '<' could close the style tag.
		return '<style>' . str_replace( '<', '', $css ) . '</style>';
	}

	/**
	 * Generate scoped CSS string from block attributes.
	 *
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @return string CSS rules.
	 */
	public static function generate( $unique_id, $attributes ) {
		// Block attributes are author-supplied: everything below is cast or allow-listed.
		$unique_id = sanitize_html_class( (string) $unique_id );
		if ( '' === $unique_id ) {
			return '';
		}

		$selector = '.listora-block-' . $unique_id;
		$rules    = array(
			'desktop' => array(),
			'tablet'  => array(),
			'mobile'  => array(),
		);

		// Spacing and radius: [ attribute, unit attribute, property, target ].
		$boxes = array(
			array( 'padding', 'paddingUnit', 'padding', 'desktop' ),
			array( 'paddingTablet', 'paddingUnit', 'padding', 'tablet' ),
			array( 'paddingMobile', 'paddingUnit', 'padding', 'mobile' ),
			array( 'margin', 'marginUnit', 'margin', 'desktop' ),
			array( 'marginTablet', 'marginUnit', 'margin', 'tablet' ),
			array( 'marginMobile', 'marginUnit', 'margin', 'mobile' ),
			array( 'borderRadius', 'borderRadiusUnit', 'border-radius', 'desktop' ),
		);
		foreach ( $boxes as $box ) {
			list( $attr, $unit_attr, $property, $target ) = $box;
			if ( empty( $attributes[ $attr ] ) || ! is_array( $attributes[ $attr ] ) ) {
				continue;
			}
			$unit  = self::unit( $attributes[ $unit_attr ] ?? 'px' );
			$sides = array();
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$sides[] = self::length( $attributes[ $attr ][ $side ] ?? null, $unit );
			}
			// A side that is not a length drops the whole declaration, as a browser would.
			if ( in_array( null, $sides, true ) ) {
				continue;
			}
			$rules[ $target ][] = $property . ': ' . implode( ' ', $sides ) . ';';
		}

		// Box shadow.
		if ( ! empty( $attributes['boxShadow'] ) ) {
			$rules['desktop'][] = sprintf(
				'box-shadow: %dpx %dpx %dpx %dpx %s;',
				(int) ( $attributes['shadowHorizontal'] ?? 0 ),
				(int) ( $attributes['shadowVertical'] ?? 4 ),
				(int) ( $attributes['shadowBlur'] ?? 8 ),
				(int) ( $attributes['shadowSpread'] ?? 0 ),
				self::color( $attributes['shadowColor'] ?? '', 'rgba(0,0,0,0.12)' )
			);
		}

		// Build CSS.
		list( 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile ) = $rules;
		$css = '';

		if ( ! empty( $desktop ) ) {
			$css .= $selector . " {\n  " . implode( "\n  ", $desktop ) . "\n}\n";
		}

		if ( ! empty( $tablet ) ) {
			$css .= "@media (max-width: 1024px) {\n  " . $selector . " {\n    " . implode( "\n    ", $tablet ) . "\n  }\n}\n";
		}

		if ( ! empty( $mobile ) ) {
			$css .= "@media (max-width: 767px) {\n  " . $selector . " {\n    " . implode( "\n    ", $mobile ) . "\n  }\n}\n";
		}

		return $css;
	}

	/**
	 * A CSS length unit from the editor's choices; anything else is px.
	 *
	 * @param mixed $unit Attribute value.
	 * @return string
	 */
	private static function unit( $unit ) {
		return in_array( $unit, array( 'px', 'em', 'rem', '%', 'vw', 'vh' ), true ) ? $unit : 'px';
	}

	/**
	 * One side of a box: a number with the unit, or `auto`.
	 *
	 * @param mixed  $value Attribute value.
	 * @param string $unit  Allowed unit.
	 * @return string|null Null when the value is not a length.
	 */
	private static function length( $value, $unit ) {
		if ( 'auto' === $value ) {
			return 'auto';
		}
		return is_numeric( $value ) ? ( 0 + $value ) . $unit : null;
	}

	/**
	 * A colour the editor can produce: hex, rgb/hsl(a), a var(--token) or a
	 * keyword. Anything else falls back, so a value cannot carry rules or url().
	 *
	 * @param mixed  $color    Attribute value.
	 * @param string $fallback Colour used when the value is not one of those.
	 * @return string
	 */
	public static function color( $color, $fallback ) {
		$color = is_string( $color ) ? trim( $color ) : '';
		$valid = '/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/]+\)|var\(--[a-z0-9-]+\)|[a-z]+)$/i';
		return '' !== $color && preg_match( $valid, $color ) ? $color : $fallback;
	}

	/**
	 * Get visibility CSS classes based on attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Space-separated CSS classes.
	 */
	public static function visibility_classes( $attributes ) {
		$classes = array();

		if ( ! empty( $attributes['hideOnDesktop'] ) ) {
			$classes[] = 'listora-hide-desktop';
		}
		if ( ! empty( $attributes['hideOnTablet'] ) ) {
			$classes[] = 'listora-hide-tablet';
		}
		if ( ! empty( $attributes['hideOnMobile'] ) ) {
			$classes[] = 'listora-hide-mobile';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Get the full block wrapper class string including unique ID and visibility.
	 *
	 * @param string $block_name Block name (e.g., 'listing-grid').
	 * @param string $unique_id  Unique block instance ID.
	 * @param array  $attributes Block attributes.
	 * @param string $extra      Additional classes.
	 * @return string CSS class string.
	 */
	public static function wrapper_classes( $block_name, $unique_id, $attributes, $extra = '' ) {
		$classes = array( 'listora-block' );

		if ( ! empty( $unique_id ) ) {
			$classes[] = 'listora-block-' . sanitize_html_class( (string) $unique_id );
		}

		$classes[] = 'listora-' . $block_name;

		$visibility = self::visibility_classes( $attributes );
		if ( ! empty( $visibility ) ) {
			$classes[] = $visibility;
		}

		if ( ! empty( $extra ) ) {
			$classes[] = $extra;
		}

		return implode( ' ', $classes );
	}
}
