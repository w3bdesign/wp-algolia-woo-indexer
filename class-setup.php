<?php

namespace ALGOWOO;

/**
 * Setup
 */
class Setup {

	public $path;

	/**
	 * boot
	 *
	 * @return void
	 */
	public function boot() {

		// Store the plugin folder path.
		$this->path = plugin_dir_path( __FILE__ );

		// Run other setup code here.
	}
}
