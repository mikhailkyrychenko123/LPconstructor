<?php
/**
 * Import the bundled WooCommerce demo products and their local media.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Demo_Importer {
	const PROMPT_OPTION  = 'hlpb_demo_import_prompt';
	const PRODUCT_META   = '_hlpb_demo_product_key';
	const VARIATION_META = '_hlpb_demo_variation_key';
	const MEDIA_META     = '_hlpb_demo_media_key';
	const TERM_META      = '_hlpb_demo_term_key';
	const ATTRIBUTES_OPTION = 'hlpb_demo_imported_attributes';
	const RESULT_OPTION  = 'hlpb_demo_import_last_result';
	const LOCK_OPTION    = 'hlpb_demo_import_lock';
	const LANDING_META    = '_hlpb_demo_landing';
	const MEDIA_PREFIX   = 'hlpb-demo-products-v1:';
	const NAME_SUFFIX    = 'Landing-demo';

	/** Counts collected during the current import or cleanup. */
	private static $run_counts = array();

	/** Distinct media keys encountered during the current import. */
	private static $run_media = array();

	/** Register the activation prompt and import actions. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'remove_duplicate_create_landing_submenu' ), 999 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_onboarding_assets' ) );
		add_action( 'admin_post_hlpb_import_demo_products', array( __CLASS__, 'handle_import' ) );
		add_action( 'wp_ajax_hlpb_activation_import', array( __CLASS__, 'handle_activation_import' ) );
		add_action( 'admin_post_hlpb_delete_demo_products', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_hlpb_dismiss_demo_import', array( __CLASS__, 'handle_dismiss' ) );
	}

	/** Add a persistent import and cleanup screen under Landing Pages. */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE,
			__( 'Demo Import', 'html-landing-page-builder-for-woocommerce' ),
			__( 'Import', 'html-landing-page-builder-for-woocommerce' ),
			'manage_woocommerce',
			'hlpb-demo-import',
			array( __CLASS__, 'render_import_page' )
		);
	}

	/** Keep the create button on the landing list page without a duplicate sidebar entry. */
	public static function remove_duplicate_create_landing_submenu() {
		remove_submenu_page(
			'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE,
			'post-new.php?post_type=' . HLPB_Landing_Builder::POST_TYPE
		);
	}

	/** Queue the optional import prompt after plugin activation. */
	public static function queue_activation_prompt() {
		update_option( self::PROMPT_OPTION, '1', false );
	}

	/** Load the activation welcome dialog only while it is pending. */
	public static function enqueue_onboarding_assets() {
		if ( '1' !== get_option( self::PROMPT_OPTION ) || ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) ) {
			return;
		}

		wp_enqueue_style( 'hlpb-onboarding', HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/onboarding.css', array(), HLPB_LANDING_PAGE_BUILDER_VERSION );
		wp_enqueue_script( 'hlpb-onboarding', HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/onboarding.js', array(), HLPB_LANDING_PAGE_BUILDER_VERSION, true );
		wp_localize_script(
			'hlpb-onboarding',
			'HLPBOnboarding',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'hlpb_activation_import' ),
				'busy'          => __( 'Preparing your demo store…', 'html-landing-page-builder-for-woocommerce' ),
				'error'         => __( 'The import could not be completed. You can safely retry from this window or open the Import page.', 'html-landing-page-builder-for-woocommerce' ),
				'importing'     => __( 'Importing product images and variations…', 'html-landing-page-builder-for-woocommerce' ),
				'finishing'     => __( 'WooCommerce is still preparing product images and galleries…', 'html-landing-page-builder-for-woocommerce' ),
				'longWait'      => __( 'This is taking longer than usual. Keep this window open and wait for the result before starting another import.', 'html-landing-page-builder-for-woocommerce' ),
				'invalidResponse' => __( 'The server returned an unexpected response (%d). Check the PHP error log for details.', 'html-landing-page-builder-for-woocommerce' ),
				'openLanding'   => __( 'Open demo landing page', 'html-landing-page-builder-for-woocommerce' ),
				'openFieldNotes' => __( 'Open Field Notes apparel demo', 'html-landing-page-builder-for-woocommerce' ),
			)
		);
	}

	/** Show the optional import welcome dialog after activation. */
	public static function render_admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( '1' !== get_option( self::PROMPT_OPTION ) ) {
			return;
		}

		$woocommerce_ready = function_exists( 'wc_create_attribute' ) && class_exists( 'WC_Product' );
		$logo_url          = HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/buyget-icon.png';
		$promo_image_url   = HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/onboarding-promo.webp';
		echo '<div class="hlpb-onboarding-overlay" role="presentation"><section class="hlpb-onboarding-modal" role="dialog" aria-modal="true" aria-labelledby="hlpb-onboarding-title" aria-describedby="hlpb-onboarding-copy">';
		echo '<div class="hlpb-onboarding-brand"><img class="hlpb-onboarding-brand-desktop" src="' . esc_url( $promo_image_url ) . '" width="876" height="1080" alt="' . esc_attr__( 'BuyGet landing page builder sales toolkit', 'html-landing-page-builder-for-woocommerce' ) . '"><div class="hlpb-onboarding-brand-mobile"><img src="' . esc_url( $logo_url ) . '" width="72" height="72" alt="' . esc_attr__( 'BuyGet logo', 'html-landing-page-builder-for-woocommerce' ) . '"><span>' . esc_html__( 'HTML Landing Page Builder for WooCommerce', 'html-landing-page-builder-for-woocommerce' ) . '</span></div></div>';
		echo '<div class="hlpb-onboarding-content"><p class="hlpb-onboarding-eyebrow">' . esc_html__( 'WELCOME TO BUYGET', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		echo '<h1 id="hlpb-onboarding-title">' . esc_html__( 'Turn your WooCommerce products into a landing page that sells.', 'html-landing-page-builder-for-woocommerce' ) . '</h1>';
		echo '<p id="hlpb-onboarding-copy" class="hlpb-onboarding-intro">' . esc_html__( 'Start with a ready-made shop: sample products, colour and size options, bundle offers, and two published demo landing pages.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		echo '<ul class="hlpb-onboarding-points"><li>' . esc_html__( 'Three isolated demo products with variations and galleries', 'html-landing-page-builder-for-woocommerce' ) . '</li><li>' . esc_html__( 'Buy More, Mix & Match, and Cozy Bundle examples', 'html-landing-page-builder-for-woocommerce' ) . '</li><li>' . esc_html__( 'Two ready-to-use demo pages: /promo/lp-demo/ and /promo/field-notes-demo/', 'html-landing-page-builder-for-woocommerce' ) . '</li><li>' . esc_html__( 'Static landing pages built to target a 100/100 Google PageSpeed score*', 'html-landing-page-builder-for-woocommerce' ) . '</li><li>' . esc_html__( 'Recommended with Caddy and CheckoutWC — fully compatible with both', 'html-landing-page-builder-for-woocommerce' ) . '</li></ul>';
		echo '<p class="hlpb-onboarding-footnote">' . esc_html__( '*Actual PageSpeed results depend on your hosting, theme, media, and other plugins.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		if ( $woocommerce_ready ) {
			echo '<button type="button" class="button button-primary hlpb-onboarding-import" data-hlpb-import>' . esc_html__( 'Import demo data', 'html-landing-page-builder-for-woocommerce' ) . '<span aria-hidden="true">→</span></button>';
		} else {
			echo '<p class="hlpb-onboarding-warning">' . esc_html__( 'Activate WooCommerce to import the demo data.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		}
		echo '<div class="hlpb-onboarding-progress" data-hlpb-progress hidden><span class="hlpb-onboarding-progress-label" data-hlpb-status role="status" aria-live="polite"></span><span class="hlpb-onboarding-track" role="progressbar" aria-label="' . esc_attr__( 'Import progress', 'html-landing-page-builder-for-woocommerce' ) . '"><span></span></span></div>';
		echo '<div class="hlpb-onboarding-result" data-hlpb-result hidden><span class="hlpb-onboarding-result-mark" aria-hidden="true">✓</span><div><h2 data-hlpb-result-title></h2><ul data-hlpb-report></ul><a class="hlpb-onboarding-landing" data-hlpb-landing href="#" target="_blank" rel="noopener noreferrer"></a><a class="hlpb-onboarding-landing" data-hlpb-field-notes-landing href="#" target="_blank" rel="noopener noreferrer" hidden></a></div></div>';
		echo '<div class="hlpb-onboarding-footer"><span>' . esc_html__( 'HTML Landing Page Builder for WooCommerce', 'html-landing-page-builder-for-woocommerce' ) . '</span><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hlpb_dismiss_demo_import"><input type="hidden" name="hlpb_return_to" value="landings">';
		wp_nonce_field( 'hlpb_dismiss_demo_import' );
		echo '<button type="submit" class="button-link">' . esc_html__( 'Go to Landing Pages', 'html-landing-page-builder-for-woocommerce' ) . '</button></form></div></div></section></div>';
	}

	/** Render the persistent status, report, import, and delete controls. */
	public static function render_import_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result             = get_option( self::RESULT_OPTION, array() );
		$woocommerce_ready  = function_exists( 'wc_create_attribute' ) && class_exists( 'WC_Product' );
		$owned_products     = self::get_owned_ids( 'product', self::PRODUCT_META );
		$owned_attributes   = get_option( self::ATTRIBUTES_OPTION, array() );
		$has_owned_data     = ! empty( $owned_products ) || ! empty( $owned_attributes ) || ! empty( self::get_owned_ids( 'product_variation', self::VARIATION_META ) ) || ! empty( self::get_owned_ids( 'attachment', self::MEDIA_META ) );

		echo '<div class="wrap"><h1>' . esc_html__( 'Demo Import', 'html-landing-page-builder-for-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'Import or remove the bundled WooCommerce demo catalog. Imported products and attributes are isolated with the Landing-demo suffix. Import also creates or refreshes /promo/lp-demo/ and /promo/field-notes-demo/ with ready-to-use Buy More, Mix & Match, and Cozy Bundle examples. Reimporting preserves saved sections on correctly configured pages and repairs demo pages whose saved template or section structure does not match their assigned template.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		self::render_result( $result );

		if ( ! empty( $result['time'] ) ) {
			echo '<p><strong>' . esc_html__( 'Last operation:', 'html-landing-page-builder-for-woocommerce' ) . '</strong> ' . esc_html( self::get_action_label( isset( $result['action'] ) ? $result['action'] : '' ) ) . ' — ' . esc_html( $result['time'] ) . '</p>';
		}

		if ( ! $woocommerce_ready ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'WooCommerce must be active to import or safely remove demo attributes.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div>';
		}

		echo '<h2>' . esc_html__( 'Import demo data', 'html-landing-page-builder-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Creates or updates Blankets, Hoodies, and Test Simple (Discount product1), their variations and galleries, and separate Color and Size attributes. It publishes /promo/lp-demo/ in the Cozy Mix template and /promo/field-notes-demo/ in the Field Notes template; both are connected to the imported WooCommerce products and offer rules. Repeating the import preserves saved sections on correctly configured pages and repairs demo pages whose saved template or section structure does not match their assigned template.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hlpb_import_demo_products">';
		wp_nonce_field( 'hlpb_import_demo_products' );
		echo '<button type="submit" class="button button-primary" ' . disabled( ! $woocommerce_ready, true, false ) . '>' . esc_html__( 'Import demo data', 'html-landing-page-builder-for-woocommerce' ) . '</button></form>';

		echo '<hr><h2>' . esc_html__( 'Remove demo data', 'html-landing-page-builder-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Deletes only products, variations, terms, attributes, and media files marked as imported by this plugin. Items that have been assigned to other content or cannot be verified as plugin-owned are kept and listed in the report.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete all plugin-imported demo data? This cannot be undone.', 'html-landing-page-builder-for-woocommerce' ) ) . '\');"><input type="hidden" name="action" value="hlpb_delete_demo_products">';
		wp_nonce_field( 'hlpb_delete_demo_products' );
		echo '<button type="submit" class="button button-secondary" ' . disabled( ! $woocommerce_ready || ! $has_owned_data, true, false ) . '>' . esc_html__( 'Delete all demo data', 'html-landing-page-builder-for-woocommerce' ) . '</button></form></div>';
	}

	/** Display the most recent operation as a status line and concise report. */
	private static function render_result( $result ) {
		if ( ! is_array( $result ) || empty( $result['status'] ) ) {
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Status:', 'html-landing-page-builder-for-woocommerce' ) . '</strong> ' . esc_html__( 'Demo data has not been imported.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div>';
			return;
		}

		$status = 'success' === $result['status'] ? 'success' : ( 'partial' === $result['status'] ? 'warning' : 'error' );
		echo '<div class="notice notice-' . esc_attr( $status ) . ' inline"><p><strong>' . esc_html__( 'Status:', 'html-landing-page-builder-for-woocommerce' ) . '</strong> ' . esc_html( self::get_status_label( $result['status'] ) ) . '</p>';
		if ( ! empty( $result['message'] ) ) {
			echo '<p>' . esc_html( $result['message'] ) . '</p>';
		}
		if ( ! empty( $result['report'] ) && is_array( $result['report'] ) ) {
			echo '<ul style="list-style:disc;margin-left:24px">';
			foreach ( $result['report'] as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/** Handle the import request from the activation notice or Import page. */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to import WooCommerce demo data.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		check_admin_referer( 'hlpb_import_demo_products' );
		self::prepare_import_runtime();

		if ( ! self::acquire_lock() ) {
			self::store_result( 'import', 'failed', __( 'Another demo data operation is already running. Wait a moment and retry.', 'html-landing-page-builder-for-woocommerce' ), array() );
			self::redirect_to_import_page();
		}

		self::$run_counts = self::empty_counts();
		self::$run_media  = array();
		$result           = self::run_import_package();
		self::release_lock();
		delete_option( self::PROMPT_OPTION );

		if ( is_wp_error( $result ) ) {
			$report = self::format_import_report( self::$run_counts );
			$report[] = __( 'The import stopped at an error. Run it again to safely resume; already marked demo records will be updated.', 'html-landing-page-builder-for-woocommerce' );
			self::store_result( 'import', 'partial', $result->get_error_message(), $report );
		} else {
			$status  = ! empty( $result['landing_error'] ) ? 'partial' : 'success';
			$message = ! empty( $result['landing_error'] )
				? __( 'Demo products and attributes were imported, but one or more ready-made landing pages could not be published.', 'html-landing-page-builder-for-woocommerce' )
				: __( 'Demo data import completed.', 'html-landing-page-builder-for-woocommerce' );
			self::store_result( 'import', $status, $message, $result['report'] );
		}
		self::redirect_to_import_page();
	}

	/** Run the activation import in place and return its progress result to the dialog. */
	public static function handle_activation_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to import WooCommerce demo data.', 'html-landing-page-builder-for-woocommerce' ) ), 403 );
		}
		check_ajax_referer( 'hlpb_activation_import', 'nonce' );
		self::prepare_import_runtime();

		if ( ! self::acquire_lock() ) {
			wp_send_json_error( array( 'message' => __( 'Another demo data operation is running. Wait a moment and try again.', 'html-landing-page-builder-for-woocommerce' ) ), 409 );
		}

		self::$run_counts = self::empty_counts();
		self::$run_media  = array();
		try {
			$result = self::run_import_package();
		} finally {
			self::release_lock();
		}

		if ( is_wp_error( $result ) ) {
			$report   = self::format_import_report( self::$run_counts );
			$report[] = __( 'The import stopped at an error. You can safely retry; already marked demo records will be updated.', 'html-landing-page-builder-for-woocommerce' );
			self::store_result( 'import', 'partial', $result->get_error_message(), $report );
			delete_option( self::PROMPT_OPTION );
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'report'  => $report,
				),
				500
			);
		}

		$status  = ! empty( $result['landing_error'] ) ? 'partial' : 'success';
		$message = ! empty( $result['landing_error'] )
			? __( 'Demo products and attributes were imported, but one or more ready-made landing pages could not be published.', 'html-landing-page-builder-for-woocommerce' )
			: __( 'Demo data import completed.', 'html-landing-page-builder-for-woocommerce' );
		self::store_result( 'import', $status, $message, $result['report'] );
		delete_option( self::PROMPT_OPTION );
		wp_send_json_success(
			array(
				'status'      => $status,
				'message'     => $message,
				'report'      => $result['report'],
				'landing_url'     => ! empty( $result['landing_url'] ) ? esc_url_raw( $result['landing_url'] ) : '',
				'field_notes_url' => ! empty( $result['field_notes_url'] ) ? esc_url_raw( $result['field_notes_url'] ) : '',
			)
		);
	}

	/** Give large image imports enough PHP resources and convert thrown errors to a response. */
	private static function prepare_import_runtime() {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** Run the importer and return an actionable error instead of leaving AJAX without JSON. */
	private static function run_import_package() {
		try {
			return self::import_package();
		} catch ( Throwable $error ) {
			error_log( 'HLPB demo import failed: ' . $error->getMessage() );
			return new WP_Error(
				'hlpb_demo_import_exception',
				__( 'The demo import stopped unexpectedly. Check the PHP error log before retrying.', 'html-landing-page-builder-for-woocommerce' )
			);
		}
	}

	/** Delete only plugin-marked demo records after a nonce and capability check. */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to delete WooCommerce demo data.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		check_admin_referer( 'hlpb_delete_demo_products' );

		if ( ! self::acquire_lock() ) {
			self::store_result( 'delete', 'failed', __( 'Another demo data operation is already running. Wait a moment and retry.', 'html-landing-page-builder-for-woocommerce' ), array() );
			self::redirect_to_import_page();
		}

		$result = self::delete_demo_data();
		self::release_lock();
		self::store_result( 'delete', ! empty( $result['skipped'] ) ? 'partial' : 'success', __( 'Demo data cleanup finished.', 'html-landing-page-builder-for-woocommerce' ), $result['report'] );
		self::redirect_to_import_page();
	}

	/** Dismiss the activation prompt without importing. */
	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		check_admin_referer( 'hlpb_dismiss_demo_import' );
		delete_option( self::PROMPT_OPTION );
		$return_to = isset( $_POST['hlpb_return_to'] ) ? sanitize_key( wp_unslash( $_POST['hlpb_return_to'] ) ) : '';
		if ( 'landings' === $return_to ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE ) );
			exit;
		}
		self::redirect_to_import_page();
	}

	/** Save an operation result so it remains visible on the Import page. */
	private static function store_result( $action, $status, $message, $report ) {
		update_option(
			self::RESULT_OPTION,
			array(
				'action'  => sanitize_key( $action ),
				'status'  => sanitize_key( $status ),
				'message' => sanitize_text_field( $message ),
				'report'  => array_map( 'sanitize_text_field', is_array( $report ) ? $report : array() ),
				'time'    => current_time( 'mysql' ),
			),
			false
		);
	}

	/** Redirect after an operation to the page that shows its persistent report. */
	private static function redirect_to_import_page() {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE . '&page=hlpb-demo-import' ) );
		exit;
	}

	/** Acquire a database-backed lock to prevent concurrent import or deletion. */
	private static function acquire_lock() {
		$locked_at = absint( get_option( self::LOCK_OPTION, 0 ) );
		if ( $locked_at && $locked_at < time() - 20 * MINUTE_IN_SECONDS ) {
			delete_option( self::LOCK_OPTION );
		}
		return add_option( self::LOCK_OPTION, time(), '', false );
	}

	/** Release the import/delete lock. */
	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	/** Return initialized operation counters. */
	private static function empty_counts() {
		return array(
			'products_created'    => 0,
			'products_updated'    => 0,
			'variations_created'  => 0,
			'variations_updated'  => 0,
			'attributes_created'  => 0,
			'attributes_updated'  => 0,
			'terms_created'       => 0,
			'terms_updated'       => 0,
			'terms_protected'     => 0,
			'media_created'       => 0,
			'media_reused'        => 0,
		);
	}

	/** Convert import counters to a short readable report. */
	private static function format_import_report( $counts ) {
		$counts = wp_parse_args( $counts, self::empty_counts() );
		return array(
			sprintf( __( 'Products: %1$d created, %2$d updated.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['products_created'] ), absint( $counts['products_updated'] ) ),
			sprintf( __( 'Variations: %1$d created, %2$d updated.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['variations_created'] ), absint( $counts['variations_updated'] ) ),
			sprintf( __( 'Attributes: %1$d created, %2$d refreshed.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['attributes_created'] ), absint( $counts['attributes_updated'] ) ),
			sprintf( __( 'Attribute terms: %1$d created, %2$d refreshed.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['terms_created'] ), absint( $counts['terms_updated'] ) ),
			sprintf( __( 'Media: %1$d copied, %2$d reused.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['media_created'] ), absint( $counts['media_reused'] ) ),
		);
		if ( ! empty( $counts['terms_protected'] ) ) {
			$report[] = sprintf( __( 'Terms used by other products were left unchanged: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['terms_protected'] ) );
		}
	}

	/** Human-readable labels for the most recent operation. */
	private static function get_action_label( $action ) {
		return 'delete' === $action ? __( 'Delete demo data', 'html-landing-page-builder-for-woocommerce' ) : __( 'Import demo data', 'html-landing-page-builder-for-woocommerce' );
	}

	/** Human-readable labels for operation status. */
	private static function get_status_label( $status ) {
		$labels = array(
			'success' => __( 'Completed successfully', 'html-landing-page-builder-for-woocommerce' ),
			'partial' => __( 'Completed with items kept or an import error', 'html-landing-page-builder-for-woocommerce' ),
			'failed'  => __( 'Failed', 'html-landing-page-builder-for-woocommerce' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'html-landing-page-builder-for-woocommerce' );
	}

	/** Query IDs carrying a plugin-owned marker. */
	private static function get_owned_ids( $post_type, $meta_key ) {
		return get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'inherit', 'auto-draft' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache'  => false,
				'update_post_term_cache'  => false,
				'meta_key'               => $meta_key,
				'meta_compare'           => 'EXISTS',
			)
		);
	}

	/** Find an attribute row only when both its recorded ID and slug match. */
	private static function find_attribute_record( $taxonomies, $attribute_id, $slug ) {
		if ( ! $attribute_id || '' === $slug ) {
			return false;
		}
		foreach ( $taxonomies as $taxonomy ) {
			if ( absint( $taxonomy->attribute_id ) === absint( $attribute_id ) && $slug === $taxonomy->attribute_name ) {
				return $taxonomy;
			}
		}
		return false;
	}

	/** Find a free attribute slug without taking over a store-owned attribute. */
	private static function get_available_attribute_slug( $taxonomies, $base_slug ) {
		$used = array();
		foreach ( $taxonomies as $taxonomy ) {
			$used[] = $taxonomy->attribute_name;
		}
		$candidate = $base_slug;
		$index     = 2;
		while ( in_array( $candidate, $used, true ) ) {
			$candidate = substr( $base_slug, 0, 26 - strlen( (string) $index ) ) . '-' . $index;
			++$index;
		}
		return $candidate;
	}

	/** Find a free term slug without renaming an existing store term. */
	private static function get_available_term_slug( $taxonomy, $base_slug ) {
		$candidate = $base_slug;
		$index     = 2;
		while ( get_term_by( 'slug', $candidate, $taxonomy ) ) {
			$suffix    = '-landing-demo-' . $index;
			$candidate = substr( $base_slug, 0, max( 1, 200 - strlen( $suffix ) ) ) . $suffix;
			++$index;
		}
		return $candidate;
	}

	/** Determine whether a marked term is assigned outside imported demo products. */
	private static function is_term_used_outside_demo( $term_id ) {
		$term = get_term( absint( $term_id ) );
		if ( ! $term || is_wp_error( $term ) ) {
			return true;
		}
		$objects = get_objects_in_term( absint( $term_id ), $term->taxonomy );
		if ( is_wp_error( $objects ) ) {
			return true;
		}
		$demo_products = array_flip( array_map( 'absint', self::get_owned_ids( 'product', self::PRODUCT_META ) ) );
		foreach ( $objects as $object_id ) {
			if ( ! isset( $demo_products[ absint( $object_id ) ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Remove marked demo products, terms, attributes, and unreferenced demo media. */
	private static function delete_demo_data() {
		if ( ! function_exists( 'wc_delete_attribute' ) ) {
			return array(
				'skipped' => 1,
				'report'  => array( __( 'WooCommerce is inactive; attributes were not removed.', 'html-landing-page-builder-for-woocommerce' ) ),
			);
		}

		$counts = array(
			'products'    => 0,
			'variations'  => 0,
			'terms'       => 0,
			'attributes'  => 0,
			'media'       => 0,
			'skipped'     => 0,
		);
		$product_ids   = self::get_owned_ids( 'product', self::PRODUCT_META );
		$variation_ids = self::get_owned_ids( 'product_variation', self::VARIATION_META );
		$demo_post_ids = array_merge( $product_ids, $variation_ids );

		foreach ( $variation_ids as $variation_id ) {
			if ( wp_delete_post( absint( $variation_id ), true ) ) {
				++$counts['variations'];
			} else {
				++$counts['skipped'];
			}
		}
		foreach ( $product_ids as $product_id ) {
			if ( wp_delete_post( absint( $product_id ), true ) ) {
				++$counts['products'];
			} else {
				++$counts['skipped'];
			}
		}

		$attribute_map = get_option( self::ATTRIBUTES_OPTION, array() );
		$remaining     = array();
		$taxonomies    = wc_get_attribute_taxonomies();
		foreach ( is_array( $attribute_map ) ? $attribute_map : array() as $source_slug => $owned ) {
			if ( ! is_array( $owned ) || empty( $owned['id'] ) || empty( $owned['slug'] ) ) {
				++$counts['skipped'];
				continue;
			}
			$record = self::find_attribute_record( $taxonomies, absint( $owned['id'] ), sanitize_title( $owned['slug'] ) );
			if ( ! $record ) {
				++$counts['skipped'];
				continue;
			}
			$taxonomy = wc_attribute_taxonomy_name( $record->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				$remaining[ $source_slug ] = $owned;
				++$counts['skipped'];
				continue;
			}

			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) {
				$remaining[ $source_slug ] = $owned;
				++$counts['skipped'];
				continue;
			}
			foreach ( $terms as $term ) {
				$term_key = get_term_meta( $term->term_id, self::TERM_META, true );
				if ( ! is_string( $term_key ) || 0 !== strpos( $term_key, sanitize_title( $source_slug ) . ':' ) ) {
					continue;
				}
				$objects = get_objects_in_term( $term->term_id, $taxonomy );
				if ( is_wp_error( $objects ) || ! empty( $objects ) ) {
					++$counts['skipped'];
					continue;
				}
				if ( wp_delete_term( $term->term_id, $taxonomy ) ) {
					++$counts['terms'];
				} else {
					++$counts['skipped'];
				}
			}

			$remaining_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( is_wp_error( $remaining_terms ) || ! empty( $remaining_terms ) ) {
				$remaining[ $source_slug ] = $owned;
				++$counts['skipped'];
				continue;
			}
			if ( wc_delete_attribute( absint( $record->attribute_id ) ) ) {
				++$counts['attributes'];
				delete_transient( 'wc_attribute_taxonomies' );
			} else {
				$remaining[ $source_slug ] = $owned;
				++$counts['skipped'];
			}
		}
		if ( empty( $remaining ) ) {
			delete_option( self::ATTRIBUTES_OPTION );
		} else {
			update_option( self::ATTRIBUTES_OPTION, $remaining, false );
		}

		$media_ids = self::get_owned_ids( 'attachment', self::MEDIA_META );
		foreach ( $media_ids as $media_id ) {
			if ( self::is_media_referenced_outside_demo( absint( $media_id ), $demo_post_ids ) ) {
				++$counts['skipped'];
				continue;
			}
			if ( wp_delete_attachment( absint( $media_id ), true ) ) {
				++$counts['media'];
			} else {
				++$counts['skipped'];
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			foreach ( $product_ids as $product_id ) {
				wc_delete_product_transients( absint( $product_id ) );
			}
		}
		$report = array(
			sprintf( __( 'Products deleted: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['products'] ) ),
			sprintf( __( 'Variations deleted: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['variations'] ) ),
			sprintf( __( 'Attribute terms deleted: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['terms'] ) ),
			sprintf( __( 'Attributes deleted: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['attributes'] ) ),
			sprintf( __( 'Unused imported media deleted: %d.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['media'] ) ),
		);
		if ( $counts['skipped'] ) {
			$report[] = sprintf( __( 'Items kept for safety or because deletion failed: %d. This includes media referenced elsewhere and attributes or terms that still have content assigned.', 'html-landing-page-builder-for-woocommerce' ), absint( $counts['skipped'] ) );
		} elseif ( ! $counts['products'] && ! $counts['attributes'] && ! $counts['media'] ) {
			$report[] = __( 'No marked demo data was found.', 'html-landing-page-builder-for-woocommerce' );
		}
		return array( 'skipped' => $counts['skipped'], 'report' => $report );
	}

	/** Keep an imported attachment when another piece of content refers to it. */
	private static function is_media_referenced_outside_demo( $media_id, $demo_post_ids ) {
		global $wpdb;
		$attachment = get_post( $media_id );
		if ( ! $attachment || ( $attachment->post_parent && ! in_array( absint( $attachment->post_parent ), array_map( 'absint', $demo_post_ids ), true ) ) ) {
			return true;
		}

		$exact_meta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_value = %s LIMIT 1",
				(string) $media_id
			)
		);
		if ( $exact_meta ) {
			return true;
		}
		$gallery_meta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND FIND_IN_SET(%s, meta_value) > 0 LIMIT 1",
				(string) $media_id
			)
		);
		if ( $gallery_meta ) {
			return true;
		}
		$term_meta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->termmeta} WHERE meta_value = %s LIMIT 1",
				(string) $media_id
			)
		);
		if ( $term_meta ) {
			return true;
		}

		$image_url = wp_get_attachment_url( $media_id );
		if ( $image_url ) {
			$content_match = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type <> 'attachment' LIMIT 1",
					'%' . $wpdb->esc_like( $image_url ) . '%'
				)
			);
			if ( $content_match ) {
				return true;
			}
		}
		return false;
	}

	/** Import attributes, term swatches, attachments, products, and variations. */
	private static function import_package() {
		if ( ! function_exists( 'wc_create_attribute' ) || ! class_exists( 'WC_Product_Variable' ) || ! class_exists( 'WC_Product_Variation' ) ) {
			return new WP_Error( 'hlpb_woocommerce_required', __( 'WooCommerce must be active to import the demo products.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$package_file = HLPB_LANDING_PAGE_BUILDER_PATH . 'demo-data/demo-products.json';
		$json         = is_readable( $package_file ) ? file_get_contents( $package_file ) : false;
		$package      = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $package ) || 1 !== absint( isset( $package['schema_version'] ) ? $package['schema_version'] : 0 ) || empty( $package['media'] ) || ! is_array( $package['media'] ) || empty( $package['attributes'] ) || ! is_array( $package['attributes'] ) || empty( $package['products'] ) || ! is_array( $package['products'] ) ) {
			return new WP_Error( 'hlpb_demo_package_invalid', __( 'The bundled demo data file is missing or invalid.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$attribute_map = array();
		foreach ( $package['attributes'] as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			$attribute_id = self::ensure_attribute( $attribute );
			if ( is_wp_error( $attribute_id ) ) {
				return $attribute_id;
			}
			$slug = sanitize_title( $attribute['slug'] );
			$attribute_map[ $slug ] = $attribute_id;
		}

		$term_map = array();
		foreach ( $package['attributes'] as $attribute ) {
			$slug = sanitize_title( $attribute['slug'] );
			if ( empty( $attribute_map[ $slug ] ) || empty( $attribute['terms'] ) || ! is_array( $attribute['terms'] ) ) {
				continue;
			}
			$taxonomy = $attribute_map[ $slug ]['taxonomy'];
			foreach ( $attribute['terms'] as $term_data ) {
				if ( ! is_array( $term_data ) || empty( $term_data['name'] ) || empty( $term_data['slug'] ) ) {
					continue;
				}
				$term_slug = sanitize_title( $term_data['slug'] );
				$term_key  = $slug . ':' . $term_slug;
				$owned_terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'number'     => 1,
						'meta_key'   => self::TERM_META,
						'meta_value' => $term_key,
					)
				);
				if ( is_wp_error( $owned_terms ) ) {
					return $owned_terms;
				}
				$term = ! empty( $owned_terms ) ? $owned_terms[0] : get_term_by( 'slug', $term_slug, $taxonomy );
				$is_owned_term = $term && $term_key === get_term_meta( $term->term_id, self::TERM_META, true );
				$protect_term = $is_owned_term && self::is_term_used_outside_demo( $term->term_id );
				if ( ! $term || ! $is_owned_term ) {
					$destination_slug = self::get_available_term_slug( $taxonomy, $term_slug );
					$inserted = wp_insert_term(
						sanitize_text_field( $term_data['name'] ),
						$taxonomy,
						array( 'slug' => $destination_slug )
					);
					if ( is_wp_error( $inserted ) ) {
						return $inserted;
					}
					$term = get_term( absint( $inserted['term_id'] ), $taxonomy );
					if ( ! $term || is_wp_error( $term ) ) {
						return new WP_Error( 'hlpb_demo_term_unavailable', __( 'WooCommerce could not load a newly created demo attribute term.', 'html-landing-page-builder-for-woocommerce' ) );
					}
					++self::$run_counts['terms_created'];
				} else {
					if ( $protect_term ) {
						++self::$run_counts['terms_protected'];
					} else {
						++self::$run_counts['terms_updated'];
					}
					if ( ! $protect_term && $term->name !== $term_data['name'] ) {
						$updated_term = wp_update_term( $term->term_id, $taxonomy, array( 'name' => sanitize_text_field( $term_data['name'] ) ) );
						if ( is_wp_error( $updated_term ) ) {
							return $updated_term;
						}
						$term = get_term( $term->term_id, $taxonomy );
					}
				}
				$term_id = absint( $term->term_id );
				if ( ! $is_owned_term ) {
					update_term_meta( $term_id, self::TERM_META, $term_key );
				}

				if ( ! $protect_term ) {
					$color = isset( $term_data['color'] ) ? sanitize_hex_color( $term_data['color'] ) : false;
					if ( $color ) {
						update_term_meta( $term_id, HLPB_Attribute_Admin::COLOR_META, $color );
					} else {
						delete_term_meta( $term_id, HLPB_Attribute_Admin::COLOR_META );
					}

					$image_source_id = isset( $term_data['image'] ) ? absint( $term_data['image'] ) : 0;
					if ( $image_source_id ) {
						$image_id = self::import_media( $image_source_id, $package['media'] );
						if ( is_wp_error( $image_id ) ) {
							return $image_id;
						}
						update_term_meta( $term_id, HLPB_Attribute_Admin::IMAGE_META, $image_id );
					} else {
						delete_term_meta( $term_id, HLPB_Attribute_Admin::IMAGE_META );
					}
				}
				$term_map[ $slug ][ $term_slug ] = array(
					'id'   => $term_id,
					'slug' => $term->slug,
				);
			}
		}

		$counts       = array( 'created' => 0, 'updated' => 0 );
		$product_ids  = array();
		foreach ( $package['products'] as $product_data ) {
			if ( ! is_array( $product_data ) || empty( $product_data['source_slug'] ) || empty( $product_data['type'] ) ) {
				continue;
			}
			$product = self::import_product( $product_data, $package['media'], $attribute_map, $term_map );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			$counts[ ! empty( $product['created'] ) ? 'created' : 'updated' ]++;
			$product_ids[ sanitize_title( $product_data['source_slug'] ) ] = absint( $product['id'] );
		}

		self::$run_counts['products_created'] = $counts['created'];
		self::$run_counts['products_updated'] = $counts['updated'];
		$report         = self::format_import_report( self::$run_counts );
		$landing_result = self::create_default_landing( $product_ids );
		if ( is_wp_error( $landing_result ) ) {
			$report[] = sprintf(
				/* translators: %s is the reason the default demo landing page could not be published. */
				__( 'Default landing page /promo/lp-demo/ was not published: %s', 'html-landing-page-builder-for-woocommerce' ),
				$landing_result->get_error_message()
			);
			return array( 'report' => $report, 'landing_error' => true );
		}

		$report[] = sprintf(
			/* translators: %s is the URL of the default demo landing page. */
			__( 'Default demo landing page is available at %s', 'html-landing-page-builder-for-woocommerce' ),
			$landing_result
		);
		$field_notes_result = self::create_field_notes_landing( $product_ids );
		if ( is_wp_error( $field_notes_result ) ) {
			$report[] = sprintf(
				/* translators: %s is the reason the Field Notes demo landing page could not be published. */
				__( 'Field Notes demo landing page /promo/field-notes-demo/ was not published: %s', 'html-landing-page-builder-for-woocommerce' ),
				$field_notes_result->get_error_message()
			);
			return array( 'report' => $report, 'landing_error' => true, 'landing_url' => $landing_result );
		}
		$report[] = sprintf(
			/* translators: %s is the URL of the Field Notes demo landing page. */
			__( 'Field Notes apparel demo landing page is available at %s', 'html-landing-page-builder-for-woocommerce' ),
			$field_notes_result
		);
		return array(
			'report'          => $report,
			'landing_url'     => $landing_result,
			'field_notes_url' => $field_notes_result,
		);
	}

	/** Create or refresh the plugin-owned default landing page after demo import. */
	private static function create_default_landing( $product_ids ) {
		$hoodie_id = absint( $product_ids['hoodies'] ?? 0 );
		$blanket_id = absint( $product_ids['blankets'] ?? 0 );
		if ( ! $hoodie_id || ! $blanket_id || ! wc_get_product( $hoodie_id ) || ! wc_get_product( $blanket_id ) ) {
			return new WP_Error( 'hlpb_demo_landing_products_missing', __( 'The imported Hoodies and Blankets products could not be loaded.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$landing_id = 0;
		foreach ( self::get_owned_ids( HLPB_Landing_Builder::POST_TYPE, self::LANDING_META ) as $owned_id ) {
			if ( 'lp-demo' === get_post_field( 'post_name', $owned_id ) || 'cozy-mix' === get_post_meta( $owned_id, HLPB_Landing_Builder::TEMPLATE_KEY, true ) ) {
				$landing_id = absint( $owned_id );
				break;
			}
		}
		if ( $landing_id && 'trash' === get_post_status( $landing_id ) ) {
			return new WP_Error( 'hlpb_demo_landing_trashed', __( 'The generated demo landing page is in the Trash. Restore it before importing again.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		if ( ! $landing_id ) {
			$slug_conflicts = get_posts(
				array(
					'post_type'      => HLPB_Landing_Builder::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
					'name'           => 'lp-demo',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $slug_conflicts ) ) {
				return new WP_Error( 'hlpb_demo_landing_slug_conflict', __( 'A different landing page already uses the lp-demo folder.', 'html-landing-page-builder-for-woocommerce' ) );
			}

			$sections = HLPB_Landing_Builder::get_default_sections( 'cozy-mix' );
			$cart_data = HLPB_Cart_Section::defaults();
			$cart_data['modes']['buy']['products']    = array( $hoodie_id );
			$cart_data['modes']['mix']['products']    = array( $hoodie_id, $blanket_id );
			$cart_data['modes']['bundle']['products'] = array( $hoodie_id, $blanket_id );
			$cart_data = HLPB_Cart_Section::sanitize( $cart_data );
			foreach ( $sections as &$section ) {
				if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
					$section = array_merge( $section, $cart_data );
					break;
				}
			}
			unset( $section );

			$landing_id = wp_insert_post(
				array(
					'post_type'   => HLPB_Landing_Builder::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => __( 'LP Demo', 'html-landing-page-builder-for-woocommerce' ),
					'post_name'   => 'lp-demo',
				),
				true
			);
			if ( is_wp_error( $landing_id ) ) {
				return $landing_id;
			}

			$landing_id = absint( $landing_id );
			update_post_meta( $landing_id, self::LANDING_META, '1' );
			update_post_meta( $landing_id, HLPB_Landing_Builder::TEMPLATE_KEY, 'cozy-mix' );
			update_post_meta( $landing_id, HLPB_Landing_Builder::META_KEY, $sections );
			update_post_meta( $landing_id, HLPB_Landing_Builder::EXPORT_PROMO_KEY, 'promo' );
			update_post_meta( $landing_id, HLPB_Landing_Builder::EXPORT_SLUG_KEY, 'lp-demo' );
		}

		$sections = HLPB_Landing_Builder::get_sections( $landing_id );
		$filtered_sections = array_values(
			array_filter(
				$sections,
				static function ( $section ) {
					return ! is_array( $section ) || ! isset( $section['type'] ) || 'limited_stock' !== $section['type'];
				}
			)
		);
		if ( count( $filtered_sections ) !== count( $sections ) ) {
			$sections = $filtered_sections;
			update_post_meta( $landing_id, HLPB_Landing_Builder::META_KEY, $sections );
		}
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				HLPB_Cart_Section::ensure_coupons( $landing_id, $section );
				break;
			}
		}

		return HLPB_Landing_Builder::publish_static_export( $landing_id );
	}

	/** Create or refresh the ready-to-use Field Notes apparel landing page. */
	private static function create_field_notes_landing( $product_ids ) {
		$hoodie_id  = absint( $product_ids['hoodies'] ?? 0 );
		$blanket_id = absint( $product_ids['blankets'] ?? 0 );
		if ( ! $hoodie_id || ! $blanket_id || ! wc_get_product( $hoodie_id ) || ! wc_get_product( $blanket_id ) ) {
			return new WP_Error( 'hlpb_field_notes_products_missing', __( 'The imported Hoodies and Blankets products could not be loaded for the Field Notes landing page.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$templates = HLPB_Landing_Builder::get_templates();
		if ( ! isset( $templates['field-notes'] ) ) {
			return new WP_Error( 'hlpb_field_notes_template_missing', __( 'The Field Notes template files are missing or incomplete. Reinstall the plugin package and retry the demo import.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$landing_id = 0;
		foreach ( self::get_owned_ids( HLPB_Landing_Builder::POST_TYPE, self::LANDING_META ) as $owned_id ) {
			if ( 'field-notes-demo' === get_post_field( 'post_name', $owned_id ) ) {
				$landing_id = absint( $owned_id );
				break;
			}
		}
		if ( $landing_id && 'trash' === get_post_status( $landing_id ) ) {
			return new WP_Error( 'hlpb_field_notes_landing_trashed', __( 'The generated Field Notes landing page is in the Trash. Restore it before importing again.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$created = false;
		if ( ! $landing_id ) {
			$slug_conflicts = get_posts(
				array(
					'post_type'      => HLPB_Landing_Builder::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
					'name'           => 'field-notes-demo',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $slug_conflicts ) ) {
				return new WP_Error( 'hlpb_field_notes_landing_slug_conflict', __( 'A different landing page already uses the field-notes-demo folder.', 'html-landing-page-builder-for-woocommerce' ) );
			}

			$landing_id = wp_insert_post(
				array(
					'post_type'   => HLPB_Landing_Builder::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => __( 'Field Notes Demo', 'html-landing-page-builder-for-woocommerce' ),
					'post_name'   => 'field-notes-demo',
				),
				true
			);
			if ( is_wp_error( $landing_id ) ) {
				return $landing_id;
			}
			$landing_id = absint( $landing_id );
			$created    = true;
		}

		$stored_template = sanitize_key( get_post_meta( $landing_id, HLPB_Landing_Builder::TEMPLATE_KEY, true ) );
		$sections        = get_post_meta( $landing_id, HLPB_Landing_Builder::META_KEY, true );
		$refresh_sections = $created || 'field-notes' !== $stored_template || ! is_array( $sections ) || empty( $sections );
		$allowed_types = array_keys( HLPB_Landing_Builder::get_section_types( 'field-notes' ) );
		$has_cart       = false;
		if ( ! $refresh_sections ) {
			foreach ( $sections as $section ) {
				$type = is_array( $section ) && isset( $section['type'] ) ? sanitize_key( $section['type'] ) : '';
				if ( ! $type || ( ! in_array( $type, $allowed_types, true ) && ! in_array( $type, array( 'fn_fabric', 'fn_capsule' ), true ) ) ) {
					$refresh_sections = true;
					break;
				}
				if ( 'cart_section' === $type ) {
					$has_cart = true;
				}
			}
			if ( ! $has_cart ) {
				$refresh_sections = true;
			}
		}

		if ( $refresh_sections ) {
			$sections = self::build_field_notes_demo_sections( $hoodie_id, $blanket_id );
			if ( empty( $sections ) ) {
				return new WP_Error( 'hlpb_field_notes_sections_missing', __( 'The Field Notes template does not contain a usable set of editable sections.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			update_post_meta( $landing_id, HLPB_Landing_Builder::META_KEY, $sections );
		}

		// Correct legacy demo records as well as newly created ones so the editor exposes this template's sections.
		update_post_meta( $landing_id, self::LANDING_META, 'field-notes' );
		update_post_meta( $landing_id, HLPB_Landing_Builder::TEMPLATE_KEY, 'field-notes' );
		update_post_meta( $landing_id, HLPB_Landing_Builder::EXPORT_PROMO_KEY, 'promo' );
		update_post_meta( $landing_id, HLPB_Landing_Builder::EXPORT_SLUG_KEY, 'field-notes-demo' );

		$sections = HLPB_Landing_Builder::get_sections( $landing_id );
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				HLPB_Cart_Section::ensure_coupons( $landing_id, $section );
				break;
			}
		}

		return HLPB_Landing_Builder::publish_static_export( $landing_id );
	}

	/** Build the current Field Notes demo layout with the imported products in its offer calculator. */
	private static function build_field_notes_demo_sections( $hoodie_id, $blanket_id ) {
		$sections  = HLPB_Landing_Builder::get_default_sections( 'field-notes' );
		$cart_data = HLPB_Cart_Section::defaults();
		$cart_data['modes']['buy']['products']    = array( absint( $hoodie_id ) );
		$cart_data['modes']['mix']['products']    = array( absint( $hoodie_id ), absint( $blanket_id ) );
		$cart_data['modes']['bundle']['products'] = array( absint( $hoodie_id ), absint( $blanket_id ) );
		$cart_data = HLPB_Cart_Section::sanitize( $cart_data );
		foreach ( $sections as &$section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				$section = array_merge( $section, $cart_data );
				break;
			}
		}
		unset( $section );
		return $sections;
	}

	/** Create or update an isolated plugin-owned WooCommerce attribute. */
	private static function ensure_attribute( $attribute ) {
		if ( empty( $attribute['slug'] ) || empty( $attribute['label'] ) ) {
			return new WP_Error( 'hlpb_demo_attribute_invalid', __( 'A demo attribute is missing its name or slug.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$source_slug = sanitize_title( $attribute['slug'] );
		$base_slug   = sanitize_title( $source_slug . '-landing-demo' );
		$type = isset( $attribute['type'] ) ? sanitize_key( $attribute['type'] ) : 'select';
		if ( ! in_array( $type, array( 'select', 'color-image-picker', 'button' ), true ) ) {
			return new WP_Error( 'hlpb_demo_attribute_type_invalid', __( 'The demo package contains an unsupported attribute display type.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$stored_map = get_option( self::ATTRIBUTES_OPTION, array() );
		$stored     = isset( $stored_map[ $source_slug ] ) && is_array( $stored_map[ $source_slug ] ) ? $stored_map[ $source_slug ] : array();
		$taxonomies = wc_get_attribute_taxonomies();
		$existing   = self::find_attribute_record( $taxonomies, isset( $stored['id'] ) ? absint( $stored['id'] ) : 0, isset( $stored['slug'] ) ? sanitize_title( $stored['slug'] ) : '' );
		$existing_id = $existing ? absint( $existing->attribute_id ) : 0;
		$slug = $existing ? $existing->attribute_name : self::get_available_attribute_slug( $taxonomies, $base_slug );

		$allow_demo_types = static function ( $types ) {
			$types['color-image-picker'] = __( 'Color image picker', 'html-landing-page-builder-for-woocommerce' );
			$types['button']             = __( 'Button', 'html-landing-page-builder-for-woocommerce' );
			return $types;
		};
		add_filter( 'product_attributes_type_selector', $allow_demo_types, 20 );
		$args = array(
			'name'        => sanitize_text_field( $attribute['label'] . ' ' . self::NAME_SUFFIX ),
			'slug'        => $slug,
			'type'        => $type,
			'order_by'    => isset( $attribute['order_by'] ) ? sanitize_key( $attribute['order_by'] ) : 'menu_order',
			'has_archives'=> false,
		);
		$result = $existing_id ? wc_update_attribute( $existing_id, $args ) : wc_create_attribute( $args );
		remove_filter( 'product_attributes_type_selector', $allow_demo_types, 20 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $existing_id ) {
			++self::$run_counts['attributes_updated'];
		} else {
			++self::$run_counts['attributes_created'];
		}
		$stored_map[ $source_slug ] = array( 'id' => absint( $result ), 'slug' => $slug );
		update_option( self::ATTRIBUTES_OPTION, $stored_map, false );

		delete_transient( 'wc_attribute_taxonomies' );
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			$attribute_data = wc_get_attribute( absint( $result ) );
			if ( ! $attribute_data ) {
				return new WP_Error( 'hlpb_demo_attribute_taxonomy_missing', __( 'WooCommerce could not load a demo product attribute.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$label = $attribute_data->name ? $attribute_data->name : $slug;
			$taxonomy_data = array(
				'hierarchical'          => false,
				'update_count_callback' => '_update_post_term_count',
				'labels'                => array(
					'name'              => sprintf( __( 'Product %s', 'woocommerce' ), $label ),
					'singular_name'     => $label,
					'search_items'      => sprintf( __( 'Search %s', 'woocommerce' ), $label ),
					'all_items'         => sprintf( __( 'All %s', 'woocommerce' ), $label ),
					'edit_item'         => sprintf( __( 'Edit %s', 'woocommerce' ), $label ),
					'update_item'       => sprintf( __( 'Update %s', 'woocommerce' ), $label ),
					'add_new_item'      => sprintf( __( 'Add new %s', 'woocommerce' ), $label ),
					'new_item_name'     => sprintf( __( 'New %s', 'woocommerce' ), $label ),
					'menu_name'         => $label,
				),
				'show_ui'               => true,
				'show_in_quick_edit'    => false,
				'show_in_menu'          => false,
				'meta_box_cb'           => false,
				'query_var'             => false,
				'rewrite'               => false,
				'public'                => false,
				'show_in_nav_menus'     => false,
				'capabilities'          => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			);
			$taxonomy_data = apply_filters( "woocommerce_taxonomy_args_{$taxonomy}", $taxonomy_data );
			register_taxonomy( $taxonomy, apply_filters( "woocommerce_taxonomy_objects_{$taxonomy}", array( 'product' ) ), $taxonomy_data );
			global $wc_product_attributes;
			if ( ! is_array( $wc_product_attributes ) ) {
				$wc_product_attributes = array();
			}
			$wc_product_attributes[ $taxonomy ] = $attribute_data;
		}
		return array(
			'id'       => absint( $result ),
			'slug'     => $slug,
			'taxonomy' => $taxonomy,
		);
	}

	/** Import or reuse a package attachment and return its local attachment ID. */
	private static function import_media( $source_id, $media_map ) {
		$source_id = absint( $source_id );
		if ( ! $source_id ) {
			return 0;
		}
		$media_key = self::MEDIA_PREFIX . $source_id;
		$existing  = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::MEDIA_META,
				'meta_value'     => $media_key,
			)
		);
		if ( ! empty( $existing[0] ) && wp_attachment_is_image( $existing[0] ) ) {
			self::note_media( $source_id, 'reused' );
			return absint( $existing[0] );
		}

		$relative = isset( $media_map[ (string) $source_id ] ) ? (string) $media_map[ (string) $source_id ] : '';
		if ( ! preg_match( '~^media/[A-Za-z0-9_.-]+$~', $relative ) ) {
			return new WP_Error( 'hlpb_demo_media_missing', __( 'A demo product image is missing from the plugin package.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$media_root = realpath( HLPB_LANDING_PAGE_BUILDER_PATH . 'demo-data/media' );
		$file       = realpath( HLPB_LANDING_PAGE_BUILDER_PATH . 'demo-data/' . $relative );
		$normalized_media_root = $media_root ? trailingslashit( wp_normalize_path( $media_root ) ) : '';
		$normalized_file       = $file ? wp_normalize_path( $file ) : '';
		$path_is_inside_media  = $normalized_media_root && $normalized_file && ( '\\' === DIRECTORY_SEPARATOR
			? 0 === stripos( $normalized_file, $normalized_media_root )
			: 0 === strpos( $normalized_file, $normalized_media_root )
		);
		if ( ! $media_root || ! $file || ! $path_is_inside_media || ! is_readable( $file ) ) {
			return new WP_Error( 'hlpb_demo_media_unreadable', __( 'A bundled demo product image cannot be read.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$filename = sanitize_file_name( basename( $file ) );
		$filetype = wp_check_filetype( $filename );
		$temp_file = wp_tempnam( $filename );
		if ( ! $temp_file || ! copy( $file, $temp_file ) ) {
			if ( $temp_file && file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return new WP_Error( 'hlpb_demo_media_copy_failed', __( 'WordPress could not prepare a bundled demo image for import.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'type'     => $filetype['type'],
				'tmp_name' => $temp_file,
				'error'    => 0,
				'size'     => filesize( $temp_file ),
			),
			0
		);
		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return $attachment_id;
		}
		update_post_meta( $attachment_id, self::MEDIA_META, $media_key );
		self::note_media( $source_id, 'created' );
		return absint( $attachment_id );
	}

	/** Count each source image only once in the operation report. */
	private static function note_media( $source_id, $state ) {
		$source_id = absint( $source_id );
		if ( isset( self::$run_media[ $source_id ] ) ) {
			return;
		}
		self::$run_media[ $source_id ] = $state;
		++self::$run_counts[ 'media_' . $state ];
	}

	/** Import a simple or variable product and its variation gallery data. */
	private static function import_product( $data, $media_map, $attribute_map, $term_map ) {
		$source_slug = sanitize_title( $data['source_slug'] );
		$type        = sanitize_key( $data['type'] );
		if ( ! in_array( $type, array( 'simple', 'variable' ), true ) || '' === $source_slug ) {
			return new WP_Error( 'hlpb_demo_product_invalid', __( 'The demo package contains an invalid product.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$existing_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::PRODUCT_META,
				'meta_value'     => $source_slug,
			)
		);
		$is_new = empty( $existing_ids[0] );
		$product = $is_new ? ( 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple() ) : wc_get_product( absint( $existing_ids[0] ) );
		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'hlpb_demo_product_unavailable', __( 'WooCommerce could not load a demo product for updating.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$product->set_name( sanitize_text_field( $data['name'] ) . ' ' . self::NAME_SUFFIX );
		$desired_slug = sanitize_title( $source_slug . '-landing-demo' );
		$product->set_slug( wp_unique_post_slug( $desired_slug, $product->get_id(), 'publish', 'product', 0 ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( self::make_unique_sku( isset( $data['sku'] ) ? $data['sku'] : $source_slug, 'landing-demo', $product->get_id() ) );
		$product->set_description( wp_kses_post( isset( $data['description'] ) ? $data['description'] : '' ) );
		$product->set_short_description( wp_kses_post( isset( $data['short_description'] ) ? $data['short_description'] : '' ) );
		$product->set_regular_price( wc_format_decimal( isset( $data['price'] ) ? $data['price'] : '' ) );
		$product->set_sale_price( '' );
		$product->set_price( wc_format_decimal( isset( $data['price'] ) ? $data['price'] : '' ) );
		$product->set_tax_status( 'taxable' );
		$product->set_reviews_allowed( true );
		$product->set_manage_stock( false );
		$product->set_backorders( 'no' );
		$product->set_stock_status( self::valid_stock_status( isset( $data['stock_status'] ) ? $data['stock_status'] : 'instock' ) );

		$image_id = self::import_media( isset( $data['image'] ) ? $data['image'] : 0, $media_map );
		if ( is_wp_error( $image_id ) ) {
			return $image_id;
		}
		$product->set_image_id( $image_id );
		$gallery = self::import_gallery( isset( $data['gallery'] ) ? $data['gallery'] : array(), $media_map );
		if ( is_wp_error( $gallery ) ) {
			return $gallery;
		}
		$product->set_gallery_image_ids( $gallery );

		$product_attributes = self::build_product_attributes( isset( $data['attributes'] ) ? $data['attributes'] : array(), $attribute_map, $term_map );
		if ( is_wp_error( $product_attributes ) ) {
			return $product_attributes;
		}
		$product->set_attributes( $product_attributes );
		$default_attributes = array();
		if ( ! empty( $data['default_attributes'] ) && is_array( $data['default_attributes'] ) ) {
			foreach ( $data['default_attributes'] as $attribute_slug => $term_slug ) {
				$attribute_slug = sanitize_title( $attribute_slug );
				if ( isset( $attribute_map[ $attribute_slug ], $term_map[ $attribute_slug ][ sanitize_title( $term_slug ) ] ) ) {
					$default_attributes[ $attribute_map[ $attribute_slug ]['taxonomy'] ] = $term_map[ $attribute_slug ][ sanitize_title( $term_slug ) ]['slug'];
				}
			}
		}
		$product->set_default_attributes( $default_attributes );
		$product->update_meta_data( self::PRODUCT_META, $source_slug );
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'hlpb_demo_product_save_failed', __( 'WooCommerce could not save a demo product.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		if ( 'variable' === $type ) {
			$variations = isset( $data['variations'] ) && is_array( $data['variations'] ) ? $data['variations'] : array();
			foreach ( $variations as $variation_data ) {
				$result = self::import_variation( $product_id, $source_slug, $variation_data, $data, $media_map, $attribute_map, $term_map );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			WC_Product_Variable::sync( $product_id );
		}
		wc_delete_product_transients( $product_id );
		return array( 'id' => $product_id, 'created' => $is_new );
	}

	/** Build WooCommerce taxonomy attributes using imported term IDs. */
	private static function build_product_attributes( $definitions, $attribute_map, $term_map ) {
		$attributes = array();
		foreach ( $definitions as $position => $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['slug'] ) ) {
				continue;
			}
			$slug = sanitize_title( $definition['slug'] );
			if ( ! isset( $attribute_map[ $slug ] ) ) {
				return new WP_Error( 'hlpb_demo_attribute_unavailable', __( 'A demo product refers to a missing global attribute.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$options = array();
			foreach ( isset( $definition['options'] ) && is_array( $definition['options'] ) ? $definition['options'] : array() as $term_slug ) {
				$term_slug = sanitize_title( $term_slug );
				if ( ! isset( $term_map[ $slug ][ $term_slug ] ) ) {
					return new WP_Error( 'hlpb_demo_term_unavailable', __( 'A demo product variation refers to a missing attribute term.', 'html-landing-page-builder-for-woocommerce' ) );
				}
				$options[] = absint( $term_map[ $slug ][ $term_slug ]['id'] );
			}
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( absint( $attribute_map[ $slug ]['id'] ) );
			$attribute->set_name( $attribute_map[ $slug ]['taxonomy'] );
			$attribute->set_options( $options );
			$attribute->set_position( absint( $position ) );
			$attribute->set_visible( ! empty( $definition['visible'] ) );
			$attribute->set_variation( ! empty( $definition['variation'] ) );
			$attributes[] = $attribute;
		}
		return $attributes;
	}

	/** Import or update one variation and its gallery. */
	private static function import_variation( $product_id, $source_slug, $data, $product_data, $media_map, $attribute_map, $term_map ) {
		if ( empty( $data['attributes'] ) || ! is_array( $data['attributes'] ) ) {
			return new WP_Error( 'hlpb_demo_variation_invalid', __( 'A demo variation is missing its attributes.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$variation_attributes = array();
		$key_parts = array();
		foreach ( $data['attributes'] as $attribute_slug => $term_slug ) {
			$attribute_slug = sanitize_title( $attribute_slug );
			$term_slug      = sanitize_title( $term_slug );
			if ( ! isset( $attribute_map[ $attribute_slug ], $term_map[ $attribute_slug ][ $term_slug ] ) ) {
				return new WP_Error( 'hlpb_demo_variation_term_missing', __( 'A demo variation refers to a missing attribute term.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$variation_attributes[ $attribute_map[ $attribute_slug ]['taxonomy'] ] = $term_map[ $attribute_slug ][ $term_slug ]['slug'];
			$key_parts[] = $attribute_slug . '=' . $term_slug;
		}
		$variation_key = $source_slug . ':' . implode( '|', $key_parts );
		$variation_id  = 0;
		foreach ( wc_get_product( $product_id )->get_children() as $child_id ) {
			if ( $variation_key === get_post_meta( $child_id, self::VARIATION_META, true ) ) {
				$variation_id = absint( $child_id );
				break;
			}
		}
		$variation = $variation_id ? wc_get_product( $variation_id ) : new WC_Product_Variation();
		if ( ! $variation instanceof WC_Product_Variation ) {
			return new WP_Error( 'hlpb_demo_variation_unavailable', __( 'WooCommerce could not load a demo variation for updating.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$variation->set_parent_id( absint( $product_id ) );
		$variation->set_status( 'publish' );
		$variation->set_attributes( $variation_attributes );
		$base_sku = isset( $product_data['sku'] ) ? $product_data['sku'] : $source_slug;
		$variation->set_sku( self::make_unique_sku( $base_sku, 'landing-demo-' . implode( '-', array_values( $data['attributes'] ) ), $variation->get_id() ) );
		$price = wc_format_decimal( isset( $data['price'] ) ? $data['price'] : ( isset( $product_data['price'] ) ? $product_data['price'] : '' ) );
		$variation->set_regular_price( $price );
		$variation->set_sale_price( '' );
		$variation->set_price( $price );
		$manage_stock = ! empty( $data['manage_stock'] );
		$variation->set_manage_stock( $manage_stock );
		$variation->set_backorders( self::valid_backorders( isset( $data['backorders'] ) ? $data['backorders'] : 'no' ) );
		if ( $manage_stock && isset( $data['stock_quantity'] ) ) {
			$variation->set_stock_quantity( wc_stock_amount( $data['stock_quantity'] ) );
		}
		$variation->set_stock_status( self::valid_stock_status( isset( $data['stock_status'] ) ? $data['stock_status'] : 'instock' ) );

		$image_id = self::import_media( isset( $data['image'] ) ? $data['image'] : 0, $media_map );
		if ( is_wp_error( $image_id ) ) {
			return $image_id;
		}
		$variation->set_image_id( $image_id );
		$gallery = self::import_gallery( isset( $data['gallery'] ) ? $data['gallery'] : array(), $media_map );
		if ( is_wp_error( $gallery ) ) {
			return $gallery;
		}
		$variation->set_gallery_image_ids( $gallery );
		$variation->update_meta_data( self::VARIATION_META, $variation_key );
		$saved_id = $variation->save();
		if ( ! $saved_id ) {
			return new WP_Error( 'hlpb_demo_variation_save_failed', __( 'WooCommerce could not save a demo variation.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		++self::$run_counts[ $variation_id ? 'variations_updated' : 'variations_created' ];
		wc_delete_product_transients( $saved_id );
		return $saved_id;
	}

	/** Resolve a list of package media IDs to local attachment IDs. */
	private static function import_gallery( $source_ids, $media_map ) {
		$gallery = array();
		foreach ( is_array( $source_ids ) ? $source_ids : array() as $source_id ) {
			$image_id = self::import_media( $source_id, $media_map );
			if ( is_wp_error( $image_id ) ) {
				return $image_id;
			}
			if ( $image_id ) {
				$gallery[] = absint( $image_id );
			}
		}
		return $gallery;
	}

	/** Return a valid SKU that does not collide with a product outside this record. */
	private static function make_unique_sku( $base, $suffix, $exclude_id = 0 ) {
		$base   = sanitize_title( (string) $base );
		$suffix = sanitize_title( (string) $suffix );
		for ( $index = 1; $index < 1000; $index++ ) {
			$extra     = 1 === $index ? '' : '-' . $index;
			$tail      = '-' . $suffix . $extra;
			$available = max( 1, 90 - strlen( $tail ) );
			$sku       = substr( $base, 0, $available ) . $tail;
			$found_id  = function_exists( 'wc_get_product_id_by_sku' ) ? absint( wc_get_product_id_by_sku( $sku ) ) : 0;
			if ( ! $found_id || $found_id === absint( $exclude_id ) ) {
				return $sku;
			}
		}
		return substr( $base, 0, 70 ) . '-' . wp_generate_password( 12, false, false );
	}

	/** Keep stock status within WooCommerce's supported values. */
	private static function valid_stock_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $status : 'instock';
	}

	/** Keep backorder settings within WooCommerce's supported values. */
	private static function valid_backorders( $backorders ) {
		$backorders = sanitize_key( $backorders );
		return in_array( $backorders, array( 'no', 'notify', 'yes' ), true ) ? $backorders : 'no';
	}

}
