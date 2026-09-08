<?php

/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use Algowoo\Algolia_Check_Requirements;
use Algowoo\Algolia_Verify_Nonces;
use Algowoo\Algolia_Send_Products;

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

if ( ! class_exists( 'Algolia_Woo_Indexer' ) ) {
	/**
	 * Algolia WooIndexer main class
	 */
	// TODO Rename class "Algolia_Woo_Indexer" to match the regular expression ^[A-Z][a-zA-Z0-9]*$.
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
			$api_key = get_option( ALGOWOO_DB_OPTION . ALGOLIA_API_KEY );
			$api_key = is_string( $api_key ) ? $api_key : CHANGE_ME;

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
			$application_id = get_option( ALGOWOO_DB_OPTION . ALGOLIA_APP_ID );
			$application_id = is_string( $application_id ) ? $application_id : CHANGE_ME;

			echo "<input id='algolia_woo_indexer_application_id' name='algolia_woo_indexer_application_id[id]'
				type='text' value='" . esc_attr( $application_id ) . "' />";
		}

		/**
		 * Output for index name field
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_index_name_output() {
			$index_name = get_option( ALGOWOO_DB_OPTION . INDEX_NAME );
			$index_name = is_string( $index_name ) ? $index_name : CHANGE_ME;

			echo "<input id='algolia_woo_indexer_index_name' name='algolia_woo_indexer_index_name[name]'
				type='text' value='" . esc_attr( $index_name ) . "' />";
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
			$auto_send = get_option( ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS );
			$auto_send = ( ! empty( $auto_send ) ) ? 1 : 0; ?>
			<input id="algolia_woo_indexer_automatically_send_new_products" name="algolia_woo_indexer_automatically_send_new_products[checked]" type="checkbox" <?php checked( 1, $auto_send ); ?> />
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
		 * Check if we are going to send products by verifying send products nonce
		 *
		 * Requires the manage_options capability and a valid nonce before
		 * any indexing is triggered. Registers a success or failure admin
		 * notice based on the result.
		 *
		 * @return void
		 */
		public static function maybe_send_products() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( true !== Algolia_Verify_Nonces::verify_send_products_nonce() ) {
				return;
			}

			$result = Algolia_Send_Products::send_products_to_algolia();

			if ( true === $result ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-success is-dismissible">
							  <p>' . esc_html__( 'Product(s) sent to Algolia.', 'algolia-woo-indexer' ) . '</p>
							</div>';
					}
				);
			}
		}

		/**
		 * Initialize class, setup settings sections and fields
		 *
		 * @return void
		 */
		public static function init() {

			/**
			 * Fetch the option to see if we are going to automatically send new products
			 */
			$auto_send = get_option( ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS );

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
				add_action( 'admin_init', array( $ob_class, 'update_settings_options' ) );
				add_action( 'admin_init', array( $ob_class, 'maybe_send_products' ) );

				/**
				 * Register hook to automatically send new products if the option is set
				 */

				if ( '1' === $auto_send ) {
					add_action( 'save_post', array( $ob_class, 'send_new_product_to_algolia' ), 10, 3 );
				}

				self::$plugin_url = admin_url( 'options-general.php?page=algolia-woo-indexer-settings' );

				if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
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
		 * Send a single product to Algolia once a new product has been published
		 *
		 * A failed background sync must never break the product-save flow, so
		 * any failure is ignored silently (logged only when WP_DEBUG is on).
		 *
		 * @param int   $post_id ID of the product.
		 * @param array $post Post array.
		 *
		 * @return void
		 */
		public static function send_new_product_to_algolia( $post_id, $post ) {
			if ( 'publish' !== $post->post_status || 'product' !== $post->post_type ) {
				return;
			}
			$result = Algolia_Send_Products::send_products_to_algolia( $post_id );

			if ( true !== $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Algolia Woo Indexer: background sync of product ' . absint( $post_id ) . ' failed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		/**
		 * Extract and sanitize a scalar value from a submitted settings field.
		 *
		 * Verifies that the filter_input() result is an array containing the
		 * expected key with a scalar value. Malformed POST data (missing keys,
		 * scalar instead of array) is treated as "not submitted".
		 *
		 * @param string $field_name POST field name to read.
		 * @param string $array_key  Expected key inside the submitted array.
		 *
		 * @return string|null Sanitized value, or null if the field was not submitted correctly.
		 */
		private static function get_sanitized_post_field( $field_name, $array_key ) {
			$submitted = filter_input( INPUT_POST, $field_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			if ( ! is_array( $submitted ) || ! isset( $submitted[ $array_key ] ) || ! is_scalar( $submitted[ $array_key ] ) ) {
				return null;
			}

			return sanitize_text_field( wp_unslash( (string) $submitted[ $array_key ] ) );
		}

		/**
		 * Update options and settings after capability and nonce verification.
		 *
		 * Fails closed: nothing is written unless this is a POST request with
		 * a valid settings nonce from a user with the manage_options capability.
		 *
		 * Persistence rules:
		 * - The auto-send checkbox is always persisted as '1' or '0' based on
		 *   its presence in the POST, so unchecking it is saved correctly.
		 * - Text fields (application ID, API key, index name) are persisted
		 *   when a non-empty sanitized value is submitted. A field submitted
		 *   intentionally blank is reset to the CHANGE_ME sentinel, which the
		 *   rest of the codebase treats as "unconfigured".
		 *
		 * @return void
		 */
		public static function update_settings_options() {
			/**
			 * Only act on POST requests that contain the settings form nonce field
			 */
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}
			if ( ! isset( $_POST['algolia_woo_indexer_admin_api_nonce_name'] ) ) {
				return;
			}

			/**
			 * Only users who can manage options may change plugin settings
			 */
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			/**
			 * Fail closed: invalid or missing nonce means no writes, ever
			 */
			if ( true !== Algolia_Verify_Nonces::verify_settings_nonce() ) {
				return;
			}

			/**
			 * Persist the text fields: non-empty values are saved as-is,
			 * blank submissions reset the option to the CHANGE_ME sentinel
			 */
			$text_fields = array(
				ALGOWOO_DB_OPTION . ALGOLIA_APP_ID  => self::get_sanitized_post_field( 'algolia_woo_indexer_application_id', 'id' ),
				ALGOWOO_DB_OPTION . ALGOLIA_API_KEY => self::get_sanitized_post_field( 'algolia_woo_indexer_admin_api_key', 'key' ),
				ALGOWOO_DB_OPTION . INDEX_NAME      => self::get_sanitized_post_field( 'algolia_woo_indexer_index_name', 'name' ),
			);

			foreach ( $text_fields as $option_key => $option_value ) {
				if ( null === $option_value ) {
					continue;
				}
				if ( '' === $option_value ) {
					update_option( $option_key, CHANGE_ME );
					continue;
				}
				update_option( $option_key, $option_value );
			}

			/**
			 * Always persist the checkbox as '1' or '0' so it can be disabled
			 */
			$auto_send = filter_input( INPUT_POST, 'algolia_woo_indexer_automatically_send_new_products', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			update_option(
				ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS,
				( is_array( $auto_send ) && isset( $auto_send['checked'] ) ) ? '1' : '0'
			);
		}

		/**
		 * Load text domain for translations
		 *
		 * @return void
		 */
		public static function load_textdomain() {
			load_plugin_textdomain( 'algolia-woo-indexer', false, basename( __DIR__ ) . '/languages/' );
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

			/**
			 * Set default values for options if not already set
			 */
			$auto_send              = get_option( ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS );
			$algolia_application_id = get_option( ALGOWOO_DB_OPTION . ALGOLIA_APP_ID );
			$algolia_api_key        = get_option( ALGOWOO_DB_OPTION . ALGOLIA_API_KEY );
			$algolia_index_name     = get_option( ALGOWOO_DB_OPTION . INDEX_NAME );

			if ( empty( $auto_send ) ) {
				add_option(
					ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS,
					'0'
				);
			}

			if ( empty( $algolia_application_id ) ) {
				add_option(
					ALGOWOO_DB_OPTION . ALGOLIA_APP_ID,
					'Change me'
				);
			}

			if ( empty( $algolia_api_key ) ) {
				add_option(
					ALGOWOO_DB_OPTION . ALGOLIA_API_KEY,
					'Change me'
				);
			}

			if ( empty( $algolia_index_name ) ) {
				add_option(
					ALGOWOO_DB_OPTION . INDEX_NAME,
					'Change me'
				);
			}
			set_transient( self::PLUGIN_TRANSIENT, true );
		}

		/**
		 * The actions to execute when the plugin is deactivated.
		 *
		 * @return void
		 */
		public static function deactivate_plugin() {
			delete_transient( self::PLUGIN_TRANSIENT );
		}
	}
}
