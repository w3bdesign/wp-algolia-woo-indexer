<?php

/**
 * Class for sending products to Algolia API
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Define minimum required versions of PHP and WordPress
 */
define( 'ALGOLIA_MIN_PHP_VERSION', '7.2' );
define( 'ALGOLIA_MIN_WP_VERSION', '5.4' );

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AlgoliaSendProductsToAlgolia' ) ) {
	/**
	 * Check requirements for Algolia plugin
	 */
	class AlgoliaSendProductsToAlgolia {

		/**
		 * Send WooCommerce products to Algolia
		 *
		 * @param Int $id Product to send to Algolia if we send only a single product
		 * @return void
		 */
		public static function send_products_to_algolia( $id = '' ) {
			/**
			 * Remove classes from plugin URL and autoload Algolia with Composer
			 */

			$base_plugin_directory = str_replace( 'classes', '', dirname( __FILE__ ) );
			require_once $base_plugin_directory . '/vendor/autoload.php';

			/**
			 * Fetch the required variables from the Settings API
			 */

			$algolia_application_id = get_option( ALGOWOO_DB_OPTION . APPLICATION_ID );
			$algolia_api_key        = get_option( ALGOWOO_DB_OPTION . ADMIN_API_KEY );
			$algolia_index_name     = get_option( ALGOWOO_DB_OPTION . INDEX_NAME );

			$index_in_stock = get_option( ALGOWOO_DB_OPTION . INDEX_IN_STOCK );

			/**
			 * Display admin notice and return if not all values have been set
			 */
			if ( empty( $algolia_application_id ) || empty( $algolia_api_key || empty( $algolia_index_name ) ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error notice">
							  <p>' . esc_html__( 'All settings need to be set for the plugin to work.', 'algolia-woo-indexer' ) . '</p>
							</div>';
					}
				);
				return;
			}

			/**
			 * Initiate the Algolia client
			 */
			self::$algolia = \Algolia\AlgoliaSearch\SearchClient::create( $algolia_application_id, $algolia_api_key );

			/**
			 * Check if we can connect, if not, handle the exception, display an error and then return
			 */
			try {
				self::$algolia->listApiKeys();
			} catch ( \Algolia\AlgoliaSearch\Exceptions\UnreachableException $error ) {
				add_action(
					'admin_notices',
					function () {
												echo '<div class="error notice">
                                  <p>' . esc_html__( 'An error has been encountered. Please check your application ID and API key. ', 'algolia-woo-indexer' ) . '</p>
                                </div>';
					}
				);
				return;
			}

			/**
			 * Initialize the search index and set the name to the option from the database
			 */
			$index = self::$algolia->initIndex( $algolia_index_name );

			/**
			 * Setup arguments for sending all products to Algolia
			 *
			 * Limit => -1 means we send all products
			 */
			$arguments = array(
				'status'   => 'publish',
				'limit'    => -1,
				'paginate' => false,
			);

			/**
			 * Setup arguments for sending only a single product
			 */
			if ( isset( $id ) && '' !== $id ) {
				$arguments = array(
					'status'   => 'publish',
					'include'  => array( $id ),
					'paginate' => false,
				);
			}

			/**
			 * Fetch all products from WooCommerce
			 *
			 * @see https://docs.woocommerce.com/wc-apidocs/function-wc_get_products.html
			 */
			$products = wc_get_products( $arguments );

			if ( $products ) {
				$records = array();
				$record  = array();

				foreach ( $products as $product ) {
					/**
					 * Check if product is in stock if $index_in_stock is set to 1
					 */
					if ( '1' === $index_in_stock && $product->is_in_stock() ) {
						/**
						 * Extract image from $product->get_image()
						 */
						preg_match( '/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result );
						$product_image = array_pop( $result );
						/**
						 * Build the record array using the information from the WooCommerce product
						 */
						$record['objectID']          = $product->get_id();
						$record['product_name']      = $product->get_name();
						$record['product_image']     = $product_image;
						$record['short_description'] = $product->get_short_description();
						$record['regular_price']     = $product->get_regular_price();
						$record['sale_price']        = $product->get_sale_price();
						$record['on_sale']           = $product->is_on_sale();

						$records[] = $record;
					}
					/**
					 * Do not check if product is in stock if $index_in_stock is set to 0
					 */
					if ( '0' === $index_in_stock ) {
						/**
						 * Extract image from $product->get_image()
						 */
						preg_match( '/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result );
						$product_image = array_pop( $result );

						/**
						 * Build the record array using the information from the WooCommerce product
						 */
						$record['objectID']          = $product->get_id();
						$record['product_name']      = $product->get_name();
						$record['product_image']     = $product_image;
						$record['short_description'] = $product->get_short_description();
						$record['regular_price']     = $product->get_regular_price();
						$record['sale_price']        = $product->get_sale_price();
						$record['on_sale']           = $product->is_on_sale();

						$records[] = $record;
					}
				}
				wp_reset_postdata();
			}

			/**
			 * Send the information to Algolia and save the result
			 * If result is NullResponse, print an error message
			 */
			$result = $index->saveObjects( $records );

			if ( 'Algolia\AlgoliaSearch\Response\NullResponse' === get_class( $result ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error notice is-dismissible">
							  <p>' . esc_html__( 'No response from the server. Please check your settings and try again ', 'algolia-woo-indexer' ) . '</p>
							</div>';
					}
				);
				return;
			}

			/**
			 * Display success message
			 */
			echo '<div class="notice notice-success is-dismissible">
					 	<p>' . esc_html__( 'Product(s) sent to Algolia.', 'algolia-woo-indexer' ) . '</p>
				  		</div>';
		}
	}
}
