<?php
/**
 * Shared email header — opens the document and paints the header strip.
 *
 * Override via: {theme}/wb-listora/emails/parts/header.php
 *
 * @package WBListora
 *
 * @var array  $colors       Palette (primary, success, danger, warning, text, bg, bg_alt, border, white).
 * @var string $variant      One of success|warning|danger|neutral — drives header strip color.
 * @var string $header_title Optional override for the top-strip message. Defaults to empty.
 * @var string $logo_url     Optional logo URL. When set, rendered above the header strip.
 *                           Filter: wb_listora_email_logo_url.
 */

defined( 'ABSPATH' ) || exit;

$header_color = $colors[ $variant ] ?? $colors['primary'];
$header_title = $header_title ?? '';
$logo_url     = $logo_url ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo esc_attr( $colors['bg_alt'] ); ?>;padding:2rem 1rem;">
	<tr><td align="center">
	<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:<?php echo esc_attr( $colors['bg'] ); ?>;border-radius:8px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,sans-serif;color:<?php echo esc_attr( $colors['text'] ); ?>;">

		<?php if ( $logo_url ) : ?>
		<tr>
			<td style="padding:1.5rem 2rem 0;text-align:center;">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ?? '' ); ?>" style="max-width:160px;height:auto;display:inline-block;" />
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( $header_title ) : ?>
		<tr>
			<td style="padding:1.5rem 2rem;background:<?php echo esc_attr( $header_color ); ?>;text-align:center;">
				<p style="margin:0;font-size:1.1rem;font-weight:600;color:<?php echo esc_attr( $colors['white'] ); ?>;">
					<?php echo esc_html( $header_title ); ?>
				</p>
			</td>
		</tr>
		<?php endif; ?>
