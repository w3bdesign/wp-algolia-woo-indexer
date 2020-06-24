<?php
/**
 * Plugin Name:     Algolia Woocommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Version:         0.0.4
 *
 * @package         algolia-woo-indexer
 */

/**
 * If this file is called directly, abort
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/classes/class-algolia-woo-indexer.php';
$algowooindexer = \ALGOWOO\Algolia_Woo_Indexer::get_instance();

register_activation_hook( __FILE__, array( $algowooindexer, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $algowooindexer, 'deactivate_plugin' ) );
