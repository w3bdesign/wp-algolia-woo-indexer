[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/w3bdesign/algolia-woo-indexer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/w3bdesign/algolia-woo-indexer/?branch=master)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/bfe1f91c2d3a40e6953baabeee88f781)](https://www.codacy.com/gh/w3bdesign/algolia-woo-indexer/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=w3bdesign/algolia-woo-indexer&amp;utm_campaign=Badge_Grade)
![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green)

![Screenshot](/screenshots/screenshot1.jpg)

# Algolia WooCommerce Indexer

## Description

This plugin transfers products from WooCommerce to Algolia. You can choose if it only sends products that are in stock.

You need to add the Application ID, the Admin API Key from the `API keys` section in Algolia as well as the Index Name.

Note that this plugin is designed for developers developing headless Ecommerce solutions.

### This plugin will NOT submit products without a category

## Basic Features

-   Manually or automatically submit WooCommerce products to Algolia
-   Default options are added upon plugin activation
-   Options are deleted from database upon plugin uninstallation (not on deactivation)

## Advanced Features

-   POT file for translations is included
-   All code scanned and verified with PHPCS
-   Code follows modern coding standards
-   All variables are properly sanitized to ensure that no security issues are present

## Requirements

-   An account at www.algolia.com (can be a free community plan)
-   Access to install and activate plugins (usually administrator rights)
-   WordPress 6.1.1 or newer
-   WooCommerce 7.4.0 or newer
-   PHP 8.1 or newer
-   PHP extensions/functions enabled: `mbstring`, `mbregex` and `cURL`
-   All products MUST have a category assigned (Uncategorized is not a category), or they will not be submitted

## Installation

-   Login to WordPress as Admin
-   Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
-   Activate the plugin through the `Plugins` menu in WordPress
-   Login to Algolia and go to `API keys` and copy or write down the Application ID and the `Admin API Key`
-   Go back to WordPress and access plugin settings under `Settings->Algolia Woo Indexer`
-   Add the `Application ID` to the plugin settings page
-   Add the `Admin API Key` to the plugin settings page
-   Add the name of the index that should be used to index WooCommerce products
-   Click on the `Send products with Algolia` button to send the products to Algolia

## Troubleshooting

If you encounter any errors, first of all make sure that your hosting environment meets the requirements listed under Requirements.

Feel free to <a href="https://github.com/w3bdesign/algolia-woo-indexer/issues">open an issue</a> and I will do my best to troubleshoot and assist.

### TODO

-   Upgrade Algolia package and check that it works correctly
