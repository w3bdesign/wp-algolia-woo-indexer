<?php
/**
 * Plugin Name:     Algolia Woocommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Version:         0.0.2
 *
 * @package         algolia-woo-indexer
 */

add_action( 'plugins_loaded', 'indexer_plugin_bootstrap' );

/**
 * pdev_plugin_bootstrap
 *
 * @return void
 */
function indexer_plugin_bootstrap() {

	require_once plugin_dir_path( __FILE__ ) . 'class-setup.php';

	$setup = new \ALGOWOO\Setup();

	$setup->boot();
}
