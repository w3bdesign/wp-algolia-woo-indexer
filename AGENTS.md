# AGENTS.md

This file provides guidance to agents when working with code in this
repository.

## Requirements

- Plugin header in `algolia-woo-indexer.php` requires WordPress 7.1+,
  PHP 8.4+, WooCommerce 11.2.1+
- Runtime checks use `ALGOLIA_MIN_PHP_VERSION` / `ALGOLIA_MIN_WP_VERSION`,
  defined in `classes/class-check-requirements.php`. Keep them in sync with
  the plugin header, `readme.txt` and `README.md`
- **`Requires at least: 7.1` is correct and is not a typo.** WordPress 7.0
  and 7.1 exist and 7.1.x is the current release line. Do not "fix" this to
  6.x. Verify any WordPress version claim against
  `https://api.wordpress.org/core/stable-check/1.0/` before acting on it,
  never from memory
- Runtime dependency: `algolia/algoliasearch-client-php` (Composer).
  `Send_Products::send_products_to_algolia()` `require_once`s
  `vendor/autoload.php`, but it is guarded by `file_exists()` and shows an
  admin notice instead of fatally erroring when `vendor/` is absent

## Licence

- **The project is AGPL-3.0.** The `LICENSE` file is the authority
- Every declaration is now aligned to it. Keep all five in sync when
  touching any one of them:
  - `LICENSE` — the full AGPL-3.0 text
  - `readme.txt` — `License: AGPLv3 or later` /
    `License URI: https://www.gnu.org/licenses/agpl-3.0.html`
  - `algolia-woo-indexer.php` — the same two fields in the plugin header,
    plus `@license AGPL-3.0-or-later` in the docblock
  - `composer.json` — `"license": "AGPL-3.0-or-later"`
  - `README.md` — the `## Licence` section
- The plugin header and `readme.txt` use the WordPress readme spelling
  (`AGPLv3 or later`); `composer.json` and the docblock use the SPDX
  identifier (`AGPL-3.0-or-later`). Both forms are intentional
- Historic stale values were `GPLv2 or later` in `readme.txt` and
  `GNU version 3` in the docblock. Do not reintroduce either

## WordPress Plugin Architecture (Non-obvious patterns)

- Two-phase init: `algolia-woo-indexer.php` `require_once`s every class
  file, then calls `\Algowoo\Algolia_Woo_Indexer::get_instance()` and
  registers the activation/deactivation hooks on that instance. The
  constructor calls the static `init()`, which registers all hooks
- All classes live in namespace `Algowoo` and are loaded manually, with no
  autoloader. A new class file needs a `require_once` in the main file
- Classes are one of two styles:
  - Legacy, wrapped in `if ( ! class_exists() )`: `Algolia_Woo_Indexer`,
    `Algolia_Send_Products`, `Algolia_Verify_Nonces`,
    `Algolia_Check_Requirements`. These are mostly static methods
  - Newer `final` classes named `AlgoliaConnectionStatus` and
    `AlgoliaSettingsFields`. The main class uses `AlgoliaConnectionStatus`
    as an injected, optional constructor dependency.
    `AlgoliaSettingsFields` is called statically as Settings API render
    callbacks
- There is no AJAX. The settings form and the "Send products" form are
  plain POSTs to `options-general.php?page=algolia-woo-indexer-settings`,
  handled on `admin_init` by `update_settings_options()` and
  `maybe_send_products()`
- Settings fields are registered in a data-driven `$fields` array in
  `Algolia_Woo_Indexer::setup_settings_sections()`. Add a field there and a
  `render_*` method in `AlgoliaSettingsFields`
- Product sends are triggered either by the manual form, which is protected
  by both a nonce and a capability check, or by the `save_post` hook when
  the auto-send option is `'1'` (`send_new_product_to_algolia()`, which
  sends only published `product` posts)
- **The `save_post` hook is registered outside `is_admin()` on purpose**, so
  that products published through the REST API, WP-CLI or a scheduled post
  are indexed too. Do not move it back inside the `is_admin()` block. Only
  the admin menu, styles and the `admin_init` handlers belong there
- `algolia-woo-indexer.php` declares WooCommerce HPOS (`custom_order_tables`)
  and `cart_checkout_blocks` compatibility on `before_woocommerce_init`,
  guarded by a `class_exists()` check on `FeaturesUtil`. The plugin only
  reads products, so it is unaffected by the order storage backend

## Security conventions

- **Nonces guard against CSRF, not authorization. Every write path pairs a
  nonce check with `current_user_can( 'manage_options' )`.** Keep both
- Pass the capability to `current_user_can()` as a string literal. A class
  constant triggers `WordPress.WP.Capabilities.Undetermined` in PHPCS
- All `$_POST` reads go through `filter_input()`, then `wp_unslash()`, then
  `sanitize_text_field()`. `Algolia_Woo_Indexer::get_posted_setting()` is the
  helper for the array-shaped settings fields
- **Never echo markup from `admin_init`, from `init()` or at file scope.**
  Queue it with `Algolia_Check_Requirements::queue_admin_notice( $message )`,
  or register your own `add_action( 'admin_notices', ... )` closure. The
  message passed to `queue_admin_notice()` must already be translated and
  unescaped; the helper escapes it
- `AlgoliaConnectionStatus` is cached per request, requires `manage_options`,
  validates the Application ID as `^[a-zA-Z0-9]+$`, disables redirects, and
  redacts both keys from diagnostics through
  `sanitize_connection_diagnostic()`. Keep all of this when editing it

## Nonces

- Both verifiers live in `Algolia_Verify_Nonces`, both return `bool`, and
  both `wp_die()` with a 403 on failure
- `verify_settings_nonce()` returns `false` when the settings form was not
  submitted at all. Otherwise it enforces `manage_options` and then
  `wp_verify_nonce()`. Action is `algolia_woo_indexer_admin_api_nonce_action`
  and field is `algolia_woo_indexer_admin_api_nonce_name`, rendered inside
  the Write API Key field renderer
- `verify_send_products_nonce()` returns `false` when the hidden
  `send_products_to_algolia` field is absent. Otherwise it enforces
  `manage_options` and then the nonce. Action is
  `send_products_to_algolia_nonce_action` and field is
  `send_products_to_algolia_nonce_name`
- `update_settings_options()` returns early unless
  `verify_settings_nonce()` is truthy, so the capability gate covers every
  `update_option()` call in that method

## Options and constants

- Constants are global `define()`s at the top of
  `classes/class-send-products.php`, not class constants, and are loaded
  after the main class file. Do not use them at file scope in
  `class-algolia-woo-indexer.php`
  - `ALGOWOO_DB_OPTION` = `_algolia_woo_indexer`
  - `ALGOLIA_APP_ID`, `ALGOLIA_SEARCH_API_KEY`, `INDEX_NAME`,
    `AUTOMATICALLY_SEND_NEW_PRODUCTS`
  - `ALGOLIA_API_KEY` = `_admin_api_key`. This legacy suffix holds the
    **Write** API key and is kept so existing saved keys keep working. Do
    not rename it
  - `CHANGE_ME` = `'change me'`, the placeholder compared
    case-insensitively
- These names are unprefixed globals, which is a known wart. Prefix any new
  constant you add
- Each setting is its own option, `ALGOWOO_DB_OPTION . SUFFIX`, read through
  `get_option()`. `Algolia_Woo_Indexer::get_algolia_settings()` returns the
  connection settings
- Form field names use an array shape, for example
  `algolia_woo_indexer_application_id[id]`, `..._admin_api_key[key]`,
  `..._search_api_key[key]`, `..._index_name[name]`
- The four credential options are only written when the submitted value is
  non-empty, so clearing a field does not clear the saved option
- **The auto-send option is written unconditionally** as `'1'` or `'0'`,
  because an unchecked checkbox is absent from the POST data. Keep it
  outside the non-empty loop or automatic indexing becomes impossible to
  switch off
- `uninstall.php` redefines `ALGOWOO_DB_OPTION` and deletes the options by
  literal suffix, including the legacy `_index_in_stock`. Update it when
  adding or renaming options
- Activation seeds `'Change me'` defaults and sets the transient
  `algowoo-plugin-notice`. Deactivation deletes only the transient

## Two different Algolia keys

- Write API Key (`_admin_api_key`) is used by `Send_Products` via the
  Algolia PHP client (`SearchClient::create()` then `saveObjects()`). It is
  created fresh on every send. Docs say it should have only `addObject`
  permission
- Search API Key (`_search_api_key`) is used only by
  `AlgoliaConnectionStatus`, which makes one `wp_remote_post()` to
  `https://{appId}.algolia.net/1/indexes/{index}/query` with an empty query
  on each settings-page load

## Indexing details

- `send_products_to_algolia( $id = '' )` guards the Composer autoloader,
  reads the settings, and **returns early unless
  `Algolia_Check_Requirements::check_algolia_input_values()` passes**. That
  check returns `bool` and queues its own notice
- It then fetches published products with `wc_get_products()`, using
  `limit => -1` for all products or `include => [ $id ]` for one, builds all
  records, and sends them in one `saveObjects()` call. There is no chunking,
  which is the main scaling limitation
- Record fields: `objectID`, `product_name`, `product_image`,
  `short_description`, `regular_price`, `sale_price`, `on_sale`
- Image URL is extracted with a regex on `$product->get_image()`:
  `preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', ...)` followed by
  `array_pop()`
- Price logic in `get_product_type_price()` handles only `simple` (sale and
  regular price getters) and `variable` (min sale, max regular variation
  prices). Other product types get `0`
- Outcomes are all reported through `admin_notices`: an `AlgoliaException`
  from `saveObjects()`, a `NullResponse` result, and the plural-aware `_n()`
  success message

## Known quirks (do not "fix" unasked)

- `class-algolia-woo-indexer.php` has a bare `// TODO Rename class ...` line
  comment sitting between the class docblock and the `class` keyword. The
  same shape in `class-send-products.php` tripped
  `Squiz.Commenting.ClassComment.WrongStyle` and had to be moved into the
  docblock as `@todo`. If the commenting sniff fires here, move it rather
  than deleting it
- Text domain is `algolia-woo-indexer` everywhere. Translation files are in
  `languages/`, and `load_textdomain()` resolves that with
  `basename( dirname( __DIR__ ) )` because the class file lives in
  `classes/`
- `DOCS/repository_context.txt` is generated by the Repomix GitHub workflow
  on pushes to `main`. Do not edit it by hand
- `.roo/rules-architect/AGENTS.md` describes AJAX endpoints and AJAX-driven
  singleton behaviour. That is wrong; this plugin has no AJAX. Trust this
  file over that one

## Static analysis: three independent gates

A change has to satisfy all three. They use different rulesets, so passing
one says nothing about the others.

1. **PHPCS** via `.github/workflows/phpcs.yml` (rtCamp action, `WordPress`
   standard). Local config is `phpcs.xml`: `WordPress` plus
   `PHPCompatibility`, excluding
   `WordPress.Files.FileName.NotHyphenatedLowercase` and
   `WordPress.Files.FileName.InvalidClassFileName`, ignoring `vendor`,
   `node_modules`, `dev-lib`, `.github` and `algoliasearch-client-php`. Its
   `testVersion` is still `7.3-`, stale versus the PHP 8.4 requirement
2. **Codacy, running PHPMD.** It is not configured anywhere in the repo and
   it **blocks merges**. Its `StaticAccess` rule fires on this codebase's
   deliberate static style. The accepted workaround is a
   `@SuppressWarnings(PHPMD.StaticAccess)` line in the docblock of the
   static hook callback, not a refactor to dependency injection. It is
   already applied to `maybe_send_products()`, `init()`,
   `send_new_product_to_algolia()`, `update_settings_options()` and
   `Algolia_Send_Products::send_products_to_algolia()`
3. **A VIP-style PHPCS scanner** that reports `Squiz.Commenting.*` and
   `Generic.Commenting.*` errors the local `phpcs.xml` does not. Recurring
   ones: no blank line between `<?php` and the file docblock, `@param`
   comments must end in a full stop, docblocks need a short description, and
   class comments must use `/** */`

## Development Commands

- Install dependencies: `composer install`
- Run PHP_CodeSniffer: `vendor/bin/phpcs`
- Fix coding standards: `vendor/bin/phpcbf`. `phpcs-autofix.yml` also
  applies automatic fixes in CI
- There is no test suite. Plugin behaviour must be verified inside a
  WordPress + WooCommerce environment
