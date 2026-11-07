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
 * License:         AGPLv3 or later
 * License URI:     https://www.gnu.org/licenses/agpl-3.0.html
 *
 * @package         algolia-woo-indexer
 * @license         AGPL-3.0-or-later
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

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage and
 * Cart/Checkout Blocks. This plugin only reads products, so it is unaffected by
 * the order storage backend.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
);
