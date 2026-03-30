<?php defined( 'ABSPATH' ) || exit;

$settings   = DSK_Settings::all();
$categories = DSK_Settings::categories();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection, no state change.
$active_tab = isset( $_GET['dsk_tab'] ) ? sanitize_key( wp_unslash( $_GET['dsk_tab'] ) ) : 'default';
if ( ! array_key_exists( $active_tab, $categories ) ) $active_tab = 'default';

// ── Row renderers ─────────────────────────────────────────────────────────────

function dsk_render_row_single( string $key, array $field, array $settings ): void {
    $value   = $settings[ $key ] ?? $field['default'];
    $is_bool = ( $field['type'] ?? '' ) === 'bool';
    ?>
    <div class="dsk-setting-row">
        <div class="dsk-setting-content">
            <h3 class="dsk-setting-name"><?php echo esc_html( $field['label'] ); ?></h3>
            <?php if ( ! empty( $field['description'] ) ): ?>
                <p class="dsk-setting-description"><?php echo esc_html( $field['description'] ); ?></p>
            <?php endif; ?>
        </div>
        <?php if ( $is_bool ): ?>
            <div class="dsk-setting-toggle">
                <label class="dsk-switch">
                    <input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( (bool) $value ); ?>>
                    <span class="dsk-slider"></span>
                </label>
            </div>
        <?php else: ?>
            <div class="dsk-setting-input">
                <?php DSK_Settings::render_field( $key, $field, $value ); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function dsk_render_row_multiple( string $key, array $field, array $settings ): void {
    $value   = $settings[ $key ] ?? $field['default'];
    $is_bool = ( $field['type'] ?? '' ) === 'bool';
    ?>
    <div class="dsk-list-row">
        <?php if ( $is_bool ): ?>
            <div class="dsk-module-toggle switch-small">
                <?php DSK_Settings::render_field( $key, $field, $value ); ?>
            </div>
        <?php endif; ?>
        <div class="dsk-label">
            <strong><?php echo esc_html( $field['label'] ); ?></strong>
            <?php if ( ! empty( $field['description'] ) ): ?>
                <div class="dsk-muted"><?php echo esc_html( $field['description'] ); ?></div>
            <?php endif; ?>
            <?php if ( ! $is_bool ): DSK_Settings::render_field( $key, $field, $value ); endif; ?>
        </div>
    </div>
    <?php
}

// ── Group renderer ────────────────────────────────────────────────────────────
// 'single' mode: renders rows directly (no wrapper — parent is the list container).
// 'multiple' mode: renders a collapsible card.

function dsk_render_group( string $id, array $group, array $settings ): void {
    $mode   = $group['mode']     ?? 'single';
    $fields = $group['settings'] ?? [];
    if ( empty( $fields ) ) return;

    if ( $mode === 'single' ):
        foreach ( $fields as $key => $field ):
            dsk_render_row_single( $key, $field, $settings );
        endforeach;

    else:
        $card_id = 'dsk-settings-group-' . sanitize_html_class( $id );
        $list_id = $card_id . '-list';
        ?>
        <div class="dsk-card" id="<?php echo esc_attr( $card_id ); ?>">
            <div class="dsk-card-head">
                <h3><?php echo esc_html( $group['title'] ?? '' ); ?></h3>
                <button type="button" class="dsk-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $list_id ); ?>">
                    <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path fill="currentColor" d="M5.25 7.5 10 12.25 14.75 7.5 16 8.75 10 14.75 4 8.75z"/>
                    </svg>
                </button>
            </div>
            <div class="dsk-collapsible" id="<?php echo esc_attr( $list_id ); ?>" style="max-height:0;">
                <div class="dsk-list">
                    <?php foreach ( $fields as $key => $field ):
                        dsk_render_row_multiple( $key, $field, $settings );
                    endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    endif;
}

// ── Tab content renderer ──────────────────────────────────────────────────────

function dsk_render_tab( string $category, array $settings ): void {
    foreach ( DSK_Settings::groups_for( $category ) as $id => $group ) {
        dsk_render_group( $id, $group, $settings );
    }
}
?>

<div class="dsk-tab-header">
    <div class="dsk-tab-title">
        <h1>Settings</h1>
        <p>Configure your Darwin Site Kit settings.</p>
    </div>
    <div class="dsk-tools">
        <button type="button" class="button button-primary dsk-submit" id="dsk-settings-save" disabled>Save</button>
    </div>
</div>

<?php if ( count( $categories ) > 1 ): ?>
<div class="dsk-subtabs dsk-settings-subtabs">
    <?php foreach ( $categories as $slug => $label ): ?>
        <button type="button"
            class="dsk-subtab-chip<?php echo $slug === $active_tab ? ' is-active' : ''; ?>"
            data-tab="<?php echo esc_attr( $slug ); ?>">
            <?php echo esc_html( $label ); ?>
        </button>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="dsk-tab-inner">
    <?php foreach ( $categories as $slug => $label ): ?>
        <div class="dsk-subtab-panel dsk-settings-list<?php echo $slug === $active_tab ? ' is-active' : ''; ?>"
             data-panel="<?php echo esc_attr( $slug ); ?>">

            <?php if ( 'default' === $slug ): ?>
                <div class="dsk-setting-row">
                    <div class="dsk-setting-content">
                        <h3 class="dsk-setting-name">Activate dark mode</h3>
                        <p class="dsk-setting-description">Turn on to activate dark mode, off for light mode.</p>
                    </div>
                    <div class="dsk-setting-toggle">
                        <label class="dsk-switch">
                            <input type="checkbox" data-setting="switch-light-dark-mode">
                            <span class="dsk-slider"></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <?php dsk_render_tab( $slug, $settings ); ?>
        </div>
    <?php endforeach; ?>
</div>