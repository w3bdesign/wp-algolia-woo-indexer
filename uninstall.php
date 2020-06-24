<?php
/**
 * Delete the saved options freom the database when we uninstall the plugin
 * We do not do it when we deactivate the plugin, in case we want to maintain the data
 *
 * @package         algolia-woo-indexer
 */

define( 'ALGOWOO_DB_OPTION', '_algolia_woo_indexer' );

delete_option( ALGOWOO_DB_OPTION . '_application_id' );
delete_option( ALGOWOO_DB_OPTION . '_api_search_key' );
