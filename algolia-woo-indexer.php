<?php
/**
 * Plugin Name:     Algolia WooCommerce Indexer
 * Description:     Implement Algolia indexing from WooCommerce
 * Text Domain:     algolia-woo-indexer
 * Author:          Daniel Fjeldstad
 * Requires at least: 7.1
 * Requires PHP: 8.4
 * WC requires at least: 11.2.1
 * Version:         1.0.9
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
require_once plugin_dir_path( __FILE__ ) . '/classes/class-algolia-woo-indexer.php';

/**
 * Class for checking plugin requirements
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-check-requirements.php';

/**
 * Class for verifying nonces
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-verify-nonces.php';

/**
 * Class for sending products
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-send-products.php';

/**
 * Class for checking the Algolia connection and displaying its status.
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-algolia-connection-status.php';

/**
 * Class for rendering the settings sections and fields.
 */
require_once plugin_dir_path( __FILE__ ) . '/classes/class-algolia-settings-fields.php';

$algowooindexer = \Algowoo\Algolia_Woo_Indexer::get_instance();

register_activation_hook( __FILE__, array( $algowooindexer, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $algowooindexer, 'deactivate_plugin' ) );
