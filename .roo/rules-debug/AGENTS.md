# Project Debug Rules (Non-Obvious Only)

The root `AGENTS.md` is the authority. This file only adds debugging notes
and must not contradict it.

- **There is no AJAX in this plugin.** There is no network tab to check and
  no JSON error response. Every failure surfaces as a WordPress admin
  notice on the settings page, or not at all
- Admin notices are queued, not echoed. If a message is missing, check that
  the code path called
  `Algolia_Check_Requirements::queue_admin_notice()` before `admin_notices`
  fires. Anything echoed from `admin_init` or file scope is a bug
- The WordPress debug log is the only log output. The plugin writes no log
  files of its own
- `Algolia_Check_Requirements::check_unmet_requirements()` queues notices
  for missing `mbstring`, `mb_ereg_replace` or `curl`, but it does **not**
  block activation. A version mismatch likewise only queues a notice
- `Algolia_Send_Products::send_products_to_algolia()` has three silent
  early returns. Check them in order:
  1. `vendor/autoload.php` missing, guarded by `file_exists()`
  2. `Algolia_Check_Requirements::check_algolia_input_values()` returning
     `false` because a credential or the index name is empty
  3. `wc_get_products()` returning nothing
- A failed send surfaces as a caught `AlgoliaException` from
  `saveObjects()`, or as a `NullResponse`. Detect the latter with
  `'Algolia\AlgoliaSearch\Response\NullResponse' === get_class( $result )`
- The connection check is a single `wp_remote_post()` from
  `AlgoliaConnectionStatus` to
  `https://{appId}.algolia.net/1/indexes/{index}/query` with an empty
  query, using the **Search** API key. It is not `listApiKeys()`. Results
  are cached per request, and `sanitize_connection_diagnostic()` redacts
  both keys, so a diagnostic string will never show you the real key
- A send that works manually but not automatically means the auto-send
  option is not `'1'`. `init()` only registers `save_post` when it is, and
  `send_new_product_to_algolia()` additionally ignores anything that is not
  a published post of type `product`
- `wp_die()` with a 403 from `Algolia_Verify_Nonces` means either an
  expired nonce or a user without `manage_options`. Both verifiers check
  both
- There is no chunking. A timeout or memory exhaustion on a large catalogue
  is the expected failure mode of the single `saveObjects()` call
- `uninstall.php` only runs when `WP_UNINSTALL_PLUGIN` is defined, and it
  deletes options by literal suffix. An option added without a matching
  `delete_option()` line will be orphaned
- There is no test suite. Reproduce inside a real WordPress plus
  WooCommerce install
