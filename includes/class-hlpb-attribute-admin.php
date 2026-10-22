<?php
/**
 * WooCommerce attribute display types and per-term visual data.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Attribute_Admin {
	const COLOR_META = '_hlpb_swatch_color';
	const IMAGE_META = '_hlpb_swatch_image_id';

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'product_attributes_type_selector', array( __CLASS__, 'add_attribute_types' ) );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'render_variation_options' ), 20, 2 );
		add_action( 'woocommerce_after_register_taxonomy', array( __CLASS__, 'register_term_hooks' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Replace WooCommerce variation dropdowns with controls for visual attribute types.
	 * The native select stays in the markup so WooCommerce continues to handle
	 * variation matching, availability, stock, price, and gallery updates.
	 *
	 * @param string $html  Native dropdown markup.
	 * @param array  $args  Dropdown arguments supplied by WooCommerce.
	 * @return string
	 */
	public static function render_variation_options( $html, $args ) {
		$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		$product   = isset( $args['product'] ) ? $args['product'] : false;
		$options   = isset( $args['options'] ) ? (array) $args['options'] : array();

		if ( ! $attribute || ! ( $product instanceof WC_Product ) || ! taxonomy_exists( $attribute ) ) {
			return $html;
		}

		$type = self::get_attribute_type( $attribute );
		if ( ! in_array( $type, array( 'color-image-picker', 'button' ), true ) ) {
			return $html;
		}

		$terms = wc_get_product_terms(
			$product->get_id(),
			$attribute,
			array(
				'fields' => 'all',
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return $html;
		}

		$available = array_map( 'strval', $options );
		$selected  = isset( $args['selected'] ) ? sanitize_title( $args['selected'] ) : '';
		$label     = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $attribute, $product ) : $attribute;
		$classes   = 'hlpb-variation-options hlpb-variation-options--' . ( 'button' === $type ? 'button' : 'color' );
		$buttons   = '';

		foreach ( $terms as $term ) {
			if ( ! in_array( (string) $term->slug, $available, true ) ) {
				continue;
			}

			$name        = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product );
			$is_active   = $selected === $term->slug;
			$button_class = 'hlpb-variation-option' . ( $is_active ? ' is-selected' : '' );

			if ( 'button' === $type ) {
				$buttons .= sprintf(
					'<button type="button" class="%1$s" data-value="%2$s" aria-label="%3$s" aria-pressed="%4$s" title="%3$s"><span>%5$s</span></button>',
					esc_attr( $button_class ),
					esc_attr( $term->slug ),
					esc_attr( $name ),
					$is_active ? 'true' : 'false',
					esc_html( $name )
				);
				continue;
			}

			$color    = get_term_meta( $term->term_id, self::COLOR_META, true );
			$color    = sanitize_hex_color( $color ) ? sanitize_hex_color( $color ) : '#e5e7eb';
			$image_id = absint( get_term_meta( $term->term_id, self::IMAGE_META, true ) );
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
			$style    = $image ? 'background-image:url("' . esc_url( $image ) . '");' : 'background-color:' . $color . ';';

			$buttons .= sprintf(
				'<button type="button" class="%1$s" data-value="%2$s" aria-label="%3$s" aria-pressed="%4$s" title="%3$s"><span class="hlpb-swatch" style="%5$s"><span class="screen-reader-text">%3$s</span></span></button>',
				esc_attr( $button_class ),
				esc_attr( $term->slug ),
				esc_attr( $name ),
				$is_active ? 'true' : 'false',
				esc_attr( $style )
			);
		}

		if ( '' === $buttons ) {
			return $html;
		}

		return '<div class="hlpb-variation-control">' . $html . '<div class="' . esc_attr( $classes ) . '" role="group" aria-label="' . esc_attr( $label ) . '">' . $buttons . '</div></div>';
	}

	/**
	 * Find the saved display type for a global product attribute.
	 *
	 * @param string $taxonomy Attribute taxonomy, e.g. pa_color.
	 * @return string
	 */
	private static function get_attribute_type( $taxonomy ) {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return 'select';
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			if ( wc_attribute_taxonomy_name( $attribute->attribute_name ) === $taxonomy ) {
				return isset( $attribute->attribute_type ) ? (string) $attribute->attribute_type : 'select';
			}
		}

		return 'select';
	}

	/**
	 * Load the small variation swatch assets on product pages.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style( 'hlpb-variation-swatches', HLPB_URL . 'assets/frontend/variation-swatches.css', array(), HLPB_VERSION );
		wp_enqueue_script( 'hlpb-variation-swatches', HLPB_URL . 'assets/frontend/variation-swatches.js', array( 'jquery' ), HLPB_VERSION, true );
	}

	/**
	 * Add the types shown in WooCommerce's global attribute editor.
	 *
	 * @param array<string, string> $types Existing WooCommerce attribute types.
	 * @return array<string, string>
	 */
	public static function add_attribute_types( $types ) {
		$attribute_editor = isset( $_GET['page'] ) && is_string( $_GET['page'] ) && 'product_attributes' === sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( ! $attribute_editor ) {
			return $types;
		}

		$types['select']             = __( 'Dropdown', 'html-landing-page-builder-for-woocommerce' );
		$types['color-image-picker'] = __( 'Color image picker', 'html-landing-page-builder-for-woocommerce' );
		$types['button']             = __( 'Button', 'html-landing-page-builder-for-woocommerce' );
		return $types;
	}

	/**
	 * Attach term fields to each global WooCommerce attribute taxonomy.
	 *
	 * @return void
	 */
	public static function register_term_hooks() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$save_term_fields = static function ( $term_id, $tt_id ) use ( $taxonomy ) {
				self::save_term_fields( $term_id, $tt_id, $taxonomy );
			};

			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'render_edit_fields' ), 10, 2 );
			add_action( 'created_' . $taxonomy, $save_term_fields, 10, 2 );
			add_action( 'edited_' . $taxonomy, $save_term_fields, 10, 2 );
		}
	}

	/**
	 * Render color and image controls on the Add Term screen.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function render_add_fields( $taxonomy ) {
		?>
		<div class="form-field hlpb-term-field">
			<label for="hlpb-term-color"><?php esc_html_e( 'Swatch color', 'html-landing-page-builder-for-woocommerce' ); ?></label>
			<div class="hlpb-term-color-row"><input type="color" id="hlpb-term-color-picker" value="#6c2034"><input type="text" id="hlpb-term-color" name="hlpb_term_color" value="#6c2034" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" aria-label="<?php esc_attr_e( 'Hex color code', 'html-landing-page-builder-for-woocommerce' ); ?>"></div>
			<p class="description"><?php esc_html_e( 'Choose a color or enter a hex code. No image is required; this color is shown when no swatch image is set.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
		</div>
		<div class="form-field hlpb-term-field">
			<label><?php esc_html_e( 'Swatch image', 'html-landing-page-builder-for-woocommerce' ); ?></label>
			<div class="hlpb-term-image-control"><input type="hidden" name="hlpb_term_image_id" value="0"><img class="hlpb-term-image-preview" src="" alt="" hidden><button type="button" class="button hlpb-term-select-image"><?php esc_html_e( 'Choose image', 'html-landing-page-builder-for-woocommerce' ); ?></button><button type="button" class="button-link hlpb-term-remove-image" hidden><?php esc_html_e( 'Remove image', 'html-landing-page-builder-for-woocommerce' ); ?></button></div>
			<p class="description"><?php esc_html_e( 'Optional. When set, this image is shown instead of the swatch color.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
		</div>
		<?php wp_nonce_field( 'hlpb_save_term', 'hlpb_term_nonce' ); ?>
		<?php
	}

	/**
	 * Render color and image controls on the Edit Term screen.
	 *
	 * @param WP_Term $term Current term.
	 * @param string  $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function render_edit_fields( $term, $taxonomy ) {
		$color    = get_term_meta( $term->term_id, self::COLOR_META, true );
		$color    = sanitize_hex_color( $color ) ? sanitize_hex_color( $color ) : '#6c2034';
		$image_id = absint( get_term_meta( $term->term_id, self::IMAGE_META, true ) );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
		?>
		<tr class="form-field hlpb-term-field">
			<th scope="row"><label for="hlpb-term-color"><?php esc_html_e( 'Swatch color', 'html-landing-page-builder-for-woocommerce' ); ?></label></th>
			<td><div class="hlpb-term-color-row"><input type="color" id="hlpb-term-color-picker" value="<?php echo esc_attr( $color ); ?>"><input type="text" id="hlpb-term-color" name="hlpb_term_color" value="<?php echo esc_attr( $color ); ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" aria-label="<?php esc_attr_e( 'Hex color code', 'html-landing-page-builder-for-woocommerce' ); ?>"></div><p class="description"><?php esc_html_e( 'Choose a color or enter a hex code. No image is required; this color is shown when no swatch image is set.', 'html-landing-page-builder-for-woocommerce' ); ?></p></td>
		</tr>
		<tr class="form-field hlpb-term-field">
			<th scope="row"><label><?php esc_html_e( 'Swatch image', 'html-landing-page-builder-for-woocommerce' ); ?></label></th>
			<td><div class="hlpb-term-image-control"><input type="hidden" name="hlpb_term_image_id" value="<?php echo esc_attr( $image_id ); ?>"><img class="hlpb-term-image-preview" src="<?php echo esc_url( $image ? $image : '' ); ?>" alt="" <?php echo $image ? '' : 'hidden'; ?>><button type="button" class="button hlpb-term-select-image"><?php esc_html_e( 'Choose image', 'html-landing-page-builder-for-woocommerce' ); ?></button><button type="button" class="button-link hlpb-term-remove-image" <?php echo $image ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'html-landing-page-builder-for-woocommerce' ); ?></button></div><p class="description"><?php esc_html_e( 'Optional. When set, this image is shown instead of the swatch color.', 'html-landing-page-builder-for-woocommerce' ); ?></p><?php wp_nonce_field( 'hlpb_save_term', 'hlpb_term_nonce' ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Store visual values for an attribute term.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function save_term_fields( $term_id, $tt_id, $taxonomy ) {
		if ( ! isset( $_POST['hlpb_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hlpb_term_nonce'] ) ), 'hlpb_save_term' ) ) {
			return;
		}

		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
			return;
		}

		if ( isset( $_POST['hlpb_term_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['hlpb_term_color'] ) );
			if ( $color ) {
				update_term_meta( $term_id, self::COLOR_META, $color );
			} else {
				delete_term_meta( $term_id, self::COLOR_META );
			}
		}

		if ( isset( $_POST['hlpb_term_image_id'] ) ) {
			$image_id = absint( $_POST['hlpb_term_image_id'] );
			if ( $image_id && wp_attachment_is_image( $image_id ) ) {
				update_term_meta( $term_id, self::IMAGE_META, $image_id );
			} else {
				delete_term_meta( $term_id, self::IMAGE_META );
			}
		}
	}

	/**
	 * Load assets only on WooCommerce attribute and attribute term screens.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		$screen       = get_current_screen();
		$attribute_ui = isset( $_GET['page'] ) && 'product_attributes' === sanitize_key( wp_unslash( $_GET['page'] ) );
		$taxonomy_ui  = $screen && ! empty( $screen->taxonomy ) && 0 === strpos( $screen->taxonomy, 'pa_' );
		if ( ! $attribute_ui && ! $taxonomy_ui ) {
			return;
		}

		wp_enqueue_style( 'hlpb-attribute-admin', HLPB_URL . 'assets/admin/attributes.css', array(), HLPB_VERSION );
		if ( $attribute_ui ) {
			wp_enqueue_script( 'hlpb-attribute-preview', HLPB_URL . 'assets/admin/attribute-preview.js', array(), HLPB_VERSION, true );
		}
		if ( $taxonomy_ui ) {
			wp_enqueue_media();
			wp_enqueue_script( 'hlpb-term-admin', HLPB_URL . 'assets/admin/term-fields.js', array( 'jquery' ), HLPB_VERSION, true );
		}
	}
}
