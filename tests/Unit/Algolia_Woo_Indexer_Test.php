<?php
/**
 * Tests for Algolia_Woo_Indexer.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use ArrayObject;
use Algowoo\Algolia_Woo_Indexer;
use Algowoo\AlgoliaConnectionStatus;
use Algowoo\AlgoliaSettingsFields;
use Algowoo\Tests\Support\Built_In_Functions;
use Algowoo\Tests\Support\Test_Case;
use Algowoo\Tests\Support\Wp_Die_Exception;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

/**
 * Covers hook registration, settings, the settings page and activation.
 *
 * PHPMD suppressions: the plugin API, Mockery and the built-in override
 * registry are static, test names describe the behaviour under test, and one
 * test class per production class keeps related tests together.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongMethodName)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Algolia_Woo_Indexer_Test extends Test_Case {

	const SETTINGS_URL = 'https://shop.test/wp-admin/options-general.php?page=algolia-woo-indexer-settings';

	/**
	 * Options written by update_option() during the test.
	 *
	 * @var array<string, mixed>
	 */
	private $updated_options = array();

	/**
	 * Set up a front-end request on a server that meets every requirement.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Built_In_Functions::override(
			'extension_loaded',
			static function () {
				return true;
			}
		);
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_plugin_active' )->justReturn( true );
		Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://shop.test/wp-admin/' . $path;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->updated_options[ $name ] = $value;
				return true;
			}
		);
		$this->stub_options( array() );
	}

	/**
	 * Stub a valid settings form submission by an administrator.
	 *
	 * @param array $fields Settings fields to add to the POST data.
	 * @return void
	 */
	private function submit_settings_form( array $fields ) {
		Built_In_Functions::set_post_data( array( 'algolia_woo_indexer_admin_api_nonce_name' => 'nonce' ) + $fields );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
	}

	/**
	 * Make the next call to send_products_to_algolia() stop at the autoloader check.
	 *
	 * That check queues a notice, which shows that the sender was reached
	 * without involving the Algolia client.
	 *
	 * @return ArrayObject Notice callbacks.
	 */
	private function stop_sender_at_autoloader() {
		Built_In_Functions::override(
			'file_exists',
			static function () {
				return false;
			}
		);

		return $this->capture_hook_callbacks( 'admin_notices' );
	}

	/**
	 * Front-end requests register only the text domain loader.
	 *
	 * @return void
	 */
	public function test_init_on_front_end_registers_only_textdomain() {
		Actions\expectAdded( 'admin_notices' )->never();

		Algolia_Woo_Indexer::init();

		self::assertNotFalse( has_action( 'plugins_loaded', array( Algolia_Woo_Indexer::class, 'load_textdomain' ) ) );
		self::assertFalse( has_action( 'admin_menu' ) );
		self::assertFalse( has_action( 'admin_init' ) );
		self::assertFalse( has_action( 'save_post' ) );
		self::assertSame( '', $this->get_static_property( Algolia_Woo_Indexer::class, 'plugin_url' ) );
	}

	/**
	 * Admin requests register the menu, styles and admin_init handlers, and set the form URL.
	 *
	 * @return void
	 */
	public function test_init_in_admin_registers_admin_hooks() {
		Functions\when( 'is_admin' )->justReturn( true );
		Actions\expectAdded( 'admin_notices' )->never();

		Algolia_Woo_Indexer::init();

		self::assertNotFalse( has_action( 'admin_menu', array( Algolia_Woo_Indexer::class, 'admin_menu' ) ) );
		self::assertNotFalse( has_action( 'admin_enqueue_scripts', array( Algolia_Woo_Indexer::class, 'enqueue_settings_styles' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'setup_settings_sections' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'update_settings_options' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'maybe_send_products' ) ) );
		self::assertSame( self::SETTINGS_URL, $this->get_static_property( Algolia_Woo_Indexer::class, 'plugin_url' ) );
	}

	/**
	 * Automatic indexing hooks save_post on every request type, not only in admin.
	 *
	 * @param bool $is_admin Whether the request is an admin request.
	 * @return void
	 */
	#[DataProvider( 'request_types' )]
	public function test_init_registers_save_post_when_auto_send_enabled( $is_admin ) {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		$this->stub_options( array( '_algolia_woo_indexer_automatically_send_new_products' => '1' ) );
		Actions\expectAdded( 'save_post' )
			->once()
			->with( array( Algolia_Woo_Indexer::class, 'send_new_product_to_algolia' ), 10, 2 );

		Algolia_Woo_Indexer::init();
	}

	/**
	 * Admin and front-end requests.
	 *
	 * @return array<string, array{bool}>
	 */
	public static function request_types() {
		return array(
			'admin'     => array( true ),
			'front end' => array( false ),
		);
	}

	/**
	 * Auto-send values that must not register the save_post hook.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function auto_send_disabled_values() {
		return array(
			'zero'        => array( '0' ),
			'unset'       => array( false ),
			'integer one' => array( 1 ),
		);
	}

	/**
	 * Only the string '1' enables automatic indexing.
	 *
	 * @param mixed $value Saved auto-send option.
	 * @return void
	 */
	#[DataProvider( 'auto_send_disabled_values' )]
	public function test_init_skips_save_post_unless_auto_send_is_string_one( $value ) {
		$this->stub_options( array( '_algolia_woo_indexer_automatically_send_new_products' => $value ) );
		Actions\expectAdded( 'save_post' )->never();

		Algolia_Woo_Indexer::init();
	}

	/**
	 * An old WordPress version queues a notice naming both minimum versions.
	 *
	 * @return void
	 */
	public function test_init_warns_when_wordpress_is_too_old() {
		global $wp_version;
		$wp_version = '6.9'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tearDown().
		$notices    = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		$rendered = $this->render_notices( $notices );
		self::assertCount( 1, $rendered );
		self::assertStringContainsString( 'requires minimum PHP version 8.5 and WordPress version 7.1', $rendered[0] );
	}

	/**
	 * An old PHP version queues the same requirements notice.
	 *
	 * @return void
	 */
	public function test_init_warns_when_php_is_too_old() {
		Built_In_Functions::override(
			'version_compare',
			static function ( $version, $minimum, $operator ) {
				return PHP_VERSION === $version ? \version_compare( '8.4.0', $minimum, $operator ) : \version_compare( $version, $minimum, $operator );
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		self::assertCount( 1, $notices );
	}

	/**
	 * Missing PHP extensions are reported through check_unmet_requirements().
	 *
	 * @return void
	 */
	public function test_init_runs_extension_checks() {
		Built_In_Functions::override(
			'extension_loaded',
			static function ( $extension ) {
				return 'curl' !== $extension;
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		self::assertStringContainsString( '&quot;cURL&quot; PHP extension', $this->render_notices( $notices )[0] );
	}

	/**
	 * Admin requests warn when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function test_init_warns_when_woocommerce_is_inactive() {
		Functions\when( 'is_admin' )->justReturn( true );
		$checked_plugins = array();
		Functions\when( 'is_plugin_active' )->alias(
			static function ( $plugin ) use ( &$checked_plugins ) {
				$checked_plugins[] = $plugin;
				return false;
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		$rendered = $this->render_notices( $notices );
		self::assertSame( array( 'woocommerce/woocommerce.php' ), $checked_plugins );
		self::assertCount( 1, $rendered );
		self::assertStringContainsString( 'WooCommerce plugin must be enabled', $rendered[0] );
	}

	/**
	 * The singleton is created once and runs init().
	 *
	 * @return void
	 */
	public function test_get_instance_returns_a_singleton() {
		$first  = Algolia_Woo_Indexer::get_instance();
		$second = Algolia_Woo_Indexer::get_instance();

		self::assertInstanceOf( Algolia_Woo_Indexer::class, $first );
		self::assertSame( $first, $second );
		self::assertNotFalse( has_action( 'plugins_loaded', array( Algolia_Woo_Indexer::class, 'load_textdomain' ) ) );
	}

	/**
	 * Settings sections are not registered outside admin.
	 *
	 * @return void
	 */
	public function test_settings_sections_are_not_registered_outside_admin() {
		Functions\expect( 'register_setting' )->never();
		Functions\expect( 'add_settings_section' )->never();
		Functions\expect( 'add_settings_field' )->never();

		Algolia_Woo_Indexer::setup_settings_sections();
	}

	/**
	 * In admin, one setting, one section and five fields are registered.
	 *
	 * @return void
	 */
	public function test_settings_sections_register_setting_section_and_fields() {
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
	 * Without the send-products form nothing is sent.
	 *
	 * @return void
	 */
	public function test_maybe_send_products_does_nothing_without_the_form() {
		$notices = $this->stop_sender_at_autoloader();

		Algolia_Woo_Indexer::maybe_send_products();

		self::assertCount( 0, $notices );
	}

	/**
	 * A verified send-products form starts a full send.
	 *
	 * @return void
	 */
	public function test_maybe_send_products_sends_after_verification() {
		Built_In_Functions::set_post_data(
			array(
				'send_products_to_algolia'            => 'true',
				'send_products_to_algolia_nonce_name' => 'nonce',
			)
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		$notices = $this->stop_sender_at_autoloader();

		Algolia_Woo_Indexer::maybe_send_products();

		self::assertStringContainsString( 'The Algolia PHP client is missing.', $this->render_notices( $notices )[0] );
	}

	/**
	 * Posts that must not be sent when saved.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function posts_not_sent() {
		return array(
			'draft product'  => array( 'draft', 'product' ),
			'published post' => array( 'publish', 'post' ),
			'private page'   => array( 'private', 'page' ),
		);
	}

	/**
	 * Only published products are sent from save_post.
	 *
	 * @param string $status    Post status.
	 * @param string $post_type Post type.
	 * @return void
	 */
	#[DataProvider( 'posts_not_sent' )]
	public function test_saved_post_is_ignored_unless_published_product( $status, $post_type ) {
		$notices = $this->stop_sender_at_autoloader();
		$post    = (object) array(
			'post_status' => $status,
			'post_type'   => $post_type,
		);

		Algolia_Woo_Indexer::send_new_product_to_algolia( 7, $post );

		self::assertCount( 0, $notices );
	}

	/**
	 * A published product is sent when saved.
	 *
	 * @return void
	 */
	public function test_saved_published_product_is_sent() {
		$notices = $this->stop_sender_at_autoloader();
		$post    = (object) array(
			'post_status' => 'publish',
			'post_type'   => 'product',
		);

		Algolia_Woo_Indexer::send_new_product_to_algolia( 7, $post );

		self::assertCount( 1, $notices );
	}

	/**
	 * Without a verified settings form nothing is written.
	 *
	 * @return void
	 */
	public function test_update_settings_does_nothing_without_settings_form() {
		Built_In_Functions::set_post_data( array( 'algolia_woo_indexer_application_id' => array( 'id' => 'APP' ) ) );

		Algolia_Woo_Indexer::update_settings_options();

		self::assertSame( array(), $this->updated_options );
	}

	/**
	 * A complete form is unslashed, sanitized and saved, and auto-send is stored as '1'.
	 *
	 * @return void
	 */
	public function test_update_settings_saves_all_fields() {
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
	public function test_update_settings_skips_empty_fields_and_switches_auto_send_off() {
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
	public function test_settings_validation_sanitizes_keys_and_values() {
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
	 * Translations are loaded from the plugin's languages directory.
	 *
	 * @return void
	 */
	public function test_load_textdomain_uses_plugin_languages_directory() {
		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with( 'algolia-woo-indexer', false, basename( self::plugin_dir() ) . '/languages/' );

		Algolia_Woo_Indexer::load_textdomain();
	}

	/**
	 * The settings page is added under Settings for manage_options users.
	 *
	 * @return void
	 */
	public function test_admin_menu_adds_settings_page() {
		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'options-general.php',
				'Algolia Woo Indexer Settings',
				'Algolia Woo Indexer Settings',
				'manage_options',
				'algolia-woo-indexer-settings',
				array( Algolia_Woo_Indexer::get_instance(), 'algolia_woo_indexer_settings' )
			);

		Algolia_Woo_Indexer::admin_menu();
	}

	/**
	 * The stylesheet is only enqueued on the plugin's settings page.
	 *
	 * @return void
	 */
	public function test_styles_are_not_enqueued_on_other_pages() {
		Functions\expect( 'wp_enqueue_style' )->never();

		Algolia_Woo_Indexer::enqueue_settings_styles( 'index.php' );
	}

	/**
	 * The stylesheet is enqueued with its file modification time as the version.
	 *
	 * @return void
	 */
	public function test_styles_are_enqueued_on_settings_page() {
		$plugin_dir = self::plugin_dir();
		Functions\expect( 'plugins_url' )
			->once()
			->with( '/assets/css/admin.css', $plugin_dir . '/algolia-woo-indexer.php' )
			->andReturn( 'https://shop.test/admin.css' );
		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'algolia-woo-indexer-settings',
				'https://shop.test/admin.css',
				array(),
				(string) filemtime( $plugin_dir . '/assets/css/admin.css' )
			);

		Algolia_Woo_Indexer::enqueue_settings_styles( 'settings_page_algolia-woo-indexer-settings' );
	}

	/**
	 * Connection settings are read from their individual options.
	 *
	 * @return void
	 */
	public function test_get_algolia_settings_reads_each_option() {
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

	/**
	 * Users without manage_options cannot open the settings page.
	 *
	 * @return void
	 */
	public function test_settings_page_requires_manage_options() {
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
	public function test_settings_page_renders_status_and_forms() {
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
	 * Without an injected service, the constructor creates a real connection checker.
	 *
	 * @return void
	 */
	public function test_constructor_creates_default_connection_status() {
		$indexer = new Algolia_Woo_Indexer();

		self::assertInstanceOf(
			AlgoliaConnectionStatus::class,
			( new ReflectionProperty( Algolia_Woo_Indexer::class, 'connection_status' ) )->getValue( $indexer )
		);
	}

	/**
	 * Activation seeds placeholder defaults for unset options and sets the notice transient.
	 *
	 * @return void
	 */
	public function test_activation_seeds_defaults() {
		$added = array();
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value ) use ( &$added ) {
				$added[ $name ] = $value;
			}
		);
		Functions\expect( 'set_transient' )->once()->with( 'algowoo-plugin-notice', true );

		Algolia_Woo_Indexer::activate_plugin();

		self::assertSame(
			array(
				'_algolia_woo_indexer_automatically_send_new_products' => '0',
				'_algolia_woo_indexer_application_id' => 'Change me',
				'_algolia_woo_indexer_admin_api_key'  => 'Change me',
				'_algolia_woo_indexer_index_name'     => 'Change me',
				'_algolia_woo_indexer_search_api_key' => '',
			),
			$added
		);
	}

	/**
	 * Activation keeps existing options, including an empty Search API Key.
	 *
	 * @return void
	 */
	public function test_activation_keeps_existing_options() {
		$this->stub_options(
			array(
				'_algolia_woo_indexer_automatically_send_new_products' => '1',
				'_algolia_woo_indexer_application_id' => 'APP',
				'_algolia_woo_indexer_admin_api_key'  => 'write',
				'_algolia_woo_indexer_search_api_key' => '',
				'_algolia_woo_indexer_index_name'     => 'products',
			)
		);
		Functions\expect( 'add_option' )->never();
		Functions\expect( 'set_transient' )->once();

		Algolia_Woo_Indexer::activate_plugin();
	}

	/**
	 * Deactivation removes only the notice transient.
	 *
	 * @return void
	 */
	public function test_deactivation_deletes_transient() {
		Functions\expect( 'delete_transient' )->once()->with( 'algowoo-plugin-notice' );
		Functions\expect( 'delete_option' )->never();

		Algolia_Woo_Indexer::deactivate_plugin();
	}
}
