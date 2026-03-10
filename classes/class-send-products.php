<?php

/**
 * Algolia Woo Indexer class for sending products
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use \Algowoo\Algolia_Check_Requirements as Algolia_Check_Requirements;
use \Algowoo\Algolia_Attributes as Algolia_Attributes;

/**
 * Abort if this file is called directly
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Include plugin file if function is_plugin_active does not exist
 */
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

/**
 * Define the plugin version and the database table name
 */
define('ALGOWOO_DB_OPTION', '_algolia_woo_indexer');
define('ALGOWOO_CURRENT_DB_VERSION', '0.3');

/**
 * Define application constants
 */
define('CHANGE_ME', 'change me');

/**
 * Define list of fields available to index
 */
define('BASIC_FIELDS', array(
    'product_name',
    'permalink',
    'tags',
    'categories',
    'short_description',
    'long_description',
    'excerpt',
    'product_image',
    'regular_price',
    'sale_price',
    'on_sale',
    "stock_quantity",
    "stock_status"
));

/**
 * Database table names
 */
define('INDEX_NAME', '_index_name');
define('AUTOMATICALLY_SEND_NEW_PRODUCTS', '_automatically_send_new_products');
define('BASIC_FIELD_PREFIX', '_field_');
define('ALGOLIA_APP_ID', '_application_id');
define('ALGOLIA_API_KEY', '_admin_api_key');
define('CUSTOM_FIELDS', '_custom_fields');

/**
 * Define settings update related options and cron identifiers
 */
define('ALGOLIA_SETTINGS_CONFIG', '_index_settings_config');
define('ALGOLIA_SETTINGS_CRON_ENABLED', '_index_settings_cron_enabled');
define('ALGOLIA_SETTINGS_CRON_INTERVAL', '_index_settings_cron_interval');
define('ALGOLIA_SETTINGS_DEFAULT_CRON_INTERVAL', 60);
define('ALGOLIA_SETTINGS_CRON_HOOK', 'algolia_cron_update_index_settings');
define('ALGOLIA_SETTINGS_CRON_SCHEDULE', 'algolia_settings_interval');


if (!class_exists('Algolia_Send_Products')) {
    /**
     * Algolia WooIndexer main class
     */
    // TODO Rename class "Algolia_Send_Products" to match the regular expression ^[A-Z][a-zA-Z0-9]*$.
    class Algolia_Send_Products
    {
        const PLUGIN_NAME      = 'Algolia Woo Indexer';
        const PLUGIN_TRANSIENT = 'algowoo-plugin-notice';

        /**
         * The Algolia instance
         *
         * @var \Algolia\AlgoliaSearch\SearchClient
         */
        private static $algolia = null;

        /**
         * Return absolute path to config directory
         *
         * @return string
         */
        private static function get_config_directory()
        {
            return plugin_dir_path(__DIR__) . 'config/';
        }

        /**
         * List available JSON config files inside config directory
         *
         * @return array
         */
        public static function get_available_config_files()
        {
            $config_directory = self::get_config_directory();
            if (!is_dir($config_directory)) {
                return array();
            }

            $config_files = glob($config_directory . '*.json');

            if (false === $config_files) {
                return array();
            }

            $config_files = array_map('basename', $config_files);
            sort($config_files);

            return $config_files;
        }

        /**
         * Prepare Algolia client and index using stored credentials
         *
         * @return \Algolia\AlgoliaSearch\SearchIndex|null
         */
        private static function prepare_algolia_index()
        {
            $base_plugin_directory = plugin_dir_path(__DIR__);
            require_once $base_plugin_directory . 'vendor/autoload.php';

            $algolia_application_id = get_option(ALGOWOO_DB_OPTION . ALGOLIA_APP_ID);
            $algolia_application_id = is_string($algolia_application_id) ? $algolia_application_id : CHANGE_ME;

            $algolia_api_key = get_option(ALGOWOO_DB_OPTION . ALGOLIA_API_KEY);
            $algolia_api_key = is_string($algolia_api_key) ? $algolia_api_key : CHANGE_ME;

            $algolia_index_name = get_option(ALGOWOO_DB_OPTION . INDEX_NAME);
            $algolia_index_name = is_string($algolia_index_name) ? $algolia_index_name : CHANGE_ME;

            Algolia_Check_Requirements::check_algolia_input_values($algolia_application_id, $algolia_api_key, $algolia_index_name);

            if (empty($algolia_application_id) || empty($algolia_api_key) || empty($algolia_index_name) || CHANGE_ME === $algolia_application_id || CHANGE_ME === $algolia_api_key || CHANGE_ME === $algolia_index_name) {
                return null;
            }

            try {
                self::$algolia = \Algolia\AlgoliaSearch\SearchClient::create($algolia_application_id, $algolia_api_key);
                self::can_connect_to_algolia();
                return self::$algolia->initIndex($algolia_index_name);
            } catch (\Exception $exception) {
                add_action(
                    'admin_notices',
                    function () use ($exception) {
                        echo '<div class="error notice"><p>' . esc_html__('Unable to connect to Algolia. ', 'algolia-woo-indexer') . esc_html($exception->getMessage()) . '</p></div>';
                    }
                );
            }

            return null;
        }

        /**
         * Check if we can connect to Algolia, if not, handle the exception, display an error and then return
         */
        public static function can_connect_to_algolia()
        {
            try {
                self::$algolia->listApiKeys();
            } catch (\Algolia\AlgoliaSearch\Exceptions\UnreachableException $error) {
                add_action(
                    'admin_notices',
                    function () {
                        echo '<div class="error notice">
                            <p>' . esc_html__('An error has been encountered. Please check your application ID and API key. ', 'algolia-woo-indexer') . '</p>
						</div>';
                    }
                );
                return;
            }
        }

        /**
         * check if the field is enabled and shall be sent
         *
         * @param  mixed $field name of field to be checked according to BASIC_FIELDS 
         * @return boolean true if enable, false is not enabled
         */
        public static function is_basic_field_enabled($field)
        {
            $fieldValue = get_option(ALGOWOO_DB_OPTION . BASIC_FIELD_PREFIX . $field);
            return $fieldValue;
        }

        /**
         * helper function to add a field to a record while checking their state
         *
         * @param  array $record existing record where the field and value shall be added to 
         * @param  string $field name of field to be checked according to BASIC_FIELDS 
         * @param  mixed $value data to be added to the record array named to $field
         * @param  boolean $skip_basic_field_validation set to true if it is not a basic field to skip validation 
         * @return array $record previous passed $record with added field data
         */
        public static function add_to_record($record, $field, $value, $skip_basic_field_validation = false)
        {
            /**
             *  only if enabled or validation skipped and not empty
             */
            if ((!self::is_basic_field_enabled($field) && !$skip_basic_field_validation) || empty($value)) {
                return $record;
            }

            $record[$field] = $value;
            return $record;
        }

        /**
         * Get sale price or regular price based on product type
         *
         * @param  mixed $product Product to check   
         * @return array ['sale_price' => $sale_price,'regular_price' => $regular_price] Array with regular price and sale price
         */
        public static function get_product_type_price($product)
        {
            $sale_price = 0;
            $regular_price = 0;
            if ($product->is_type('simple')) {
                $sale_price     =  $product->get_sale_price();
                $regular_price  =  $product->get_regular_price();
            } elseif ($product->is_type('variable')) {
                $sale_price     =  $product->get_variation_sale_price('min', true);
                $regular_price  =  $product->get_variation_regular_price('max', true);
            }
            return array(
                'sale_price' => $sale_price,
                'regular_price' => $regular_price
            );
        }


        /**
         * Checks if stock management is enabled and if so, returns quantity and status
         *
         * @param  mixed $product Product to check   
         * @return array ['stock_quantity' => $stock_quantity,'stock_status' => $stock_status] Array with quantity and status. if stock management is disabled, false will be returned,
         */
        public static function get_product_stock_data($product)
        {
            if ($product->get_manage_stock()) {
                return array(
                    'stock_quantity' => $product->get_stock_quantity(),
                    'stock_status' => $product->get_stock_status()
                );
            }
            return false;
        }

        /**
         * Checks if stock management is enabled and if so, returns quantity and status
         *
         * @param  mixed $product Product to check   
         * @return array ['stock_quantity' => $stock_quantity,'stock_status' => $stock_status] Array with quantity and status. if stock management is disabled, false will be returned,
         */
        public static function get_custom_fields($product)
        {
            $custom_fields_string = get_option(ALGOWOO_DB_OPTION . CUSTOM_FIELDS);
            $custom_fields_array = explode(",", $custom_fields_string);
            $custom_field_with_values = array();
            foreach ($custom_fields_array as $custom_field) {
                $value = get_post_meta($product->get_id(), $custom_field);
                if (!empty($value)) {
                    $custom_field_with_values[$custom_field] = $value;
                }
            }
            return $custom_field_with_values;
        }

        /**
         * Get product tags
         *
         * @param  mixed $product Product to check   
         * @return array ['tag1', 'tag2', ...] simple array with associated tags
         */
        public static function get_product_tags($product)
        {
            $tags = get_the_terms($product->get_id(), 'product_tag');
            $term_array = array();
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $name = get_term($tag)->name;
                    array_push($term_array, $name);
                }
            }
            return $term_array;
        }

        /**
         * Get product categories
         *
         * @param  mixed $product Product to check   
         * @return array ['tag1', 'tag2', ...] simple array with associated categories
         */
        public static function get_product_categories($product)
        {
            $categories = get_the_terms($product->get_id(), 'product_cat');
            $term_array = array();
            foreach ($categories as $category) {
                $name = get_term($category)->name;
                $slug = get_term($category)->slug;
                array_push($term_array, array(
                    "name" => $name,
                    "slug" => $slug
                ));
            }
            return $term_array;
        }

        /**
         * Send specific WooCommerce products to Algolia
         *
         * @param Int $id Product to send to Algolia if we send only a single product
         * @return void
         */
        public static function send_product_to_algolia($id = '')
        {
            self::send_products_to_algolia_wrapper("saveObjects", $id);
        }

        /**
         * Replace all exitsting WooCommerce products to Algolia
         *
         * @return void
         */
        public static function send_products_to_algolia($id = '')
        {
            self::send_products_to_algolia_wrapper("replaceAllObjects", $id);
        }

        /**
         * Send WooCommerce products to Algolia
         *
         * @param string $type represent how we handle it on algolia, options are ""replaceAll"
         * @param Int $id Product to send to Algolia if we send only a single product
         * @return void
         */
        public static function send_products_to_algolia_wrapper($type, $id = '')
        {
            /**
             * Prepare Algolia client and index
             */
            $index = self::prepare_algolia_index();

            if (!$index) {
                return;
            }

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
             * Setup arguments for sending only a single product
             */
            if (isset($id) && '' !== $id) {
                $arguments = array(
                    'status'   => 'publish',
                    'include'  => array($id),
                    'paginate' => false,
                );
            }

            /**
             * Fetch all products from WooCommerce
             *
             * @see https://docs.woocommerce.com/wc-apidocs/function-wc_get_products.html
             */
            $products =
                /** @scrutinizer ignore-call */
                wc_get_products($arguments);

            if (empty($products)) {
                return;
            }
            $records = array();
            $record  = array();

            foreach ($products as $product) {
                /**
                 * Set sale price or regular price based on product type
                 */
                $product_type_price = self::get_product_type_price($product);
                $sale_price = $product_type_price['sale_price'];
                $regular_price = $product_type_price['regular_price'];


                /**
                 * always add objectID (mandatory field for algolia)
                 */
                $record['objectID'] = $product->get_id();

                /**
                 * Extract image from $product->get_image()
                 */
                if (self::is_basic_field_enabled("product_image")) {
                    preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result);
                    $record["product_image"] = array_pop($result);
                }

                $record = self::add_to_record($record, 'product_name', $product->get_name());
                $record = self::add_to_record($record, 'short_description', $product->get_short_description());
                $record = self::add_to_record($record, 'long_description', $product->get_description());
                $record = self::add_to_record($record, 'excerpt', get_the_excerpt($product->get_id()));
                $record = self::add_to_record($record, 'regular_price', $regular_price);
                $record = self::add_to_record($record, 'sale_price', $sale_price);
                $record = self::add_to_record($record, 'on_sale', $product->is_on_sale());
                $record = self::add_to_record($record, 'permalink', $product->get_permalink());
                $record = self::add_to_record($record, 'categories', self::get_product_categories($product));
                $record = self::add_to_record($record, 'tags', self::get_product_tags($product));
                $record = self::add_to_record($record, 'attributes', Algolia_Attributes::get_product_attributes($product), true);

                /**
                 * get custom fields and merge
                 */
                $custom_fields = self::get_custom_fields($product);
                if (!empty($custom_fields)) {
                    $record = array_merge($record, $custom_fields);
                }


                /**
                 * Add stock information if stock management is on
                 */
                $stock_data = self::get_product_stock_data($product);
                if (is_array($stock_data)) {
                    $record = self::add_to_record($record, 'stock_quantity', $stock_data['stock_quantity']);
                    $record = self::add_to_record($record, 'stock_status', $stock_data['stock_status']);
                }

                $records[] = $record;
            }

            wp_reset_postdata();

            /**
             * Send the information to Algolia and save the result
             * If result is NullResponse, print an error message
             */ 
            switch($type) {
                case "replaceAllObjects":
                    // use safe mode so Algolia waits for settings/synonyms/rules copy before moving the tmp index
                    $result = $index->replaceAllObjects($records, array('safe' => true));
                break;
                case "saveObjects":
                    $result = $index->saveObjects($records);
                break;
            }
            

            if ('Algolia\AlgoliaSearch\Response\NullResponse' === get_class($result)) {
                wp_die(esc_html__('No response from the server. Please check your settings and try again', 'algolia_woo_indexer_settings'));
            }

            /**
             * Display success message
             */
            echo '<div class="notice notice-success is-dismissible">
					 	<p>' . esc_html__('Product(s) sent to Algolia.', 'algolia-woo-indexer') . '</p>
				  		</div>';
        }

        /**
         * Update Algolia index settings, rules and synonyms based on a config file
         *
         * @param string $config_file Optional config file name to override the saved option.
         * @param bool   $display_notice Whether to show admin notices (disable for cron).
         * @return void
         */
        public static function update_index_settings($config_file = '', $display_notice = true)
        {
            $available_configs = self::get_available_config_files();
            $selected_config = sanitize_file_name($config_file);

            if (empty($selected_config)) {
                $selected_config = get_option(ALGOWOO_DB_OPTION . ALGOLIA_SETTINGS_CONFIG);
            }

            if (empty($selected_config) && !empty($available_configs)) {
                $selected_config = $available_configs[0];
            }

            if (empty($selected_config) || !in_array($selected_config, $available_configs, true)) {
                if ($display_notice && is_admin()) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Config file for Algolia index settings not found.', 'algolia-woo-indexer') . '</p></div>';
                }
                return;
            }

            $config_path = self::get_config_directory() . $selected_config;
            $config_raw = file_get_contents($config_path);

            if (false === $config_raw) {
                if ($display_notice && is_admin()) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not read the selected Algolia config file.', 'algolia-woo-indexer') . '</p></div>';
                }
                return;
            }

            $config_data = json_decode($config_raw, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                if ($display_notice && is_admin()) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid JSON in the selected Algolia config file.', 'algolia-woo-indexer') . '</p></div>';
                }
                return;
            }

            $index = self::prepare_algolia_index();

            if (!$index) {
                return;
            }

            try {
                if (isset($config_data['settings']) && is_array($config_data['settings'])) {
                    $index->setSettings($config_data['settings']);
                }

                if (isset($config_data['synonyms']) && is_array($config_data['synonyms'])) {
                    $index->saveSynonyms($config_data['synonyms'], array('replaceExistingSynonyms' => true));
                }

                if (isset($config_data['rules']) && is_array($config_data['rules'])) {
                    $index->saveRules($config_data['rules'], array('clearExistingRules' => true));
                }

                if ($display_notice && is_admin()) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Algolia index settings were updated from the selected config.', 'algolia-woo-indexer') . '</p></div>';
                }
            } catch (\Exception $exception) {
                if ($display_notice && is_admin()) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to update Algolia index settings: ', 'algolia-woo-indexer') . esc_html($exception->getMessage()) . '</p></div>';
                }
            }
        }
    }
}
