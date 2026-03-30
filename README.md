# Darwin Site Kit — Core

**Darwin Site Kit (DSK) Core** is a WordPress admin framework that acts as the foundation for DSK module plugins. On its own it provides a clean, modern admin interface with a dashboard, a settings system, and a module registry. Its real power comes when you pair it with DSK-compatible module plugins, each of which injects itself into the framework and gains a dedicated tab, settings panel, or both.

---

## Features

- **Module dashboard** — discover, activate, and deactivate DSK module plugins from a single screen, grouped by category with status tracking and visual feedback
- **Tab-based admin UI** — AJAX-driven, hash-routed navigation with animated transitions, collapsible sidebar, and light/dark mode support
- **Settings system** — a filterable schema-driven settings registry; modules register their own settings panels without touching core files
- **Module registry** — discovers installed DSK modules via plugin headers, boots active ones, and optionally hides them from the WordPress plugin list
- **Notice system** — modules can push floating in-app notices to alert users after activation or configuration changes
- **Dark mode** — full light/dark theme, persisted in localStorage, applied before first paint to avoid flash

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher

---

## Installation

1. Upload the `dsk-core` folder to `/wp-content/plugins/`
2. Activate **Darwin Site Kit** through the WordPress Plugins screen
3. Navigate to **Darwin Site Kit** in the admin sidebar

The plugin adds no front-end output and has no effect on your site's public pages.

---

## Modules

DSK Core is designed to be extended by DSK-compatible module plugins. Each module is a standard WordPress plugin with additional headers that identify it to the registry:

```
DSK-Module: true
DSK-Slug:   my-module
DSK-Icon:   dashicons-admin-generic
DSK-Color:  #A7B2C3
DSK-Tags:   utilities
DSK-Infos:  <p>What this module does.</p>
```

### Module registration

Modules self-register via the `dsk_register_module` action:

```php
add_action( 'dsk_register_module', function( DSK_Module_Registry $registry ) {
    $registry->add( [
        'slug' => 'my-module',
        'boot' => function() {
            require_once __DIR__ . '/module-init.php';
            My_Module::init( __FILE__ );
        },
    ] );
} );
```

### Adding a tab

Modules register a tab via the `dsk_tabs` filter:

```php
add_filter( 'dsk_tabs', function( array $tabs ): array {
    $tabs[] = [
        'key'      => 'my-module',
        'label'    => 'My Module',
        'icon'     => 'dashicons-admin-generic',
        'template' => __DIR__ . '/admin/tab.php',
        'position' => 'top', // 'top' | 'bottom'
    ];
    return $tabs;
} );
```

### Adding settings

Modules register settings groups via the `dsk_settings_groups` filter:

```php
add_filter( 'dsk_settings_groups', function( array $groups ): array {
    $groups['my_setting'] = [
        'category' => 'my_module', // settings tab slug
        'mode'     => 'single',    // 'single' or 'multiple' (collapsible card)
        'settings' => [
            'my_setting_key' => [
                'type'        => 'bool',
                'default'     => false,
                'label'       => 'Enable feature',
                'description' => 'Enables the feature across the site.',
            ],
        ],
    ];
    return $groups;
} );
```

Register the settings tab label:

```php
add_filter( 'dsk_settings_categories', function( array $cats ): array {
    $cats['my_module'] = 'My Module';
    return $cats;
} );
```

Read a setting anywhere:

```php
$value = DSK_Settings::get( 'my_setting_key' );
```

### Module base classes

DSK Core provides two abstract base classes for module development:

**`DSK_Module_Init_Base`** — provides path helpers, header parsing, and version shortcuts:

```php
final class My_Module extends DSK_Module_Init_Base {
    public static function init( string $plugin_file ): void {
        self::set_base( $plugin_file );
        self::require_includes();
        // boot services...
    }
    protected static function require_includes(): void {
        require_once self::path( 'includes/class-main.php' );
    }
}
```

**`DSK_Admin_Menu_Base`** — handles tab registration automatically from class constants:

```php
final class My_Module_Admin_Menu extends DSK_Admin_Menu_Base {
    protected const MODULE_INIT  = My_Module::class;
    protected const TAB_KEY      = 'my-module';
    protected const TAB_LABEL    = 'My Module';
    protected const TAB_TEMPLATE = 'admin/tab.php';
}
```

### Module tags

Known tags: `marketing`, `security`, `utilities`. Declare them in the plugin header:

```
DSK-Tags: security, utilities
```

Modules with unrecognised tags are not broken — unknown tags are silently ignored.

### Activation notices

Modules can display a one-time in-app notice after activation:

```php
// In the plugin root file:
register_activation_hook( __FILE__, function() {
    set_transient( 'dsk_notice_my_module_activated', true, 60 );
} );

// In the module init class:
private static function maybe_show_activation_notice(): void {
    if ( ! get_transient( 'dsk_notice_my_module_activated' ) ) return;
    delete_transient( 'dsk_notice_my_module_activated' );
    add_action( 'admin_footer', function() { ?>
        <script>
        window.DSK_NOTICES = window.DSK_NOTICES || [];
        window.DSK_NOTICES.push({
            message: 'My Module settings are available under Settings.',
            tab: 'settings',
            type: 'info'
        });
        </script>
    <?php } );
}
```

---

## JavaScript API

DSK Core exposes a global `DSK` object available on all DSK admin pages:

```js
// AJAX helper
DSK.ajax( 'action_name', { key: 'value' } ).then( function( data ) {} );

// Busy state for buttons
DSK.setBusy( true, btn );

// Subtab initialiser (chip ↔ panel bridge)
DSK.initSubtabs({
    chips:  '.my-tabs .dsk-subtab-chip',
    panels: '.dsk-subtab-panel',
});

// Notice system
DSK.addNotice( 'Something happened.', { tab: 'settings', type: 'info' } );

// Theme
DSK.setTheme( true ); // true = dark, false = light
```

---

## Frequently Asked Questions

**Does DSK Core do anything on its own?**
It provides the admin framework and dashboard. You need at least one DSK-compatible module plugin to get functionality beyond that.

**Where can I find DSK modules?**
Search for "DSK" in the WordPress plugin directory, or visit [darwin-labs.co.uk](https://darwin-labs.co.uk).

**Does it affect my site's front end?**
No. DSK Core and all its modules are admin-only.

**Can I build my own modules?**
Yes. Any plugin with the correct headers and registration hook is automatically discovered and managed by DSK Core.

---

## Changelog

### 1.0.0
- Initial public release
- Dashboard with module management, grouping by tag, and activation notices
- Settings system with filterable schema and per-category tabs
- Module registry with header-based discovery
- Light/dark theme with flash-free initialisation
- Notice system for module activation feedback
- Dark mode persisted per browser with no flash on load
