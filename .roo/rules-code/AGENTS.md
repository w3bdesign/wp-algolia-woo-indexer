# Project Coding Rules (Non-Obvious Only)

The root `AGENTS.md` is the authority. This file only adds coding notes and
must not contradict it.

- Always obtain the main plugin class through
  `Algolia_Woo_Indexer::get_instance()`, never by direct instantiation
- **There is no AJAX in this plugin.** Do not add `wp_send_json_error()`,
  `wp_ajax_*` hooks or an AJAX nonce helper. Writes are plain admin POSTs
  handled on `admin_init`
- Every write path pairs a nonce check from `Algolia_Verify_Nonces` with
  `current_user_can( 'manage_options' )`. Both are required. Pass the
  capability as a string literal, because a class constant trips
  `WordPress.WP.Capabilities.Undetermined` in PHPCS
- Use `Algolia_Verify_Nonces::verify_settings_nonce()` and
  `verify_send_products_nonce()` rather than calling `wp_verify_nonce()`
  directly. Both return `bool` and both `wp_die()` with a 403 on failure
- Read `$_POST` via `filter_input()`, then `wp_unslash()`, then
  `sanitize_text_field()`. For the array-shaped settings fields, use
  `Algolia_Woo_Indexer::get_posted_setting()`
- Never echo markup from `admin_init`, from `init()` or at file scope. Use
  `Algolia_Check_Requirements::queue_admin_notice()`, which escapes the
  already-translated message for you
- Read the connection settings through
  `Algolia_Woo_Indexer::get_algolia_settings()`, not scattered
  `get_option()` calls
- Record building lives inline in
  `Algolia_Send_Products::send_products_to_algolia()`. There is no
  `prepare_product_data()` method. Fields are `objectID`, `product_name`,
  `product_image`, `short_description`, `regular_price`, `sale_price`,
  `on_sale`
- Plugin text domain is `algolia-woo-indexer` everywhere, and must match
  the plugin directory name for translations to load
- Constants are **global** `define()`s at the top of
  `classes/class-send-products.php`, not class constants:
  `ALGOWOO_DB_OPTION`, `ALGOLIA_API_KEY`, `ALGOLIA_APP_ID`,
  `ALGOLIA_SEARCH_API_KEY`, `INDEX_NAME`,
  `AUTOMATICALLY_SEND_NEW_PRODUCTS`, `CHANGE_ME`. Prefix any new constant
  you add
- `ALGOLIA_API_KEY` is `_admin_api_key` and holds the **Write** key. The
  legacy suffix is kept so saved keys keep working. Do not rename it
- Database options use the prefix pattern `ALGOWOO_DB_OPTION . SUFFIX`, for
  example `_algolia_woo_indexer_application_id`
- Core functionality assumes WooCommerce is active. There are no fallbacks
- Product price extraction differs by type in
  `get_product_type_price()`: `simple` uses the sale and regular price
  getters, `variable` uses `get_variation_sale_price( 'min', true )` and
  `get_variation_regular_price( 'max', true )`. Everything else returns `0`
- Add `@SuppressWarnings(PHPMD.StaticAccess)` to the docblock of any new
  static hook callback that calls other classes statically, otherwise
  Codacy blocks the merge
