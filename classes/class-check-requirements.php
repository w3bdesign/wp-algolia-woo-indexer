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
define('ALGOLIA_MIN_PHP_VERSION', '8.1');
define('ALGOLIA_MIN_WP_VERSION', '6.1');

/**
 * Abort if this file is called directly
 */
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('Algolia_Check_Requirements')) {
    /**
     * Check requirements for Algolia plugin
     */
    class Algolia_Check_Requirements
    {

        /**
         * Check for required PHP version.
         *
         * @return bool
         */
        public static function algolia_php_version_check()
        {
            if (version_compare(PHP_VERSION, ALGOLIA_MIN_PHP_VERSION, '<')) {
                return false;
            }
            return true;
        }

		/**
		 * Check if values are empty and display error notice if not all values have been set
		 *
		 *  @param string $algolia_application_id Algolia application ID.
		 * 	@param string $algolia_api_key Algolia API key.
		 * 	@param string $algolia_index_name Algolia index name.
		 * 
		 */
		public static function check_algolia_input_values($algolia_application_id, $algolia_api_key, $algolia_index_name )
		{	
			if (empty($algolia_application_id) || empty($algolia_api_key || empty($algolia_index_name))) {
                add_action(
                    'admin_notices',
                    function () {
                        echo '<div class="error notice">
							  <p>' . esc_html__('All settings need to be set for the plugin to work.', 'algolia-woo-indexer') . '</p>
							</div>';
                    }
                );    
            }
        }

        /**
         * Check for required WordPress version.
         *
         * @return bool
         */
        public static function algolia_wp_version_check()
        {
            if (version_compare($GLOBALS['wp_version'], ALGOLIA_MIN_WP_VERSION, '<')) {
                return false;
            }
            return true;
        }

        /**
         * Check that we have all of the required PHP extensions installed
         *
         * @return void
         */
        public static function check_unmet_requirements()
        {
            if (! extension_loaded('mbstring')) {
                echo '<div class="error notice">
					  <p>' . esc_html__('Algolia Woo Indexer requires the "mbstring" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer') . '</p>
				  </div>';
            } elseif (! function_exists('mb_ereg_replace')) {
                echo '<div class="error notice">
					  <p>' . esc_html__('Algolia Woo Indexer needs "mbregex" NOT to be disabled. Please contact your hosting provider.', 'algolia-woo-indexer') . '</p>
				  </div>';
            }
            if (! extension_loaded('curl')) {
                echo '<div class="error notice">
					  <p>' . esc_html__('Algolia Woo Indexer requires the "cURL" PHP extension to be enabled. Please contact your hosting provider.', 'algolia-woo-indexer') . '</p>
				  </div>';
                return;
            }
        }
	}
}
