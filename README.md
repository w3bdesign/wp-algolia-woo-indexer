![Inspections](https://github.com/w3bdesign/algolia-woo-indexer/workflows/Inspections/badge.svg) ![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green)

# Algolia Woocommerce Indexer

## Description

This plugin sends products from WooCommerce to Algolia.

You need to add the Application ID and the Search-Only API Key from the "API keys" section in Algolia.

![Screenshot 1](/screenshots/screenshot1.jpg?raw=true "Screenshot 1") ![Screenshot 2](/screenshots/screenshot2.jpg?raw=true "Screenshot 1")

## Installation

* Login to Wordpress as Admin
* Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
* Activate the plugin through the 'Plugins' menu in WordPress
* Login to Algolia and go to 'API keys' and copy or write down the Application ID and the Search-Only API Key
* Go back to Wordpress and access plugin settings under 'Settings->Algolia Woo Indexer'
* Add the Application ID to the plugin settings page
* Add the Search-Only API Key to the plugin settings page
* Add the name of the index that should be used to index Woocommerce products
* Click on the 'Send products with Algolia' button to send the products to Algolia
