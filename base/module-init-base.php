<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Module_Init_Base
 *
 * Abstract base for all DSK module init classes.
 * Provides path helpers, header parsing, and enforces a consistent init contract.
 *
 * Usage in a module plugin's main file:
 *
 *   final class DSK_OL extends DSK_Module_Init_Base {
 *       public static function init( string $plugin_file ): void {
 *           self::set_base( $plugin_file );
 *           self::require_includes();
 *           // boot services...
 *       }
 *       protected static function require_includes(): void { ... }
 *   }
 *
 * The module is booted by DSK_Module_Registry when active — do not call init() directly.
 *
 * @package DSK
 * @since   0.9.0
 */
abstract class DSK_Module_Init_Base {

	/**
	 * Base filesystem paths for each subclass, keyed by class name.
	 *
	 * @var array<class-string, string>
	 */
	private static array $paths = [];

	/**
	 * Base URLs for each subclass, keyed by class name.
	 *
	 * @var array<class-string, string>
	 */
	private static array $urls = [];

	/**
	 * Parsed file header cache, keyed by class name.
	 *
	 * @var array<class-string, array<string, string>>
	 */
	private static array $headers_cache = [];

	/**
	 * Plugin main file paths for header parsing, keyed by class name.
	 *
	 * @var array<class-string, string>
	 */
	private static array $plugin_files = [];


	// -------------------------------------------------------------------------
	// Header parsing
	// -------------------------------------------------------------------------

	/**
	 * Map of header labels to array keys returned by get_file_data().
	 * Override in a subclass to add module-specific header fields.
	 *
	 * @return array<string, string>
	 */
	protected static function header_map(): array {
		return [
			'Module Name' => 'Plugin Name',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Icon'        => 'DSK-Icon',
			'Color'       => 'DSK-Color',
			'Tags'        => 'DSK-Tags',
		    'Infos'       => 'DSK-Infos',
		];
	}

	/**
	 * Returns all parsed headers from the module's main file.
	 * Results are cached per class.
	 *
	 * @return array<string, string>
	 */
	final public static function headers(): array {
		$cls = static::class;

		if ( isset( self::$headers_cache[ $cls ] ) ) {
			return self::$headers_cache[ $cls ];
		}

		$file = self::$plugin_files[ $cls ]
			?? ( new \ReflectionClass( $cls ) )->getFileName();

		if ( ! $file || ! is_file( $file ) ) {
			return self::$headers_cache[ $cls ] = [];
		}

		return self::$headers_cache[ $cls ] = (array) get_file_data( $file, static::header_map() );
	}

	/**
	 * Returns a single header value by key, with an optional fallback.
	 *
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	final public static function header( string $key, string $default = '' ): string {
		$h = static::headers();
		$v = isset( $h[ $key ] ) && is_string( $h[ $key ] ) ? trim( $h[ $key ] ) : '';
		return $v !== '' ? $v : $default;
	}


	// -------------------------------------------------------------------------
	// Path helpers
	// -------------------------------------------------------------------------

	/**
	 * Set the module's base directory, URL, and plugin file for header parsing.
	 * Must be called first inside init(). Pass __FILE__ from the plugin's main file.
	 *
	 * @param string $file Absolute path to the plugin's main file.
	 * @return void
	 */
	final protected static function set_base( string $file ): void {
		self::$paths[ static::class ]        = trailingslashit( dirname( $file ) );
		self::$urls[ static::class ]         = trailingslashit( plugins_url( '', $file ) );
		self::$plugin_files[ static::class ] = $file;
	}

	/**
	 * Returns an absolute filesystem path within the module directory.
	 *
	 * @param string $append Optional sub-path to append.
	 * @return string
	 */
	final public static function path( string $append = '' ): string {
		return ( self::$paths[ static::class ] ?? '' ) . ltrim( $append, '/' );
	}

	/**
	 * Returns a URL within the module directory.
	 *
	 * @param string $append Optional sub-path to append.
	 * @return string
	 */
	final public static function url( string $append = '' ): string {
		return ( self::$urls[ static::class ] ?? '' ) . ltrim( $append, '/' );
	}


	// -------------------------------------------------------------------------
	// Header shortcuts
	// -------------------------------------------------------------------------

	final public static function version( string $default = '1.0.0' ): string {
		return static::header( 'Version', $default );
	}

	final public static function module_name( string $default = '' ): string {
		$name = static::header( 'Module Name', $default );
		return trim( preg_replace( '/\s*\[DSK\]\s*$/i', '', $name ) );
	}

	final public static function module_desc( string $default = '' ): string {
		return static::header( 'Description', $default );
	}

	final public static function module_icon( string $default = 'dashicons-admin-plugins' ): string {
		return static::header( 'Icon', $default );
	}

	final public static function module_color( string $default = '#c5dcff' ): string {
		return static::header( 'Color', $default );
	}

	/**
	 * Returns validated tag definitions for this module.
	 *
	 * @return array<int, array>  [ [ 'slug', 'label', 'color' ], ... ]
	 */
	final public static function module_tags(): array {
		return DSK_Module_Registry::parse_tags( static::header( 'Tags', '' ) );
	}

	/**
	 * Returns the HTML info content from the DSK-Infos header.
	 *
	 * @return string
	 */
	final public static function module_infos(): string {
		return static::header( 'Infos', '' );
	}


	// -------------------------------------------------------------------------
	// Abstract contract
	// -------------------------------------------------------------------------

	/**
	 * Boot the module. Called by DSK_Module_Registry via the boot callable.
	 * Must call set_base( $plugin_file ) before anything else.
	 *
	 * @param string $plugin_file Absolute path to the plugin's main file.
	 * @return void
	 */
	abstract public static function init( string $plugin_file ): void;

	/**
	 * Require all class files needed by this module.
	 *
	 * @return void
	 */
	abstract protected static function require_includes(): void;
}