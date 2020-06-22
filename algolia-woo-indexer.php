<?php

/**
 * Plugin Name:     Algolia Woocommerce Indexer
 * Description:     Implement Algolia indexing from Woocommerce
 * Text Domain:     algolia-woo-indexer
 * Version:         0.0.2
 *
 * @package         algolia-woo-indexer
 */

namespace ALGOWOO;

if ( ! class_exists( 'WooIndexer' ) ) {
	/**
	 * WooIndexer
	 */
	class WooIndexer {

		/**
		 * __construct
		 *
		 * @return void
		 */
		public function __construct() {     }

		/**
		 *  Init
		 *
		 * @return void
		 */
		public static function init() {     }
	}
}
