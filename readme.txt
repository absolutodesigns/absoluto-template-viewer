=== Absoluto Template Viewer ===
Contributors: absolutodesigns
Tags: template, theme, debug, developer, admin-bar
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instantly see which template is active on the current page, plus environment/debug status and related included files from the front-end admin bar.

== Description ==

**Absoluto Template Viewer** is a lightweight developer utility for WordPress.
It adds a front-end admin bar panel that helps you understand exactly what template file is being used and what related files are included.

This is especially useful when debugging theme behavior, overrides, child themes, and complex template loading.

### What you can see at a glance

* **Template file + source** (theme/plugin/mu-plugin/core)
* **Copy full path** action for quick debugging
* **Page/Post/Object ID** (when available)
* **Theme name** and **Child theme: YES/NO** status
* **Environment badge**: LOCAL / STAGING / LIVE
* **Debug mode status**: ON / OFF
* **Related Included Files** list with copy support
* **Settings gear shortcut** and plugin action link
* **Role-based visibility control**

### Built for clean workflow

* Front-end only output (no admin clutter)
* Fast local processing (no external API dependency)
* Clipboard helper with fallback behavior
* Safe output escaping and WordPress standards-friendly structure

Use **Settings > Current Template** to choose who can view the panel.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install through the WordPress Plugins screen.
2. Activate **Absoluto Template Viewer** from the Plugins screen.
3. Go to **Settings > Current Template**.
4. Select the user roles that can view template details.
5. Open any front-end page while logged in and use the admin bar panel.

== Frequently Asked Questions ==

= Why is the panel visible only on the front-end? =

Template resolution primarily applies to front-end requests. The plugin is intentionally focused there.

= Who can access template details? =

Only logged-in users whose roles are enabled in plugin settings.

= Can I copy file paths from the list? =

Yes. Click **Copy full path** or any file row to copy its absolute path.

= Does this plugin send site data to third parties? =

No. The plugin works locally and does not send telemetry.

= Where can I support development? =

Use the support link on the settings page:
[https://buymeacoffee.com/absoluto](https://buymeacoffee.com/absoluto)

== Changelog ==

= 1.0.0 =

* Initial public release.
* Front-end admin bar template inspector.
* Role-based visibility settings.
* Environment and debug mode indicators.
* Related included files listing with copy support.
* Settings shortcut and plugin action link.

