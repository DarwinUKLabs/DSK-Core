<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Admin_Menu_Base
 *
 * Abstract base for module admin menu integrations.
 * Handles tab registration into DSK_Admin_Menu via the `dsk_register_tabs` filter.
 *
 * Usage:
 *
 *   final class DSK_OL_Admin_Menu extends DSK_Admin_Menu_Base {
 *       protected const MODULE_INIT  = DSK_OL::class;
 *       protected const TAB_KEY      = 'omni-logger';
 *       protected const TAB_LABEL    = 'Omni Logger';
 *       protected const TAB_TEMPLATE = 'admin/tab-omni-logger.php';
 *   }
 *
 * TAB_TEMPLATE is resolved relative to the module's directory via MODULE_INIT::path().
 *
 * @package DSK
 * @since   0.9.0
 */
abstract class DSK_Admin_Menu_Base {

	/**
	 * The module's init class name (must extend DSK_Module_Init_Base).
	 * Used to resolve the icon and template path.
	 *
	 * @var class-string<DSK_Module_Init_Base>
	 */
	protected const MODULE_INIT = '';

	/**
	 * Unique tab key used in the #tab= hash.
	 * Convention: module slug, e.g. 'omni-logger'.
	 *
	 * @var string
	 */
	protected const TAB_KEY = '';

	/**
	 * Display label shown in the nav.
	 *
	 * @var string
	 */
	protected const TAB_LABEL = '';

	/**
	 * Nav position: 'top' (after Dashboard) or 'bottom' (before Settings).
	 *
	 * @var string
	 */
	protected const TAB_POSITION = 'top';

	/**
	 * Path to the tab template file, relative to the module directory.
	 * e.g. 'admin/tab-omni-logger.php'
	 *
	 * @var string
	 */
	protected const TAB_TEMPLATE = '';
	
	/** @var string Path to the tab CSS, relative to the module directory. */
    protected const TAB_CSS = '';
    
    /** @var string Path to the tab JS, relative to the module directory. */
    protected const TAB_JS = '';

	/**
	 * Hook into the `dsk_register_tabs` filter.
	 * Called from the module's init() method.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'dsk_tabs', [ static::class, 'register_tab' ] );
	}

	/**
	 * Append this module's tab definition to the tabs array.
	 * Sanitization and validation is handled by DSK_Admin_Menu::load_tabs().
	 *
	 * @param  array $tabs Existing tab definitions.
	 * @return array
	 */
	public static function register_tab( array $tabs ): array {
        $tabs[] = [
            'key'        => static::TAB_KEY,
            'label'      => static::TAB_LABEL,
            'icon'       => static::MODULE_INIT::module_icon(),
            'template'   => static::MODULE_INIT::path( static::TAB_TEMPLATE ),
            'position'   => static::TAB_POSITION,
            'menu_class' => static::class,
        ];
        return $tabs;
    }
	
	/**
     * Register static assets for lazy loading when the tab is first visited.
     * Call from the subclass init() after parent::init().
     *
     * @param string $css_url Fully-qualified URL to the CSS file, or empty string.
     * @param string $js_url  Fully-qualified URL to the JS file, or empty string.
     * @param string $version Cache-busting version string.
     */
    protected static function register_tab_assets( string $css_url, string $js_url, string $version ): void {
        add_filter( 'dsk_tabs', function( array $tabs ) use ( $css_url, $js_url, $version ) {
            foreach ( $tabs as &$tab ) {
                if ( $tab['key'] === static::TAB_KEY ) {
                    $tab['assets'] = [ 'css' => $css_url, 'js' => $js_url, 'version' => $version ];
                    break;
                }
            }
            return $tabs;
        }, 20 ); // after register_tab at default priority
    }
    
    /**
     * Print the lazy-load asset injector for this tab.
     * Call from the tab template: <?php static::print_tab_assets(); ?>
     */
    public static function print_tab_assets(): void {
        $guard  = 'DSK_TAB_' . strtoupper( preg_replace( '/[^a-zA-Z0-9]/', '_', static::TAB_KEY ) ) . '_LOADED';
        $module = static::MODULE_INIT;
        $v      = $module::version();
        $init   = $guard . '_INIT';
    
        $js  = '(function(){';
        $js .= 'if(!window[' . wp_json_encode( $guard ) . ']){';
        $js .= 'window[' . wp_json_encode( $guard ) . ']=true;';
    
        if ( static::TAB_CSS !== '' ) {
            $js .= 'var l=document.createElement("link");';
            $js .= 'l.rel="stylesheet";';
            $js .= 'l.href=' . wp_json_encode( $module::url( static::TAB_CSS ) . '?v=' . $v ) . ';';
            $js .= 'document.head.appendChild(l);';
        }
    
        if ( static::TAB_JS !== '' ) {
            $js .= 'var s=document.createElement("script");';
            $js .= 's.src=' . wp_json_encode( $module::url( static::TAB_JS ) . '?v=' . $v ) . ';';
            $js .= 's.onload=function(){';
            $js .= 'if(window[' . wp_json_encode( $init ) . '])window[' . wp_json_encode( $init ) . ']();';
            $js .= 'var w=document.querySelector(".dsk-tab-content");';
            $js .= 'if(w)w.classList.add("is-ready");';
            $js .= '};';
            $js .= 'document.head.appendChild(s);';
        } else {
            $js .= 'var w=document.querySelector(".dsk-tab-content");';
            $js .= 'if(w)w.classList.add("is-ready");';
        }
    
        $js .= '}else if(window[' . wp_json_encode( $init ) . ']){';
        $js .= 'window[' . wp_json_encode( $init ) . ']();';
        $js .= 'var w=document.querySelector(".dsk-tab-content");';
        $js .= 'if(w)w.classList.add("is-ready");';
        $js .= '}';
        $js .= '})();';
    
        wp_print_inline_script_tag( $js );
    }
}