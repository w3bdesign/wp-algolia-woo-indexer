<?php
/**
 * Namespaced shims for the PHP built-ins the plugin calls unqualified.
 *
 * They must be defined before any plugin file is loaded. PHP caches the
 * resolution of an unqualified call the first time it runs, so a shim defined
 * later would be ignored. Each shim delegates to the shared
 * Built_In_Functions instance created in tests/bootstrap.php.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Shim for filter_input(). The CLI has no request data, so tests set it.
 *
 * @param mixed ...$args Original arguments.
 * @return mixed
 */
function filter_input( ...$args ) {
	global $algowoo_built_ins;
	return $algowoo_built_ins->call( 'filter_input', $args );
}

/**
 * Shim for extension_loaded().
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function extension_loaded( ...$args ) {
	global $algowoo_built_ins;
	return $algowoo_built_ins->call( 'extension_loaded', $args );
}

/**
 * Shim for function_exists().
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function function_exists( ...$args ) {
	global $algowoo_built_ins;
	return $algowoo_built_ins->call( 'function_exists', $args );
}

/**
 * Shim for file_exists().
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function file_exists( ...$args ) {
	global $algowoo_built_ins;
	return $algowoo_built_ins->call( 'file_exists', $args );
}

/**
 * Shim for version_compare().
 *
 * @param mixed ...$args Original arguments.
 * @return mixed
 */
function version_compare( ...$args ) {
	global $algowoo_built_ins;
	return $algowoo_built_ins->call( 'version_compare', $args );
}
