<?php
/**
 * Plugin Name:     Algolia Woocommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Version:         1.0.0
 *
 * @package         algolia-woo-indexer
 * @license         GNU version 3
 */

/**
 * If this file is called directly, abort
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

$algowooindexer = \Algowoo\Algolia_Woo_Indexer::get_instance();

register_activation_hook( __FILE__, array( $algowooindexer, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $algowooindexer, 'deactivate_plugin' ) );
