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
		return '<style>' . $css . '</style>';
	}

	/**
	 * Generate scoped CSS string from block attributes.
	 *
	 * @param string $unique_id  Block instance unique ID.
	 * @param array  $attributes Block attributes.
	 * @return string CSS rules.
	 */
	public static function generate( $unique_id, $attributes ) {
		if ( empty( $unique_id ) ) {
			return '';
		}

		$selector = '.listora-block-' . $unique_id;
		$desktop  = array();
		$tablet   = array();
		$mobile   = array();

		// Padding.
		if ( ! empty( $attributes['padding'] ) && is_array( $attributes['padding'] ) ) {
			$u         = $attributes['paddingUnit'] ?? 'px';
			$p         = $attributes['padding'];
			$desktop[] = sprintf( 'padding: %s%s %s%s %s%s %s%s;', $p['top'], $u, $p['right'], $u, $p['bottom'], $u, $p['left'], $u );
		}
		if ( ! empty( $attributes['paddingTablet'] ) && is_array( $attributes['paddingTablet'] ) ) {
			$u        = $attributes['paddingUnit'] ?? 'px';
			$p        = $attributes['paddingTablet'];
			$tablet[] = sprintf( 'padding: %s%s %s%s %s%s %s%s;', $p['top'], $u, $p['right'], $u, $p['bottom'], $u, $p['left'], $u );
		}
		if ( ! empty( $attributes['paddingMobile'] ) && is_array( $attributes['paddingMobile'] ) ) {
			$u        = $attributes['paddingUnit'] ?? 'px';
			$p        = $attributes['paddingMobile'];
			$mobile[] = sprintf( 'padding: %s%s %s%s %s%s %s%s;', $p['top'], $u, $p['right'], $u, $p['bottom'], $u, $p['left'], $u );
		}

		// Margin.
		if ( ! empty( $attributes['margin'] ) && is_array( $attributes['margin'] ) ) {
			$u         = $attributes['marginUnit'] ?? 'px';
			$m         = $attributes['margin'];
			$desktop[] = sprintf( 'margin: %s%s %s%s %s%s %s%s;', $m['top'], $u, $m['right'], $u, $m['bottom'], $u, $m['left'], $u );
		}
		if ( ! empty( $attributes['marginTablet'] ) && is_array( $attributes['marginTablet'] ) ) {
			$u        = $attributes['marginUnit'] ?? 'px';
			$m        = $attributes['marginTablet'];
			$tablet[] = sprintf( 'margin: %s%s %s%s %s%s %s%s;', $m['top'], $u, $m['right'], $u, $m['bottom'], $u, $m['left'], $u );
		}
		if ( ! empty( $attributes['marginMobile'] ) && is_array( $attributes['marginMobile'] ) ) {
			$u        = $attributes['marginUnit'] ?? 'px';
			$m        = $attributes['marginMobile'];
			$mobile[] = sprintf( 'margin: %s%s %s%s %s%s %s%s;', $m['top'], $u, $m['right'], $u, $m['bottom'], $u, $m['left'], $u );
		}

		// Border radius.
		if ( ! empty( $attributes['borderRadius'] ) && is_array( $attributes['borderRadius'] ) ) {
			$u         = $attributes['borderRadiusUnit'] ?? 'px';
			$r         = $attributes['borderRadius'];
			$desktop[] = sprintf( 'border-radius: %s%s %s%s %s%s %s%s;', $r['top'], $u, $r['right'], $u, $r['bottom'], $u, $r['left'], $u );
		}

		// Box shadow.
		if ( ! empty( $attributes['boxShadow'] ) ) {
			$h         = $attributes['shadowHorizontal'] ?? 0;
			$v         = $attributes['shadowVertical'] ?? 4;
			$b         = $attributes['shadowBlur'] ?? 8;
			$s         = $attributes['shadowSpread'] ?? 0;
			$c         = $attributes['shadowColor'] ?? 'rgba(0,0,0,0.12)';
			$desktop[] = sprintf( 'box-shadow: %dpx %dpx %dpx %dpx %s;', $h, $v, $b, $s, $c );
		}

		// Build CSS.
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
			$classes[] = 'listora-block-' . $unique_id;
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
