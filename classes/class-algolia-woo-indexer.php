<?php

/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use \Algowoo\Algolia_Check_Requirements as Algolia_Check_Requirements;
use \Algowoo\Algolia_Verify_Nonces as Algolia_Verify_Nonces;
use \Algowoo\Algolia_Send_Products as Algolia_Send_Products;
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

if (!class_exists('Algolia_Woo_Indexer')) {
    /**
     * Algolia WooIndexer main class
     */
    // TODO Rename class "Algolia_Woo_Indexer" to match the regular expression ^[A-Z][a-zA-Z0-9]*$.
    class Algolia_Woo_Indexer
    {
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
        public function __construct()
        {
            $this->init();
        }

        /**
         * Setup sections and fields to store and retrieve values from Settings API
         *
         * @return void
         */
        public static function setup_settings_sections()
        {
            /**
             * Setup arguments for settings sections and fields
             *
             * @see https://developer.wordpress.org/reference/functions/register_setting/
             */
            if (is_admin()) {
                $arguments = array(
                    'type'              => 'string',
                    'sanitize_callback' => 'settings_fields_validate_options',
                    'default'           => null,
                );
                register_setting('algolia_woo_options', 'algolia_woo_options', $arguments);

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
                    esc_html__('Algolia Woo Plugin Settings', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_section_text'),
                    'algolia_woo_indexer'
                );
                add_settings_field(
                    'algolia_woo_indexer_application_id',
                    esc_html__('Application ID', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_application_id_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_main'
                );
                add_settings_field(
                    'algolia_woo_indexer_admin_api_key',
                    esc_html__('Admin API Key', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_admin_api_key_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_main'
                );
                add_settings_field(
                    'algolia_woo_indexer_index_name',
                    esc_html__('Index name (will be created if not existing)', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_index_name_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_main'
                );
                add_settings_field(
                    'algolia_woo_indexer_automatically_send_new_products',
                    esc_html__('Automatically index new products', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_automatically_send_new_products_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_main'
                );

                /**
                 * Add sections and fields for the basic fields
                 */
                add_settings_section(
                    'algolia_woo_indexer_fields',
                    esc_html__('Fields indexing settings', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_fields_section_text'),
                    'algolia_woo_indexer'
                );
                foreach (BASIC_FIELDS as $field) {
                    add_settings_field(
                        'algolia_woo_indexer_field_' . $field,
                        esc_html__($field, 'algolia-woo-indexer'),
                        array($algowooindexer, 'algolia_woo_indexer_field_output'),
                        'algolia_woo_indexer',
                        'algolia_woo_indexer_fields',
                        array(
                            'label_for' => 'algolia_woo_indexer_field_' . $field,
                            'name' => $field
                        )
                    );
                }
                add_settings_field(
                    'algolia_woo_indexer_custom_fields',
                    esc_html__('Custom Fields', 'algolia-woo-indexer'),
                    array($algowooindexer, 'algolia_woo_indexer_custom_fields_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_fields'
                );

                /**
                 * add settings for Attributes
                 */
                Algolia_Attributes::setup_attributes_settings();

            }
        }

        /**
         * Output for admin API key field
         *
         * @see https://developer.wordpress.org/reference/functions/wp_nonce_field/
         *
         * @return void
         */
        public static function algolia_woo_indexer_admin_api_key_output()
        {
            $api_key = get_option(ALGOWOO_DB_OPTION . ALGOLIA_API_KEY);
            $api_key = is_string($api_key) ? $api_key : CHANGE_ME;

            wp_nonce_field('algolia_woo_indexer_admin_api_nonce_action', 'algolia_woo_indexer_admin_api_nonce_name');

            echo "<input id='algolia_woo_indexer_admin_api_key' name='algolia_woo_indexer_admin_api_key[key]'
				type='text' value='" . esc_attr($api_key) . "' />";
        }

        /**
         * Output for application ID field
         *
         * @return void
         */
        public static function algolia_woo_indexer_application_id_output()
        {
            $application_id = get_option(ALGOWOO_DB_OPTION . ALGOLIA_APP_ID);
            $application_id = is_string($application_id) ? $application_id : CHANGE_ME;

            echo "<input id='algolia_woo_indexer_application_id' name='algolia_woo_indexer_application_id[id]'
				type='text' value='" . esc_attr($application_id) . "' />";
        }

        /**
         * Output for index name field
         *
         * @return void
         */
        public static function algolia_woo_indexer_index_name_output()
        {
            $index_name = get_option(ALGOWOO_DB_OPTION . INDEX_NAME);
            $index_name = is_string($index_name) ? $index_name : CHANGE_ME;

            echo "<input id='algolia_woo_indexer_index_name' name='algolia_woo_indexer_index_name[name]'
				type='text' value='" . esc_attr($index_name) . "' />";
        }

        /**
         * Output for checkbox to check if we automatically send new products to Algolia
         *
         * @return void
         */
        public static function algolia_woo_indexer_automatically_send_new_products_output()
        {
            /**
             * Sanitization is not really needed as the variable is not directly echoed
             * But I have still done it to be 100% safe
             */
            $auto_send = get_option(ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS);
            $auto_send = (!empty($auto_send)) ? 1 : 0; ?>
            <input id="algolia_woo_indexer_automatically_send_new_products" name="algolia_woo_indexer_automatically_send_new_products[checked]" type="checkbox" <?php checked(1, $auto_send); ?> />
        <?php
        }

        /**
         * Output for fields which data shall be sent to Algolia
         *
         * @return void
         */
        public static function algolia_woo_indexer_field_output($args)
        {
            $value = get_option(ALGOWOO_DB_OPTION . BASIC_FIELD_PREFIX . $args["name"]);
            $isChecked = (!empty($value)) ? 1 : 0;
        ?>
            <input id="<?php echo $args["label_for"] ?>" name="<?php echo $args["label_for"] ?>[checked]" type="checkbox" <?php checked(1, $isChecked); ?> />
        <?php
        }

        /**
         * Output textarea for custom fields
         *
         * @return void
         */
        public static function algolia_woo_indexer_custom_fields_output()
        {
            $custom_fields = get_option(ALGOWOO_DB_OPTION . CUSTOM_FIELDS);
            $asLines = str_replace(',', "\r", $custom_fields);
        ?>
            <p><?php echo esc_html__('Add some custom fields names, for example from ACF (Advanced custom fields) here. create a new line for each field or separate with comma.', 'algolia-woo-indexer'); ?></p>
            <textarea id="algolia_woo_indexer_custom_fields" name="algolia_woo_indexer_custom_fields" rows="6" cols="50"><?php echo $asLines; ?></textarea>
        <?php
        }

        /**
         * Section text for plugin settings section text
         *
         * @return void
         */
        public static function algolia_woo_indexer_section_text()
        {
            echo esc_html__('Enter your API settings here.', 'algolia-woo-indexer');
        }

        /**
         * Section text for basic fields settings section text
         *
         * @return void
         */
        public static function algolia_woo_indexer_fields_section_text()
        {
            echo esc_html__('Choose which basic fields shall be indexed.', 'algolia-woo-indexer');
        }
        
        /**
         * Check if we are going to send products by verifying send products nonce
         *
         * @return void
         */
        public static function maybe_send_products()
        {
            if (true === Algolia_Verify_Nonces::verify_send_products_nonce()) {
                Algolia_Send_Products::send_products_to_algolia();
            }
        }

        /**
         * Initialize class, setup settings sections and fields
         *
         * @return void
         */
        public static function init()
        {
            /**
             * Fetch the option to see if we are going to automatically send new products
             */
            $auto_send = get_option(ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS);

            /**
             * Check that we have the minimum versions required and all of the required PHP extensions
             */
            Algolia_Check_Requirements::check_unmet_requirements();

            if (!Algolia_Check_Requirements::algolia_wp_version_check() || !Algolia_Check_Requirements::algolia_php_version_check()) {
                add_action(
                    'admin_notices',
                    function () {
                        echo '<div class="error notice">
                                  <p>' . esc_html__('Please check the server requirements for Algolia Woo Indexer. <br/> It requires minimum PHP version 7.2 and WordPress version 5.0', 'algolia-woo-indexer') . '</p>
                                </div>';
                    }
                );
            }

            $ob_class = get_called_class();

            /**
             * Setup translations
             */
            add_action('plugins_loaded', array($ob_class, 'load_textdomain'));

            /**
             * Add actions to setup admin menu
             */
            if (is_admin()) {
                add_action('admin_menu', array($ob_class, 'admin_menu'));
                add_action('admin_init', array($ob_class, 'setup_settings_sections'));
                add_action('admin_init', array($ob_class, 'update_settings_options'));
                add_action('admin_init', array($ob_class, 'maybe_send_products'));

                /**
                 * Register hook to automatically send new products if the option is set
                 */

                if ('1' === $auto_send) {
                    add_action('save_post', array($ob_class, 'send_new_product_to_algolia'), 10, 3);
                }

                self::$plugin_url = admin_url('options-general.php?page=algolia-woo-indexer-settings');

                if (!is_plugin_active('woocommerce/woocommerce.php')) {
                    add_action(
                        'admin_notices',
                        function () {
                            echo '<div class="error notice">
								  <p>' . esc_html__('WooCommerce plugin must be enabled for Algolia Woo Indexer to work.', 'algolia-woo-indexer') . '</p>
								</div>';
                        }
                    );
                }
            }
        }

        /**
         * Send a single product to Algolia once a new product has been published
         *
         * @param int   $post_id ID of the product.
         * @param array $post Post array.
         *
         * @return void
         */
        public static function send_new_product_to_algolia($post_id, $post)
        {
            if ('publish' !== $post->post_status || 'product' !== $post->post_type) {
                return;
            }
            Algolia_Send_Products::send_products_to_algolia($post_id);
        }

        /**
         * Verify nonces before we update options and settings
         * Also retrieve the value from the send_products_to_algolia hidden field to check if we are sending products to Algolia
         *
         * @return void
         */
        public static function update_settings_options()
        {
            /**
             * Do not proceed if nonce for settings is not set
             */
            if (false === Algolia_Verify_Nonces::verify_settings_nonce()) {
                return;
            }

            /**
             * Do not proceed if we are going to send products
             */
            if (true === Algolia_Verify_Nonces::verify_send_products_nonce()) {
                return;
            }

            /**
             * Filter the application id, api key, index name, custom_fields and verify that the input is an array
             *
             * @see https://www.php.net/manual/en/function.filter-input.php
             */
            $post_application_id             = filter_input(INPUT_POST, 'algolia_woo_indexer_application_id', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $post_api_key                    = filter_input(INPUT_POST, 'algolia_woo_indexer_admin_api_key', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $post_index_name                 = filter_input(INPUT_POST, 'algolia_woo_indexer_index_name', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $auto_send                       = filter_input(INPUT_POST, 'algolia_woo_indexer_automatically_send_new_products', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $custom_fields                   = filter_input(INPUT_POST, 'algolia_woo_indexer_custom_fields', FILTER_DEFAULT);

            /**
             * Properly sanitize text fields before updating data
             *
             * @see https://developer.wordpress.org/reference/functions/sanitize_text_field/
             */
            $sanitized = array();
            $sanitized['app_id']                    = sanitize_text_field($post_application_id['id']);
            $sanitized['api_key']                   = sanitize_text_field($post_api_key['key']);
            $sanitized['index_name']                = sanitize_text_field($post_index_name['name']);
            $sanitized['custom_fields']             = sanitize_textarea_field($custom_fields);

            /**
             * Sanitizing by setting the value to either 1 or 0
             */
            $sanitized['product'] = (!empty($auto_send)) ? 1 : 0;

            /**
             * Filter the data fields checkboxes and verify that the input is an array and assign it to an associative array
             *
             * @see https://www.php.net/manual/en/function.filter-input.php
             */
            $sanitized['fields'] = array();
            foreach (BASIC_FIELDS as $field) {
                $raw_field = filter_input(INPUT_POST, 'algolia_woo_indexer_field_' . $field, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
                $filtered_field = (!empty($raw_field)) ? 1 : 0;
                $sanitized['fields'][$field] = $filtered_field;
            }

            /**
             * Values have been filtered and sanitized
             * Check if set and not empty and update the database
             *
             * @see https://developer.wordpress.org/reference/functions/update_option/
             */
            if (isset($sanitized['app_id']) && (!empty($sanitized['app_id']))) {
                update_option(
                    ALGOWOO_DB_OPTION . ALGOLIA_APP_ID,
                    $sanitized['app_id']
                );
            }

            if (isset($sanitized['api_key']) && (!empty($sanitized['api_key']))) {
                update_option(
                    ALGOWOO_DB_OPTION . ALGOLIA_API_KEY,
                    $sanitized['api_key']
                );
            }

            if (isset($sanitized['index_name']) && (!empty($sanitized['index_name']))) {
                update_option(
                    ALGOWOO_DB_OPTION . INDEX_NAME,
                    $sanitized['index_name']
                );
            }

            if (isset($sanitized['product'])) {
                update_option(
                    ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS,
                    $sanitized['product']
                );
            }

            if (isset($sanitized['fields']) && (!empty($sanitized['fields']))) {
                foreach ($sanitized['fields'] as $key => $value) {
                    update_option(
                        ALGOWOO_DB_OPTION . BASIC_FIELD_PREFIX . $key,
                        $value
                    );
                }
            }

            if (isset($sanitized['custom_fields'])) {
                $custom_fields_array = preg_split("/[\r\n,]+/", $sanitized['custom_fields'], -1, PREG_SPLIT_NO_EMPTY);
                $custom_fields_string = implode(",", $custom_fields_array);
                update_option(
                    ALGOWOO_DB_OPTION . CUSTOM_FIELDS,
                    $custom_fields_string
                );
            }

            /**
             * Update Attributes as well 
             */
            Algolia_Attributes::update_attribute_options();

        }

        /**
         * Sanitize input in settings fields and filter through regex to accept only a-z and A-Z
         *
         * @param string $input Settings text data
         * @return array
         */
        public static function settings_fields_validate_options($input)
        {
            $valid         = array();
            $valid['name'] = preg_replace(
                '/[^a-zA-Z\s]/',
                '',
                $input['name']
            );
            return $valid;
        }

        /**
         * Load text domain for translations
         *
         * @return void
         */
        public static function load_textdomain()
        {
            load_plugin_textdomain('algolia-woo-indexer', false, basename(dirname(__FILE__)) . '/languages/');
        }

        /**
         * Add the new menu to settings section so that we can configure the plugin
         *
         * @return void
         */
        public static function admin_menu()
        {
            add_submenu_page(
                'options-general.php',
                esc_html__('Algolia Woo Indexer Settings', 'algolia-woo-indexer'),
                esc_html__('Algolia Woo Indexer Settings', 'algolia-woo-indexer'),
                'manage_options',
                'algolia-woo-indexer-settings',
                array(get_called_class(), 'algolia_woo_indexer_settings')
            );
        }

        /**
         * Display settings and allow user to modify them
         *
         * @return void
         */
        public static function algolia_woo_indexer_settings()
        {
            /**
             * Verify that the user can access the settings page
             */
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Action not allowed.', 'algolia_woo_indexer_settings'));
            } ?>
            <div class="wrap">
                <h1><?php esc_html__('Algolia Woo Indexer Settings', 'algolia-woo-indexer'); ?></h1>
                <form action="<?php echo esc_url(self::$plugin_url); ?>" method="POST">
                    <?php
                    settings_fields('algolia_woo_options');
                    do_settings_sections('algolia_woo_indexer');
                    submit_button('', 'primary wide'); ?>
                </form>
                <form action="<?php echo esc_url(self::$plugin_url); ?>" method="POST">
                    <?php wp_nonce_field('send_products_to_algolia_nonce_action', 'send_products_to_algolia_nonce_name'); ?>
                    <input type="hidden" name="send_products_to_algolia" id="send_products_to_algolia" value="true" />
                    <?php submit_button(esc_html__('Send products to Algolia', 'algolia_woo_indexer_settings'), 'primary wide', '', false); ?>
                </form>
            </div>
<?php
        }

        /**
         * Get active object instance
         *
         * @return object
         */
        public static function get_instance()
        {
            if (!self::$instance) {
                self::$instance = new Algolia_Woo_Indexer();
            }
            return self::$instance;
        }

        /**
         * The actions to execute when the plugin is activated.
         *
         * @return void
         */
        public static function activate_plugin()
        {

            /**
             * Set default values for options if not already set
             */
            $auto_send = get_option(ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS);
            $algolia_application_id          = get_option(ALGOWOO_DB_OPTION . ALGOLIA_APP_ID);
            $algolia_api_key                 = get_option(ALGOWOO_DB_OPTION . ALGOLIA_API_KEY);
            $algolia_index_name              = get_option(ALGOWOO_DB_OPTION . INDEX_NAME);
            $custom_fields                   = get_option(ALGOWOO_DB_OPTION . CUSTOM_FIELDS);

            if (empty($auto_send)) {
                add_option(
                    ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS,
                    '0'
                );
            }

            if (empty($algolia_application_id)) {
                add_option(
                    ALGOWOO_DB_OPTION . ALGOLIA_APP_ID,
                    'Change me'
                );
            }

            if (empty($algolia_api_key)) {
                add_option(
                    ALGOWOO_DB_OPTION . ALGOLIA_API_KEY,
                    'Change me'
                );
            }

            if (empty($algolia_index_name)) {
                add_option(
                    ALGOWOO_DB_OPTION . INDEX_NAME,
                    'Change me'
                );
            }
            if (empty($custom_fields)) {
                add_option(
                    ALGOWOO_DB_OPTION . CUSTOM_FIELDS,
                    ''
                );
            }

            /**
             * Set default values for index fields if not already set
             */
            foreach (BASIC_FIELDS as $field) {
                $value = get_option(ALGOWOO_DB_OPTION . BASIC_FIELD_PREFIX . $field);
                if (empty($value)) {
                    update_option(
                        ALGOWOO_DB_OPTION . BASIC_FIELD_PREFIX . $field,
                        '1'
                    );
                }
            }

            /**
             * activate attributes
             */
            Algolia_Attributes::activate_attributes();

            set_transient(self::PLUGIN_TRANSIENT, true);
        }

        /**
         * The actions to execute when the plugin is deactivated.
         *
         * @return void
         */
        public static function deactivate_plugin()
        {
            delete_transient(self::PLUGIN_TRANSIENT);
        }
    }
}
