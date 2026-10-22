# AGENTS.md

This file provides guidance to agents when working with code in this repository.

## Requirements

- Plugin header in `algolia-woo-indexer.php` requires WordPress 7.1+, PHP 8.4+, WooCommerce 11.2.1+
- Runtime checks use `ALGOLIA_MIN_PHP_VERSION` / `ALGOLIA_MIN_WP_VERSION`, defined in `classes/class-check-requirements.php`. Keep them in sync with the plugin header, `readme.txt` and `README.md`
- Runtime dependency: `algolia/algoliasearch-client-php` (Composer). `vendor/` must exist, because `Send_Products::send_products_to_algolia()` does `require_once` on `vendor/autoload.php`

## WordPress Plugin Architecture (Non-obvious patterns)

- Two-phase init: `algolia-woo-indexer.php` `require_once`s every class file, then calls `\Algowoo\Algolia_Woo_Indexer::get_instance()` and registers the activation/deactivation hooks on that instance. The constructor calls the static `init()`, which registers all hooks
- All classes live in namespace `Algowoo` and are loaded manually, with no autoloader. A new class file needs a `require_once` in the main file
- Classes are one of two styles:
  - Legacy, wrapped in `if ( ! class_exists() )`: `Algolia_Woo_Indexer`, `Algolia_Send_Products`, `Algolia_Verify_Nonces`, `Algolia_Check_Requirements`. These are mostly static methods
  - Newer `final` classes named `AlgoliaConnectionStatus` and `AlgoliaSettingsFields`. The main class uses `AlgoliaConnectionStatus` as an injected, optional constructor dependency. `AlgoliaSettingsFields` is called statically as Settings API render callbacks
- There is no AJAX. The settings form and the "Send products" form are plain POSTs to `options-general.php?page=algolia-woo-indexer-settings`, handled on `admin_init` by `update_settings_options()` and `maybe_send_products()`
- Settings fields are registered in a data-driven `$fields` array in `Algolia_Woo_Indexer::setup_settings_sections()`. Add a field there and a `render_*` method in `AlgoliaSettingsFields`
- Product sends are triggered either by the nonce-protected manual form or, when the auto-send option is `'1'`, by the `save_post` hook (`send_new_product_to_algolia()`, which sends only published `product` posts)

## Options and constants

- Constants are global `define()`s at the top of `classes/class-send-products.php`, not class constants, and are loaded after the main class file. Do not use them at file scope in `class-algolia-woo-indexer.php`
  - `ALGOWOO_DB_OPTION` = `_algolia_woo_indexer`
  - `ALGOLIA_APP_ID`, `ALGOLIA_SEARCH_API_KEY`, `INDEX_NAME`, `AUTOMATICALLY_SEND_NEW_PRODUCTS`
  - `ALGOLIA_API_KEY` = `_admin_api_key`. This legacy suffix holds the **Write** API key and is kept so existing saved keys keep working. Do not rename it
  - `CHANGE_ME` = `'change me'`, the placeholder compared case-insensitively
- Each setting is its own option, `ALGOWOO_DB_OPTION . SUFFIX`, read through `get_option()`. `Algolia_Woo_Indexer::get_algolia_settings()` returns the connection settings
- Form field names use an array shape, for example `algolia_woo_indexer_application_id[id]`, `..._admin_api_key[key]`, `..._search_api_key[key]`, `..._index_name[name]`
- `update_settings_options()` only calls `update_option()` for non-empty values. Unchecking auto-send (value `0`) is therefore never persisted. Clearing a field does not clear the saved option either
- `uninstall.php` redefines `ALGOWOO_DB_OPTION` and deletes the options by literal suffix, including the legacy `_index_in_stock`. Update it when adding or renaming options
- Activation seeds `'Change me'` defaults and sets the transient `algowoo-plugin-notice`. Deactivation deletes only the transient

## Two different Algolia keys

- Write API Key (`_admin_api_key`) is used by `Send_Products` via the Algolia PHP client (`SearchClient::create()` then `saveObjects()`). It is created fresh on every send. Docs say it should have only `addObject` permission
- Search API Key (`_search_api_key`) is used only by `AlgoliaConnectionStatus`, which makes one `wp_remote_post()` to `https://{appId}.algolia.net/1/indexes/{index}/query` with an empty query on each settings-page load
- Connection status is cached per request, requires `manage_options`, validates the Application ID as `^[a-zA-Z0-9]+$`, disables redirects, and redacts both keys from diagnostics through `sanitize_connection_diagnostic()`. Keep this when editing it

## Nonces

- The send-products flow uses `Algolia_Verify_Nonces::verify_send_products_nonce()`. Action is `send_products_to_algolia_nonce_action` and field is `send_products_to_algolia_nonce_name`. It calls `wp_die()` on an invalid nonce when the hidden `send_products_to_algolia` field is present, and returns `true` only when both are valid
- `verify_settings_nonce()` currently checks nothing and always returns nothing. The settings form emits a nonce (`algolia_woo_indexer_admin_api_nonce_action` / `_name`, rendered inside the Write API Key field renderer), but it is not enforced
- There is no capability check in `update_settings_options()`. The page callback checks `manage_options`

## Indexing details

- `send_products_to_algolia( $id = '' )` fetches published products with `wc_get_products()`. It uses `limit => -1` for all products, or `include => [ $id ]` for one, builds all records, and sends them in one `saveObjects()` call. There is no chunking
- Record fields: `objectID`, `product_name`, `product_image`, `short_description`, `regular_price`, `sale_price`, `on_sale`
- Image URL is extracted with a regex on `$product->get_image()`: `preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', ...)` followed by `array_pop()`
- Price logic in `get_product_type_price()` handles only `simple` (sale and regular price getters) and `variable` (min sale, max regular variation prices). Other product types get `0`
- Errors from `saveObjects()` (`AlgoliaException`) add an admin notice and return. Success echoes a notice directly (plural-aware `_n()` message)

## Known quirks (do not "fix" unasked)

- `register_setting()` uses `sanitize_callback => 'settings_fields_validate_options'`, a bare string that is not a global function (the real method is a static method on the main class). Sanitization actually happens in `update_settings_options()`
- `load_textdomain()` uses `basename( __DIR__ ) . '/languages/'`, which resolves relative to `classes/`. Text domain is `algolia-woo-indexer` (one `esc_html__` in `class-send-products.php` uses the wrong domain `algolia_woo_indexer_settings`). Translation files are in `languages/`
- `Algolia_Check_Requirements::check_algolia_input_values()` has a misplaced parenthesis in its `empty()` condition, so the index name check is effectively skipped
- `DOCS/repository_context.txt` is generated by the Repomix GitHub workflow on pushes to `main`. Do not edit it by hand

## Development Commands

- Install dependencies: `composer install`
- Run PHP_CodeSniffer: `vendor/bin/phpcs` (`phpcs.xml`, `WordPress` ruleset plus `PHPCompatibility`)
- Fix coding standards: `vendor/bin/phpcbf`
- `phpcs.xml` excludes `WordPress.Files.FileName.NotHyphenatedLowercase` and `WordPress.Files.FileName.InvalidClassFileName`, and ignores `vendor`, `node_modules`, `dev-lib`, `.github` and `algoliasearch-client-php`. It still sets `testVersion` to `7.3-`, which is stale versus the PHP 8.4 requirement
- CI: `.github/workflows/phpcs.yml` runs PHPCS on pull requests (rtCamp action with the `WordPress` standard). `phpcs-autofix.yml` applies automatic fixes
- There is no test suite. Plugin behaviour must be verified inside a WordPress + WooCommerce environment
