<?php
/**
 * Settings page section and field renderers.
 *
 * Method names are single lowercase words so they satisfy both the WordPress
 * (snake_case) and SonarQube (^[a-z][a-zA-Z0-9]*$) naming conventions.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the settings sections and fields registered with the Settings API.
 */
final class AlgoliaSettingsFields {

	/**
	 * Section text for plugin settings section.
	 *
	 * @return void
	 */
	public static function renderSectionText() {
		echo '<p>' . esc_html__( 'Connect your WooCommerce catalog to Algolia.', 'algolia-woo-indexer' ) . '</p>';
	}

	/**
	 * Output for application ID field.
	 *
	 * @return void
	 */
	public static function renderApplicationIdField() {
		$application_id = get_option( ALGOWOO_DB_OPTION . ALGOLIA_APP_ID );
		$application_id = is_string( $application_id ) ? $application_id : CHANGE_ME;

		echo '<div class="algowoo-settings-field">';
		echo "<input id='algolia_woo_indexer_application_id' name='algolia_woo_indexer_application_id[id]'
			type='text' class='regular-text' value='" . esc_attr( $application_id ) . "' />";
		echo '</div>';
	}

	/**
	 * Output the Search API Key field used for connection checks.
	 *
	 * @return void
	 */
	public static function renderSearchApiKeyField() {
		$settings = Algolia_Woo_Indexer::getAlgoliaSettings();
		$api_key  = is_string( $settings['search_api_key'] ) ? $settings['search_api_key'] : '';
		?>
		<div class="algowoo-settings-field">
		<input id="algolia_woo_indexer_search_api_key" name="algolia_woo_indexer_search_api_key[key]" type="password" class="regular-text" autocomplete="new-password" aria-label="<?php echo esc_attr__( 'Search API Key', 'algolia-woo-indexer' ); ?>" aria-describedby="algolia_woo_indexer_search_api_key_description" value="<?php echo esc_attr( $api_key ); ?>" />
		<p class="description" id="algolia_woo_indexer_search_api_key_description"><?php echo esc_html__( 'Used only to check the connection. This key must have search access to the index below.', 'algolia-woo-indexer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Output for Write API Key field, retaining the legacy form and nonce identifiers.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_nonce_field/
	 *
	 * @return void
	 */
	public static function renderWriteApiKeyField() {
		$api_key = get_option( ALGOWOO_DB_OPTION . ALGOLIA_API_KEY );
		$api_key = is_string( $api_key ) ? $api_key : CHANGE_ME;

		wp_nonce_field( 'algolia_woo_indexer_admin_api_nonce_action', 'algolia_woo_indexer_admin_api_nonce_name' );

		echo '<div class="algowoo-settings-field">';
		echo "<input id='algolia_woo_indexer_admin_api_key' name='algolia_woo_indexer_admin_api_key[key]'
			type='password' class='regular-text' autocomplete='new-password' aria-describedby='algolia_woo_indexer_api_key_description' value='" . esc_attr( $api_key ) . "' />";
		echo '<p class="description" id="algolia_woo_indexer_api_key_description">' . esc_html__( 'Use a dedicated key with only Add records (addObject) permission, restricted to this index. Do not use an Admin or Search API Key.', 'algolia-woo-indexer' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output for index name field.
	 *
	 * @return void
	 */
	public static function renderIndexNameField() {
		$index_name = get_option( ALGOWOO_DB_OPTION . INDEX_NAME );
		$index_name = is_string( $index_name ) ? $index_name : CHANGE_ME;

		echo '<div class="algowoo-settings-field">';
		echo "<input id='algolia_woo_indexer_index_name' name='algolia_woo_indexer_index_name[name]'
			type='text' class='regular-text' aria-describedby='algolia_woo_indexer_index_name_description' value='" . esc_attr( $index_name ) . "' />";
		echo '<p class="description" id="algolia_woo_indexer_index_name_description">' . esc_html__( 'Created automatically when products are first sent, if it does not already exist.', 'algolia-woo-indexer' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output for checkbox to check if we automatically send new products to Algolia.
	 *
	 * @return void
	 */
	public static function renderAutoSendField() {
		/**
		 * Sanitization is not really needed as the variable is not directly echoed
		 * But I have still done it to be 100% safe
		 */
		$auto_send = get_option( ALGOWOO_DB_OPTION . AUTOMATICALLY_SEND_NEW_PRODUCTS );
		$auto_send = ( ! empty( $auto_send ) ) ? 1 : 0;
		?>
		<label for="algolia_woo_indexer_automatically_send_new_products">
			<input id="algolia_woo_indexer_automatically_send_new_products" name="algolia_woo_indexer_automatically_send_new_products[checked]" type="checkbox" <?php checked( 1, $auto_send ); ?> />
			<?php echo esc_html__( 'Send new products to Algolia when they are published.', 'algolia-woo-indexer' ); ?>
		</label>
		<?php
	}
}
