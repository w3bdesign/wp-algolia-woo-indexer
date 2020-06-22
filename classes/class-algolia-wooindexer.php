<?php

namespace ALGOWOO;

if ( ! class_exists( 'Algolia_Woo_Indexer' ) ) {
	/**
	 * WooIndexer
	 */
	class Algolia_Woo_Indexer {

		const PLUGIN_NAME      = 'Algolia Woo Indexer';
		const PLUGIN_TRANSIENT = 'algowoo-plugin-notice';

		/**
		 * __construct
		 *
		 * @return void
		 */
		public function __construct() {
			$this->init();
		}

		/**
		 *  Init
		 *
		 * @return void
		 */
		public static function init() {
			if ( is_admin() ) {
				echo 'We have admin rights';
				self::load_settings();
			}

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
			echo 'Loaded settings';
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
		 * The actions to execute when the plugin is activated.
		 *
		 * @return void
		 */
		public static function deactivate_plugin() {
			// self::maybe_upgrade_version();
			delete_transient( self::PLUGIN_TRANSIENT, true );
		}
	}
}
