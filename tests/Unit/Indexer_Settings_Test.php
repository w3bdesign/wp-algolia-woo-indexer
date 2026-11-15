<?php
/**
 * Tests for the Algolia_Woo_Indexer settings registration and saving.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use Algowoo\AlgoliaSettingsFields;
use Algowoo\Tests\Support\Indexer_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the Settings API registration, saving and sanitizing of settings.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Indexer_Settings_Test extends Indexer_Test_Case {

	/**
	 * Settings sections are not registered outside admin.
	 *
	 * @return void
	 */
	public function test_sections_skipped_outside_admin() {
		$settings = $this->record_calls( 'register_setting' );
		$sections = $this->record_calls( 'add_settings_section' );
		$fields   = $this->record_calls( 'add_settings_field' );

		Algolia_Woo_Indexer::setup_settings_sections();

		self::assertCount( 0, $settings );
		self::assertCount( 0, $sections );
		self::assertCount( 0, $fields );
	}

	/**
	 * In admin, one setting, one section and five fields are registered.
	 *
	 * @return void
	 */
	public function test_registers_setting_section_fields() {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\expect( 'register_setting' )
			->once()
			->with(
				'algolia_woo_options',
				'algolia_woo_options',
				array(
					'type'              => 'string',
					'sanitize_callback' => array( Algolia_Woo_Indexer::class, 'settings_fields_validate_options' ),
					'default'           => null,
				)
			);
		Functions\expect( 'add_settings_section' )
			->once()
			->with(
				'algolia_woo_indexer_main',
				'Algolia Woo Plugin Settings',
				array( AlgoliaSettingsFields::class, 'render_section_text' ),
				'algolia_woo_indexer'
			);
		$fields = array();
		Functions\when( 'add_settings_field' )->alias(
			static function ( $field_id, $title, $callback, $page, $section, $args ) use ( &$fields ) {
				$fields[ $field_id ] = array( $title, $callback, $page, $section, $args );
			}
		);

		Algolia_Woo_Indexer::setup_settings_sections();

		self::assertSame(
			array(
				'algolia_woo_indexer_application_id',
				'algolia_woo_indexer_search_api_key',
				'algolia_woo_indexer_admin_api_key',
				'algolia_woo_indexer_index_name',
				'algolia_woo_indexer_automatically_send_new_products',
			),
			array_keys( $fields )
		);
		foreach ( $fields as $field_id => $field ) {
			self::assertTrue( is_callable( $field[1] ), $field_id . ' has a callable renderer' );
			self::assertSame( AlgoliaSettingsFields::class, $field[1][0] );
			self::assertSame( 'algolia_woo_indexer', $field[2] );
			self::assertSame( 'algolia_woo_indexer_main', $field[3] );
		}
		self::assertSame( 'Write API Key', $fields['algolia_woo_indexer_admin_api_key'][0] );
		self::assertSame( array( 'label_for' => 'algolia_woo_indexer_index_name' ), $fields['algolia_woo_indexer_index_name'][4] );
		self::assertSame( array(), $fields['algolia_woo_indexer_automatically_send_new_products'][4] );
	}

	/**
	 * Without a verified settings form nothing is written.
	 *
	 * @return void
	 */
	public function test_update_needs_settings_form() {
		$this->set_post_data( array( 'algolia_woo_indexer_application_id' => array( 'id' => 'APP' ) ) );

		Algolia_Woo_Indexer::update_settings_options();

		self::assertSame( array(), $this->updated_options );
	}

	/**
	 * A complete form is unslashed, sanitized and saved, and auto-send is stored as '1'.
	 *
	 * @return void
	 */
	public function test_update_saves_all_fields() {
		$this->submit_settings_form(
			array(
				'algolia_woo_indexer_application_id' => array( 'id' => ' APP1 ' ),
				'algolia_woo_indexer_admin_api_key'  => array( 'key' => 'write\\\'key' ),
				'algolia_woo_indexer_search_api_key' => array( 'key' => '<b>search</b>' ),
				'algolia_woo_indexer_index_name'     => array( 'name' => 'products' ),
				'algolia_woo_indexer_automatically_send_new_products' => array( 'checked' => 'on' ),
			)
		);

		Algolia_Woo_Indexer::update_settings_options();

		self::assertSame(
			array(
				'_algolia_woo_indexer_application_id' => 'APP1',
				'_algolia_woo_indexer_admin_api_key'  => "write'key",
				'_algolia_woo_indexer_search_api_key' => 'search',
				'_algolia_woo_indexer_index_name'     => 'products',
				'_algolia_woo_indexer_automatically_send_new_products' => '1',
			),
			$this->updated_options
		);
	}

	/**
	 * Empty, missing or malformed fields keep the saved value, and an unchecked box stores '0'.
	 *
	 * @return void
	 */
	public function test_update_skips_empty_fields() {
		$this->submit_settings_form(
			array(
				'algolia_woo_indexer_application_id' => array( 'id' => '' ),
				'algolia_woo_indexer_admin_api_key'  => 'not-an-array',
				'algolia_woo_indexer_search_api_key' => array( 'key' => array( 'nested' ) ),
				'algolia_woo_indexer_index_name'     => array( 'other' => 'products' ),
			)
		);

		Algolia_Woo_Indexer::update_settings_options();

		self::assertSame(
			array( '_algolia_woo_indexer_automatically_send_new_products' => '0' ),
			$this->updated_options
		);
	}

	/**
	 * The registered setting's sanitizer.
	 *
	 * @return void
	 */
	public function test_validation_sanitizes_input() {
		self::assertSame( array(), Algolia_Woo_Indexer::settings_fields_validate_options( 'string' ) );
		self::assertSame(
			array(
				'name'  => 'Index 1',
				'appid' => '',
			),
			Algolia_Woo_Indexer::settings_fields_validate_options(
				array(
					'Name'    => ' Index 1 ',
					'App ID!' => array( 'nested' ),
				)
			)
		);
	}

	/**
	 * Connection settings are read from their individual options.
	 *
	 * @return void
	 */
	public function test_get_settings_reads_each_option() {
		$this->stub_options(
			array(
				'_algolia_woo_indexer_application_id' => 'APP',
				'_algolia_woo_indexer_admin_api_key'  => 'write',
				'_algolia_woo_indexer_search_api_key' => 'search',
				'_algolia_woo_indexer_index_name'     => 'products',
			)
		);

		self::assertSame(
			array(
				'application_id' => 'APP',
				'api_key'        => 'write',
				'search_api_key' => 'search',
				'index_name'     => 'products',
			),
			Algolia_Woo_Indexer::get_algolia_settings()
		);
	}
}
