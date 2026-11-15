<?php
/**
 * Base class for the plugin's unit tests.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

use ArrayObject;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Sets up Brain Monkey, common WordPress stubs and isolation between tests.
 */
abstract class Test_Case extends TestCase {

	use MockeryPHPUnitIntegration;
	use Overrides_Built_Ins;

	/**
	 * Plugin class files, in the order algolia-woo-indexer.php loads them.
	 */
	const PLUGIN_CLASS_FILES = array(
		'class-algolia-woo-indexer.php',
		'class-check-requirements.php',
		'class-verify-nonces.php',
		'class-send-products.php',
		'class-algolia-connection-status.php',
		'class-algolia-settings-fields.php',
	);

	/**
	 * Whether setUpBeforeClass() loads the plugin class files.
	 *
	 * @var bool
	 */
	protected static $load_plugin_classes = true;

	/**
	 * WordPress version restored after each test.
	 *
	 * @var string|null
	 */
	private $original_wp_version;

	/**
	 * Absolute path of the plugin root directory.
	 *
	 * @return string
	 */
	protected static function plugin_dir() {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Load the plugin class files once per process.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( static::$load_plugin_classes ) {
			foreach ( self::PLUGIN_CLASS_FILES as $class_file ) {
				require_once self::plugin_dir() . '/classes/' . $class_file;
			}
		}
	}

	/**
	 * Start Brain Monkey and stub the WordPress helpers every test relies on.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		global $wp_version;
		$this->original_wp_version = $wp_version ?? null;
		$wp_version                = '7.1'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test fixture, restored in tearDown().

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'sanitize_text_field' => static function ( $value ) {
					return is_string( $value ) ? trim( preg_replace( '/<[^>]*>/', '', $value ) ) : '';
				},
				'sanitize_key'        => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'wp_unslash'          => static function ( $value ) {
					return is_string( $value ) ? stripslashes( $value ) : $value;
				},
				'wp_html_excerpt'     => static function ( $text, $length, $more = '' ) {
					return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length ) . $more : $text;
				},
				'wp_json_encode'      => 'json_encode',
				'number_format_i18n'  => static function ( $number ) {
					return number_format( (float) $number );
				},
				'is_wp_error'         => static function ( $thing ) {
					return $thing instanceof \WP_Error;
				},
			)
		);

		Functions\when( 'wp_die' )->alias(
			static function ( $message = '', $title = '', $args = array() ) {
				throw new Wp_Die_Exception( esc_html( (string) $message ), esc_html( (string) $title ), (int) ( $args['response'] ?? 0 ) );
			}
		);
	}

	/**
	 * Undo every stub, override and static state change made by the test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->reset_built_ins();
		$this->reset_plugin_statics();

		global $wp_version;
		$wp_version = $this->original_wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the value saved in setUp().

		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset the private static properties the plugin classes keep between calls.
	 *
	 * @return void
	 */
	private function reset_plugin_statics() {
		$statics = array(
			'Algowoo\Algolia_Woo_Indexer'   => array(
				'instance'   => null,
				'plugin_url' => '',
			),
			'Algowoo\Algolia_Send_Products' => array( 'algolia' => null ),
		);

		foreach ( $statics as $class_name => $properties ) {
			if ( ! class_exists( $class_name, false ) ) {
				continue;
			}
			foreach ( $properties as $property => $value ) {
				$this->set_static_property( $class_name, $property, $value );
			}
		}
	}

	/**
	 * Set a private static property.
	 *
	 * @param string $class_name Class holding the property.
	 * @param string $property   Property name.
	 * @param mixed  $value      New value.
	 * @return void
	 */
	protected function set_static_property( $class_name, $property, $value ) {
		( new ReflectionProperty( $class_name, $property ) )->setValue( null, $value );
	}

	/**
	 * Read a private static property.
	 *
	 * @param string $class_name Class holding the property.
	 * @param string $property   Property name.
	 * @return mixed
	 */
	protected function get_static_property( $class_name, $property ) {
		return ( new ReflectionProperty( $class_name, $property ) )->getValue();
	}

	/**
	 * Record every callback added to a hook, so the test can run it later.
	 *
	 * @param string $hook Hook name.
	 * @return ArrayObject Callbacks in the order they were added.
	 */
	protected function capture_hook_callbacks( $hook ) {
		$callbacks = new ArrayObject();

		Actions\expectAdded( $hook )
			->zeroOrMoreTimes()
			->whenHappen(
				static function ( $callback ) use ( $callbacks ) {
					$callbacks[] = $callback;
				}
			);

		return $callbacks;
	}

	/**
	 * Run a callback and return everything it printed.
	 *
	 * @param callable $callback Code that writes output.
	 * @return string
	 */
	protected function capture_output( callable $callback ) {
		ob_start();
		try {
			$callback();
		} finally {
			$output = (string) ob_get_clean();
		}

		return $output;
	}

	/**
	 * Run each captured notice callback and return what each one printed.
	 *
	 * @param ArrayObject $callbacks Callbacks captured from admin_notices.
	 * @return string[]
	 */
	protected function render_notices( ArrayObject $callbacks ) {
		$output = array();
		foreach ( $callbacks as $callback ) {
			$output[] = $this->capture_output( $callback );
		}

		return $output;
	}

	/**
	 * Stub get_option() with a map of option name to value. Unknown names return false.
	 *
	 * @param array $options Option values keyed by full option name.
	 * @return void
	 */
	protected function stub_options( array $options ) {
		Functions\when( 'get_option' )->alias(
			static function ( $name ) use ( $options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : false;
			}
		);
	}

	/**
	 * Stub a WordPress function and record the arguments of every call.
	 *
	 * Lets a test assert on calls with PHPUnit instead of relying on
	 * expectations, which static analysis does not count as assertions.
	 *
	 * @param string $function_name WordPress function to stub.
	 * @param mixed  $return_value  Value each call returns.
	 * @return ArrayObject One array of arguments per call, in call order.
	 */
	protected function record_calls( $function_name, $return_value = null ) {
		$calls = new ArrayObject();

		Functions\when( $function_name )->alias(
			static function ( ...$args ) use ( $calls, $return_value ) {
				$calls[] = $args;
				return $return_value;
			}
		);

		return $calls;
	}
}
