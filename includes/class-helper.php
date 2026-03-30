<?php defined( 'ABSPATH' ) || exit;

/**
 * DSK_Helper
 *
 * General-purpose helper methods for Darwin Site Kit.
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Helper {

	/**
	 * Returns the plugin icon for use in add_menu_page().
	 * Encodes the SVG as a base64 data URI when available,
	 * falls back to a dashicon slug.
	 *
	 * @return string Base64 data URI or dashicon slug.
	 */
	public static function get_plugin_icon(): string {
		$svg = @file_get_contents( DSK_PATH . 'assets/admin/icon.svg' );

		return $svg
			? 'data:image/svg+xml;base64,' . base64_encode( $svg )
			: 'dashicons-admin-generic';
	}

	/**
	 * Returns the plugin icon as an inline SVG string for use in HTML templates.
	 * Falls back to a dashicon span when the SVG file is unavailable.
	 *
	 * @return string Inline SVG markup or dashicon span.
	 */
	public static function get_plugin_icon_html(): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $svg = @file_get_contents( DSK_PATH . 'assets/admin/icon.svg' );
    
        if ( $svg ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return $svg;
        }
    
        return '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>';
    }

	/**
	 * Returns the plugin display label.
	 *
	 * @return string
	 */
	public static function get_plugin_label(): string {
		return 'Darwin Site Kit';
	}
}