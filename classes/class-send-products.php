<?php
/**
 * Algolia Woo Indexer class for sending products
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use Algowoo\Algolia_Check_Requirements;

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include plugin file if function is_plugin_active does not exist
 */
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . '/wp-admin/includes/plugin.php';
}

/**
 * Define the plugin version and the database table name
 */
define( 'ALGOWOO_DB_OPTION', '_algolia_woo_indexer' );
define( 'ALGOWOO_CURRENT_DB_VERSION', '0.3' );

/**
 * Define application constants
 */
define( 'CHANGE_ME', 'change me' );

/**
 * Database table names
 */
define( 'INDEX_NAME', '_index_name' );
define( 'AUTOMATICALLY_SEND_NEW_PRODUCTS', '_automatically_send_new_products' );
define( 'ALGOLIA_APP_ID', '_application_id' );
define( 'ALGOLIA_SEARCH_API_KEY', '_search_api_key' );
// Keep the legacy option suffix so existing saved keys remain available.
define( 'ALGOLIA_API_KEY', '_admin_api_key' );

if ( ! class_exists( 'Algolia_Send_Products' ) ) {
	/**
	 * Algolia WooIndexer main class
	 *
	 * @todo Rename class "Algolia_Send_Products" to match the regular expression ^[A-Z][a-zA-Z0-9]*$.
	 */
	class Algolia_Send_Products {

		const PLUGIN_NAME      = 'Algolia Woo Indexer';
		const PLUGIN_TRANSIENT = 'algowoo-plugin-notice';

		/**
		 * The Algolia instance
		 *
		 * @var \Algolia\AlgoliaSearch\SearchClient
		 */
		private static $algolia = null;

		/**
		 * Get sale price or regular price based on product type
		 *
		 * @param  mixed $product Product to check.
		 * @return array ['sale_price' => $sale_price,'regular_price' => $regular_price] Array with regular price and sale price
		 */
		public static function get_product_type_price( $product ) {
			$sale_price    = 0;
			$regular_price = 0;
			if ( $product->is_type( 'simple' ) ) {
				$sale_price    = $product->get_sale_price();
				$regular_price = $product->get_regular_price();
			} elseif ( $product->is_type( 'variable' ) ) {
				$sale_price    = $product->get_variation_sale_price( 'min', true );
				$regular_price = $product->get_variation_regular_price( 'max', true );
			}
			return array(
				'sale_price'    => $sale_price,
				'regular_price' => $regular_price,
			);
		}

		/**
		 * Send WooCommerce products to Algolia
		 *
		 * Called statically from hook callbacks, so the requirement checks have to
		 * be reached statically.
		 *
		 * @SuppressWarnings("PHPMD.StaticAccess")
		 *
		 * @param int|string|null $id ID of a single product to send, or '' to send all published products.
		 * @return void
		 */
		public static function send_products_to_algolia( $id = '' ) {
			if ( ! self::load_composer_autoloader() ) {
				Algolia_Check_Requirements::queue_admin_notice(
					__( 'The Algolia PHP client is missing. Run "composer install" in the plugin directory.', 'algolia-woo-indexer' )
				);

				return;
			}

			$algolia_application_id = self::get_setting( ALGOLIA_APP_ID );
			$algolia_api_key        = self::get_setting( ALGOLIA_API_KEY );
			$algolia_index_name     = self::get_setting( INDEX_NAME );

			// Queues its own admin notice when a value is missing.
			if ( ! Algolia_Check_Requirements::check_algolia_input_values( $algolia_application_id, $algolia_api_key, $algolia_index_name ) ) {
				return;
			}

			self::$algolia = \Algolia\AlgoliaSearch\SearchClient::create( $algolia_application_id, $algolia_api_key );
			$index         = self::$algolia->initIndex( $algolia_index_name );

			/**
			 * Fetch the published products from WooCommerce
			 *
			 * @see https://docs.woocommerce.com/wc-apidocs/function-wc_get_products.html
			 */
			$products = wc_get_products( self::build_query_arguments( $id ) );

			if ( empty( $products ) ) {
				return;
			}

			$records = self::build_records( $products );
			wp_reset_postdata();

			$result = self::save_records( $index, $records );

			// save_records() has already queued an error notice.
			if ( null === $result ) {
				return;
			}

			if ( 'Algolia\AlgoliaSearch\Response\NullResponse' === get_class( $result ) ) {
				Algolia_Check_Requirements::queue_admin_notice(
					__( 'No response from the server. Please check your settings and try again.', 'algolia-woo-indexer' )
				);

				return;
			}

			self::queue_success_notice( count( $records ), $algolia_index_name );
		}

		/**
		 * Load the Composer autoloader that provides the Algolia client.
		 *
		 * @return bool True when the autoloader exists and was loaded.
		 */
		private static function load_composer_autoloader() {
			// Remove "classes" from the directory to get the plugin root.
			$base_plugin_directory = str_replace( 'classes', '', __DIR__ );
			$autoloader            = $base_plugin_directory . '/vendor/autoload.php';

			if ( ! file_exists( $autoloader ) ) {
				return false;
			}

			require_once $autoloader;

			return true;
		}

		/**
		 * Read a connection setting, falling back to the placeholder when it is not a string.
		 *
		 * @param string $suffix Option suffix appended to ALGOWOO_DB_OPTION.
		 * @return string
		 */
		private static function get_setting( $suffix ) {
			$value = get_option( ALGOWOO_DB_OPTION . $suffix );

			return is_string( $value ) ? $value : CHANGE_ME;
		}

		/**
		 * Build the wc_get_products() arguments for one product or for all of them.
		 *
		 * Limit => -1 means every published product is fetched.
		 *
		 * @param int|string|null $product_id ID of a single product, or '' or null for all products.
		 * @return array<string, mixed>
		 */
		private static function build_query_arguments( $product_id ) {
			if ( isset( $product_id ) && '' !== $product_id ) {
				return array(
					'status'   => 'publish',
					'include'  => array( $product_id ),
					'paginate' => false,
				);
			}

			return array(
				'status'   => 'publish',
				'limit'    => -1,
				'paginate' => false,
			);
		}

		/**
		 * Extract the image URL from the product's image HTML.
		 *
		 * @param \WC_Product $product Product to read the image from.
		 * @return string|null The image URL, or null when no image tag matches.
		 */
		private static function get_product_image_url( $product ) {
			preg_match( '/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result );

			return array_pop( $result );
		}

		/**
		 * Build one Algolia record from a WooCommerce product.
		 *
		 * @param \WC_Product $product Product to convert.
		 * @return array<string, mixed>
		 */
		private static function build_record( $product ) {
			$product_type_price = self::get_product_type_price( $product );

			return array(
				'objectID'          => $product->get_id(),
				'product_name'      => $product->get_name(),
				'product_image'     => self::get_product_image_url( $product ),
				'short_description' => $product->get_short_description(),
				'regular_price'     => $product_type_price['regular_price'],
				'sale_price'        => $product_type_price['sale_price'],
				'on_sale'           => $product->is_on_sale(),
			);
		}

		/**
		 * Build the Algolia records for a list of WooCommerce products.
		 *
		 * @param array<int, \WC_Product> $products Products to convert.
		 * @return array<int, array<string, mixed>>
		 */
		private static function build_records( $products ) {
			$records = array();

			foreach ( $products as $product ) {
				$records[] = self::build_record( $product );
			}

			return $records;
		}

		/**
		 * Save the records to the index, queueing an error notice if Algolia rejects them.
		 *
		 * Access is validated through the write itself, so the key needs no key or
		 * index listing permissions.
		 *
		 * @param \Algolia\AlgoliaSearch\SearchIndex $index   Index to write to.
		 * @param array<int, array<string, mixed>>   $records Records to save.
		 * @return object|null The client response, or null when Algolia threw an exception.
		 */
		private static function save_records( $index, $records ) {
			try {
				return $index->saveObjects( $records );
			} catch ( \Algolia\AlgoliaSearch\Exceptions\AlgoliaException $error ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error notice">
							<p>' . esc_html__( 'Unable to send products to Algolia. Check your connection, Application ID, index name, and Write API Key. The key must have addObject permission for the configured index. A Search API Key cannot upload products.', 'algolia-woo-indexer' ) . '</p>
							</div>';
					}
				);

				return null;
			}
		}

		/**
		 * Queue the success message for admin_notices.
		 *
		 * The message is queued rather than echoed, because this runs from an
		 * admin_init callback.
		 *
		 * @param int    $product_count Number of products sent.
		 * @param string $index_name    Algolia index name.
		 * @return void
		 */
		private static function queue_success_notice( $product_count, $index_name ) {
			$message = sprintf(
				/* translators: 1: Number of products sent, 2: Algolia index name. */
				_n(
					'%1$s product sent to Algolia index "%2$s". Indexing may still be in progress.',
					'%1$s products sent to Algolia index "%2$s". Indexing may still be in progress.',
					$product_count,
					'algolia-woo-indexer'
				),
				number_format_i18n( $product_count ),
				$index_name
			);

			add_action(
				'admin_notices',
				function () use ( $message ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( $message )
					);
				}
			);
		}
	}
}
