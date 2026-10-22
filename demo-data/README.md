# WooCommerce demo products

`demo-products.json` is the version 1 import package. Its `media/` directory contains every product, variation-gallery, and color-swatch image referenced by the package, so importing does not depend on the source site's domain or attachment IDs.

After activating the plugin with WooCommerce active, an administrator can import the package from the activation notice or from **Landing Pages → Import**. The importer creates or updates three published products with `Landing-demo` appended to their names. It creates separate global attributes named `Color Landing-demo` and `Size Landing-demo`; the attribute slugs are also isolated from existing Color and Size attributes. Repeating the import updates only plugin-marked demo records instead of creating duplicates.

The import screen displays a persistent status and a short report after each import or cleanup. Its **Delete all demo data** action removes only products, variations, attributes, terms, and attachments carrying this importer's ownership markers. It keeps media that is referenced elsewhere and keeps any attribute or term that still contains other content. Landing page templates and their reference sections are not part of this product-data cleanup.

The `Flower` color uses the bundled image swatch instead of a hex color. Product and variation galleries are copied into the destination Media Library.

The source simple product used a shortcode supplied by another plugin. That shortcode is omitted from the demo product so this package remains usable on stores that do not have that separate plugin installed.
