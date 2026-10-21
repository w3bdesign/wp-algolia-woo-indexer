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
 * Declare compatibility with WooCommerce features that do not affect product indexing.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

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

$algowooindexer = \Algowoo\Algolia_Woo_Indexer::get_instance();

register_activation_hook( __FILE__, array( $algowooindexer, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $algowooindexer, 'deactivate_plugin' ) );
