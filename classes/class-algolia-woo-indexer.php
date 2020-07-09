<?php

/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use \Algowoo\Algolia_Check_Requirements as Algolia_Check_Requirements;

/**
 * Define the plugin version and the database table name
 */
define( 'ALGOWOO_DB_OPTION', '_algolia_woo_indexer' );
define( 'ALGOWOO_CURRENT_DB_VERSION', '0.3' );

if ( ! class_exists( 'Algolia_Woo_Indexer' ) ) {
	/**
	 * Algolia WooIndexer main class
	 */
	class Algolia_Woo_Indexer {

		const PLUGIN_NAME      = 'Algolia Woo Indexer';
		const PLUGIN_TRANSIENT = 'algowoo-plugin-notice';

		/**
		 * Class instance
		 *
		 * @var object
		 */
		private static $instance;

		/**
		 * The plugin URL
		 *
		 * @var string
		 */
		private static $plugin_url = '';

		/**
		 * The Algolia instance
		 *
		 * @var string
		 */
		private static $algolia = null;

		/**
		 * Class constructor
		 *
		 * @return void
		 */
		public function __construct() {
			$this->init();
		}

		/**
		 * Setup sections and fields to store and retrieve values from Settings API
		 *
		 * @return void
		 */
		public static function setup_settings_sections() {
			/**
			* Setup arguments for settings sections and fields
			*
			* @see https://developer.wordpress.org/reference/functions/register_setting/
			*/
			if ( is_admin() ) {
				$arguments = array(
					'type'              => 'string',
					'sanitize_callback' => 'settings_fields_validate_options',
					'default'           => null,
				);
				register_setting( 'algolia_woo_options', 'algolia_woo_options', $arguments );

				/**
				 * Make sure we reference the instance of the current class by using self::get_instance()
				 * This way we can setup the correct callback function for add_settings_section and add_settings_field
				 */
				$algowooindexer = self::get_instance();

				/**
				 * Add our necessary settings sections and fields
				 */
				add_settings_section(
					'algolia_woo_indexer_main',
					esc_html__( 'Algolia Woo Plugin Settings', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_section_text' ),
					'algolia_woo_indexer'
				);
				add_settings_field(
					'algolia_woo_indexer_application_id',
					esc_html__( 'Application ID', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_application_id_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
				add_settings_field(
					'algolia_woo_indexer_admin_api_key',
					esc_html__( 'Admin API Key', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_admin_api_key_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
				add_settings_field(
					'algolia_woo_indexer_index_name',
					esc_html__( 'Index name (will be created if not existing)', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_index_name_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
				add_settings_field(
					'algolia_woo_indexer_index_in_stock',
					esc_html__( 'Only index products in stock', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_index_in_stock_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
				add_settings_field(
					'algolia_woo_indexer_automatically_send_new_products',
					esc_html__( 'Automatically index new products', 'algolia-woo-indexer' ),
					array( $algowooindexer, 'algolia_woo_indexer_automatically_send_new_products_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
			}
		}

		/**
		 * Output for admin API key field
		 *
		 * @see https://developer.wordpress.org/reference/functions/wp_nonce_field/
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_admin_api_key_output() {
			$api_key = get_option( ALGOWOO_DB_OPTION . '_admin_api_key' );

			wp_nonce_field( 'algolia_woo_indexer_admin_api_nonce_action', 'algolia_woo_indexer_admin_api_nonce_name' );

			echo "<input id='algolia_woo_indexer_admin_api_key' name='algolia_woo_indexer_admin_api_key[key]'
				type='text' value='" . esc_attr( $api_key ) . "' />";
		}

		/**
		 * Output for application ID field
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_application_id_output() {
			$application_id = get_option( ALGOWOO_DB_OPTION . '_application_id' );

			echo "<input id='algolia_woo_indexer_application_id' name='algolia_woo_indexer_application_id[id]'
				type='text' value='" . esc_attr( $application_id ) . "' />";
		}

		/**
		 * Output for index name field
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_index_name_output() {
			$index_name = get_option( ALGOWOO_DB_OPTION . '_index_name' );

			echo "<input id='algolia_woo_indexer_index_name' name='algolia_woo_indexer_index_name[name]'
				type='text' value='" . esc_attr( $index_name ) . "' />";
		}

		/**
		 * Output for checkbox to check if we send products that are in stock
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_index_in_stock_output() {
			/**
			 * Sanitization is not really needed as the variable is not directly echoed
			 * But I have still done it to be 100% safe
			 */
			$index_in_stock = get_option( ALGOWOO_DB_OPTION . '_index_in_stock' );
			$index_in_stock = ( ! empty( $index_in_stock ) ) ? 1 : 0;
			?>
			<input id="algolia_woo_indexer_index_in_stock" name="algolia_woo_indexer_index_in_stock[checked]"
			type="checkbox" <?php checked( 1, $index_in_stock ); ?> />
			<?php
		}

		/**
		 * Output for checkbox to check if we automatically send new products to Algolia
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_automatically_send_new_products_output() {
			/**
			 * Sanitization is not really needed as the variable is not directly echoed
			 * But I have still done it to be 100% safe
			 */
			$automatically_send_new_products = get_option( ALGOWOO_DB_OPTION . '_automatically_send_new_products' );
			$automatically_send_new_products = ( ! empty( $automatically_send_new_products ) ) ? 1 : 0;
			?>
			<input id="algolia_woo_indexer_automatically_send_new_products" name="algolia_woo_indexer_automatically_send_new_products[checked]"
			type="checkbox" <?php checked( 1, $automatically_send_new_products ); ?> />
			<?php
		}


		/**
		 * Section text for plugin settings section text
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_section_text() {
			echo esc_html__( 'Enter your settings here', 'algolia-woo-indexer' );
		}

		/**
		 * Initialize class, setup settings sections and fields
		 *
		 * @return void
		 */
		public static function init() {
			/**
			 * Check that we have the minimum versions required and all of the required PHP extensions
			 */
			Algolia_Check_Requirements::check_unmet_requirements();

			if ( ! Algolia_Check_Requirements::algolia_wp_version_check() || ! Algolia_Check_Requirements::algolia_php_version_check() ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error notice">
                                  <p>' . esc_html__( 'Please check the server requirements for Algolia Woo Indexer. <br/> It requires minimum PHP version 7.2 and WordPress version 5.0', 'algolia-woo-indexer' ) . '</p>
                                </div>';
					}
				);
			}

			$ob_class = get_called_class();

			/**
			 * Setup translations
			 */
			add_action( 'plugins_loaded', array( $ob_class, 'load_textdomain' ) );

			/**
			 * Add actions to setup admin menu
			 */
			if ( is_admin() ) {
				add_action( 'admin_menu', array( $ob_class, 'admin_menu' ) );
				add_action( 'admin_init', array( $ob_class, 'setup_settings_sections' ) );
				add_action( 'admin_init', array( $ob_class, 'verify_settings_nonce' ) );

				self::$plugin_url = admin_url( 'options-general.php?page=algolia-woo-indexer-settings' );

				if ( ! Algolia_Check_Requirements::is_woocommerce_plugin_active() ) {
					add_action(
						'admin_notices',
						function () {
							echo '<div class="error notice">
								  <p>' . esc_html__( 'WooCommerce plugin must be enabled for Algolia Woo Indexer to work.', 'algolia-woo-indexer' ) . '</p>
								</div>';
						}
					);
				}
			}
		}

		/**
		 * Verify nonces before we update options and settings
		 * Also retrieve the value from the send_products_to_algolia hidden field to check if we are sending products to Algolia
		 *
		 * @return void
		 */
		public static function verify_settings_nonce() {
			/**
			 * Filter incoming nonces and values
			 */
			$settings_nonce           = filter_input( INPUT_POST, 'algolia_woo_indexer_admin_api_nonce_name', FILTER_DEFAULT );
			$send_products_nonce      = filter_input( INPUT_POST, 'send_products_to_algolia_nonce_name', FILTER_DEFAULT );
			$send_products_to_algolia = filter_input( INPUT_POST, 'send_products_to_algolia', FILTER_DEFAULT );

			/**
			 * Return if if no nonce has been set for either of the two forms
			 */
			if ( ! isset( $settings_nonce ) && ! isset( $send_products_nonce ) ) {
				return;
			}

			/**
			 * Display error and die if nonce is not verified and does not pass security check
			 * Also check if the hidden value field send_products_to_algolia is set
			 */
			if ( ! wp_verify_nonce( $settings_nonce, 'algolia_woo_indexer_admin_api_nonce_action' ) && ! isset( $send_products_to_algolia ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ) );
			}

			if ( ! wp_verify_nonce( $send_products_nonce, 'send_products_to_algolia_nonce_action' ) && isset( $send_products_to_algolia ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ) );
			}

			/**
			 * If we have verified the send_products_nonce and the send_products hidden field is set, call the function to send the products
			 */
			if ( wp_verify_nonce( $send_products_nonce, 'send_products_to_algolia_nonce_action' ) && isset( $send_products_to_algolia ) ) {
				self::send_products_to_algolia();
				return;
			}

			/**
			 * Filter the application id, api key, index name and verify that the input is an array
			 *
			 * @see https://www.php.net/manual/en/function.filter-input.php
			 */
			$post_application_id             = filter_input( INPUT_POST, 'algolia_woo_indexer_application_id', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$post_api_key                    = filter_input( INPUT_POST, 'algolia_woo_indexer_admin_api_key', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$post_index_name                 = filter_input( INPUT_POST, 'algolia_woo_indexer_index_name', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$index_in_stock                  = filter_input( INPUT_POST, 'algolia_woo_indexer_index_in_stock', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$automatically_send_new_products = filter_input( INPUT_POST, 'algolia_woo_indexer_automatically_send_new_products', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			/**
			 * Properly sanitize text fields before updating data
			 *
			 * @see https://developer.wordpress.org/reference/functions/sanitize_text_field/
			 */
			$filtered_application_id = sanitize_text_field( $post_application_id['id'] );
			$filtered_api_key        = sanitize_text_field( $post_api_key['key'] );
			$filtered_index_name     = sanitize_text_field( $post_index_name['name'] );

			/**
			 * No need for sanitizing here as we set the value to either 1 or 0
			 */
			$filtered_index_in_stock                  = ( ! empty( $index_in_stock ) ) ? 1 : 0;
			$filtered_automatically_send_new_products = ( ! empty( $automatically_send_new_products ) ) ? 1 : 0;

			/**
			 * Values have been filtered and sanitized
			 * Check if set and update the database with update_option with the submitted values
			 *
			 * @see https://developer.wordpress.org/reference/functions/update_option/
			 */
			if ( isset( $filtered_application_id ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_application_id',
					$filtered_application_id
				);
			}

			if ( isset( $filtered_api_key ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_admin_api_key',
					$filtered_api_key
				);
			}

			if ( isset( $filtered_index_name ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_index_name',
					$filtered_index_name
				);
			}

			if ( isset( $filtered_index_in_stock ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_index_in_stock',
					$filtered_index_in_stock
				);
			}

			if ( isset( $filtered_index_name ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_automatically_send_new_products',
					$filtered_automatically_send_new_products
				);
			}
		}

		/**
		 * Send WooCommerce products to Algolia
		 *
		 * @return void
		 */
		public static function send_products_to_algolia() {
			/**
			 * Remove classes from plugin URL and autoload Algolia with Composer
			 */

			$base_plugin_directory = str_replace( 'classes', '', dirname( __FILE__ ) );

			require_once $base_plugin_directory . '/vendor/autoload.php';

			/**
			 * Fetch the required variables from the Settings API
			 */

			$algolia_application_id = get_option( ALGOWOO_DB_OPTION . '_application_id' );
			$algolia_api_key        = get_option( ALGOWOO_DB_OPTION . '_admin_api_key' );
			$algolia_index_name     = get_option( ALGOWOO_DB_OPTION . '_index_name' );

			$index_in_stock                  = get_option( ALGOWOO_DB_OPTION . '_index_in_stock' );
			$automatically_send_new_products = get_option( ALGOWOO_DB_OPTION . '_automatically_send_new_products' );

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
					 * Check if product is in stock if $index_in_stock is set to 0
					 */
					if ( '1' === $index_in_stock && $product->is_in_stock() ) {

						/**
						 * Extract image from $product->get_image()
						 */
						preg_match_all( '/<img.*?src=[\'"]( . * ? )[\'"].*?>/i', $product->get_image(), $matches );
						$product_image = implode( $matches[1] );
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
						$record['short_description'] = $product->get_short_description();

						$records[] = $record;
					}
					/**
					 * Do not check if product is in stock if $index_in_stock is set to 0
					 */
					if ( '0' === $index_in_stock ) {
						/**
						 * Extract image from $product->get_image()
						 */
						preg_match_all( '/<img.*?src=[\'"]( . * ? )[\'"].*?>/i', $product->get_image(), $matches );
						$product_image = implode( $matches[1] );
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
						$record['short_description'] = $product->get_short_description();

						$records[] = $record;
					}
				}
				wp_reset_postdata();
			}

			/**
			 * Send the information to Algolia
			 */
			$result = $index->saveObjects( $records );

			// print_r( $result );

			/**
			 * Display success message
			 */
			echo '<div class="notice notice-success is-dismissible">
					 	<p>' . esc_html__( 'Product(s) sent to Algolia.', 'algolia-woo-indexer' ) . '</p>
				  		</div>';
		}

		/**
		 * Sanitize input in settings fields and filter through regex to accept only a-z and A-Z
		 *
		 * @param string $input Settings text data
		 * @return array
		 */
		public static function settings_fields_validate_options( $input ) {
			$valid         = array();
			$valid['name'] = preg_replace(
				'/[^a-zA-Z\s]/',
				'',
				$input['name']
			);
			return $valid;
		}

		/**
		 * Load text domain for translations
		 *
		 * @return void
		 */
		public static function load_textdomain() {
			load_plugin_textdomain( 'algolia-woo-indexer', false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Add the new menu to settings section so that we can configure the plugin
		 *
		 * @return void
		 */
		public static function admin_menu() {
			add_submenu_page(
				'options-general.php',
				esc_html__( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ),
				esc_html__( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ),
				'manage_options',
				'algolia-woo-indexer-settings',
				array( get_called_class(), 'algolia_woo_indexer_settings' )
			);
		}

		/**
		 * Display settings and allow user to modify them
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_settings() {
			/**
			* Verify that the user can access the settings page
			*/
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Action not allowed.', 'algolia_woo_indexer_settings' ) );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html__( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ); ?></h1>
				<form action="<?php echo esc_url( self::$plugin_url ); ?>" method="POST">
			<?php
			settings_fields( 'algolia_woo_options' );
			do_settings_sections( 'algolia_woo_indexer' );
			submit_button( '', 'primary wide' );
			?>
				</form>
				<form action="<?php echo esc_url( self::$plugin_url ); ?>" method="POST">
					<?php wp_nonce_field( 'send_products_to_algolia_nonce_action', 'send_products_to_algolia_nonce_name' ); ?>
					<input type="hidden" name="send_products_to_algolia" id="send_products_to_algolia" value="true" />
					<?php submit_button( esc_html__( 'Send products to Algolia', 'algolia_woo_indexer_settings' ), 'primary wide', '', false ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Get active object instance
		 *
		 * @return object
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new Algolia_Woo_Indexer();
			}
			return self::$instance;
		}

		/**
		 * The actions to execute when the plugin is activated.
		 *
		 * @return void
		 */
		public static function activate_plugin() {
			set_transient( self::PLUGIN_TRANSIENT, true );
		}

		/**
		 * The actions to execute when the plugin is deactivated.
		 *
		 * @return void
		 */
		public static function deactivate_plugin() {
			delete_transient( self::PLUGIN_TRANSIENT, true );
		}
	}
}
