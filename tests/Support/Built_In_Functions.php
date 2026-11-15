<?php
/**
 * Registry of overrides for PHP built-in functions called by the plugin.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

/**
 * Lets a test replace the result of a PHP built-in function for plugin code.
 *
 * The plugin classes live in the Algowoo namespace and call built-ins such as
 * filter_input() unqualified, so PHP resolves Algowoo\filter_input() first.
 * tests/shims.php defines those namespaced functions and routes every call
 * through this registry, which falls back to the real built-in.
 */
final class Built_In_Functions {

	/**
	 * Active overrides, keyed by built-in function name.
	 *
	 * @var array<string, callable>
	 */
	private static $overrides = array();

	/**
	 * Replace a built-in function until the next reset().
	 *
	 * @param string   $name     Built-in function name, without a namespace.
	 * @param callable $callback Replacement receiving the original arguments.
	 * @return void
	 */
	public static function override( $name, callable $callback ) {
		self::$overrides[ $name ] = $callback;
	}

	/**
	 * Make filter_input( INPUT_POST, ... ) read from the given array.
	 *
	 * Mirrors the real behaviour for FILTER_DEFAULT: a missing field is null,
	 * and a value whose shape does not match FILTER_REQUIRE_ARRAY is false.
	 *
	 * @param array $post Simulated POST data.
	 * @return void
	 */
	public static function set_post_data( array $post ) {
		self::override(
			'filter_input',
			static function ( $type, $var_name, $filter = FILTER_DEFAULT, $options = 0 ) use ( $post ) {
				unset( $filter );
				if ( INPUT_POST !== $type || ! array_key_exists( $var_name, $post ) ) {
					return null;
				}

				$value         = $post[ $var_name ];
				$require_array = is_int( $options ) && ( $options & FILTER_REQUIRE_ARRAY );
				if ( is_array( $value ) !== (bool) $require_array ) {
					return false;
				}

				return $value;
			}
		);
	}

	/**
	 * Call the override for a built-in, or the real built-in when none is set.
	 *
	 * @param string $name Built-in function name.
	 * @param array  $args Arguments passed by the plugin code.
	 * @return mixed
	 */
	public static function call( $name, array $args ) {
		if ( isset( self::$overrides[ $name ] ) ) {
			return ( self::$overrides[ $name ] )( ...$args );
		}

		return ( '\\' . $name )( ...$args );
	}

	/**
	 * Remove every override.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$overrides = array();
	}
}
