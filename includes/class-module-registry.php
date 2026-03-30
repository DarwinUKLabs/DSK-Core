<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Module_Registry
 *
 * Discovers installed DSK module plugins, boots active ones,
 * and hides them from the WordPress plugin list.
 *
 * A DSK module plugin is any plugin with the following header:
 *   DSK-Module: true
 *   DSK-Tags:   security, utilities
 *
 * Discovery reads installed plugin headers — no separate option needed.
 * Activation state is determined entirely by whether the plugin is active in WordPress.
 *
 * Module plugins self-register on the `dsk_register_module` action:
 *
 *   add_action( 'dsk_register_module', function( DSK_Module_Registry $registry ) {
 *       $registry->add( [
 *           'slug'        => 'omni-logger',
 *           'name'        => 'Omni Logger',
 *           'description' => 'Tracks site activity.',
 *           'icon'        => 'dashicons-chart-area',
 *           'color'       => '#A7B2C3',
 *           'boot'        => function() { require_once __DIR__ . '/module-init.php'; DSK_OL::init(); },
 *       ] );
 *   } );
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Module_Registry {

	/**
	 * Registered module definitions, keyed by DSK slug.
	 * Populated by active plugins via dsk_register_module.
	 *
	 * @var array<string, array>
	 */
	private static array $modules = [];

	/**
	 * Discovered plugins with DSK-Module: true, keyed by plugin file (folder/file.php).
	 * Populated once by discover(), includes both active and inactive plugins.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $discovered = null;

	/**
	 * Known tags with their display label and color.
	 * Slug => [ label, color ]
	 *
	 * @var array<string, array>
	 */
	private const TAGS = [
		'marketing'  => [ 'label' => 'Marketing',  'color' => '#c5dcff' ],
		'security'   => [ 'label' => 'Security',   'color' => '#A8D5A2' ],
		'utilities'  => [ 'label' => 'Utilities',  'color' => '#f8d3af' ],
	];


	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	/**
	 * Collect self-registrations from active module plugins and boot them.
	 * Must be called on plugins_loaded after all plugins are loaded.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'extra_plugin_headers', [ self::class, 'register_headers' ] );
		$instance = new self();

		do_action( 'dsk_register_module', $instance );

		self::boot_active();

		if ( DSK_Settings::get( 'hide_modules_from_plugin_list' ) ) {
			self::hide_from_plugin_list();
		}
	}

	/**
	 * Register custom DSK plugin headers so get_plugins() picks them up.
	 *
	 * @param string[] $headers
	 * @return string[]
	 */
	public static function register_headers( array $headers ): array {
		return array_merge( $headers, [ 'DSK-Module', 'DSK-Slug', 'DSK-Icon', 'DSK-Color', 'DSK-Tags', 'DSK-Infos' ] );
	}

	/**
	 * Call boot() on all registered (i.e. active) modules.
	 *
	 * @return void
	 */
	private static function boot_active(): void {
		foreach ( self::$modules as $slug => $boot ) {
			call_user_func( $boot );
		}
	}

	/**
	 * Remove all DSK module plugins from the WP plugin list.
	 *
	 * @return void
	 */
	private static function hide_from_plugin_list(): void {
		add_filter( 'all_plugins', function ( array $plugins ): array {
			foreach ( array_keys( self::get_discovered() ) as $plugin_file ) {
				unset( $plugins[ $plugin_file ] );
			}
			return $plugins;
		} );
	}


	// -------------------------------------------------------------------------
	// Self-registration (called by module plugins)
	// -------------------------------------------------------------------------

	/**
	 * Register a module's boot callable.
	 * Metadata is already known from plugin header discovery — only slug and boot are needed.
	 *
	 * @param array $module Must contain: slug (string), boot (callable)
	 * @return void
	 */
	public function add( array $module ): void {
    	$slug = sanitize_key( $module['slug'] ?? '' );
    
    	if ( ! $slug ) {
    		_doing_it_wrong( __METHOD__, 'Module definition is missing a valid slug.', '0.9.0' );
    		return;
    	}
    
    	if ( isset( self::$modules[ $slug ] ) ) {
    		_doing_it_wrong(
    			__METHOD__,
    			sprintf( 'Module slug "%s" is already registered.', esc_html( $slug ) ),
    			'0.9.0'
    		);
    		return;
    	}
    
    	if ( ! is_callable( $module['boot'] ?? null ) ) {
    		_doing_it_wrong(
    			__METHOD__,
    			sprintf( 'Module "%s" must provide a callable boot.', esc_html( $slug ) ),
    			'0.9.0'
    		);
    		return;
    	}
    
    	self::$modules[ $slug ] = $module['boot'];
    }


	// -------------------------------------------------------------------------
	// Tags
	// -------------------------------------------------------------------------

	/**
	 * Returns all known tags.
	 *
	 * @return array<string, array>  [ slug => [ 'label' => string, 'color' => string ] ]
	 */
	public static function get_tags(): array {
		return self::TAGS;
	}

	/**
	 * Parses a raw DSK-Tags header string into a validated list of known tag definitions.
	 * Unrecognised tags are silently ignored.
	 *
	 * @param  string $raw  e.g. "security, utilities"
	 * @return array<int, array>  [ [ 'slug', 'label', 'color' ], ... ]
	 */
	public static function parse_tags( string $raw ): array {
		if ( $raw === '' ) return [];

		$result = [];
		foreach ( array_map( 'trim', explode( ',', strtolower( $raw ) ) ) as $slug ) {
			$slug = sanitize_key( $slug );
			if ( isset( self::TAGS[ $slug ] ) ) {
				$result[] = array_merge( [ 'slug' => $slug ], self::TAGS[ $slug ] );
			}
		}
		return $result;
	}


	// -------------------------------------------------------------------------
	// Discovery
	// -------------------------------------------------------------------------

	/**
	 * Discover all installed plugins with the DSK-Module: true header.
	 * Results are cached for the request.
	 *
	 * Returns an array keyed by plugin file (e.g. 'dsk-omni-logger/dsk-omni-logger.php'),
	 * each entry containing:
	 *   - plugin_file (string)        WordPress plugin file key
	 *   - slug        (string)        DSK module slug
	 *   - name        (string)
	 *   - description (string)
	 *   - icon        (string)
	 *   - color       (string)
	 *   - tags        (array)         Validated tag definitions
	 *   - active      (bool)
	 *
	 * @return array<string, array>
	 */
	public static function get_discovered(): array {
		if ( self::$discovered !== null ) {
			return self::$discovered;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$discovered = [];

		foreach ( get_plugins() as $plugin_file => $headers ) {
			if ( empty( $headers['DSK-Module'] ) || $headers['DSK-Module'] !== 'true' ) {
				continue;
			}

			$folder = explode( '/', $plugin_file )[0];
			$slug   = ! empty( $headers['DSK-Slug'] )
				? sanitize_key( $headers['DSK-Slug'] )
				: sanitize_key( $folder );

			$name = trim( preg_replace( '/\s*\[DSK\]\s*$/i', '', $headers['Name'] ?? $slug ) );

			self::$discovered[ $plugin_file ] = [
				'plugin_file' => $plugin_file,
				'slug'        => $slug,
				'name'        => sanitize_text_field( $name ),
				'description' => sanitize_text_field( $headers['Description'] ?? '' ),
				'icon'        => sanitize_html_class( $headers['DSK-Icon']  ?? 'dashicons-admin-plugins' ),
				'color'       => sanitize_hex_color(  $headers['DSK-Color'] ?? '' ) ?: '#e5e7eb',
				'tags'        => self::parse_tags( $headers['DSK-Tags'] ?? '' ),
			    'infos'       => wp_kses_post( $headers['DSK-Infos'] ?? '' ),
				'active'      => is_plugin_active( $plugin_file ),
			];
		}

		return self::$discovered;
	}

	/**
	 * Returns discovered modules that are currently active.
	 *
	 * @return array<string, array>
	 */
	public static function get_active(): array {
		return array_filter( self::get_discovered(), fn( $m ) => $m['active'] );
	}

	/**
	 * Returns discovered modules that are currently inactive.
	 *
	 * @return array<string, array>
	 */
	public static function get_inactive(): array {
		return array_filter( self::get_discovered(), fn( $m ) => ! $m['active'] );
	}

	/**
	 * Check whether a module is active by DSK slug.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public static function is_active( string $slug ): bool {
		foreach ( self::get_discovered() as $module ) {
			if ( $module['slug'] === $slug ) {
				return $module['active'];
			}
		}
		return false;
	}

	/**
	 * Returns the plugin_file for a given DSK slug, or null if not found.
	 *
	 * @param string $slug
	 * @return string|null
	 */
	public static function get_plugin_file( string $slug ): ?string {
		foreach ( self::get_discovered() as $plugin_file => $module ) {
			if ( $module['slug'] === $slug ) {
				return $plugin_file;
			}
		}
		return null;
	}
}