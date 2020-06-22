<?php
/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace ALGOWOO;

// Define the plugin version.
define( 'ALGOWOO_DB_OPTION', 'algo_woo' );
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
		 * Class constructor
		 *
		 * @return void
		 */
		public function __construct() {
			$this->init();
		}

		/**
		 *  Initialize class, load settings and add settings menu
		 *
		 * @return void
		 */
		public static function init() {
			$ob_class = get_called_class();
			add_action( 'plugins_loaded', array( $ob_class, 'load_textdomain' ) );
			self::load_settings();
			if ( is_admin() ) {
				add_action( 'admin_menu', array( $ob_class, 'admin_menu' ) );
			}
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
			// Verify that the user can access the settings page
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Action not allowed.', 'algolia_woo_indexer_settings' ) );
			}

			?>
			<div class="wrap">
			<h1><div class="dashicons dashicons-admin-site ic-devops"></div> <?php esc_html_e( 'Algolia Woo Indexer Settings', 'algolia-woo-indexer' ); ?></h1>
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
			delete_option( ALGOWOO_DB_OPTION . '_db_ver' );
		}
	}
}
