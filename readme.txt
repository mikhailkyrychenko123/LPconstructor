=== HTML Landing Page Builder for WooCommerce ===
Contributors: mikhailkyrychenko
Donate link: https://www.privat24.ua/send/k4hk0
Tags: woocommerce, landing page, product bundles, mix and match, page builder
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create standalone landing pages for WooCommerce products with editable sections, bundle offers, variation choices, and a live cart.

== Description ==

HTML Landing Page Builder for WooCommerce helps store owners create product-focused landing pages in WordPress and publish them as standalone HTML pages. Configure the page path, select a design template, arrange sections, and connect the page to products in your WooCommerce store.

The published page keeps its offer and cart interactions connected to WooCommerce, so shoppers can choose product variations and add configured offers to their cart.

= Landing page editor =

* Build a page from reusable sections and change their order, visibility, and content.
* Choose from the included Cozy Mix and Field Notes designs.
* Edit section text, images, links, and template-specific options.
* Add page-specific CSS without changing the shared template styles.
* Set the landing page URL folder and publish or update its standalone page.

= WooCommerce offers =

* Configure Buy More, Mix & Match, and Cozy Bundle offer types.
* Connect offer choices to WooCommerce products and variations.
* Let shoppers select variation options for items in an offer.
* Configure offer pricing, coupons, and optional gifts in the cart section.
* Embed the cart section on another page with a generated shortcode such as `[hlpb_cart landing="123" gallery="yes"]`. The Shortcodes screen creates the code for a selected landing page and optional product gallery; use it in page builders that support WordPress shortcodes.

= Product information and stock =

* Display current product variation price and stock information on the landing page.
* Show backorder or availability details when the selected variation provides them.
* Collect email addresses for out-of-stock variations when an availability description is set, and manage subscriptions from the WordPress admin.
* Send a back-in-stock email when a subscribed variation becomes available, or send it manually from the subscription list.

= Page settings and reporting =

* Set page-specific SEO metadata, canonical URL, indexing directives, and social sharing metadata.
* Review offer and coupon sales analytics and export report details to CSV.
* Import optional demo products, attributes, and ready-to-edit landing pages.

== Screenshots ==

1. Landing page editor showing template selection, URL folder settings, and section controls.
1. Edit a Text + Image section, including its copy, image position, text colour, and feature captions.
1. Configure the WooCommerce cart section with product offers, variation choices, and display settings.
1. Manage back-in-stock subscribers, review delivery status, and edit the notification email template.
1. Product landing page showing variation gallery, live stock status, and the back-in-stock signup form.
1. Set quantity-specific offer prices, badge text, gifts, and gift discounts.
1. Field Notes landing page with product gallery, bundle choices, gift options, and variation selectors.
1. WooCommerce checkout order summary showing offer badges and discounted item prices.

== Requirements ==

* WordPress 6.2 or later.
* PHP 7.4 or later.
* WooCommerce must be installed and active.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin folder to `/wp-content/plugins/` or install the plugin ZIP from **Plugins → Add New**.
3. Activate **HTML Landing Page Builder for WooCommerce**.
4. To explore the included examples, open **Landing Pages → Import** and import the demo data. This creates sample WooCommerce products and landing pages in your store.
5. Open **Landing Pages → Add Landing Page**, choose a template, configure its sections and offers, then publish the page.

== Frequently Asked Questions ==

= Does this plugin replace WooCommerce? =

No. WooCommerce remains responsible for products, variations, cart totals, coupons, and checkout. This plugin provides the landing page editor and connects its offer section to WooCommerce.

= Do I need Elementor or another page builder? =

No. Landing pages are edited in the plugin's section-based editor. To place only the cart section on another page, use the generated `[hlpb_cart landing="123" gallery="yes"]` shortcode with a page builder that supports WordPress shortcodes. Replace `123` with the ID of the landing page selected in the Shortcodes screen.

= Why is the back-in-stock email form not visible? =

The form is shown for an out-of-stock variation when that variation has a description. The description is used for the availability or delivery note above the form. If the variation description is empty, the signup form is not displayed.

= When are back-in-stock emails sent? =

The plugin sends a notification when a subscribed variation becomes available. Store administrators can also send an email to an individual subscriber or to eligible subscribers from **Landing Pages → Stock Notifications**.

= Will a published page always score 100 on Google PageSpeed? =

No score can be guaranteed. The plugin publishes a standalone HTML page, but PageSpeed results also depend on the hosting environment, selected images, fonts, theme, and other installed software.

== Privacy ==

When a shopper subscribes to a back-in-stock alert, the email address and subscription status are stored in the site's WordPress database against the relevant product variation. Offer analytics reads WooCommerce order and coupon data associated with this plugin's offers. The store owner controls this data and should describe the relevant processing in the store's privacy policy.

== Changelog ==

= 0.6.8 =
* Prepared the plugin listing description and user-facing documentation.

== Upgrade Notice ==

= 0.6.8 =
Initial WordPress.org release. Demo data import is optional and adds sample products and landing pages to your store.
