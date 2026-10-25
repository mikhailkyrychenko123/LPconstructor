<?php
/**
 * Landing-page cart shortcode and shortcode builder screen.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Shortcodes {
	const PAGE_SLUG = 'hlpb-shortcodes';
	const TAG       = 'hlpb_cart';

	/** Register the shortcode and its admin screen. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_shortcode( self::TAG, array( __CLASS__, 'render_cart' ) );
	}

	/** Add the shortcode builder under Landing Pages. */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE,
			__( 'Shortcodes', 'html-landing-page-builder-for-woocommerce' ),
			__( 'Shortcodes', 'html-landing-page-builder-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/** Show published landings that have a configured Cart Section. */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$landings = get_posts(
			array(
				'post_type'      => HLPB_Landing_Builder::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$eligible = array();
		foreach ( $landings as $landing ) {
			$sections = HLPB_Landing_Builder::get_sections( $landing->ID );
			foreach ( $sections as $section ) {
				if ( is_array( $section ) && isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
					$eligible[] = $landing;
					break;
				}
			}
		}

		echo '<div class="wrap hlpb-shortcode-admin"><h1>' . esc_html__( 'Cart Section Shortcodes', 'html-landing-page-builder-for-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'Create a shortcode that displays only the Cart Section from a published landing page. Product choices, offer tabs, prices, gifts, and styling come from that landing page.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		if ( ! $eligible ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No published landing pages with a Cart Section were found. Publish a landing page first.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div></div>';
			return;
		}

		echo '<div class="card" style="max-width:820px;padding:24px">';
		echo '<h2 style="margin-top:0">' . esc_html__( 'Build your shortcode', 'html-landing-page-builder-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody><tr><th scope="row"><label for="hlpb-shortcode-landing">' . esc_html__( 'Landing page', 'html-landing-page-builder-for-woocommerce' ) . '</label></th><td><select id="hlpb-shortcode-landing">';
		foreach ( $eligible as $landing ) {
			$label = sprintf( '%1$s (ID: %2$d)', get_the_title( $landing ), absint( $landing->ID ) );
			echo '<option value="' . esc_attr( (string) absint( $landing->ID ) ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Only published landing pages with a Cart Section are listed.', 'html-landing-page-builder-for-woocommerce' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="hlpb-shortcode-gallery">' . esc_html__( 'Left product gallery', 'html-landing-page-builder-for-woocommerce' ) . '</label></th><td><select id="hlpb-shortcode-gallery"><option value="yes">' . esc_html__( 'Show slider and thumbnails', 'html-landing-page-builder-for-woocommerce' ) . '</option><option value="no">' . esc_html__( 'Hide gallery and use full width', 'html-landing-page-builder-for-woocommerce' ) . '</option></select><p class="description">' . esc_html__( 'The gallery option controls the left-side slider shown in the landing page design.', 'html-landing-page-builder-for-woocommerce' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="hlpb-shortcode-value">' . esc_html__( 'Shortcode', 'html-landing-page-builder-for-woocommerce' ) . '</label></th><td><input id="hlpb-shortcode-value" class="large-text code" type="text" readonly value="" /><p><button type="button" class="button button-primary" id="hlpb-shortcode-copy">' . esc_html__( 'Copy shortcode', 'html-landing-page-builder-for-woocommerce' ) . '</button> <span id="hlpb-shortcode-copy-status" aria-live="polite"></span></p></td></tr></tbody></table>';
		echo '<hr><h3>' . esc_html__( 'Use it in Elementor', 'html-landing-page-builder-for-woocommerce' ) . '</h3><p>' . esc_html__( 'Add Elementor’s Shortcode widget to the page, then paste the copied code. The shortcode renders one offer/cart section and uses the selected landing page’s configuration.', 'html-landing-page-builder-for-woocommerce' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Use one Cart Section shortcode per page because the interactive cart uses unique element IDs.', 'html-landing-page-builder-for-woocommerce' ) . '</p></div></div>';
		?>
		<script>
		(function () {
			var landing = document.getElementById('hlpb-shortcode-landing');
			var gallery = document.getElementById('hlpb-shortcode-gallery');
			var output = document.getElementById('hlpb-shortcode-value');
			var button = document.getElementById('hlpb-shortcode-copy');
			var status = document.getElementById('hlpb-shortcode-copy-status');
			function update() {
				if (landing && gallery && output) output.value = '[hlpb_cart landing="' + landing.value + '" gallery="' + gallery.value + '"]';
			}
			if (landing) landing.addEventListener('change', update);
			if (gallery) gallery.addEventListener('change', update);
			if (button) button.addEventListener('click', function () {
				if (!output) return;
				output.focus(); output.select();
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(output.value).then(function () { if (status) status.textContent = '<?php echo esc_js( __( 'Copied.', 'html-landing-page-builder-for-woocommerce' ) ); ?>'; });
				} else {
					document.execCommand('copy');
					if (status) status.textContent = '<?php echo esc_js( __( 'Copied.', 'html-landing-page-builder-for-woocommerce' ) ); ?>';
				}
			});
			update();
		}());
		</script>
		<?php
	}

	/** Render a single landing's Cart Section as a portable shortcode. */
	public static function render_cart( $attributes ) {
		$attributes = shortcode_atts( array( 'landing' => 0, 'gallery' => 'yes' ), $attributes, self::TAG );
		$landing_id = absint( $attributes['landing'] );
		if ( ! $landing_id || HLPB_Landing_Builder::POST_TYPE !== get_post_type( $landing_id ) || 'publish' !== get_post_status( $landing_id ) ) {
			return self::shortcode_error( __( 'The selected landing page is unavailable.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$sections = HLPB_Landing_Builder::get_sections( $landing_id );
		$cart_section = false;
		foreach ( $sections as $section ) {
			if ( is_array( $section ) && isset( $section['type'] ) && 'cart_section' === $section['type'] ) {
				$cart_section = $section;
				break;
			}
		}
		if ( ! $cart_section ) {
			return self::shortcode_error( __( 'This landing page does not have a Cart Section.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$config = HLPB_Cart_Section::build_public_config( $landing_id, $sections );
		if ( empty( $config ) ) {
			return self::shortcode_error( __( 'The Cart Section could not be loaded. Check that WooCommerce is active.', 'html-landing-page-builder-for-woocommerce' ) );
		}
		if ( empty( $config['active'] ) ) {
			return self::shortcode_error( __( 'Enable and configure at least one offer in this landing page’s Cart Section before using its shortcode.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$template_slug = HLPB_Landing_Builder::get_template_slug( $landing_id );
		$asset_url = HLPB_Landing_Builder::get_template_asset_url( $template_slug );
		$markup = HLPB_Landing_Builder::render_frontend_section( $cart_section, $template_slug, $asset_url );
		$sprite = HLPB_Landing_Builder::render_frontend_section( array( 'type' => 'sprite' ), $template_slug, $asset_url );
		if ( '' === $markup ) {
			return self::shortcode_error( __( 'The Cart Section template markup could not be found.', 'html-landing-page-builder-for-woocommerce' ) );
		}

		$gallery = in_array( strtolower( (string) $attributes['gallery'] ), array( '1', 'true', 'yes', 'on' ), true );
		$wrapper_class = 'hlpb-cart-shortcode' . ( $gallery ? '' : ' hlpb-cart-shortcode--no-gallery' );
		$styles = self::build_shortcode_styles( $template_slug );
		self::enqueue_cart_script( $config );

		return '<div class="' . esc_attr( $wrapper_class ) . '"><style>' . $styles . '</style>' . $sprite . $markup . '</div>';
	}

	/** Add the cart runtime and its landing-specific config to the page footer. */
	private static function enqueue_cart_script( $config ) {
		$handle = 'hlpb-cart-shortcode';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, HLPB_LANDING_PAGE_BUILDER_URL . 'assets/frontend/cart-section.js', array(), HLPB_LANDING_PAGE_BUILDER_VERSION, true );
		}
		wp_enqueue_script( $handle );
		$json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( is_string( $json ) ) {
			wp_add_inline_script( $handle, 'window.HLPBCartData=' . $json . ';', 'before' );
		}
	}

	/** Build a locally scoped copy of the selected template and cart styles. */
	private static function build_shortcode_styles( $template_slug ) {
		static $cache = array();
		$template_slug = sanitize_key( $template_slug );
		if ( isset( $cache[ $template_slug ] ) ) {
			return $cache[ $template_slug ];
		}

		$css = HLPB_Landing_Builder::get_template_stylesheet_content( $template_slug );
		$scoped = self::scope_css_rules( $css, '.hlpb-cart-shortcode' );
		$cart_css_path = HLPB_LANDING_PAGE_BUILDER_PATH . 'assets/frontend/cart-section.css';
		$cart_css = is_readable( $cart_css_path ) ? file_get_contents( $cart_css_path ) : '';
		$scoped .= is_string( $cart_css ) ? $cart_css : '';
		$scoped .= '.hlpb-cart-shortcode--no-gallery #shop,.hlpb-cart-shortcode--no-gallery #shop .hero-grid{grid-template-columns:minmax(0,1fr)!important}.hlpb-cart-shortcode--no-gallery #shop>.gallery,.hlpb-cart-shortcode--no-gallery #shop .hero-grid>.gallery{display:none!important}.hlpb-cart-shortcode--no-gallery #shop .buy-panel{grid-column:1/-1;width:100%;max-width:none}';
		$cache[ $template_slug ] = $scoped;
		return $scoped;
	}

	/** Prefix selectors while preserving nested media rules and declaration at-rules. */
	private static function scope_css_rules( $css, $scope ) {
		$css = (string) $css;
		$output = '';
		$length = strlen( $css );
		$position = 0;
		while ( $position < $length ) {
			$delimiter = self::find_css_delimiter( $css, $position );
			if ( false === $delimiter ) {
				$output .= substr( $css, $position );
				break;
			}
			$prelude = substr( $css, $position, $delimiter - $position );
			$kind = $css[ $delimiter ];
			if ( ';' === $kind ) {
				$output .= $prelude . ';';
				$position = $delimiter + 1;
				continue;
			}
			$close = self::find_css_block_end( $css, $delimiter );
			if ( false === $close ) {
				$output .= substr( $css, $position );
				break;
			}
			$trimmed = trim( $prelude );
			$body = substr( $css, $delimiter + 1, $close - $delimiter - 1 );
			if ( preg_match( '/^@(media|supports|container|layer|document|scope)\b/i', $trimmed ) ) {
				$output .= $prelude . '{' . self::scope_css_rules( $body, $scope ) . '}';
			} elseif ( isset( $trimmed[0] ) && '@' === $trimmed[0] ) {
				$output .= $prelude . '{' . $body . '}';
			} else {
				$output .= self::scope_css_selectors( $prelude, $scope ) . '{' . $body . '}';
			}
			$position = $close + 1;
		}
		return $output;
	}

	/** Find the next top-level CSS opening brace or semicolon. */
	private static function find_css_delimiter( $css, $start ) {
		$length = strlen( $css );
		$quote = '';
		$comment = false;
		$paren = 0;
		for ( $index = $start; $index < $length; $index++ ) {
			$char = $css[ $index ];
			$next = $index + 1 < $length ? $css[ $index + 1 ] : '';
			if ( $comment ) {
				if ( '*' === $char && '/' === $next ) { $comment = false; $index++; }
				continue;
			}
			if ( $quote ) {
				if ( '\\' === $char ) { $index++; continue; }
				if ( $quote === $char ) { $quote = ''; }
				continue;
			}
			if ( '/' === $char && '*' === $next ) { $comment = true; $index++; continue; }
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '(' === $char || '[' === $char ) { $paren++; continue; }
			if ( ')' === $char || ']' === $char ) { $paren = max( 0, $paren - 1 ); continue; }
			if ( 0 === $paren && ( '{' === $char || ';' === $char ) ) { return $index; }
		}
		return false;
	}

	/** Find the matching closing brace for a CSS rule. */
	private static function find_css_block_end( $css, $open ) {
		$length = strlen( $css );
		$depth = 1;
		$quote = '';
		$comment = false;
		for ( $index = $open + 1; $index < $length; $index++ ) {
			$char = $css[ $index ];
			$next = $index + 1 < $length ? $css[ $index + 1 ] : '';
			if ( $comment ) {
				if ( '*' === $char && '/' === $next ) { $comment = false; $index++; }
				continue;
			}
			if ( $quote ) {
				if ( '\\' === $char ) { $index++; continue; }
				if ( $quote === $char ) { $quote = ''; }
				continue;
			}
			if ( '/' === $char && '*' === $next ) { $comment = true; $index++; continue; }
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '{' === $char ) { $depth++; }
			if ( '}' === $char && 0 === --$depth ) { return $index; }
		}
		return false;
	}

	/** Scope each selector in a comma-separated CSS selector list. */
	private static function scope_css_selectors( $prelude, $scope ) {
		$selectors = array();
		$start = 0;
		$depth = 0;
		$quote = '';
		$length = strlen( $prelude );
		for ( $index = 0; $index < $length; $index++ ) {
			$char = $prelude[ $index ];
			if ( $quote ) {
				if ( '\\' === $char ) { $index++; continue; }
				if ( $quote === $char ) { $quote = ''; }
				continue;
			}
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '(' === $char || '[' === $char ) { $depth++; continue; }
			if ( ')' === $char || ']' === $char ) { $depth = max( 0, $depth - 1 ); continue; }
			if ( ',' === $char && 0 === $depth ) {
				$selectors[] = trim( substr( $prelude, $start, $index - $start ) );
				$start = $index + 1;
			}
		}
		$selectors[] = trim( substr( $prelude, $start ) );
		$scoped = array();
		foreach ( $selectors as $selector ) {
			if ( '' === $selector ) { continue; }
			// Standalone landing styles use this body marker; the shortcode wrapper replaces it.
			$selector = preg_replace( '/(^|[\s>+~])\.hlpb-page(?=$|[.#:\[\s>+~])/i', '$1', $selector );
			$selector = trim( $selector );
			if ( '' === $selector ) { $selector = '*'; }
			if ( preg_match( '/^(?:html|body|:root)(?=$|[.#:\[\s>+~])/i', $selector, $match ) ) {
				$selector = $scope . substr( $selector, strlen( $match[0] ) );
			} elseif ( '*' === $selector ) {
				$selector = $scope . ',' . $scope . ' *';
			} else {
				$selector = $scope . ' ' . $selector;
			}
			$scoped[] = $selector;
		}
		return implode( ',', $scoped );
	}

	/** Return an error only to editors; front-end visitors see no broken shortcode. */
	private static function shortcode_error( $message ) {
		return current_user_can( 'edit_pages' ) ? '<p class="hlpb-shortcode-error">' . esc_html( $message ) . '</p>' : '';
	}
}
