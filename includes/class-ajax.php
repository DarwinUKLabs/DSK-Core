<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Ajax
 *
 * Registers and handles all DSK wp_ajax_* endpoints.
 * All handlers are authenticated — they require a valid nonce
 * and the manage_options capability.
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Ajax {

	/**
	 * Register AJAX action hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_dsk_load_tab',      [ self::class, 'load_tab'      ] );
		add_action( 'wp_ajax_dsk_save_modules',  [ self::class, 'save_modules'  ] );
		add_action( 'wp_ajax_dsk_save_settings', [ self::class, 'save_settings'  ] );
	}

	/**
	 * Verify nonce and capability for every authenticated endpoint.
	 * Terminates with a 403 JSON error response on failure.
	 *
	 * @return void
	 */
	public static function must_auth(): void {
		check_ajax_referer( 'dsk_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}
	}

	/**
	 * Load and return the HTML for a given admin tab.
	 *
	 * Expected POST params:
	 *   - tab (string) Tab key to render.
	 *
	 * @return void
	 */
	public static function load_tab(): void {
		self::must_auth();
		
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::must_auth().
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : '';

		if ( ! $tab ) {
			wp_send_json_error( [ 'message' => 'Missing tab' ], 400 );
		}

        try {
            ob_start();
            DSK_Admin_Menu::render_view( [ 'tab' => $tab ] );
            $html = ob_get_clean();
        } catch ( Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] );
        }

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Activate and deactivate DSK module plugins in a single call.
	 *
	 * Expected POST params:
	 *   - activate   (JSON string) Array of slugs to activate.
	 *   - deactivate (JSON string) Array of slugs to deactivate.
	 *
	 * @return void
	 */
	public static function save_modules(): void {
		self::must_auth();
		
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in self::must_auth(); JSON payload is sanitised after decoding.
        $to_activate_raw = json_decode( wp_unslash( $_POST['activate'] ?? '[]' ), true );
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in self::must_auth(); JSON payload is sanitised after decoding.
        $to_deactivate_raw = json_decode( wp_unslash( $_POST['deactivate'] ?? '[]' ), true );
        
        $to_activate   = is_array( $to_activate_raw ) ? array_map( 'sanitize_key', $to_activate_raw ) : [];
        $to_deactivate = is_array( $to_deactivate_raw ) ? array_map( 'sanitize_key', $to_deactivate_raw ) : [];

		$errors = [];

		foreach ( $to_activate as $slug ) {
			$plugin_file = DSK_Module_Registry::get_plugin_file( $slug );
			if ( ! $plugin_file ) { $errors[] = $slug . ': not found'; continue; }
			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) $errors[] = $slug . ': ' . $result->get_error_message();
		}

		foreach ( $to_deactivate as $slug ) {
			$plugin_file = DSK_Module_Registry::get_plugin_file( $slug );
			if ( ! $plugin_file ) { $errors[] = $slug . ': not found'; continue; }
			deactivate_plugins( $plugin_file );
		}

		if ( $errors ) {
			wp_send_json_error( [ 'message' => implode( ', ', $errors ) ], 500 );
		}

		wp_send_json_success();
	}
	
	/**
     * Save DSK core settings.
     *
     * Expected POST params:
     *   - settings (JSON string) Key/value map of settings to persist.
     *
     * @return void
     */
    public static function save_settings(): void {
    	self::must_auth();
    
    	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in self::must_auth(); JSON payload is validated after decoding.
    	$raw = isset( $_POST['settings'] ) ? wp_unslash( (string) $_POST['settings'] ) : '{}';
    
    	$incoming = json_decode( $raw, true );
    
    	if ( ! is_array( $incoming ) ) {
    		wp_send_json_error( [ 'message' => 'Invalid settings payload' ], 400 );
    	}
    
    	DSK_Settings::save( $incoming );
    	wp_send_json_success();
    }
}