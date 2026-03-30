<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Admin_Menu
 *
 * Registers and manages the Darwin Site Kit top-level admin menu,
 * its submenus, and the tab-based navigation system.
 *
 * Modules (standalone plugins) register tabs via the `dsk_tabs` filter:
 *
 *   add_filter( 'dsk_tabs', function( array $tabs ): array {
 *       $tabs[] = [
 *           'key'      => 'my-module',
 *           'label'    => 'My Module',
 *           'icon'     => 'dashicons-admin-generic',
 *           'template' => plugin_dir_path( __FILE__ ) . 'admin/tab.php',
 *           'position' => 'top', // 'top' | 'bottom', defaults to 'top'
 *       ];
 *       return $tabs;
 *   } );
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Admin_Menu {

	/** @var string Main menu and page slug. */
	public const SLUG = 'dsk-dashboard';

	/** @var string Admin page hook suffix, used to scope asset enqueuing. */
	public const HOOK = 'toplevel_page_' . self::SLUG;

	/** @var string Built-in tab key for the dashboard. */
	public const TAB_DASHBOARD = 'dashboard';

	/** @var string Built-in tab key for settings. */
	public const TAB_SETTINGS = 'settings';

	/** @var string Required capability to access the menu. */
	public const CAPABILITY = 'manage_options';

	/**
	 * Module-registered tabs for the top navigation group.
	 *
	 * @var array<string, array>
	 */
	private static array $tabs_top = [];

	/**
	 * Module-registered tabs for the bottom navigation group.
	 *
	 * @var array<string, array>
	 */
	private static array $tabs_bottom = [];

	/** @var bool Guards against load_tabs() running more than once. */
	private static bool $tabs_loaded = false;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu',            [ self::class, 'register' ],       9  );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ], 10 );
	}

	/**
	 * Returns the asset version string used for cache busting.
	 *
	 * @return string
	 */
	public static function assets_ver(): string {
		return DSK_VERSION;
	}

	/**
	 * Load and validate tabs registered via the `dsk_tabs` filter.
	 * Skips any tab missing required fields or pointing to a non-existent template.
	 *
	 * @return void
	 */
	private static function load_tabs(): void {
		if ( self::$tabs_loaded ) return;
		self::$tabs_loaded = true;

		$tabs = apply_filters( 'dsk_tabs', [] );

		if ( ! is_array( $tabs ) ) return;

		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) continue;

			$key        = sanitize_key( $tab['key']      ?? '' );
			$label      = sanitize_text_field( $tab['label']    ?? '' );
			$icon       = sanitize_html_class( $tab['icon']     ?? '' );
			$template   = $tab['template'] ?? '';
			$position   = isset( $tab['position'] ) && $tab['position'] === 'bottom' ? 'bottom' : 'top';
			$menu_class = $tab['menu_class'] ?? '';

			if ( ! $key || ! $label || ! $template ) continue;
			if ( ! file_exists( $template ) ) continue;

			$entry = compact( 'key', 'label', 'icon', 'template', 'menu_class' );

			if ( $position === 'bottom' ) {
				self::$tabs_bottom[ $key ] = $entry;
			} else {
				self::$tabs_top[ $key ] = $entry;
			}
		}
	}

	/**
	 * Register the top-level menu page and all submenu entries.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::load_tabs();

		add_menu_page(
			DSK_Helper::get_plugin_label(),
			DSK_Helper::get_plugin_label(),
			self::CAPABILITY,
			self::SLUG,
			[ self::class, 'render_app_shell' ],
			DSK_Helper::get_plugin_icon(),
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'DSK Dashboard', 'dsk-core' ),
			__( 'Dashboard', 'dsk-core' ),
			self::CAPABILITY,
			self::SLUG,
			'__return_null'
		);

		foreach ( self::get_sorted_top_tabs() as $tab ) {
			add_submenu_page(
				self::SLUG,
				$tab['label'],
				$tab['label'],
				self::CAPABILITY,
				self::SLUG . '#tab=' . $tab['key'],
				'__return_null'
			);
		}

		foreach ( self::get_sorted_bottom_tabs() as $tab ) {
			add_submenu_page(
				self::SLUG,
				$tab['label'],
				$tab['label'],
				self::CAPABILITY,
				self::SLUG . '#tab=' . $tab['key'],
				'__return_null'
			);
		}

		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'dsk-core' ),
			__( 'Settings', 'dsk-core' ),
			self::CAPABILITY,
			self::SLUG . '#tab=' . self::TAB_SETTINGS,
			'__return_null'
		);
	}

	/**
	 * Enqueue styles and scripts for the DSK admin page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== self::HOOK ) return;

		$ver = self::assets_ver();

		$admin_js  = 'assets/admin/admin.min.js';
		$admin_css = 'assets/admin/admin.min.css';

		wp_enqueue_style( 'dsk-noto-sans', DSK_URL . 'assets/fonts/noto-sans/noto-sans.css',  [],                             $ver );
		wp_enqueue_style( 'select2',       DSK_URL . 'assets/vendor/select2/select2.min.css', [],                             $ver );
		wp_enqueue_style( 'dsk-admin',     DSK_URL . $admin_css,                              [ 'dsk-noto-sans', 'select2' ], $ver );

		wp_enqueue_script( 'select2',   DSK_URL . 'assets/vendor/select2/select2.min.js', [ 'jquery' ], $ver, true );
		wp_enqueue_script( 'dsk-admin', DSK_URL . $admin_js,                              [ 'jquery' ], $ver, true );

		wp_localize_script( 'dsk-admin', 'DSK_ADMIN', [
			'slug'    => self::SLUG,
			'baseUrl' => admin_url( 'admin.php?page=' . self::SLUG ),
			'nonce'   => wp_create_nonce( 'dsk_admin' ),
		] );

		wp_localize_script( 'dsk-admin', 'DSK_SHELL', [
			'defaultTab' => self::TAB_DASHBOARD,
			'pageSlug'   => self::SLUG,
		] );
	}

	/**
	 * Render the main app shell template.
	 *
	 * @return void
	 */
	public static function render_app_shell(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) wp_die( 'Forbidden', 403 );

		$path = DSK_PATH . 'admin/app-shell.php';
		if ( file_exists( $path ) ) {
			require $path;
			return;
		}

		echo '<div class="wrap"><h1>Dev Site Kit</h1><p><b>FATAL ERROR</b>: Missing app shell template.</p></div>';
	}

	/**
	 * Render the active tab template based on the current state.
	 * Called from within the app shell template.
	 *
	 * @param array $state Current UI state, expects a 'tab' key.
	 * @return void
	 */
	public static function render_view( array $state ): void {
		$tab = (string) ( $state['tab'] ?? self::TAB_DASHBOARD );

		self::load_tabs();

		$map = [
			self::TAB_DASHBOARD => [ 'template' => DSK_PATH . 'admin/core-tabs/dashboard.php' ],
			self::TAB_SETTINGS  => [ 'template' => DSK_PATH . 'admin/core-tabs/settings.php'  ],
		];

		foreach ( array_merge( self::$tabs_top, self::$tabs_bottom ) as $key => $t ) {
			$map[ $key ] = $t;
		}

		$entry    = $map[ $tab ] ?? $map[ self::TAB_DASHBOARD ];
		$template = $entry['template'] ?? '';

		if ( file_exists( $template ) ) {
			$menu_class = $entry['menu_class'] ?? '';
			if ( $menu_class && method_exists( $menu_class, 'print_tab_assets' ) ) {
				$menu_class::print_tab_assets();
			}
			require $template;
			return;
		}

		require DSK_PATH . 'admin/core-tabs/template-missing.php';
	}

	/**
	 * Returns the full tab list for use in the navigation template.
	 * Top: Dashboard first, then registered modules alphabetically.
	 * Bottom: Registered modules first, Settings pinned last.
	 *
	 * @return array{ top: list<array>, bottom: list<array> }
	 */
	public static function get_tabs(): array {
		$base = admin_url( 'admin.php?page=' . self::SLUG );

		$top = [
			[
				'key'   => self::TAB_DASHBOARD,
				'label' => __( 'Dashboard', 'dsk-core' ),
				'icon'  => 'dashicons-dashboard',
				'href'  => $base . '#tab=' . self::TAB_DASHBOARD,
			],
		];

		$bottom = [
			[
				'key'   => self::TAB_SETTINGS,
				'label' => __( 'Settings', 'dsk-core' ),
				'icon'  => 'dashicons-admin-settings',
				'href'  => $base . '#tab=' . self::TAB_SETTINGS,
			],
		];

		foreach ( self::get_sorted_top_tabs() as $t ) {
			$top[] = [
				'key'   => $t['key'],
				'label' => $t['label'],
				'icon'  => $t['icon'],
				'href'  => $base . '#tab=' . $t['key'],
			];
		}

		foreach ( self::get_sorted_bottom_tabs() as $t ) {
			array_unshift( $bottom, [
				'key'   => $t['key'],
				'label' => $t['label'],
				'icon'  => $t['icon'],
				'href'  => $base . '#tab=' . $t['key'],
			] );
		}

		return [ 'top' => $top, 'bottom' => $bottom ];
	}

	/**
	 * Returns top-registered module tabs sorted alphabetically by label.
	 *
	 * @return array
	 */
	private static function get_sorted_top_tabs(): array {
		$tabs = self::$tabs_top;
		uasort( $tabs, fn( $a, $b ) => strcmp( $a['label'], $b['label'] ) );
		return $tabs;
	}

	/**
	 * Returns bottom-registered module tabs sorted alphabetically by label.
	 *
	 * @return array
	 */
	private static function get_sorted_bottom_tabs(): array {
		$tabs = self::$tabs_bottom;
		uasort( $tabs, fn( $a, $b ) => strcmp( $a['label'], $b['label'] ) );
		return $tabs;
	}
}