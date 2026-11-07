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
		 * Connection-status service shared by the singleton's settings renderer.
		 *
		 * @var AlgoliaConnectionStatus
		 */
		private $connection_status;

		/**
		 * Class constructor
		 *
		 * @param AlgoliaConnectionStatus|null $connection_status Optional connection-status service.
		 * @return void
		 */
		public function __construct( ?AlgoliaConnectionStatus $connection_status = null ) {
			$this->connection_status = $connection_status ?? new AlgoliaConnectionStatus();
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
					'sanitize_callback' => array( self::class, 'settings_fields_validate_options' ),
					'default'           => null,
				);
				register_setting( 'algolia_woo_options', 'algolia_woo_options', $arguments );

				/**
				 * Add our necessary settings sections and fields.
				 * Field output is handled by the AlgoliaSettingsFields class.
				 */
				add_settings_section(
					'algolia_woo_indexer_main',
					esc_html__( 'Algolia Woo Plugin Settings', 'algolia-woo-indexer' ),
					array( AlgoliaSettingsFields::class, 'render_section_text' ),
					'algolia_woo_indexer'
				);
				/**
				 * Field id => array( title, render method, whether the title label targets the input ).
				 * The automatic indexing checkbox renders its own label, so it has no label_for.
				 */
				$fields = array(
					'algolia_woo_indexer_application_id' => array( esc_html__( 'Application ID', 'algolia-woo-indexer' ), 'render_application_id_field', true ),
					'algolia_woo_indexer_search_api_key' => array( esc_html__( 'Search API Key', 'algolia-woo-indexer' ), 'render_search_api_key_field', true ),
					'algolia_woo_indexer_admin_api_key'  => array( esc_html__( 'Write API Key', 'algolia-woo-indexer' ), 'render_write_api_key_field', true ),
					'algolia_woo_indexer_index_name'     => array( esc_html__( 'Index name', 'algolia-woo-indexer' ), 'render_index_name_field', true ),
					'algolia_woo_indexer_automatically_send_new_products' => array( esc_html__( 'Automatic indexing', 'algolia-woo-indexer' ), 'render_auto_send_field', false ),
				);

				foreach ( $fields as $field_id => $field ) {
					add_settings_field(
						$field_id,
						$field[0],
						array( AlgoliaSettingsFields::class, $field[1] ),
						'algolia_woo_indexer',
						'algolia_woo_indexer_main',
						$field[2] ? array( 'label_for' => $field_id ) : array()
					);
				}
			}
		}

		/**
		 * Check if we are going to send products by verifying send products nonce
		 *
		 * Registered as a static `admin_init` callback, so the collaborating classes
		 * have to be reached statically.
		 *
		 * @SuppressWarnings(PHPMD.StaticAccess)
		 *
		 * @return void
		 */
		public static function maybe_send_products() {
			if ( true === Algolia_Verify_Nonces::verify_send_products_nonce() ) {
				Algolia_Send_Products::send_products_to_algolia();
			}
		}

		/**
		 * Initialize class, setup settings sections and fields
		 *
		 * Called statically from the constructor before any instance is available,
		 * so the requirement checks have to be reached statically.
		 *
		 * @SuppressWarnings(PHPMD.StaticAccess)
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
                                  <p>' . sprintf(
							/* translators: 1: Minimum PHP version, 2: Minimum WordPress version. */
							esc_html__( 'Please check the server requirements for Algolia Woo Indexer. It requires minimum PHP version %1$s and WordPress version %2$s.', 'algolia-woo-indexer' ),
							esc_html( ALGOLIA_MIN_PHP_VERSION ),
							esc_html( ALGOLIA_MIN_WP_VERSION )
						) . '</p>
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
			/**
			 * Register the automatic indexing hook outside of is_admin() so that products
			 * published through the REST API, WP-CLI or a scheduled post are also indexed.
			 */
			if ( '1' === $auto_send ) {
				add_action( 'save_post', array( $ob_class, 'send_new_product_to_algolia' ), 10, 2 );
			}

			if ( is_admin() ) {
				add_action( 'admin_menu', array( $ob_class, 'admin_menu' ) );
				add_action( 'admin_enqueue_scripts', array( $ob_class, 'enqueue_settings_styles' ) );
				add_action( 'admin_init', array( $ob_class, 'setup_settings_sections' ) );
				add_action( 'admin_init', array( $ob_class, 'update_settings_options' ) );
				add_action( 'admin_init', array( $ob_class, 'maybe_send_products' ) );

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
		 * Registered as a static `save_post` callback, so the sender has to be
		 * reached statically.
		 *
		 * @SuppressWarnings(PHPMD.StaticAccess)
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
			Algolia_Send_Products::send_products_to_algolia( $post_id );
		}

		/**
		 * Verify nonces before we update options and settings
		 * Also retrieve the value from the send_products_to_algolia hidden field to check if we are sending products to Algolia
		 *
		 * Registered as a static `admin_init` callback, so the nonce verifier has to
		 * be reached statically.
		 *
		 * @SuppressWarnings(PHPMD.StaticAccess)
		 *
		 * @return void
		 */
		public static function update_settings_options() {
			/**
			 * Both the nonce and the manage_options capability are enforced here before
			 * anything is written to the database.
			 */
			if ( ! Algolia_Verify_Nonces::verify_settings_nonce() ) {
				return;
			}

			$options = array(
				ALGOWOO_DB_OPTION . ALGOLIA_APP_ID         => self::get_posted_setting( 'algolia_woo_indexer_application_id', 'id' ),
				ALGOWOO_DB_OPTION . ALGOLIA_API_KEY        => self::get_posted_setting( 'algolia_woo_indexer_admin_api_key', 'key' ),
				ALGOWOO_DB_OPTION . ALGOLIA_SEARCH_API_KEY => self::get_posted_setting( 'algolia_woo_indexer_search_api_key', 'key' ),
				ALGOWOO_DB_OPTION . INDEX_NAME             => self::get_posted_setting( 'algolia_woo_indexer_index_name', 'name' ),
			);

			foreach ( $options as $option_key => $option_value ) {
				if ( '' !== $option_value ) {
					update_option( $option_key, $option_value );
				}
			}

			/**
			 * The checkbox is absent from the POST data when unchecked, so the value 0 has
			 * to be written unconditionally for automatic indexing to be switchable off.
			 */
			$auto_send = filter_input( INPUT_POST, 'algolia_woo_indexer_automatically_send_new_products', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			update_option( ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS, empty( $auto_send ) ? '0' : '1' );
		}

		/**
		 * Read a single submitted settings value, unslash it and sanitize it.
		 *
		 * @param string $field_name Name of the POST field holding the value array.
		 * @param string $key        Array key inside the POST field.
		 * @return string Sanitized value, or an empty string when it was not submitted.
		 */
		private static function get_posted_setting( $field_name, $key ) {
			$field = filter_input( INPUT_POST, $field_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			if ( ! is_array( $field ) || ! isset( $field[ $key ] ) || ! is_string( $field[ $key ] ) ) {
				return '';
			}

			return sanitize_text_field( wp_unslash( $field[ $key ] ) );
		}

		/**
		 * Sanitize the registered setting.
		 *
		 * Algolia credentials and index names may contain digits and punctuation, so the
		 * value is only stripped of unsafe characters rather than filtered to letters.
		 *
		 * @param mixed $input Submitted settings data.
		 * @return array Sanitized settings data.
		 */
		public static function settings_fields_validate_options( $input ) {
			if ( ! is_array( $input ) ) {
				return array();
			}

			$valid = array();
			foreach ( $input as $key => $value ) {
				$valid[ sanitize_key( $key ) ] = is_string( $value ) ? sanitize_text_field( $value ) : '';
			}

			return $valid;
		}

		/**
		 * Load text domain for translations
		 *
		 * The languages directory sits in the plugin root, one level above this class file.
		 *
		 * @return void
		 */
		public static function load_textdomain() {
			load_plugin_textdomain( 'algolia-woo-indexer', false, basename( dirname( __DIR__ ) ) . '/languages/' );
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
				array( self::get_instance(), 'algolia_woo_indexer_settings' )
			);
		}

		/**
		 * Keep settings help text readable without restyling native controls.
		 *
		 * @param string $hook_suffix Current admin page hook.
		 * @return void
		 */
		public static function enqueue_settings_styles( $hook_suffix ) {
			if ( 'settings_page_algolia-woo-indexer-settings' !== $hook_suffix ) {
				return;
			}

			$stylesheet = '/assets/css/admin.css';
			wp_enqueue_style(
				'algolia-woo-indexer-settings',
				plugins_url( $stylesheet, dirname( __DIR__ ) . '/algolia-woo-indexer.php' ),
				array(),
				(string) filemtime( dirname( __DIR__ ) . $stylesheet )
			);
		}

		/**
		 * Retrieve connection settings through the main plugin class.
		 *
		 * @return array Saved Application ID, write and search keys, and index name.
		 */
		public static function get_algolia_settings() {
			return array(
				'application_id' => get_option( ALGOWOO_DB_OPTION . ALGOLIA_APP_ID ),
				'api_key'        => get_option( ALGOWOO_DB_OPTION . ALGOLIA_API_KEY ),
				'search_api_key' => get_option( ALGOWOO_DB_OPTION . ALGOLIA_SEARCH_API_KEY ),
				'index_name'     => get_option( ALGOWOO_DB_OPTION . INDEX_NAME ),
			);
		}

		/**
		 * Display settings and allow user to modify them
		 *
		 * @return void
		 */
		public function algolia_woo_indexer_settings() {
			/**
			 * Verify that the user can access the settings page
			 */
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Action not allowed.', 'algolia-woo-indexer' ) );
			}
			?>
			<div class="wrap algowoo-settings">
				<h1><?php echo esc_html__( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ); ?></h1>
				<?php $this->connection_status->display_algolia_connection_status( self::get_algolia_settings() ); ?>
				<form action="<?php echo esc_url( self::$plugin_url ); ?>" method="POST">
					<?php
					settings_fields( 'algolia_woo_options' );
					do_settings_sections( 'algolia_woo_indexer' );
					submit_button( esc_html__( 'Save Settings', 'algolia-woo-indexer' ) );
					?>
				</form>
				<hr />
				<h2><?php echo esc_html__( 'Product indexing', 'algolia-woo-indexer' ); ?></h2>
				<p><?php echo esc_html__( 'Send your products using the saved connection settings. Save any changes above before starting.', 'algolia-woo-indexer' ); ?></p>
				<form action="<?php echo esc_url( self::$plugin_url ); ?>" method="POST">
					<?php wp_nonce_field( 'send_products_to_algolia_nonce_action', 'send_products_to_algolia_nonce_name' ); ?>
					<input type="hidden" name="send_products_to_algolia" id="send_products_to_algolia" value="true" />
					<?php submit_button( esc_html__( 'Send products to Algolia', 'algolia-woo-indexer' ), 'primary', 'send_products_submit' ); ?>
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
			$search_api_key         = get_option( ALGOWOO_DB_OPTION . ALGOLIA_SEARCH_API_KEY );
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
			if ( false === $search_api_key ) {
				add_option( ALGOWOO_DB_OPTION . ALGOLIA_SEARCH_API_KEY, '' );
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
