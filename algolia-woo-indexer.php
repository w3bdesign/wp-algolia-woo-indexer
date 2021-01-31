<?php
/**
 * Plugin Name:     Algolia WooCommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Author:          Daniel F
 * Requires at least: 5.5
 * Tested up to: 5.5
 * Requires PHP: 7.3
 * WC requires at least: 4.6.1
 * WC tested up to: 4.6.1
 * Version:         1.0.31
 *
 * @package         algolia-woo-indexer
 * @license         GNU version 3
 */

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class file
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-algoliawooindexer.php';

/**
 * Class for sending Algolia products
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-send-products-to-algolia.php';

/**
 * Class for checking plugin requirements
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-check-requirements.php';

/**
 * Class for verifying nonces
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-verify-nonces.php';

$algowooindexer = \Algowoo\AlgoliaWooIndexer::get_instance();

register_activation_hook( __FILE__, array( $algowooindexer, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $algowooindexer, 'deactivate_plugin' ) );
