![Inspections](https://github.com/w3bdesign/algolia-woo-indexer/workflows/Inspections/badge.svg) ![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green)


# Algolia Woocommerce Indexer

## Description

This plugin indexes products inside of Woocommerce and submits them to Algolia.

You need to add the Application ID and the Search-Only API Key from the "API keys" section in Algolia.

## Installation

* Login to Wordpress as Admin
* Upload `Algolia Woo Indexer` to the `/wp-content/plugins/` directory of your application
* Activate the plugin through the 'Plugins' menu in WordPress
* Login to Algolia and go to 'API keys' and copy or write down the Application ID and the Search-Only API Key
* Go back to Wordpress and access plugin settings under 'Settings->Algolia Woo Indexer'
* Add the Application ID to the plugin settings page
* Add the Search-Only API Key to the plugin settings page
* Add the name of the index that should be used to index Woocommerce products (default is wp_products)
* Click on the 'Sync now!' button to send the products to Algolia
