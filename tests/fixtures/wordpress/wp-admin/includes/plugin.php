<?php
/**
 * Stand-in for WordPress's wp-admin/includes/plugin.php.
 *
 * The plugin class files require this file when is_plugin_active() is not
 * defined yet. It deliberately defines nothing, so that each test can stub
 * is_plugin_active() with Brain Monkey.
 *
 * @package algolia-woo-indexer
 */
