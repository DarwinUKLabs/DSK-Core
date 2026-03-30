=== Darwin Site Kit ===
Contributors: darwinlabs
Tags: admin, modules, framework, dashboard, settings
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular WordPress admin framework. Manage your site with purpose-built modules from a single, clean dashboard.

== Description ==

Darwin Site Kit (DSK) Core is a WordPress admin framework designed to be extended by DSK-compatible module plugins. On its own it provides a clean, modern admin interface — a dashboard, a settings system, and a module registry. Pair it with DSK modules and each one gains a dedicated tab, settings panel, or both, all managed from one place.

**What DSK Core provides:**

* Module dashboard — activate and deactivate DSK modules with visual feedback, grouped by category
* Tab-based admin UI — AJAX-driven navigation with animated transitions and a collapsible sidebar
* Settings system — a filterable schema-driven registry; modules register their own panels without touching core files
* Module registry — discovers installed DSK modules via plugin headers and boots active ones automatically
* Notice system — modules can push floating in-app notices after activation or configuration changes
* Dark mode — full light/dark theme, persisted per browser, applied before first paint

**No front-end output.** DSK Core and all its modules are admin-only and have no effect on your site's public pages.

= Building modules =

Any WordPress plugin with the right headers is automatically discovered:

`DSK-Module: true`
`DSK-Slug:   my-module`
`DSK-Icon:   dashicons-admin-generic`
`DSK-Color:  #A7B2C3`
`DSK-Tags:   utilities`

Modules register via the `dsk_register_module` action, add tabs via `dsk_tabs`, and add settings via `dsk_settings_groups`. DSK Core ships abstract base classes (`DSK_Module_Init_Base`, `DSK_Admin_Menu_Base`) to make module development straightforward.

Known tags: `marketing`, `security`, `utilities`.

== Installation ==

1. Upload the `dsk-core` folder to `/wp-content/plugins/`
2. Activate **Darwin Site Kit** through the WordPress Plugins screen
3. Navigate to **Darwin Site Kit** in the admin sidebar

== Frequently Asked Questions ==

= Does DSK Core do anything on its own? =

It provides the admin framework and dashboard. You need at least one DSK-compatible module plugin to get functionality beyond that.

= Where can I find DSK modules? =

Search for "DSK" in the WordPress plugin directory, or visit darwin-labs.co.uk.

= Does it affect my site's front end? =

No. DSK Core and all its modules are admin-only.

= Can I build my own modules? =

Yes. Any plugin with the correct headers and registration hook is automatically discovered and managed by DSK Core. See the plugin's GitHub repository for full developer documentation.

= What PHP version is required? =

PHP 8.1 or higher. DSK Core uses typed properties, match expressions, and named arguments.

== Changelog ==

= 1.0.0 =
* Initial public release
* Dashboard with module management, tag grouping, and activation notices
* Settings system with filterable schema and per-category tabs
* Module registry with header-based discovery and optional plugin list hiding
* Light/dark theme with flash-free initialisation via synchronous inline script
* Notice system for module activation feedback
