<?php
/**
 * Class for checking plugin requirements
 * Like checking PHP version, WordPress version and so on
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Define minimum required versions of PHP and WordPress.
 * Keep these in sync with the plugin header, readme.txt and README.md.
 */
define( 'ALGOLIA_MIN_PHP_VERSION', '8.4' );
define( 'ALGOLIA_MIN_WP_VERSION', '7.1' );

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Algolia_Check_Requirements' ) ) {
	/**
	 * Check requirements for Algolia plugin
	 */
	class Algolia_Check_Requirements {


		/**
		 * Check for required PHP version.
		 *
		 * @return bool
		 */
		public static function algolia_php_version_check() {
			if ( version_compare( PHP_VERSION, ALGOLIA_MIN_PHP_VERSION, '<' ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Check if values are empty and display error notice if not all values have been set
		 *
		 *  @param string $algolia_application_id Algolia application ID.
		 *  @param string $algolia_api_key Algolia API key.
		 *  @param string $algolia_index_name Algolia index name.
		 *
		 *  @return bool True when every required value is present.
		 */
		public static function check_algolia_input_values( $algolia_application_id, $algolia_api_key, $algolia_index_name ) {
			if ( empty( $algolia_application_id ) || empty( $algolia_api_key ) || empty( $algolia_index_name ) ) {
				self::queue_admin_notice( __( 'All settings need to be set for the plugin to work.', 'algolia-woo-indexer' ) );

				return false;
			}

			return true;
		}

		/**
		 * Queue an error notice so that it is rendered on the admin_notices hook
		 * instead of being echoed at an arbitrary point during page load.
		 *
		 * @param string $message Already translated, unescaped message.
		 *
		 * @return void
		 */
		public static function queue_admin_notice( $message ) {
			add_action(
				'admin_notices',
				function () use ( $message ) {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( $message )
					);
				}
			);
		}

		/**
		 * Check for required WordPress version.
		 *
		 * @return bool
		 */
		public static function algolia_wp_version_check() {
			if ( version_compare( $GLOBALS['wp_version'], ALGOLIA_MIN_WP_VERSION, '<' ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Check that we have all of the required PHP extensions installed.
		 * Any problem is queued as an admin notice rather than echoed directly.
		 *
		 * @return void
		 */
		public static function check_unmet_requirements() {
			if ( ! extension_loaded( 'mbstring' ) ) {
				self::queue_admin_notice( __( 'Algolia Woo Indexer requires the "mbstring" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer' ) );
			} elseif ( ! function_exists( 'mb_ereg_replace' ) ) {
				self::queue_admin_notice( __( 'Algolia Woo Indexer needs "mbregex" NOT to be disabled. Please contact your hosting provider.', 'algolia-woo-indexer' ) );
			}

			if ( ! extension_loaded( 'curl' ) ) {
				self::queue_admin_notice( __( 'Algolia Woo Indexer requires the "cURL" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer' ) );
			}
		}
	}
}
