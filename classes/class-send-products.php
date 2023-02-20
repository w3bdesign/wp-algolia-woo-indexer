<?php

/**
 * Algolia Woo Indexer class for sending products
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use \Algowoo\Algolia_Check_Requirements as Algolia_Check_Requirements;

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
    'permalink', 
    'tags', 
    'categories', 
    'short_description', 
    'product_name', 
    'product_image', 
    'regular_price', 
    'sale_price', 
    'on_sale', 
    'attributes',
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

/**
 * constants for attributes
 */
define('ATTRIBUTES_ENABLED', '_attributes_enabled');
define('ATTRIBUTES_VISIBILITY', '_attributes_visibility');
define('ATTRIBUTES_VISIBILITY_STATES', array('both', 'visible', 'hidden'));
define('ATTRIBUTES_VARIATION', '_attributes_variation');
define('ATTRIBUTES_VARIATION_STATES', array('both', 'used', 'notused'));
define('ATTRIBUTES_LIST', '_attributes_list');


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
        public static function is_field_enabled($field)
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
         * @return array $record previous passed $record with added field data
         */
        public static function add_to_record($record, $field, $value)
        {
            if (!self::is_field_enabled($field)) {
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
            } else {
                return false;
            }
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
         * Get attributes from product
         *
         * @param  mixed $product Product to check   
         * @return array ['pa_name' => ['value1', 'value2']] Array with key set to the product attribute internal name and values as array. returns false if not attributes found.
         */
        public static function get_product_attributes($product)
        {
            $rawAttributes = $product->get_attributes();
            $numericRangeAttributes = ["pa_height", "pa_flowermonth"];
            if (!$rawAttributes) {
                return false;
            }

            $attributes = [];
            foreach ($rawAttributes as $attribute) {
                if ($attribute->get_variation()) {
                    continue;
                }
                $name = $attribute->get_name();
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product->get_id(), $name, 'all');
                    $tax_terms = array();

                    // interpolate all values when found in numericRangeAttributes
                    if (array_search($name, $numericRangeAttributes, true) !== false) {
                        $integers = array();
                        foreach ($terms as $term) {
                            array_push($integers, (int) $term->name);
                        }
                        for ($i = min($integers); $i <= max($integers); $i++) {
                            array_push($tax_terms, $i);
                        }
                    } else {
                        // strings
                        foreach ($terms as $term) {
                            $single_term = esc_html($term->name);
                            array_push($tax_terms, $single_term);
                        }
                    }
                }
                $attributes[$name] = $tax_terms;
            }
            return $attributes;
        }

        /**
         * Send WooCommerce products to Algolia
         *
         * @param Int $id Product to send to Algolia if we send only a single product
         * @return void
         */
        public static function send_products_to_algolia($id = '')
        {
            /**
             * Remove classes from plugin URL and autoload Algolia with Composer
             */

            $base_plugin_directory = str_replace('classes', '', dirname(__FILE__));
            require_once $base_plugin_directory . '/vendor/autoload.php';

            /**
             * Fetch the required variables from the Settings API
             */

            $algolia_application_id = get_option(ALGOWOO_DB_OPTION . ALGOLIA_APP_ID);
            $algolia_application_id = is_string($algolia_application_id) ? $algolia_application_id : CHANGE_ME;

            $algolia_api_key        = get_option(ALGOWOO_DB_OPTION . ALGOLIA_API_KEY);
            $algolia_api_key        = is_string($algolia_api_key) ? $algolia_api_key : CHANGE_ME;

            $algolia_index_name     = get_option(ALGOWOO_DB_OPTION . INDEX_NAME);
            $algolia_index_name        = is_string($algolia_index_name) ? $algolia_index_name : CHANGE_ME;

            /**
             * Display admin notice and return if not all values have been set
             */

            Algolia_Check_Requirements::check_algolia_input_values($algolia_application_id, $algolia_api_key, $algolia_index_name);

            /**
             * Initiate the Algolia client
             */
            self::$algolia = \Algolia\AlgoliaSearch\SearchClient::create($algolia_application_id, $algolia_api_key);

            /**
             * Check if we can connect, if not, handle the exception, display an error and then return
             */
            self::can_connect_to_algolia();

            /**
             * Initialize the search index and set the name to the option from the database
             */
            $index = self::$algolia->initIndex($algolia_index_name);

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
                if (self::is_field_enabled("product_image")) {
                    preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $product->get_image(), $result);
                    $record["product_image"] = array_pop($result);
                }

                $record = self::add_to_record($record, 'product_name', $product->get_name());
                $record = self::add_to_record($record, 'short_description', $product->get_short_description());
                $record = self::add_to_record($record, 'regular_price', $regular_price);
                $record = self::add_to_record($record, 'sale_price', $sale_price);
                $record = self::add_to_record($record, 'on_sale', $product->is_on_sale());
                $record = self::add_to_record($record, 'permalink', $product->get_permalink());
                $record = self::add_to_record($record, 'categories', self::get_product_categories($product));
                $record = self::add_to_record($record, 'tags', self::get_product_tags($product));
                $record = self::add_to_record($record, 'attributes', self::get_product_attributes($product));
            


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
            
            echo "<pre>";
            var_dump($records);
            echo "</pre>";
            die();
            wp_reset_postdata();

            /**
             * Send the information to Algolia and save the result
             * If result is NullResponse, print an error message
             */
            $result = $index->saveObjects($records);

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
    }
}
