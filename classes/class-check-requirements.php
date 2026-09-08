<?php
/**
 * Class for checking plugin requirements
 * Like checking PHP version, WordPress version and so on
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Define minimum required versions of PHP and WordPress
 */
define( 'ALGOLIA_MIN_PHP_VERSION', '8.1' );
define( 'ALGOLIA_MIN_WP_VERSION', '6.1' );

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
		 * Check if values are usable and display error notice if not all values have been set
		 *
		 * A value is unusable when it is empty or still equals the CHANGE_ME
		 * sentinel (case-insensitive), meaning the plugin is unconfigured.
		 *
		 * @param string $algolia_application_id Algolia application ID.
		 * @param string $algolia_api_key Algolia API key.
		 * @param string $algolia_index_name Algolia index name.
		 *
		 * @return bool True if all values are usable, false otherwise.
		 */
		public static function check_algolia_input_values( $algolia_application_id, $algolia_api_key, $algolia_index_name ) {
			$values_usable = true;

			foreach ( array( $algolia_application_id, $algolia_api_key, $algolia_index_name ) as $value ) {
				if ( empty( $value ) || 0 === strcasecmp( (string) $value, CHANGE_ME ) ) {
					$values_usable = false;
					break;
				}
			}

			if ( ! $values_usable ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error notice">
							  <p>' . esc_html__( 'All settings need to be set for the plugin to work.', 'algolia-woo-indexer' ) . '</p>
							</div>';
					}
				);
			}

			return $values_usable;
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
		 * Check that we have all of the required PHP extensions installed
		 *
		 * Errors are registered as admin_notices callbacks instead of being
		 * echoed inline, so this method is safe to call during init.
		 *
		 * @return void
		 */
		public static function check_unmet_requirements() {
			$messages = array();

			if ( ! extension_loaded( 'mbstring' ) ) {
				$messages[] = __( 'Algolia Woo Indexer requires the "mbstring" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer' );
			} elseif ( ! function_exists( 'mb_ereg_replace' ) ) {
				$messages[] = __( 'Algolia Woo Indexer needs "mbregex" NOT to be disabled. Please contact your hosting provider.', 'algolia-woo-indexer' );
			}
			if ( ! extension_loaded( 'curl' ) ) {
				$messages[] = __( 'Algolia Woo Indexer requires the "cURL" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer' );
			}

			if ( empty( $messages ) ) {
				return;
			}

			add_action(
				'admin_notices',
				function () use ( $messages ) {
					foreach ( $messages as $message ) {
						echo '<div class="error notice">
							  <p>' . esc_html( $message ) . '</p>
						  </div>';
					}
				}
			);
		}
	}
}
