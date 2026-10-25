<?php
/**
 * Standalone output for a builder landing page.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hlpb_landing_id = get_queried_object_id();
$hlpb_template_slug = HLPB_Landing_Builder::get_template_slug( $hlpb_landing_id );
$hlpb_asset_url = HLPB_Landing_Builder::get_template_asset_url( $hlpb_template_slug );
$hlpb_sections = HLPB_Landing_Builder::get_sections( $hlpb_landing_id );
$hlpb_seo = HLPB_Landing_Builder::get_seo_settings( $hlpb_landing_id );
$hlpb_has_cart = false;
foreach ( $hlpb_sections as $hlpb_section ) {
	if ( isset( $hlpb_section['type'] ) && 'cart_section' === $hlpb_section['type'] && ( ! array_key_exists( 'visible', $hlpb_section ) || ! empty( $hlpb_section['visible'] ) ) ) {
		$hlpb_has_cart = true;
		break;
	}
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#fffdf9">
	<title><?php echo esc_html( $hlpb_seo['title'] ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( $hlpb_has_cart ? 'hlpb-landing-page' : 'hlpb-landing-page hlpb-no-cart' ); ?>>
<?php wp_body_open(); ?>
<div class="hlpb-page">
	<?php echo HLPB_Landing_Builder::render_frontend_section( array( 'type' => 'sprite' ), $hlpb_template_slug, $hlpb_asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled static SVG sprite. ?>
	<main id="top">
		<?php foreach ( $hlpb_sections as $hlpb_section ) : ?>
			<?php echo HLPB_Landing_Builder::render_frontend_section( $hlpb_section, $hlpb_template_slug, $hlpb_asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section renderer escapes editor data and reads bundled markup. ?>
		<?php endforeach; ?>
	</main>
	<?php if ( $hlpb_has_cart ) : ?>
		<?php echo HLPB_Landing_Builder::render_demo_buybar( $hlpb_template_slug, $hlpb_asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled static demo controls. ?>
	<?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
