<?php
/**
 * Plugin Name: Darwin Site Kit
 * Plugin URI:  https://github.com/DarwinUKLabs/DSK-Core
 * Description: Making WP Management easier.
 * Version:     1.0.0
 * Author:      Darwin Labs
 * Author URI:  https://darwin-labs.co.uk
 * License:     GPL-2.0-or-later
 * Text Domain: dsk-core
 */
defined( 'ABSPATH' ) || exit;

/**
 * DSK_Plugin
 *
 * Bootstrap class for Darwin Site Kit.
 * Responsible for defining constants, loading base classes and includes,
 * and initialising core services on plugins_loaded.
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Plugin {

	/**
	 * Guards against double initialisation.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boot the plugin.
	 * Runs once on plugins_loaded. Subsequent calls are no-ops.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$booted ) return;
		self::$booted = true;

		self::define_constants();
		self::require_base();
		self::require_includes();

		DSK_Module_Registry::init();
		DSK_Ajax::init();
		DSK_Admin_Menu::init();
	}

	/**
	 * Define core plugin constants.
	 * Safe to call multiple times — constants are only defined once.
	 *
	 * @return void
	 */
	private static function define_constants(): void {
		if ( ! defined( 'DSK_PATH' ) )    define( 'DSK_PATH', plugin_dir_path( __FILE__ ) );
		if ( ! defined( 'DSK_URL' ) )     define( 'DSK_URL',  plugin_dir_url( __FILE__ ) );

		if ( ! defined( 'DSK_VERSION' ) ) {
			$data = get_file_data( __FILE__, [ 'Version' => 'Version' ], 'plugin' );
			define( 'DSK_VERSION', $data['Version'] ?: '0.0.0' );
		}
	}

	/**
	 * Load abstract base classes required before any module or include is loaded.
	 *
	 * @return void
	 */
	private static function require_base(): void {
		require_once DSK_PATH . 'base/module-init-base.php';
		require_once DSK_PATH . 'base/admin-menu-base.php';
	}

	/**
	 * Load core includes.
	 *
	 * @return void
	 */
	private static function require_includes(): void {
		require_once DSK_PATH . 'includes/class-helper.php';
		require_once DSK_PATH . 'includes/class-compat.php';
		require_once DSK_PATH . 'includes/class-settings.php';
		require_once DSK_PATH . 'includes/class-ajax.php';
		require_once DSK_PATH . 'includes/class-admin-menu.php';
		require_once DSK_PATH . 'includes/class-module-registry.php';
	}
}

add_action( 'plugins_loaded', [ DSK_Plugin::class, 'init' ] );