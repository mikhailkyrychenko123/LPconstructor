<?php
/**
 * Landing page post type and section editor.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Landing_Builder {
	const POST_TYPE    = 'hlpb_landing';
	const META_KEY     = '_hlpb_sections';
	const TEMPLATE_KEY = '_hlpb_template';
	const EXPORT_KEY   = '_hlpb_export_path';
	const EXPORT_SLUG_KEY = '_hlpb_export_slug';
	const EXPORT_PROMO_KEY = '_hlpb_export_promo_folder';
	const EXPORT_DIRECTORY_KEY = '_hlpb_export_directory';
	const EXPORT_HISTORY_KEY = '_hlpb_export_history';
	const EXPORT_FOLDER_QUERY_VAR = 'hlpb_export_folder';

	/**
	 * Connect the builder to WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_export_route' ), 20 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_sections' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_export_notice' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'select_template' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_export_query_var' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'use_classic_editor' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'serve_static_export' ) );
		add_action( 'wp_ajax_hlpb_render_section', array( __CLASS__, 'ajax_render_section' ) );
		add_filter( 'post_type_link', array( __CLASS__, 'filter_landing_permalink' ), 10, 4 );
		add_action( 'trashed_post', array( __CLASS__, 'delete_export_for_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_export_for_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'restore_export_for_post' ) );
	}

	/**
	 * Keep the builder in the classic editor so Publish and Update save the builder fields.
	 *
	 * @param bool   $use_block_editor Whether the block editor is enabled.
	 * @param string $post_type Current post type.
	 * @return bool
	 */
	public static function use_classic_editor( $use_block_editor, $post_type ) {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	/**
	 * Register the public landing page post type.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Landing Pages', 'html-landing-page-builder-for-woocommerce' ),
			'singular_name'      => __( 'Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'add_new'            => __( 'Add Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'add_new_item'       => __( 'Create Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'edit_item'          => __( 'Edit Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'new_item'           => __( 'New Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'view_item'          => __( 'View Landing Page', 'html-landing-page-builder-for-woocommerce' ),
			'search_items'       => __( 'Search Landing Pages', 'html-landing-page-builder-for-woocommerce' ),
			'not_found'          => __( 'No landing pages found.', 'html-landing-page-builder-for-woocommerce' ),
			'not_found_in_trash' => __( 'No landing pages found in Trash.', 'html-landing-page-builder-for-woocommerce' ),
			'menu_name'          => __( 'Landing Pages', 'html-landing-page-builder-for-woocommerce' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_in_rest'        => true,
				'menu_position'       => 58,
				'menu_icon'           => 'dashicons-layout',
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'landing' ),
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register static export routes for every configured public folder.
	 *
	 * @return void
	 */
	public static function register_export_route() {
		$folders = array( 'promo' => 'promo' );
		$posts   = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post_id ) {
			$folder = self::get_export_promo_folder( $post_id );
			if ( preg_match( '/^[a-z0-9-]+$/i', $folder ) ) {
				$folders[ strtolower( $folder ) ] = $folder;
			}
		}
		foreach ( $folders as $route_folder => $folder ) {
			$regex = '^' . preg_quote( $route_folder, '/' ) . '/([^/]+)/?$';
			$query = 'index.php?hlpb_static_export=$matches[1]&' . self::EXPORT_FOLDER_QUERY_VAR . '=' . rawurlencode( $folder );
			add_rewrite_rule( $regex, $query, 'top' );
		}
	}

	/**
	 * Add the export slug to public query vars.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public static function register_export_query_var( $vars ) {
		$vars[] = 'hlpb_static_export';
		$vars[] = self::EXPORT_FOLDER_QUERY_VAR;
		return $vars;
	}

	/**
	 * Get the supported section types.
	 *
	 * @return array<string, string>
	 */
	public static function get_section_types() {
		return array(
			'header'       => __( 'Header', 'html-landing-page-builder-for-woocommerce' ),
			'offer_line'   => __( 'Offer Line', 'html-landing-page-builder-for-woocommerce' ),
			'cart_section' => __( 'Cart Section', 'html-landing-page-builder-for-woocommerce' ),
			'mix_it'       => __( 'Mix it your way', 'html-landing-page-builder-for-woocommerce' ),
			'text_image'   => __( 'Text + Image', 'html-landing-page-builder-for-woocommerce' ),
			'reviews'      => __( 'Reviews', 'html-landing-page-builder-for-woocommerce' ),
			'faq'          => __( 'FAQ', 'html-landing-page-builder-for-woocommerce' ),
			'why'          => __( 'Why?', 'html-landing-page-builder-for-woocommerce' ),
			'limited_stock'=> __( 'Limited stock', 'html-landing-page-builder-for-woocommerce' ),
			'footer'       => __( 'Footer', 'html-landing-page-builder-for-woocommerce' ),
		);
	}

	/**
	 * Return the section types allowed only once per landing page.
	 *
	 * @return array<int, string>
	 */
	private static function get_singleton_section_types() {
		return array( 'header', 'cart_section', 'footer' );
	}

	/**
	 * Return a small vector wireframe that identifies a section in the editor.
	 *
	 * @param string $type Section type.
	 * @return string
	 */
	private static function get_section_preview_svg( $type ) {
		$artwork = array(
			'header'        => '<rect class="hlpb-preview-bg" x="4" y="9" width="112" height="54" rx="6"/><rect class="hlpb-preview-accent" x="11" y="16" width="22" height="10" rx="2"/><path class="hlpb-preview-line" d="M49 21h12m6 0h12m6 0h9"/><rect class="hlpb-preview-soft" x="11" y="34" width="98" height="21" rx="3"/><path class="hlpb-preview-line" d="M18 42h34m-34 6h23"/>',
			'offer_line'    => '<rect class="hlpb-preview-soft" x="4" y="15" width="112" height="42" rx="6"/><circle class="hlpb-preview-accent" cx="21" cy="36" r="9"/><path class="hlpb-preview-line" d="m21 29 2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path class="hlpb-preview-line" d="M39 31h60m-60 10h45"/>',
			'cart_section'  => '<rect class="hlpb-preview-bg" x="4" y="6" width="112" height="60" rx="5"/><rect class="hlpb-preview-soft" x="9" y="11" width="46" height="50" rx="3"/><circle class="hlpb-preview-accent" cx="32" cy="30" r="10"/><path class="hlpb-preview-line" d="M63 17h38m-38 8h29m-29 9h33"/><rect class="hlpb-preview-soft" x="63" y="42" width="21" height="14" rx="3"/><rect class="hlpb-preview-accent" x="88" y="42" width="20" height="14" rx="3"/>',
			'mix_it'        => '<path class="hlpb-preview-line" d="M42 10h36m-31 5h26"/><rect class="hlpb-preview-soft" x="8" y="23" width="31" height="39" rx="4"/><rect class="hlpb-preview-bg" x="44" y="23" width="31" height="39" rx="4"/><rect class="hlpb-preview-soft" x="80" y="23" width="31" height="39" rx="4"/><circle class="hlpb-preview-accent" cx="23" cy="37" r="6"/><circle class="hlpb-preview-accent" cx="59" cy="37" r="6"/><circle class="hlpb-preview-accent" cx="95" cy="37" r="6"/><path class="hlpb-preview-line" d="M16 50h14m-14 5h9m36-5h14m-14 5h9m27-5h14m-14 5h9"/>',
			'text_image'    => '<rect class="hlpb-preview-soft" x="5" y="8" width="48" height="56" rx="4"/><circle class="hlpb-preview-accent" cx="29" cy="28" r="9"/><path class="hlpb-preview-line" d="m12 55 13-15 8 8 7-9 8 16"/><path class="hlpb-preview-line" d="M62 18h46m-46 8h37m-37 8h42m-42 8h31"/><circle class="hlpb-preview-accent" cx="67" cy="55" r="4"/><circle class="hlpb-preview-accent" cx="83" cy="55" r="4"/><circle class="hlpb-preview-accent" cx="99" cy="55" r="4"/>',
			'reviews'       => '<rect class="hlpb-preview-bg" x="5" y="15" width="32" height="48" rx="4"/><rect class="hlpb-preview-bg" x="44" y="15" width="32" height="48" rx="4"/><rect class="hlpb-preview-bg" x="83" y="15" width="32" height="48" rx="4"/><circle class="hlpb-preview-accent" cx="21" cy="27" r="6"/><circle class="hlpb-preview-accent" cx="60" cy="27" r="6"/><circle class="hlpb-preview-accent" cx="99" cy="27" r="6"/><path class="hlpb-preview-line" d="M13 40h16m-16 6h12m27-6h16m-16 6h12m27-6h16m-16 6h12"/><path class="hlpb-preview-accent-stroke" d="m13 55 2 2 2-2 2 2 2-2 2 2 2-2 2 2 2-2"/>',
			'faq'           => '<path class="hlpb-preview-line" d="M11 17h40m-40 7h31"/><rect class="hlpb-preview-soft" x="61" y="11" width="49" height="12" rx="3"/><rect class="hlpb-preview-bg" x="61" y="28" width="49" height="12" rx="3"/><rect class="hlpb-preview-bg" x="61" y="45" width="49" height="12" rx="3"/><path class="hlpb-preview-line" d="M68 17h27m-27 17h30m-30 17h24"/><path class="hlpb-preview-accent-stroke" d="m102 15 3 2-3 2m0 15 3 2-3 2m0 15 3 2-3 2"/>',
			'why'           => '<path class="hlpb-preview-line" d="M38 11h44m-37 6h30"/><rect class="hlpb-preview-soft" x="10" y="26" width="46" height="16" rx="4"/><rect class="hlpb-preview-bg" x="64" y="26" width="46" height="16" rx="4"/><rect class="hlpb-preview-bg" x="10" y="47" width="46" height="16" rx="4"/><rect class="hlpb-preview-soft" x="64" y="47" width="46" height="16" rx="4"/><circle class="hlpb-preview-accent" cx="21" cy="34" r="4"/><circle class="hlpb-preview-accent" cx="75" cy="34" r="4"/><circle class="hlpb-preview-accent" cx="21" cy="55" r="4"/><circle class="hlpb-preview-accent" cx="75" cy="55" r="4"/><path class="hlpb-preview-line" d="M29 33h19m-19 4h14m34-4h19m-19 4h14m-61 17h19m-19 4h14m34-4h19m-19 4h14"/>',
			'limited_stock' => '<rect class="hlpb-preview-soft" x="5" y="16" width="110" height="41" rx="6"/><path class="hlpb-preview-accent-stroke" d="M25 25c-7 7-2 14 3 15-1-5 4-7 3-13 5 5 8 10 5 16-4 8-17 6-18-2-1-5 2-11 7-16z"/><path class="hlpb-preview-line" d="M48 29h53m-53 9h43"/><rect class="hlpb-preview-accent" x="48" y="44" width="33" height="6" rx="3"/>',
			'footer'        => '<rect class="hlpb-preview-dark" x="4" y="9" width="112" height="54" rx="5"/><rect class="hlpb-preview-accent" x="11" y="17" width="21" height="9" rx="2"/><path class="hlpb-preview-footer-line" d="M43 19h17m-17 6h13m17-6h17m-17 6h13m17-6h10m-10 6h8M11 36h98M11 44h24m7 0h24m7 0h24m7 0h15M11 55h52"/>',
		);

		if ( ! isset( $artwork[ $type ] ) ) {
			return '';
		}

		return '<svg class="hlpb-section-preview-svg" viewBox="0 0 120 72" width="120" height="72" aria-hidden="true" focusable="false">' . $artwork[ $type ] . '</svg>';
	}

	/**
	 * Return the previews used by the section type selector.
	 *
	 * @return array<string, string>
	 */
	private static function get_section_previews() {
		$previews = array();
		foreach ( self::get_section_types() as $type => $label ) {
			$previews[ $type ] = self::get_section_preview_svg( $type );
		}
		return $previews;
	}

	/**
	 * Discover templates from templates/{slug}/template.json.
	 *
	 * Each template keeps its source markup, assets, image library, and partials together.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_templates() {
		$templates = array();
		$files     = glob( HLPB_PATH . 'templates/*/template.json' );
		if ( ! is_array( $files ) ) {
			return $templates;
		}

		foreach ( $files as $manifest_path ) {
			$slug     = sanitize_key( basename( dirname( $manifest_path ) ) );
			$manifest = json_decode( file_get_contents( $manifest_path ), true );
			if ( ! $slug || ! is_array( $manifest ) || empty( $manifest['source'] ) || empty( $manifest['styles'] ) || empty( $manifest['assets'] ) || empty( $manifest['sections'] ) ) {
				continue;
			}
			$directory = dirname( $manifest_path );
			$source    = self::resolve_template_file( $slug, $manifest['source'] );
			$styles    = self::resolve_template_file( $slug, $manifest['styles'] );
			$assets    = self::resolve_template_file( $slug, $manifest['assets'] );
			if ( ! $source || ! is_file( $source ) || ! $styles || ! is_file( $styles ) || ! $assets || ! is_dir( $assets ) || false === self::path_from_asset_root( $manifest, $manifest['styles'] ) ) {
				continue;
			}
			$manifest['slug']      = $slug;
			$manifest['directory'] = $directory;
			$manifest['name']      = isset( $manifest['name'] ) ? sanitize_text_field( $manifest['name'] ) : $slug;
			$manifest['source_path'] = $source;
			$manifest['styles_path'] = $styles;
			$manifest['assets_path'] = $assets;
			$templates[ $slug ]     = $manifest;
		}

		return $templates;
	}

	/**
	 * Return template metadata for one registered template.
	 *
	 * @param string $slug Template slug.
	 * @return array<string, mixed>|false
	 */
	private static function get_template_info( $slug ) {
		$templates = self::get_templates();
		$slug      = sanitize_key( $slug );
		return isset( $templates[ $slug ] ) ? $templates[ $slug ] : false;
	}

	/**
	 * Return the public URL for a template's asset directory.
	 *
	 * @param string $slug Template slug.
	 * @return string
	 */
	public static function get_template_asset_url( $slug ) {
		$template = self::get_template_info( $slug );
		if ( ! $template || empty( $template['assets'] ) ) {
			return '';
		}
		return HLPB_URL . 'templates/' . sanitize_key( $slug ) . '/' . trailingslashit( ltrim( $template['assets'], '/' ) );
	}

	/**
	 * Select the saved template for a landing page.
	 *
	 * @param int $post_id Landing page ID.
	 * @return string
	 */
	public static function get_template_slug( $post_id ) {
		$templates = self::get_templates();
		$slug      = sanitize_key( get_post_meta( $post_id, self::TEMPLATE_KEY, true ) );
		if ( isset( $templates[ $slug ] ) ) {
			return $slug;
		}
		return isset( $templates['cozy-mix'] ) ? 'cozy-mix' : ( $templates ? key( $templates ) : '' );
	}

	/**
	 * Resolve a path inside a template folder without allowing traversal.
	 *
	 * @param string $slug Template slug.
	 * @param string $relative Relative path from the manifest.
	 * @return string|false
	 */
	private static function resolve_template_file( $slug, $relative ) {
		$relative = str_replace( '\\', '/', ltrim( (string) $relative, '/' ) );
		if ( '' === $relative || false !== strpos( $relative, '../' ) || false !== strpos( $relative, '..\\' ) ) {
			return false;
		}
		$base = wp_normalize_path( HLPB_PATH . 'templates/' . sanitize_key( $slug ) . '/' );
		$path = wp_normalize_path( $base . $relative );
		return 0 === strpos( $path, $base ) ? $path : false;
	}

	/**
	 * Demo section content used for new pages and newly added Text + Image sections.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_demo_text_image( $variant = 0, $template_slug = 'cozy-mix' ) {
		$examples = array(
			array(
				'subtitle'       => 'FEELS LIKE A HUG',
				'title'          => "Sumptuously soft,\ninside and out",
				'body'           => 'Our oversized hoodie and weighted blanket are crafted from ultra-soft, high-quality fabrics that keep you warm, cosy and comfortable throughout the day and night.',
				'demo_image'     => 'texture-closeup-480.webp',
				'image_alt'      => 'A hand resting on plush ivory sherpa and burgundy fleece',
				'image_position' => 'right',
				'color'          => '#29445c',
				'features'       => array(
					array( 'icon' => 'leaf', 'text' => "Ultra-soft\nsherpa lining" ),
					array( 'icon' => 'waves', 'text' => "Warm without\nthe weight" ),
					array( 'icon' => 'diamond', 'text' => "Durable for\neveryday cosiness" ),
				),
			),
			array(
				'subtitle'       => 'SLOWER DAYS, BRIGHTER EVENINGS',
				'title'          => "Made for\nreal life moments",
				'body'           => 'Whether it’s a movie night, a lazy Sunday or a quiet morning with a coffee, our bundle brings extra warmth to the moments that matter most.',
				'demo_image'     => 'family-evening-480.webp',
				'image_alt'      => 'Family and their dog relaxing together under cosy blankets',
				'image_position' => 'left',
				'color'          => '#29445c',
				'features'       => array(
					array( 'icon' => 'sofa', 'text' => "Perfect for\nfamily time" ),
					array( 'icon' => 'moon', 'text' => "Helps you\nrelax and unwind" ),
					array( 'icon' => 'heart', 'text' => "Everyday comfort\nfor a happier you" ),
				),
			),
			array(
				'subtitle'       => 'A THOUGHTFUL GIFT',
				'title'          => "Cosy comfort\nfor the people you love",
				'body'           => 'Beautifully packaged and ready to give, the Nest & Nook bundle makes the perfect gift for birthdays, anniversaries or just because.',
				'demo_image'     => 'gift-box-480.webp',
				'image_alt'      => 'Nest & Nook gift box tied with a satin bow on cosy blankets',
				'image_position' => 'right',
				'color'          => '#29445c',
				'features'       => array(
					array( 'icon' => 'gift', 'text' => "Premium\ngift box" ),
					array( 'icon' => 'heart', 'text' => "A meaningful\ngift they’ll love" ),
					array( 'icon' => 'gift', 'text' => "Personalised\ngift note available" ),
				),
			),
		);

		$template = self::get_template_info( $template_slug );
		if ( $template && ! empty( $template['demo_text_image'] ) && is_array( $template['demo_text_image'] ) ) {
			$template_examples = array_values( array_filter( $template['demo_text_image'], 'is_array' ) );
			if ( $template_examples ) {
				$examples = array_map(
					static function ( $example, $index ) use ( $examples ) {
						return array_merge( $examples[ $index % count( $examples ) ], $example );
					},
					$template_examples,
					array_keys( $template_examples )
				);
			}
		}
		$variant = absint( $variant ) % count( $examples );
		return $examples[ $variant ];
	}

	/**
	 * Default page layout follows the supplied Nest & Nook design.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_default_sections( $template_slug = 'cozy-mix' ) {
		$sections = array();
		$default_items = array(
			'header',
			'offer_line',
			'cart_section',
			'mix_it',
			'text_image',
			'text_image',
			'text_image',
			'reviews',
			'faq',
			'why',
			'limited_stock',
			'footer',
		);
		$template = self::get_template_info( $template_slug );
		$items    = $template && ! empty( $template['default_layout'] ) && is_array( $template['default_layout'] ) ? $template['default_layout'] : $default_items;
		$items    = array_values( array_filter( $items, static function ( $type ) { return isset( self::get_section_types()[ sanitize_key( $type ) ] ); } ) );

		$singletons_seen = array();
		foreach ( $items as $type ) {
			$type = sanitize_key( $type );
			if ( in_array( $type, self::get_singleton_section_types(), true ) ) {
				if ( isset( $singletons_seen[ $type ] ) ) {
					continue;
				}
				$singletons_seen[ $type ] = true;
			}
			$index   = count( $sections );
			$section = array(
				'id'    => 'section_' . ( $index + 1 ),
				'type'  => $type,
				'order' => $index + 1,
			);

			if ( 'text_image' === $type ) {
				$section = array_merge( $section, self::get_demo_text_image( count( array_filter( $sections, static function ( $item ) { return 'text_image' === $item['type']; } ) ), $template_slug ) );
			} elseif ( 'header' === $type ) {
				$section = array_merge( $section, self::get_default_header_settings() );
			} elseif ( 'offer_line' === $type ) {
				$section = array_merge( $section, self::get_default_offer_settings() );
			} elseif ( 'mix_it' === $type ) {
				$section = array_merge( $section, self::get_default_mix_settings() );
			} elseif ( 'reviews' === $type ) {
				$section = array_merge( $section, self::get_default_reviews_settings() );
			} elseif ( 'faq' === $type ) {
				$section = array_merge( $section, self::get_default_faq_settings() );
			} elseif ( 'footer' === $type ) {
				$section = array_merge( $section, self::get_default_footer_settings() );
			}

			$sections[] = $section;
		}

		return $sections;
	}

	/**
	 * Default editable header values matching the supplied design.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_header_settings() {
		return array(
			'logo_id' => 0,
			'left_menu_mode' => 'custom',
			'left_menu_id' => 0,
			'left_menu_items' => array( array( 'label' => __( 'Home', 'html-landing-page-builder-for-woocommerce' ), 'url' => home_url( '/' ) ) ),
			'right_menu_mode' => 'custom',
			'right_menu_id' => 0,
			'right_menu_items' => array( array( 'label' => __( 'Shop', 'html-landing-page-builder-for-woocommerce' ), 'url' => home_url( '/shop/' ) ) ),
		);
	}

	/**
	 * Default editable announcement values matching the supplied design.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_offer_settings() {
		return array(
			'left_text' => __( 'Cozy Season Offer', 'html-landing-page-builder-for-woocommerce' ),
			'left_bold' => 1,
			'left_color' => '#376c5c',
			'right_text' => __( 'Buy more, save more · Free delivery on all orders', 'html-landing-page-builder-for-woocommerce' ),
			'right_bold' => 0,
			'right_color' => '#376c5c',
			'background' => '#dff1e8',
		);
	}

	/**
	 * Default editable benefit band values matching the supplied design.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_mix_settings() {
		return array(
			'title' => __( 'Mix it your way', 'html-landing-page-builder-for-woocommerce' ),
			'subtitle' => __( 'Hoodies and blankets in beautiful colours, made for real life.', 'html-landing-page-builder-for-woocommerce' ),
			'background' => '#eef8f3',
			'title_color' => '#082b49',
			'subtitle_color' => '#3b5264',
			'benefit_title_color' => '#082b49',
			'benefit_text_color' => '#4b5d6c',
			'benefits' => array(
				array( 'icon' => 'palette', 'icon_color' => '#244c59', 'title' => __( 'Any colours', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( "Mix and match hoodies\nand blankets.", 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'icon' => 'heart', 'icon_color' => '#ec8180', 'title' => __( 'For everyone', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( "Cosy combinations\nfor the whole family.", 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'icon' => 'people', 'icon_color' => '#273f5a', 'title' => __( 'Create your set', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( "Build the perfect mix\nfor your home.", 'html-landing-page-builder-for-woocommerce' ) ),
			),
		);
	}

	/**
	 * Default editable reviews matching the supplied design.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_reviews_settings() {
		return array(
			'title' => __( 'Over 200,000+ Happy Customers', 'html-landing-page-builder-for-woocommerce' ),
			'subtitle' => __( 'Real people. Cosy homes. Brighter days.', 'html-landing-page-builder-for-woocommerce' ),
			'overall_stars' => 5,
			'overall_score' => '4.9 out of 5',
			'overall_count' => __( 'Based on 2,418 reviews', 'html-landing-page-builder-for-woocommerce' ),
			'background' => '#ffffff',
			'heading_color' => '#082b49',
			'subtitle_color' => '#5b6c79',
			'star_color' => '#f1aa20',
			'score_color' => '#122b42',
			'count_color' => '#71808b',
			'card_background' => '#ffffff',
			'card_border_color' => '#e0e5e5',
			'review_title_color' => '#122b42',
			'review_text_color' => '#546472',
			'reviewer_color' => '#122b42',
			'verified_color' => '#33a67d',
			'reviews' => array(
				array( 'demo_image' => 'thumb-hero-family.webp', 'image_id' => 0, 'image_alt' => __( 'Mother and daughter in cosy hoodie blankets', 'html-landing-page-builder-for-woocommerce' ), 'stars' => 5, 'title' => __( '“Perfect for cosy nights!”', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( 'Me and my daughter both have a hoodie and we absolutely love them. So soft and warm — we live in them!', 'html-landing-page-builder-for-woocommerce' ), 'reviewer' => 'Sophie L.', 'verified' => 1, 'verified_text' => __( '✓ Verified buyer', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'demo_image' => 'thumb-family-evening.webp', 'image_id' => 0, 'image_alt' => __( 'Family enjoying a cosy evening together', 'html-landing-page-builder-for-woocommerce' ), 'stars' => 5, 'title' => __( '“Amazing quality”', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( 'I ordered a blanket and a hoodie and the quality is unreal. Super soft, washes really well, and delivered quickly. Highly recommend!', 'html-landing-page-builder-for-woocommerce' ), 'reviewer' => 'James T.', 'verified' => 1, 'verified_text' => __( '✓ Verified buyer', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'demo_image' => 'thumb-family-evening.webp', 'image_id' => 0, 'image_alt' => __( 'Cosy blankets shared by a family', 'html-landing-page-builder-for-woocommerce' ), 'stars' => 5, 'title' => __( '“The best gift!”', 'html-landing-page-builder-for-woocommerce' ), 'text' => __( 'Bought a mix for my family and they all love them. Such a great idea and really good value when you buy more.', 'html-landing-page-builder-for-woocommerce' ), 'reviewer' => 'Emily R.', 'verified' => 1, 'verified_text' => __( '✓ Verified buyer', 'html-landing-page-builder-for-woocommerce' ) ),
			),
		);
	}

	/**
	 * Default editable FAQ content matching the supplied design.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_faq_settings() {
		return array(
			'title' => __( "Frequently\nAsked Questions", 'html-landing-page-builder-for-woocommerce' ),
			'subtitle' => __( 'Everything you need to know about your cosy order.', 'html-landing-page-builder-for-woocommerce' ),
			'background' => '#ffffff',
			'heading_color' => '#082b49',
			'intro_color' => '#63717b',
			'question_color' => '#122b42',
			'answer_color' => '#63717b',
			'item_background' => '#ffffff',
			'item_border_color' => '#e0e5e5',
			'arrow_color' => '#6e7d86',
			'faqs' => array(
				array( 'question' => __( 'How long does delivery take?', 'html-landing-page-builder-for-woocommerce' ), 'answer' => __( 'UK delivery usually takes 2–4 working days in this demo. Delivery estimates should be updated to match your store’s actual fulfilment terms.', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'question' => __( 'Can I mix hoodies and blankets?', 'html-landing-page-builder-for-woocommerce' ), 'answer' => __( 'Yes. Choose Mix & Match to combine hoodie blankets and weighted blankets in the colours you like.', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'question' => __( 'What if I’m not happy with my order?', 'html-landing-page-builder-for-woocommerce' ), 'answer' => __( 'We want you to feel cosy and confident. Add your real returns and exchange policy here before launch.', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'question' => __( 'How do I care for my hoodie and blanket?', 'html-landing-page-builder-for-woocommerce' ), 'answer' => __( 'Follow the care label on each product. Use your product’s verified washing instructions here.', 'html-landing-page-builder-for-woocommerce' ) ),
				array( 'question' => __( 'Do you offer gift wrapping?', 'html-landing-page-builder-for-woocommerce' ), 'answer' => __( 'Gift wrapping and a personalised gift note can be selected with eligible bundles.', 'html-landing-page-builder-for-woocommerce' ) ),
			),
		);
	}

	/** Default editable footer values matching the supplied design. */
	private static function get_default_footer_settings() {
		return array(
			'logo_id' => 0,
			'brand_name' => 'NEST & NOOK',
			'brand_tagline' => 'COMFORT GOODS',
			'brand_url' => '#top',
			'primary_links' => array(
				array( 'label' => 'Shop', 'url' => '#shop' ),
				array( 'label' => 'Our Story', 'url' => '#story' ),
				array( 'label' => 'FAQs', 'url' => '#faqs' ),
				array( 'label' => 'Contact', 'url' => '#contact' ),
				array( 'label' => 'Reviews', 'url' => '#reviews' ),
			),
			'social_links' => array(
				array( 'icon' => 'instagram', 'label' => 'Instagram', 'url' => '#instagram' ),
				array( 'icon' => 'facebook', 'label' => 'Facebook', 'url' => '#facebook' ),
				array( 'icon' => 'pinterest', 'label' => 'Pinterest', 'url' => '#pinterest' ),
				array( 'icon' => 'tiktok', 'label' => 'TikTok', 'url' => '#tiktok' ),
				array( 'icon' => 'youtube', 'label' => 'YouTube', 'url' => '#youtube' ),
			),
			'copyright' => '© 2024 Nest & Nook. All rights reserved.',
			'legal_links' => array(
				array( 'label' => 'Privacy Policy', 'url' => '#privacy' ),
				array( 'label' => 'Terms of Service', 'url' => '#terms' ),
				array( 'label' => 'Returns', 'url' => '#returns' ),
			),
			'legal_note' => '🇬🇧 UK Owned & Operated',
			'disclaimer' => 'Demo recreation. Review totals, customer quotes, prices, delivery, and stock wording are illustrative and should be replaced with verified store information.',
			'background' => '#082b49',
			'text_color' => '#e9f0f2',
			'brand_color' => '#ffffff',
			'bottom_text_color' => '#c7d5db',
			'disclaimer_color' => '#afc2ca',
			'divider_color' => '#38566b',
		);
	}

	/** Footer social networks supported by the template icon sprite. */
	private static function get_footer_social_icons() {
		return array(
			'instagram' => __( 'Instagram', 'html-landing-page-builder-for-woocommerce' ),
			'facebook' => __( 'Facebook', 'html-landing-page-builder-for-woocommerce' ),
			'pinterest' => __( 'Pinterest', 'html-landing-page-builder-for-woocommerce' ),
			'tiktok' => __( 'TikTok', 'html-landing-page-builder-for-woocommerce' ),
			'youtube' => __( 'YouTube', 'html-landing-page-builder-for-woocommerce' ),
		);
	}

	/**
	 * Render the section editor box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function add_meta_box( $post ) {
		add_meta_box(
			'hlpb-landing-sections',
			__( 'Landing Page Sections', 'html-landing-page-builder-for-woocommerce' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the section editor.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hlpb_save_sections', 'hlpb_sections_nonce' );
		$templates         = self::get_templates();
		$selected_template = self::get_template_slug( $post->ID );
		$sections = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! metadata_exists( 'post', $post->ID, self::META_KEY ) || ! is_array( $sections ) ) {
			$sections = self::get_default_sections( $selected_template );
		}
		$types             = self::get_section_types();
		$post_slug         = get_post_field( 'post_name', $post->ID );
		$post_slug         = $post_slug && 'auto-draft' !== $post_slug ? $post_slug : sanitize_title( $post->post_title );
		$export_slug       = self::get_export_slug( $post->ID );
		$export_slug       = $export_slug ? $export_slug : $post_slug;
		$promo_folder      = self::get_export_promo_folder( $post->ID );
		$export_url        = $export_slug ? self::build_export_url( $promo_folder, $export_slug ) : '';
		$export_path       = get_post_meta( $post->ID, self::EXPORT_KEY, true );
		$last_export_dir   = self::get_current_export_relative_path( $post->ID );
		?>
		<div class="hlpb-builder">
			<p class="hlpb-builder-intro"><?php esc_html_e( 'Drag sections to change their order, or enter an order number. New sections start with demo content.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
			<div class="hlpb-template-panel">
				<label for="hlpb-template-select"><strong><?php esc_html_e( 'Landing page template', 'html-landing-page-builder-for-woocommerce' ); ?></strong></label>
				<select id="hlpb-template-select" name="hlpb_template">
					<?php foreach ( $templates as $template_slug => $template ) : ?>
						<option value="<?php echo esc_attr( $template_slug ); ?>" data-image-base="<?php echo esc_url( self::get_template_asset_url( $template_slug ) . 'images/' ); ?>" data-default-image="<?php echo esc_attr( isset( $template['default_image'] ) ? sanitize_file_name( $template['default_image'] ) : '' ); ?>" <?php selected( $selected_template, $template_slug ); ?>><?php echo esc_html( $template['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $selected_template && isset( $templates[ $selected_template ]['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $templates[ $selected_template ]['description'] ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Templates live in their own folder with a manifest, source HTML, CSS, JavaScript, images, icons, and section partials.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
				<p><label for="hlpb-export-promo-folder"><strong><?php esc_html_e( 'Promo folder', 'html-landing-page-builder-for-woocommerce' ); ?></strong></label><br><input type="text" class="regular-text" id="hlpb-export-promo-folder" name="hlpb_export_promo_folder" value="<?php echo esc_attr( $promo_folder ); ?>" pattern="[a-zA-Z0-9-]+" placeholder="Promo"><br><span class="description"><?php esc_html_e( 'Top-level folder for published landing pages. Use letters, numbers, and hyphens.', 'html-landing-page-builder-for-woocommerce' ); ?></span></p>
				<p><label for="hlpb-export-slug"><strong><?php esc_html_e( 'Landing folder', 'html-landing-page-builder-for-woocommerce' ); ?></strong></label><br><input type="text" class="regular-text" id="hlpb-export-slug" name="hlpb_export_slug" value="<?php echo esc_attr( $export_slug ); ?>" pattern="[a-zA-Z0-9-]+" placeholder="Christmas-Event"><br><span class="description"><?php esc_html_e( 'Folder for this landing page. Use letters, numbers, and hyphens.', 'html-landing-page-builder-for-woocommerce' ); ?></span></p>
				<p><strong><?php esc_html_e( 'Next Publish/Update creates', 'html-landing-page-builder-for-woocommerce' ); ?>:</strong> <?php esc_html_e( 'WordPress root', 'html-landing-page-builder-for-woocommerce' ); ?>/<code><?php echo esc_html( $promo_folder ); ?>/<?php echo esc_html( $export_slug ? $export_slug : '{landing-folder}' ); ?>/index.html</code> · <code><?php echo esc_html( $export_url ? wp_parse_url( $export_url, PHP_URL_PATH ) : '/promo/{landing-folder}/' ); ?></code></p>
				<?php if ( $export_path && $last_export_dir ) : ?>
					<p><strong><?php esc_html_e( 'Last published static copy', 'html-landing-page-builder-for-woocommerce' ); ?>:</strong> <?php esc_html_e( 'WordPress root', 'html-landing-page-builder-for-woocommerce' ); ?>/<code><?php echo esc_html( $last_export_dir ); ?>/index.html</code> · <a href="<?php echo esc_url( $export_path ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open published landing page', 'html-landing-page-builder-for-woocommerce' ); ?></a></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Publish or Update automatically creates or refreshes the static page. Changing either folder creates a new copy and leaves the previous folder in place. Moving this landing page to Trash removes folders generated for it.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
			</div>
			<div class="hlpb-toolbar">
				<label for="hlpb-new-section-type"><?php esc_html_e( 'Add section', 'html-landing-page-builder-for-woocommerce' ); ?></label>
				<select id="hlpb-new-section-type">
					<?php $first_type = key( $types ); ?>
					<?php foreach ( $types as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $first_type, $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="hlpb-section-type-preview" aria-live="polite">
					<span class="hlpb-section-type-preview-art"><?php echo self::get_section_preview_svg( $first_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from the section preview map. ?></span>
					<strong class="hlpb-section-type-preview-label"><?php echo esc_html( $types[ $first_type ] ); ?></strong>
				</div>
				<button type="button" class="button" id="hlpb-add-section"><?php esc_html_e( 'Add', 'html-landing-page-builder-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="hlpb-collapse-all"><?php esc_html_e( 'Collapse all', 'html-landing-page-builder-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="hlpb-expand-all"><?php esc_html_e( 'Expand all', 'html-landing-page-builder-for-woocommerce' ); ?></button>
				<span class="hlpb-toolbar-status" aria-live="polite"></span>
			</div>
			<div id="hlpb-section-list" class="hlpb-section-list">
				<?php foreach ( $sections as $section ) : ?>
					<?php echo self::render_section_card( $section, $selected_template ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated from escaped form controls. ?>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Header, Offer Line, Mix it your way, Text + Image, Reviews, FAQ, and Footer sections can be edited here. Other sections use their template demo content.', 'html-landing-page-builder-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Build a section editor card.
	 *
	 * @param array<string, mixed> $section Section data.
	 * @return string
	 */
	public static function render_section_card( $section, $template_slug = 'cozy-mix' ) {
		$types = self::get_section_types();
		$type  = isset( $section['type'] ) && isset( $types[ $section['type'] ] ) ? $section['type'] : 'text_image';
		$id    = isset( $section['id'] ) ? sanitize_key( $section['id'] ) : wp_generate_password( 10, false, false );
		$order = isset( $section['order'] ) ? absint( $section['order'] ) : 1;
		$label = $types[ $type ];
		$html  = '<article class="hlpb-section-card is-collapsed" data-section-id="' . esc_attr( $id ) . '" data-section-type="' . esc_attr( $type ) . '">';
		$duplicate_disabled = in_array( $type, self::get_singleton_section_types(), true );
		$duplicate_title    = $duplicate_disabled ? __( 'This section can only appear once.', 'html-landing-page-builder-for-woocommerce' ) : __( 'Duplicate section', 'html-landing-page-builder-for-woocommerce' );
		$html .= '<div class="hlpb-section-heading"><span class="hlpb-drag-handle" aria-hidden="true">⠿</span><span class="hlpb-section-heading-preview" title="' . esc_attr( $label ) . '">' . self::get_section_preview_svg( $type ) . '</span><span class="hlpb-section-heading-label"><small>' . esc_html__( 'Section type', 'html-landing-page-builder-for-woocommerce' ) . '</small><strong class="hlpb-section-title">' . esc_html( $label ) . '</strong></span><label class="hlpb-order-label">' . esc_html__( 'Order', 'html-landing-page-builder-for-woocommerce' ) . ' <input class="hlpb-order" type="number" min="1" step="1" name="hlpb_sections[' . esc_attr( $id ) . '][order]" value="' . esc_attr( $order ) . '"></label><button type="button" class="button hlpb-toggle-section" aria-expanded="false" aria-controls="hlpb-content-' . esc_attr( $id ) . '">' . esc_html__( 'Expand', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button hlpb-clone-section" aria-label="' . esc_attr( $duplicate_title ) . '" title="' . esc_attr( $duplicate_title ) . '" ' . disabled( $duplicate_disabled, true, false ) . '>' . esc_html__( 'Duplicate', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button-link-delete hlpb-remove-section">' . esc_html__( 'Remove', 'html-landing-page-builder-for-woocommerce' ) . '</button></div>';
		$html .= '<div class="hlpb-section-content" id="hlpb-content-' . esc_attr( $id ) . '"><input type="hidden" name="hlpb_sections[' . esc_attr( $id ) . '][id]" value="' . esc_attr( $id ) . '"><input type="hidden" name="hlpb_sections[' . esc_attr( $id ) . '][type]" value="' . esc_attr( $type ) . '">';

		if ( 'text_image' === $type ) {
			$html .= self::render_text_image_fields( $section, $id, $template_slug );
		} elseif ( 'cart_section' === $type ) {
			$html .= HLPB_Cart_Section::render_editor_fields( $section, $id );
		} elseif ( 'header' === $type ) {
			$html .= self::render_header_fields( $section, $id, $template_slug );
		} elseif ( 'offer_line' === $type ) {
			$html .= self::render_offer_fields( $section, $id );
		} elseif ( 'mix_it' === $type ) {
			$html .= self::render_mix_fields( $section, $id );
		} elseif ( 'reviews' === $type ) {
			$html .= self::render_reviews_fields( $section, $id, $template_slug );
		} elseif ( 'faq' === $type ) {
			$html .= self::render_faq_fields( $section, $id );
		} elseif ( 'footer' === $type ) {
			$html .= self::render_footer_fields( $section, $id, $template_slug );
		} else {
			$html .= '<p class="hlpb-section-demo">' . esc_html__( 'This section displays the matching demo content from the supplied landing page.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		}

		$html .= '</div></article>';
		return $html;
	}

	/** Render editable Header controls. */
	private static function render_header_fields( $section, $id, $template_slug ) {
		$section = array_merge( self::get_default_header_settings(), $section );
		$logo_id = absint( $section['logo_id'] );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : false;
		$logo_url = $logo_url ? $logo_url : self::get_template_asset_url( $template_slug ) . 'icons/nest-stack.svg';
		$name = 'hlpb_sections[' . $id . ']';
		$html = '<div class="hlpb-header-fields"><div class="hlpb-field"><label>' . esc_html__( 'Logo', 'html-landing-page-builder-for-woocommerce' ) . '</label><div class="hlpb-image-control"><img class="hlpb-logo-preview" src="' . esc_url( $logo_url ) . '" alt=""><input class="hlpb-logo-id" type="hidden" name="' . esc_attr( $name . '[logo_id]' ) . '" value="' . esc_attr( $logo_id ) . '"><button type="button" class="button hlpb-select-image" data-image-target="logo">' . esc_html__( 'Choose logo', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button-link hlpb-clear-logo">' . esc_html__( 'Use template logo', 'html-landing-page-builder-for-woocommerce' ) . '</button></div><p class="description">' . esc_html__( 'Without a selected image, the logo and wordmark from the template are shown.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div>';
		$html .= self::render_menu_editor( $id, 'left', __( 'Left menu', 'html-landing-page-builder-for-woocommerce' ), $section );
		$html .= self::render_menu_editor( $id, 'right', __( 'Right menu', 'html-landing-page-builder-for-woocommerce' ), $section );
		$html .= '</div>';
		return $html;
	}

	/** Render a custom links or WordPress menu control group. */
	private static function render_menu_editor( $section_id, $side, $label, $section ) {
		$mode = 'wp_menu' === $section[ $side . '_menu_mode' ] ? 'wp_menu' : 'custom';
		$menus = wp_get_nav_menus();
		$menu_id = absint( $section[ $side . '_menu_id' ] );
		$items = is_array( $section[ $side . '_menu_items' ] ) ? array_slice( $section[ $side . '_menu_items' ], 0, 20 ) : array();
		$name = 'hlpb_sections[' . $section_id . ']';
		$html = '<fieldset class="hlpb-menu-editor" data-menu-side="' . esc_attr( $side ) . '"><legend>' . esc_html( $label ) . '</legend><div class="hlpb-field"><label>' . esc_html__( 'Menu source', 'html-landing-page-builder-for-woocommerce' ) . '</label><select class="hlpb-menu-mode" name="' . esc_attr( $name . '[' . $side . '_menu_mode]' ) . '"><option value="custom" ' . selected( $mode, 'custom', false ) . '>' . esc_html__( 'Custom links', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="wp_menu" ' . selected( $mode, 'wp_menu', false ) . '>' . esc_html__( 'WordPress menu', 'html-landing-page-builder-for-woocommerce' ) . '</option></select></div><div class="hlpb-menu-custom" ' . ( 'custom' !== $mode ? 'hidden' : '' ) . '><div class="hlpb-menu-item-list">';
		foreach ( $items as $index => $item ) {
			$html .= self::render_menu_item_row( $section_id, $side, $index, $item );
		}
		$html .= '</div><button type="button" class="button hlpb-add-menu-item" data-menu-side="' . esc_attr( $side ) . '">' . esc_html__( 'Add menu item', 'html-landing-page-builder-for-woocommerce' ) . '</button></div><div class="hlpb-menu-wordpress" ' . ( 'wp_menu' !== $mode ? 'hidden' : '' ) . '><label>' . esc_html__( 'Choose menu', 'html-landing-page-builder-for-woocommerce' ) . ' <select name="' . esc_attr( $name . '[' . $side . '_menu_id]' ) . '"><option value="0">' . esc_html__( 'Select a menu', 'html-landing-page-builder-for-woocommerce' ) . '</option>';
		foreach ( $menus as $menu ) {
			$html .= '<option value="' . esc_attr( $menu->term_id ) . '" ' . selected( $menu_id, $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
		}
		$html .= '</select></label>' . ( empty( $menus ) ? '<p class="description">' . esc_html__( 'No WordPress menus exist yet. Create one in Appearance → Menus.', 'html-landing-page-builder-for-woocommerce' ) . '</p>' : '' ) . '</div></fieldset>';
		return $html;
	}

	/** Render one custom menu link. */
	private static function render_menu_item_row( $section_id, $side, $index, $item ) {
		$name = 'hlpb_sections[' . $section_id . '][' . $side . '_menu_items][' . absint( $index ) . ']';
		$label = isset( $item['label'] ) ? $item['label'] : '';
		$url = isset( $item['url'] ) ? $item['url'] : '';
		return '<div class="hlpb-menu-item-row"><label><span>' . esc_html__( 'Label', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( $label ) . '"></label><label><span>' . esc_html__( 'Link URL', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" inputmode="url" name="' . esc_attr( $name . '[url]' ) . '" value="' . esc_attr( $url ) . '"></label><button type="button" class="button-link-delete hlpb-remove-menu-item" aria-label="' . esc_attr__( 'Remove menu item', 'html-landing-page-builder-for-woocommerce' ) . '">×</button></div>';
	}

	/** Render editable Offer Line controls. */
	private static function render_offer_fields( $section, $id ) {
		$section = array_merge( self::get_default_offer_settings(), $section );
		if ( 'Buy more, save more · Free UK delivery on all orders' === $section['right_text'] ) {
			$section['right_text'] = self::get_default_offer_settings()['right_text'];
		}
		$name = 'hlpb_sections[' . $id . ']';
		$html = '<div class="hlpb-fields-grid">';
		foreach ( array( 'left' => __( 'Left text', 'html-landing-page-builder-for-woocommerce' ), 'right' => __( 'Right text', 'html-landing-page-builder-for-woocommerce' ) ) as $side => $label ) {
			$html .= '<div class="hlpb-offer-side"><h4>' . esc_html( $label ) . '</h4>' . self::text_field( $name . '[' . $side . '_text]', __( 'Text (emoji supported)', 'html-landing-page-builder-for-woocommerce' ), $section[ $side . '_text' ] ) . '<label class="hlpb-checkbox"><input type="checkbox" name="' . esc_attr( $name . '[' . $side . '_bold]' ) . '" value="1" ' . checked( ! empty( $section[ $side . '_bold' ] ), true, false ) . '> ' . esc_html__( 'Bold text', 'html-landing-page-builder-for-woocommerce' ) . '</label>' . self::render_color_control( $name . '[' . $side . '_color]', __( 'Text color', 'html-landing-page-builder-for-woocommerce' ), $section[ $side . '_color' ], $id . '-' . $side ) . '</div>';
		}
		$html .= '</div>' . self::render_color_control( $name . '[background]', __( 'Line background', 'html-landing-page-builder-for-woocommerce' ), $section['background'], $id . '-background' );
		return $html;
	}

	/** Render editable Mix it your way controls. */
	private static function render_mix_fields( $section, $id ) {
		$section = array_merge( self::get_default_mix_settings(), $section );
		$name = 'hlpb_sections[' . $id . ']';
		$benefits = is_array( $section['benefits'] ) ? array_slice( $section['benefits'], 0, 8 ) : array();
		$html = '<div class="hlpb-mix-fields">' . self::text_field( $name . '[title]', __( 'Title', 'html-landing-page-builder-for-woocommerce' ), $section['title'] ) . self::text_field( $name . '[subtitle]', __( 'Subtitle', 'html-landing-page-builder-for-woocommerce' ), $section['subtitle'] ) . '</div><div class="hlpb-fields-grid">';
		$html .= self::render_color_control( $name . '[background]', __( 'Section background', 'html-landing-page-builder-for-woocommerce' ), $section['background'], $id . '-background' );
		$html .= self::render_color_control( $name . '[title_color]', __( 'Title color', 'html-landing-page-builder-for-woocommerce' ), $section['title_color'], $id . '-title' );
		$html .= self::render_color_control( $name . '[subtitle_color]', __( 'Subtitle color', 'html-landing-page-builder-for-woocommerce' ), $section['subtitle_color'], $id . '-subtitle' );
		$html .= self::render_color_control( $name . '[benefit_title_color]', __( 'Benefit title color', 'html-landing-page-builder-for-woocommerce' ), $section['benefit_title_color'], $id . '-benefit-title' );
		$html .= self::render_color_control( $name . '[benefit_text_color]', __( 'Benefit text color', 'html-landing-page-builder-for-woocommerce' ), $section['benefit_text_color'], $id . '-benefit-text' ) . '</div><fieldset class="hlpb-mix-benefits"><legend>' . esc_html__( 'Benefits', 'html-landing-page-builder-for-woocommerce' ) . '</legend><div class="hlpb-mix-benefit-list">';
		foreach ( $benefits as $index => $benefit ) {
			$html .= self::render_mix_benefit_row( $id, $index, $benefit );
		}
		$html .= '</div><button type="button" class="button hlpb-add-mix-benefit">' . esc_html__( 'Add benefit', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset>';
		return $html;
	}

	/** Render one Mix benefit editor row. */
	private static function render_mix_benefit_row( $section_id, $index, $benefit ) {
		$icons = self::get_icon_labels();
		$icon = isset( $benefit['icon'] ) && isset( $icons[ $benefit['icon'] ] ) ? $benefit['icon'] : 'heart';
		$name = 'hlpb_sections[' . $section_id . '][benefits][' . absint( $index ) . ']';
		$html = '<div class="hlpb-mix-benefit-row"><div class="hlpb-mix-benefit-controls"><label><span>' . esc_html__( 'Icon', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $name . '[icon]' ) . '">';
		foreach ( $icons as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $icon, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$icon_color = isset( $benefit['icon_color'] ) ? sanitize_hex_color( $benefit['icon_color'] ) : '#244c59';
		$html .= '</select></label><label><span>' . esc_html__( 'Title', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[title]' ) . '" value="' . esc_attr( isset( $benefit['title'] ) ? $benefit['title'] : '' ) . '"></label>';
		$html .= self::render_color_control( $name . '[icon_color]', __( 'Icon color', 'html-landing-page-builder-for-woocommerce' ), $icon_color ? $icon_color : '#244c59', $section_id . '-icon-' . absint( $index ) );
		$html .= '<label class="hlpb-mix-benefit-text"><span>' . esc_html__( 'Text', 'html-landing-page-builder-for-woocommerce' ) . '</span><textarea rows="2" name="' . esc_attr( $name . '[text]' ) . '">' . esc_textarea( isset( $benefit['text'] ) ? $benefit['text'] : '' ) . '</textarea></label><button type="button" class="button-link-delete hlpb-remove-mix-benefit" aria-label="' . esc_attr__( 'Remove benefit', 'html-landing-page-builder-for-woocommerce' ) . '">×</button></div></div>';
		return $html;
	}

	/** Render editable reviews section controls. */
	private static function render_reviews_fields( $section, $id, $template_slug ) {
		$section = array_merge( self::get_default_reviews_settings(), $section );
		$name = 'hlpb_sections[' . $id . ']';
		$reviews = is_array( $section['reviews'] ) ? array_slice( $section['reviews'], 0, 20 ) : array();
		$html = '<div class="hlpb-fields-column hlpb-reviews-fields">' . self::text_field( $name . '[title]', __( 'Heading', 'html-landing-page-builder-for-woocommerce' ), $section['title'] ) . self::text_field( $name . '[subtitle]', __( 'Subtitle', 'html-landing-page-builder-for-woocommerce' ), $section['subtitle'] ) . '<div class="hlpb-fields-grid"><div class="hlpb-field"><label>' . esc_html__( 'Overall stars', 'html-landing-page-builder-for-woocommerce' ) . '</label>' . self::render_star_select( $name . '[overall_stars]', $section['overall_stars'] ) . '</div>' . self::text_field( $name . '[overall_score]', __( 'Overall score text', 'html-landing-page-builder-for-woocommerce' ), $section['overall_score'] ) . self::text_field( $name . '[overall_count]', __( 'Review count text', 'html-landing-page-builder-for-woocommerce' ), $section['overall_count'] ) . '</div><div class="hlpb-fields-grid">';
		$color_fields = array(
			'background' => __( 'Section background', 'html-landing-page-builder-for-woocommerce' ),
			'heading_color' => __( 'Heading color', 'html-landing-page-builder-for-woocommerce' ),
			'subtitle_color' => __( 'Subtitle color', 'html-landing-page-builder-for-woocommerce' ),
			'star_color' => __( 'Star color', 'html-landing-page-builder-for-woocommerce' ),
			'score_color' => __( 'Overall score color', 'html-landing-page-builder-for-woocommerce' ),
			'count_color' => __( 'Review count color', 'html-landing-page-builder-for-woocommerce' ),
			'card_background' => __( 'Review card background', 'html-landing-page-builder-for-woocommerce' ),
			'card_border_color' => __( 'Review card border color', 'html-landing-page-builder-for-woocommerce' ),
			'review_title_color' => __( 'Review title color', 'html-landing-page-builder-for-woocommerce' ),
			'review_text_color' => __( 'Review text color', 'html-landing-page-builder-for-woocommerce' ),
			'reviewer_color' => __( 'Reviewer name color', 'html-landing-page-builder-for-woocommerce' ),
			'verified_color' => __( 'Verified label color', 'html-landing-page-builder-for-woocommerce' ),
		);
		foreach ( $color_fields as $key => $label ) {
			$html .= self::render_color_control( $name . '[' . $key . ']', $label, $section[ $key ], $id . '-reviews-' . $key );
		}
		$html .= '</div><fieldset class="hlpb-reviews-list"><legend>' . esc_html__( 'Reviews', 'html-landing-page-builder-for-woocommerce' ) . '</legend><input type="hidden" name="' . esc_attr( $name . '[reviews_present]' ) . '" value="1"><div class="hlpb-review-items">';
		foreach ( $reviews as $index => $review ) {
			$html .= self::render_review_row( $id, $index, $review, $template_slug );
		}
		$html .= '</div><template class="hlpb-review-template">' . self::render_review_row( '__hlpb_review_template__', 0, self::get_default_reviews_settings()['reviews'][0], $template_slug ) . '</template><button type="button" class="button hlpb-add-review">' . esc_html__( 'Add review', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset></div>';
		return $html;
	}

	/** Render editable FAQ controls. */
	private static function render_faq_fields( $section, $id ) {
		$section = array_merge( self::get_default_faq_settings(), $section );
		$name = 'hlpb_sections[' . $id . ']';
		$faqs = is_array( $section['faqs'] ) ? array_slice( $section['faqs'], 0, 30 ) : array();
		$html = '<div class="hlpb-fields-column hlpb-faq-fields"><div class="hlpb-field"><label for="hlpb-faq-title-' . esc_attr( $id ) . '">' . esc_html__( 'Heading', 'html-landing-page-builder-for-woocommerce' ) . '</label><textarea id="hlpb-faq-title-' . esc_attr( $id ) . '" rows="2" name="' . esc_attr( $name . '[title]' ) . '">' . esc_textarea( $section['title'] ) . '</textarea></div>';
		$html .= self::text_field( $name . '[subtitle]', __( 'Intro text', 'html-landing-page-builder-for-woocommerce' ), $section['subtitle'] ) . '<div class="hlpb-fields-grid">';
		$color_fields = array(
			'background' => __( 'Section background', 'html-landing-page-builder-for-woocommerce' ),
			'heading_color' => __( 'Heading color', 'html-landing-page-builder-for-woocommerce' ),
			'intro_color' => __( 'Intro text color', 'html-landing-page-builder-for-woocommerce' ),
			'question_color' => __( 'Question color', 'html-landing-page-builder-for-woocommerce' ),
			'answer_color' => __( 'Answer color', 'html-landing-page-builder-for-woocommerce' ),
			'item_background' => __( 'Question background', 'html-landing-page-builder-for-woocommerce' ),
			'item_border_color' => __( 'Question border color', 'html-landing-page-builder-for-woocommerce' ),
			'arrow_color' => __( 'Arrow color', 'html-landing-page-builder-for-woocommerce' ),
		);
		foreach ( $color_fields as $key => $label ) {
			$html .= self::render_color_control( $name . '[' . $key . ']', $label, $section[ $key ], $id . '-faq-' . $key );
		}
		$html .= '</div><fieldset class="hlpb-faq-list"><legend>' . esc_html__( 'Questions and answers', 'html-landing-page-builder-for-woocommerce' ) . '</legend><input type="hidden" name="' . esc_attr( $name . '[faqs_present]' ) . '" value="1"><div class="hlpb-faq-items">';
		foreach ( $faqs as $index => $faq ) {
			$html .= self::render_faq_row( $id, $index, $faq );
		}
		$html .= '</div><template class="hlpb-faq-template">' . self::render_faq_row( '__hlpb_faq_template__', 0, array( 'question' => '', 'answer' => '' ) ) . '</template><button type="button" class="button hlpb-add-faq">' . esc_html__( 'Add question', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset></div>';
		return $html;
	}

	/** Render one FAQ editor row. */
	private static function render_faq_row( $section_id, $index, $faq ) {
		$question = isset( $faq['question'] ) ? $faq['question'] : '';
		$answer = isset( $faq['answer'] ) ? $faq['answer'] : '';
		$name = 'hlpb_sections[' . $section_id . '][faqs][' . absint( $index ) . ']';
		return '<fieldset class="hlpb-faq-item"><div class="hlpb-faq-item-heading"><strong>' . esc_html( sprintf( __( 'Question %d', 'html-landing-page-builder-for-woocommerce' ), $index + 1 ) ) . '</strong><button type="button" class="button-link-delete hlpb-remove-faq">' . esc_html__( 'Remove question', 'html-landing-page-builder-for-woocommerce' ) . '</button></div><label><span>' . esc_html__( 'Question', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[question]' ) . '" value="' . esc_attr( $question ) . '"></label><label><span>' . esc_html__( 'Answer', 'html-landing-page-builder-for-woocommerce' ) . '</span><textarea rows="3" name="' . esc_attr( $name . '[answer]' ) . '">' . esc_textarea( $answer ) . '</textarea></label></fieldset>';
	}

	/** Render editable footer settings and repeatable content. */
	private static function render_footer_fields( $section, $id, $template_slug ) {
		$section = array_merge( self::get_default_footer_settings(), $section );
		$name = 'hlpb_sections[' . $id . ']';
		$logo_id = absint( $section['logo_id'] );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : false;
		$logo_url = $logo_url ? $logo_url : self::get_template_asset_url( $template_slug ) . 'icons/nest-stack.svg';
		$primary_links = is_array( $section['primary_links'] ) ? array_slice( $section['primary_links'], 0, 20 ) : array();
		$legal_links = is_array( $section['legal_links'] ) ? array_slice( $section['legal_links'], 0, 20 ) : array();
		$social_links = is_array( $section['social_links'] ) ? array_slice( $section['social_links'], 0, 20 ) : array();
		$html = '<div class="hlpb-fields-column hlpb-footer-fields"><div class="hlpb-footer-brand-fields"><div class="hlpb-field"><label>' . esc_html__( 'Footer logo', 'html-landing-page-builder-for-woocommerce' ) . '</label><div class="hlpb-image-control"><img class="hlpb-footer-logo-preview" src="' . esc_url( $logo_url ) . '" alt=""><input class="hlpb-footer-logo-id" type="hidden" name="' . esc_attr( $name . '[logo_id]' ) . '" value="' . esc_attr( $logo_id ) . '"><button type="button" class="button hlpb-select-image" data-image-target="footer-logo">' . esc_html__( 'Choose logo', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button-link hlpb-clear-footer-logo">' . esc_html__( 'Use template logo', 'html-landing-page-builder-for-woocommerce' ) . '</button></div></div><div class="hlpb-fields-column">' . self::text_field( $name . '[brand_name]', __( 'Brand name', 'html-landing-page-builder-for-woocommerce' ), $section['brand_name'] ) . self::text_field( $name . '[brand_tagline]', __( 'Brand tagline', 'html-landing-page-builder-for-woocommerce' ), $section['brand_tagline'] ) . self::text_field( $name . '[brand_url]', __( 'Brand link URL', 'html-landing-page-builder-for-woocommerce' ), $section['brand_url'] ) . '</div></div>';
		$html .= '<fieldset class="hlpb-footer-list"><legend>' . esc_html__( 'Main footer links', 'html-landing-page-builder-for-woocommerce' ) . '</legend><input type="hidden" name="' . esc_attr( $name . '[primary_links_present]' ) . '" value="1"><div class="hlpb-footer-rows" data-footer-group="primary_links">';
		foreach ( $primary_links as $index => $item ) {
			$html .= self::render_footer_link_row( $id, 'primary_links', $index, $item );
		}
		$html .= '</div><template class="hlpb-footer-link-template" data-footer-group="primary_links">' . self::render_footer_link_row( '__hlpb_footer_template__', 'primary_links', 0, array( 'label' => '', 'url' => '' ) ) . '</template><button type="button" class="button hlpb-add-footer-row" data-footer-group="primary_links">' . esc_html__( 'Add footer link', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset>';
		$html .= '<fieldset class="hlpb-footer-list"><legend>' . esc_html__( 'Social links', 'html-landing-page-builder-for-woocommerce' ) . '</legend><input type="hidden" name="' . esc_attr( $name . '[social_links_present]' ) . '" value="1"><div class="hlpb-footer-rows" data-footer-group="social_links">';
		foreach ( $social_links as $index => $item ) {
			$html .= self::render_footer_social_row( $id, $index, $item );
		}
		$html .= '</div><template class="hlpb-footer-social-template">' . self::render_footer_social_row( '__hlpb_footer_template__', 0, array( 'icon' => 'instagram', 'label' => '', 'url' => '' ) ) . '</template><button type="button" class="button hlpb-add-footer-row" data-footer-group="social_links">' . esc_html__( 'Add social link', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset>';
		$html .= '<div class="hlpb-fields-grid">' . self::text_field( $name . '[copyright]', __( 'Copyright text', 'html-landing-page-builder-for-woocommerce' ), $section['copyright'] ) . self::text_field( $name . '[legal_note]', __( 'Bottom note', 'html-landing-page-builder-for-woocommerce' ), $section['legal_note'] ) . '<div class="hlpb-field"><label>' . esc_html__( 'Disclaimer text', 'html-landing-page-builder-for-woocommerce' ) . '</label><textarea rows="3" name="' . esc_attr( $name . '[disclaimer]' ) . '">' . esc_textarea( $section['disclaimer'] ) . '</textarea></div></div>';
		$html .= '<fieldset class="hlpb-footer-list"><legend>' . esc_html__( 'Legal links', 'html-landing-page-builder-for-woocommerce' ) . '</legend><input type="hidden" name="' . esc_attr( $name . '[legal_links_present]' ) . '" value="1"><div class="hlpb-footer-rows" data-footer-group="legal_links">';
		foreach ( $legal_links as $index => $item ) {
			$html .= self::render_footer_link_row( $id, 'legal_links', $index, $item );
		}
		$html .= '</div><template class="hlpb-footer-link-template" data-footer-group="legal_links">' . self::render_footer_link_row( '__hlpb_footer_template__', 'legal_links', 0, array( 'label' => '', 'url' => '' ) ) . '</template><button type="button" class="button hlpb-add-footer-row" data-footer-group="legal_links">' . esc_html__( 'Add legal link', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset><div class="hlpb-fields-grid">';
		$color_fields = array(
			'background' => __( 'Footer background', 'html-landing-page-builder-for-woocommerce' ),
			'text_color' => __( 'Main text and link color', 'html-landing-page-builder-for-woocommerce' ),
			'brand_color' => __( 'Brand text color', 'html-landing-page-builder-for-woocommerce' ),
			'bottom_text_color' => __( 'Bottom text color', 'html-landing-page-builder-for-woocommerce' ),
			'disclaimer_color' => __( 'Disclaimer color', 'html-landing-page-builder-for-woocommerce' ),
			'divider_color' => __( 'Divider color', 'html-landing-page-builder-for-woocommerce' ),
		);
		foreach ( $color_fields as $key => $label ) {
			$html .= self::render_color_control( $name . '[' . $key . ']', $label, $section[ $key ], $id . '-footer-' . $key );
		}
		return $html . '</div></div>';
	}

	/** Render one primary or legal footer link editor row. */
	private static function render_footer_link_row( $section_id, $group, $index, $item ) {
		$name = 'hlpb_sections[' . $section_id . '][' . $group . '][' . absint( $index ) . ']';
		return '<div class="hlpb-footer-row"><strong>' . esc_html( sprintf( __( 'Link %d', 'html-landing-page-builder-for-woocommerce' ), $index + 1 ) ) . '</strong><label><span>' . esc_html__( 'Label', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( isset( $item['label'] ) ? $item['label'] : '' ) . '"></label><label><span>' . esc_html__( 'Link URL', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[url]' ) . '" value="' . esc_attr( isset( $item['url'] ) ? $item['url'] : '' ) . '"></label><button type="button" class="button-link-delete hlpb-remove-footer-row">' . esc_html__( 'Remove', 'html-landing-page-builder-for-woocommerce' ) . '</button></div>';
	}

	/** Render one social footer link editor row. */
	private static function render_footer_social_row( $section_id, $index, $item ) {
		$name = 'hlpb_sections[' . $section_id . '][social_links][' . absint( $index ) . ']';
		$icons = self::get_footer_social_icons();
		$icon = isset( $item['icon'] ) && isset( $icons[ $item['icon'] ] ) ? $item['icon'] : 'instagram';
		$html = '<div class="hlpb-footer-row hlpb-footer-social-row"><strong>' . esc_html( sprintf( __( 'Social link %d', 'html-landing-page-builder-for-woocommerce' ), $index + 1 ) ) . '</strong><label><span>' . esc_html__( 'Icon', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $name . '[icon]' ) . '">';
		foreach ( $icons as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $icon, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></label><label><span>' . esc_html__( 'Accessible label', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( isset( $item['label'] ) ? $item['label'] : '' ) . '"></label><label><span>' . esc_html__( 'Link URL', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[url]' ) . '" value="' . esc_attr( isset( $item['url'] ) ? $item['url'] : '' ) . '"></label><button type="button" class="button-link-delete hlpb-remove-footer-row">' . esc_html__( 'Remove', 'html-landing-page-builder-for-woocommerce' ) . '</button></div>';
		return $html;
	}

	/** Render one review editor row. */
	private static function render_review_row( $section_id, $index, $review, $template_slug ) {
		$defaults = self::get_default_reviews_settings()['reviews'][ $index % 3 ];
		$review = array_merge( $defaults, $review );
		$image_id = absint( $review['image_id'] );
		$demo_image = self::safe_demo_image( $review['demo_image'], $template_slug );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
		$image_url = $image_url ? $image_url : self::get_template_asset_url( $template_slug ) . 'images/' . $demo_image;
		$name = 'hlpb_sections[' . $section_id . '][reviews][' . absint( $index ) . ']';
		$html = '<article class="hlpb-review-item"><div class="hlpb-review-item-heading"><strong>' . esc_html( sprintf( __( 'Review %d', 'html-landing-page-builder-for-woocommerce' ), $index + 1 ) ) . '</strong><button type="button" class="button-link-delete hlpb-remove-review">' . esc_html__( 'Remove review', 'html-landing-page-builder-for-woocommerce' ) . '</button></div><div class="hlpb-review-item-fields"><div class="hlpb-image-control"><img class="hlpb-image-preview hlpb-review-image-preview" src="' . esc_url( $image_url ) . '" alt=""><input type="hidden" class="hlpb-image-id hlpb-review-image-id" name="' . esc_attr( $name . '[image_id]' ) . '" value="' . esc_attr( $image_id ) . '"><input type="hidden" class="hlpb-demo-image" name="' . esc_attr( $name . '[demo_image]' ) . '" value="' . esc_attr( $demo_image ) . '"><button type="button" class="button hlpb-select-image">' . esc_html__( 'Choose photo', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button-link hlpb-clear-image">' . esc_html__( 'Use demo photo', 'html-landing-page-builder-for-woocommerce' ) . '</button></div><div class="hlpb-review-text-fields">' . self::text_field( $name . '[image_alt]', __( 'Photo description (alt text)', 'html-landing-page-builder-for-woocommerce' ), $review['image_alt'] ) . '<div class="hlpb-field"><label>' . esc_html__( 'Stars', 'html-landing-page-builder-for-woocommerce' ) . '</label>' . self::render_star_select( $name . '[stars]', $review['stars'] ) . '</div>' . self::text_field( $name . '[title]', __( 'Review title', 'html-landing-page-builder-for-woocommerce' ), $review['title'] ) . '<div class="hlpb-field"><label>' . esc_html__( 'Review text', 'html-landing-page-builder-for-woocommerce' ) . '</label><textarea rows="4" name="' . esc_attr( $name . '[text]' ) . '">' . esc_textarea( $review['text'] ) . '</textarea></div>' . self::text_field( $name . '[reviewer]', __( 'Reviewer name', 'html-landing-page-builder-for-woocommerce' ), $review['reviewer'] ) . '<label class="hlpb-checkbox"><input type="checkbox" name="' . esc_attr( $name . '[verified]' ) . '" value="1" ' . checked( ! empty( $review['verified'] ), true, false ) . '> ' . esc_html__( 'Show verified buyer label', 'html-landing-page-builder-for-woocommerce' ) . '</label>' . self::text_field( $name . '[verified_text]', __( 'Verified buyer label', 'html-landing-page-builder-for-woocommerce' ), $review['verified_text'] ) . '</div></div></article>';
		return $html;
	}

	/** Render a five-point star selector. */
	private static function render_star_select( $name, $value ) {
		$value = min( 5, max( 1, absint( $value ) ) );
		$html = '<select name="' . esc_attr( $name ) . '">';
		for ( $stars = 1; $stars <= 5; $stars++ ) {
			$html .= '<option value="' . esc_attr( $stars ) . '" ' . selected( $value, $stars, false ) . '>' . esc_html( $stars . ' / 5' ) . '</option>';
		}
		return $html . '</select>';
	}

	/** Render a WordPress color control. */
	private static function render_color_control( $name, $label, $color, $id ) {
		$color = sanitize_hex_color( $color );
		$color = $color ? $color : '#000000';
		return '<div class="hlpb-field hlpb-color-field"><label for="hlpb-color-' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><input type="color" name="' . esc_attr( $name ) . '" id="hlpb-color-' . esc_attr( $id ) . '" value="' . esc_attr( $color ) . '"><span>' . esc_html( $color ) . '</span></div>';
	}

	/**
	 * Render Text + Image controls.
	 *
	 * @param array<string, mixed> $section Section data.
	 * @param string               $id Section key.
	 * @return string
	 */
	private static function render_text_image_fields( $section, $id, $template_slug ) {
		$defaults       = self::get_demo_text_image( 0, $template_slug );
		$section        = array_merge( $defaults, $section );
		$image_id       = isset( $section['image_id'] ) ? absint( $section['image_id'] ) : 0;
		$demo_image     = self::safe_demo_image( $section['demo_image'], $template_slug );
		$image_url      = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : false;
		$image_url      = $image_url ? $image_url : self::get_template_asset_url( $template_slug ) . 'images/' . $demo_image;
		$image_alt      = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : $section['image_alt'];
		$position       = 'left' === $section['image_position'] ? 'left' : 'right';
		$text_color     = sanitize_hex_color( $section['color'] );
		$text_color     = $text_color ? $text_color : '#29445c';
		$features       = is_array( $section['features'] ) ? array_slice( $section['features'], 0, 8 ) : array();
		$icon_labels    = self::get_icon_labels();
		$name_prefix    = 'hlpb_sections[' . $id . ']';
		$html           = '<div class="hlpb-text-image-fields">';
		$html          .= '<div class="hlpb-image-control"><img class="hlpb-image-preview" src="' . esc_url( $image_url ) . '" alt=""><input class="hlpb-image-id" type="hidden" name="' . esc_attr( $name_prefix . '[image_id]' ) . '" value="' . esc_attr( $image_id ) . '"><input type="hidden" class="hlpb-demo-image" name="' . esc_attr( $name_prefix . '[demo_image]' ) . '" value="' . esc_attr( $demo_image ) . '"><button type="button" class="button hlpb-select-image">' . esc_html__( 'Choose image', 'html-landing-page-builder-for-woocommerce' ) . '</button><button type="button" class="button-link hlpb-clear-image">' . esc_html__( 'Use demo image', 'html-landing-page-builder-for-woocommerce' ) . '</button></div>';
		$html          .= '<div class="hlpb-fields-column">';
		$html          .= self::text_field( $name_prefix . '[subtitle]', __( 'Subtitle', 'html-landing-page-builder-for-woocommerce' ), $section['subtitle'] );
		$html          .= self::text_field( $name_prefix . '[title]', __( 'Title', 'html-landing-page-builder-for-woocommerce' ), $section['title'], true );
		$html          .= self::text_field( $name_prefix . '[body]', __( 'Text', 'html-landing-page-builder-for-woocommerce' ), $section['body'], true );
		$html          .= '<div class="hlpb-field"><label>' . esc_html__( 'Image position', 'html-landing-page-builder-for-woocommerce' ) . '</label><select name="' . esc_attr( $name_prefix . '[image_position]' ) . '"><option value="left" ' . selected( $position, 'left', false ) . '>' . esc_html__( 'Image left, text right', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="right" ' . selected( $position, 'right', false ) . '>' . esc_html__( 'Image right, text left', 'html-landing-page-builder-for-woocommerce' ) . '</option></select></div>';
		$html          .= '<div class="hlpb-field hlpb-color-field"><label for="hlpb-color-' . esc_attr( $id ) . '">' . esc_html__( 'Text color', 'html-landing-page-builder-for-woocommerce' ) . '</label><input type="color" name="' . esc_attr( $name_prefix . '[color]' ) . '" id="hlpb-color-' . esc_attr( $id ) . '" value="' . esc_attr( $text_color ) . '"><span>' . esc_html( $text_color ) . '</span></div>';
		$html          .= '<fieldset class="hlpb-features"><legend>' . esc_html__( 'Icons and captions', 'html-landing-page-builder-for-woocommerce' ) . '</legend><div class="hlpb-feature-list">';
		foreach ( $features as $index => $feature ) {
			$html .= self::render_feature_row( $id, $index, $feature, $icon_labels );
		}
		$html          .= '</div><button type="button" class="button hlpb-add-feature">' . esc_html__( 'Add icon', 'html-landing-page-builder-for-woocommerce' ) . '</button></fieldset>';
		$html          .= '</div></div>';
		return $html;
	}

	/**
	 * Render one icon/caption row.
	 *
	 * @param string $section_id Section key.
	 * @param int    $index Feature index.
	 * @param array  $feature Feature data.
	 * @param array  $icons Available icons.
	 * @return string
	 */
	private static function render_feature_row( $section_id, $index, $feature, $icons ) {
		$icon = isset( $feature['icon'] ) && isset( $icons[ $feature['icon'] ] ) ? $feature['icon'] : 'heart';
		$text = isset( $feature['text'] ) ? $feature['text'] : '';
		$name = 'hlpb_sections[' . $section_id . '][features][' . absint( $index ) . ']';
		$html = '<div class="hlpb-feature-row"><label><span>' . esc_html__( 'Icon', 'html-landing-page-builder-for-woocommerce' ) . '</span><select name="' . esc_attr( $name . '[icon]' ) . '">';
		foreach ( $icons as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $icon, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></label><label><span>' . esc_html__( 'Caption', 'html-landing-page-builder-for-woocommerce' ) . '</span><input type="text" name="' . esc_attr( $name . '[text]' ) . '" value="' . esc_attr( $text ) . '"></label><button type="button" class="button-link-delete hlpb-remove-feature" aria-label="' . esc_attr__( 'Remove icon', 'html-landing-page-builder-for-woocommerce' ) . '">×</button></div>';
		return $html;
	}

	/**
	 * Render a labeled text input or textarea.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @param bool   $textarea Use textarea.
	 * @return string
	 */
	private static function text_field( $name, $label, $value, $textarea = false ) {
		$html = '<div class="hlpb-field"><label>' . esc_html( $label ) . '</label>';
		if ( $textarea ) {
			$html .= '<textarea rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			$html .= '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Available icon sprite symbols.
	 *
	 * @return array<string, string>
	 */
	public static function get_icon_labels() {
		return array(
			'leaf'    => __( 'Leaf', 'html-landing-page-builder-for-woocommerce' ),
			'gift'    => __( 'Gift', 'html-landing-page-builder-for-woocommerce' ),
			'palette' => __( 'Palette', 'html-landing-page-builder-for-woocommerce' ),
			'heart'   => __( 'Heart', 'html-landing-page-builder-for-woocommerce' ),
			'people'  => __( 'People', 'html-landing-page-builder-for-woocommerce' ),
			'waves'   => __( 'Waves', 'html-landing-page-builder-for-woocommerce' ),
			'diamond' => __( 'Diamond', 'html-landing-page-builder-for-woocommerce' ),
			'sofa'    => __( 'Sofa', 'html-landing-page-builder-for-woocommerce' ),
			'moon'    => __( 'Moon', 'html-landing-page-builder-for-woocommerce' ),
			'truck'   => __( 'Truck', 'html-landing-page-builder-for-woocommerce' ),
			'shield'  => __( 'Shield', 'html-landing-page-builder-for-woocommerce' ),
		);
	}

	/**
	 * Save a newly requested section card over AJAX.
	 *
	 * @return void
	 */
	public static function ajax_render_section() {
		check_ajax_referer( 'hlpb_builder', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit landing pages.', 'html-landing-page-builder-for-woocommerce' ) ), 403 );
		}

		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$template_slug = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'cozy-mix';
		$types = self::get_section_types();
		if ( ! isset( $types[ $type ] ) || ! self::get_template_info( $template_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid section type.', 'html-landing-page-builder-for-woocommerce' ) ), 400 );
		}

		$section = array(
			'id'    => 'section_' . wp_generate_password( 10, false, false ),
			'type'  => $type,
			'order' => 1,
		);
		if ( 'text_image' === $type ) {
			$section = array_merge( $section, self::get_demo_text_image( 0, $template_slug ) );
		} elseif ( 'header' === $type ) {
			$section = array_merge( $section, self::get_default_header_settings() );
		} elseif ( 'offer_line' === $type ) {
			$section = array_merge( $section, self::get_default_offer_settings() );
		} elseif ( 'mix_it' === $type ) {
			$section = array_merge( $section, self::get_default_mix_settings() );
		} elseif ( 'reviews' === $type ) {
			$section = array_merge( $section, self::get_default_reviews_settings() );
		} elseif ( 'faq' === $type ) {
			$section = array_merge( $section, self::get_default_faq_settings() );
		} elseif ( 'footer' === $type ) {
			$section = array_merge( $section, self::get_default_footer_settings() );
		}

		wp_send_json_success( array( 'html' => self::render_section_card( $section, $template_slug ) ) );
	}

	/**
	 * Enqueue section editor assets on the landing page editor.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'hlpb-builder-admin', HLPB_URL . 'assets/admin/builder.css', array(), HLPB_VERSION );
		wp_enqueue_style( 'hlpb-cart-section-admin', HLPB_URL . 'assets/frontend/cart-section.css', array( 'hlpb-builder-admin' ), HLPB_VERSION );
		wp_enqueue_script( 'hlpb-builder-admin', HLPB_URL . 'assets/admin/builder.js', array( 'jquery', 'jquery-ui-sortable' ), HLPB_VERSION, true );
		$template_slug = self::get_template_slug( get_the_ID() );
		$template      = self::get_template_info( $template_slug );
		wp_localize_script(
			'hlpb-builder-admin',
			'HLPBBuilder',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'hlpb_builder' ),
				'icons'       => self::get_icon_labels(),
				'labels'      => self::get_section_types(),
				'previews'    => self::get_section_previews(),
				'singleInstanceTypes' => self::get_singleton_section_types(),
				'error'       => __( 'The section could not be added. Please try again.', 'html-landing-page-builder-for-woocommerce' ),
				'pluginUrl'   => HLPB_URL,
				'imageBase'   => self::get_template_asset_url( $template_slug ) . 'images/',
				'defaultImage'=> $template && ! empty( $template['default_image'] ) ? sanitize_file_name( $template['default_image'] ) : 'texture-closeup-480.webp',
				'orderLabel'  => __( 'Order', 'html-landing-page-builder-for-woocommerce' ),
				'removeLabel' => __( 'Remove', 'html-landing-page-builder-for-woocommerce' ),
				'demoText'    => __( 'This section displays the matching demo content from the supplied landing page.', 'html-landing-page-builder-for-woocommerce' ),
				'iconLabel'   => __( 'Icon', 'html-landing-page-builder-for-woocommerce' ),
				'captionLabel'=> __( 'Caption', 'html-landing-page-builder-for-woocommerce' ),
				'titleLabel' => __( 'Title', 'html-landing-page-builder-for-woocommerce' ),
				'textLabel' => __( 'Text', 'html-landing-page-builder-for-woocommerce' ),
				'menuItemLabel' => __( 'Label', 'html-landing-page-builder-for-woocommerce' ),
				'menuLinkLabel' => __( 'Link URL', 'html-landing-page-builder-for-woocommerce' ),
				'iconColorLabel' => __( 'Icon color', 'html-landing-page-builder-for-woocommerce' ),
				'removeIcon'  => __( 'Remove icon', 'html-landing-page-builder-for-woocommerce' ),
				'removeMenuItem' => __( 'Remove menu item', 'html-landing-page-builder-for-woocommerce' ),
				'removeBenefit' => __( 'Remove benefit', 'html-landing-page-builder-for-woocommerce' ),
				'collapseSection' => __( 'Collapse', 'html-landing-page-builder-for-woocommerce' ),
				'expandSection' => __( 'Expand', 'html-landing-page-builder-for-woocommerce' ),
				'reviewLabel' => __( 'Review', 'html-landing-page-builder-for-woocommerce' ),
				'faqQuestionLabel' => __( 'Question', 'html-landing-page-builder-for-woocommerce' ),
				'footerLinkLabel' => __( 'Link', 'html-landing-page-builder-for-woocommerce' ),
				'footerSocialLabel' => __( 'Social link', 'html-landing-page-builder-for-woocommerce' ),
			)
		);
	}

	/**
	 * Save all section configuration.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function save_sections( $post_id, $post ) {
		if ( ! isset( $_POST['hlpb_sections_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hlpb_sections_nonce'] ) ), 'hlpb_save_sections' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$templates      = self::get_templates();
		$template_slug  = isset( $_POST['hlpb_template'] ) ? sanitize_key( wp_unslash( $_POST['hlpb_template'] ) ) : self::get_template_slug( $post_id );
		$input          = isset( $_POST['hlpb_sections'] ) ? wp_unslash( $_POST['hlpb_sections'] ) : array();
		$sections       = self::sanitize_sections( $input, $template_slug );
		$export_error   = '';
		$previous_directory = self::get_current_export_relative_path( $post_id );
		$previous_promo_folder = self::get_export_promo_folder( $post_id );
		if ( $previous_directory ) {
			self::add_export_history( $post_id, $previous_directory );
		}
		update_post_meta( $post_id, self::META_KEY, $sections );
		$has_cart_section = false;
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				$has_cart_section = true;
				HLPB_Cart_Section::ensure_coupon( $post_id, $section );
				break;
			}
		}
		if ( ! $has_cart_section && get_post_meta( $post_id, HLPB_Cart_Section::COUPON_META, true ) ) {
			HLPB_Cart_Section::deactivate_for_landing( $post_id );
			delete_post_meta( $post_id, HLPB_Cart_Section::COUPON_META );
		}
		if ( isset( $_POST['hlpb_export_promo_folder'] ) ) {
			$promo_folder = trim( sanitize_file_name( wp_unslash( $_POST['hlpb_export_promo_folder'] ) ) );
			if ( preg_match( '/^[a-z0-9-]+$/i', $promo_folder ) ) {
				update_post_meta( $post_id, self::EXPORT_PROMO_KEY, $promo_folder );
			} else {
				$export_error = __( 'The Promo folder must contain only letters, numbers, and hyphens.', 'html-landing-page-builder-for-woocommerce' );
			}
		}
		if ( isset( $_POST['hlpb_export_slug'] ) ) {
			$export_slug = trim( sanitize_file_name( wp_unslash( $_POST['hlpb_export_slug'] ) ) );
			if ( preg_match( '/^[a-z0-9-]+$/i', $export_slug ) ) {
				update_post_meta( $post_id, self::EXPORT_SLUG_KEY, $export_slug );
			} else {
				$export_error = __( 'The Landing folder must contain only letters, numbers, and hyphens.', 'html-landing-page-builder-for-woocommerce' );
			}
		}
		if ( isset( $templates[ $template_slug ] ) ) {
			update_post_meta( $post_id, self::TEMPLATE_KEY, $template_slug );
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		$current_promo_folder = self::get_export_promo_folder( $post_id );
		if ( strtolower( $previous_promo_folder ) !== strtolower( $current_promo_folder ) || ! self::has_export_rewrite_rule( $current_promo_folder ) ) {
			self::register_export_route();
			flush_rewrite_rules( false );
		}

		if ( $export_error ) {
			set_transient(
				'hlpb_export_notice_' . get_current_user_id(),
				array( 'success' => false, 'message' => $export_error ),
				MINUTE_IN_SECONDS
			);
			return;
		}

		$result             = self::generate_static_export( $post_id, $previous_directory );
		if ( is_wp_error( $result ) ) {
			set_transient(
				'hlpb_export_notice_' . get_current_user_id(),
				array( 'success' => false, 'message' => $result->get_error_message() ),
				MINUTE_IN_SECONDS
			);
			return;
		}

		$current_directory = self::get_current_export_relative_path( $post_id );
		if ( ! $previous_directory || $previous_directory !== $current_directory ) {
			$message = $previous_directory
				? sprintf(
					/* translators: %s is a folder path relative to the WordPress root. */
					__( 'A new static copy was published in %1$s. The previous folder, %2$s, was left in place.', 'html-landing-page-builder-for-woocommerce' ),
					$current_directory,
					$previous_directory
				)
				: __( 'The static landing page was published.', 'html-landing-page-builder-for-woocommerce' );
			set_transient(
				'hlpb_export_notice_' . get_current_user_id(),
				array( 'success' => true, 'message' => $message, 'url' => $result ),
				MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Show the result of the last static export to the current administrator.
	 *
	 * @return void
	 */
	public static function show_export_notice() {
		$key    = 'hlpb_export_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = ! empty( $notice['success'] ) ? 'notice notice-success is-dismissible' : 'notice notice-error';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $notice['message'] );
		if ( ! empty( $notice['url'] ) ) {
			echo ' <a href="' . esc_url( $notice['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open published landing page', 'html-landing-page-builder-for-woocommerce' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * Write a standalone HTML page and a copy of its selected template assets.
	 *
	 * The export folder is ABSPATH/{promo-folder}/{landing-folder}.
	 *
	 * @param int $post_id Landing page ID.
	 * @return string|WP_Error Public export URL or an error.
	 */
	private static function generate_static_export( $post_id, $previous_directory = '' ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return new WP_Error( 'hlpb_invalid_post', __( 'This record is not a Landing Page.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( $previous_directory ) {
			self::add_export_history( $post_id, $previous_directory );
		}

		$slug = self::get_export_slug( $post_id );
		if ( '' === $slug ) {
			return new WP_Error( 'hlpb_missing_slug', __( 'Add a title and save the record before generating its static page.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( ! preg_match( '/^[a-z0-9-]+$/i', $slug ) ) {
			return new WP_Error( 'hlpb_invalid_slug', __( 'The page slug can contain lowercase letters, numbers, and hyphens only.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$promo_folder = self::get_export_promo_folder( $post_id );
		if ( ! preg_match( '/^[a-z0-9-]+$/i', $promo_folder ) ) {
			return new WP_Error( 'hlpb_invalid_promo_folder', __( 'The Promo folder can contain letters, numbers, and hyphens only.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$template_slug = self::get_template_slug( $post_id );
		$template      = self::get_template_info( $template_slug );
		if ( ! $template ) {
			return new WP_Error( 'hlpb_missing_template', __( 'The selected template is missing or invalid.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$relative_export_path = $promo_folder . '/' . $slug;
		$export_dir           = self::get_export_directory_path( $relative_export_path );
		$root                 = realpath( ABSPATH );
		if ( ! $export_dir || ! $root ) {
			return new WP_Error( 'hlpb_unsafe_export_path', __( 'The static export path is not inside the WordPress root or contains a symbolic link.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$promo_dir = trailingslashit( wp_normalize_path( $root ) ) . $promo_folder;
		if ( is_link( $promo_dir ) || ( file_exists( $promo_dir ) && ! is_dir( $promo_dir ) ) ) {
			return new WP_Error( 'hlpb_export_promo_exists', __( 'A file or symbolic link already uses the Promo folder name.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( ! wp_mkdir_p( $promo_dir ) || ! self::path_is_inside_root( $promo_dir ) ) {
			return new WP_Error( 'hlpb_export_mkdir', __( 'WordPress could not create the Promo folder. Check folder write permissions.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( file_exists( $export_dir ) || is_link( $export_dir ) ) {
			if ( is_link( $export_dir ) || ! is_dir( $export_dir ) ) {
				return new WP_Error( 'hlpb_export_path_exists', __( 'A file or symbolic link already uses the requested landing folder name.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$marker_path = trailingslashit( $export_dir ) . '.hlpb-export.json';
			if ( is_link( $marker_path ) ) {
				return new WP_Error( 'hlpb_export_marker_link', __( 'The export ownership file cannot be a symbolic link.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			if ( is_file( $marker_path ) ) {
				$marker = json_decode( file_get_contents( $marker_path ), true );
				if ( ! is_array( $marker ) || absint( $marker['post_id'] ?? 0 ) !== absint( $post_id ) ) {
					return new WP_Error( 'hlpb_export_owned_elsewhere', __( 'This folder is already used by another landing page export.', 'html-landing-page-builder-for-woocommerce' ) );
				}
			} else {
				$contents = array_diff( scandir( $export_dir ), array( '.', '..' ) );
				if ( ! empty( $contents ) ) {
					return new WP_Error( 'hlpb_export_folder_not_empty', __( 'The export folder already contains files and is not managed by this plugin.', 'html-landing-page-builder-for-woocommerce' ) );
				}
			}
		}

		if ( ! wp_mkdir_p( $export_dir ) || ! self::path_is_inside_root( $export_dir ) ) {
			return new WP_Error( 'hlpb_export_mkdir', __( 'WordPress could not create the static export folder. Check folder write permissions.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$marker_path = trailingslashit( $export_dir ) . '.hlpb-export.json';
		if ( is_link( $marker_path ) ) {
			return new WP_Error( 'hlpb_export_marker_link', __( 'The export ownership file cannot be a symbolic link.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$marker = array(
			'post_id'      => absint( $post_id ),
			'template'     => $template_slug,
			'generated_at' => current_time( 'mysql', true ),
		);
		if ( false === file_put_contents( $marker_path, wp_json_encode( $marker, JSON_PRETTY_PRINT ) ) ) {
			return new WP_Error( 'hlpb_export_marker', __( 'WordPress could not write the export ownership file.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		self::add_export_history( $post_id, $relative_export_path );

		$asset_dir       = $export_dir . '/assets';
		if ( is_link( $asset_dir ) || ( file_exists( $asset_dir ) && ! is_dir( $asset_dir ) ) ) {
			return new WP_Error( 'hlpb_export_assets_path', __( 'The export assets path is not a safe directory.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$template_assets = self::resolve_template_file( $template_slug, $template['assets'] );
		if ( ! $template_assets || ! is_dir( $template_assets ) || ! self::copy_template_directory( $template_assets, $asset_dir ) ) {
			return new WP_Error( 'hlpb_export_assets', __( 'The selected template assets could not be copied to the export folder.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		$sections  = self::get_sections( $post_id );
		$has_cart = (bool) array_filter( $sections, static function ( $section ) { return isset( $section['type'] ) && 'cart_section' === $section['type']; } );
		if ( $has_cart && ! HLPB_Cart_Section::copy_export_assets( $asset_dir ) ) {
			return new WP_Error( 'hlpb_export_cart_assets', __( 'The Cart Section interaction files could not be copied to the export folder.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$asset_url = trailingslashit( home_url( '/' . rawurlencode( $promo_folder ) . '/' . rawurlencode( $slug ) . '/assets/' ) );
		$images    = self::copy_section_images( $sections, $asset_dir, $asset_url );
		if ( is_wp_error( $images ) ) {
			return $images;
		}

		$styles_path = self::path_from_asset_root( $template, $template['styles'] );
		$script_path = ! empty( $template['script'] ) ? self::path_from_asset_root( $template, $template['script'] ) : '';
		if ( ! $styles_path ) {
			return new WP_Error( 'hlpb_export_styles', __( 'The selected template stylesheet must be inside its assets folder.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$title     = get_the_title( $post_id );
		$title_html = esc_html( $title );
		$language  = esc_attr( get_bloginfo( 'language' ) );
		$script_config = wp_json_encode(
			array( 'assetsUrl' => $asset_url ),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$html = "<!doctype html>\n<html lang=\"{$language}\">\n<head>\n<meta charset=\"" . esc_attr( get_bloginfo( 'charset' ) ) . "\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1,viewport-fit=cover\">\n<meta name=\"theme-color\" content=\"#fffdf9\">\n<title>{$title_html}</title>\n<link rel=\"stylesheet\" href=\"" . esc_url( $asset_url . $styles_path ) . "\">\n";
		if ( $script_config ) {
			$html .= '<script>window.HLPBDemo=' . $script_config . ";</script>\n";
		}
		if ( $script_path && is_file( self::resolve_template_file( $template_slug, $template['script'] ) ) ) {
			$html .= '<script defer src="' . esc_url( $asset_url . $script_path ) . '"></script>' . "\n";
		}
		$cart_config = $has_cart ? HLPB_Cart_Section::build_public_config( $post_id, $sections ) : array();
		$cart_config_json = $cart_config ? wp_json_encode( $cart_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) : '';
		if ( $has_cart ) {
			$html .= '<link rel="stylesheet" href="' . esc_url( $asset_url . 'hlpb-cart.css' ) . '?v=' . esc_attr( HLPB_VERSION ) . '">' . "\n";
			if ( $cart_config_json ) {
				$html .= '<script>window.HLPBCartData=' . $cart_config_json . ';</script>' . "\n";
			}
			$html .= '<script defer src="' . esc_url( $asset_url . 'hlpb-cart.js' ) . '?v=' . esc_attr( HLPB_VERSION ) . '"></script>' . "\n";
		}
		$html .= "</head>\n<body class=\"hlpb-landing-page\">\n<div class=\"hlpb-page\">\n";
		$html .= self::get_demo_fragment( 'sprite', $template_slug, $asset_url ) . "\n<main id=\"top\">\n";
		$has_cart = false;
		foreach ( $sections as $section ) {
			if ( isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				$has_cart = true;
			}
			$html .= self::render_frontend_section( $section, $template_slug, $asset_url, $images ) . "\n";
		}
		$html .= "</main>\n";
		if ( $has_cart ) {
			$html .= self::render_demo_buybar( $template_slug, $asset_url ) . "\n";
		}
		$html .= "</div>\n</body>\n</html>\n";

		$index_path = $export_dir . '/index.html';
		$temp_path  = $index_path . '.tmp';
		if ( is_link( $index_path ) || is_link( $temp_path ) ) {
			return new WP_Error( 'hlpb_export_html_link', __( 'The export HTML path cannot be a symbolic link.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( false === file_put_contents( $temp_path, $html ) || ! rename( $temp_path, $index_path ) ) {
			if ( file_exists( $temp_path ) ) {
				wp_delete_file( $temp_path );
			}
			return new WP_Error( 'hlpb_export_html', __( 'WordPress could not write index.html to the export folder.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$public_url = self::build_export_url( $promo_folder, $slug );
		update_post_meta( $post_id, self::EXPORT_KEY, $public_url );
		update_post_meta( $post_id, self::EXPORT_DIRECTORY_KEY, $relative_export_path );
		self::add_export_history( $post_id, $relative_export_path );
		return $public_url;
	}

	/**
	 * Copy a template assets tree without following symbolic links.
	 *
	 * @param string $source Template assets path.
	 * @param string $destination Export assets path.
	 * @return bool
	 */
	private static function copy_template_directory( $source, $destination ) {
		$source_real = realpath( $source );
		if ( ! $source_real || ! is_dir( $source_real ) || ! wp_mkdir_p( $destination ) ) {
			return false;
		}
		$source_real = trailingslashit( wp_normalize_path( $source_real ) );
		$iterator    = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_real, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				continue;
			}
			$source_path = wp_normalize_path( $item->getPathname() );
			if ( 0 !== strpos( $source_path, $source_real ) ) {
				continue;
			}
			$relative = substr( $source_path, strlen( $source_real ) );
			$target   = trailingslashit( $destination ) . $relative;
			if ( $item->isDir() ) {
				if ( ! wp_mkdir_p( $target ) ) {
					return false;
				}
			} elseif ( ! wp_mkdir_p( dirname( $target ) ) || ! copy( $source_path, $target ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Copy selected WordPress uploads into the static export and return their new URLs.
	 *
	 * @param array<int, array<string, mixed>> $sections Saved sections.
	 * @param string                           $asset_dir Export assets path.
	 * @param string                           $asset_url Export assets URL.
	 * @return array<int, string>|WP_Error
	 */
	private static function copy_section_images( $sections, $asset_dir, $asset_url ) {
		$images      = array();
		$upload_info = wp_upload_dir();
		$upload_root = isset( $upload_info['basedir'] ) ? realpath( $upload_info['basedir'] ) : false;
		if ( ! $upload_root ) {
			foreach ( $sections as $section ) {
				$has_review_image = false;
				if ( ! empty( $section['reviews'] ) && is_array( $section['reviews'] ) ) {
					foreach ( $section['reviews'] as $review ) {
						if ( ! empty( $review['image_id'] ) ) {
							$has_review_image = true;
							break;
						}
					}
				}
				if ( ! empty( $section['image_id'] ) || ! empty( $section['logo_id'] ) || $has_review_image ) {
					return new WP_Error( 'hlpb_export_uploads_missing', __( 'WordPress uploads are unavailable, so the selected image cannot be included in a standalone export.', 'html-landing-page-builder-for-woocommerce' ) );
				}
			}
			return $images;
		}
		$upload_root = trailingslashit( strtolower( wp_normalize_path( $upload_root ) ) );
		foreach ( $sections as $section ) {
			$image_ids = array( isset( $section['image_id'] ) ? absint( $section['image_id'] ) : 0, isset( $section['logo_id'] ) ? absint( $section['logo_id'] ) : 0 );
			if ( ! empty( $section['reviews'] ) && is_array( $section['reviews'] ) ) {
				foreach ( $section['reviews'] as $review ) {
					$image_ids[] = isset( $review['image_id'] ) ? absint( $review['image_id'] ) : 0;
				}
			}
			foreach ( array_unique( array_filter( $image_ids ) ) as $image_id ) {
				if ( isset( $images[ $image_id ] ) ) {
					continue;
				}
			$file = get_attached_file( $image_id );
			$file = $file ? realpath( $file ) : false;
			if ( ! $file || ! is_file( $file ) ) {
				return new WP_Error( 'hlpb_export_image_missing', __( 'A selected image file could not be found. Choose the image again before generating.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$file_normalized = strtolower( wp_normalize_path( $file ) );
			if ( 0 !== strpos( $file_normalized, $upload_root ) ) {
				return new WP_Error( 'hlpb_export_image_location', __( 'Selected images must be stored in the WordPress uploads folder to include them in a standalone export.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ), true ) ) {
				return new WP_Error( 'hlpb_export_image_type', __( 'A selected image uses an unsupported file type. Use JPG, PNG, GIF, WebP, or AVIF.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$relative_name = 'uploads/landing-image-' . $image_id . '.' . $extension;
			$destination   = trailingslashit( $asset_dir ) . $relative_name;
			if ( ! wp_mkdir_p( dirname( $destination ) ) || ! copy( $file, $destination ) ) {
				return new WP_Error( 'hlpb_export_image', __( 'A selected image could not be copied into the static export.', 'html-landing-page-builder-for-woocommerce' ) );
			}
			$images[ $image_id ] = trailingslashit( $asset_url ) . $relative_name;
			}
		}
		return $images;
	}

	/**
	 * Return the configured output folder slug, falling back to the post slug.
	 *
	 * @param int $post_id Landing page ID.
	 * @return string
	 */
	private static function get_export_slug( $post_id ) {
		$slug = get_post_meta( $post_id, self::EXPORT_SLUG_KEY, true );
		if ( ! $slug ) {
			$slug = get_post_field( 'post_name', $post_id );
			if ( 'auto-draft' === $slug ) {
				$slug = '';
			}
		}
		return sanitize_title( $slug );
	}

	/**
	 * Return the top-level static output folder, defaulting to Promo.
	 *
	 * @param int $post_id Landing page ID.
	 * @return string
	 */
	private static function get_export_promo_folder( $post_id ) {
		$folder = get_post_meta( $post_id, self::EXPORT_PROMO_KEY, true );
		if ( ! is_string( $folder ) || '' === $folder ) {
			$folder = 'Promo';
		}
		$folder = trim( sanitize_file_name( $folder ) );
		return preg_match( '/^[a-z0-9-]+$/i', $folder ) ? $folder : 'Promo';
	}

	/**
	 * Build the public URL for a static export.
	 *
	 * Folder names on disk preserve their case. URLs use lowercase paths so they
	 * remain consistent with the normal WordPress permalink style.
	 *
	 * @param string $promo_folder Top-level folder name.
	 * @param string $slug Landing folder name.
	 * @return string
	 */
	private static function build_export_url( $promo_folder, $slug ) {
		return home_url( '/' . rawurlencode( strtolower( $promo_folder ) ) . '/' . rawurlencode( strtolower( $slug ) ) . '/' );
	}

	/**
	 * Normalize a relative export path and allow one legacy or two current segments.
	 *
	 * @param string $relative Relative path inside ABSPATH.
	 * @return string|false
	 */
	private static function normalize_export_relative_path( $relative ) {
		$relative = str_replace( '\\', '/', trim( (string) $relative, " \/\\\t\n\r\0\x0B" ) );
		$parts    = explode( '/', $relative );
		if ( ! in_array( count( $parts ), array( 1, 2 ), true ) ) {
			return false;
		}
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^[a-z0-9-]+$/i', $part ) ) {
				return false;
			}
		}
		return implode( '/', $parts );
	}

	/**
	 * Resolve a relative export path, rejecting symlinks and paths outside ABSPATH.
	 *
	 * @param string $relative Relative export folder.
	 * @return string|false
	 */
	private static function get_export_directory_path( $relative ) {
		$relative = self::normalize_export_relative_path( $relative );
		$root     = realpath( ABSPATH );
		if ( ! $relative || ! $root ) {
			return false;
		}
		$root = untrailingslashit( wp_normalize_path( $root ) );
		$path = $root;
		foreach ( explode( '/', $relative ) as $segment ) {
			$path .= '/' . $segment;
			if ( is_link( $path ) ) {
				return false;
			}
		}
		if ( file_exists( $path ) ) {
			$real_path = realpath( $path );
			if ( ! $real_path || ! self::path_is_inside_root( $real_path ) ) {
				return false;
			}
			return wp_normalize_path( $real_path );
		}
		$parent = realpath( dirname( $path ) );
		if ( $parent && ! self::path_is_inside_root( $parent ) ) {
			return false;
		}
		return wp_normalize_path( $path );
	}

	/**
	 * Check that a resolved path is below the real WordPress root.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private static function path_is_inside_root( $path ) {
		$root = realpath( ABSPATH );
		$path = realpath( $path );
		if ( ! $root || ! $path ) {
			return false;
		}
		$root = trailingslashit( wp_normalize_path( $root ) );
		$path = trailingslashit( wp_normalize_path( $path ) );
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$root = strtolower( $root );
			$path = strtolower( $path );
		}
		return 0 === strpos( $path, $root );
	}

	/**
	 * Check the plugin ownership marker for a generated directory.
	 *
	 * @param string $directory Export directory.
	 * @param int    $post_id Landing page ID.
	 * @return bool
	 */
	private static function export_marker_belongs_to_post( $directory, $post_id ) {
		if ( ! $directory || is_link( $directory ) || ! is_dir( $directory ) || ! self::path_is_inside_root( $directory ) ) {
			return false;
		}
		$marker_path = trailingslashit( $directory ) . '.hlpb-export.json';
		if ( is_link( $marker_path ) || ! is_file( $marker_path ) ) {
			return false;
		}
		$marker = json_decode( file_get_contents( $marker_path ), true );
		return is_array( $marker ) && absint( $marker['post_id'] ?? 0 ) === absint( $post_id );
	}

	/**
	 * Remember an export directory so it can be safely removed with its page.
	 *
	 * @param int    $post_id Landing page ID.
	 * @param string $relative Relative path to the export.
	 * @return void
	 */
	private static function add_export_history( $post_id, $relative ) {
		$relative = self::normalize_export_relative_path( $relative );
		if ( ! $relative ) {
			return;
		}
		$history = get_post_meta( $post_id, self::EXPORT_HISTORY_KEY, true );
		$history = is_array( $history ) ? $history : array();
		foreach ( $history as $existing ) {
			if ( is_string( $existing ) && 0 === strcasecmp( $existing, $relative ) ) {
				return;
			}
		}
		$history[] = $relative;
		update_post_meta( $post_id, self::EXPORT_HISTORY_KEY, array_values( $history ) );
	}

	/**
	 * Find the currently published folder, including pre-folder-setting exports.
	 *
	 * @param int $post_id Landing page ID.
	 * @return string
	 */
	private static function get_current_export_relative_path( $post_id ) {
		$candidates = array();
		$current    = get_post_meta( $post_id, self::EXPORT_DIRECTORY_KEY, true );
		if ( is_string( $current ) && '' !== $current ) {
			$candidates[] = $current;
		}
		$legacy_slug = self::get_export_slug( $post_id );
		if ( $legacy_slug ) {
			$candidates[] = $legacy_slug;
		}
		$history = get_post_meta( $post_id, self::EXPORT_HISTORY_KEY, true );
		if ( is_array( $history ) ) {
			$candidates = array_merge( $candidates, array_reverse( $history ) );
		}
		$checked = array();
		foreach ( $candidates as $candidate ) {
			$relative = self::normalize_export_relative_path( $candidate );
			if ( ! $relative || isset( $checked[ strtolower( $relative ) ] ) ) {
				continue;
			}
			$checked[ strtolower( $relative ) ] = true;
			$directory = self::get_export_directory_path( $relative );
			if ( $directory && self::export_marker_belongs_to_post( $directory, $post_id ) ) {
				return $relative;
			}
		}
		return '';
	}

	/**
	 * Check whether rewrite rules already include a configured folder route.
	 *
	 * @param string $folder Export folder.
	 * @return bool
	 */
	private static function has_export_rewrite_rule( $folder ) {
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) ) {
			return false;
		}
		$rule = '^' . strtolower( $folder ) . '/([^/]+)/?$';
		return isset( $rules[ $rule ] ) && false !== strpos( $rules[ $rule ], self::EXPORT_FOLDER_QUERY_VAR . '=' );
	}

	/**
	 * Link the landing page permalink to its generated static copy when available.
	 *
	 * @param string  $permalink Original permalink.
	 * @param WP_Post $post Landing page.
	 * @param bool    $leavename Whether to preserve the post name token.
	 * @param bool    $sample Whether this is a sample permalink.
	 * @return string
	 */
	public static function filter_landing_permalink( $permalink, $post, $leavename, $sample ) {
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return $permalink;
		}
		$export_url = get_post_meta( $post->ID, self::EXPORT_KEY, true );
		return is_string( $export_url ) && $export_url ? esc_url_raw( $export_url ) : $permalink;
	}

	/**
	 * Delete only generated directories whose ownership marker matches this post.
	 *
	 * @param int $post_id Post being trashed/deleted.
	 * @return void
	 */
	public static function delete_export_for_post( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$paths   = array( get_post_meta( $post_id, self::EXPORT_DIRECTORY_KEY, true ) );
		$history = get_post_meta( $post_id, self::EXPORT_HISTORY_KEY, true );
		if ( is_array( $history ) ) {
			$paths = array_merge( $paths, $history );
		}
		$legacy_slug = self::get_export_slug( $post_id );
		if ( $legacy_slug ) {
			$paths[] = $legacy_slug;
		}
		$removed = array();
		foreach ( $paths as $relative ) {
			$relative = self::normalize_export_relative_path( $relative );
			if ( ! $relative || isset( $removed[ strtolower( $relative ) ] ) ) {
				continue;
			}
			$removed[ strtolower( $relative ) ] = true;
			$directory = self::get_export_directory_path( $relative );
			if ( $directory && self::export_marker_belongs_to_post( $directory, $post_id ) ) {
				self::remove_export_directory_contents( $directory );
			}
		}
		delete_post_meta( $post_id, self::EXPORT_KEY );
		delete_post_meta( $post_id, self::EXPORT_DIRECTORY_KEY );
		delete_post_meta( $post_id, self::EXPORT_HISTORY_KEY );
	}

	/**
	 * Remove an owned export tree without traversing symlinks.
	 *
	 * @param string $directory Export directory.
	 * @return void
	 */
	private static function remove_export_directory_contents( $directory ) {
		if ( is_link( $directory ) || ! is_dir( $directory ) || ! self::path_is_inside_root( $directory ) ) {
			return;
		}
		$entries = scandir( $directory );
		if ( ! is_array( $entries ) ) {
			return;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = trailingslashit( $directory ) . $entry;
			if ( is_link( $path ) || is_file( $path ) ) {
				wp_delete_file( $path );
			} elseif ( is_dir( $path ) && self::path_is_inside_root( $path ) ) {
				self::remove_export_directory_contents( $path );
			}
		}
		if ( is_dir( $directory ) && ! is_link( $directory ) ) {
			rmdir( $directory );
		}
	}

	/**
	 * Republish a page after it is restored from Trash.
	 *
	 * @param int $post_id Restored post ID.
	 * @return void
	 */
	public static function restore_export_for_post( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		$result = self::generate_static_export( $post_id );
		if ( is_wp_error( $result ) ) {
			set_transient( 'hlpb_export_notice_' . get_current_user_id(), array( 'success' => false, 'message' => $result->get_error_message() ), MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Convert a manifest path into a path relative to its exported asset directory.
	 *
	 * @param array<string, mixed> $template Template manifest.
	 * @param string               $path Manifest path.
	 * @return string|false
	 */
	private static function path_from_asset_root( $template, $path ) {
		$asset_root = trailingslashit( str_replace( '\\', '/', $template['assets'] ) );
		$path       = str_replace( '\\', '/', ltrim( $path, '/' ) );
		if ( 0 !== strpos( $path, $asset_root ) ) {
			return false;
		}
		return substr( $path, strlen( $asset_root ) );
	}

	/**
	 * Serve a static page from its configured root/{folder}/{slug} directory.
	 *
	 * @return void
	 */
	public static function serve_static_export() {
		$requested = get_query_var( 'hlpb_static_export' );
		if ( ! $requested ) {
			return;
		}
		$slug        = sanitize_title( $requested );
		$folder      = get_query_var( self::EXPORT_FOLDER_QUERY_VAR );
		$folder      = is_string( $folder ) && '' !== $folder ? sanitize_file_name( $folder ) : 'Promo';
		$relative    = self::normalize_export_relative_path( $folder . '/' . $slug );
		$export_dir  = $relative ? self::get_export_directory_path( $relative ) : false;
		if ( ! $export_dir || ! is_dir( $export_dir ) ) {
			// Older plugin versions put exports directly in the WordPress root.
			$export_dir = self::get_export_directory_path( $slug );
		}
		$index_path  = $export_dir ? trailingslashit( $export_dir ) . 'index.html' : '';
		$marker_path = $export_dir ? trailingslashit( $export_dir ) . '.hlpb-export.json' : '';
		$marker      = $marker_path && is_file( $marker_path ) && ! is_link( $marker_path ) ? json_decode( file_get_contents( $marker_path ), true ) : false;
		$owner_id    = is_array( $marker ) ? absint( $marker['post_id'] ?? 0 ) : 0;
		if ( ! preg_match( '/^[a-z0-9-]+$/i', $slug ) || ! $index_path || is_link( $index_path ) || ! is_file( $index_path ) || ! $owner_id || self::POST_TYPE !== get_post_type( $owner_id ) || 'publish' !== get_post_status( $owner_id ) || ! self::path_is_inside_root( $export_dir ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			$template = get_404_template();
			if ( $template ) {
				include $template;
			}
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		readfile( $index_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile -- Serve generated static HTML from the WordPress root.
		exit;
	}

	/**
	 * Validate and normalize posted sections.
	 *
	 * @param mixed $input Submitted section data.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_sections( $input, $template_slug = 'cozy-mix' ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$allowed       = self::get_section_types();
		$icon_labels   = self::get_icon_labels();
		$sections      = array();
		$singletons    = array();

		foreach ( $input as $raw_section ) {
			if ( ! is_array( $raw_section ) ) {
				continue;
			}
			$type = isset( $raw_section['type'] ) ? sanitize_key( $raw_section['type'] ) : '';
			if ( ! isset( $allowed[ $type ] ) ) {
				continue;
			}
			if ( in_array( $type, self::get_singleton_section_types(), true ) ) {
				if ( isset( $singletons[ $type ] ) ) {
					continue;
				}
				$singletons[ $type ] = true;
			}
			$id = isset( $raw_section['id'] ) ? sanitize_key( $raw_section['id'] ) : '';
			if ( '' === $id ) {
				$id = 'section_' . count( $sections );
			}

			$section = array(
				'id'    => $id,
				'type'  => $type,
				'order' => isset( $raw_section['order'] ) ? max( 1, absint( $raw_section['order'] ) ) : count( $sections ) + 1,
			);

			if ( 'text_image' === $type ) {
				$demo             = self::get_demo_text_image( 0, $template_slug );
				$section['image_id'] = isset( $raw_section['image_id'] ) ? absint( $raw_section['image_id'] ) : 0;
				$section['demo_image'] = isset( $raw_section['demo_image'] ) ? self::safe_demo_image( sanitize_file_name( $raw_section['demo_image'] ), $template_slug ) : self::safe_demo_image( $demo['demo_image'], $template_slug );
				$section['image_alt'] = isset( $raw_section['image_alt'] ) ? sanitize_text_field( $raw_section['image_alt'] ) : $demo['image_alt'];
				$section['subtitle'] = isset( $raw_section['subtitle'] ) ? sanitize_text_field( $raw_section['subtitle'] ) : '';
				$section['title'] = isset( $raw_section['title'] ) ? sanitize_textarea_field( $raw_section['title'] ) : '';
				$section['body'] = isset( $raw_section['body'] ) ? sanitize_textarea_field( $raw_section['body'] ) : '';
				$section['image_position'] = isset( $raw_section['image_position'] ) && 'left' === $raw_section['image_position'] ? 'left' : 'right';
				$color = isset( $raw_section['color'] ) ? sanitize_hex_color( $raw_section['color'] ) : false;
				$section['color'] = $color ? $color : '#29445c';
				$section['features'] = array();
				if ( isset( $raw_section['features'] ) && is_array( $raw_section['features'] ) ) {
					foreach ( array_slice( $raw_section['features'], 0, 8 ) as $feature ) {
						if ( ! is_array( $feature ) ) {
							continue;
						}
						$icon = isset( $feature['icon'] ) ? sanitize_key( $feature['icon'] ) : 'heart';
						$section['features'][] = array(
							'icon' => isset( $icon_labels[ $icon ] ) ? $icon : 'heart',
							'text' => isset( $feature['text'] ) ? sanitize_text_field( $feature['text'] ) : '',
						);
					}
				}
			} elseif ( 'cart_section' === $type ) {
				$section = array_merge( $section, HLPB_Cart_Section::sanitize( $raw_section ) );
			} elseif ( 'header' === $type ) {
				$defaults = self::get_default_header_settings();
				$section['logo_id'] = isset( $raw_section['logo_id'] ) ? absint( $raw_section['logo_id'] ) : 0;
				foreach ( array( 'left', 'right' ) as $side ) {
					$mode = isset( $raw_section[ $side . '_menu_mode' ] ) ? sanitize_key( $raw_section[ $side . '_menu_mode' ] ) : 'custom';
					$section[ $side . '_menu_mode' ] = 'wp_menu' === $mode ? 'wp_menu' : 'custom';
					$section[ $side . '_menu_id' ] = isset( $raw_section[ $side . '_menu_id' ] ) ? absint( $raw_section[ $side . '_menu_id' ] ) : 0;
					$section[ $side . '_menu_items' ] = array();
					$items = isset( $raw_section[ $side . '_menu_items' ] ) && is_array( $raw_section[ $side . '_menu_items' ] ) ? array_slice( $raw_section[ $side . '_menu_items' ], 0, 20 ) : $defaults[ $side . '_menu_items' ];
					foreach ( $items as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
						$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
						if ( '' !== $label && '' !== $url ) {
							$section[ $side . '_menu_items' ][] = array( 'label' => $label, 'url' => $url );
						}
					}
				}
			} elseif ( 'offer_line' === $type ) {
				$defaults = self::get_default_offer_settings();
				foreach ( array( 'left', 'right' ) as $side ) {
					$section[ $side . '_text' ] = isset( $raw_section[ $side . '_text' ] ) ? sanitize_text_field( $raw_section[ $side . '_text' ] ) : $defaults[ $side . '_text' ];
					$section[ $side . '_bold' ] = ! empty( $raw_section[ $side . '_bold' ] ) ? 1 : 0;
					$color = isset( $raw_section[ $side . '_color' ] ) ? sanitize_hex_color( $raw_section[ $side . '_color' ] ) : false;
					$section[ $side . '_color' ] = $color ? $color : $defaults[ $side . '_color' ];
				}
				$background = isset( $raw_section['background'] ) ? sanitize_hex_color( $raw_section['background'] ) : false;
				$section['background'] = $background ? $background : $defaults['background'];
			} elseif ( 'mix_it' === $type ) {
				$defaults = self::get_default_mix_settings();
				$section['title'] = isset( $raw_section['title'] ) ? sanitize_text_field( $raw_section['title'] ) : $defaults['title'];
				$section['subtitle'] = isset( $raw_section['subtitle'] ) ? sanitize_text_field( $raw_section['subtitle'] ) : $defaults['subtitle'];
				foreach ( array( 'background', 'title_color', 'subtitle_color', 'benefit_title_color', 'benefit_text_color' ) as $color_key ) {
					$color = isset( $raw_section[ $color_key ] ) ? sanitize_hex_color( $raw_section[ $color_key ] ) : false;
					$section[ $color_key ] = $color ? $color : $defaults[ $color_key ];
				}
				$section['benefits'] = array();
				if ( isset( $raw_section['benefits'] ) && is_array( $raw_section['benefits'] ) ) {
					foreach ( array_slice( $raw_section['benefits'], 0, 8 ) as $benefit ) {
						if ( ! is_array( $benefit ) ) {
							continue;
						}
						$icon = isset( $benefit['icon'] ) ? sanitize_key( $benefit['icon'] ) : 'heart';
						$icon_color = isset( $benefit['icon_color'] ) ? sanitize_hex_color( $benefit['icon_color'] ) : false;
						$section['benefits'][] = array(
							'icon' => isset( $icon_labels[ $icon ] ) ? $icon : 'heart',
							'icon_color' => $icon_color ? $icon_color : '#244c59',
							'title' => isset( $benefit['title'] ) ? sanitize_text_field( $benefit['title'] ) : '',
							'text' => isset( $benefit['text'] ) ? sanitize_textarea_field( $benefit['text'] ) : '',
						);
					}
				}
			} elseif ( 'reviews' === $type ) {
				$defaults = self::get_default_reviews_settings();
				foreach ( array( 'title', 'subtitle', 'overall_score', 'overall_count' ) as $text_key ) {
					$section[ $text_key ] = isset( $raw_section[ $text_key ] ) ? sanitize_text_field( $raw_section[ $text_key ] ) : $defaults[ $text_key ];
				}
				$section['overall_stars'] = isset( $raw_section['overall_stars'] ) ? min( 5, max( 1, absint( $raw_section['overall_stars'] ) ) ) : $defaults['overall_stars'];
				foreach ( array( 'background', 'heading_color', 'subtitle_color', 'star_color', 'score_color', 'count_color', 'card_background', 'card_border_color', 'review_title_color', 'review_text_color', 'reviewer_color', 'verified_color' ) as $color_key ) {
					$color = isset( $raw_section[ $color_key ] ) ? sanitize_hex_color( $raw_section[ $color_key ] ) : false;
					$section[ $color_key ] = $color ? $color : $defaults[ $color_key ];
				}
				$raw_reviews = isset( $raw_section['reviews'] ) && is_array( $raw_section['reviews'] ) ? array_slice( $raw_section['reviews'], 0, 20 ) : ( ! empty( $raw_section['reviews_present'] ) ? array() : $defaults['reviews'] );
				$section['reviews'] = array();
				foreach ( $raw_reviews as $review_index => $raw_review ) {
					if ( ! is_array( $raw_review ) ) {
						continue;
					}
					$review_defaults = $defaults['reviews'][ $review_index % count( $defaults['reviews'] ) ];
					$demo_image = isset( $raw_review['demo_image'] ) ? sanitize_file_name( $raw_review['demo_image'] ) : $review_defaults['demo_image'];
					$section['reviews'][] = array(
						'image_id' => isset( $raw_review['image_id'] ) ? absint( $raw_review['image_id'] ) : 0,
						'demo_image' => self::safe_demo_image( $demo_image, $template_slug ),
						'image_alt' => isset( $raw_review['image_alt'] ) ? sanitize_text_field( $raw_review['image_alt'] ) : $review_defaults['image_alt'],
						'stars' => isset( $raw_review['stars'] ) ? min( 5, max( 1, absint( $raw_review['stars'] ) ) ) : $review_defaults['stars'],
						'title' => isset( $raw_review['title'] ) ? sanitize_text_field( $raw_review['title'] ) : '',
						'text' => isset( $raw_review['text'] ) ? sanitize_textarea_field( $raw_review['text'] ) : '',
						'reviewer' => isset( $raw_review['reviewer'] ) ? sanitize_text_field( $raw_review['reviewer'] ) : '',
						'verified' => ! empty( $raw_review['verified'] ) ? 1 : 0,
						'verified_text' => isset( $raw_review['verified_text'] ) ? sanitize_text_field( $raw_review['verified_text'] ) : $review_defaults['verified_text'],
					);
				}
			} elseif ( 'faq' === $type ) {
				$defaults = self::get_default_faq_settings();
				$section['title'] = isset( $raw_section['title'] ) ? sanitize_textarea_field( $raw_section['title'] ) : $defaults['title'];
				$section['subtitle'] = isset( $raw_section['subtitle'] ) ? sanitize_text_field( $raw_section['subtitle'] ) : $defaults['subtitle'];
				foreach ( array( 'background', 'heading_color', 'intro_color', 'question_color', 'answer_color', 'item_background', 'item_border_color', 'arrow_color' ) as $color_key ) {
					$color = isset( $raw_section[ $color_key ] ) ? sanitize_hex_color( $raw_section[ $color_key ] ) : false;
					$section[ $color_key ] = $color ? $color : $defaults[ $color_key ];
				}
				$raw_faqs = isset( $raw_section['faqs'] ) && is_array( $raw_section['faqs'] ) ? array_slice( $raw_section['faqs'], 0, 30 ) : ( ! empty( $raw_section['faqs_present'] ) ? array() : $defaults['faqs'] );
				$section['faqs'] = array();
				foreach ( $raw_faqs as $faq ) {
					if ( ! is_array( $faq ) ) {
						continue;
					}
					$section['faqs'][] = array(
						'question' => isset( $faq['question'] ) ? sanitize_text_field( $faq['question'] ) : '',
						'answer' => isset( $faq['answer'] ) ? sanitize_textarea_field( $faq['answer'] ) : '',
					);
				}
			} elseif ( 'footer' === $type ) {
				$defaults = self::get_default_footer_settings();
				$section['logo_id'] = isset( $raw_section['logo_id'] ) ? absint( $raw_section['logo_id'] ) : 0;
				foreach ( array( 'brand_name', 'brand_tagline', 'copyright', 'legal_note' ) as $text_key ) {
					$section[ $text_key ] = isset( $raw_section[ $text_key ] ) ? sanitize_text_field( $raw_section[ $text_key ] ) : $defaults[ $text_key ];
				}
				$section['brand_url'] = isset( $raw_section['brand_url'] ) ? self::sanitize_menu_url( $raw_section['brand_url'] ) : $defaults['brand_url'];
				$section['disclaimer'] = isset( $raw_section['disclaimer'] ) ? sanitize_textarea_field( $raw_section['disclaimer'] ) : $defaults['disclaimer'];
				foreach ( array( 'background', 'text_color', 'brand_color', 'bottom_text_color', 'disclaimer_color', 'divider_color' ) as $color_key ) {
					$color = isset( $raw_section[ $color_key ] ) ? sanitize_hex_color( $raw_section[ $color_key ] ) : false;
					$section[ $color_key ] = $color ? $color : $defaults[ $color_key ];
				}
				foreach ( array( 'primary_links', 'legal_links' ) as $group ) {
					$present_key = $group . '_present';
					$raw_items = isset( $raw_section[ $group ] ) && is_array( $raw_section[ $group ] ) ? array_slice( $raw_section[ $group ], 0, 20 ) : ( ! empty( $raw_section[ $present_key ] ) ? array() : $defaults[ $group ] );
					$section[ $group ] = array();
					foreach ( $raw_items as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
						$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
						if ( '' !== $label && '' !== $url ) {
							$section[ $group ][] = array( 'label' => $label, 'url' => $url );
						}
					}
				}
				$raw_social_links = isset( $raw_section['social_links'] ) && is_array( $raw_section['social_links'] ) ? array_slice( $raw_section['social_links'], 0, 20 ) : ( ! empty( $raw_section['social_links_present'] ) ? array() : $defaults['social_links'] );
				$social_icons = self::get_footer_social_icons();
				$section['social_links'] = array();
				foreach ( $raw_social_links as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$icon = isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : 'instagram';
					$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
					$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
					if ( isset( $social_icons[ $icon ] ) && '' !== $label && '' !== $url ) {
						$section['social_links'][] = array( 'icon' => $icon, 'label' => $label, 'url' => $url );
					}
				}
			}
			$sections[] = $section;
		}

		usort(
			$sections,
			static function ( $left, $right ) {
				return $left['order'] <=> $right['order'];
			}
		);

		foreach ( $sections as $index => $section ) {
			$sections[ $index ]['order'] = $index + 1;
		}

		return $sections;
	}

	/** Sanitize an absolute or site-relative navigation URL. */
	private static function sanitize_menu_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		return esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Enqueue the original design's styles and interaction script.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}
		$template_slug = self::get_template_slug( get_queried_object_id() );
		$template      = self::get_template_info( $template_slug );
		if ( ! $template ) {
			return;
		}
		$asset_url = self::get_template_asset_url( $template_slug );
		wp_enqueue_style( 'hlpb-landing-design', HLPB_URL . 'templates/' . $template_slug . '/' . ltrim( $template['styles'], '/' ), array(), HLPB_VERSION );
		if ( ! empty( $template['script'] ) && is_file( self::resolve_template_file( $template_slug, $template['script'] ) ) ) {
			wp_enqueue_script( 'hlpb-landing-design', HLPB_URL . 'templates/' . $template_slug . '/' . ltrim( $template['script'], '/' ), array(), HLPB_VERSION, true );
			wp_localize_script(
				'hlpb-landing-design',
				'HLPBDemo',
				array( 'assetsUrl' => $asset_url )
			);
		}
		HLPB_Cart_Section::enqueue_public_assets( get_queried_object_id() );
	}

	/**
	 * Use the builder's standalone page template.
	 *
	 * @param string $template Theme template path.
	 * @return string
	 */
	public static function select_template( $template ) {
		if ( is_singular( self::POST_TYPE ) ) {
			return HLPB_PATH . 'templates/landing-page.php';
		}
		return $template;
	}

	/**
	 * Fetch the saved section list, using the design demo for new pages.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_sections( $post_id ) {
		$sections = get_post_meta( $post_id, self::META_KEY, true );
		if ( metadata_exists( 'post', $post_id, self::META_KEY ) && is_array( $sections ) ) {
			return $sections;
		}
		return self::get_default_sections( self::get_template_slug( $post_id ) );
	}

	/**
	 * Render a saved section on the public page.
	 *
	 * @param array<string, mixed> $section Section data.
	 * @return string
	 */
	public static function render_frontend_section( $section, $template_slug = 'cozy-mix', $asset_url = '', $custom_images = array() ) {
		$type = isset( $section['type'] ) ? sanitize_key( $section['type'] ) : '';
		$asset_url = $asset_url ? trailingslashit( $asset_url ) : self::get_template_asset_url( $template_slug );
		if ( 'sprite' === $type ) {
			return self::get_demo_fragment( 'sprite', $template_slug, $asset_url );
		}
		if ( 'text_image' === $type ) {
			return self::render_text_image( $section, $template_slug, $asset_url, $custom_images );
		}
		if ( 'header' === $type ) {
			return self::render_header( $section, $template_slug, $asset_url, $custom_images );
		}
		if ( 'offer_line' === $type ) {
			return self::render_offer_line( $section );
		}
		if ( 'mix_it' === $type ) {
			return self::render_mix_it( $section );
		}
		if ( 'reviews' === $type ) {
			return self::render_reviews( $section, $template_slug, $asset_url, $custom_images );
		}
		if ( 'faq' === $type ) {
			return self::render_faq( $section );
		}
		if ( 'footer' === $type ) {
			return self::render_footer( $section, $template_slug, $asset_url, $custom_images );
		}

		$fragment_keys = array(
			'header'        => 'header',
			'offer_line'    => 'offer_line',
			'cart_section'  => 'cart_section',
			'mix_it'        => 'mix_it',
			'reviews'       => 'reviews',
			'faq'           => 'faq',
			'why'           => 'why',
			'limited_stock' => 'limited_stock',
			'footer'        => 'footer',
			'buybar'        => 'buybar',
		);
		if ( ! isset( $fragment_keys[ $type ] ) ) {
			return '';
		}
		return self::get_demo_fragment( $fragment_keys[ $type ], $template_slug, $asset_url );
	}

	/** Render the editable header and its selected menus. */
	private static function render_header( $section, $template_slug, $asset_url, $custom_images ) {
		$section = array_merge( self::get_default_header_settings(), $section );
		$logo_id = absint( $section['logo_id'] );
		$logo_url = $logo_id && isset( $custom_images[ $logo_id ] ) ? $custom_images[ $logo_id ] : false;
		if ( ! $logo_url && $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
		}
		$site_name = get_bloginfo( 'name' );
		if ( $logo_url ) {
			$alt = get_post_meta( $logo_id, '_wp_attachment_image_alt', true );
			$brand = '<img class="hlpb-custom-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $alt ? $alt : $site_name ) . '" style="max-height:44px;width:auto;object-fit:contain">';
		} else {
			$brand = '<img src="' . esc_url( $asset_url . 'icons/nest-stack.svg' ) . '" width="34" height="28" alt=""><span><b>NEST &amp; NOOK</b><small>COMFORT GOODS</small></span>';
		}
		$left_links = self::render_header_links( $section, 'left' );
		$right_links = self::render_header_links( $section, 'right' );
		return '<header class="site-header"><div class="container header-inner"><nav class="header-nav" aria-label="' . esc_attr__( 'Left navigation', 'html-landing-page-builder-for-woocommerce' ) . '">' . $left_links . '</nav><a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $site_name . ' ' . __( 'home', 'html-landing-page-builder-for-woocommerce' ) ) . '">' . $brand . '</a><nav class="header-story" aria-label="' . esc_attr__( 'Right navigation', 'html-landing-page-builder-for-woocommerce' ) . '">' . $right_links . '</nav></div></header>';
	}

	/** Render menu links from a selected WordPress menu or custom links. */
	private static function render_header_links( $section, $side ) {
		$mode = isset( $section[ $side . '_menu_mode' ] ) ? $section[ $side . '_menu_mode' ] : 'custom';
		$items = array();
		if ( 'wp_menu' === $mode && ! empty( $section[ $side . '_menu_id' ] ) ) {
			$menu_items = wp_get_nav_menu_items( absint( $section[ $side . '_menu_id' ] ) );
			if ( is_array( $menu_items ) ) {
				foreach ( $menu_items as $menu_item ) {
					$items[] = array( 'label' => $menu_item->title, 'url' => $menu_item->url );
				}
			}
		} elseif ( isset( $section[ $side . '_menu_items' ] ) && is_array( $section[ $side . '_menu_items' ] ) ) {
			$items = $section[ $side . '_menu_items' ];
		}
		$markup = '';
		foreach ( array_slice( $items, 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$markup .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
		return $markup;
	}

	/** Render the editable offer/announcement line. */
	private static function render_offer_line( $section ) {
		$section = array_merge( self::get_default_offer_settings(), $section );
		if ( 'Buy more, save more · Free UK delivery on all orders' === $section['right_text'] ) {
			$section['right_text'] = self::get_default_offer_settings()['right_text'];
		}
		$background = sanitize_hex_color( $section['background'] ) ?: '#dff1e8';
		$left_color = sanitize_hex_color( $section['left_color'] ) ?: '#376c5c';
		$right_color = sanitize_hex_color( $section['right_color'] ) ?: '#376c5c';
		$left_weight = ! empty( $section['left_bold'] ) ? '700' : '600';
		$right_weight = ! empty( $section['right_bold'] ) ? '700' : '600';
		return '<div class="announcement" style="background-color:' . esc_attr( $background ) . '"><svg class="icon leaf" aria-hidden="true"><use href="#i-leaf"></use></svg><span><b style="font-weight:' . esc_attr( $left_weight ) . ';color:' . esc_attr( $left_color ) . '">' . esc_html( $section['left_text'] ) . '</b><i>·</i><span style="font-weight:' . esc_attr( $right_weight ) . ';color:' . esc_attr( $right_color ) . '">' . esc_html( $section['right_text'] ) . '</span></span><svg class="icon leaf leaf-right" aria-hidden="true"><use href="#i-leaf"></use></svg></div>';
	}

	/** Render the editable Mix it your way benefits band. */
	private static function render_mix_it( $section ) {
		$section = array_merge( self::get_default_mix_settings(), $section );
		$background = sanitize_hex_color( $section['background'] ) ?: '#eef8f3';
		$title_color = sanitize_hex_color( $section['title_color'] ) ?: '#082b49';
		$subtitle_color = sanitize_hex_color( $section['subtitle_color'] ) ?: '#3b5264';
		$title_text_color = sanitize_hex_color( $section['benefit_title_color'] ) ?: '#082b49';
		$benefit_text_color = sanitize_hex_color( $section['benefit_text_color'] ) ?: '#4b5d6c';
		$icons = self::get_icon_labels();
		$benefits_markup = '';
		foreach ( is_array( $section['benefits'] ) ? array_slice( $section['benefits'], 0, 8 ) : array() as $benefit ) {
			if ( ! is_array( $benefit ) ) {
				continue;
			}
			$icon = isset( $benefit['icon'] ) && isset( $icons[ $benefit['icon'] ] ) ? $benefit['icon'] : 'heart';
			$icon_color = isset( $benefit['icon_color'] ) ? sanitize_hex_color( $benefit['icon_color'] ) : '#244c59';
			$benefits_markup .= '<article><span class="benefit-icon ' . esc_attr( $icon ) . '" style="color:' . esc_attr( $icon_color ? $icon_color : '#244c59' ) . '"><svg class="icon" aria-hidden="true"><use href="#i-' . esc_attr( $icon ) . '"></use></svg></span><div><b style="color:' . esc_attr( $title_text_color ) . '">' . esc_html( isset( $benefit['title'] ) ? $benefit['title'] : '' ) . '</b><small style="color:' . esc_attr( $benefit_text_color ) . '">' . nl2br( esc_html( isset( $benefit['text'] ) ? $benefit['text'] : '' ) ) . '</small></div></article>';
		}
		return '<section class="mix-band" style="background-color:' . esc_attr( $background ) . '"><div class="container"><h2 style="color:' . esc_attr( $title_color ) . '">' . esc_html( $section['title'] ) . '</h2><p style="color:' . esc_attr( $subtitle_color ) . '">' . esc_html( $section['subtitle'] ) . '</p><div class="benefits">' . $benefits_markup . '</div></div></section>';
	}

	/** Render the editable reviews section and its review cards. */
	private static function render_reviews( $section, $template_slug, $asset_url, $custom_images ) {
		$section = array_merge( self::get_default_reviews_settings(), $section );
		$colors = array();
		foreach ( array( 'background', 'heading_color', 'subtitle_color', 'star_color', 'score_color', 'count_color', 'card_background', 'card_border_color', 'review_title_color', 'review_text_color', 'reviewer_color', 'verified_color' ) as $color_key ) {
			$colors[ $color_key ] = sanitize_hex_color( $section[ $color_key ] ) ?: self::get_default_reviews_settings()[ $color_key ];
		}
		$overall_stars = min( 5, max( 1, absint( $section['overall_stars'] ) ) );
		$score_stars = str_repeat( '★', $overall_stars );
		$cards = '';
		$reviews = is_array( $section['reviews'] ) ? array_slice( $section['reviews'], 0, 20 ) : array();
		foreach ( $reviews as $index => $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}
			$review_defaults = self::get_default_reviews_settings()['reviews'][ $index % 3 ];
			$review = array_merge( $review_defaults, $review );
			$image_id = absint( $review['image_id'] );
			$image_url = $image_id && isset( $custom_images[ $image_id ] ) ? $custom_images[ $image_id ] : false;
			if ( ! $image_url && $image_id ) {
				$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			}
			if ( ! $image_url ) {
				$image_url = $asset_url . 'images/' . self::safe_demo_image( $review['demo_image'], $template_slug );
			}
			$stars = min( 5, max( 1, absint( $review['stars'] ) ) );
			$avatar_class = 'avatar-' . ( ( $index % 3 ) + 1 );
			$verified = ! empty( $review['verified'] ) && '' !== trim( $review['verified_text'] );
			$verified_markup = $verified ? '<span style="color:' . esc_attr( $colors['verified_color'] ) . '">' . esc_html( $review['verified_text'] ) . '</span>' : '';
			$cards .= '<article class="review-card" style="background-color:' . esc_attr( $colors['card_background'] ) . ';border-color:' . esc_attr( $colors['card_border_color'] ) . '"><img class="avatar ' . esc_attr( $avatar_class ) . '" src="' . esc_url( $image_url ) . '" width="184" height="152" alt="' . esc_attr( $review['image_alt'] ) . '" loading="lazy"><div><span class="stars" style="color:' . esc_attr( $colors['star_color'] ) . '" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'html-landing-page-builder-for-woocommerce' ), $stars ) ) . '">' . esc_html( str_repeat( '★', $stars ) ) . '</span><b style="color:' . esc_attr( $colors['review_title_color'] ) . '">' . esc_html( $review['title'] ) . '</b><p style="color:' . esc_attr( $colors['review_text_color'] ) . '">' . nl2br( esc_html( $review['text'] ) ) . '</p><footer style="color:' . esc_attr( $colors['reviewer_color'] ) . '">' . esc_html( $review['reviewer'] ) . $verified_markup . '</footer></div></article>';
		}
		return '<section class="reviews-section" id="reviews" style="background-color:' . esc_attr( $colors['background'] ) . '"><div class="container"><div class="reviews-heading"><div><h2 style="color:' . esc_attr( $colors['heading_color'] ) . '">' . esc_html( $section['title'] ) . '</h2><p style="color:' . esc_attr( $colors['subtitle_color'] ) . '">' . esc_html( $section['subtitle'] ) . '</p></div><div class="review-score"><span class="stars" style="color:' . esc_attr( $colors['star_color'] ) . '" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'html-landing-page-builder-for-woocommerce' ), $overall_stars ) ) . '">' . esc_html( $score_stars ) . '</span><b style="color:' . esc_attr( $colors['score_color'] ) . '">' . esc_html( $section['overall_score'] ) . '</b><small style="color:' . esc_attr( $colors['count_color'] ) . '">' . esc_html( $section['overall_count'] ) . '</small></div></div><div class="review-grid">' . $cards . '</div></div></section>';
	}

	/** Render the editable FAQ section. */
	private static function render_faq( $section ) {
		$section = array_merge( self::get_default_faq_settings(), $section );
		$defaults = self::get_default_faq_settings();
		$colors = array();
		foreach ( array( 'background', 'heading_color', 'intro_color', 'question_color', 'answer_color', 'item_background', 'item_border_color', 'arrow_color' ) as $color_key ) {
			$colors[ $color_key ] = sanitize_hex_color( $section[ $color_key ] ) ?: $defaults[ $color_key ];
		}
		$items = '';
		$faqs = is_array( $section['faqs'] ) ? array_slice( $section['faqs'], 0, 30 ) : array();
		foreach ( $faqs as $faq ) {
			if ( ! is_array( $faq ) ) {
				continue;
			}
			$question = isset( $faq['question'] ) ? $faq['question'] : '';
			$answer = isset( $faq['answer'] ) ? $faq['answer'] : '';
			$items .= '<details style="--hlpb-faq-arrow:' . esc_attr( $colors['arrow_color'] ) . ';background-color:' . esc_attr( $colors['item_background'] ) . ';border-color:' . esc_attr( $colors['item_border_color'] ) . '"><summary style="color:' . esc_attr( $colors['question_color'] ) . '">' . esc_html( $question ) . '</summary><p style="color:' . esc_attr( $colors['answer_color'] ) . '">' . nl2br( esc_html( $answer ) ) . '</p></details>';
		}
		return '<section class="faq-section container" id="faqs" style="background-color:' . esc_attr( $colors['background'] ) . '"><div class="faq-intro"><h2 style="color:' . esc_attr( $colors['heading_color'] ) . '">' . nl2br( esc_html( $section['title'] ) ) . '</h2><p style="color:' . esc_attr( $colors['intro_color'] ) . '">' . esc_html( $section['subtitle'] ) . '</p></div><div class="faq-list">' . $items . '</div></section>';
	}

	/** Render the editable footer section. */
	private static function render_footer( $section, $template_slug, $asset_url, $custom_images = array() ) {
		$defaults = self::get_default_footer_settings();
		$section = array_merge( $defaults, $section );
		$colors = array();
		foreach ( array( 'background', 'text_color', 'brand_color', 'bottom_text_color', 'disclaimer_color', 'divider_color' ) as $color_key ) {
			$colors[ $color_key ] = sanitize_hex_color( $section[ $color_key ] ) ?: $defaults[ $color_key ];
		}

		$logo_id = absint( $section['logo_id'] );
		$logo_url = $logo_id && isset( $custom_images[ $logo_id ] ) ? $custom_images[ $logo_id ] : false;
		if ( ! $logo_url && $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
		}
		$brand_url = self::sanitize_menu_url( $section['brand_url'] );
		$brand_url = $brand_url ? $brand_url : '#top';
		$brand_name = sanitize_text_field( $section['brand_name'] );
		$brand_tagline = sanitize_text_field( $section['brand_tagline'] );
		if ( $logo_url ) {
			$logo_alt = get_post_meta( $logo_id, '_wp_attachment_image_alt', true );
			$brand = '<a class="brand footer-brand" href="' . esc_url( $brand_url ) . '" style="color:' . esc_attr( $colors['brand_color'] ) . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_alt ? $logo_alt : $brand_name ) . '" loading="lazy"><span><b>' . esc_html( $brand_name ) . '</b><small>' . esc_html( $brand_tagline ) . '</small></span></a>';
		} else {
			$icon_url = $asset_url . 'icons/nest-stack.svg';
			$brand = '<a class="brand footer-brand" href="' . esc_url( $brand_url ) . '" style="color:' . esc_attr( $colors['brand_color'] ) . '"><img src="' . esc_url( $icon_url ) . '" width="34" height="28" alt=""><span><b>' . esc_html( $brand_name ) . '</b><small>' . esc_html( $brand_tagline ) . '</small></span></a>';
		}

		$primary_markup = '';
		$primary_links = is_array( $section['primary_links'] ) ? array_slice( $section['primary_links'], 0, 20 ) : array();
		foreach ( $primary_links as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
			$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
			if ( '' !== $label && '' !== $url ) {
				$primary_markup .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
		}

		$social_markup = '';
		$social_links = is_array( $section['social_links'] ) ? array_slice( $section['social_links'], 0, 20 ) : array();
		$social_icons = self::get_footer_social_icons();
		foreach ( $social_links as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$icon = isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : '';
			$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
			$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
			if ( ! isset( $social_icons[ $icon ] ) || '' === $label || '' === $url ) {
				continue;
			}
			if ( in_array( $icon, array( 'instagram', 'facebook', 'pinterest' ), true ) ) {
				$icon_markup = '<svg class="icon" aria-hidden="true"><use href="#i-' . esc_attr( $icon ) . '"></use></svg>';
			} else {
				$icon_markup = 'tiktok' === $icon ? '♪' : '▶';
			}
			$social_markup .= '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label ) . '" style="color:' . esc_attr( $colors['text_color'] ) . '">' . $icon_markup . '</a>';
		}

		$legal_markup = '';
		$legal_links = is_array( $section['legal_links'] ) ? array_slice( $section['legal_links'], 0, 20 ) : array();
		foreach ( $legal_links as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
			$url = isset( $item['url'] ) ? self::sanitize_menu_url( $item['url'] ) : '';
			if ( '' !== $label && '' !== $url ) {
				$legal_markup .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		$legal_note = sanitize_text_field( $section['legal_note'] );
		if ( '' !== $legal_note ) {
			$legal_markup .= '<span>' . esc_html( $legal_note ) . '</span>';
		}

		$copyright = sanitize_text_field( $section['copyright'] );
		$disclaimer = sanitize_textarea_field( $section['disclaimer'] );
		return '<footer class="site-footer" id="contact" style="background-color:' . esc_attr( $colors['background'] ) . ';color:' . esc_attr( $colors['text_color'] ) . '"><div class="container footer-main">' . $brand . '<nav>' . $primary_markup . '</nav><div class="socials" aria-label="' . esc_attr__( 'Social media', 'html-landing-page-builder-for-woocommerce' ) . '">' . $social_markup . '</div></div><div class="container footer-bottom" style="color:' . esc_attr( $colors['bottom_text_color'] ) . ';border-top-color:' . esc_attr( $colors['divider_color'] ) . '"><span>' . esc_html( $copyright ) . '</span><nav>' . $legal_markup . '</nav></div><div class="container demo-disclaimer" style="color:' . esc_attr( $colors['disclaimer_color'] ) . '">' . nl2br( esc_html( $disclaimer ) ) . '</div></footer>';
	}

	/**
	 * Render an editable Text + Image section.
	 *
	 * @param array<string, mixed> $section Section data.
	 * @return string
	 */
	private static function render_text_image( $section, $template_slug, $asset_url, $custom_images = array() ) {
		$defaults = self::get_demo_text_image();
		$section  = array_merge( $defaults, $section );
		$position = 'left' === $section['image_position'] ? 'left' : 'right';
		$image_id = isset( $section['image_id'] ) ? absint( $section['image_id'] ) : 0;
		$image    = $image_id && isset( $custom_images[ $image_id ] ) ? $custom_images[ $image_id ] : false;
		if ( ! $image && $image_id ) {
			$image = wp_get_attachment_image_url( $image_id, 'large' );
		}
		$demo_image = self::safe_demo_image( $section['demo_image'], $template_slug );
		$image      = $image ? $image : $asset_url . 'images/' . $demo_image;
		$alt      = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : $section['image_alt'];
		$color    = sanitize_hex_color( $section['color'] );
		$color    = $color ? $color : '#29445c';
		$features_markup = '';
		$features = is_array( $section['features'] ) ? array_slice( $section['features'], 0, 8 ) : array();
		foreach ( $features as $feature ) {
			$icon = isset( $feature['icon'] ) && isset( self::get_icon_labels()[ $feature['icon'] ] ) ? $feature['icon'] : 'heart';
			$text = isset( $feature['text'] ) ? $feature['text'] : '';
			$features_markup .= '<article><svg class="icon" aria-hidden="true"><use href="#i-' . esc_attr( $icon ) . '"></use></svg><span>' . nl2br( esc_html( $text ) ) . '</span></article>';
		}
		$template  = self::get_template_info( $template_slug );
		$partial    = $template && ! empty( $template['text_image_template'] ) ? self::resolve_template_file( $template_slug, $template['text_image_template'] ) : false;
		$markup     = $partial && is_file( $partial ) ? file_get_contents( $partial ) : '';
		if ( '' === $markup ) {
			return '';
		}
		$image_class = ( false !== strpos( $demo_image, 'gift-box' ) && ! $image_id ) ? ' gift-story' : '';
		$replacements = array(
			'{{image_position_class}}' => 'left' === $position ? ' reverse' : '',
			'{{image_class}}'          => $image_class,
			'{{text_color}}'           => esc_attr( $color ),
			'{{subtitle}}'             => esc_html( $section['subtitle'] ),
			'{{title}}'                => nl2br( esc_html( $section['title'] ) ),
			'{{body}}'                 => nl2br( esc_html( $section['body'] ) ),
			'{{features}}'             => $features_markup,
			'{{image_url}}'            => esc_url( $image ),
			'{{image_alt}}'            => esc_attr( $alt ),
		);
		return strtr( $markup, $replacements );
	}

	/**
	 * Return a section fragment from the supplied static design.
	 *
	 * @param string $key Section name.
	 * @return string
	 */
	private static function get_demo_fragment( $key, $template_slug = 'cozy-mix', $asset_url = '' ) {
		$template = self::get_template_info( $template_slug );
		if ( ! $template || empty( $template['sections'][ $key ] ) ) {
			return '';
		}
		$asset_url = $asset_url ? trailingslashit( $asset_url ) : self::get_template_asset_url( $template_slug );
		$cache_key = $template_slug . '|' . $key . '|' . $asset_url;
		static $cache = array();
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}
		$html    = file_exists( $template['source_path'] ) ? file_get_contents( $template['source_path'] ) : '';
		$pattern = $template['sections'][ $key ];
		$fragment = preg_match( $pattern, $html, $matches ) ? $matches[0] : '';
		$fragment = str_replace( 'assets/images/', $asset_url . 'images/', $fragment );
		$fragment = str_replace( 'assets/icons/', $asset_url . 'icons/', $fragment );
		$cache[ $cache_key ] = $fragment;
		return $fragment;
	}

	/**
	 * Return the demo mobile cart bar and status message.
	 *
	 * @return string
	 */
	public static function render_demo_buybar( $template_slug = 'cozy-mix', $asset_url = '' ) {
		return self::get_demo_fragment( 'buybar', $template_slug, $asset_url );
	}

	/**
	 * Restrict demo image names to files shipped in the plugin.
	 *
	 * @param string $filename Candidate image basename.
	 * @return string
	 */
	private static function safe_demo_image( $filename, $template_slug = 'cozy-mix' ) {
		$filename = sanitize_file_name( $filename );
		$template = self::get_template_info( $template_slug );
		$images   = $template ? trailingslashit( $template['assets'] ) . 'images/' : 'assets/images/';
		$path     = self::resolve_template_file( $template_slug, $images . $filename );
		if ( $filename && $path && file_exists( $path ) ) {
			return $filename;
		}
		$fallback = $template && ! empty( $template['default_image'] ) ? sanitize_file_name( $template['default_image'] ) : '';
		$path     = $fallback ? self::resolve_template_file( $template_slug, $images . $fallback ) : false;
		if ( $fallback && $path && file_exists( $path ) ) {
			return $fallback;
		}
		$files = glob( HLPB_PATH . 'templates/' . sanitize_key( $template_slug ) . '/' . $images . '*.{jpg,jpeg,png,webp}', GLOB_BRACE );
		return $files ? basename( $files[0] ) : '';
	}
}
