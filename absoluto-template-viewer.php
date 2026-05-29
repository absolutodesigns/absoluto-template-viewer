<?php
/**
 * Plugin Name:       Absoluto Template Viewer
 * Plugin URI:        https://www.absolutodesigns.com/plugins/absoluto-template-viewer/
 * Description:       Shows the current template file name, theme, and included templates in the toolbar — with clipboard copy and per-role visibility settings.
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Absoluto Designs
 * Author URI:        http://absolutodesigns.com
 * Tested up to:      7.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       absoluto-template-viewer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────
//  Constants
// ─────────────────────────────────────────────
define( 'ABSOLTV_VERSION', '1.0.0' );
define( 'ABSOLTV_OPTION_KEY', 'absoltv_settings' );
define( 'ABSOLTV_MENU_SLUG', 'absoltv-settings' );
define( 'ABSOLTV_LEGACY_OPTION_KEY', 'atv_settings' );


// ─────────────────────────────────────────────
//  Default settings
// ─────────────────────────────────────────────
function absoltv_default_settings() {
    return array(
        'allowed_roles' => array( 'administrator' ),
    );
}

/**
 * Reads settings, migrating from the pre-review option key if present.
 */
function absoltv_get_settings() {
    $defaults = absoltv_default_settings();
    $saved     = get_option( ABSOLTV_OPTION_KEY, null );

    if ( null === $saved || false === $saved || '' === $saved ) {
        $legacy = get_option( ABSOLTV_LEGACY_OPTION_KEY, null );
        if ( is_array( $legacy ) && array() !== $legacy ) {
            update_option( ABSOLTV_OPTION_KEY, $legacy );
            delete_option( ABSOLTV_LEGACY_OPTION_KEY );
            $saved = $legacy;
        } else {
            $saved = array();
        }
    }

    return wp_parse_args( (array) $saved, $defaults );
}

// ─────────────────────────────────────────────
//  Access check — can the current user see info?
// ─────────────────────────────────────────────
function absoltv_current_user_can_view() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    $settings      = absoltv_get_settings();
    $allowed_roles = (array) $settings['allowed_roles'];
    $user          = wp_get_current_user();

    foreach ( $user->roles as $role ) {
        if ( in_array( $role, $allowed_roles, true ) ) {
            return true;
        }
    }
    return false;
}

// ─────────────────────────────────────────────
//  Current template path (via template_include, not global $template)
// ─────────────────────────────────────────────
add_filter( 'template_include', 'absoltv_filter_template_include', 9999 );

/**
 * Remembers the template path for the current request.
 *
 * @param string|null $absoltv_path Optional path to store; omit to read only.
 * @return string
 */
function absoltv_current_template_path( $absoltv_path = null ) {
    static $absoltv_stored_path = '';

    if ( null !== $absoltv_path ) {
        $absoltv_stored_path = is_string( $absoltv_path ) ? $absoltv_path : '';
    }

    return $absoltv_stored_path;
}

/**
 * @param string $absoltv_template_path Absolute path from WordPress.
 * @return string
 */
function absoltv_filter_template_include( $absoltv_template_path ) {
    absoltv_current_template_path( $absoltv_template_path );
    return $absoltv_template_path;
}

// ─────────────────────────────────────────────
//  Admin toolbar node
// ─────────────────────────────────────────────
add_action( 'admin_bar_menu', 'absoltv_add_toolbar_node', 100 );
add_action( 'shutdown', 'absoltv_capture_included_theme_files', 9999 );

function absoltv_add_toolbar_node( $wp_admin_bar ) {
    // Only on the front-end (not in wp-admin), and only for allowed users
    if ( is_admin() ) {
        return;
    }
    if ( ! absoltv_current_user_can_view() ) {
        return;
    }

    // Block / FSE theme notice
    if ( wp_is_block_theme() ) {
        $wp_admin_bar->add_node(
            array(
                'id'    => 'absoltv-root',
                'title' => '📄 Template: <em>Block theme — not applicable</em>',
                'meta'  => array( 'html' => '' ),
            )
        );
        return;
    }

    $absoltv_template_path = absoltv_current_template_path();

    if ( '' === $absoltv_template_path ) {
        return;
    }

    $theme_dir                = get_stylesheet_directory();
    $parent_dir               = get_template_directory();
    $absoltv_template_basename = wp_basename( $absoltv_template_path );
    $is_child                 = ( $theme_dir !== $parent_dir && strpos( $absoltv_template_path, $theme_dir ) === 0 );
    $absoltv_template_source  = absoltv_get_template_source_label( $absoltv_template_path, $theme_dir, $parent_dir );
    $object_id                = get_queried_object_id();
    $display_name             = esc_html( $absoltv_template_basename ) . ' ← ' . esc_html( $absoltv_template_source );

    // Root node — shows the main template
    $wp_admin_bar->add_node(
        array(
            'id'    => 'absoltv-root',
            'title' => 'Template: ' . $display_name,
            'href'  => '#',
            'meta'  => array(
                'title' => 'Current template: ' . esc_attr( $absoltv_template_path ),
            ),
        )
    );

    // Sub-node: copy full path
    $wp_admin_bar->add_node(
        array(
            'parent' => 'absoltv-root',
            'id'     => 'absoltv-copy-path',
            'title'  => '📋 Copy full path',
            'href'   => '#absoltv-copy:' . rawurlencode( $absoltv_template_path ),
            'meta'   => array(
                'class'       => 'absoltv-copy-btn',
                'data-path'   => esc_attr( $absoltv_template_path ),
                'title'       => 'Click to copy: ' . esc_attr( $absoltv_template_path ),
            ),
        )
    );

    if ( current_user_can( 'manage_options' ) ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'absoltv-root',
                'id'     => 'absoltv-settings-link',
                'title'  => '⚙',
                'href'   => admin_url( 'options-general.php?page=' . ABSOLTV_MENU_SLUG ),
                'meta'   => array(
                    'class' => 'absoltv-settings-btn',
                    'title' => __( 'Open Current Template settings', 'absoluto-template-viewer' ),
                ),
            )
        );
    }

    // Sub-node: queried object ID (above theme name)
    if ( $object_id ) {
        $id_label = is_singular( 'page' ) ? 'Page ID' : ( is_singular( 'post' ) ? 'Post ID' : 'Object ID' );
        $wp_admin_bar->add_node(
            array(
                'parent' => 'absoltv-root',
                'id'     => 'absoltv-object-id',
                'title'  => '🆔 ' . esc_html( $id_label ) . ': ' . absint( $object_id ),
                'href'   => '#',
            )
        );
    }

    // Sub-node: current theme
    $theme = wp_get_theme();
    $wp_admin_bar->add_node(
        array(
            'parent' => 'absoltv-root',
            'id'     => 'absoltv-theme',
            'title'  => '🎨 Theme: ' . esc_html( $theme->get( 'Name' ) )
                        . ( $is_child ? ' + ' . esc_html( wp_get_theme( get_template() )->get( 'Name' ) ) . ' (parent)' : '' ),
            'href'   => '#',
        )
    );

    // Sub-node: child theme status
    $wp_admin_bar->add_node(
        array(
            'parent' => 'absoltv-root',
            'id'     => 'absoltv-theme-type',
            'title'  => $is_child ? '🧩 Child theme: YES' : '🧩 Child theme: NO',
            'href'   => '#',
        )
    );

    // Sub-node: environment badge
    $env_badge = absoltv_get_environment_badge();
    $wp_admin_bar->add_node(
        array(
            'parent' => 'absoltv-root',
            'id'     => 'absoltv-environment',
            'title'  => $env_badge['label'],
            'href'   => '#',
            'meta'   => array(
                'class' => 'absoltv-env-' . $env_badge['key'],
                'title' => 'Detected environment: ' . strtoupper( $env_badge['key'] ),
            ),
        )
    );

    // Sub-node: debug mode status
    $debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $wp_admin_bar->add_node(
        array(
            'parent' => 'absoltv-root',
            'id'     => 'absoltv-debug-mode',
            'title'  => $debug_on ? '🐞 Debug mode: ON' : '🐞 Debug mode: OFF',
            'href'   => '#',
            'meta'   => array(
                'class' => $debug_on ? 'absoltv-debug-on' : 'absoltv-debug-off',
            ),
        )
    );

    // Sub-nodes: full included template files (theme scope)
    $all_included = absoltv_get_all_included_theme_files();
    if ( ! empty( $all_included ) ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'absoltv-root',
                'id'     => 'absoltv-all-parts-heading',
                'title'  => '── Related Included Files ──',
                'href'   => '#',
            )
        );

        foreach ( $all_included as $i => $row ) {
            $file = is_array( $row ) && ! empty( $row['file'] ) ? $row['file'] : $row;
            if ( ! is_string( $file ) || '' === $file ) {
                continue;
            }
            $rel = absoltv_format_theme_relative_path( $file, $theme_dir, $parent_dir );
            $wp_admin_bar->add_node(
                array(
                    'parent' => 'absoltv-root',
                    'id'     => 'absoltv-all-part-' . $i,
                    'title'  => esc_html( $rel ),
                    'href'   => '#absoltv-copy:' . rawurlencode( $file ),
                    'meta'   => array(
                        'class'     => 'absoltv-copy-btn',
                        'data-path' => esc_attr( $file ),
                        'title'     => 'Click to copy: ' . esc_attr( $file ),
                    ),
                )
            );
        }
    }
}

// ─────────────────────────────────────────────
//  Collect included template files
// ─────────────────────────────────────────────
function absoltv_get_all_included_theme_files() {
    $cached = get_transient( absoltv_get_included_files_cache_key() );
    if ( is_array( $cached ) && ! empty( $cached ) ) {
        return array_map(
            function( $file ) {
                return array( 'file' => $file );
            },
            $cached
        );
    }

    $included = absoltv_collect_included_theme_files();
    return array_map(
        function( $file ) {
            return array( 'file' => $file );
        },
        $included
    );
}

function absoltv_capture_included_theme_files() {
    if ( is_admin() || ! absoltv_current_user_can_view() ) {
        return;
    }

    $included = absoltv_collect_included_theme_files();
    set_transient( absoltv_get_included_files_cache_key(), $included, 10 * MINUTE_IN_SECONDS );
}

function absoltv_collect_included_theme_files() {
    $theme_dir  = get_stylesheet_directory();
    $parent_dir = get_template_directory();
    $files      = get_included_files();
    $included   = array();
    $seen       = array();

    foreach ( $files as $file ) {
        if ( ! is_string( $file ) || '' === $file ) {
            continue;
        }
        if ( strpos( $file, $theme_dir ) !== 0 && strpos( $file, $parent_dir ) !== 0 ) {
            continue;
        }
        if ( substr( $file, -4 ) !== '.php' ) {
            continue;
        }
        if ( isset( $seen[ $file ] ) ) {
            continue;
        }
        $seen[ $file ] = true;
        $included[]    = $file;
    }

    return $included;
}

function absoltv_get_included_files_cache_key() {
    $user_id = get_current_user_id();
    $uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $theme   = get_stylesheet();

    return 'absoltv_inc_' . md5( $theme . '|' . $user_id . '|' . $uri );
}

function absoltv_get_template_source_label( $file, $theme_dir, $parent_dir ) {
    $file_path   = wp_normalize_path( (string) $file );
    $theme_path  = wp_normalize_path( (string) $theme_dir );
    $parent_path = wp_normalize_path( (string) $parent_dir );

    if ( '' !== $theme_path && strpos( $file_path, $theme_path ) === 0 ) {
        if ( $theme_path !== $parent_path ) {
            return 'child-theme';
        }
        return 'theme';
    }

    if ( '' !== $parent_path && strpos( $file_path, $parent_path ) === 0 ) {
        return 'theme';
    }

    $plugin_dir = defined( 'WP_PLUGIN_DIR' ) ? wp_normalize_path( (string) WP_PLUGIN_DIR ) : '';
    if ( '' !== $plugin_dir && strpos( $file_path, $plugin_dir ) === 0 ) {
        return 'plugin';
    }

    $mu_plugin_dir = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( (string) WPMU_PLUGIN_DIR ) : '';
    if ( '' !== $mu_plugin_dir && strpos( $file_path, $mu_plugin_dir ) === 0 ) {
        return 'mu-plugin';
    }

    return 'core/other';
}

function absoltv_get_environment_badge() {
    $env = '';
    if ( function_exists( 'wp_get_environment_type' ) ) {
        $env = (string) wp_get_environment_type();
    }

    if ( '' === $env && defined( 'WP_ENV' ) ) {
        $env = (string) WP_ENV;
    }

    if ( '' === $env && isset( $_SERVER['HTTP_HOST'] ) ) {
        $host = strtolower( sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
        if ( false !== strpos( $host, 'localhost' ) || false !== strpos( $host, '.local' ) || false !== strpos( $host, '127.0.0.1' ) ) {
            $env = 'local';
        } elseif ( false !== strpos( $host, 'staging' ) || false !== strpos( $host, 'stage' ) || false !== strpos( $host, 'uat' ) || false !== strpos( $host, 'test' ) ) {
            $env = 'staging';
        } else {
            $env = 'production';
        }
    }

    $env = strtolower( trim( $env ) );
    if ( in_array( $env, array( 'local', 'development', 'dev' ), true ) ) {
        return array(
            'key'   => 'local',
            'label' => '🟢 LOCAL',
        );
    }
    if ( in_array( $env, array( 'staging', 'stage', 'uat', 'testing', 'test' ), true ) ) {
        return array(
            'key'   => 'staging',
            'label' => '🟡 STAGING',
        );
    }

    return array(
        'key'   => 'live',
        'label' => '🔴 LIVE',
    );
}

function absoltv_format_theme_relative_path( $file, $theme_dir, $parent_dir ) {
    $theme_slug  = wp_basename( $theme_dir );
    $parent_slug = wp_basename( $parent_dir );

    if ( strpos( $file, $theme_dir ) === 0 ) {
        $rel = ltrim( str_replace( $theme_dir, '', $file ), '/\\' );
        return 'themes/' . $theme_slug . '/' . $rel;
    }

    if ( strpos( $file, $parent_dir ) === 0 ) {
        $rel = ltrim( str_replace( $parent_dir, '', $file ), '/\\' );
        return 'themes/' . $parent_slug . '/' . $rel;
    }

    return wp_basename( $file );
}


// ─────────────────────────────────────────────
//  Front-end styles + clipboard script (enqueue API)
// ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'absoltv_enqueue_frontend_assets', 20 );

function absoltv_enqueue_frontend_assets() {
    if ( is_admin() || ! absoltv_current_user_can_view() ) {
        return;
    }

    $style_handle = 'absoltv-toolbar';
    wp_register_style( $style_handle, false, array(), ABSOLTV_VERSION );
    wp_enqueue_style( $style_handle );
    wp_add_inline_style( $style_handle, absoltv_get_frontend_inline_css() );

    $script_handle = 'absoltv-toolbar-clipboard';
    wp_register_script( $script_handle, false, array(), ABSOLTV_VERSION, true );
    wp_enqueue_script( $script_handle );
    wp_add_inline_script( $script_handle, absoltv_get_frontend_inline_js() );
}

/**
 * @return string
 */
function absoltv_get_frontend_inline_css() {
    return '
        #wp-admin-bar-absoltv-root > .ab-item {
            color: #80ff00 !important;
            font-weight: 600;
        }
        #wp-admin-bar-absoltv-root .ab-sub-wrapper {
            max-height: 65vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            position: relative;
            padding-top: 6px;
        }
        #wp-admin-bar-absoltv-root .ab-sub-wrapper .ab-submenu {
            max-height: none !important;
            position: relative;
            padding-top: 22px;
        }
        .absoltv-copy-btn > .ab-item {
            cursor: pointer !important;
        }
        .absoltv-copy-btn > .ab-item:hover {
            background: #0073aa !important;
            color: #fff !important;
        }
        #wp-admin-bar-absoltv-settings-link {
            position: static !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #wp-admin-bar-absoltv-settings-link > .ab-item {
            position: absolute !important;
            top: 4px;
            right: 8px;
            z-index: 10001;
            display: inline-block !important;
            min-width: 0 !important;
            margin: 0 !important;
            width: 26px;
            height: 24px;
            padding: 3px 0 !important;
            text-align: center;
            line-height: 1.1 !important;
            border-radius: 4px;
            color: #9ca3af !important;
            font-weight: 600;
            background: rgba(255,255,255,0.03) !important;
        }
        #wp-admin-bar-absoltv-settings-link > .ab-item:hover {
            color: #fff !important;
            background: #0073aa !important;
        }
        .absoltv-env-local > .ab-item {
            color: #46b450 !important;
            font-weight: 600;
        }
        .absoltv-env-staging > .ab-item {
            color: #dba617 !important;
            font-weight: 600;
        }
        .absoltv-env-live > .ab-item {
            color: #d63638 !important;
            font-weight: 600;
        }
        .absoltv-debug-on > .ab-item {
            color: #46b450 !important;
            font-weight: 600;
        }
        .absoltv-debug-off > .ab-item {
            color: #8c8f94 !important;
        }
        #wp-admin-bar-absoltv-parts-heading > .ab-item,
        #wp-admin-bar-absoltv-all-parts-heading > .ab-item {
            color: #aaa !important;
            font-style: italic;
            pointer-events: none;
            font-size: 11px !important;
        }
        #absoltv-toast {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #23282d;
            color: #fff;
            padding: 10px 18px;
            border-radius: 4px;
            font: 13px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            z-index: 999999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.35);
            transition: opacity 0.3s;
        }
    ';
}

/**
 * @return string
 */
function absoltv_get_frontend_inline_js() {
    return "(function () {\n"
        . "    if (!document.getElementById('absoltv-toast')) {\n"
        . "        var toastEl = document.createElement('div');\n"
        . "        toastEl.id = 'absoltv-toast';\n"
        . "        document.body.appendChild(toastEl);\n"
        . "    }\n"
        . "    function showToast(msg) {\n"
        . "        var t = document.getElementById('absoltv-toast');\n"
        . "        if (!t) return;\n"
        . "        t.textContent = msg;\n"
        . "        t.style.display = 'block';\n"
        . "        t.style.opacity = '1';\n"
        . "        clearTimeout(t._timer);\n"
        . "        t._timer = setTimeout(function () {\n"
        . "            t.style.opacity = '0';\n"
        . "            setTimeout(function () { t.style.display = 'none'; }, 320);\n"
        . "        }, 2000);\n"
        . "    }\n"
        . "    function copyToClipboard(text) {\n"
        . "        if (navigator.clipboard && window.isSecureContext) {\n"
        . "            navigator.clipboard.writeText(text).then(function () {\n"
        . "                showToast('✅ Copied: ' + text);\n"
        . "            }).catch(function () {\n"
        . "                fallbackCopy(text);\n"
        . "            });\n"
        . "        } else {\n"
        . "            fallbackCopy(text);\n"
        . "        }\n"
        . "    }\n"
        . "    function fallbackCopy(text) {\n"
        . "        var ta = document.createElement('textarea');\n"
        . "        ta.value = text;\n"
        . "        ta.setAttribute('readonly', '');\n"
        . "        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;pointer-events:none';\n"
        . "        document.body.appendChild(ta);\n"
        . "        ta.focus();\n"
        . "        ta.select();\n"
        . "        ta.setSelectionRange(0, ta.value.length);\n"
        . "        var copied = false;\n"
        . "        try {\n"
        . "            copied = document.execCommand('copy');\n"
        . "        } catch (e) {\n"
        . "            copied = false;\n"
        . "        }\n"
        . "        document.body.removeChild(ta);\n"
        . "        if (copied) {\n"
        . "            showToast('✅ Copied: ' + text);\n"
        . "            return;\n"
        . "        }\n"
        . "        window.prompt('Copy path:', text);\n"
        . "        showToast('⚠️ Clipboard blocked — use the prompt to copy.');\n"
        . "    }\n"
        . "    function getPathFromHref(href) {\n"
        . "        if (!href) return '';\n"
        . "        var marker = '#absoltv-copy:';\n"
        . "        var idx = href.indexOf(marker);\n"
        . "        if (idx !== -1) {\n"
        . "            var encoded = href.slice(idx + marker.length);\n"
        . "            try {\n"
        . "                return decodeURIComponent(encoded);\n"
        . "            } catch (err) {\n"
        . "                return encoded;\n"
        . "            }\n"
        . "        }\n"
        . "        try {\n"
        . "            var decodedHref = decodeURIComponent(href);\n"
        . "            var idxDecoded = decodedHref.indexOf(marker);\n"
        . "            if (idxDecoded !== -1) {\n"
        . "                return decodedHref.slice(idxDecoded + marker.length);\n"
        . "            }\n"
        . "        } catch (err2) {\n"
        . "        }\n"
        . "        return '';\n"
        . "    }\n"
        . "    function getPathFromTitle(title) {\n"
        . "        if (!title) return '';\n"
        . "        var prefix = 'Click to copy: ';\n"
        . "        if (title.indexOf(prefix) === 0) {\n"
        . "            return title.slice(prefix.length);\n"
        . "        }\n"
        . "        return '';\n"
        . "    }\n"
        . "    document.addEventListener('click', function (e) {\n"
        . "        var link = e.target.closest('a');\n"
        . "        if (!link) return;\n"
        . "        var href = link.getAttribute('href') || '';\n"
        . "        var path = getPathFromHref(href);\n"
        . "        if (!path) {\n"
        . "            path = getPathFromTitle(link.getAttribute('title') || '');\n"
        . "        }\n"
        . "        if (!path) {\n"
        . "            var li = link.closest('li');\n"
        . "            path = getPathFromTitle(li ? (li.getAttribute('title') || '') : '');\n"
        . "        }\n"
        . "        if (!path) return;\n"
        . "        e.preventDefault();\n"
        . "        e.stopPropagation();\n"
        . "        copyToClipboard(path);\n"
        . "    }, true);\n"
        . "})();\n";
}

// ─────────────────────────────────────────────
//  Settings page
// ─────────────────────────────────────────────
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'absoltv_add_plugin_action_links' );

function absoltv_add_plugin_action_links( $links ) {
    $settings_url  = admin_url( 'options-general.php?page=' . ABSOLTV_MENU_SLUG );
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'absoluto-template-viewer' ) . '</a>';
    array_unshift( $links, $settings_link );

    return $links;
}

add_action( 'admin_menu', 'absoltv_register_settings_page' );

function absoltv_register_settings_page() {
    add_options_page(
        __( 'Current Template Viewer', 'absoluto-template-viewer' ),
        __( 'Current Template', 'absoluto-template-viewer' ),
        'manage_options',
        ABSOLTV_MENU_SLUG,
        'absoltv_render_settings_page'
    );
}

add_action( 'admin_init', 'absoltv_register_settings' );

function absoltv_register_settings() {
    register_setting(
        'absoltv_settings_group',
        ABSOLTV_OPTION_KEY,
        array(
            'sanitize_callback' => 'absoltv_sanitize_settings',
            'default'           => absoltv_default_settings(),
        )
    );

    add_settings_section(
        'absoltv_main_section',
        __( 'Visibility Settings', 'absoluto-template-viewer' ),
        '__return_false',
        ABSOLTV_MENU_SLUG
    );

    add_settings_field(
        'absoltv_allowed_roles',
        __( 'Roles that can see the template info', 'absoluto-template-viewer' ),
        'absoltv_render_roles_field',
        ABSOLTV_MENU_SLUG,
        'absoltv_main_section'
    );
}

function absoltv_sanitize_settings( $input ) {
    $clean = absoltv_default_settings();

    if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
        $all_roles              = array_keys( wp_roles()->roles );
        $clean['allowed_roles'] = array_values(
            array_intersect( array_map( 'sanitize_key', $input['allowed_roles'] ), $all_roles )
        );
    } else {
        $clean['allowed_roles'] = array();
    }

    return $clean;
}

function absoltv_render_roles_field() {
    $settings      = absoltv_get_settings();
    $allowed_roles = (array) $settings['allowed_roles'];
    $all_roles     = wp_roles()->roles;
    ?>
    <fieldset>
        <legend class="screen-reader-text"><?php esc_html_e( 'Allowed roles', 'absoluto-template-viewer' ); ?></legend>
        <?php foreach ( $all_roles as $role_key => $role_data ) : ?>
            <label style="display:block;margin-bottom:6px;">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr( ABSOLTV_OPTION_KEY ); ?>[allowed_roles][]"
                    value="<?php echo esc_attr( $role_key ); ?>"
                    <?php checked( in_array( $role_key, $allowed_roles, true ) ); ?>
                >
                <?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
                <code style="font-size:11px;color:#888;">(<?php echo esc_html( $role_key ); ?>)</code>
            </label>
        <?php endforeach; ?>
        <p class="description">
            <?php esc_html_e( 'Checked roles will see the template information in the front-end admin toolbar.', 'absoluto-template-viewer' ); ?>
        </p>
    </fieldset>
    <?php
}

function absoltv_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <p>
            <?php esc_html_e( 'This plugin displays the current template file name and included template parts in the front-end admin toolbar. Use the settings below to control which user roles can see this information.', 'absoluto-template-viewer' ); ?>
        </p>
        <p>
            <a href="https://buymeacoffee.com/absoluto" target="_blank" rel="noopener noreferrer">
                ☕ <?php esc_html_e( 'Support the plugin on Buy Me a Coffee', 'absoluto-template-viewer' ); ?>
            </a>
        </p>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'absoltv_settings_group' );
            do_settings_sections( ABSOLTV_MENU_SLUG );
            submit_button();
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e( 'Features', 'absoluto-template-viewer' ); ?></h2>
        <ul style="list-style:disc;padding-left:20px;line-height:1.8">
            <li><?php esc_html_e( 'Shows active template file name in the toolbar dropdown.', 'absoluto-template-viewer' ); ?></li>
            <li><?php esc_html_e( 'Distinguishes child-theme vs parent-theme templates.', 'absoluto-template-viewer' ); ?></li>
            <li><?php esc_html_e( 'Lists all included template parts in the dropdown.', 'absoluto-template-viewer' ); ?></li>
            <li><?php esc_html_e( 'Click any item to copy its full server path to your clipboard.', 'absoluto-template-viewer' ); ?></li>
            <li><?php esc_html_e( 'Shows a friendly notice on block (FSE) themes instead of crashing.', 'absoluto-template-viewer' ); ?></li>
            <li><?php esc_html_e( 'Per-role visibility — configure exactly who can see the info.', 'absoluto-template-viewer' ); ?></li>
        </ul>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  Activation: set default options
// ─────────────────────────────────────────────
register_activation_hook( __FILE__, 'absoltv_activate' );

function absoltv_activate() {
    if ( false === get_option( ABSOLTV_OPTION_KEY ) ) {
        $legacy = get_option( ABSOLTV_LEGACY_OPTION_KEY, null );
        if ( is_array( $legacy ) && array() !== $legacy ) {
            add_option( ABSOLTV_OPTION_KEY, $legacy );
            delete_option( ABSOLTV_LEGACY_OPTION_KEY );
        } else {
            add_option( ABSOLTV_OPTION_KEY, absoltv_default_settings() );
        }
    }
}

// ─────────────────────────────────────────────
//  Deactivation: nothing to clean up
// ─────────────────────────────────────────────
register_deactivation_hook( __FILE__, '__return_false' );
