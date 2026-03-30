<?php defined( 'ABSPATH' ) || exit;
/**
 * DSK_Settings
 *
 * Handles the settings schema, persistence, and category registry.
 * The template is responsible for rendering only — all logic lives here.
 *
 * ── Schema ────────────────────────────────────────────────────────────────────
 *
 * Groups are registered via the `dsk_settings_groups` filter.
 * Each group:
 *   - title    (string)   Display title. Required for 'multiple' mode.
 *   - category (string)   Tab slug. Omit or set 'default' for the main tab.
 *   - mode     (string)   'single' (bare rows) or 'multiple' (collapsible card).
 *   - settings (array)    Keyed array of field definitions.
 *
 * Each field:
 *   - type        (string)   'bool', 'int', 'string', 'select', 'custom'.
 *   - default     (mixed)
 *   - label       (string)
 *   - description (string)   Optional.
 *   - options     (array)    Required for type 'select'. [ value => label ]
 *   - render      (callable) Required for type 'custom'. Receives ($key, $field, $value).
 *   - sanitize    (callable) Optional for type 'custom'. Receives ($value).
 *
 * ── Categories ────────────────────────────────────────────────────────────────
 *
 * Categories (tabs) are derived automatically from registered groups.
 * 'default' is always first. Others are sorted alphabetically by label.
 * Category labels are registered via the `dsk_settings_categories` filter:
 *
 *   add_filter( 'dsk_settings_categories', function( $cats ) {
 *       $cats['my_module'] = 'My Module';
 *       return $cats;
 *   } );
 *
 * If a group declares a category with no registered label, the slug is
 * title-cased and used automatically.
 *
 * @package DSK
 * @since   1.0.0
 */
final class DSK_Settings {

    /** @var string Option key used to persist all settings. */
    public const OPTION_KEY = 'dsk_settings';

    // ── Groups ────────────────────────────────────────────────────────────────

    /**
     * Returns all registered groups, merged from core + filter.
     * Enforces that 'single' mode groups contain exactly one setting.
     *
     * @return array<string, array>
     */
    public static function groups(): array {
        $groups = [];

        $groups['hide_modules_from_plugin_list'] = [
            'category' => 'default',
            'mode'     => 'single',
            'settings' => [
                'hide_modules_from_plugin_list' => [
                    'type'        => 'bool',
                    'default'     => false,
                    'label'       => 'Hide modules from plugin list',
                    'description' => 'Hides DSK module plugins from the WordPress plugins screen.',
                ],
            ],
        ];

        $filtered = apply_filters( 'dsk_settings_groups', $groups );
        if ( ! is_array( $filtered ) ) return $groups;

        // Enforce: single mode groups may only have one setting
        foreach ( $filtered as $id => &$group ) {
            if ( ( $group['mode'] ?? 'single' ) === 'single' && count( $group['settings'] ?? [] ) > 1 ) {
                $group['settings'] = array_slice( $group['settings'], 0, 1, true );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    wp_trigger_error(
                        'DSK_Settings',
                        sprintf(
                            "Group '%s' is mode 'single' but declares multiple settings. Only the first will be used.",
                            esc_html( $id )
                        ),
                        E_USER_NOTICE
                    );
                }
            }
        }
        unset( $group );

        return $filtered;
    }

    /**
     * Returns groups belonging to a specific category.
     *
     * @param  string $category
     * @return array<string, array>
     */
    public static function groups_for( string $category ): array {
        return array_filter( self::groups(), function ( $group ) use ( $category ) {
            return ( $group['category'] ?? 'default' ) === $category;
        } );
    }

    // ── Categories (tabs) ─────────────────────────────────────────────────────

    /**
     * Returns all active categories, sorted: 'default' first, then alphabetical.
     * Only categories that have at least one group are returned.
     *
     * @return array<string, string>  [ slug => label ]
     */
    public static function categories(): array {
        $used = [];
        foreach ( self::groups() as $group ) {
            $used[ $group['category'] ?? 'default' ] = true;
        }

        $registered = apply_filters( 'dsk_settings_categories', [ 'default' => 'General' ] );
        if ( ! is_array( $registered ) ) $registered = [ 'default' => 'General' ];

        $cats = [];
        foreach ( array_keys( $used ) as $slug ) {
            $cats[ $slug ] = $registered[ $slug ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $slug ) );
        }

        $default_label = $cats['default'] ?? null;
        unset( $cats['default'] );
        asort( $cats );
        if ( $default_label !== null ) {
            $cats = array_merge( [ 'default' => $default_label ], $cats );
        }

        return $cats;
    }

    // ── Schema & values ───────────────────────────────────────────────────────

    /**
     * Flat map of all fields across all groups.
     *
     * @return array<string, array>
     */
    public static function schema(): array {
        $schema = [];
        foreach ( self::groups() as $group ) {
            foreach ( $group['settings'] ?? [] as $key => $field ) {
                $schema[ $key ] = $field;
            }
        }
        return $schema;
    }

    /**
     * Returns all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function all(): array {
        $saved    = get_option( self::OPTION_KEY, [] );
        $saved    = is_array( $saved ) ? $saved : [];
        $defaults = array_map( fn( $f ) => $f['default'], self::schema() );
        return array_merge( $defaults, $saved );
    }

    /**
     * Returns a single setting value.
     *
     * @param  string $key
     * @param  mixed  $fallback
     * @return mixed
     */
    public static function get( string $key, mixed $fallback = null ): mixed {
        $all = self::all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
    }

    // ── Persistence ───────────────────────────────────────────────────────────

    /**
     * Saves a flat array of settings, sanitized against the schema.
     * Called by the AJAX handler.
     *
     * @param  array $input   Raw input from JS: [ key => raw_value ]
     * @return array          Sanitized values that were saved.
     */
    public static function save( array $input ): array {
        $schema  = self::schema();
        $current = self::all();
        $clean   = [];

        foreach ( $input as $key => $raw ) {
            if ( ! isset( $schema[ $key ] ) ) continue;
            $clean[ $key ] = self::sanitize( $raw, $schema[ $key ] );
        }

        update_option( self::OPTION_KEY, array_merge( $current, $clean ) );
        return $clean;
    }

    /**
     * Sanitizes a single value against its field definition.
     *
     * @param  mixed $value
     * @param  array $field
     * @return mixed
     */
    private static function sanitize( mixed $value, array $field ): mixed {
        return match ( $field['type'] ) {
            'bool'   => (bool) $value,
            'int'    => (int) $value,
            'select' => ( isset( $field['options'][ $value ] ) )
                    ? sanitize_text_field( $value )
                    : sanitize_text_field( $field['default'] ?? '' ),
            'custom' => isset( $field['sanitize'] ) && is_callable( $field['sanitize'] )
                            ? call_user_func( $field['sanitize'], $value )
                            : sanitize_text_field( $value ),
            default  => sanitize_text_field( $value ),
        };
    }

    // ── Field rendering ───────────────────────────────────────────────────────

    /**
     * Renders a single field input.
     * For custom field types, provide a 'render' callable in the field definition.
     *
     * @param string $key
     * @param array  $field
     * @param mixed  $value
     */
    public static function render_field( string $key, array $field, mixed $value ): void {
        $type = $field['type'] ?? 'string';
    
        switch ( $type ) {
            case 'bool':
                ?>
                <label class="dsk-switch">
                    <input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( (bool) $value ); ?>>
                    <span class="dsk-slider"></span>
                </label>
                <?php
                break;
    
            case 'int':
                ?>
                <input type="number" class="dsk-input" data-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
                <?php
                break;
    
            case 'select':
                ?>
                <select class="dsk-input" data-setting="<?php echo esc_attr( $key ); ?>">
                    <?php foreach ( $field['options'] as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $value, $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
    
            case 'custom':
                if ( isset( $field['render'] ) && is_callable( $field['render'] ) ) {
                    call_user_func( $field['render'], $key, $field, $value );
                }
                break;
    
            default:
                ?>
                <input type="text" class="dsk-input" data-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
                <?php
                break;
        }
    }
}