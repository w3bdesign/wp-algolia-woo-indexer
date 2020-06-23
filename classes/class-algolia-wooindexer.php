<?php
/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace ALGOWOO;

// Define the plugin version.
define( 'ALGOWOO_DB_OPTION', '_algolia_woo_indexer' );
define( 'ALGOWOO_CURRENT_DB_VERSION', 0.3 );

if ( ! class_exists( 'Algolia_Woo_Indexer' ) ) {
	/**
	 * WooIndexer
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
			* See https://developer.wordpress.org/reference/functions/register_setting/
			*/
			if ( is_admin() ) {
				$arguments = array(
					'type'              => 'string',
					'sanitize_callback' => 'settings_fields_validate_options',
					'default'           => null,
				);
				register_setting( 'algo_woo_options', 'algo_woo_options', $arguments );

				/**
				 * Make sure we reference the instance of the current class by using self::get_instance()
				 * This way we can setup the correct callback function for add_settings_section and add_settings_field
				 */
				$algowooindexer = self::get_instance();

				add_settings_section(
					'algolia_woo_indexer_main',
					'Algo Woo Plugin Settings',
					array( $algowooindexer, 'algolia_woo_indexer_section_text' ),
					'algolia_woo_indexer'
				);
				add_settings_field(
					'algolia_woo_indexer_application_id',
					'Application ID',
					array( $algowooindexer, 'algolia_woo_indexer_application_id_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
				add_settings_field(
					'algolia_woo_indexer_search_api_key',
					'Search-Only API Key',
					array( $algowooindexer, 'algolia_woo_indexer_search_api_key_output' ),
					'algolia_woo_indexer',
					'algolia_woo_indexer_main'
				);
			}
		}

		/**
		 * Section text for plugin settings field
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_search_api_key_output() {

			$api_key = get_option( '_algolia_woo_indexer_api_search_key' );

			wp_nonce_field( 'algolia_woo_indexer_search_api_nonce_action', 'algolia_woo_indexer_search_api_nonce_name' );

			echo "<input id='algolia_woo_indexer_search_api_key' name='algolia_woo_indexer_search_api_key[key]'
				type='text' value='" . esc_attr( $api_key ) . "' />";
		}


		/**
		 * Section text for plugin settings field
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_application_id_output() {

			$application_id = get_option( '_algolia_woo_indexer_application_id' );

			echo "<input id='algolia_woo_indexer_application_id' name='algolia_woo_indexer_application_id[id]'
				type='text' value='" . esc_attr( $application_id ) . "' />";
		}

		/**
		 * Section text for plugin settings section
		 *
		 * @return void
		 */
		public static function algolia_woo_indexer_section_text() {
			echo esc_html__( 'Enter your settings here', 'algolia-woo-indexer' );
		}

		/**
		 * Check if Woocommerce is activated
		 *
		 * @return boolean
		 */
		public static function is_woocommerce_plugin_active() {
			return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
		}

		/**
		 * Initialize class, setup settings sections and fields
		 *
		 * @return void
		 */
		public static function init() {
			$ob_class = get_called_class();
			add_action( 'plugins_loaded', array( $ob_class, 'load_textdomain' ) );
			self::load_settings();

			/**
			* Add actions to setup admin menu
			*/
			if ( is_admin() ) {
				add_action( 'admin_menu', array( $ob_class, 'admin_menu' ) );
				add_action( 'admin_init', array( $ob_class, 'setup_settings_sections' ) );
				add_action( 'admin_init', array( $ob_class, 'verify_settings_nonce' ) );

				self::$plugin_url = admin_url( 'options-general.php?page=algolia-woo-indexer-settings' );

				if ( ! self::is_woocommerce_plugin_active() ) {
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
		 *
		 * @return void
		 */
		public static function verify_settings_nonce() {

			/**
			 * Filter incoming nonce
			 */
			$nonce = filter_input( INPUT_POST, 'algolia_woo_indexer_search_api_nonce_name', FILTER_DEFAULT );

			/**
			 * Return if if no nonce field
			 */
			if ( ! isset( $nonce ) ) {
				return;
			}

			/**
			 * Display error and die if nonce is not verified and does not pass security check
			 */
			if ( ! wp_verify_nonce( $nonce, 'algolia_woo_indexer_search_api_nonce_action' ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ) );
			}

			/**
			 * Filter the application id and api key
			 */
			$post_application_id = filter_input( INPUT_POST, 'algolia_woo_indexer_application_id', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$post_api_key        = filter_input( INPUT_POST, 'algolia_woo_indexer_search_api_key', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			/**
			 * Properly sanitize text fields before updating data
			*/
			$filtered_application_id = sanitize_text_field( $post_application_id['id'] );
			$filtered_api_key        = sanitize_text_field( $post_api_key['key'] );

			if ( isset( $filtered_application_id ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_application_id',
					$filtered_application_id
				);
			}

			if ( isset( $filtered_api_key ) ) {
				update_option(
					ALGOWOO_DB_OPTION . '_api_search_key',
					$filtered_api_key
				);
			}
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
		 * Load text domain for internalization
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
				<h1><?php esc_html_e( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ); ?></h1>
				<form action="<?php echo esc_url( self::$plugin_url ); ?>" method="POST">
				<?php
				settings_fields( 'algo_woo_options' );
				do_settings_sections( 'algolia_woo_indexer' );
				submit_button( 'Save Changes', 'primary' );
				?>
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
		 * Load plugin settings.
		 *
		 * @return void
		 */
		public static function load_settings() {
			// TODO Load settings and get plugin options !
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
			// TODO Delete the options we have registered !
		}
	}
}
