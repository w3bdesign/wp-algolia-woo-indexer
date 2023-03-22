<?php

/**
 * Main Algolia Woo Indexer class
 * Called from main plugin file algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Definitions for attributes
 */
define('ATTRIBUTES_ENABLED', '_attributes_enabled');
define('ATTRIBUTES_VISIBILITY', '_attributes_visibility');
define('ATTRIBUTES_VISIBILITY_STATES', array('all', 'visible', 'hidden'));
define('ATTRIBUTES_LIST', '_attributes_list');
define('ATTRIBUTES_INTERP', '_attributes_interp');
define('ATTRIBUTES_TAX_FIELDS', '_attributes_tax_fields');
define('ALLOWED_TAXONOMIES', array(
    'term_id',
    'name',
    'slug',
    'term_group',
    'description',
    'count',
    'filter'
));

/**
 * definitions of available settings
 */
define('ATTRIBUTES_SETTINGS', array(
    'enabled' => 'Enable indexing of attributes',
    'visibility' => 'Visibility',
    'list' => 'Valid Attributes',
    'interp' => 'Numeric Interpolation',
    'tax_fields' => 'Content of each attribute term'
));

/**
 * Abort if this file is called directly
 */
if (!defined('ABSPATH')) {
    exit;
}


if (!class_exists('Algolia_Attributes')) {
    /**
     * Algolia WooIndexer Attributes
     */
    class Algolia_Attributes
    {


        /**
         * Class instance
         *
         * @var object
         */
        private static $instance;


        /**
         * Setup sections and fields to store and retrieve values from Settings API
         *
         * @return void
         */
        public static function setup_attributes_settings()
        {
            /**"
             * Make sure we reference the instance of the current class by using self::get_instance()
             * This way we can setup the correct callback function for add_settings_section and add_settings_field
             */
            $algowoo_attributes = self::get_instance();

            /**
             * Add sections and fields for the attributes
             */
            add_settings_section(
                'algolia_woo_indexer_attributes',
                esc_html__('Attributes indexing settings', 'algolia-woo-indexer'),
                array($algowoo_attributes, 'algolia_woo_indexer_attributes_section_text'),
                'algolia_woo_indexer'
            );

            /**
             * Add fields based on ATTRIBUTES_SETTINGS
             */
            foreach (ATTRIBUTES_SETTINGS as $key => $description) {
                add_settings_field(
                    'algolia_woo_indexer_attributes_' . $key,
                    esc_html__($description, 'algolia-woo-indexer'),
                    array($algowoo_attributes, 'algolia_woo_indexer_attributes_' . $key . '_output'),
                    'algolia_woo_indexer',
                    'algolia_woo_indexer_attributes'
                );
            }
        }

        /**
         * Output for attributes if functionality is enabled
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_enabled_output()
        {
            $value = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_ENABLED);
            $isChecked = (!empty($value)) ? 1 : 0;
?>
            <input id="algolia_woo_indexer_attributes_enabled" name="algolia_woo_indexer_attributes_enabled[checked]" type="checkbox" <?php checked(1, $isChecked); ?> />
            <?php
        }

        /**
         * Output for attributes how to handle visibility setting
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_visibility_output()
        {
            $value = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_VISIBILITY);
            foreach (ATTRIBUTES_VISIBILITY_STATES as $state) {
                $id = 'algolia_woo_indexer_attributes_visibility_' . $state;
            ?>
                <p><input id="<?php echo $id; ?>" name="algolia_woo_indexer_attributes_visibility[value]" type="radio" value="<?php echo $state; ?>" <?php checked($state, $value); ?> /><label for="<?php echo $id; ?>"><?php echo esc_html__($state, 'algolia-woo-indexer'); ?></label></p>
            <?php
            }
        }

        /**
         * Output for attributes list which attributes are whitelisted
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_list_output()
        {
            $value = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_LIST);
            $selectedIds = explode(",", $value);
            $name = "algolia_woo_indexer_attributes_list[list]";
            $description = __('Here you can whitelist all the attributes. Use the <b>shift</b> or <b>control</b> buttons to select multiple attributes.', 'algolia-woo-indexer');
            self::generic_attributes_select_output($name, $selectedIds, $description);
        }

        /**
         * Output for attributes list which are using a numeric interpolation
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_interp_output()
        {
            $value = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_INTERP);
            $selectedIds = explode(",", $value);
            $name = "algolia_woo_indexer_attributes_interp[list]";
            $description = __('If you have some attributes based on number which shall be interpd between the lowest to the highest number, you can select it here. A common usecase for this is if you want to have a <b>range slider</b> in aloglia which works for a certain range. Example: a plant grows between 20 and 25cm tall. for this you enter 20 and 25 as attribute values to your product and it will automatically extend the data to [20,21,22,23,24,25]', 'algolia-woo-indexer');
            self::generic_attributes_select_output($name, $selectedIds, $description);
        }

        /**
         * Output for attributes list which are using a numeric interpolation
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_tax_fields_output()
        {
            $selected_raw = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_TAX_FIELDS);
            $selected_entries = explode(",", $selected_raw);
            $name = "algolia_woo_indexer_attributes_tax_fields[list]";
            $description = __('Select which taxonomy fields for each attribute shall be indexed', 'algolia-woo-indexer');
            $values = ALLOWED_TAXONOMIES;
            ?>
            <p><?php echo $description; ?></p>
            <select multiple="multiple" name="<?php echo $name; ?>[]" size="<?php echo count($values); ?>">
                <?php
                foreach ($values as $tax) {
                    $selected = in_array($tax, $selected_entries) ? ' selected="selected" ' : '';
                ?>
                    <option value="<?php echo $tax; ?>" <?php echo $selected; ?>>
                        <?php echo __($tax, 'algolia-woo-indexer'); ?>
                    </option>
                <?php
                }
                ?>
            </select>
        <?php
        }

        /**
         * Generic Output for attributes list where attributes are whitelisted using Woocommerce attributes taxonomies
         * @param string $name id and name for select
         * @param array $selected_entries will be preselected if matching with WC taxonomies
         * @param string $description will be displayed on top
         * 
         */
        public static function generic_attributes_select_output($name, $selected_entries, $description)
        {


            $values = wc_get_attribute_taxonomies();
            if (!$values) {
                echo esc_html__('You don\'t have any attributes defined yet. Go to WooCommerce and add some to use this feature.', 'algolia-woo-indexer');
                return;
            }

        ?>
            <p><?php echo $description; ?></p>
            <select multiple="multiple" name="<?php echo $name; ?>[]" size="<?php echo count($values); ?>">
                <?php
                foreach ($values as $tax) {

                    $id = $tax->attribute_id;
                    $label = $tax->attribute_label;
                    $name = $tax->attribute_name;
                    $selected = in_array($id, $selected_entries) ? ' selected="selected" ' : '';
                ?>
                    <option value="<?php echo $id; ?>" <?php echo $selected; ?>>
                        <?php echo $label . ' (' . $name . ')'; ?>
                    </option>
                <?php
                }
                ?>
            </select>
<?php

        }

        /**
         * Section text for attributes settings section text
         *
         * @return void
         */
        public static function algolia_woo_indexer_attributes_section_text()
        {
            
        }


        /**
         * parse, sanitize and update attribute settings in DB
         *
         * @return void
         */
        public static function update_attribute_options()
        {

            /**
             * Filter the inputs
             *
             * @see https://www.php.net/manual/en/function.filter-input.php
             */
            $attributes_enabled              = filter_input(INPUT_POST, 'algolia_woo_indexer_attributes_enabled', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $attributes_visibility           = filter_input(INPUT_POST, 'algolia_woo_indexer_attributes_visibility', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $attributes_list                 = filter_input(INPUT_POST, 'algolia_woo_indexer_attributes_list', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $attributes_interp               = filter_input(INPUT_POST, 'algolia_woo_indexer_attributes_interp', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            $attributes_tax_fields           = filter_input(INPUT_POST, 'algolia_woo_indexer_attributes_tax_fields', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

            /**
             * Properly sanitize text fields before updating data
             *
             * @see https://developer.wordpress.org/reference/functions/sanitize_text_field/
             */
            $sanitized = array();
            $sanitized['attributes_visibility']     = sanitize_text_field($attributes_visibility['value']);

            /**
             * sanitize select list of id's by getting integers and them implode seperated with comma
             */

            $attributes_list_integers = [];
            foreach ($attributes_list['list'] as $id) {
                $sanitizedId = sanitize_text_field($id);
                array_push($attributes_list_integers, (int) $sanitizedId);
            }
            $sanitized['attributes_list'] = implode(',', $attributes_list_integers);

            $attributes_interp_int = [];
            foreach ($attributes_interp['list'] as $id) {
                $sanitizedId = sanitize_text_field($id);
                array_push($attributes_interp_int, (int) $sanitizedId);
            }
            $sanitized['attributes_interp'] = implode(',', $attributes_interp_int);

            /**
             * only allow values from the ALLOWED_TAXONOMIES to be saved
             */
            $sanitized['attributes_tax_fields'] = [];
            foreach ($attributes_tax_fields['list'] as $name) {
                if (in_array($name, ALLOWED_TAXONOMIES)) {
                    array_push($sanitized['attributes_tax_fields'], $name);
                }
            }
            $sanitized['attributes_tax_fields'] = implode(',', $sanitized['attributes_tax_fields']);

            /**
             * Sanitizing by setting the value to either 1 or 0
             */
            $sanitized['attributes_enabled'] = (!empty($attributes_enabled)) ? 1 : 0;


            /**
             * Values have been filtered and sanitized
             * Check if set and not empty and update the database
             *
             * @see https://developer.wordpress.org/reference/functions/update_option/
             */

            foreach (array_keys(ATTRIBUTES_SETTINGS) as $key) {
                $value = $sanitized['attributes_' . $key];
                if (isset($value)) {
                    $extension = constant('ATTRIBUTES_' . strtoupper($key));
                    update_option(
                        ALGOWOO_DB_OPTION . $extension,
                        $value
                    );
                }
            }
        }


        /**
         * The actions to execute when the plugin is activated.
         *
         * @return void
         */
        public static function activate_attributes()
        {

            /**
             * Set default values for options if not already set
             */
            $attributes_enabled              = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_ENABLED);
            $attributes_visibility           = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_VISIBILITY);
            $attributes_list                 = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_LIST);
            $attributes_interp               = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_INTERP);
            $attributes_tax_fields           = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_TAX_FIELDS);


            if (empty($attributes_enabled)) {
                add_option(
                    ALGOWOO_DB_OPTION . ATTRIBUTES_ENABLED,
                    1
                );
            }
            if (empty($attributes_visibility)) {
                add_option(
                    ALGOWOO_DB_OPTION . ATTRIBUTES_VISIBILITY,
                    ATTRIBUTES_VISIBILITY_STATES[0]
                );
            }
            if (empty($attributes_list)) {
                add_option(
                    ALGOWOO_DB_OPTION . ATTRIBUTES_LIST,
                    ''
                );
            }
            if (empty($attributes_interp)) {
                add_option(
                    ALGOWOO_DB_OPTION . ATTRIBUTES_INTERP,
                    ''
                );
            }
            if (empty($attributes_tax_fields)) {
                add_option(
                    ALGOWOO_DB_OPTION . ATTRIBUTES_TAX_FIELDS,
                    'name,slug'
                );
            }
        }
        /**
         * Get active object instance
         *
         * @return object
         */
        public static function get_instance()
        {
            if (!self::$instance) {
                self::$instance = new Algolia_Attributes();
            }
            return self::$instance;
        }

        /**
         * format attributes terms according to settings in ATTRIBUTES_TAX_FIELDS
         *
         * @param  array $terms list of woocommerce attribute taxonomy
         * @return array Array with fields set in config as defined in ATTRIBUTEX_TAX_FIELDS.
         */
        public static function format_product_attribute_terms($terms, $interpolateValues)
        {
            $allowed_keys_raw = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_TAX_FIELDS);
            $allowed_keys = explode(',', $allowed_keys_raw);
            $final_terms = array();

            switch ($interpolateValues) {
                case true:
                    $integers = array();
                    foreach ($terms as $term) {
                        array_push($integers, (int) $term->name);
                    }
                    if (count($integers) > 0) {
                        for ($i = min($integers); $i <= max($integers); $i++) {
                            array_push($final_terms, $i);
                        }
                    }
                    break;
                    /**
                     * normal mixed content case 
                     */
                default:
                    foreach ($terms as $term) {
                        $final_term = array();
                        foreach ($allowed_keys as $key) {
                            array_push($final_term, esc_html($term->{$key}));
                        }
                        $string_with_Separator = implode("|", $final_term);
                        array_push($final_terms, $string_with_Separator);
                    }
            }
            return $final_terms;
        }

        /**
         * skip variable related attributes,
         * ensure it is a taxonomy,
         * ensure that taxonomy is whitelisted and
         * ensure that the visibility and variation is respected
         * @param mixed $attribute Woocommerce attribute
         */
        private static function is_attribute_not_allowed($attribute)
        {
            /**
             * gather settings
             */
            $setting_visibility = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_VISIBILITY);
            $setting_ids = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_LIST);
            $setting_ids = explode(",", $setting_ids);
            $visibility = $attribute["visible"];
            $attribute_id = $attribute->get_id();

            return ($attribute->get_variation() ||
                !$attribute->is_taxonomy() ||
                !in_array($attribute_id, $setting_ids) ||
                ($setting_visibility ===  "visible" && $visibility === false) ||
                ($setting_visibility ===  "hidden" && $visibility === true)
            );
        }

        /**
         * Get attributes from product
         *
         * @param  mixed $product Product to check   
         * @return array ['pa_name' => ['value1', 'value2']] Array with key set to the product attribute internal name and values as array. returns false if not attributes found.
         */
        public static function get_product_attributes($product)
        {
            /**
             * ensure that attributes are actually enabled and we having data
             */
            $attributes_enabled = (int) get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_ENABLED);
            $rawAttributes = $product->get_attributes("edit");
            if ($attributes_enabled !== 1 || !$rawAttributes) {
                return false;
            }


            $setting_ids_interp = get_option(ALGOWOO_DB_OPTION . ATTRIBUTES_INTERP);
            $setting_ids_interp = explode(",", $setting_ids_interp);


            $attributes = [];
            foreach ($rawAttributes as $attribute) {
                if (!self::is_attribute_not_allowed($attribute)) {
                    $name = $attribute->get_name();
                    $terms = wp_get_post_terms($product->get_id(), $name, 'all');
                    $is_interpolation = in_array($attribute->get_id(), $setting_ids_interp);
                    $attributes[$name] = self::format_product_attribute_terms($terms, $is_interpolation);
                }
            }
            return $attributes;
        }
    }
}
