# Absoluto Template Viewer

> Instantly see which template is active on the current page — plus environment/debug status and related included files — right from the front-end admin bar.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress) ![PHP](https://img.shields.io/badge/PHP-7.0%2B-777BB4?logo=php) ![License](https://img.shields.io/badge/License-GPLv2%2B-green) ![Version](https://img.shields.io/badge/Stable-1.0.0-brightgreen)

---

## Overview

**Absoluto Template Viewer** is a lightweight developer utility for WordPress. It adds a front-end admin bar panel that helps you understand exactly what template file is being used and what related files are included.

Especially useful when debugging theme behavior, overrides, child themes, and complex template loading.

---

## Features

| Feature | Details |
|---|---|
| 🗂 **Template file + source** | Shows theme / plugin / mu-plugin / core origin |
| 📋 **Copy full path** | One-click copy for quick debugging |
| 🔢 **Page/Post/Object ID** | Displayed when available |
| 🎨 **Theme info** | Theme name and child theme status (YES/NO) |
| 🌍 **Environment badge** | LOCAL / STAGING / LIVE |
| 🐛 **Debug mode status** | ON / OFF |
| 📎 **Related Included Files** | Full list with individual copy support |
| ⚙️ **Settings shortcut** | Gear icon links directly to plugin settings |
| 👥 **Role-based visibility** | Control who sees the panel |

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`, or install via **Plugins → Add New** in your WordPress dashboard.
2. Activate **Absoluto Template Viewer** from the Plugins screen.
3. Navigate to **Settings → Current Template**.
4. Select the user roles that should be able to view the panel.
5. Open any front-end page while logged in — the admin bar panel will be visible.

---

## FAQ

**Why is the panel only visible on the front-end?**  
Template resolution primarily applies to front-end requests. The plugin is intentionally scoped there to stay out of the way.

**Who can see the template details?**  
Only logged-in users whose roles are enabled in **Settings → Current Template**.

**Can I copy file paths from the included files list?**  
Yes. Click **Copy full path** or any file row to copy its absolute path to your clipboard.

**Does this plugin send site data to third parties?**  
No. Everything runs locally. No telemetry is collected or transmitted.

---

## Requirements

- WordPress 5.0 or higher (tested up to 7.0)
- PHP 7.0 or higher

---

## Changelog

### 1.0.0
- Initial public release
- Front-end admin bar template inspector
- Role-based visibility settings
- Environment and debug mode indicators
- Related included files listing with copy support
- Settings shortcut and plugin action link

---

## License

Licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Support Development

If this plugin saves you time, consider buying me a coffee ☕

👉 [buymeacoffee.com/absoluto](https://buymeacoffee.com/absoluto)
