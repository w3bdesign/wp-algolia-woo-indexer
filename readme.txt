=== Algolia Woo Indexer ===
Contributors: Daniel F
Tags: algolia, woocommerce, search, algolia search, algolia indexing
Requires at least: 7.1
Requires PHP: 8.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==
This plugin indexes products inside of WooCommerce and submits them to Algolia. 
You need to add the Application ID, a dedicated API key from the API keys section in Algolia, and the index name. Grant Search (search) for connection checks and Add records (addObject) for uploads, restricted to the exact product index. A Search API Key alone cannot upload products; the Admin API Key is not required.


== Installation ==
* Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
* Login as Admin
* Activate the plugin through the 'Plugins' menu in WordPress
* Login to Algolia and go to "API keys"
* Create a dedicated custom key with Search (search) and Add records (addObject) permissions, restricted to the exact product index name (avoid wildcards)
* Add the Application ID to the plugin settings page
* Add the custom key to the Write API Key field on the plugin settings page
* Enter the name of the index that should be used to index Woocommerce products


== Frequently Asked Questions ==
= Which API key permissions are required? =
Search (search) for the connection check and Add records (addObject) for product uploads, restricted to the configured index. No key-management or index-listing permissions are needed. Keep this combined key server-side and use a separate Search API Key for browser or storefront searches. Existing addObject-only keys still upload, but need search permission for connection checks to succeed.

= How does the connection status work? =
An emoji status notice appears at the top of the plugin settings page. It checks once per page load, including after saving, when Application ID, Write API Key, and index name are all set. A single search request with a five-second timeout, zero returned records, and analytics disabled checks search access without changing products. It does not run on other admin pages or poll in the background. The notice explains missing settings, rejected credentials or permissions, network failures, rate limits, and missing indices. A new index may need its first product upload before it can be searched. This check verifies search access, not write permission or completion of previous uploads.

Failed checks show the HTTP status and Algolia error message, or the WordPress network error when the request could not reach Algolia. Diagnostic text is length-limited, escaped, visible only to settings administrators, and redacts the configured API key. Raw HTML responses are not displayed.

= What if I previously saved an Admin API Key? =
Existing settings are retained, but upgrading does not restrict or replace the saved key. Create an index-restricted write key in Algolia, replace the saved key in the plugin settings, and verify uploads. Rotate the previously used Admin API Key after checking whether other integrations depend on it.

= Why did an upload fail? =
Check connectivity, the Application ID, index name, and the key's Add records (addObject) permission for that index. A Search API Key or a key restricted to another index cannot upload products.


== Changelog ==

= 1.0.9 =
* Require PHP 8.4 or newer
* Require WooCommerce 11.2.1 or newer
* Support dedicated index-restricted Write API Keys without key-management or index-listing permissions
* Handle Algolia upload errors and clarify API key setup
* Mask the Write API Key field while retaining existing saved settings
* Declare compatibility with WooCommerce High-Performance Order Storage and Cart/Checkout Blocks

= 1.0.8 =
* Minor package updates

= 1.0.7 =
* Add support for premium version

= 1.0 =
* Initial release


== Upgrade Notice ==
= 1.0.9 =
Requires PHP 8.4 and WooCommerce 11.2.1 or newer. Replace any previously saved Admin API Key with a dedicated key restricted to the configured index, with Search (search) for connection checks and Add records (addObject) for uploads. Existing settings are retained. WooCommerce HPOS and Cart/Checkout Blocks compatibility is now declared.


== License ==
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.


== Version history ==

1.0.9 - Restricted Write API Key support and WooCommerce feature compatibility declarations
1.0.8 - Minor package updates
1.0.7 - Add support for premium version
1.0   - Initial version
