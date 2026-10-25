<?php
/**
 * Guided documentation screen for the landing page builder.
 *
 * @package HTMLLandingPageBuilderForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HLPB_Instructions {
	const PAGE_SLUG = 'hlpb-instructions';

	/** Register the guide page and its small, page-specific assets. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/** Add the user guide below the Landing Pages menu. */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE,
			'Instructions',
			'Instructions',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** Load guide styling and search only on this page. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'hlpb-instructions', HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/instructions.css', array(), HLPB_LANDING_PAGE_BUILDER_VERSION );
		wp_enqueue_script( 'hlpb-instructions', HLPB_LANDING_PAGE_BUILDER_URL . 'assets/admin/instructions.js', array(), HLPB_LANDING_PAGE_BUILDER_VERSION, true );
	}

	/** Render the searchable, section-linked help centre. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$landing_url = admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE );
		$import_url  = admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE . '&page=hlpb-demo-import' );
		$stock_url   = admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE . '&page=hlpb-stock-notifications' );
		$analytics_url = admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE . '&page=hlpb-offer-analytics' );
		$shortcodes_url = admin_url( 'edit.php?post_type=' . HLPB_Landing_Builder::POST_TYPE . '&page=hlpb-shortcodes' );

		?>
		<div class="wrap hlpb-guide">
			<header class="hlpb-guide-hero">
				<div class="hlpb-guide-hero-copy">
					<p class="hlpb-guide-kicker">BUYGET · HELP CENTRE</p>
					<h1>Landing Page Guide</h1>
					<p class="hlpb-guide-lead">A practical guide to importing demo content, building landing pages, configuring offers, managing stock notifications, and understanding SEO and analytics. Search for a topic or choose one from the contents.</p>
					<div class="hlpb-guide-hero-links">
						<a class="button button-primary" href="<?php echo esc_url( $landing_url ); ?>">Open landing pages</a>
						<a class="button" href="<?php echo esc_url( $import_url ); ?>">Import demo data</a>
					</div>
				</div>
				<div class="hlpb-guide-hero-aside" aria-label="Recommended workflow">
					<span class="hlpb-guide-step-number">01—04</span>
					<strong>Import → configure → publish → measure</strong>
					<span>Each section below points to a specific task in your dashboard.</span>
				</div>
			</header>

			<div class="hlpb-guide-toolbar">
				<label class="screen-reader-text" for="hlpb-guide-search">Search the guide</label>
				<span class="hlpb-guide-search-icon" aria-hidden="true">⌕</span>
				<input id="hlpb-guide-search" type="search" placeholder="Find a topic: stock, coupon, SEO…" autocomplete="off">
				<span class="hlpb-guide-search-hint">Enter a word or phrase</span>
			</div>
			<p class="hlpb-guide-search-empty" hidden>No sections match your search. Try another word.</p>

			<div class="hlpb-guide-layout">
				<nav class="hlpb-guide-nav" aria-label="Guide sections">
					<p class="hlpb-guide-nav-title">Contents</p>
					<a href="#start"><span>01</span> Quick start</a>
					<a href="#templates"><span>02</span> Templates &amp; sections</a>
					<a href="#offers"><span>03</span> Cart &amp; offers</a>
					<a href="#stock"><span>04</span> Variations &amp; stock</a>
					<a href="#waitlist"><span>05</span> Back-in-stock sign-ups</a>
					<a href="#checkout"><span>06</span> Cart &amp; checkout</a>
					<a href="#shortcodes"><span>07</span> Shortcodes &amp; Elementor</a>
					<a href="#analytics"><span>08</span> Analytics &amp; export</a>
					<a href="#seo"><span>09</span> SEO settings</a>
					<a href="#about"><span>10</span> About the developer</a>
					<div class="hlpb-guide-nav-footer"><a href="<?php echo esc_url( $shortcodes_url ); ?>">Open shortcodes ↗</a><a href="<?php echo esc_url( $stock_url ); ?>">Stock sign-ups ↗</a><a href="<?php echo esc_url( $analytics_url ); ?>">Analytics ↗</a></div>
				</nav>

				<main class="hlpb-guide-content">
					<section class="hlpb-guide-section" id="start" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">01</span><div><p class="hlpb-guide-eyebrow">BEFORE YOU BEGIN</p><h2>Quick start</h2></div></div>
						<p class="hlpb-guide-intro">The demo setup is the easiest way to get started. It adds sample WooCommerce products and attributes, then creates two ready-to-edit landing pages.</p>
						<div class="hlpb-guide-steps">
							<article><span>1</span><div><h3>Import the demo content</h3><p>Open <a href="<?php echo esc_url( $import_url ); ?>">Landing Pages → Import</a>. The importer creates three dedicated demo products with variations and galleries, the Color and Size attributes, and the pages <code>/promo/lp-demo/</code> and <code>/promo/field-notes-demo/</code>.</p></div></article>
							<article><span>2</span><div><h3>Open a landing page in the editor</h3><p>Under <strong>Landing Pages</strong>, select a page or click <strong>Create Landing Page</strong>. Set its title, choose a template, then review the products and offer in the cart section.</p></div></article>
							<article><span>3</span><div><h3>Save and check the published page</h3><p>Click <strong>Publish</strong> or <strong>Update</strong>. The plugin generates a static page in the site folder defined by <strong>Promo folder</strong> and <strong>Landing folder</strong>. The editor shows the published link beside the export path.</p></div></article>
						</div>
						<div class="hlpb-guide-callout hlpb-guide-callout-note"><strong>Re-running the importer</strong><p>The importer is designed to be run again: products and attributes marked as demo content are refreshed. Existing landing page sections are kept when the template and structure match the demo; if the structure differs, the demo page is restored to its original template structure.</p></div>
						<div class="hlpb-guide-screen-demo" aria-label="Landing page editor diagram">
							<div class="hlpb-guide-screen-top"><span>LANDING PAGE EDITOR</span><span>Publishing &amp; structure</span></div>
							<div class="hlpb-guide-screen-row"><b>Landing page template</b><span>Nest &amp; Nook — Cozy Mix</span></div>
							<div class="hlpb-guide-screen-row"><b>Promo folder / Landing folder</b><span>promo / lp-demo</span></div>
							<div class="hlpb-guide-screen-sections"><span>01 · Header</span><span>02 · Cart Section</span><span>03 · Text + Image</span><span>04 · SEO settings</span></div>
							<p>DIAGRAM: choose a design, configure sections, then update the page</p>
						</div>
					</section>

					<section class="hlpb-guide-section" id="templates" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">02</span><div><p class="hlpb-guide-eyebrow">PAGE STRUCTURE</p><h2>Templates &amp; sections</h2></div></div>
						<p class="hlpb-guide-intro">Each template has its own design and available section types. The <strong>Add section</strong> list automatically reflects the selected template.</p>
						<div class="hlpb-guide-two-col">
							<article class="hlpb-guide-card"><h3>Nest &amp; Nook — Cozy Mix</h3><p>A product page with offer tabs, mix-and-match choices, image-and-text sections, the Cozy Calculator, reviews, FAQs, a benefits section, and a footer.</p><p>Image-and-text sections can be repeated. Change their image, caption, text colour, and whether the image appears on the left or right.</p></article>
							<article class="hlpb-guide-card"><h3>Field Notes — The Everyday Edit</h3><p>A dedicated apparel design with an announcement, header, Shop &amp; offer calculator, winter gift section, The edit, editorial stories, interactive field guide, bundle offers, reviews, FAQs, and a footer.</p><p>For the calculator, edit the left panel—its copy and image—and choose which mini-calculators appear. The right panel contains the interactive calculator itself.</p></article>
						</div>
						<div class="hlpb-guide-feature-list">
							<div><b>Reorder</b><span>Drag a section by its handle or enter its position in the Order field.</span></div>
							<div><b>Visibility</b><span>Click the eye button in a section header to hide it on the published page. Its fields are preserved; click the eye again to show it.</span></div>
							<div><b>Duplicate</b><span>The Duplicate button makes a copy of a section. It is disabled for sections that must be unique.</span></div>
							<div><b>Save</b><span>Click Update after editing. Changes appear on the live static page after publishing or updating; they are not published while you edit.</span></div>
						</div>
						<div class="hlpb-guide-callout hlpb-guide-callout-warning"><strong>When changing a template</strong><p>Sections and fields are template-specific. Review the section list and preview before saving. Do not assume that copy and settings from one design will automatically convert to their equivalents in another.</p></div>
					</section>

					<section class="hlpb-guide-section" id="offers" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">03</span><div><p class="hlpb-guide-eyebrow">SELLING WITH OFFERS</p><h2>Cart &amp; offers</h2></div></div>
						<p class="hlpb-guide-intro">The Cart Section controls how offers look and work. Settings belong to a specific landing page and tab; they are not shared automatically across all pages.</p>
						<div class="hlpb-guide-two-col">
							<article class="hlpb-guide-card"><h3>Three cart modes</h3><ul><li><strong>Buy More</strong> — multiple units of a selected product.</li><li><strong>Mix &amp; Match</strong> — choose products to make a mix.</li><li><strong>Cozy Bundle</strong> — a set of specified products.</li></ul><p>For each mode, configure its tab, products, quantity tiers, and discount or gift rules.</p></article>
							<article class="hlpb-guide-card"><h3>Prices, coupons &amp; gifts</h3><p>Configure tiers 1–4 separately: a percentage or fixed price, badge text, a gift, or a discounted product. Each enabled mode has its own WooCommerce coupon associated with that page's offer.</p><p>The plugin recalculates offer membership and discounts when quantities change in the cart. Offer items carry their name and group ID, while separate purchases remain regular products.</p></article>
							<article class="hlpb-guide-card"><h3>Offer gifts</h3><p>Assign a gift to the required quantity tier. It is linked to its offer group, so changing or removing the offer also updates the gift. Gifts added by other plugins should not be tagged as part of this offer.</p></article>
							<article class="hlpb-guide-card"><h3>Design options</h3><p>Change tab labels, step headings, helper text, summary labels, accent and background colours, button text, tier badges, and service details.</p></article>
			</div>
					</section>

					<section class="hlpb-guide-section" id="stock" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">04</span><div><p class="hlpb-guide-eyebrow">LIVE CATALOG DATA</p><h2>Variations, prices &amp; stock</h2></div></div>
						<p class="hlpb-guide-intro">Choose a colour and size on the landing page to show the matching price, availability, shipping note, and variation images.</p>
						<div class="hlpb-guide-steps">
							<article><span>1</span><div><h3>Set up WooCommerce variations</h3><p>Open <strong>Product data → Variations</strong> in the product editor. For each variation, set its price, stock management and quantity, backorder rules, variation gallery, and—when needed—the <strong>Description</strong> field.</p></div></article>
							<article><span>2</span><div><h3>Update the product and landing page</h3><p>Save the product changes. The landing page requests current catalog data when it loads and again when a variation is selected or changed. If a request fails, reload the page and select the variation again.</p></div></article>
							<article><span>3</span><div><h3>Check the result</h3><p>A variation gallery can be shown even when the product is out of stock. Unavailable options are marked and cannot be added to the cart; allowed backorders display their availability information.</p></div></article>
						</div>
						<div class="hlpb-guide-callout hlpb-guide-callout-important"><span class="hlpb-guide-callout-mark">!</span><div><strong>Why is the email sign-up missing?</strong><p>The back-in-stock form appears <strong>only when the selected variation is out of stock and its WooCommerce “Description” field is not empty</strong>. Use this description to explain the expected restock or delivery date. If the variation is out of stock but its description is blank, neither the notice nor the email field appears. Add and save a description on that specific variation—for example, “Back in stock on 12 December 2026”.</p></div></div>
						<div class="hlpb-guide-callout hlpb-guide-callout-note"><strong>Use the variation description</strong><p>The plugin reads the <em>variation</em> description under Product data → Variations, not the parent product's short description. The sign-up form is hidden while the variation is in stock, even if it has a description.</p></div>
					</section>

					<section class="hlpb-guide-section" id="waitlist" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">05</span><div><p class="hlpb-guide-eyebrow">BRING SHOPPERS BACK</p><h2>Back-in-stock sign-ups</h2></div></div>
						<p class="hlpb-guide-intro">A shopper can leave an email for an out-of-stock variation that has a description. Sign-ups appear in the plugin dashboard and are linked to a specific product and variation.</p>
						<div class="hlpb-guide-two-col">
							<article class="hlpb-guide-card"><h3>What is stored</h3><p>Email address, sign-up date, product or variation, referring landing page, and notification status. View the list under <a href="<?php echo esc_url( $stock_url ); ?>">Landing Pages → Stock Notifications</a>.</p></article>
							<article class="hlpb-guide-card"><h3>Send a notification</h3><p>When an item is back in stock, a notification may be sent automatically. You can also email one subscriber or everyone from the list. Edit the email template before sending and review each delivery status.</p></article>
						</div>
						<div class="hlpb-guide-callout hlpb-guide-callout-warning"><strong>Email delivery depends on your site's mail setup</strong><p>If a message does not arrive, check WordPress SMTP or mail delivery, the spam folder, and the sign-up status. A subscriber appearing in the list does not by itself confirm delivery.</p></div>
					</section>

					<section class="hlpb-guide-section" id="checkout" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">06</span><div><p class="hlpb-guide-eyebrow">FROM OFFER TO ORDER</p><h2>Cart &amp; checkout</h2></div></div>
						<p class="hlpb-guide-intro">An offer continues beyond the landing page button: its group, discounted price, and gift should carry through the cart, block or classic checkout, and order.</p>
						<div class="hlpb-guide-feature-list">
							<div><b>One or more offers</b><span>Items from separate landing pages and offer groups can be distinguished by their saved metadata; coupons are assigned to the matching items.</span></div>
							<div><b>CheckoutWC</b><span>Offer details are displayed in standard WooCommerce, checkout blocks, and CheckoutWC. Third-party themes may use different markup, so check the result with your active theme.</span></div>
							<div><b>Other plugins</b><span>A bonus item added by another extension, such as a game or promotion, should remain independent. Check its labels separately from a BuyGet gift.</span></div>
						</div>
						<p class="hlpb-guide-inline-link"><a href="<?php echo esc_url( $landing_url ); ?>">Go to landing pages →</a></p>
					</section>

					<section class="hlpb-guide-section" id="shortcodes" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">07</span><div><p class="hlpb-guide-eyebrow">EMBED YOUR CART</p><h2>Shortcodes &amp; Elementor</h2></div></div>
						<p class="hlpb-guide-intro">The shortcode displays only the cart section from a selected published landing page, including its products, offer modes, offers, and section design settings.</p>
						<ol class="hlpb-guide-numbered"><li>Open <a href="<?php echo esc_url( $shortcodes_url ); ?>">Landing Pages → Shortcodes</a>.</li><li>Select a published landing page that contains a Cart Section.</li><li>Choose whether to show the gallery and thumbnails on the left.</li><li>Copy the generated shortcode and paste it into a page.</li></ol>
						<pre class="hlpb-guide-code"><code>[hlpb_cart landing="253" gallery="yes"]</code></pre>
						<p>In Elementor, add the <strong>Shortcode</strong> widget and paste the copied value. Use only one interactive cart on a page to avoid duplicate element IDs.</p>
					</section>

					<section class="hlpb-guide-section" id="analytics" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">08</span><div><p class="hlpb-guide-eyebrow">MEASURE PERFORMANCE</p><h2>Analytics &amp; CSV export</h2></div></div>
						<p class="hlpb-guide-intro">The plugin report shows coupon usage and sales linked to its offer metadata.</p>
						<div class="hlpb-guide-two-col">
							<article class="hlpb-guide-card"><h3>Filters</h3><p>Open <a href="<?php echo esc_url( $analytics_url ); ?>">Landing Pages → Offer Analytics</a>. Filter by date range, order status, landing page, offer mode, coupon, or product.</p></article>
							<article class="hlpb-guide-card"><h3>Export</h3><p>Click CSV export to download order rows and line items, discounts, coupons, quantities, refunds, landing page, and offer type for spreadsheet analysis.</p></article>
						</div>
						<div class="hlpb-guide-callout hlpb-guide-callout-note"><strong>Why might an order be missing?</strong><p>BuyGet reports include only orders and line items that have saved offer metadata. A regular sale without an offer marker is not counted as a landing page offer sale. WooCommerce Analytics may also need its data to be refreshed after an order changes.</p></div>
					</section>

					<section class="hlpb-guide-section" id="seo" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">09</span><div><p class="hlpb-guide-eyebrow">SEARCH &amp; SHARING PREVIEW</p><h2>Landing page SEO settings</h2></div></div>
						<p class="hlpb-guide-intro">Open the <strong>SEO settings</strong> panel at the bottom of the editor. Values are saved per landing page and used when it is published.</p>
						<div class="hlpb-guide-seo-grid">
							<div><b>SEO title</b><span>The browser title and search result heading; keep it within 70 characters.</span></div>
							<div><b>Meta description</b><span>A concise page summary, up to 160 characters. Write naturally and keep it relevant to the page.</span></div>
							<div><b>Canonical URL</b><span>Optional URL for the preferred version of this page. Leave it blank to use the landing page URL automatically.</span></div>
							<div><b>Social title / description</b><span>Copy shown in social sharing cards. If left blank, the SEO title and description are used.</span></div>
							<div><b>Social sharing image</b><span>Optional image URL for social cards. A wide image around 1200 × 630 px is a useful target.</span></div>
							<div><b>Index / Follow</b><span>Allow search engines to index the page and follow its links. Enabled by default in demo content; turn these off for private, test, or duplicate pages.</span></div>
						</div>
						<p>The published WordPress page includes title, meta description, canonical, robots, and Open Graph/Twitter metadata. The <code>max-image-preview:large</code> directive allows a large image preview.</p>
						<div class="hlpb-guide-callout hlpb-guide-callout-warning"><strong>SEO settings cannot guarantee a result</strong><p>Complete metadata helps search engines understand a page, but cannot guarantee indexing, rankings, traffic, or a PageSpeed score. Check <code>robots.txt</code>, your sitemap, HTTPS, server performance, and caching separately on the live site.</p></div>
						<div class="hlpb-guide-screen-demo hlpb-guide-seo-demo" aria-label="SEO settings diagram">
							<div class="hlpb-guide-screen-top"><span>SEO SETTINGS</span><span>Page-specific settings</span></div>
							<div class="hlpb-guide-screen-field"><b>SEO title</b><span>Search and browser title</span></div>
							<div class="hlpb-guide-screen-field"><b>Meta description</b><span>Short page summary</span></div>
							<div class="hlpb-guide-screen-checks"><span>✓ Index this page</span><span>✓ Follow links</span></div>
							<p>DIAGRAM: SEO settings are at the bottom of the editor, after the sections</p>
						</div>
					</section>

					<section class="hlpb-guide-section" id="about" data-guide-section>
						<div class="hlpb-guide-section-heading"><span class="hlpb-guide-index">10</span><div><p class="hlpb-guide-eyebrow">ABOUT BUYGET</p><h2>About the developer</h2></div></div>
						<div class="hlpb-guide-two-col">
							<article class="hlpb-guide-card"><h3>Mikhail Kyrychenko</h3><p>BuyGet is built to help WooCommerce store owners turn products into focused landing pages with flexible bundle offers, live variation data, and a smoother path to checkout.</p><p>This guide and the bundled templates are part of the BuyGet plugin project.</p></article>
							<article class="hlpb-guide-card"><h3>Questions or feedback?</h3><p>For support, feature ideas, or collaboration enquiries, email Mikhail at <a href="mailto:mikhail.kyrychenko@gmail.com">mikhail.kyrychenko@gmail.com</a>.</p><p><a class="button button-primary" href="mailto:mikhail.kyrychenko@gmail.com">Email the developer ↗</a></p><p><a href="https://github.com/mikhailkyrychenko123/LPconstructor" target="_blank" rel="noopener noreferrer">View the project on GitHub</a></p></article>
						</div>
					</section>

					<footer class="hlpb-guide-footer"><div><strong>Ready for the next step?</strong><span>Open the editor, choose a landing page, and save your changes with Update.</span></div><a class="button button-primary" href="<?php echo esc_url( $landing_url ); ?>">Go to landing pages ↗</a></footer>
				</main>
			</div>
		</div>
		<?php
	}
}
