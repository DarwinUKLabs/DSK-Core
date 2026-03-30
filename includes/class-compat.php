<?php defined( 'ABSPATH' ) || exit;
/**
 * DSK_Compat
 *
 * Provides compatibility checks for third-party plugins.
 * All methods are safe to call at any point after plugins_loaded,
 * except where noted.
 *
 * @package DSK
 * @since   0.9.0
 */
final class DSK_Compat {
	// -------------------------------------------------------------------------
	// Elementor
	// -------------------------------------------------------------------------
	/**
	 * Check if Elementor is active.
	 *
	 * @return bool
	 */
	public static function is_elementor(): bool {
		return class_exists( '\Elementor\Plugin' );
	}
	/**
	 * Check if Elementor Pro is active.
	 * Note: only reliable after plugin init (plugins_loaded or later).
	 *
	 * @return bool
	 */
	public static function is_elementor_pro(): bool {
		return class_exists( '\ElementorPro\Plugin' );
	}
	// -------------------------------------------------------------------------
	// Advanced Custom Fields
	// -------------------------------------------------------------------------
	/**
	 * Check if ACF (free) is active.
	 *
	 * @return bool
	 */
	public static function is_acf(): bool {
		return class_exists( 'ACF' );
	}
	/**
	 * Check if ACF Pro is active.
	 *
	 * @return bool
	 */
	public static function is_acf_pro(): bool {
		return class_exists( 'ACF_PRO' );
	}

	public static function is_acf_any(): bool {
		return self::is_acf() || self::is_acf_pro();
	}
	// -------------------------------------------------------------------------
	// E-commerce
	// -------------------------------------------------------------------------
	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce(): bool {
		return class_exists( 'WooCommerce' );
	}
	// -------------------------------------------------------------------------
	// User Management
	// -------------------------------------------------------------------------
	/**
	 * Check if Login as User is active.
	 *
	 * @return bool
	 */
	public static function is_login_as_user(): bool {
		return class_exists( 'LoginAsUser' );
	}
	/**
	 * Check if New User Approve is active.
	 *
	 * @return bool
	 */
	public static function is_new_user_approve(): bool {
		return class_exists( 'PW_New_User_Approve' );
	}
	// -------------------------------------------------------------------------
	// SEO
	// -------------------------------------------------------------------------
	/**
	 * Check if Rank Math (free) is active.
	 *
	 * @return bool
	 */
	public static function is_rank_math(): bool {
		return class_exists( 'RankMath' );
	}
	/**
	 * Check if Rank Math Pro is active.
	 *
	 * @return bool
	 */
	public static function is_rank_math_pro(): bool {
		return class_exists( 'RankMathPro' );
	}
}