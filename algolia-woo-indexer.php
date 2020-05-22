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

function is_woocommerce_plugin_active() {
	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * If Woocommerce is not active, let users know.
 **/
if ( ! is_woocommerce_plugin_active() ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error notice">
			  	<p>' . __( 'Algolia Woocommerce Indexer: <a href="' . admin_url( 'plugin-install.php?s=Woocommerce&tab=search&type=term' ) . '">Woocommerce</a> plugin should be enabled.', 'algolia-woo-indexer' ) . '</p>
		  	  </div>';
	} );
}