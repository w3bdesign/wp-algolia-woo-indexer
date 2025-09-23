# Project Coding Rules (Non-Obvious Only)

- Always use singleton pattern for main plugin class via `get_instance()` method (not direct instantiation)
- AJAX handlers must call `Verify_Nonces::verify_nonce()` instead of WordPress standard `wp_verify_nonce()` for consistency
- Product data uses custom field mapping in `Send_Products::prepare_product_data()` - don't rely on WooCommerce defaults
- Settings access must go through main class wrapper methods, not direct `get_option()` calls
- Error responses use `wp_send_json_error()` with specific error codes matching the class error handling patterns
- Plugin text domain is `algolia-woo-indexer` - must match directory name exactly for translations
- Constants are defined in `Send_Products` class: `ALGOWOO_DB_OPTION`, `ALGOLIA_API_KEY`, `INDEX_NAME`, etc.
- Database options use prefix pattern: `ALGOWOO_DB_OPTION . SUFFIX` (e.g., `_algolia_woo_indexer_application_id`)
- All database operations assume WooCommerce is active - no fallback checks in core functionality
- Product price extraction differs by type: use `get_variation_sale_price('min', true)` for variable products