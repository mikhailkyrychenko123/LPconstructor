<?php
/**
 * Standalone output for a builder landing page.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$landing_id = get_queried_object_id();
$template_slug = HLPB_Landing_Builder::get_template_slug( $landing_id );
$asset_url = HLPB_Landing_Builder::get_template_asset_url( $template_slug );
$sections   = HLPB_Landing_Builder::get_sections( $landing_id );
$has_cart   = false;
foreach ( $sections as $section ) {
	if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
		$has_cart = true;
		break;
	}
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#fffdf9">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( $has_cart ? 'hlpb-landing-page' : 'hlpb-landing-page hlpb-no-cart' ); ?>>
<?php wp_body_open(); ?>
<div class="hlpb-page">
	<?php echo HLPB_Landing_Builder::render_frontend_section( array( 'type' => 'sprite' ), $template_slug, $asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled static SVG sprite. ?>
	<main id="top">
		<?php foreach ( $sections as $section ) : ?>
			<?php echo HLPB_Landing_Builder::render_frontend_section( $section, $template_slug, $asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section renderer escapes editor data and reads bundled markup. ?>
		<?php endforeach; ?>
	</main>
	<?php if ( $has_cart ) : ?>
		<?php echo HLPB_Landing_Builder::render_demo_buybar( $template_slug, $asset_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled static demo controls. ?>
	<?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
