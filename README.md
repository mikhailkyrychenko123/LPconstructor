# MikhailKyrychenko Offer Builder for WooCommerce

## Template structure

Each landing page design is a self-contained directory under `templates/`:

```text
templates/<template-slug>/
  template.json          # name, source, styles, script, assets, section patterns
  source.html             # source design used to extract demo sections
  assets/
    css/                  # shared design styles
    js/                   # shared interactive behavior
    images/               # demo and product images
    icons/                # vector icons
  partials/               # editable section markup
```

The template selector discovers directories that contain a valid `template.json`. The manifest paths must stay inside that template directory. Section patterns are PHP regular expressions used to extract the matching starter section from `source.html`. A template can also declare `default_layout` and `demo_text_image` values in its manifest. Keep shared colors, typography, and layout in the template assets so landing pages using the same template reuse one design system.

## Landing page flow

In WordPress Admin, open **Landing Pages → Add Landing Page**, set a title, arrange the demo sections, choose a template, edit the Text + Image sections, and press **Publish** or **Update**. Saving a published page writes a standalone copy to `wp-content/uploads/hlpb-exports/<Promo folder>/<Landing folder>/index.html`; the default folders are `Promo` and the landing page slug. The public URL uses the lowercase promo path, for example `/promo/demo1/`, and WordPress serves the export through the plugin's landing-page route. Both folder names can be changed in the builder. Changing either folder writes a new copy and leaves the previous folder in place. Moving the landing page to Trash removes only export folders with this plugin's ownership marker.

The export operation only manages folders carrying this plugin's `.hlpb-export.json` ownership marker. Selected media images are copied from the WordPress uploads directory into the export so the HTML does not depend on attachment URLs.

## Current scope

The bundled templates are Nest & Nook — Cozy Mix and Field Notes — The Everyday Edit. Their editable sections and fields are declared in each template's manifest. WooCommerce attribute setup adds Dropdown, Color image picker, and Button types, with color and image fields on attribute terms. On variable product pages, Color image picker renders term colors/images as swatches and Button renders terms as buttons while WooCommerce's native selects continue to drive variation matching and gallery updates.

## Variation galleries

For WooCommerce versions that provide variation galleries, open a variable product, expand a variation, and use **Variation gallery → Manage** to select and order its images. WooCommerce stores these images with the variation and swaps the product gallery when that variation is selected, then restores the main product gallery when the selection is reset. This plugin uses WooCommerce's native variation gallery instead of adding duplicate variation-image storage.
