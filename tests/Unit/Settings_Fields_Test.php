<?php
/**
 * Tests for AlgoliaSettingsFields.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\AlgoliaSettingsFields;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the Settings API section and field renderers.
 *
 * PHPMD suppressions: test names describe the behaviour under test.
 *
 * @SuppressWarnings(PHPMD.LongMethodName)
 */
#[CoversClass( AlgoliaSettingsFields::class )]
final class Settings_Fields_Test extends Test_Case {

	/**
	 * Render a static field method and return its output.
	 *
	 * @param string $method Renderer method name.
	 * @return string
	 */
	private function render( $method ) {
		return $this->capture_output( array( AlgoliaSettingsFields::class, $method ) );
	}

	/**
	 * The section intro is escaped text in a paragraph.
	 *
	 * @return void
	 */
	public function test_section_text() {
		self::assertSame( '<p>Connect your WooCommerce catalog to Algolia.</p>', $this->render( 'render_section_text' ) );
	}

	/**
	 * Text fields, their option suffix and their input name.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function text_fields() {
		return array(
			'application ID' => array( 'render_application_id_field', '_algolia_woo_indexer_application_id', "name='algolia_woo_indexer_application_id[id]'" ),
			'write API key'  => array( 'render_write_api_key_field', '_algolia_woo_indexer_admin_api_key', "name='algolia_woo_indexer_admin_api_key[key]'" ),
			'index name'     => array( 'render_index_name_field', '_algolia_woo_indexer_index_name', "name='algolia_woo_indexer_index_name[name]'" ),
		);
	}

	/**
	 * Saved values are rendered escaped in the matching input.
	 *
	 * @param string $method     Renderer method name.
	 * @param string $option     Full option name.
	 * @param string $input_name Expected name attribute.
	 * @return void
	 */
	#[DataProvider( 'text_fields' )]
	public function test_text_field_renders_saved_value_escaped( $method, $option, $input_name ) {
		$this->stub_options( array( $option => "AB'C<1>" ) );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );

		$output = $this->render( $method );

		self::assertStringContainsString( $input_name, $output );
		self::assertStringContainsString( "value='AB&#039;C&lt;1&gt;'", $output );
		self::assertStringNotContainsString( "AB'C<1>", $output );
	}

	/**
	 * A non-string option, such as false when unset, falls back to the placeholder.
	 *
	 * @param string $method     Renderer method name.
	 * @param string $option     Full option name, stored as a non-string value.
	 * @param string $input_name Expected name attribute.
	 * @return void
	 */
	#[DataProvider( 'text_fields' )]
	public function test_text_field_falls_back_to_placeholder( $method, $option, $input_name ) {
		$this->stub_options( array( $option => array( 'not a string' ) ) );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );

		$output = $this->render( $method );

		self::assertStringContainsString( $input_name, $output );
		self::assertStringContainsString( "value='change me'", $output );
	}

	/**
	 * The Write API Key field is a password input that also emits the settings nonce.
	 *
	 * @return void
	 */
	public function test_write_api_key_field_is_masked_and_emits_settings_nonce() {
		$this->stub_options( array( '_algolia_woo_indexer_admin_api_key' => 'secret' ) );
		Functions\expect( 'wp_nonce_field' )
			->once()
			->with( 'algolia_woo_indexer_admin_api_nonce_action', 'algolia_woo_indexer_admin_api_nonce_name' );

		$output = $this->render( 'render_write_api_key_field' );

		self::assertStringContainsString( "type='password'", $output );
		self::assertStringContainsString( "autocomplete='new-password'", $output );
		self::assertStringContainsString( 'Add records (addObject) permission', $output );
	}

	/**
	 * The index name field explains that the index is created on first send.
	 *
	 * @return void
	 */
	public function test_index_name_field_has_description() {
		$this->stub_options( array( '_algolia_woo_indexer_index_name' => 'products' ) );

		self::assertStringContainsString(
			'id="algolia_woo_indexer_index_name_description">Created automatically',
			$this->render( 'render_index_name_field' )
		);
	}

	/**
	 * The Search API Key field reads the saved key through get_algolia_settings().
	 *
	 * @return void
	 */
	public function test_search_api_key_field_renders_saved_key() {
		$this->stub_options( array( '_algolia_woo_indexer_search_api_key' => 'search"key' ) );

		$output = $this->render( 'render_search_api_key_field' );

		self::assertStringContainsString( 'name="algolia_woo_indexer_search_api_key[key]"', $output );
		self::assertStringContainsString( 'type="password"', $output );
		self::assertStringContainsString( 'value="search&quot;key"', $output );
		self::assertStringContainsString( 'aria-label="Search API Key"', $output );
	}

	/**
	 * An unset Search API Key renders an empty value rather than the placeholder.
	 *
	 * @return void
	 */
	public function test_search_api_key_field_is_empty_when_unset() {
		$this->stub_options( array() );

		self::assertStringContainsString( 'value=""', $this->render( 'render_search_api_key_field' ) );
	}

	/**
	 * Saved auto-send values and the checked() value they produce.
	 *
	 * @return array<string, array{mixed, int}>
	 */
	public static function auto_send_values() {
		return array(
			'enabled'  => array( '1', 1 ),
			'disabled' => array( '0', 0 ),
			'unset'    => array( false, 0 ),
		);
	}

	/**
	 * The checkbox is checked only when the option is truthy.
	 *
	 * @param mixed $saved    Saved option value.
	 * @param int   $expected Value passed to checked() as the current value.
	 * @return void
	 */
	#[DataProvider( 'auto_send_values' )]
	public function test_auto_send_checkbox( $saved, $expected ) {
		$this->stub_options( array( '_algolia_woo_indexer_automatically_send_new_products' => $saved ) );
		Functions\expect( 'checked' )
			->once()
			->with( 1, $expected )
			->andReturnUsing(
				static function ( $checked, $current ) {
					if ( $checked === $current ) {
						echo " checked='checked'";
					}
				}
			);

		$output = $this->render( 'render_auto_send_field' );

		self::assertStringContainsString( 'name="algolia_woo_indexer_automatically_send_new_products[checked]"', $output );
		self::assertSame( 1 === $expected, str_contains( $output, "checked='checked'" ) );
		self::assertStringContainsString( 'Send new products to Algolia when they are published.', $output );
	}
}
