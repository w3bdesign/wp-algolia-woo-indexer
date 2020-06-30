![Inspections](https://github.com/w3bdesign/algolia-woo-indexer/workflows/Inspections/badge.svg) ![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green)

![Screenshot](/screenshots/screenshot1.jpg)

# Algolia Woocommerce Indexer

## Description

This plugin sends products from WooCommerce to Algolia.

You need to add the Application ID and the Admin API Key from the `API keys` section in Algolia.

Note that this plugin is designed for developers developing headless Ecommerce solutions.
 
As a result, this plugin requires SSH access to the server where Wordpress is installed.

This is NOT a plugin for beginners.

If for any reason you are unable to use Composer, I have created a version of the plugin with /vendor included, which should work without Composer.

However, this is NOT the recommended method for deployment. Use at your own risk. 

You can find it here: <a href="https://github.com/w3bdesign/algolia-woo-indexer/tree/version-without-composer">https://github.com/w3bdesign/algolia-woo-indexer/tree/version-without-composer</a>


## Requirements

* Access to install and activate plugins (usually administrator rights)
* Wordpress 5.4
* PHP 7.2
* PHP extensions/functions enabled: `mbstring`, `mbregex` and `cURL`
* SSH access to the server with Composer installed

## Installation

* Login to Wordpress as Admin
* Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
* SSH to the server and navigate to the folder where the plugin is installed and do a `composer install` to properly setup all required dependencies
* Activate the plugin through the `Plugins` menu in WordPress
* Login to Algolia and go to `API keys` and copy or write down the Application ID and the `Admin API Key`
* Go back to Wordpress and access plugin settings under `Settings->Algolia Woo Indexer`
* Add the `Application ID` to the plugin settings page
* Add the `Admin API Key` to the plugin settings page
* Add the name of the index that should be used to index Woocommerce products
* Click on the `Send products with Algolia` button to send the products to Algolia
