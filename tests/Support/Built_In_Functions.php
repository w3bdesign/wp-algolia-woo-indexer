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
 * tests/shims.php defines those namespaced functions. Because they are plain
 * functions, they reach the shared instance through the global
 * $algowoo_built_ins, created in tests/bootstrap.php. Tests use the
 * Overrides_Built_Ins trait rather than this class directly.
 */
final class Built_In_Functions {

	/**
	 * Active overrides, keyed by built-in function name.
	 *
	 * @var array<string, callable>
	 */
	private $overrides = array();

	/**
	 * Replace a built-in function until the next reset().
	 *
	 * @param string   $name     Built-in function name, without a namespace.
	 * @param callable $callback Replacement receiving the original arguments.
	 * @return void
	 */
	public function override( $name, callable $callback ) {
		$this->overrides[ $name ] = $callback;
	}

	/**
	 * Call the override for a built-in, or the real built-in when none is set.
	 *
	 * @param string $name Built-in function name.
	 * @param array  $args Arguments passed by the plugin code.
	 * @return mixed
	 */
	public function call( $name, array $args ) {
		if ( isset( $this->overrides[ $name ] ) ) {
			return ( $this->overrides[ $name ] )( ...$args );
		}

		return ( '\\' . $name )( ...$args );
	}

	/**
	 * Remove every override.
	 *
	 * @return void
	 */
	public function reset() {
		$this->overrides = array();
	}
}
