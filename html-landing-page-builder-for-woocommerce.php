<?php
/**
 * Plugin Name: HTML Landing Page Builder for WooCommerce
 * Description: Build HTML landing pages in WooCommerce and configure visual product attributes.
 * Version: 0.5.9
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: html-landing-page-builder-for-woocommerce
 * Domain Path: /languages
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HLPB_VERSION', '0.5.9' );
define( 'HLPB_FILE', __FILE__ );
define( 'HLPB_PATH', plugin_dir_path( __FILE__ ) );
define( 'HLPB_URL', plugin_dir_url( __FILE__ ) );

require_once HLPB_PATH . 'includes/class-hlpb-landing-builder.php';
require_once HLPB_PATH . 'includes/class-hlpb-cart-section.php';
require_once HLPB_PATH . 'includes/class-hlpb-attribute-admin.php';
require_once HLPB_PATH . 'includes/class-hlpb-demo-importer.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'HLPB_Landing_Builder', 'init' ) );
add_action( 'plugins_loaded', array( 'HLPB_Cart_Section', 'init' ) );
add_action( 'plugins_loaded', array( 'HLPB_Attribute_Admin', 'init' ) );
add_action( 'plugins_loaded', array( 'HLPB_Demo_Importer', 'init' ) );

register_activation_hook(
	__FILE__,
	static function () {
		HLPB_Landing_Builder::register_post_type();
		HLPB_Landing_Builder::register_export_route();
		HLPB_Demo_Importer::queue_activation_prompt();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
