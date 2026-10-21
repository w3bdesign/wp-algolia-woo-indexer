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
Add the Application ID, Search API Key, Write API Key, and index name. Copy the built-in Search API Key from Algolia for connection checks. For uploads, create a separate Write API Key with only Add records (addObject) permission, restricted to the exact product index. The Admin API Key is not required.


== Installation ==
* Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
* Login as Admin
* Activate the plugin through the 'Plugins' menu in WordPress
* Login to Algolia and go to "API keys"
* Copy the built-in Search API Key for connection checks
* Create a dedicated custom Write API Key with only Add records (addObject) permission, restricted to the exact product index name (avoid wildcards)
* Add the Application ID to the plugin settings page
* Paste the built-in Search API Key into the Search API Key field
* Add the custom key to the Write API Key field on the plugin settings page
* Enter the name of the index that should be used to index Woocommerce products


== Frequently Asked Questions ==
= Which API key permissions are required? =
Use separate keys: the built-in Search API Key for connection checks, and a dedicated Write API Key with only Add records (addObject) permission restricted to the configured index for uploads. No key-management or index-listing permissions are needed. Never expose the Write API Key to a browser or storefront. Existing saved Write API Keys are retained; add the Search API Key to enable connection checks.

= How does the connection status work? =
An emoji status notice appears at the top of the plugin settings page. It checks once per page load, including after saving, when Application ID, Search API Key, Write API Key, and index name are all set. A single search request with a five-second timeout, zero returned records, and analytics disabled checks the Search API Key without changing products. It does not run on other admin pages or poll in the background. The notice explains missing settings, rejected credentials or permissions, network failures, rate limits, and missing indices. A new index may need its first product upload before it can be searched. This check does not verify the separate Write API Key or completion of previous uploads.

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
* Add a separate Search API Key field and check the connection once per settings-page load
* Show emoji connection status and redacted HTTP or transport diagnostics at the top of the settings page
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
Requires PHP 8.4 and WooCommerce 11.2.1 or newer. Replace any previously saved Admin API Key with a dedicated Write API Key restricted to the configured index with only Add records (addObject) permission. Paste the built-in Search API Key into the separate Search API Key field for connection checks. Existing settings are retained. WooCommerce HPOS and Cart/Checkout Blocks compatibility is now declared.


== License ==
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.


== Version history ==

1.0.9 - Restricted Write API Key support and WooCommerce feature compatibility declarations
1.0.8 - Minor package updates
1.0.7 - Add support for premium version
1.0   - Initial version
