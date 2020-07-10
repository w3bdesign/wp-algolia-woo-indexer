![Inspections](https://github.com/w3bdesign/algolia-woo-indexer/workflows/Inspections/badge.svg) ![PHP Linter](https://img.shields.io/badge/Code%20checked%20with-PHPCS-green)

![Screenshot](/screenshots/screenshot1.jpg)

# Algolia WooCommerce Indexer

## Description

This plugin sends products from WooCommerce to Algolia. You can choose if it only sends products that are in stock.

You need to add the Application ID, the Admin API Key from the `API keys` section in Algolia as well as the Index Name.

Note that this plugin is designed for developers developing headless Ecommerce solutions.

## Important notice: This plugin will NOT submit products without a category.

## Basic Features 

* Manually or automatically submit WooCommerce products to Algolia
* Option to submit only products that are in stock
* Default options are added upon plugin activation
* Options are deleted from database upon plugin uninstallation (not on deactivation)

## Advanced Features

* POT file for translations is included
* All code scanned and verified with PHPCS
* Code follows modern coding standards
* All variables are properly sanitized to ensure that no security issues are present

## Requirements

* An account at www.algolia.com (can be a free community plan)
* Access to install and activate plugins (usually administrator rights)
* Wordpress 5.4
* WooCommerce 4.3.0
* PHP 7.2
* PHP extensions/functions enabled: `mbstring`, `mbregex` and `cURL`
* All products need to have a category, or they will not be submitted

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
