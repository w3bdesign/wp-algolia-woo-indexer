# AGENTS.md

This file provides guidance to agents when working with code in this repository.

## WordPress Plugin Architecture (Non-obvious patterns)

- Plugin initialization happens in two phases: main file registers hooks, then `Algolia_Woo_Indexer` class handles functionality via singleton pattern with `get_instance()`
- All AJAX/form actions use custom nonce verification through `Verify_Nonces` class, not standard WordPress `wp_verify_nonce()` directly
- Product indexing is batched via `Send_Products::send_products_to_algolia()` - processes arrays, not individual products
- Settings stored as individual prefixed options (`ALGOWOO_DB_OPTION . SUFFIX`), accessed via wrapper methods, not direct `get_option()` calls
- Constants defined in `Send_Products` class, not main file - `ALGOWOO_DB_OPTION`, `ALGOLIA_API_KEY`, etc.
- Plugin requires PHP 8.1+ and WordPress 6.1+ (despite error message mentioning 7.2/5.0)
- Uninstall process in `uninstall.php` requires `WP_UNINSTALL_PLUGIN` constant check before executing
- All classes use manual loading via `require_once` - no autoloader used
- Image extraction uses regex parsing: `preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result)`
- WooCommerce product price logic differs by type: `simple` vs `variable` products use different getter methods

## Development Commands

- Run PHP_CodeSniffer: `vendor/bin/phpcs` (WordPress-Core, WordPress-Docs, WordPress-Extra rules)  
- Fix coding standards: `vendor/bin/phpcbf`
- PHPCS excludes: file naming rules (`WordPress.Files.FileName.*`)
- Plugin must be tested within WordPress environment (no standalone testing)