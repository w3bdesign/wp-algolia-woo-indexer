[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/quality/g/w3bdesign/algolia-woo-indexer/master?style=flat-square&logo=scrutinizer)](https://scrutinizer-ci.com/g/w3bdesign/algolia-woo-indexer/?branch=master)
[![Codacy Badge](https://img.shields.io/codacy/grade/bfe1f91c2d3a40e6953baabeee88f781?style=flat-square&logo=codacy)](https://app.codacy.com/gh/w3bdesign/wp-algolia-woo-indexer/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green?style=flat-square&logo=php)](https://github.com/w3bdesign/algolia-woo-indexer)

![Screenshot](/screenshots/screenshot1.jpg)

# Algolia WooCommerce Indexer

- Used in production at https://flora-di-berna.ch

## Description

This plugin transfers products from WooCommerce to Algolia. You can choose if it only sends products that are in stock.

You need to add the Application ID, a dedicated Write API Key from the API keys section in Algolia, and the index name. The key needs only the Add records (addObject) permission and must be restricted to that exact index. A Search API Key is read-only and cannot upload products; the Admin API Key is not required.

Note that this plugin is designed for developers developing headless Ecommerce solutions.

### This plugin will NOT submit products without a category

## Basic Features

- Manually or automatically submit WooCommerce products to Algolia
- Default options are added upon plugin activation
- Options are deleted from database upon plugin uninstallation (not on deactivation)
- Compatible with WooCommerce High-Performance Order Storage (HPOS) and Cart/Checkout Blocks; the plugin indexes products and does not access orders or modify checkout

## Advanced Features

- POT file for translations is included
- All code scanned and verified with PHPCS
- Code follows modern coding standards
- All variables are properly sanitized to ensure that no security issues are present
- AI Repository documentation located in `/DOCS/repository_context.txt`
- PHPCS Autofix with Github actions
- AI Assistant guidance files (`AGENTS.md` and `.roo/rules-*/AGENTS.md`) for immediate productivity with AI coding tools

## Requirements

- An account at <www.algolia.com> (either free or premium)
- Access to install and activate plugins (usually administrator rights)
- WordPress 7.1 or newer
- WooCommerce 11.2.1 or newer
- PHP 8.4 or newer
- PHP extensions/functions enabled: `mbstring`, `mbregex` and `cURL`
- All products MUST have a category assigned (Uncategorized is not a category), or they will not be submitted

## Installation

- Login to WordPress as Admin
- Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
- Activate the plugin through the `Plugins` menu in WordPress
- Log in to Algolia, open API keys, and copy the Application ID
- Create a dedicated custom API key with only the Add records (addObject) permission and restrict its allowed indices to the exact product index name (avoid wildcards)
- Go back to WordPress and access plugin settings under `Settings->Algolia Woo Indexer`
- Add the `Application ID` to the plugin settings page
- Add the custom key to the Write API Key field on the plugin settings page
- Add the name of the index that should be used to index WooCommerce products
- Click on the `Send products with Algolia` button to send the products to Algolia

### API key security and upgrades

The plugin checks connection access using a read-only task-status request that requires only Add records (addObject) permission. Uploads also validate access when records are sent. No key-management or index-listing permissions are needed. Keep the Write API Key server-side; use a separate Search API Key for browser or storefront searches.

Existing saved settings remain compatible. Updating the plugin does not replace or restrict an existing Admin API Key: create the dedicated write key in Algolia, replace the saved key in the plugin settings, and verify product uploads. Rotate the previously used Admin API Key after checking whether other integrations depend on it.

### Connection status

The top of the plugin settings page shows an automatic connection check with an emoji and explanatory message:

- ✅ Connected: Algolia accepted the credentials and permission check for the configured index.
- ❌ Not connected: the check failed; the message explains credential, permission, connectivity, or rate-limit issues.
- ⚙️ Not checked: save all three connection settings first. Blank values and default placeholders do not trigger a request.
- ⚠️ Not verified: Algolia responded, but the index or probe task was not found. Verify the index name; if the index is new, upload products to create it and reload the page.

The check runs once per settings-page load, including after saving settings. It does not run on unrelated admin pages, create tasks, upload test products, or poll in the background. Each check uses a single request with a five-second timeout. It verifies current access, not whether previous product uploads have completed.

## Troubleshooting

If you encounter any errors, first of all make sure that your hosting environment meets the requirements listed under Requirements.

For upload errors, also check connectivity, the Application ID, and that the Write API Key has Add records (addObject) permission for the configured index. Read-only keys and keys restricted to a different index cannot upload products.

Feel free to <a href="https://github.com/w3bdesign/algolia-woo-indexer/issues">open an issue</a> and I will do my best to troubleshoot and assist.

### TODO

- Upgrade Algolia package and check that it works correctly
