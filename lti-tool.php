<?php
/*
  Plugin Name: LTI Tool
  Plugin URI: http://www.spvsoftwareproducts.com/php/wordpress-lti/
  Description: This plugin allows WordPress to be integrated as a tool with on-line courses using the IMS Learning Tools Interoperability (LTI) specification.
  Version: 3.0.1
  Network: true
  Author: Simon Booth, Stephen P Vickers
  Author URI: http://www.celtic-project.org/
  License: GPL3
 */

/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2022  Simon Booth, Stephen P Vickers
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 *  Contact: Stephen P Vickers <stephen@spvsoftwareproducts.com>
 */

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\DataConnector\DataConnector;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Jwt\Jwt;

// Prevent loading this file directly
defined('ABSPATH') || exit;

/* -------------------------------------------------------------------
 * This is where we do all the Wordpress set up.
  ------------------------------------------------------------------ */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// include the Library functions & lib.php loads LTI library
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'lib.php');

/* -------------------------------------------------------------------
 * Initialisation once WordPress is loaded
  ------------------------------------------------------------------ */

function lti_tool_once_wp_loaded()
{
    global $wpdb, $lti_tool_data_connector;

    $allow = true;
    if (!empty(get_plugins('/lti'))) {  // Check for old version of plugin
        $is_active = is_plugin_active('lti/lti.php');
        if (is_multisite() && (get_site_option('lti_tool_options') === false)) {  // Check for settings in old plugin when no existing settings
            $options = get_site_option('lti_choices');
            if (!empty($options)) {
                if (!$is_active) {
                    include_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lti' . DIRECTORY_SEPARATOR . 'config.php');
                }
                // Check for any default settings from deprecated config.php file
                if (defined('LTI_LOG_LEVEL')) {
                    $options['loglevel'] = strval(LTI_LOG_LEVEL);
                }
                if (defined('LTI_SIGNATURE_METHOD')) {
                    $options['lti13_signaturemethod'] = LTI_SIGNATURE_METHOD;
                }
                if (defined('LTI_KID')) {
                    $options['lti13_kid'] = LTI_KID;
                }
                if (defined('LTI_PRIVATE_KEY')) {
                    $options['lti13_privatekey'] = LTI_PRIVATE_KEY;
                }
                if (defined('AUTO_ENABLE')) {
                    $options['registration_autoenable'] = strval(AUTO_ENABLE);
                }
                if (defined('ENABLE_FOR_DAYS')) {
                    $options['registration_enablefordays'] = strval(ENABLE_FOR_DAYS);
                }
                $options = array_merge(lti_tool_get_options(), $options);
                add_site_option('lti_tool_options', $options);
            }
        }
        if ($is_active) {
            $allow = false;
            if (is_multisite()) {
                add_action('network_admin_notices', 'lti_tool_old_plugin_active_error');
            } else {
                add_action('admin_notices', 'lti_tool_old_plugin_active_error');
            }
        }
    } elseif (!lti_tool_check_lti_library()) {
        $allow = false;
        if (is_multisite()) {
            add_action('network_admin_notices', 'lti_tool_error_deactivate');
        } else {
            add_action('admin_notices', 'lti_tool_error_deactivate');
        }
    }
    if (!$allow) {
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    } else {
        require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'WPTool.php');

        // Set logging level
        $options = lti_tool_get_options();
        Util::$logLevel = intval($options['loglevel']);

        // Set the default tool
        $tool = apply_filters('lti_tool_tool', null, null);
        if (empty($tool)) {
            $tool = new LTI_Tool_WPTool(null);
        }
        Tool::$defaultTool = $tool;

        $lti_tool_data_connector = DataConnector::getDataConnector($wpdb->dbh, $wpdb->base_prefix);
    }
}

add_action('wp_loaded', 'lti_tool_once_wp_loaded');

function lti_tool_old_plugin_active_error()
{
    $allowed = array('strong' => array(), 'em' => array());
    $msg = wp_kses(__('The <em>LTI Tool</em> plugin is an update for the old <em>LTI</em> plugin which is already installed on your system.  ' .
            'The <em>LTI</em> plugin must be deactivated before this new plugin can be activated.  <strong>Ensure that the <em>\'Delete data on uninstall?\'</em> option ' .
            'is not enabled in the old <em>LTI</em> plugin before deleting it from your system so that this updated version can continue using any ' .
            'LTI platforms you have already configured.</strong>', 'lti-tool'), $allowed);
    echo <<< EOD
    <div class="notice notice-error">
      <p>{$msg}</p>
    </div>

EOD;
}

function lti_tool_error_deactivate()
{
    $allowed = array('em' => array());
    $msg = wp_kses(__('The <em>LTI Tool</em> plugin has been deactivated because a dependency is missing; either use <em>Composer</em> to install the dependent libraries or activate the <em>ceLTIc LTI Library</em> plugin.',
            'lti-tool'), $allowed);
    echo <<< EOD
  <div class="notice notice-error">
  <p>{$msg}</p>
  </div>

EOD;
}

/* -------------------------------------------------------------------
 * This is called by Wordpress when it parses a request. Therefore
 * need to be careful when the request isn't LTI.
 *
 * Parameters
 *  $wp - WordPress environment class
  ------------------------------------------------------------------- */

function lti_tool_parse_request($wp)
{
    global $lti_tool_data_connector;

    if (isset($_GET['lti-tool']) || isset($_GET['lti'])) {  // Check for 'lti' parameter as well for backward compatibility
        if (isset($_GET['addplatform'])) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'DoAddLTIPlatform.php');
            exit;
        } else if (isset($_GET['saveoptions'])) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'DoSaveOptions.php');
            exit;
        } else if (isset($_GET['keys'])) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'jwks.php');
            exit;
        } else if (isset($_GET['icon'])) {
            wp_redirect(plugins_url('images/wp.png', __FILE__));
            exit;
        } else if (isset($_GET['xml'])) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'XML.php');
            exit;
        } else if (isset($_GET['configure'])) {
            if (strtolower(trim(sanitize_text_field($_GET['configure']))) !== 'json') {
                require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'CanvasXML.php');
            } else {
                require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'CanvasJSON.php');
            }
            exit;
        } else if (isset($_GET['registration'])) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'registration.php');
            exit;
        } else if (isset($_GET['loading'])) {
            wp_redirect(plugins_url('images/loading.gif', __FILE__));
            exit;
        } else if (($_SERVER['REQUEST_METHOD'] !== 'POST') && empty($_GET['iss']) && empty($_GET['openid_configuration'])) {
            return false;
        }

        // Set redirect
        if (!empty($_POST)) {
            $lti_tool_data_connector->redirect = !empty($_POST['custom_redirect']) ? $_POST['custom_redirect'] : null;
        }

        // Clear any existing session variables for this plugin
        lti_tool_reset_session(true);

        // Do the necessary
        $tool = apply_filters('lti_tool_tool', null, $lti_tool_data_connector);
        if (empty($tool)) {
            $tool = new LTI_Tool_WPTool($lti_tool_data_connector);
        }
        $tool->handleRequest();
        exit();
    }
}

// Add our function to the parse_request action
add_action('parse_request', 'lti_tool_parse_request');

/* -------------------------------------------------------------------
 * Add menu pages. A new main item and a subpage
  ------------------------------------------------------------------ */

function lti_tool_register_manage_submenu_page()
{
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'LTI_Tool_List_Table.php');

    // If plugin not actve simply return
    if ((is_multisite() && !is_plugin_active_for_network('lti-tool/lti-tool.php')) ||
        (!is_multisite() && !is_plugin_active('lti-tool/lti-tool.php'))) {
        return;
    }

    $manage_lti_page = add_menu_page(__('LTI Platforms', 'lti-tool'), // <title>...</title>
        __('LTI Platforms', 'lti-tool'), // Menu title
        'edit_plugins', // Capability needed to see this page
        'lti_tool_platforms', // admin.php?page=lti_tool_platforms
        'lti_tool_platforms', // Function to call
        plugins_url('images/ims.png', __FILE__)); // Image for menu item
    add_action('load-' . $manage_lti_page, 'lti_tool_manage_screen_options');
    if (is_multisite()) {
        $manage_lti_page .= '-network';
    }
    add_filter("manage_{$manage_lti_page}_columns", array('LTI_Tool_List_Table', 'define_columns'), 10, 0);

    // Add submenus to the Manage LTI menu
    add_submenu_page('lti_tool_platforms', // Menu page for this submenu
        __('Add New', 'lti-tool'), // <title>...</title>
        __('Add New', 'lti-tool'), // Menu title
        'edit_plugins', // Capability needed to see this page
        'lti_tool_add_platform', // The slug name for this menu
        'lti_tool_add_platform');                // Function to call

    add_submenu_page('lti_tool_platforms', __('Settings', 'lti-tool'), __('Settings', 'lti-tool'), 'edit_plugins',
        'lti_tool_options', 'lti_tool_options_page');
}

// Add the Manage LTI option on the Network Admin page
if (is_multisite()) {
    add_action('network_admin_menu', 'lti_tool_register_manage_submenu_page');
} else {
    add_action('admin_menu', 'lti_tool_register_manage_submenu_page');
}

/* -------------------------------------------------------------------
 * Add script for input form pages to prompt before leaving changes unsaved
  ------------------------------------------------------------------ */

function lti_tool_enqueue_scripts($hook)
{
    if ($hook === 'lti-platforms_page_lti_tool_add_platform') {
        wp_enqueue_script('lti-tool-platforms-js', plugins_url('js/formchanges.js', __FILE__));
        wp_enqueue_script('lti-tool-add-platform-js', plugins_url('js/genkey.js', __FILE__));
    } elseif ($hook === 'lti-platforms_page_lti_tool_options') {
        wp_enqueue_script('lti-tool-platforms-js', plugins_url('js/formchanges.js', __FILE__));
    }
}

// Insert the script file
add_action('admin_enqueue_scripts', 'lti_tool_enqueue_scripts');

/* -------------------------------------------------------------------
 * Add menu page under user (in network sites) for Synchronising
 * enrolments, sharing and managing shares
  ------------------------------------------------------------------ */

function lti_tool_register_user_submenu_page()
{
    global $lti_tool_data_connector, $lti_tool_session;

    // Check this is an LTI site for which the following make sense
    if (is_multisite() && is_null(get_option('lti_tool_site')) && is_null(get_option('ltisite'))) {  // Allow ltisite for backward compatibility
        return;
    }

    // Not sure if this is strictly needed but it stops the LTI option
    // appearing on the main site (/)
    if (is_multisite() && is_main_site()) {
        return;
    }

    // Check user is accessing via LTI
    if (empty($lti_tool_session['userkey'])) {
        return;
    }

    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'LTI_Tool_User_List_Table.php');
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'LTI_Tool_List_Keys.php');

    // Sort out platform instance and membership service stuff
    $platform = Platform::fromConsumerKey($lti_tool_session['userkey'], $lti_tool_data_connector);
    $resource_link = ResourceLink::fromPlatform($platform, $lti_tool_session['userresourcelink']);
    // If there is a membership service then offer appropriate options
    if ($resource_link->hasMembershipsService()) {
        // Add a user synchronisation menu option
        if (current_user_can('list_users')) {
            $sync_page = add_users_page(__('LTI Users Sync', 'lti-tool'), __('LTI Users Sync', 'lti-tool'), 'list_users',
                'lti_tool_sync_enrolments', 'lti_tool_sync_enrolments');
        } else {
            $sync_page = add_menu_page(
                __('LTI Users Sync', 'lti-tool'), __('LTI Users Sync', 'lti-tool'), 'edit_others_posts', 'lti_tool_sync_enrolments',
                'lti_tool_sync_enrolments', plugins_url('images/ims.png', __FILE__));
        }
        add_filter("manage_{$sync_page}_columns", array('LTI_Tool_User_List_Table', 'define_columns'), 10, 0);
        add_action('load-' . $sync_page, 'lti_tool_sync_admin_header');
    }

    // Add a submenu to the tool menu for sharing if sharing is enabled and this is
    // the platform from where the sharing was initiated.
    if (is_multisite() && ($lti_tool_session['key'] == $lti_tool_session['userkey']) &&
        ($lti_tool_session['resourceid'] == $lti_tool_session['userresourcelink'])) {
        $manage_share_keys_page = add_menu_page(
            __('LTI Share Keys', 'lti-tool'), // <title>...</title>
            __('LTI Share Keys', 'lti-tool'), // Menu title
            'edit_others_posts', // Capability needed to see this page
            'lti_tool_manage_share_keys', // admin.php?page=lti_tool_manage_share_keys
            'lti_tool_manage_share_keys', // Function to call
            plugins_url('images/ims.png', __FILE__)); // Image for menu item

        add_filter("manage_{$manage_share_keys_page}_columns", array('LTI_Tool_List_Keys', 'define_columns'), 10, 0);
        add_action('load-' . $manage_share_keys_page, 'lti_tool_manage_share_keys_screen_options');

        // Add submenus to the Manage LTI menu
        add_submenu_page('lti_tool_manage_share_keys', // Menu page for this submenu
            __('Add New', 'lti-tool'), // <title>...</title>
            __('Add New', 'lti-tool'), // Menu title
            'edit_others_posts', // Capability needed to see this page
            'lti_tool_create_share_key', // The slug name for this menu
            'lti_tool_create_share_key');                 // Function to call
    }
}

// Add the menu stuff to the admin_menu function
add_action('admin_menu', 'lti_tool_register_user_submenu_page');

/* -------------------------------------------------------------------
 * Called when the admin header is generated and used to do the
 * synchronising of membership
  ------------------------------------------------------------------ */

function lti_tool_sync_admin_header()
{
    // If we're doing updates
    if (isset($_REQUEST['nodelete'])) {
        lti_tool_update(false);
    } elseif (isset($_REQUEST['delete'])) {
        lti_tool_update(true);
    }

    $screen = get_current_screen();
    add_screen_option('per_page', array('label' => __('Users', 'lti-tool'), 'option' => 'users_per_page'));
    $screen->add_help_tab(array(
        'id' => 'lti-text-display',
        'title' => __('Screen Display', 'lti-tool'),
        'content' => '<p>' . __('You can decide how many users to list per screen using the Screen Options tab.', 'lti-tool') . '</p>'
    ));
}

/* -------------------------------------------------------------------
 * Pop up the screen choices on the Manage LTI screen
  ------------------------------------------------------------------ */

function lti_tool_manage_screen_options()
{
    $screen = get_current_screen();
    add_screen_option('per_page',
        array('label' => __('LTI Platforms', 'lti-tool'), 'default' => 10, 'option' => 'lti_tool_per_page'));

    $screen->add_help_tab(array(
        'id' => 'lti-display',
        'title' => __('Screen Display', 'lti-tool'),
        'content' => '<p>' . __('You can specify the number of LTI Platforms to list per screen using the Screen Options tab.',
            'lti-tool') . '</p>'
    ));
}

function lti_tool_set_screen_options($status, $option, $value)
{
    if ('lti_tool_per_page' === $option) {
        return $value;
    }

    return $status;
}

add_filter('set-screen-option', 'lti_tool_set_screen_options', 10, 3);
add_filter('set_screen_option_lti_tool_per_page', 'lti_tool_set_screen_options', 10, 3);

/* -------------------------------------------------------------------
 * Function to produce the LTI list. Basically builds the form and
 * then uses the LTI_Tool_List_Table function to produce the list
  ------------------------------------------------------------------ */

function lti_tool_platforms()
{
    // Load the class definition
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'LTI_Tool_List_Table.php');

    $screen = get_current_screen();
    $screen_option = $screen->get_option('per_page', 'option');

    $user = get_current_user_id();
    $per_page = get_user_meta($user, $screen_option, true);
    if (empty($per_page) || $per_page < 1) {
        $per_page = $screen->get_option('per_page', 'default');
    }

    $lti = new LTI_Tool_List_Table($per_page);
    $lti->prepare_items();
    ?>
    <div class="wrap">

      <h1 class="wp-heading-inline"><?php _e('LTI Platforms', 'lti-tool'); ?></h1>
      <a href="<?php
      echo get_admin_url();
      if (is_multisite())
          echo 'network/';
      ?>admin.php?page=lti_tool_add_platform" class="page-title-action"><?php
         _e('Add New', 'lti-tool');
         ?></a>
      <hr class="wp-header-end">
      <p>
        <?php echo __('Launch URL, Initiate Login URL, Redirection URI, Dynamic Registration URL: ', 'lti-tool') . '<b>' . esc_html(get_option('siteurl')) . '/?lti-tool</b><br>'; ?>
        <?php echo __('Public Keyset URL: ', 'lti-tool') . '<b>' . esc_html(get_option('siteurl')) . '/?lti-tool&keys</b><br>'; ?>
        <?php
        echo __('Canvas configuration URLs: ', 'lti-tool') . '<b>' . esc_html(get_option('siteurl')) . '/?lti-tool&amp;configure</b>' . __(' (XML) and ',
            'lti-tool') .
        '<b>' . esc_html(get_option('siteurl')) . '/?lti-tool&amp;configure=json</b>' . __(' (JSON)', 'lti-tool') . '<br>';
        ?>
      </p>
      <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
      <form id="lti_tool_filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php esc_attr_e(sanitize_text_field($_REQUEST['page'])) ?>" />
        <!-- Now we can render the completed list table -->
        <?php $lti->display() ?>
      </form>

    </div>
    <?php
}

function lti_tool_genkeysecret()
{
    header('Content-type: application/json');
    echo '{"Key": "' . lti_tool_get_guid() . '","Secret": "' . Util::getRandomString(32) . '"}';
}

function lti_tool_addplatform()
{
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'DoAddLTIPlatform.php');
}

// Load various functions --- filename is function
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'AddLTIPlatform.php');

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'SyncEnrolments.php');

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'ShareKeys.php');

/* -------------------------------------------------------------------
 * Allow redirect to arbitrary site (as specified in return_url).
  ------------------------------------------------------------------ */
add_filter('allowed_redirect_hosts', 'lti_tool_allow_ms_parent_redirect');

function lti_tool_allow_ms_parent_redirect($allowed)
{
    global $lti_tool_session;

    if (isset($lti_tool_session['return_url'])) {
        $allowed[] = parse_url($lti_tool_session['return_url'], PHP_URL_HOST);
    }

    return $allowed;
}

/* -------------------------------------------------------------------
 * Update the logout url if the platform has provided.
  ------------------------------------------------------------------ */

function lti_tool_set_logout_url($logout_url)
{
    global $lti_tool_session;

    if (isset($lti_tool_session['key']) && !empty($lti_tool_session['return_url'])) {
        $tool_name = (!empty($lti_tool_session['tool_name'])) ? $lti_tool_session['tool_name'] : 'WordPress';
        $urlencode = '&redirect_to=' . urlencode($lti_tool_session['return_url'] .
                'lti_msg=' . urlencode(__("You have been logged out of {$tool_name}", 'lti-tool')));
        $logout_url .= $urlencode;
    }

    return $logout_url;
}

// Use platform URL if provided instead of usual.
add_filter('logout_url', 'lti_tool_set_logout_url');

/* -------------------------------------------------------------------
 * Update the logout link name.
  ------------------------------------------------------------------ */

function lti_tool_loginout($link)
{
    global $lti_tool_session;

    if (!empty($lti_tool_session['return_name']) && (strpos($link, 'action=logout') !== false)) {
        $link = preg_replace('/>.*<\/a>/', '>' . esc_html($lti_tool_session['return_name']) . '</a>', $link);
    }

    return $link;
}

add_filter('loginout', 'lti_tool_loginout');

/* -------------------------------------------------------------------
 * Function to add the last login on the home page
  ------------------------------------------------------------------ */

function lti_tool_add_sum_tips_admin_bar_link()
{
    global $wp_admin_bar, $current_user;

    // Only write last login on the home page
    if (!is_home()) {
        return;
    }

    wp_get_current_user();
    $last_login = get_user_meta($current_user->ID, 'Last Login', true);

    if (empty($last_login)) {
        return;
    }

    $wp_admin_bar->add_menu(array(
        'id' => 'Last',
        'title' => __('Last Login: ', 'lti-tool') . $last_login
    ));
}

// Add the admin menu bar
add_action('admin_bar_menu', 'lti_tool_add_sum_tips_admin_bar_link', 25);

add_action('admin_init', 'lti_tool_options_init');

add_action('admin_action_lti_tool_genkeysecret', 'lti_tool_genkeysecret');
add_action('admin_action_lti_tool_addplatform', 'lti_tool_addplatform');

function lti_tool_options_init()
{
    register_setting('lti_tool_options_settings_group', 'lti_tool_options');
    add_settings_section(
        'lti_tool_options_general_section', '', 'lti_tool_options_general_section_info', 'lti_tool_options_admin'
    );
    add_settings_field(
        'uninstalldb', __('Delete data on uninstall?', 'lti-tool'), 'lti_tool_uninstalldb_callback', 'lti_tool_options_admin',
        'lti_tool_options_general_section'
    );
    if (is_multisite()) {
        add_settings_field(
            'uninstallblogs', __('Delete LTI blogs on platform delete?', 'lti-tool'), 'lti_tool_uninstallblogs_callback',
            'lti_tool_options_admin', 'lti_tool_options_general_section'
        );
    }
    add_settings_field(
        'adduser', __('Hide <em>Add User</em> menu?', 'lti-tool'), 'lti_tool_adduser_callback', 'lti_tool_options_admin',
        'lti_tool_options_general_section'
    );
    if (is_multisite()) {
        add_settings_field(
            'mysites', __('Hide <em>My Sites</em> menu?', 'lti-tool'), 'lti_tool_mysites_callback', 'lti_tool_options_admin',
            'lti_tool_options_general_section'
        );
    }
    add_settings_field(
        'scope', __('Default username format', 'lti-tool'), 'lti_tool_scope_callback', 'lti_tool_options_admin',
        'lti_tool_options_general_section'
    );
    add_settings_field(
        'saveemail', __('Save email addresses?', 'lti-tool'), 'lti_tool_saveemail_callback', 'lti_tool_options_admin',
        'lti_tool_options_general_section'
    );
    if (!is_multisite()) {
        add_settings_field(
            'homepage', __('Homepage', 'lti-tool'), 'lti_tool_homepage_callback', 'lti_tool_options_admin',
            'lti_tool_options_general_section'
        );
    }
    add_settings_field(
        'loglevel', __('Default logging level', 'lti-tool'), 'lti_tool_loglevel_callback', 'lti_tool_options_admin',
        'lti_tool_options_general_section'
    );

    add_settings_section(
        'lti_tool_options_roles_section', '', 'lti_tool_options_roles_section_info', 'lti_tool_options_admin'
    );
    add_settings_field(
        'role_staff', __('Staff', 'lti-tool'), 'lti_tool_roles_callback', 'lti_tool_options_admin',
        'lti_tool_options_roles_section', array('role' => 'staff')
    );
    add_settings_field(
        'role_student', __('Student', 'lti-tool'), 'lti_tool_roles_callback', 'lti_tool_options_admin',
        'lti_tool_options_roles_section', array('role' => 'student')
    );
    add_settings_field(
        'role_other', __('Other', 'lti-tool'), 'lti_tool_roles_callback', 'lti_tool_options_admin',
        'lti_tool_options_roles_section', array('role' => 'other')
    );

    add_settings_section(
        'lti_tool_options_lti13_section', '', 'lti_tool_options_lti13_section_info', 'lti_tool_options_admin'
    );
    add_settings_field(
        'lti13_signaturemethod', __('Signature method', 'lti-tool'), 'lti_tool_lti13_signaturemethod_callback',
        'lti_tool_options_admin', 'lti_tool_options_lti13_section'
    );
    add_settings_field(
        'lti13_kid', __('Key ID', 'lti-tool'), 'lti_tool_lti13_kid_callback', 'lti_tool_options_admin',
        'lti_tool_options_lti13_section'
    );
    add_settings_field(
        'lti13_privatekey', __('Private key', 'lti-tool'), 'lti_tool_lti13_privatekey_callback', 'lti_tool_options_admin',
        'lti_tool_options_lti13_section'
    );

    add_settings_section(
        'lti_tool_options_registration_section', '', 'lti_tool_options_registration_section_info', 'lti_tool_options_admin'
    );
    add_settings_field(
        'registration_autoenable', __('Auto enable?', 'lti-tool'), 'lti_tool_registration_autoenable_callback',
        'lti_tool_options_admin', 'lti_tool_options_registration_section'
    );
    add_settings_field(
        'registration_enablefordays', __('Period to auto enable for', 'lti-tool'), 'lti_tool_registration_enablefordays_callback',
        'lti_tool_options_admin', 'lti_tool_options_registration_section'
    );

    do_action('lti_tool_init_options');
}

function lti_tool_options_general_section_info()
{
    echo('<h2>' . __('General', 'lti-tool') . "</h2>\n");
}

function lti_tool_options_roles_section_info()
{
    echo('<h2>' . __('Roles', 'lti-tool') . "</h2>\n");
}

function lti_tool_options_lti13_section_info()
{
    echo('<h2>' . __('LTI 1.3 Configuration', 'lti-tool') . "</h2>\n");
}

function lti_tool_options_registration_section_info()
{
    echo('<h2>' . __('Dynamic Registration', 'lti-tool') . "</h2>\n");
}

function lti_tool_uninstalldb_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[uninstalldb]" id="uninstalldb" value="1"%s> <label for="uninstalldb">' . __('Check this box if you want to permanently delete the LTI tables from the database when the plugin is uninstalled',
            'lti-tool') . '</label>', (!empty($options['uninstalldb'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_uninstallblogs_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[uninstallblogs]" id="uninstallblogs" value="1"%s> <label for="uninstallblogs">' . __('Check this box if you want to permanently delete the LTI blogs when the assoociated platform is deleted',
            'lti-tool') . '</label>', (!empty($options['uninstallblogs'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_adduser_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[adduser]" id="adduser" value="1"%s> <label for="adduser">' . __('Check this box if there is no need to invite external users into blogs; i.e. all users will come via an LTI connection',
            'lti-tool') . '</label>', (!empty($options['adduser'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_saveemail_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[saveemail]" id="savemeail" value="1"%s> <label for="saveemail">' . __('Check this box if email addresses should be saved in WordPress (only applies when a platforms uses a platform or global username format)',
            'lti-tool') . '</label>', (!empty($options['saveemail'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_mysites_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[mysites]" id="mysites" value="1"%s> <label for="mysites">' . __('Check this box to prevent users from moving between their blogs in WordPress',
            'lti-tool') . '</label>', (!empty($options['mysites'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_scope_callback()
{
    $options = lti_tool_get_options();

    $here = function($value) {
        return $value;
    };
    $escape = function($value) {
        return __($value, 'lti-tool');
    };
    $checked = function($value, $current) {
        return checked($value, $current, false);
    };
    echo <<< EOD
    <fieldset>

EOD;
    if (is_multisite()) {
        echo <<< EOD
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(Tool::ID_SCOPE_RESOURCE)}" value="{$here(Tool::ID_SCOPE_RESOURCE)}"{$checked(Tool::ID_SCOPE_RESOURCE,
            $options['scope'])}> {$escape('Resource: Prefix the ID with the consumer key and resource link ID')}</label><br>
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(Tool::ID_SCOPE_CONTEXT)}" value="{$here(Tool::ID_SCOPE_CONTEXT)}"{$checked(Tool::ID_SCOPE_CONTEXT,
            $options['scope'])}> {$escape('Context: Prefix the ID with the consumer key and context ID')}</label><br>

EOD;
    }
    echo <<< EOD
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(Tool::ID_SCOPE_GLOBAL)}" value="{$here(Tool::ID_SCOPE_GLOBAL)}"{$checked(Tool::ID_SCOPE_GLOBAL,
        $options['scope'])}> {$escape('Platform: Prefix the ID with the consumer key', 'lti-tool')}</label><br>
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(Tool::ID_SCOPE_ID_ONLY)}" value="{$here(Tool::ID_SCOPE_ID_ONLY)}"{$checked(Tool::ID_SCOPE_ID_ONLY,
        $options['scope'])}> {$escape('Global: Use ID value only', 'lti-tool')}</label><br>
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(LTI_Tool_WP_User::ID_SCOPE_USERNAME)}" value="{$here(LTI_Tool_WP_User::ID_SCOPE_USERNAME)}"{$checked(LTI_Tool_WP_User::ID_SCOPE_USERNAME,
        $options['scope'])}> {$escape('Username: Use platform username only', 'lti-tool')}</label><br>
      <label><input type="radio" name="lti_tool_options[scope]" id="lti_tool_scope{$here(LTI_Tool_WP_User::ID_SCOPE_EMAIL)}" value="{$here(LTI_Tool_WP_User::ID_SCOPE_EMAIL)}"{$checked(LTI_Tool_WP_User::ID_SCOPE_EMAIL,
        $options['scope'])}> {$escape('Email: Use email address only', 'lti-tool')}</label>
    </fieldset>

EOD;
}

function lti_tool_homepage_callback()
{
    $options = lti_tool_get_options();
    printf(
        '%s/<input type="text" name="lti_tool_options[homepage]" id="homepage" value="%s">', get_option('siteurl'),
        esc_attr($options['homepage'])
    );
    echo "\n";
}

function lti_tool_loglevel_callback()
{
    $name = 'loglevel';
    $options = lti_tool_get_options();
    $current = $options[$name];
    printf('<select name="lti_tool_options[%s]" id="%s">', esc_attr($name), esc_attr($name));
    echo "\n";
    $loglevels = array(__('No logging', 'lti-tool') => strval(Util::LOGLEVEL_NONE), __('Log errors only', 'lti-tool') => strval(Util::LOGLEVEL_ERROR),
        __('Log error and information messages', 'lti-tool') => strval(Util::LOGLEVEL_INFO), __('Log all messages', 'lti-tool') => strval(Util::LOGLEVEL_DEBUG));
    foreach ($loglevels as $key => $value) {
        $selected = ($value === $current) ? ' selected' : '';
        printf('  <option value="%s"%s>%s</option>', esc_attr($value), esc_attr($selected), esc_html($key));
        echo "\n";
    }
    echo ("</select>\n");
}

function lti_tool_roles_callback($args)
{
    $name = "role_{$args['role']}";
    $options = lti_tool_get_options();
    $current = $options[$name];
    printf('<select name="lti_tool_options[%s]" id="%s">', esc_attr($name), esc_attr($name));
    echo "\n";
    $roles = get_editable_roles();
    foreach ($roles as $key => $role) {
        $selected = ($key === $current) ? ' selected' : '';
        printf('  <option value="%s"%s>%s</option>', esc_attr($key), esc_attr($selected), esc_html($role['name']));
        echo "\n";
    }
    echo ("</select>\n");
}

function lti_tool_lti13_signaturemethod_callback()
{
    $name = 'lti13_signaturemethod';
    $options = lti_tool_get_options();
    $current = $options[$name];
    printf('<select name="lti_tool_options[%s]" id="%s">', esc_attr($name), esc_attr($name));
    echo "\n";
    $signaturemethods = Jwt::getJwtClient()->getSupportedAlgorithms();
    foreach ($signaturemethods as $signaturemethod) {
        $selected = ($signaturemethod === $current) ? ' selected' : '';
        printf('  <option value="%s"%s>%s</option>', esc_attr($signaturemethod), esc_attr($selected), esc_html($signaturemethod));
        echo "\n";
    }
    echo ("</select>\n");
}

function lti_tool_lti13_kid_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="text" name="lti_tool_options[lti13_kid]" id="lti13_kid" value="%s">', esc_attr($options['lti13_kid'])
    );
    echo "\n";
}

function lti_tool_lti13_privatekey_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<textarea name="lti_tool_options[lti13_privatekey]" id="lti13_privatekey" rows="10" cols="70" class="code">%s</textarea>',
        esc_attr($options['lti13_privatekey'])
    );
    echo "\n";
}

function lti_tool_registration_autoenable_callback()
{
    $options = lti_tool_get_options();
    printf(
        '<input type="checkbox" name="lti_tool_options[registration_autoenable]" id="registration_autoenable" value="1"%s> <label for="registration_autoenable">' . __('Check this box if platform registrations should be automatically enabled',
            'lti-tool') . '</label>', (!empty($options['registration_autoenable'])) ? ' checked' : ''
    );
    echo "\n";
}

function lti_tool_registration_enablefordays_callback()
{
    $name = 'registration_enablefordays';
    $options = lti_tool_get_options();
    $current = $options[$name];
    printf('<select name="lti_tool_options[%s]" id="%s">', esc_attr($name), esc_attr($name));
    echo "\n";
    $days = array(__('Unlimited', 'lti-tool') => '0', __('1 day', 'lti-tool') => '1', __('2 days', 'lti-tool') => '2', __('3 days',
            'lti-tool') => '3', __('4 days', 'lti-tool') => '4', __('5 days', 'lti-tool') => '5', __('1 week', 'lti-tool') => '7', __('2 weeks',
            'lti-tool') => '14', __('3 weeks', 'lti-tool') => '21', __('4 weeks', 'lti-tool') => '28', __('6 months', 'lti-tool') => '183', __('1 year',
            'lti-tool') => '365');
    foreach ($days as $key => $value) {
        $selected = ($value === $current) ? ' selected' : '';
        printf('  <option value="%s"%s>%s</option>', esc_attr($value), esc_attr($selected), esc_html($key));
        echo "\n";
    }
    echo ("</select>\n");
}

/* -------------------------------------------------------------------
 * Draw the LTI Settings page
  ------------------------------------------------------------------ */

function lti_tool_options_page()
{
    ?>
    <div class="wrap">
      <h1 class="wp-heading-inline"><?php _e('Settings', 'lti-tool'); ?></h1>
      <?php settings_errors(); ?>

      <form method="post" action="<?php echo esc_url(get_option('siteurl')) . '/?lti-tool&saveoptions'; ?>">
        <?php
        settings_fields('lti_tool_options_settings_group');
        do_settings_sections('lti_tool_options_admin');
        submit_button();
        ?>
      </form>
    </div>
    <?php
}

/* -------------------------------------------------------------------
 * Activate hook to create database tables
  ------------------------------------------------------------------ */

function lti_tool_create_db_tables()
{
    if (lti_tool_check_lti_library()) {
        lti_tool_create_db();
    }
}

register_activation_hook(__FILE__, 'lti_tool_create_db_tables');

/* -------------------------------------------------------------------
 * Remove the Your Profile from the side-menu
  ------------------------------------------------------------------ */

function lti_tool_remove_menus()
{
    global $submenu;

    if (lti_tool_check_lti_library()) {
        $options = lti_tool_get_options();
        if (!empty($options['adduser'])) {
            unset($submenu['users.php'][10]);
        }
    }

    unset($submenu['users.php'][15]);
}

add_action('admin_menu', 'lti_tool_remove_menus');

/* -------------------------------------------------------------------
 * Remove Edit My Profile from the admin bar
  ------------------------------------------------------------------ */

function lti_tool_admin_bar_item_remove()
{
    global $wp_admin_bar;

    if (is_super_admin()) {
        return;
    }

    /* edit-profile is the ID */
    $wp_admin_bar->remove_menu('edit-profile');
    $options = lti_tool_get_options();
    if (!empty($options['mysites'])) {
        $wp_admin_bar->remove_node('my-sites');
    }
}

add_action('wp_before_admin_bar_render', 'lti_tool_admin_bar_item_remove', 0);

/* -------------------------------------------------------------------
 * Strip out some chars that don't work in usernames
  ------------------------------------------------------------------ */

function lti_tool_remove_chars($login)
{
    $login = str_replace(':', '-', $login);
    $login = str_replace('{', '', $login);
    $login = str_replace('}', '', $login);

    return $login;
}

add_filter('pre_user_login', 'lti_tool_remove_chars');

/* -------------------------------------------------------------------
 * Clean-up session
  ------------------------------------------------------------------ */

function lti_tool_end_session()
{
    global $lti_tool_session;

    // Avoid deleting the session if the user is in the process of being logged in
    if (empty($lti_tool_session['logging_in'])) {
        lti_tool_reset_session();
    }
}

add_action('wp_logout', 'lti_tool_end_session');

/* -------------------------------------------------------------------
 * Keep the expiration time of the session the same as the logged-in cookie
  ------------------------------------------------------------------ */

function lti_tool_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token)
{
    global $lti_tool_session;

    $expire = $expiration - time();
    $lti_tool_session['_session_token'] = $token;
    set_site_transient("lti_tool_{$token}", $lti_tool_session, $expire);
}

add_action('set_logged_in_cookie', 'lti_tool_cookie', 10, 6);

/* -------------------------------------------------------------------
 * Initialise the global session each time the current user is verified
  ------------------------------------------------------------------ */

function lti_tool_init_session($cookie_elements, $user)
{
    global $lti_tool_session;

    $lti_tool_session = lti_tool_get_session();
}

add_action('auth_cookie_valid', 'lti_tool_init_session', 10, 2);
