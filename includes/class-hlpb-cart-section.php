<?php
/**
 * WooCommerce product, offer, and checkout integration for landing page Cart Sections.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Cart_Section {
	const COUPON_META = '_hlpb_cart_coupon_id';
	const COUPON_IDS_META = '_hlpb_cart_coupon_ids';
	const COUPON_LANDING_META = '_hlpb_landing_id';
	const COUPON_FLAG_META = '_hlpb_landing_offer_coupon';
	private static $syncing_cart = false;

	/** Register the public checkout endpoint and WooCommerce coupon filters. */
	public static function init() {
		add_action( 'admin_post_hlpb_landing_add_to_cart', array( __CLASS__, 'handle_checkout_request' ) );
		add_action( 'admin_post_nopriv_hlpb_landing_add_to_cart', array( __CLASS__, 'handle_checkout_request' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon' ), 20, 3 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'coupon_valid_for_product' ), 20, 4 );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( __CLASS__, 'coupon_discount' ), 20, 5 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_offer_data' ), 20, 2 );
		add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'remember_removed_bundle_gift' ), 10, 2 );
		add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'remember_removed_bundle_gift' ), 10, 2 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'sync_bundle_after_removal' ), 20, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'sync_bundle_after_quantity_change' ), 20, 4 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item_data' ), 10, 4 );
		add_action( 'trashed_post', array( __CLASS__, 'deactivate_for_landing' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'restore_for_landing' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_coupon_for_landing' ) );
	}

	/** Default editable copy and offer configuration. */
	public static function defaults() {
		$rules = array();
		for ( $quantity = 1; $quantity <= 4; $quantity++ ) {
			$rules[ $quantity ] = array(
				'quantity' => $quantity,
				'type'     => 1 === $quantity ? 'none' : 'percent',
				'value'    => 1 === $quantity ? '' : array( 2 => 9, 3 => 18, 4 => 28 )[ $quantity ],
				'gift_product_id' => 0,
				'gift_mode' => 'none',
				'gift_discount_type' => 'percent',
				'gift_discount_value' => 0,
				'gift_label' => '',
				'badge_enabled' => 4 === $quantity ? 1 : 0,
				'badge_text' => 4 === $quantity ? 'BEST VALUE' : '',
			);
		}
		return array(
			'eyebrow'        => 'THE MIX & MATCH COLLECTION',
			'title'          => 'Build your perfect cosy mix',
			'rating'         => '4.9',
			'rating_stars'   => 5,
			'rating_count'   => '2,418',
			'countdown'      => 1,
			'countdown_text' => 'Buy more deal ends in',
			'button_text'    => 'ADD TO CART',
			'accent_color'   => '#ef555c',
			'background'     => '#ffffff',
			'mode_labels'    => array( 'buy' => 'Buy more', 'mix' => 'Mix & Match', 'bundle' => 'Cozy bundle' ),
			'mode_copy'      => array(
				'buy' => array( 'quantity_heading' => 'STEP 1 · CHOOSE QUANTITY', 'items_heading' => 'STEP 2 · CHOOSE OPTIONS', 'hint' => 'One product, up to 4 items. Choose a colour or size for each.', 'summary' => 'Items total:' ),
				'mix' => array( 'quantity_heading' => 'STEP 1 · CHOOSE MIX SIZE', 'items_heading' => 'STEP 2 · PICK EACH PRODUCT', 'hint' => 'Choose 2 different products. Extra items can repeat either.', 'summary' => 'Mix total:' ),
				'bundle' => array( 'quantity_heading' => 'STEP 1 · CHOOSE SETS', 'items_heading' => 'STEP 2 · PICK EACH SET', 'hint' => 'Each set contains both configured products.', 'summary' => 'Bundle total:' ),
			),
			'saving_label'   => 'You save:',
			'total_label'    => 'You pay:',
			'modes'          => array(
				'buy'    => array( 'enabled' => 1, 'products' => array( 0 ), 'coupon_name' => 'Cozy Season Offer', 'rules' => $rules ),
				'mix'    => array( 'enabled' => 1, 'products' => array( 0, 0 ), 'coupon_name' => 'Cozy Season Offer', 'rules' => $rules ),
				'bundle' => array( 'enabled' => 1, 'products' => array( 0, 0 ), 'coupon_name' => 'Cozy Season Offer', 'rules' => $rules ),
			),
			'addon'          => array( 'product_id' => 0, 'mode' => 'none', 'discount_type' => 'percent', 'discount_value' => 10, 'label' => 'Add a special extra' ),
			'coupon_name'    => 'Cozy Season Offer',
			'service_points' => array(
				array( 'title' => 'Free delivery', 'text' => '2–4 working days' ),
				array( 'title' => 'Secure checkout', 'text' => 'SSL encrypted' ),
				array( 'title' => 'Makes a wonderful', 'text' => 'gift choice' ),
			),
			'payment_methods' => 'VISA, Mastercard, Apple Pay, Google Pay, PayPal',
		);
	}

	/** Sanitize and normalize editor values. */
	public static function sanitize( $raw ) {
		$defaults = self::defaults();
		$raw      = is_array( $raw ) ? $raw : array();
		$section  = array();
		foreach ( array( 'eyebrow', 'title', 'rating', 'rating_count', 'countdown_text', 'button_text', 'payment_methods', 'saving_label', 'total_label' ) as $key ) {
			$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : $defaults[ $key ];
			$section[ $key ] = 'title' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		$legacy_addon = isset( $raw['addon'] ) && is_array( $raw['addon'] ) ? $raw['addon'] : array();
		$section['countdown'] = ! empty( $raw['countdown'] ) ? 1 : 0;
		foreach ( array( 'accent_color', 'background' ) as $color_key ) {
			$color = isset( $raw[ $color_key ] ) ? sanitize_hex_color( $raw[ $color_key ] ) : false;
			$section[ $color_key ] = $color ? $color : $defaults[ $color_key ];
		}
		$section['rating'] = (string) min( 5, max( 0, (float) $section['rating'] ) );
		$section['rating_stars'] = isset( $raw['rating_stars'] ) ? min( 5, max( 1, absint( $raw['rating_stars'] ) ) ) : $defaults['rating_stars'];
		$section['mode_labels'] = array();
		$section['mode_copy'] = array();
		$section['modes'] = array();
		foreach ( array( 'buy', 'mix', 'bundle' ) as $mode ) {
			$raw_mode = isset( $raw['modes'][ $mode ] ) && is_array( $raw['modes'][ $mode ] ) ? $raw['modes'][ $mode ] : array();
			$section['mode_labels'][ $mode ] = isset( $raw['mode_labels'][ $mode ] ) ? sanitize_text_field( $raw['mode_labels'][ $mode ] ) : $defaults['mode_labels'][ $mode ];
			$raw_copy = isset( $raw['mode_copy'][ $mode ] ) && is_array( $raw['mode_copy'][ $mode ] ) ? $raw['mode_copy'][ $mode ] : array();
			$section['mode_copy'][ $mode ] = array();
			foreach ( array( 'quantity_heading', 'items_heading', 'hint', 'summary' ) as $copy_key ) {
				$copy_value = isset( $raw_copy[ $copy_key ] ) && is_scalar( $raw_copy[ $copy_key ] ) ? (string) $raw_copy[ $copy_key ] : $defaults['mode_copy'][ $mode ][ $copy_key ];
				$legacy_hints = array(
					'buy' => array( 'Select a product, then choose its available variations.' ),
					'mix' => array( 'Choose from the two products configured for this offer.' ),
				);
				if ( 'hint' === $copy_key && in_array( trim( $copy_value ), $legacy_hints[ $mode ] ?? array(), true ) ) {
					$copy_value = $defaults['mode_copy'][ $mode ]['hint'];
				}
				$section['mode_copy'][ $mode ][ $copy_key ] = 'hint' === $copy_key ? sanitize_textarea_field( $copy_value ) : sanitize_text_field( $copy_value );
			}
			$max_products = 'buy' === $mode ? 1 : 2;
			$raw_products = isset( $raw_mode['products'] ) && is_array( $raw_mode['products'] ) ? $raw_mode['products'] : $defaults['modes'][ $mode ]['products'];
			if ( 'buy' === $mode && empty( $raw_products[0] ) && ! empty( $raw_products[1] ) ) {
				$raw_products[0] = $raw_products[1];
			}
			$products = array_slice( $raw_products, 0, $max_products );
			while ( count( $products ) < ( 'buy' === $mode ? 1 : 2 ) ) {
				$products[] = 0;
			}
			$section['modes'][ $mode ] = array(
				'enabled'  => ! empty( $raw_mode['enabled'] ) ? 1 : 0,
				'products' => array_map( 'absint', $products ),
				'coupon_name' => isset( $raw_mode['coupon_name'] ) && is_scalar( $raw_mode['coupon_name'] ) ? sanitize_text_field( $raw_mode['coupon_name'] ) : ( isset( $raw['coupon_name'] ) && is_scalar( $raw['coupon_name'] ) ? sanitize_text_field( $raw['coupon_name'] ) : $defaults['modes'][ $mode ]['coupon_name'] ),
				'rules'    => array(),
			);
			if ( '' === trim( $section['modes'][ $mode ]['coupon_name'] ) ) {
				$section['modes'][ $mode ]['coupon_name'] = $defaults['modes'][ $mode ]['coupon_name'];
			}
			for ( $quantity = 1; $quantity <= 4; $quantity++ ) {
				$raw_rule = isset( $raw_mode['rules'][ $quantity ] ) && is_array( $raw_mode['rules'][ $quantity ] ) ? $raw_mode['rules'][ $quantity ] : $defaults['modes'][ $mode ]['rules'][ $quantity ];
				$type = isset( $raw_rule['type'] ) ? sanitize_key( $raw_rule['type'] ) : 'none';
				if ( ! in_array( $type, array( 'none', 'percent', 'amount', 'fixed_total' ), true ) ) {
					$type = 'none';
				}
				$value = isset( $raw_rule['value'] ) ? (float) ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_rule['value'] ) : $raw_rule['value'] ) : 0;
				$gift_mode = isset( $raw_rule['gift_mode'] ) ? sanitize_key( $raw_rule['gift_mode'] ) : 'none';
				if ( ! in_array( $gift_mode, array( 'none', 'free', 'discounted' ), true ) ) {
					$gift_mode = 'none';
				}
				$gift_product_id = isset( $raw_rule['gift_product_id'] ) ? absint( $raw_rule['gift_product_id'] ) : 0;
				// Migrate legacy global add-on settings into per-quantity gifts when upgrading an existing draft.
				if ( ! isset( $raw_rule['gift_mode'] ) && ! empty( $legacy_addon['product_id'] ) && in_array( $legacy_addon['mode'] ?? 'none', array( 'gift', 'discounted' ), true ) ) {
					$gift_mode = 'gift' === $legacy_addon['mode'] ? 'free' : 'discounted';
					$gift_product_id = absint( $legacy_addon['product_id'] );
				}
				$gift_discount_type = isset( $raw_rule['gift_discount_type'] ) ? sanitize_key( $raw_rule['gift_discount_type'] ) : sanitize_key( $legacy_addon['discount_type'] ?? 'percent' );
				if ( ! in_array( $gift_discount_type, array( 'percent', 'fixed_price' ), true ) ) {
					$gift_discount_type = 'percent';
				}
				$gift_discount_value = isset( $raw_rule['gift_discount_value'] ) ? (float) ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_rule['gift_discount_value'] ) : $raw_rule['gift_discount_value'] ) : (float) ( $legacy_addon['discount_value'] ?? 0 );
				$badge_enabled = array_key_exists( 'badge_enabled', $raw_rule ) ? ( ! empty( $raw_rule['badge_enabled'] ) ? 1 : 0 ) : absint( $defaults['modes'][ $mode ]['rules'][ $quantity ]['badge_enabled'] ?? 0 );
				$badge_text = isset( $raw_rule['badge_text'] ) && is_scalar( $raw_rule['badge_text'] ) ? sanitize_text_field( (string) $raw_rule['badge_text'] ) : (string) ( $defaults['modes'][ $mode ]['rules'][ $quantity ]['badge_text'] ?? '' );
				$badge_text = function_exists( 'mb_substr' ) ? mb_substr( $badge_text, 0, 60 ) : substr( $badge_text, 0, 60 );
				$section['modes'][ $mode ]['rules'][ $quantity ] = array(
					'quantity' => $quantity,
					'type'     => $type,
					'value'    => max( 0, min( 'percent' === $type ? 100 : 1000000, $value ) ),
					'gift_product_id' => $gift_product_id,
					'gift_mode' => $gift_mode,
					'gift_discount_type' => $gift_discount_type,
					'gift_discount_value' => max( 0, min( 'percent' === $gift_discount_type ? 100 : 1000000, $gift_discount_value ) ),
					'gift_label' => isset( $raw_rule['gift_label'] ) ? sanitize_text_field( $raw_rule['gift_label'] ) : ( isset( $legacy_addon['label'] ) ? sanitize_text_field( $legacy_addon['label'] ) : '' ),
					'badge_enabled' => $badge_enabled,
					'badge_text' => $badge_text,
				);
			}
		}
		$raw_addon = isset( $raw['addon'] ) && is_array( $raw['addon'] ) ? $raw['addon'] : array();
		$addon_mode = isset( $raw_addon['mode'] ) ? sanitize_key( $raw_addon['mode'] ) : 'none';
		if ( ! in_array( $addon_mode, array( 'none', 'gift', 'discounted' ), true ) ) {
			$addon_mode = 'none';
		}
		$addon_type = isset( $raw_addon['discount_type'] ) ? sanitize_key( $raw_addon['discount_type'] ) : 'percent';
		if ( ! in_array( $addon_type, array( 'percent', 'fixed_price' ), true ) ) {
			$addon_type = 'percent';
		}
		$section['addon'] = array(
			'product_id'     => isset( $raw_addon['product_id'] ) ? absint( $raw_addon['product_id'] ) : 0,
			'mode'           => $addon_mode,
			'discount_type'  => $addon_type,
			'discount_value' => max( 0, min( 'percent' === $addon_type ? 100 : 1000000, isset( $raw_addon['discount_value'] ) ? (float) ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_addon['discount_value'] ) : $raw_addon['discount_value'] ) : 0 ) ),
			'label'          => isset( $raw_addon['label'] ) ? sanitize_text_field( $raw_addon['label'] ) : $defaults['addon']['label'],
		);
		$section['service_points'] = array();
		$raw_points = isset( $raw['service_points'] ) && is_array( $raw['service_points'] ) ? array_slice( $raw['service_points'], 0, 3 ) : $defaults['service_points'];
		for ( $i = 0; $i < 3; $i++ ) {
			$point = isset( $raw_points[ $i ] ) && is_array( $raw_points[ $i ] ) ? $raw_points[ $i ] : $defaults['service_points'][ $i ];
			$section['service_points'][] = array(
				'title' => isset( $point['title'] ) ? sanitize_text_field( $point['title'] ) : '',
				'text'  => isset( $point['text'] ) ? sanitize_text_field( $point['text'] ) : '',
			);
		}
		return $section;
	}

	/** Render the optional badge controls attached to one quantity tier. */
	private static function badge_fields_markup( $rule_name, $rule ) {
		$enabled = ! empty( $rule['badge_enabled'] );
		$badge_name = $rule_name . '[badge_enabled]';
		$text_name = $rule_name . '[badge_text]';
		$html = '<div class="hlpb-rule-badge-settings"><label class="hlpb-rule-badge-toggle"><input type="hidden" name="' . esc_attr( $badge_name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $badge_name ) . '" value="1" ' . checked( $enabled, true, false ) . '><span>' . esc_html__( 'Show badge on this offer', 'html-landing-page-builder-for-woocommerce' ) . '</span></label>';
		$html .= '<label class="hlpb-rule-badge-text"><span>' . esc_html__( 'Badge text', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" maxlength="60" name="' . esc_attr( $text_name ) . '" value="' . esc_attr( $rule['badge_text'] ?? '' ) . '" placeholder="' . esc_attr__( 'For example: MOST POPULAR', 'html-landing-page-builder-for-woocommerce' ) . '"></label></div>';
		return $html;
	}

	/** Render editable Cart Section fields. */
	public static function render_editor_fields( $section, $id ) {
		$section = array_merge( self::defaults(), self::sanitize( is_array( $section ) ? $section : array() ) );
		$section['modes'] = isset( $section['modes'] ) && is_array( $section['modes'] ) ? $section['modes'] : self::defaults()['modes'];
		$defaults = self::defaults();
		$name = 'hlpb_sections[' . $id . ']';
		$products = self::get_product_choices();
		$html = '<div class="hlpb-cart-editor"><p class="description">' . esc_html__( 'Configure WooCommerce products, variation choices, offer tiers and the checkout coupon used by this section.', 'html-landing-page-builder-for-woocommerce' ) . '</p><p class="description">' . esc_html__( 'Product prices, available variations and variation galleries are read from WooCommerce again whenever you update or publish this landing page. The next static HTML copy will include the current product data.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		$html .= '<fieldset class="hlpb-cart-fieldset"><legend>' . esc_html__( 'Section content', 'html-landing-page-builder-for-woocommerce' ) . '</legend>';
		$html .= self::input( $name, 'eyebrow', __( 'Eyebrow', 'html-landing-page-builder-for-woocommerce' ), $section['eyebrow'] );
		$html .= self::textarea( $name, 'title', __( 'Heading', 'html-landing-page-builder-for-woocommerce' ), $section['title'] );
		$html .= '<div class="hlpb-field"><label>' . esc_html__( 'Rating', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="number" min="0" max="5" step="0.1" name="' . esc_attr( $name . '[rating]' ) . '" value="' . esc_attr( $section['rating'] ) . '"> <label>' . esc_html__( 'Review count', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="text" name="' . esc_attr( $name . '[rating_count]' ) . '" value="' . esc_attr( $section['rating_count'] ) . '"></div>';
		$html .= '<div class="hlpb-field"><label>' . esc_html__( 'Displayed stars', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="number" min="1" max="5" step="1" name="' . esc_attr( $name . '[rating_stars]' ) . '" value="' . esc_attr( $section['rating_stars'] ) . '"></div>';
		$html .= '<div class="hlpb-field"><label><input type="checkbox" name="' . esc_attr( $name . '[countdown]' ) . '" value="1" ' . checked( ! empty( $section['countdown'] ), true, false ) . '> ' . esc_html__( 'Show countdown', 'html-landing-page-builder-for-woocommerce' ) . '</label></div>';
		$html .= self::input( $name, 'countdown_text', __( 'Countdown label', 'html-landing-page-builder-for-woocommerce' ), $section['countdown_text'] );
		$html .= self::input( $name, 'button_text', __( 'Add to cart button', 'html-landing-page-builder-for-woocommerce' ), $section['button_text'] );
		$html .= '<div class="hlpb-field"><label>' . esc_html__( 'Accent color', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="color" name="' . esc_attr( $name . '[accent_color]' ) . '" value="' . esc_attr( $section['accent_color'] ) . '"> <label>' . esc_html__( 'Section background', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="color" name="' . esc_attr( $name . '[background]' ) . '" value="' . esc_attr( $section['background'] ) . '"></div>';
		$html .= '</fieldset>';
		foreach ( array( 'buy' => __( 'Buy more', 'html-landing-page-builder-for-woocommerce' ), 'mix' => __( 'Mix & Match', 'html-landing-page-builder-for-woocommerce' ), 'bundle' => __( 'Cozy bundle', 'html-landing-page-builder-for-woocommerce' ) ) as $mode => $label ) {
			$prefix = array( 'buy' => 'BuyMore', 'mix' => 'MixNMatch', 'bundle' => 'CozyBundle' )[ $mode ];
			$mode_data = isset( $section['modes'][ $mode ] ) && is_array( $section['modes'][ $mode ] ) ? array_merge( $defaults['modes'][ $mode ], $section['modes'][ $mode ] ) : $defaults['modes'][ $mode ];
			$mode_data['rules'] = isset( $mode_data['rules'] ) && is_array( $mode_data['rules'] ) ? $mode_data['rules'] : $defaults['modes'][ $mode ]['rules'];
			$html .= '<fieldset class="hlpb-cart-fieldset hlpb-cart-mode"><legend>' . esc_html( $label ) . '</legend><label><input type="checkbox" name="' . esc_attr( $name . '[modes][' . $mode . '][enabled]' ) . '" value="1" ' . checked( ! empty( $mode_data['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable tab', 'html-landing-page-builder-for-woocommerce' ) . '</label> ' . self::input( $name, 'mode_labels][' . $mode, __( 'Tab label', 'html-landing-page-builder-for-woocommerce' ), $section['mode_labels'][ $mode ] ?? $label );
			$html .= '<div class="hlpb-field hlpb-coupon-name-field"><label>' . esc_html__( 'Coupon name', 'html-landing-page-builder-for-woocommerce' ) . '</label><span class="hlpb-coupon-prefix">' . esc_html( $prefix ) . '</span><input type="text" class="regular-text" name="' . esc_attr( $name . '[modes][' . $mode . '][coupon_name]' ) . '" value="' . esc_attr( $mode_data['coupon_name'] ?? $defaults['modes'][ $mode ]['coupon_name'] ) . '"><span class="description">' . esc_html__( 'This prefix is added automatically to the WooCommerce coupon name and code.', 'html-landing-page-builder-for-woocommerce' ) . '</span></div>';
			$mode_copy = isset( $section['mode_copy'][ $mode ] ) && is_array( $section['mode_copy'][ $mode ] ) ? array_merge( $defaults['mode_copy'][ $mode ], $section['mode_copy'][ $mode ] ) : $defaults['mode_copy'][ $mode ];
			$html .= self::input( $name, 'quantity_heading', __( 'Quantity step heading', 'html-landing-page-builder-for-woocommerce' ), $mode_copy['quantity_heading'], 'mode_copy][' . $mode );
			$html .= self::input( $name, 'items_heading', __( 'Products step heading', 'html-landing-page-builder-for-woocommerce' ), $mode_copy['items_heading'], 'mode_copy][' . $mode );
			$html .= self::input( $name, 'hint', __( 'Products hint', 'html-landing-page-builder-for-woocommerce' ), $mode_copy['hint'], 'mode_copy][' . $mode );
			$html .= self::input( $name, 'summary', __( 'Price summary label', 'html-landing-page-builder-for-woocommerce' ), $mode_copy['summary'], 'mode_copy][' . $mode );
			$slots = 'buy' === $mode ? 1 : 2;
			$products_heading = 'buy' === $mode ? __( 'Product to repeat', 'html-landing-page-builder-for-woocommerce' ) : ( 'mix' === $mode ? __( 'Products to mix', 'html-landing-page-builder-for-woocommerce' ) : __( 'Products in each set', 'html-landing-page-builder-for-woocommerce' ) );
			$html .= '<div class="hlpb-cart-product-slots"><strong>' . esc_html( $products_heading ) . '</strong>';
			for ( $slot = 0; $slot < $slots; $slot++ ) {
				$product_id = isset( $mode_data['products'][ $slot ] ) ? absint( $mode_data['products'][ $slot ] ) : 0;
				$slot_label = 'buy' === $mode ? __( 'Product', 'html-landing-page-builder-for-woocommerce' ) : ( 'mix' === $mode ? sprintf( __( 'Mix option %d', 'html-landing-page-builder-for-woocommerce' ), $slot + 1 ) : sprintf( __( 'Set item %d', 'html-landing-page-builder-for-woocommerce' ), $slot + 1 ) );
				$html .= '<label>' . esc_html( $slot_label ) . ' <select class="hlpb-mode-product" data-mode="' . esc_attr( $mode ) . '" name="' . esc_attr( $name . '[modes][' . $mode . '][products][' . $slot . ']' ) . '"><option value="0">' . esc_html__( '— Select product —', 'html-landing-page-builder-for-woocommerce' ) . '</option>';
				foreach ( $products as $product ) {
					$html .= '<option value="' . esc_attr( $product['id'] ) . '" ' . selected( $product_id, $product['id'], false ) . '>' . esc_html( $product['name'] ) . '</option>';
				}
				$html .= '</select></label>';
			}
			$product_help = 'buy' === $mode
				? __( 'The same product is repeated. Shoppers can choose a different colour or size for each item.', 'html-landing-page-builder-for-woocommerce' )
				: ( 'mix' === $mode
					? __( 'Choose two different products. Every mix includes both; extra items can repeat either.', 'html-landing-page-builder-for-woocommerce' )
					: __( 'Choose two different products. Every set includes one of each.', 'html-landing-page-builder-for-woocommerce' ) );
			$html .= '</div><p class="description">' . esc_html( $product_help ) . '</p>';
			$html .= '<div class="hlpb-cart-rules"><div class="hlpb-cart-rules-heading"><strong>' . esc_html__( 'Offer price and gift by quantity', 'html-landing-page-builder-for-woocommerce' ) . '</strong><p class="description">' . esc_html__( 'Set the price or discount for the offer products in the left panel. Configure a separate optional gift in the right panel.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div>';
			for ( $quantity = ( 'mix' === $mode ? 2 : 1 ); $quantity <= 4; $quantity++ ) {
				$rule = isset( $mode_data['rules'][ $quantity ] ) && is_array( $mode_data['rules'][ $quantity ] ) ? $mode_data['rules'][ $quantity ] : $defaults['modes'][ $mode ]['rules'][ $quantity ];
				$rule_name = $name . '[modes][' . $mode . '][rules][' . $quantity . ']';
				$rule_label = 'mix' === $mode ? sprintf( __( '%d items', 'html-landing-page-builder-for-woocommerce' ), $quantity ) : ( 'bundle' === $mode ? sprintf( _n( '%d set', '%d sets', $quantity, 'html-landing-page-builder-for-woocommerce' ), $quantity ) : sprintf( __( 'Buy %d', 'html-landing-page-builder-for-woocommerce' ), $quantity ) );
				$html .= '<div class="hlpb-cart-rule"><div class="hlpb-cart-rule-title"><strong>' . esc_html( $rule_label ) . '</strong><span>' . esc_html__( 'Settings for this quantity', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="hidden" name="' . esc_attr( $rule_name . '[quantity]' ) . '" value="' . esc_attr( $quantity ) . '"></div><div class="hlpb-cart-rule-panels"><section class="hlpb-rule-panel hlpb-rule-offer"><h4>' . esc_html__( 'Offer price / discount', 'html-landing-page-builder-for-woocommerce' ) . '</h4><p>' . esc_html__( 'Applies to the products in this offer.', 'html-landing-page-builder-for-woocommerce' ) . '</p><div class="hlpb-rule-fields"><label><span>' . esc_html__( 'Price rule', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $rule_name . '[type]' ) . '"><option value="none" ' . selected( $rule['type'] ?? '', 'none', false ) . '>' . esc_html__( 'No discount', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="percent" ' . selected( $rule['type'] ?? '', 'percent', false ) . '>' . esc_html__( 'Percent off', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="amount" ' . selected( $rule['type'] ?? '', 'amount', false ) . '>' . esc_html__( 'Fixed amount off', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="fixed_total" ' . selected( $rule['type'] ?? '', 'fixed_total', false ) . '>' . esc_html__( 'Fixed total price', 'html-landing-page-builder-for-woocommerce' ) . '</option></select></label><label><span>' . esc_html__( 'Discount / total value', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="number" min="0" step="0.01" name="' . esc_attr( $rule_name . '[value]' ) . '" value="' . esc_attr( $rule['value'] ?? '' ) . '"></label></div><p class="description">' . esc_html__( 'Enter a discount for Percent off or Fixed amount off. For Fixed total price, enter the final price for this offer.', 'html-landing-page-builder-for-woocommerce' ) . '</p>' . self::badge_fields_markup( $rule_name, $rule ) . '</section><section class="hlpb-rule-panel hlpb-rule-gift"><h4>' . esc_html__( 'Gift product (optional)', 'html-landing-page-builder-for-woocommerce' ) . '</h4><p>' . esc_html__( 'The gift is added separately once and does not count toward the offer quantity.', 'html-landing-page-builder-for-woocommerce' ) . '</p><div class="hlpb-rule-fields hlpb-gift-fields"><label><span>' . esc_html__( 'Gift product', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $rule_name . '[gift_product_id]' ) . '"><option value="0">' . esc_html__( 'No gift', 'html-landing-page-builder-for-woocommerce' ) . '</option>';
				foreach ( $products as $product ) {
					$html .= '<option value="' . esc_attr( $product['id'] ) . '" ' . selected( absint( $rule['gift_product_id'] ?? 0 ), $product['id'], false ) . '>' . esc_html( $product['name'] ) . '</option>';
				}
				$html .= '</select></label><label><span>' . esc_html__( 'Gift treatment', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $rule_name . '[gift_mode]' ) . '"><option value="none" ' . selected( $rule['gift_mode'] ?? 'none', 'none', false ) . '>' . esc_html__( 'Not included', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="free" ' . selected( $rule['gift_mode'] ?? 'none', 'free', false ) . '>' . esc_html__( 'Free gift', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="discounted" ' . selected( $rule['gift_mode'] ?? 'none', 'discounted', false ) . '>' . esc_html__( 'Discounted price', 'html-landing-page-builder-for-woocommerce' ) . '</option></select></label><label><span>' . esc_html__( 'Gift price rule', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $rule_name . '[gift_discount_type]' ) . '"><option value="percent" ' . selected( $rule['gift_discount_type'] ?? 'percent', 'percent', false ) . '>' . esc_html__( 'Percent off', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="fixed_price" ' . selected( $rule['gift_discount_type'] ?? 'percent', 'fixed_price', false ) . '>' . esc_html__( 'Fixed price', 'html-landing-page-builder-for-woocommerce' ) . '</option></select></label><label><span>' . esc_html__( 'Gift discount / price', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="number" min="0" step="0.01" name="' . esc_attr( $rule_name . '[gift_discount_value]' ) . '" value="' . esc_attr( $rule['gift_discount_value'] ?? 0 ) . '"></label></div><p class="description">' . esc_html__( 'This value is used when Gift treatment is set to Discounted price.', 'html-landing-page-builder-for-woocommerce' ) . '</p></section></div></div>';
			}
			$html .= '</div></fieldset>';
		}
		$html .= '<fieldset class="hlpb-cart-fieldset"><legend>' . esc_html__( 'Savings and trust row', 'html-landing-page-builder-for-woocommerce' ) . '</legend>' . self::input( $name, 'saving_label', __( 'Savings label', 'html-landing-page-builder-for-woocommerce' ), $section['saving_label'] ) . self::input( $name, 'total_label', __( 'Total label', 'html-landing-page-builder-for-woocommerce' ), $section['total_label'] ) . '<p class="description">' . esc_html__( 'Each enabled tab has its own WooCommerce coupon. A selected quantity gift uses that tab’s coupon and remains linked only to its offer bundle.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		foreach ( $section['service_points'] ?? $defaults['service_points'] as $index => $point ) {
			$html .= '<div class="hlpb-cart-service"><strong>' . esc_html( sprintf( __( 'Service item %d', 'html-landing-page-builder-for-woocommerce' ), $index + 1 ) ) . '</strong><input type="text" aria-label="' . esc_attr__( 'Title', 'html-landing-page-builder-for-woocommerce' ) . '" name="' . esc_attr( $name . '[service_points][' . $index . '][title]' ) . '" value="' . esc_attr( $point['title'] ) . '"><input type="text" aria-label="' . esc_attr__( 'Text', 'html-landing-page-builder-for-woocommerce' ) . '" name="' . esc_attr( $name . '[service_points][' . $index . '][text]' ) . '" value="' . esc_attr( $point['text'] ) . '"></div>';
		}
		$html .= self::input( $name, 'payment_methods', __( 'Payment labels', 'html-landing-page-builder-for-woocommerce' ), $section['payment_methods'] ) . '</fieldset></div>';
		return $html;
	}

	/** List published simple and variable WooCommerce products for the editor. */
	private static function get_product_choices() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 500, 'orderby' => 'title', 'order' => 'ASC', 'type' => array( 'simple', 'variable' ) ) );
		$choices = array();
		foreach ( $products as $product ) {
			if ( $product && $product->is_purchasable() ) {
				$choices[] = array( 'id' => $product->get_id(), 'name' => $product->get_name() . ' (#' . $product->get_id() . ')' );
			}
		}
		return $choices;
	}

	private static function input( $base_name, $key, $label, $value, $nested = '' ) {
		$field_name = $base_name . ( $nested ? '[' . $nested . ']' : '' ) . '[' . $key . ']';
		return '<div class="hlpb-field"><label>' . esc_html( $label ) . '</label><input type="text" class="regular-text" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"></div>';
	}

	private static function textarea( $base_name, $key, $label, $value ) {
		return '<div class="hlpb-field"><label>' . esc_html( $label ) . '</label><textarea class="large-text" rows="2" name="' . esc_attr( $base_name . '[' . $key . ']' ) . '">' . esc_textarea( $value ) . '</textarea></div>';
	}

	/** Create or update one internal WooCommerce coupon per offer tab. */
	public static function ensure_coupons( $post_id, $section ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return array();
		}
		$section = self::sanitize( is_array( $section ) ? $section : array() );
		$defaults = self::defaults();
		$modes = isset( $section['modes'] ) && is_array( $section['modes'] ) ? $section['modes'] : array();
		$ids = get_post_meta( $post_id, self::COUPON_IDS_META, true );
		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
		if ( empty( $ids['buy'] ) ) {
			$ids['buy'] = absint( get_post_meta( $post_id, self::COUPON_META, true ) );
		}
		$prefixes = array( 'buy' => 'BuyMore', 'mix' => 'MixNMatch', 'bundle' => 'CozyBundle' );
		foreach ( $prefixes as $mode => $prefix ) {
			$mode_data = isset( $modes[ $mode ] ) && is_array( $modes[ $mode ] ) ? array_merge( $defaults['modes'][ $mode ], $modes[ $mode ] ) : $defaults['modes'][ $mode ];
			$entered_name = sanitize_text_field( $mode_data['coupon_name'] ?? $section['coupon_name'] ?? $defaults['modes'][ $mode ]['coupon_name'] );
			if ( '' === trim( $entered_name ) ) {
				$entered_name = $defaults['modes'][ $mode ]['coupon_name'];
			}
			$name = $prefix . ' ' . $entered_name;
			$coupon_id = absint( $ids[ $mode ] ?? 0 );
			$coupon = $coupon_id && 'shop_coupon' === get_post_type( $coupon_id ) ? new WC_Coupon( $coupon_id ) : new WC_Coupon();
			$base_code = substr( sanitize_title( $prefix . '-' . $entered_name ), 0, 32 );
			$code = $base_code . '-lp-' . absint( $post_id );
			$owner = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
			if ( $owner && (int) $owner !== (int) $coupon_id ) {
				$code = substr( $base_code, 0, 26 ) . '-hlpb-' . absint( $post_id ) . '-' . substr( $mode, 0, 1 );
			}
			$is_active = ! empty( $mode_data['enabled'] ) && 'publish' === get_post_status( $post_id );
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( 0 );
			$coupon->set_product_ids( array() );
			$coupon->set_individual_use( false );
			$coupon->set_description( sprintf( __( '%1$s offer coupon for landing page “%2$s”. Offer prices are validated by the landing page configuration.', 'html-landing-page-builder-for-woocommerce' ), $prefix, get_the_title( $post_id ) ) );
			if ( method_exists( $coupon, 'set_status' ) ) {
				$coupon->set_status( $is_active ? 'publish' : 'draft' );
			}
			$coupon->update_meta_data( self::COUPON_LANDING_META, absint( $post_id ) );
			$coupon->update_meta_data( self::COUPON_FLAG_META, 'yes' );
			$coupon->update_meta_data( '_hlpb_campaign_name', $name );
			$coupon->update_meta_data( '_hlpb_offer_mode', $mode );
			$coupon_id = $coupon->save();
			if ( $coupon_id ) {
				wp_update_post( array( 'ID' => $coupon_id, 'post_title' => $name, 'post_status' => $is_active ? 'publish' : 'draft' ) );
				$ids[ $mode ] = absint( $coupon_id );
			}
		}
		update_post_meta( $post_id, self::COUPON_IDS_META, $ids );
		if ( ! empty( $ids['buy'] ) ) {
			update_post_meta( $post_id, self::COUPON_META, absint( $ids['buy'] ) );
		}
		return $ids;
	}

	/** Backward-compatible entry point used by the landing builder. */
	public static function ensure_coupon( $post_id, $section ) {
		$ids = self::ensure_coupons( $post_id, $section );
		return absint( $ids['buy'] ?? 0 );
	}

	private static function coupon_ids_for_landing( $post_id ) {
		$ids = get_post_meta( $post_id, self::COUPON_IDS_META, true );
		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
		if ( empty( $ids['buy'] ) ) {
			$ids['buy'] = absint( get_post_meta( $post_id, self::COUPON_META, true ) );
		}
		return $ids;
	}

	private static function coupon_id_for_mode( $post_id, $mode ) {
		$ids = self::coupon_ids_for_landing( $post_id );
		return absint( $ids[ $mode ] ?? 0 );
	}

	/** Public data needed to render the selected WooCommerce products in the static HTML. */
	public static function build_public_config( $post_id, $sections = null ) {
		$sections = is_array( $sections ) ? $sections : HLPB_Landing_Builder::get_sections( $post_id );
		$section = self::find_section( $sections );
		if ( ! $section ) {
			return array();
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array( 'active' => false );
		}
		$settings = array_merge( self::defaults(), self::sanitize( $section ) );
		$product_ids = array();
		foreach ( $settings['modes'] as $mode ) {
			foreach ( $mode['products'] ?? array() as $product_id ) {
				if ( absint( $product_id ) ) {
					$product_ids[ absint( $product_id ) ] = absint( $product_id );
				}
			}
		}
		foreach ( $settings['modes'] as $mode_data ) {
			foreach ( $mode_data['rules'] ?? array() as $rule ) {
				$gift_id = absint( $rule['gift_product_id'] ?? 0 );
				if ( $gift_id ) {
					$product_ids[ $gift_id ] = $gift_id;
				}
			}
		}
		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product_data = self::get_product_public_data( $product_id );
			if ( $product_data ) {
				$products[ $product_id ] = $product_data;
			}
		}
		$settings['modes'] = array_intersect_key( $settings['modes'], array_flip( array( 'buy', 'mix', 'bundle' ) ) );
		$coupon_ids = self::coupon_ids_for_landing( $post_id );
		$needs_coupons = false;
		foreach ( array( 'buy', 'mix', 'bundle' ) as $coupon_mode ) {
			if ( empty( $coupon_ids[ $coupon_mode ] ) || 'shop_coupon' !== get_post_type( absint( $coupon_ids[ $coupon_mode ] ) ) ) {
				$needs_coupons = true;
				break;
			}
		}
		if ( 'publish' === get_post_status( $post_id ) && $needs_coupons ) {
			$coupon_ids = self::ensure_coupons( $post_id, $section );
		}
		$coupons = array();
		foreach ( array( 'buy', 'mix', 'bundle' ) as $mode ) {
			$coupon_id = absint( $coupon_ids[ $mode ] ?? 0 );
			$coupon = $coupon_id && class_exists( 'WC_Coupon' ) ? new WC_Coupon( $coupon_id ) : false;
			$coupons[ $mode ] = array( 'id' => $coupon && $coupon->get_id() ? $coupon_id : 0, 'code' => $coupon && $coupon->get_id() ? $coupon->get_code() : '' );
		}
		$has_active_mode = false;
		foreach ( array( 'buy', 'mix', 'bundle' ) as $mode ) {
			if ( ! empty( $settings['modes'][ $mode ]['enabled'] ) && ! empty( $coupons[ $mode ]['id'] ) && self::has_configured_mode( array( 'modes' => array( $mode => $settings['modes'][ $mode ] ) ), $products ) ) {
				$has_active_mode = true;
				break;
			}
		}
		$first_coupon = $coupons['buy'];
		$config = array(
			'active'         => $has_active_mode && 'publish' === get_post_status( $post_id ),
			'landingId'      => absint( $post_id ),
			'token'          => self::token( $post_id, $section ),
			'endpoint'       => admin_url( 'admin-post.php' ),
			'action'         => 'hlpb_landing_add_to_cart',
			'checkout'       => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
			'couponId'       => $first_coupon['id'],
			'couponCode'     => $first_coupon['code'],
			'coupons'        => $coupons,
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
			'currencyFormat' => function_exists( 'get_option' ) ? array( 'position' => get_option( 'woocommerce_currency_pos', 'left' ), 'decimals' => wc_get_price_decimals(), 'decimal' => wc_get_price_decimal_separator(), 'thousand' => wc_get_price_thousand_separator() ) : array(),
			'settings'       => array(
				'eyebrow' => $settings['eyebrow'], 'title' => $settings['title'], 'rating' => $settings['rating'], 'rating_count' => $settings['rating_count'],
				'countdown' => ! empty( $settings['countdown'] ), 'countdownText' => $settings['countdown_text'], 'buttonText' => $settings['button_text'],
				'accentColor' => $settings['accent_color'], 'background' => $settings['background'], 'modeLabels' => $settings['mode_labels'], 'modeCopy' => $settings['mode_copy'],
				'ratingStars' => absint( $settings['rating_stars'] ), 'savingLabel' => $settings['saving_label'], 'totalLabel' => $settings['total_label'],
				'servicePoints' => $settings['service_points'], 'paymentMethods' => $settings['payment_methods'],
			),
			'modes'          => $settings['modes'],
			'products'       => $products,
		);
		return $config;
	}

	private static function get_product_public_data( $product_id ) {
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product || ! $product->is_purchasable() || 'publish' !== get_post_status( $product->get_id() ) || ! $product->is_in_stock() ) {
			return false;
		}
		$images = self::product_images( $product );
		$placeholder_url = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_single' ) : '';
		$placeholder_image = $placeholder_url ? array( 'src' => $placeholder_url, 'thumb' => $placeholder_url, 'alt' => wp_strip_all_tags( $product->get_name() ) ) : '';
		$data = array(
			'id' => $product->get_id(), 'name' => wp_strip_all_tags( $product->get_name() ), 'price' => (float) wc_get_price_to_display( $product ), 'type' => $product->is_type( 'variable' ) ? 'variable' : 'simple',
			'image' => $images ? $images[0] : $placeholder_image, 'gallery' => $images ? $images : ( $placeholder_image ? array( $placeholder_image ) : array() ), 'attributes' => array(), 'variations' => array(), 'defaultAttributes' => array(),
		);
		if ( $product->is_type( 'variable' ) ) {
			$default_attributes = method_exists( $product, 'get_default_attributes' ) ? $product->get_default_attributes() : array();
			foreach ( (array) $default_attributes as $key => $value ) {
				$key = preg_replace( '/^attribute_/', '', (string) $key );
				$data['defaultAttributes'][ sanitize_key( $key ) ] = (string) $value;
			}
			foreach ( $product->get_variation_attributes() as $attribute_key => $options ) {
				$attribute_key = preg_replace( '/^attribute_/', '', (string) $attribute_key );
				$data['attributes'][] = array(
					'key' => sanitize_key( $attribute_key ),
					'label' => wc_attribute_label( $attribute_key, $product ),
					'type' => self::get_attribute_display_type( $attribute_key ),
					'options' => self::get_attribute_public_options( $attribute_key, $options ),
				);
			}
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation || ! $variation->variation_is_visible() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
					continue;
				}
				$variation_images = self::product_images( $variation, $product );
				$variation_attributes = array();
				foreach ( $variation->get_attributes() as $key => $value ) {
					$key = preg_replace( '/^attribute_/', '', (string) $key );
					$variation_attributes[ 'attribute_' . sanitize_key( $key ) ] = (string) $value;
				}
				$data['variations'][] = array(
					'id' => $variation->get_id(), 'price' => (float) wc_get_price_to_display( $variation ), 'attributes' => $variation_attributes,
					'image' => $variation_images ? $variation_images[0] : $data['image'], 'gallery' => $variation_images ? $variation_images : $images,
				);
			}
		}
		return $data;
	}

	private static function has_configured_mode( $settings, $products ) {
		foreach ( array( 'buy', 'mix', 'bundle' ) as $mode ) {
			if ( empty( $settings['modes'][ $mode ]['enabled'] ) ) {
				continue;
			}
			$ids = array_values( array_filter( array_map( 'absint', $settings['modes'][ $mode ]['products'] ?? array() ), static function ( $id ) use ( $products ) { return isset( $products[ $id ] ); } ) );
			if ( 'buy' === $mode && ! empty( $ids ) ) {
				return true;
			}
			if ( 'buy' !== $mode && count( array_unique( $ids ) ) >= 2 ) {
				return true;
			}
		}
		return false;
	}

	private static function product_images( $product, $parent = null ) {
		$ids = array();
		if ( $product->get_image_id() ) {
			$ids[] = $product->get_image_id();
		}
		if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
			$ids = array_merge( $ids, $product->get_gallery_image_ids() );
		}
		if ( $parent && method_exists( $parent, 'get_gallery_image_ids' ) ) {
			if ( $parent->get_image_id() ) {
				$ids[] = $parent->get_image_id();
			}
			$ids = array_merge( $ids, $parent->get_gallery_image_ids() );
		}
		$images = array();
		foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $image_id ) {
			$url = wp_get_attachment_image_url( $image_id, 'large' );
			if ( $url ) {
				$images[] = array( 'src' => $url, 'thumb' => wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) ?: $url, 'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $image_id ) );
			}
		}
		return $images;
	}

	private static function get_attribute_display_type( $attribute_key ) {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return 'select';
		}
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			if ( wc_attribute_taxonomy_name( $attribute->attribute_name ) === $attribute_key ) {
				return isset( $attribute->attribute_type ) ? sanitize_key( $attribute->attribute_type ) : 'select';
			}
		}
		return 'select';
	}

	private static function get_attribute_public_options( $attribute_key, $options ) {
		$values = array();
		foreach ( (array) $options as $value ) {
			$value = (string) $value;
			$option = array( 'value' => $value, 'label' => $value );
			if ( taxonomy_exists( $attribute_key ) ) {
				$term = get_term_by( 'slug', $value, $attribute_key );
				if ( $term && ! is_wp_error( $term ) ) {
					$option['label'] = $term->name;
					$color = get_term_meta( $term->term_id, HLPB_Attribute_Admin::COLOR_META, true );
					$image_id = absint( get_term_meta( $term->term_id, HLPB_Attribute_Admin::IMAGE_META, true ) );
					$option['color'] = sanitize_hex_color( $color ) ? sanitize_hex_color( $color ) : '';
					$option['image'] = $image_id ? ( wp_get_attachment_image_url( $image_id, 'thumbnail' ) ?: '' ) : '';
				}
			}
			$values[] = $option;
		}
		return $values;
	}

	private static function find_section( $sections ) {
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				return $section;
			}
		}
		return false;
	}

	private static function token( $post_id, $section ) {
		$payload = absint( $post_id ) . '|' . hash( 'sha256', wp_json_encode( $section ) );
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private static function coupon_code( $post_id ) {
		$coupon_id = self::coupon_id_for_mode( $post_id, 'buy' );
		if ( $coupon_id && class_exists( 'WC_Coupon' ) ) {
			$coupon = new WC_Coupon( $coupon_id );
			return $coupon->get_id() ? $coupon->get_code() : '';
		}
		return '';
	}

	/** Load only the configured product and variation data into the public landing JS. */
	public static function enqueue_public_assets( $post_id ) {
		$config = self::build_public_config( $post_id );
		if ( ! $config ) {
			return;
		}
		$style_dependencies = wp_style_is( 'hlpb-landing-design', 'registered' ) ? array( 'hlpb-landing-design' ) : array();
		wp_enqueue_style( 'hlpb-cart-section', HLPB_URL . 'assets/frontend/cart-section.css', $style_dependencies, HLPB_VERSION );
		$script_dependencies = wp_script_is( 'hlpb-landing-design', 'enqueued' ) ? array( 'hlpb-landing-design' ) : array();
		wp_enqueue_script( 'hlpb-cart-section', HLPB_URL . 'assets/frontend/cart-section.js', $script_dependencies, HLPB_VERSION, true );
		$config_json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$config_script = 'window.HLPBCartData=' . $config_json . ';';
		if ( $script_dependencies ) {
			wp_add_inline_script( 'hlpb-landing-design', $config_script, 'before' );
		} else {
			wp_add_inline_script( 'hlpb-cart-section', $config_script, 'before' );
		}
	}

	/** Copy the standalone interaction assets into a static landing export. */
	public static function copy_export_assets( $asset_dir ) {
		$files = array( 'assets/frontend/cart-section.js' => 'hlpb-cart.js', 'assets/frontend/cart-section.css' => 'hlpb-cart.css' );
		foreach ( $files as $source => $name ) {
			$path = HLPB_PATH . $source;
			if ( ! is_file( $path ) || ! copy( $path, trailingslashit( $asset_dir ) . $name ) ) {
				return false;
			}
		}
		return true;
	}

	/** Read JSON safely from a public form request, with server-side offer validation. */
	public static function handle_checkout_request() {
		if ( ! self::is_same_site_request() ) {
			self::checkout_error( __( 'This checkout request must come from the landing page.', 'html-landing-page-builder-for-woocommerce' ), 403 );
		}
		if ( function_exists( 'wc_load_cart' ) && function_exists( 'WC' ) && WC() && ! WC()->cart ) {
			wc_load_cart();
		}
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			wp_die( esc_html__( 'WooCommerce cart is unavailable.', 'html-landing-page-builder-for-woocommerce' ), '', array( 'response' => 503 ) );
		}
		$landing_id = isset( $_POST['landing_id'] ) ? absint( wp_unslash( $_POST['landing_id'] ) ) : 0;
		$sections = $landing_id ? HLPB_Landing_Builder::get_sections( $landing_id ) : array();
		$section = self::find_section( $sections );
		if ( ! $section || 'publish' !== get_post_status( $landing_id ) || ! isset( $_POST['token'] ) || ! hash_equals( self::token( $landing_id, $section ), sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) ) {
			self::checkout_error( __( 'This landing offer has changed. Refresh the page and try again.', 'html-landing-page-builder-for-woocommerce' ), 403 );
		}
		$settings = array_merge( self::defaults(), self::sanitize( $section ) );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $mode, array( 'buy', 'mix', 'bundle' ), true ) ) {
			self::checkout_error( __( 'The selected offer is invalid.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		$coupon_id = self::coupon_id_for_mode( $landing_id, $mode );
		if ( ! $coupon_id && class_exists( 'WC_Coupon' ) ) {
			$ids = self::ensure_coupons( $landing_id, $section );
			$coupon_id = absint( $ids[ $mode ] ?? 0 );
		}
		if ( ! $coupon_id || ! class_exists( 'WC_Coupon' ) ) {
			self::checkout_error( __( 'The offer coupon is not ready. Please contact the store.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		$quantity = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;
		$minimum_quantity = 'mix' === $mode ? 2 : 1;
		if ( empty( $settings['modes'][ $mode ]['enabled'] ) || $quantity < $minimum_quantity || $quantity > 4 ) {
			self::checkout_error( __( 'The selected offer is invalid.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		$rules = $settings['modes'][ $mode ]['rules'] ?? array();
		$rule = isset( $rules[ $quantity ] ) ? $rules[ $quantity ] : ( isset( $rules[ (string) $quantity ] ) ? $rules[ (string) $quantity ] : false );
		if ( ! $rule || ! self::configured_products_valid( $settings, $mode ) ) {
			self::checkout_error( __( 'Add the required WooCommerce products to this offer before publishing it.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		$include_gift = ! isset( $_POST['include_gift'] ) || '1' === sanitize_text_field( wp_unslash( $_POST['include_gift'] ) );
		$items_json = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
		$items = json_decode( $items_json, true );
		$validated = self::validate_selected_items( $items, $settings, $mode, $quantity );
		if ( is_wp_error( $validated ) ) {
			self::checkout_error( $validated->get_error_message(), 400 );
		}
		$bundle_key = wp_generate_uuid4();
		$base_total = 0;
		foreach ( $validated as $item ) {
			$base_total += $item['price'];
		}
		$coupon = new WC_Coupon( $coupon_id );
		if ( ! $coupon->get_id() ) {
			$ids = self::ensure_coupons( $landing_id, $section );
			$coupon_id = absint( $ids[ $mode ] ?? 0 );
			$coupon = $coupon_id ? new WC_Coupon( $coupon_id ) : new WC_Coupon();
		}
		if ( ! $coupon->get_id() ) {
			self::checkout_error( __( 'The offer coupon is not available.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		$campaign_name = (string) $coupon->get_meta( '_hlpb_campaign_name', true );
		$cart_ids = array();
		foreach ( $validated as $item ) {
			$cart_data = array(
				'_hlpb_landing_id' => $landing_id, '_hlpb_coupon_id' => $coupon_id, '_hlpb_campaign_name' => $campaign_name, '_hlpb_bundle_key' => $bundle_key,
				'_hlpb_mode' => $mode, '_hlpb_bundle_quantity' => $quantity, '_hlpb_rule' => $rule,
				'_hlpb_base_price' => $item['price'], '_hlpb_base_total' => $base_total, '_hlpb_is_addon' => false, '_hlpb_is_gift' => false, '_hlpb_gift_eligible' => false, '_hlpb_gift_opt_in' => $include_gift,
			);
			$cart_item_key = WC()->cart->add_to_cart( $item['product_id'], 1, $item['variation_id'], $item['variation'], $cart_data );
			if ( ! $cart_item_key ) {
				self::rollback_bundle( $bundle_key );
				self::checkout_error( __( 'One of the selected variations is no longer available.', 'html-landing-page-builder-for-woocommerce' ), 400 );
			}
			$cart_ids[] = $cart_item_key;
		}
		$gift_product_id = absint( $rule['gift_product_id'] ?? 0 );
		$gift_mode = sanitize_key( $rule['gift_mode'] ?? 'none' );
		if ( $include_gift && $gift_product_id && in_array( $gift_mode, array( 'free', 'discounted' ), true ) ) {
			$gift_product = wc_get_product( $gift_product_id );
			if ( ! $gift_product || ! $gift_product->is_purchasable() || ! $gift_product->is_in_stock() ) {
				self::rollback_bundle( $bundle_key );
				self::checkout_error( __( 'The configured gift is unavailable.', 'html-landing-page-builder-for-woocommerce' ), 400 );
			}
			$gift_selection = isset( $_POST['gift_item'] ) ? json_decode( wp_unslash( $_POST['gift_item'] ), true ) : array( 'product_id' => $gift_product_id );
			$gift_validated = self::validate_one_product_selection( $gift_selection, array( $gift_product_id ) );
			if ( is_wp_error( $gift_validated ) ) {
				self::rollback_bundle( $bundle_key );
				self::checkout_error( $gift_validated->get_error_message(), 400 );
			}
			$gift_data = array(
				'_hlpb_landing_id' => $landing_id, '_hlpb_coupon_id' => $coupon_id, '_hlpb_campaign_name' => $campaign_name, '_hlpb_bundle_key' => $bundle_key,
				'_hlpb_mode' => $mode, '_hlpb_bundle_quantity' => $quantity, '_hlpb_rule' => $rule,
				'_hlpb_base_price' => $gift_validated['price'], '_hlpb_base_total' => 0,
				'_hlpb_is_addon' => false, '_hlpb_is_gift' => true, '_hlpb_gift_eligible' => true,
			);
			$gift_cart_key = WC()->cart->add_to_cart( $gift_validated['product_id'], 1, $gift_validated['variation_id'], $gift_validated['variation'], $gift_data );
			if ( ! $gift_cart_key ) {
				self::rollback_bundle( $bundle_key );
				self::checkout_error( __( 'The configured gift could not be added to your cart.', 'html-landing-page-builder-for-woocommerce' ), 400 );
			}
		}
		if ( ! $coupon->get_id() || ( ! WC()->cart->has_discount( $coupon->get_code() ) && ! WC()->cart->apply_coupon( $coupon->get_code() ) ) ) {
			self::rollback_bundle( $bundle_key );
			self::checkout_error( __( 'WooCommerce could not apply the offer coupon. Please check the offer settings.', 'html-landing-page-builder-for-woocommerce' ), 400 );
		}
		WC()->cart->calculate_totals();
		WC()->cart->set_session();
		if ( isset( $_POST['ajax'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ajax'] ) ) ) {
			wp_send_json_success( array( 'redirect' => wc_get_checkout_url() ) );
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	private static function checkout_error( $message, $status = 400 ) {
		if ( isset( $_POST['ajax'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ajax'] ) ) ) {
			wp_send_json_error( array( 'message' => wp_strip_all_tags( $message ) ), $status );
		}
		wp_die( esc_html( $message ), '', array( 'response' => $status ) );
	}

	private static function is_same_site_request() {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( '' === $origin && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		if ( '' === $origin ) {
			return false;
		}
		$origin_host = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return '' !== $site_host && hash_equals( $site_host, $origin_host );
	}

	private static function configured_products_valid( $settings, $mode ) {
		$product_ids = array_values( array_filter( array_map( 'absint', $settings['modes'][ $mode ]['products'] ?? array() ) ) );
		return 'buy' === $mode ? ! empty( $product_ids ) : count( array_unique( $product_ids ) ) >= 2;
	}

	private static function validate_selected_items( $items, $settings, $mode, $quantity ) {
		if ( ! is_array( $items ) || count( $items ) > 8 ) {
			return new WP_Error( 'hlpb_items_invalid', __( 'The selected products could not be read.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$product_ids = array_values( array_filter( array_map( 'absint', $settings['modes'][ $mode ]['products'] ?? array() ) ) );
		if ( 'buy' === $mode ) {
			if ( count( $items ) !== $quantity ) {
				return new WP_Error( 'hlpb_quantity_invalid', __( 'Choose one variation for each item in this offer.', 'html-landing-page-builder-for-woocommerce' ) );
			}
		} elseif ( 'mix' === $mode ) {
			if ( count( $items ) !== $quantity ) {
				return new WP_Error( 'hlpb_quantity_invalid', __( 'Choose a product and variation for each item in your mix.', 'html-landing-page-builder-for-woocommerce' ) );
			}
		} elseif ( count( $items ) !== $quantity * 2 ) {
			return new WP_Error( 'hlpb_quantity_invalid', __( 'Choose both products for each bundle.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$validated = array();
		$counts = array();
		foreach ( $items as $selection ) {
			$item = self::validate_one_product_selection( $selection, $product_ids );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$validated[] = $item;
			$counts[ $item['product_id'] ] = ( $counts[ $item['product_id'] ] ?? 0 ) + 1;
		}
		if ( 'mix' === $mode && count( $counts ) < 2 ) {
			return new WP_Error( 'hlpb_mix_selection', __( 'Choose at least two different products for your mix.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( 'bundle' === $mode ) {
			foreach ( array_slice( $product_ids, 0, 2 ) as $product_id ) {
				if ( ( $counts[ $product_id ] ?? 0 ) !== $quantity ) {
					return new WP_Error( 'hlpb_bundle_selection', __( 'Each Cozy bundle must contain one of both configured products.', 'html-landing-page-builder-for-woocommerce' ) );
				}
			}
		}
		return $validated;
	}

	private static function validate_one_product_selection( $selection, $allowed_product_ids ) {
		if ( ! is_array( $selection ) ) {
			return new WP_Error( 'hlpb_selection_invalid', __( 'A product selection is invalid.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$product_id = isset( $selection['product_id'] ) ? absint( $selection['product_id'] ) : 0;
		if ( ! $product_id || ! in_array( $product_id, array_map( 'absint', $allowed_product_ids ), true ) ) {
			return new WP_Error( 'hlpb_product_not_allowed', __( 'A selected product is not part of this offer.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product_id ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return new WP_Error( 'hlpb_product_unavailable', __( 'A selected product is unavailable.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$variation_id = isset( $selection['variation_id'] ) ? absint( $selection['variation_id'] ) : 0;
		$variation = array();
		$selected = $product;
		if ( $product->is_type( 'variable' ) ) {
			$variation_product = $variation_id ? wc_get_product( $variation_id ) : false;
			if ( ! $variation_product || ! $variation_product->is_type( 'variation' ) || absint( $variation_product->get_parent_id() ) !== $product_id || ! $variation_product->variation_is_visible() || ! $variation_product->is_purchasable() || ! $variation_product->is_in_stock() ) {
				return new WP_Error( 'hlpb_variation_unavailable', __( 'Select an available variation for each product.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$stored_attributes = $variation_product->get_variation_attributes();
			foreach ( $product->get_variation_attributes() as $attribute_key => $options ) {
				$raw_attribute_key = preg_replace( '/^attribute_/', '', (string) $attribute_key );
				$selection_key = sanitize_key( $raw_attribute_key );
				$attribute_key = sanitize_title( $raw_attribute_key );
				$attribute_name = 'attribute_' . $attribute_key;
				$selected_raw = isset( $selection['attributes'][ $selection_key ] ) ? $selection['attributes'][ $selection_key ] : ( $selection['attributes'][ $attribute_key ] ?? '' );
				$selected_value = is_scalar( $selected_raw ) ? wc_clean( (string) $selected_raw ) : '';
				$actual = isset( $stored_attributes[ $attribute_name ] ) ? (string) $stored_attributes[ $attribute_name ] : '';
				if ( '' === $selected_value ) {
					return new WP_Error( 'hlpb_variation_incomplete', __( 'Select every variation option before continuing.', 'html-landing-page-builder-for-woocommerce' ) );
				}
				if ( '' !== $actual && $selected_value !== $actual ) {
					return new WP_Error( 'hlpb_variation_mismatch', __( 'The selected options do not match this variation. Refresh the page and try again.', 'html-landing-page-builder-for-woocommerce' ) );
				}
				if ( ! empty( $options ) && ! in_array( $selected_value, array_map( 'strval', (array) $options ), true ) ) {
					return new WP_Error( 'hlpb_variation_option_invalid', __( 'One of the selected product options is unavailable.', 'html-landing-page-builder-for-woocommerce' ) );
				}
				$variation[ $attribute_name ] = $selected_value;
			}
			$data_store = class_exists( 'WC_Data_Store' ) ? WC_Data_Store::load( 'product' ) : false;
			if ( $data_store && method_exists( $data_store, 'find_matching_product_variation' ) ) {
				$matching_id = absint( $data_store->find_matching_product_variation( $product, $variation ) );
				if ( $matching_id !== $variation_id ) {
					return new WP_Error( 'hlpb_variation_mismatch', __( 'The selected options do not match this variation. Refresh the page and try again.', 'html-landing-page-builder-for-woocommerce' ) );
				}
			}
			$selected = $variation_product;
		} elseif ( $variation_id ) {
			return new WP_Error( 'hlpb_variation_invalid', __( 'This product does not use variations.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		return array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'variation' => $variation, 'price' => (float) $selected->get_price( 'edit' ) );
	}

	private static function rollback_bundle( $bundle_key ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		self::$syncing_cart = true;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['_hlpb_bundle_key'] ) && $bundle_key === $cart_item['_hlpb_bundle_key'] ) {
				WC()->cart->remove_cart_item( $cart_item_key );
			}
		}
		self::$syncing_cart = false;
	}

	/** Restrict campaign coupons to cart rows created by this exact landing offer. */
	public static function validate_coupon( $valid, $coupon, $discounts ) {
		if ( 'yes' !== $coupon->get_meta( self::COUPON_FLAG_META, true ) ) {
			return $valid;
		}
		$landing_id = absint( $coupon->get_meta( self::COUPON_LANDING_META, true ) );
		$mode = sanitize_key( $coupon->get_meta( '_hlpb_offer_mode', true ) );
		if ( ! $landing_id || ! in_array( $mode, array( 'buy', 'mix', 'bundle' ), true ) || 'publish' !== get_post_status( $landing_id ) || self::coupon_id_for_mode( $landing_id, $mode ) !== $coupon->get_id() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		$settings = self::settings_for_landing( $landing_id );
		if ( ! $settings || empty( $settings['modes'][ $mode ]['enabled'] ) ) {
			return false;
		}
		foreach ( self::get_coupon_bundles( $coupon->get_id() ) as $bundle ) {
			if ( absint( $bundle['landing_id'] ?? 0 ) === $landing_id && sanitize_key( $bundle['mode'] ?? '' ) === $mode && ! empty( $bundle['core_count'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function coupon_valid_for_product( $valid, $product, $coupon, $values ) {
		if ( 'yes' !== $coupon->get_meta( self::COUPON_FLAG_META, true ) ) {
			return $valid;
		}
		if ( ! is_array( $values ) || absint( $values['_hlpb_coupon_id'] ?? 0 ) !== $coupon->get_id() ) {
			return false;
		}
		$landing_id = absint( $coupon->get_meta( self::COUPON_LANDING_META, true ) );
		$settings = self::settings_for_landing( $landing_id );
		$mode = sanitize_key( $values['_hlpb_mode'] ?? '' );
		if ( ! $settings || ! isset( $settings['modes'][ $mode ] ) || sanitize_key( $coupon->get_meta( '_hlpb_offer_mode', true ) ) !== $mode ) {
			return false;
		}
		if ( ! empty( $values['_hlpb_is_gift'] ) ) {
			$bundle = self::get_coupon_bundle( $coupon->get_id(), (string) ( $values['_hlpb_bundle_key'] ?? '' ) );
			$rule = $bundle ? ( $bundle['rule'] ?? array() ) : array();
			return ! empty( $values['_hlpb_gift_eligible'] ) && self::bundle_is_current( $bundle, $landing_id ) && absint( $values['product_id'] ?? 0 ) === absint( $rule['gift_product_id'] ?? 0 ) && in_array( $rule['gift_mode'] ?? 'none', array( 'free', 'discounted' ), true );
		}
		$bundle = self::get_coupon_bundle( $coupon->get_id(), (string) ( $values['_hlpb_bundle_key'] ?? '' ) );
		return ! empty( $values['_hlpb_bundle_key'] ) && self::bundle_is_current( $bundle, $landing_id ) && empty( $values['_hlpb_is_gift'] ) && in_array( absint( $values['product_id'] ?? 0 ), array_map( 'absint', $settings['modes'][ $mode ]['products'] ?? array() ), true );
	}

	public static function coupon_discount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( 'yes' !== $coupon->get_meta( self::COUPON_FLAG_META, true ) || ! is_array( $cart_item ) || absint( $cart_item['_hlpb_coupon_id'] ?? 0 ) !== $coupon->get_id() ) {
			return $discount;
		}
		$bundle = self::get_coupon_bundle( $coupon->get_id(), (string) ( $cart_item['_hlpb_bundle_key'] ?? '' ) );
		if ( ! $bundle || ! self::bundle_is_current( $bundle, absint( $cart_item['_hlpb_landing_id'] ?? 0 ) ) ) {
			return 0;
		}
		$rule = $bundle['rule'];
		$line_quantity = max( 1, absint( $cart_item['quantity'] ?? 1 ) );
		$base = (float) ( $cart_item['_hlpb_base_price'] ?? 0 ) * $line_quantity;
		if ( $base <= 0 ) {
			return 0;
		}
		if ( ! empty( $cart_item['_hlpb_is_gift'] ) ) {
			if ( empty( $cart_item['_hlpb_gift_eligible'] ) || absint( $cart_item['product_id'] ?? 0 ) !== absint( $rule['gift_product_id'] ?? 0 ) ) {
				return 0;
			}
			$gift_mode = sanitize_key( $rule['gift_mode'] ?? 'none' );
			if ( 'free' === $gift_mode ) {
				$gift_discount = $base;
				return min( (float) $discounting_amount, $single ? $gift_discount / $line_quantity : $gift_discount );
			}
			if ( 'discounted' === $gift_mode ) {
				$value = max( 0, (float) ( $rule['gift_discount_value'] ?? 0 ) );
				$off = 'fixed_price' === ( $rule['gift_discount_type'] ?? 'percent' ) ? max( 0, $base - $value * max( 1, absint( $cart_item['quantity'] ?? 1 ) ) ) : $base * min( 100, $value ) / 100;
				$gift_discount = round( $off, wc_get_price_decimals() );
				return min( (float) $discounting_amount, $single ? round( $gift_discount / $line_quantity, wc_get_price_decimals() ) : $gift_discount );
			}
			return 0;
		}
		if ( empty( $bundle['core_total'] ) ) {
			return 0;
		}
		$value = max( 0, (float) ( $rule['value'] ?? 0 ) );
		if ( 'percent' === ( $rule['type'] ?? '' ) ) {
			$discount_total = $bundle['core_total'] * min( 100, $value ) / 100;
		} elseif ( 'amount' === ( $rule['type'] ?? '' ) ) {
			$discount_total = min( $bundle['core_total'], $value );
		} elseif ( 'fixed_total' === ( $rule['type'] ?? '' ) ) {
			$discount_total = max( 0, $bundle['core_total'] - $value );
		} else {
			$discount_total = 0;
		}
		$line_discount = round( $discount_total * ( $base / $bundle['core_total'] ), wc_get_price_decimals() );
		return min( (float) $discounting_amount, $single ? round( $line_discount / $line_quantity, wc_get_price_decimals() ) : $line_discount );
	}

	private static function get_coupon_bundles( $coupon_id ) {
		$bundles = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $bundles;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( absint( $item['_hlpb_coupon_id'] ?? 0 ) !== absint( $coupon_id ) || empty( $item['_hlpb_bundle_key'] ) ) {
				continue;
			}
			$key = (string) $item['_hlpb_bundle_key'];
			$bundles[ $key ]['id'] = $key;
			$bundles[ $key ]['landing_id'] = absint( $item['_hlpb_landing_id'] ?? 0 );
			$bundles[ $key ]['mode'] = sanitize_key( $item['_hlpb_mode'] ?? '' );
			$bundles[ $key ]['quantity'] = absint( $item['_hlpb_bundle_quantity'] ?? 0 );
			$bundles[ $key ]['rule'] = is_array( $item['_hlpb_rule'] ?? null ) ? $item['_hlpb_rule'] : array();
			if ( empty( $item['_hlpb_is_addon'] ) && empty( $item['_hlpb_is_gift'] ) ) {
				$bundles[ $key ]['core_total'] = ( $bundles[ $key ]['core_total'] ?? 0 ) + (float) ( $item['_hlpb_base_price'] ?? 0 ) * max( 1, absint( $item['quantity'] ?? 1 ) );
				$bundles[ $key ]['core_count'] = ( $bundles[ $key ]['core_count'] ?? 0 ) + max( 1, absint( $item['quantity'] ?? 1 ) );
			}
			$bundles[ $key ]['items'][] = $item;
		}
		return $bundles;
	}

	private static function get_coupon_bundle( $coupon_id, $bundle_key ) {
		$bundles = self::get_coupon_bundles( $coupon_id );
		return isset( $bundles[ $bundle_key ] ) ? $bundles[ $bundle_key ] : false;
	}

	private static function bundle_is_current( $bundle, $landing_id ) {
		if ( ! is_array( $bundle ) || empty( $bundle['core_count'] ) || absint( $bundle['landing_id'] ) !== absint( $landing_id ) ) {
			return false;
		}
		$settings = self::settings_for_landing( $landing_id );
		if ( ! $settings || empty( $settings['modes'][ $bundle['mode'] ]['enabled'] ) ) {
			return false;
		}
		$mode = $bundle['mode'];
		$quantity = absint( $bundle['quantity'] );
		$expected = 'bundle' === $mode ? $quantity * 2 : $quantity;
		$rules = $settings['modes'][ $mode ]['rules'] ?? array();
		$current_rule = $rules[ $quantity ] ?? $rules[ (string) $quantity ] ?? false;
		if ( ! $current_rule || (int) $bundle['core_count'] !== (int) $expected || sanitize_key( $bundle['rule']['type'] ?? '' ) !== sanitize_key( $current_rule['type'] ?? '' ) || (float) ( $bundle['rule']['value'] ?? 0 ) !== (float) ( $current_rule['value'] ?? 0 ) || absint( $bundle['rule']['gift_product_id'] ?? 0 ) !== absint( $current_rule['gift_product_id'] ?? 0 ) || sanitize_key( $bundle['rule']['gift_mode'] ?? 'none' ) !== sanitize_key( $current_rule['gift_mode'] ?? 'none' ) || sanitize_key( $bundle['rule']['gift_discount_type'] ?? 'percent' ) !== sanitize_key( $current_rule['gift_discount_type'] ?? 'percent' ) || (float) ( $bundle['rule']['gift_discount_value'] ?? 0 ) !== (float) ( $current_rule['gift_discount_value'] ?? 0 ) ) {
			return false;
		}
		if ( 'mix' === $mode && $quantity < 2 ) {
			return false;
		}
		$allowed_ids = array_values( array_filter( array_map( 'absint', $settings['modes'][ $mode ]['products'] ?? array() ) ) );
		$core_counts = array();
		foreach ( $bundle['items'] ?? array() as $item ) {
			if ( ! empty( $item['_hlpb_is_addon'] ) || ! empty( $item['_hlpb_is_gift'] ) ) {
				continue;
			}
			$product_id = absint( $item['product_id'] ?? 0 );
			if ( ! in_array( $product_id, $allowed_ids, true ) ) {
				return false;
			}
			$core_counts[ $product_id ] = ( $core_counts[ $product_id ] ?? 0 ) + max( 1, absint( $item['quantity'] ?? 1 ) );
		}
		if ( 'bundle' === $mode ) {
			foreach ( array_slice( array_unique( $allowed_ids ), 0, 2 ) as $product_id ) {
				if ( ( $core_counts[ $product_id ] ?? 0 ) !== $quantity ) {
					return false;
				}
			}
		} elseif ( 'mix' === $mode && count( $core_counts ) < 2 ) {
			return false;
		}
		return true;
	}

	private static function settings_for_landing( $landing_id ) {
		if ( ! $landing_id || HLPB_Landing_Builder::POST_TYPE !== get_post_type( $landing_id ) ) {
			return false;
		}
		$section = self::find_section( HLPB_Landing_Builder::get_sections( $landing_id ) );
		return $section ? array_merge( self::defaults(), self::sanitize( $section ) ) : false;
	}

	/** Keep the offer coupon unavailable when its landing page is trashed. */
	public static function deactivate_for_landing( $post_id ) {
		if ( HLPB_Landing_Builder::POST_TYPE !== get_post_type( $post_id ) || ! class_exists( 'WC_Coupon' ) ) {
			return;
		}
		foreach ( array_unique( array_filter( self::coupon_ids_for_landing( $post_id ) ) ) as $coupon_id ) {
			if ( ! $coupon_id || 'shop_coupon' !== get_post_type( $coupon_id ) ) {
				continue;
			}
			$coupon = new WC_Coupon( $coupon_id );
			if ( method_exists( $coupon, 'set_status' ) ) {
				$coupon->set_status( 'draft' );
				$coupon->save();
			}
		}
	}

	/** Re-enable a coupon after restoring a published landing page. */
	public static function restore_for_landing( $post_id ) {
		if ( HLPB_Landing_Builder::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$sections = HLPB_Landing_Builder::get_sections( $post_id );
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				self::ensure_coupons( $post_id, $section );
				break;
			}
		}
	}

	/** Delete the landing-owned coupon with its landing page. */
	public static function delete_coupon_for_landing( $post_id ) {
		if ( HLPB_Landing_Builder::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		foreach ( array_unique( array_filter( self::coupon_ids_for_landing( $post_id ) ) ) as $coupon_id ) {
			if ( $coupon_id && 'shop_coupon' === get_post_type( $coupon_id ) ) {
				wp_delete_post( $coupon_id, true );
			}
		}
	}

	/** Mark campaign lines in cart and checkout while keeping unrelated duplicates separate. */
	public static function display_cart_offer_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['_hlpb_bundle_key'] ) || empty( $cart_item['_hlpb_coupon_id'] ) || ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->cart->has_discount( ( new WC_Coupon( absint( $cart_item['_hlpb_coupon_id'] ) ) )->get_code() ) ) {
			return $item_data;
		}
		$bundle = self::get_coupon_bundle( absint( $cart_item['_hlpb_coupon_id'] ), (string) $cart_item['_hlpb_bundle_key'] );
		if ( ! self::bundle_is_current( $bundle, absint( $cart_item['_hlpb_landing_id'] ?? 0 ) ) ) {
			return $item_data;
		}
		$name = sanitize_text_field( $cart_item['_hlpb_campaign_name'] ?? '' );
		if ( '' !== $name ) {
			$item_data[] = array( 'key' => __( 'Offer', 'html-landing-page-builder-for-woocommerce' ), 'value' => esc_html( $name ) );
		}
		$item_data[] = array( 'key' => __( 'Offer bundle', 'html-landing-page-builder-for-woocommerce' ), 'value' => esc_html( strtoupper( substr( preg_replace( '/[^a-f0-9]/i', '', (string) $cart_item['_hlpb_bundle_key'] ), 0, 8 ) ) ) );
		if ( ! empty( $cart_item['_hlpb_is_gift'] ) ) {
			$rule = $bundle['rule'] ?? array();
			$status = empty( $cart_item['_hlpb_gift_eligible'] ) ? __( 'Regular price — gift not included in this quantity', 'html-landing-page-builder-for-woocommerce' ) : ( 'free' === ( $rule['gift_mode'] ?? '' ) ? __( 'Free gift', 'html-landing-page-builder-for-woocommerce' ) : __( 'Discounted gift', 'html-landing-page-builder-for-woocommerce' ) );
			$item_data[] = array( 'key' => __( 'Gift status', 'html-landing-page-builder-for-woocommerce' ), 'value' => esc_html( $status ) );
		}
		return $item_data;
	}

	/** Remove an orphaned gift after its offer's final core line is removed. */
	public static function sync_bundle_after_removal( $removed_key, $cart ) {
		if ( self::$syncing_cart || ! $cart || empty( $cart->removed_cart_contents[ $removed_key ]['_hlpb_bundle_key'] ) ) {
			return;
		}
		$removed = $cart->removed_cart_contents[ $removed_key ];
		if ( ! empty( $removed['_hlpb_is_gift'] ) ) {
			return;
		}
		$bundle_key = (string) $removed['_hlpb_bundle_key'];
		$has_core = false;
		foreach ( $cart->get_cart() as $item ) {
			if ( ( $item['_hlpb_bundle_key'] ?? '' ) === $bundle_key && empty( $item['_hlpb_is_gift'] ) ) {
				$has_core = true;
				break;
			}
		}
		if ( $has_core ) {
			self::sync_changed_bundle_rule( $bundle_key, $cart );
			return;
		}
		self::$syncing_cart = true;
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ( $item['_hlpb_bundle_key'] ?? '' ) === $bundle_key && ! empty( $item['_hlpb_is_gift'] ) ) {
				$cart->remove_cart_item( $key );
			}
		}
		self::$syncing_cart = false;
	}

	/** Keep one qualifying gift per offer group; extra units are a regular cart line. */
	public static function sync_bundle_after_quantity_change( $cart_item_key, $quantity, $old_quantity, $cart ) {
		if ( self::$syncing_cart || ! $cart || empty( $cart->cart_contents[ $cart_item_key ]['_hlpb_bundle_key'] ) ) {
			return;
		}
		$item = $cart->cart_contents[ $cart_item_key ];
		if ( ! empty( $item['_hlpb_is_gift'] ) ) {
			if ( $quantity <= 1 ) {
				return;
			}
			$product_id = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			$variation = is_array( $item['variation'] ?? null ) ? $item['variation'] : array();
			self::$syncing_cart = true;
			$cart->set_quantity( $cart_item_key, 1, false );
			$cart->add_to_cart( $product_id, $quantity - 1, $variation_id, $variation );
			self::$syncing_cart = false;
			return;
		}
		self::sync_changed_bundle_rule( (string) $item['_hlpb_bundle_key'], $cart );
	}

	/** Persist a shopper's explicit gift removal; later quantity edits must not add it back. */
	public static function remember_removed_bundle_gift( $cart_item_key, $cart ) {
		if ( self::$syncing_cart || ! $cart || empty( $cart->cart_contents[ $cart_item_key ]['_hlpb_is_gift'] ) || empty( $cart->cart_contents[ $cart_item_key ]['_hlpb_bundle_key'] ) ) {
			return;
		}
		$bundle_key = (string) $cart->cart_contents[ $cart_item_key ]['_hlpb_bundle_key'];
		foreach ( $cart->cart_contents as $key => $row ) {
			if ( $key !== $cart_item_key && ( $row['_hlpb_bundle_key'] ?? '' ) === $bundle_key && empty( $row['_hlpb_is_gift'] ) ) {
				$cart->cart_contents[ $key ]['_hlpb_gift_removed'] = true;
			}
		}
	}

	/** Keep only offer-eligible units in a bundle; overflow becomes a regular cart item. */
	private static function split_bundle_quantity_overflow( $cart, $mode, $mode_config, $core_rows, $core_count, $product_counts ) {
		$allowed = array_values( array_unique( array_filter( array_map( 'absint', $mode_config['products'] ?? array() ) ) ) );
		$keep_limits = array_fill_keys( array_keys( $product_counts ), 0 );
		$should_split = false;

		if ( 'buy' === $mode && $core_count > 4 && ! empty( $allowed ) ) {
			$keep_limits[ $allowed[0] ] = 4;
			$should_split = true;
		} elseif ( 'mix' === $mode ) {
			$mix_products = array_values( array_filter( $allowed, static function ( $product_id ) use ( $product_counts ) {
				return ! empty( $product_counts[ $product_id ] );
			} ) );
			if ( $core_count < 2 || count( $mix_products ) < 2 ) {
				$should_split = ! empty( $core_rows );
			} elseif ( $core_count > 4 ) {
				foreach ( $mix_products as $product_id ) {
					$keep_limits[ $product_id ] = 1;
				}
				$remaining = 4 - count( $mix_products );
				foreach ( $mix_products as $product_id ) {
					$extra = max( 0, absint( $product_counts[ $product_id ] ) - 1 );
					$keep = min( $remaining, $extra );
					$keep_limits[ $product_id ] += $keep;
					$remaining -= $keep;
				}
				$should_split = true;
			}
		} elseif ( 'bundle' === $mode && count( $allowed ) >= 2 ) {
			$first_count  = absint( $product_counts[ $allowed[0] ] ?? 0 );
			$second_count = absint( $product_counts[ $allowed[1] ] ?? 0 );
			if ( ! $first_count || ! $second_count ) {
				$should_split = ! empty( $core_rows );
			} elseif ( $first_count > 4 || $second_count > 4 ) {
				$keep_limits[ $allowed[0] ] = min( 4, $first_count );
				$keep_limits[ $allowed[1] ] = min( 4, $second_count );
				$should_split = true;
			}
		}

		if ( ! $should_split ) {
			return false;
		}

		$remaining_limits = $keep_limits;
		self::$syncing_cart = true;
		foreach ( $core_rows as $key => $row ) {
			if ( ! isset( $cart->cart_contents[ $key ] ) ) {
				continue;
			}
			$quantity     = max( 1, absint( $row['quantity'] ?? 1 ) );
			$product_id   = absint( $row['product_id'] ?? 0 );
			$keep         = min( $quantity, max( 0, absint( $remaining_limits[ $product_id ] ?? 0 ) ) );
			$remaining_limits[ $product_id ] = max( 0, absint( $remaining_limits[ $product_id ] ?? 0 ) - $keep );
			$overflow     = $quantity - $keep;
			if ( $overflow < 1 ) {
				continue;
			}
			$variation_id = absint( $row['variation_id'] ?? 0 );
			$variation    = is_array( $row['variation'] ?? null ) ? $row['variation'] : array();
			if ( $keep > 0 ) {
				$cart->set_quantity( $key, $keep, false );
			} else {
				$cart->remove_cart_item( $key );
			}
			$cart->add_to_cart( $product_id, $overflow, $variation_id, $variation );
		}
		self::$syncing_cart = false;

		return true;
	}

	/** Update the active quantity rule and attach at most one eligible gift for a bundle. */
	private static function sync_changed_bundle_rule( $bundle_key, $cart ) {
		if ( self::$syncing_cart || ! $cart || '' === (string) $bundle_key ) {
			return;
		}
		$core_rows = array();
		$gift_rows = array();
		$mode = '';
		$landing_id = 0;
		$coupon_id = 0;
		$campaign_name = '';
		$core_count = 0;
		$product_counts = array();
		$gift_was_removed = false;
		$gift_opt_in = true;
		foreach ( $cart->cart_contents as $key => $row ) {
			if ( ( $row['_hlpb_bundle_key'] ?? '' ) !== (string) $bundle_key ) {
				continue;
			}
			$mode = sanitize_key( $row['_hlpb_mode'] ?? $mode );
			$landing_id = absint( $row['_hlpb_landing_id'] ?? $landing_id );
			$coupon_id = absint( $row['_hlpb_coupon_id'] ?? $coupon_id );
			$campaign_name = sanitize_text_field( $row['_hlpb_campaign_name'] ?? $campaign_name );
			if ( ! empty( $row['_hlpb_is_gift'] ) ) {
				$gift_rows[ $key ] = $row;
				continue;
			}
			$core_rows[ $key ] = $row;
			$gift_opt_in = $gift_opt_in && ( ! array_key_exists( '_hlpb_gift_opt_in', $row ) || ! empty( $row['_hlpb_gift_opt_in'] ) );
			$core_count += max( 1, absint( $row['quantity'] ?? 1 ) );
			$product_id = absint( $row['product_id'] ?? 0 );
			$product_counts[ $product_id ] = ( $product_counts[ $product_id ] ?? 0 ) + max( 1, absint( $row['quantity'] ?? 1 ) );
			$gift_was_removed = $gift_was_removed || ! empty( $row['_hlpb_gift_removed'] );
		}
		if ( ! $core_rows ) {
			return;
		}
		$settings = self::settings_for_landing( $landing_id );
		$mode_config = $settings['modes'][ $mode ] ?? array();
		if ( self::split_bundle_quantity_overflow( $cart, $mode, $mode_config, $core_rows, $core_count, $product_counts ) ) {
			$core_rows = array();
			$core_count = 0;
			$product_counts = array();
			foreach ( $cart->cart_contents as $key => $row ) {
				if ( ( $row['_hlpb_bundle_key'] ?? '' ) !== (string) $bundle_key || ! empty( $row['_hlpb_is_gift'] ) ) {
					continue;
				}
				$core_rows[ $key ] = $row;
				$quantity = max( 1, absint( $row['quantity'] ?? 1 ) );
				$core_count += $quantity;
				$product_id = absint( $row['product_id'] ?? 0 );
				$product_counts[ $product_id ] = ( $product_counts[ $product_id ] ?? 0 ) + $quantity;
			}
		}
		if ( ! $core_rows ) {
			self::$syncing_cart = true;
			foreach ( $gift_rows as $key => $row ) {
				if ( isset( $cart->cart_contents[ $key ] ) ) {
					$cart->remove_cart_item( $key );
				}
			}
			self::$syncing_cart = false;
			return;
		}
		$quantity = $core_count;
		$counts_valid = true;
		if ( 'bundle' === $mode ) {
			$allowed = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $mode_config['products'] ?? array() ) ) ) ), 0, 2 );
			if ( count( $allowed ) < 2 ) {
				$counts_valid = false;
			} else {
				$first_count = absint( $product_counts[ $allowed[0] ] ?? 0 );
				$second_count = absint( $product_counts[ $allowed[1] ] ?? 0 );
				$quantity = $first_count;
				$counts_valid = $first_count > 0 && $first_count === $second_count && $core_count === ( $quantity * 2 );
			}
		} else {
			$allowed = array_values( array_filter( array_map( 'absint', $mode_config['products'] ?? array() ) ) );
			foreach ( array_keys( $product_counts ) as $product_id ) {
				if ( ! in_array( absint( $product_id ), $allowed, true ) ) {
					$counts_valid = false;
				}
			}
		}
		$rules = $mode_config['rules'] ?? array();
		$rule = $rules[ $quantity ] ?? $rules[ (string) $quantity ] ?? false;
		$minimum_quantity = 'mix' === $mode ? 2 : 1;
		if ( 'mix' === $mode && count( $product_counts ) < 2 ) {
			$counts_valid = false;
		}
		$valid_rule = ! empty( $mode_config['enabled'] ) && $counts_valid && $quantity >= $minimum_quantity && $quantity <= 4 && is_array( $rule );
		$target_gift = $gift_opt_in && $valid_rule && in_array( $rule['gift_mode'] ?? 'none', array( 'free', 'discounted' ), true ) ? absint( $rule['gift_product_id'] ?? 0 ) : 0;
		self::$syncing_cart = true;
		foreach ( $core_rows as $key => $row ) {
			$cart->cart_contents[ $key ]['_hlpb_bundle_quantity'] = $quantity;
			$cart->cart_contents[ $key ]['_hlpb_rule'] = $valid_rule ? $rule : array();
			$cart->cart_contents[ $key ]['_hlpb_gift_opt_in'] = $gift_opt_in;
		}
		$has_eligible_gift = false;
		foreach ( $gift_rows as $key => $row ) {
			$eligible = $target_gift && ! $has_eligible_gift && absint( $row['product_id'] ?? 0 ) === $target_gift && ! $gift_was_removed;
			if ( ! $eligible ) {
				$cart->remove_cart_item( $key );
				continue;
			}
			$cart->cart_contents[ $key ]['_hlpb_bundle_quantity'] = $quantity;
			$cart->cart_contents[ $key ]['_hlpb_rule'] = $valid_rule ? $rule : array();
			$cart->cart_contents[ $key ]['_hlpb_gift_eligible'] = true;
			$cart->cart_contents[ $key ]['quantity'] = 1;
			$has_eligible_gift = true;
		}
		if ( $target_gift && ! $has_eligible_gift && ! $gift_was_removed ) {
			$gift = self::make_cart_gift( $target_gift );
			if ( $gift ) {
				$cart->add_to_cart( $gift['product_id'], 1, $gift['variation_id'], $gift['variation'], array(
					'_hlpb_landing_id' => $landing_id, '_hlpb_coupon_id' => $coupon_id, '_hlpb_campaign_name' => $campaign_name, '_hlpb_bundle_key' => (string) $bundle_key,
					'_hlpb_mode' => $mode, '_hlpb_bundle_quantity' => $quantity, '_hlpb_rule' => $rule,
					'_hlpb_base_price' => $gift['price'], '_hlpb_base_total' => 0,
					'_hlpb_is_addon' => false, '_hlpb_is_gift' => true, '_hlpb_gift_eligible' => true,
				) );
			}
		}
		self::$syncing_cart = false;
	}

	/** Pick one available variation when a quantity edit unlocks a variable gift. */
	private static function make_cart_gift( $product_id ) {
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return false;
		}
		$variation_id = 0;
		$attributes = array();
		$selected = $product;
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( ! $variation || ! $variation->variation_is_visible() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
					continue;
				}
				$variation_id = absint( $child_id );
				$attributes = $variation->get_variation_attributes();
				$selected = $variation;
				break;
			}
			if ( ! $variation_id ) {
				return false;
			}
		}
		return array( 'product_id' => absint( $product_id ), 'variation_id' => $variation_id, 'variation' => $attributes, 'price' => (float) $selected->get_price( 'edit' ) );
	}

	/** Add the offer name and bundle identity to order items for a clear record. */
	public static function save_order_item_data( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['_hlpb_bundle_key'] ) ) {
			return;
		}
		$landing_id = absint( $values['_hlpb_landing_id'] ?? 0 );
		$item->add_meta_data( __( 'Landing offer', 'html-landing-page-builder-for-woocommerce' ), sanitize_text_field( $values['_hlpb_campaign_name'] ?? get_the_title( $landing_id ) ), true );
		$item->add_meta_data( __( 'Offer bundle', 'html-landing-page-builder-for-woocommerce' ), strtoupper( substr( preg_replace( '/[^a-f0-9]/i', '', (string) $values['_hlpb_bundle_key'] ), 0, 8 ) ), true );
		if ( ! empty( $values['_hlpb_is_gift'] ) ) {
			$gift_mode = sanitize_key( $values['_hlpb_rule']['gift_mode'] ?? 'none' );
			$gift_status = empty( $values['_hlpb_gift_eligible'] ) ? __( 'Regular price — gift not included in this quantity', 'html-landing-page-builder-for-woocommerce' ) : ( 'free' === $gift_mode ? __( 'Free gift', 'html-landing-page-builder-for-woocommerce' ) : __( 'Discounted gift', 'html-landing-page-builder-for-woocommerce' ) );
			$item->add_meta_data( __( 'Gift status', 'html-landing-page-builder-for-woocommerce' ), $gift_status, true );
		}
		$item->add_meta_data( '_hlpb_landing_id', $landing_id, true );
		$item->add_meta_data( '_hlpb_bundle_key', sanitize_text_field( $values['_hlpb_bundle_key'] ), true );
	}
}
