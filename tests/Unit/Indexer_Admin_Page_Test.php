<?php
/**
 * Tests for the Algolia_Woo_Indexer admin page, styles and translations.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use Algowoo\AlgoliaConnectionStatus;
use Algowoo\Tests\Support\Indexer_Test_Case;
use Algowoo\Tests\Support\Wp_Die_Exception;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

/**
 * Covers the settings page, its menu entry, its stylesheet and the text domain.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Indexer_Admin_Page_Test extends Indexer_Test_Case {

	/**
	 * The settings page is added under Settings for manage_options users.
	 *
	 * @return void
	 */
	public function test_admin_menu_adds_settings_page() {
		$pages = $this->record_calls( 'add_submenu_page' );

		Algolia_Woo_Indexer::admin_menu();

		self::assertSame(
			array(
				array(
					'options-general.php',
					'Algolia Woo Indexer Settings',
					'Algolia Woo Indexer Settings',
					'manage_options',
					'algolia-woo-indexer-settings',
					array( Algolia_Woo_Indexer::get_instance(), 'algolia_woo_indexer_settings' ),
				),
			),
			$pages->getArrayCopy()
		);
	}

	/**
	 * The stylesheet is only enqueued on the plugin's settings page.
	 *
	 * @return void
	 */
	public function test_styles_skip_other_pages() {
		$styles = $this->record_calls( 'wp_enqueue_style' );

		Algolia_Woo_Indexer::enqueue_settings_styles( 'index.php' );

		self::assertCount( 0, $styles );
	}

	/**
	 * The stylesheet is enqueued with its file modification time as the version.
	 *
	 * @return void
	 */
	public function test_styles_enqueued_on_settings_page() {
		$plugin_dir = self::plugin_dir();
		$urls       = $this->record_calls( 'plugins_url', 'https://shop.test/admin.css' );
		$styles     = $this->record_calls( 'wp_enqueue_style' );

		Algolia_Woo_Indexer::enqueue_settings_styles( 'settings_page_algolia-woo-indexer-settings' );

		self::assertSame(
			array( array( '/assets/css/admin.css', $plugin_dir . '/algolia-woo-indexer.php' ) ),
			$urls->getArrayCopy()
		);
		self::assertSame(
			array(
				array(
					'algolia-woo-indexer-settings',
					'https://shop.test/admin.css',
					array(),
					(string) filemtime( $plugin_dir . '/assets/css/admin.css' ),
				),
			),
			$styles->getArrayCopy()
		);
	}

	/**
	 * Users without manage_options cannot open the settings page.
	 *
	 * @return void
	 */
	public function test_page_requires_manage_options() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		$indexer = new Algolia_Woo_Indexer();

		$this->expectException( Wp_Die_Exception::class );
		$this->expectExceptionMessage( 'Action not allowed.' );

		$indexer->algolia_woo_indexer_settings();
	}

	/**
	 * The settings page shows the injected connection status and both forms.
	 *
	 * @return void
	 */
	public function test_page_renders_status_and_forms() {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'settings_fields' )->once()->with( 'algolia_woo_options' );
		Functions\expect( 'do_settings_sections' )->once()->with( 'algolia_woo_indexer' );
		Functions\expect( 'submit_button' )->once()->with( 'Save Settings' );
		Functions\expect( 'submit_button' )->once()->with( 'Send products to Algolia', 'primary', 'send_products_submit' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'send_products_to_algolia_nonce_action', 'send_products_to_algolia_nonce_name' );

		$status = new AlgoliaConnectionStatus();
		( new ReflectionProperty( AlgoliaConnectionStatus::class, 'connection_status' ) )->setValue(
			$status,
			array(
				'type'    => 'success',
				'emoji'   => '✅',
				'title'   => 'Injected status',
				'message' => 'From the injected service.',
			)
		);
		$indexer = new Algolia_Woo_Indexer( $status );

		$output = $this->capture_output( array( $indexer, 'algolia_woo_indexer_settings' ) );

		self::assertStringContainsString( '<h1>Algolia Woo Indexer Settings</h1>', $output );
		self::assertStringContainsString( '<strong>Injected status</strong>', $output );
		self::assertSame( 2, substr_count( $output, '<form action="' . self::SETTINGS_URL . '" method="POST">' ) );
		self::assertStringContainsString( 'name="send_products_to_algolia" id="send_products_to_algolia" value="true"', $output );
	}

	/**
	 * Translations are loaded from the plugin's languages directory.
	 *
	 * @return void
	 */
	public function test_load_textdomain_path() {
		$loads = $this->record_calls( 'load_plugin_textdomain' );

		Algolia_Woo_Indexer::load_textdomain();

		self::assertSame(
			array( array( 'algolia-woo-indexer', false, basename( self::plugin_dir() ) . '/languages/' ) ),
			$loads->getArrayCopy()
		);
	}
}
