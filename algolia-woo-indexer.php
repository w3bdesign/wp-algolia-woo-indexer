<?php

/**
 * Plugin Name:     Algolia Woocommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Version:         0.0.1
 *
 * @package         algolia-woo-indexer
 */

require_once __DIR__ . '/vendor/autoload.php';

global $algolia;

$algolia = \Algolia\AlgoliaSearch\SearchClient::create("undefined", "YourAdminApiKey");

