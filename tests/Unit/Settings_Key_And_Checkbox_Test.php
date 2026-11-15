<?php
/**
 * Tests for the AlgoliaSettingsFields Search API Key and auto-send renderers.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\AlgoliaSettingsFields;
use Algowoo\Tests\Support\Settings_Fields_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the Search API Key field and the automatic indexing checkbox.
 */
#[CoversClass( AlgoliaSettingsFields::class )]
final class Settings_Key_And_Checkbox_Test extends Settings_Fields_Test_Case {

	/**
	 * The Search API Key field reads the saved key through get_algolia_settings().
	 *
	 * @return void
	 */
	public function test_search_key_renders_saved_key() {
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
	public function test_search_key_empty_when_unset() {
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
