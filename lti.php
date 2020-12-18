<?php
/*
  Plugin Name: LTI
  Plugin URI: http://www.spvsoftwareproducts.com/php/wordpress-lti/
  Description: This plugin allows WordPress to be integrated with on-line courses using the IMS Learning Tools Interoperability (LTI) specification.
  Version: 2.0
  Network: true
  Author: Simon Booth, Stephen Vickers
  Author URI: http://www.celtic-project.org/
  License: GPL3
 */

/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2020  Simon Booth, Stephen P Vickers
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
 *  Contact: s.p.booth@stir.ac.uk
 */

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\Util;

/* -------------------------------------------------------------------
 * This is where we do all the Wordpress set up.
  ------------------------------------------------------------------ */

// Ensure timezone is set (default to UTC)
$cfg_timezone = date_default_timezone_get();
date_default_timezone_set($cfg_timezone);

// include the Library functions & lib.php loads LTI library
require_once ('includes' . DIRECTORY_SEPARATOR . 'lib.php');

// Check if a session is in place, if not create.
if (!session_id()) {
    session_start();
}

/* -------------------------------------------------------------------
 * This is called by Wordpress when it parses a request. Therefore
 * need to be careful when the request isn't LTI.
 *
 * Parameters
 *  $wp - WordPress environment class
  ------------------------------------------------------------------- */

function lti_parse_request($wp)
{
    global $lti_db_connector;

    if (isset($_GET['addplatform'])) {
        require_once('includes' . DIRECTORY_SEPARATOR . 'DoAddLTIPlatform.php');
        exit;
    } else if (isset($_GET['keys'])) {
        require_once('includes' . DIRECTORY_SEPARATOR . 'jwks.php');
        exit;
    } else if (isset($_GET['icon'])) {
        wp_redirect(plugins_url() . '/lti/wp.png');
        exit;
    } else if (isset($_GET['xml'])) {
        require_once('includes' . DIRECTORY_SEPARATOR . 'XML.php');
        exit;
    } else if (isset($_GET['configure'])) {
        if (strtolower(trim($_GET['configure'])) !== 'json') {
            require_once('includes' . DIRECTORY_SEPARATOR . 'CanvasXML.php');
        } else {
            require_once('includes' . DIRECTORY_SEPARATOR . 'CanvasJSON.php');
        }
        exit;
    } else if (($_SERVER['REQUEST_METHOD'] !== 'POST') && empty($_GET['iss'])) {
        return false;
    }
    // Clear any existing session variables for this plugin
    lti_reset_session();

    // Deal with magic quotes before they cause OAuth to fail
    lti_strip_magic_quotes();

    // Do the necessary
    $tool = new WPTool($lti_db_connector);
    $tool->handleRequest();
    exit();
}

// Add our function to the parse_request action
add_action('parse_request', 'lti_parse_request');

/* -------------------------------------------------------------------
 * Add menu pages. A new main item and a subpage
  ------------------------------------------------------------------ */

function lti_register_manage_submenu_page()
{

    // If plugin not actve simply return
    if (!is_plugin_active_for_network('lti/lti.php')) {
        return;
    }

    $manage_lti_page = add_menu_page(__('LTI Platforms', 'lti-text'), // <title>...</title>
        __('LTI Platforms', 'lti-text'), // Menu title
        'administrator', // Capability needed to see this page
        __('lti_platforms', 'lti-text'), // admin.php?page=lti_platforms
        'lti_platforms', // Function to call
        plugin_dir_url(__FILE__) . 'IMS.png'); // Image for menu item

    add_action('load-' . $manage_lti_page, 'lti_manage_screen_options');

    // Add submenus to the Manage LTI menu
    add_submenu_page(__('lti_platforms', 'lti-text'), // Menu page for this submenu
        __('Add New', 'lti-text'), // <title>...</title>
        __('Add New', 'lti-text'), // Menu title
        'administrator', // Capability needed to see this page
        __('lti_add_platform', 'lti-text'), // The slug name for this menu
        'lti_add_platform');                // Function to call

    add_submenu_page(__('lti_platforms', 'lti-text'), __('Options', 'lti-text'), __('Options', 'lti-text'), 'administrator',
        __('lti_options', 'lti-text'), 'lti_options_page');
}

// Add the Manage LTI option on the Network Admin page
add_action('network_admin_menu', 'lti_register_manage_submenu_page');

/* -------------------------------------------------------------------
 * Add menu page under user (in network sites) for Synchronising
 * enrolments, sharing and managing shares
  ------------------------------------------------------------------ */

function lti_register_user_submenu_page()
{
    global $lti_db_connector;

    // Check this is an LTI site for which the following make sense
    if (is_null(get_option('ltisite'))) {
        return;
    }
    // Not sure if this is strictly needed but it stops the LTI option
    // appearing on the main site (/)
    if (is_main_site()) {
        return;
    }

    // Check whether this blog is LTI, if not return
    if (get_option('ltisite') == 1) {

        // Sort out platform instance and membership service stuff
        $platform = Platform::fromConsumerKey($_SESSION[LTI_SESSION_PREFIX . 'userkey'], $lti_db_connector);
        $resource_link = ResourceLink::fromPlatform($platform, $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink']);

        // If there is a membership service then offer appropriate options
        if ($resource_link->hasMembershipsService()) {
            // Add a submenu to the users menu
            $plugin_page = add_users_page(__('Sync Enrolments', 'lti-text'), __('Sync Enrolments', 'lti-text'), 'administrator',
                __('lti_sync_enrolments', 'lti-text'), 'lti_sync_enrolments');

            // Called when lti_sync_enrolments page is called
            add_action('admin_head-' . $plugin_page, 'lti_sync_admin_header');
        }

        // Add a submenu to the tool menu for sharing if sharing is enabled and this is
        // the platform from where the sharing was initiated.
        if ($_SESSION[LTI_SESSION_PREFIX . 'key'] == $_SESSION[LTI_SESSION_PREFIX . 'userkey'] &&
            $_SESSION[LTI_SESSION_PREFIX . 'resourceid'] == $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink']) {
            $manage_share_keys_page = add_menu_page(
                __('LTI Share Keys', 'lti-text'), // <title>...</title>
                __('LTI Share Keys', 'lti-text'), // Menu title
                'administrator', // Capability needed to see this page
                __('lti_manage_share_keys', 'lti-text'), // admin.php?page=lti_manage_share_keys
                'lti_manage_share_keys', // Function to call
                plugin_dir_url(__FILE__) . 'IMS.png'); // Image for menu item

            add_action('load-' . $manage_share_keys_page, 'lti_manage_share_keys_screen_options');

            // Add submenus to the Manage LTI menu
            add_submenu_page(__('lti_manage_share_keys', 'lti-text'), // Menu page for this submenu
                __('Add New', 'lti-text'), // <title>...</title>
                __('Add New', 'lti-text'), // Menu title
                'administrator', // Capability needed to see this page
                __('lti_create_share_key', 'lti-text'), // The slug name for this menu
                'lti_create_share_key');                 // Function to call
        }
    }
}

// Add the menu stuff to the admin_menu function
add_action('admin_menu', 'lti_register_user_submenu_page');

/* -------------------------------------------------------------------
 * Called to redirect via html meta refresh when headers may have 
 * already been sent
  ------------------------------------------------------------------ */

function lti_header_safe_redirect($target_url) {
    if (headers_sent()) {
        printf('<meta http-equiv="refresh" content="0; URL='.$target_url.'" />');
    } else {
        wp_redirect($target_url);
    }
}

/* -------------------------------------------------------------------
 * Called when the admin header is generated and used to do the
 * synchronising of membership
  ------------------------------------------------------------------ */

function lti_sync_admin_header()
{
    // If we're doing updates
    if (isset($_REQUEST['nodelete'])) {
        lti_update('nodelete');
    }
    if (isset($_REQUEST['delete'])) {
        lti_update('delete');
    }

    if (isset($_REQUEST['nodelete']) || isset($_REQUEST['delete'])) {
        if (!empty($_SESSION[LTI_SESSION_PREFIX . 'error'])) {
            lti_header_safe_redirect(get_admin_url() . "users.php?page=lti_sync_enrolments&action=error");
            exit();
        }
        lti_header_safe_redirect('users.php');
    }
}

/* -------------------------------------------------------------------
 * Pop up the screen choices on the Manage LTI screen
  ------------------------------------------------------------------ */

function lti_manage_screen_options()
{
    $screen = get_current_screen();
    add_screen_option('per_page', array('label' => __('Platforms', 'lti-text'), 'default' => 5, 'option' => 'lti_per_page'));

    $screen->add_help_tab(array(
        'id' => 'lti-display',
        'title' => __('Screen Display', 'lti-text'),
        'content' => '<p>' . __('You can specify the number of LTI Platforms to list per screen using the Screen Options tab.',
            'lti-text') . '</p>'
    ));
}

function lti_set_screen_options($status, $option, $value)
{
    if ('lti_per_page' === $option) {
        return $value;
    }
    return $status;
}

add_filter('set-screen-option', 'lti_set_screen_options', 10, 3);
add_filter('set_screen_option_lti_per_page', 'lti_set_screen_options', 10, 3);

/* -------------------------------------------------------------------
 * Function to produce the LTI list. Basically builds the form and
 * then uses the LTI_List_Table function to produce the list
  ------------------------------------------------------------------ */

function lti_platforms()
{
    // Load the class definition
    require_once('includes' . DIRECTORY_SEPARATOR . 'LTI_List_Table.php');

    $screen = get_current_screen();
    $screen_option = $screen->get_option('per_page', 'option');

    $user = get_current_user_id();
    $per_page = get_user_meta($user, $screen_option, true);
    if (empty($per_page) || $per_page < 1) {
        $per_page = $screen->get_option('per_page', 'default');
    }

    $lti = new LTI_List_Table($per_page);
    $lti->prepare_items();
    ?>
    <div class="wrap">

      <div id="icon-users" class="icon32"><br/></div>
      <h2><?php _e('Platforms', 'lti-text'); ?>
        <a href="<?php echo get_admin_url() ?>network/admin.php?page=lti_add_platform" class="add-new-h2"><?php
          _e('Add New', 'lti-text');
          ?></a></h2>
      <p>
        <?php echo __('Launch URL, Initiate Login URL, Redirection URI: ', 'lti-text') . '<b>' . get_option('siteurl') . '/?lti</b><br>'; ?>
        <?php echo __('Public Keyset URL: ', 'lti-text') . '<b>' . get_option('siteurl') . '/?lti&keys</b><br>'; ?>
        <?php
        echo __('Canvas configuration URLs: ', 'lti-text') . '<b>' . get_option('siteurl') . '/?lti&amp;configure</b>' . __(' (XML) and ',
            'lti-text') .
        '<b>' . get_option('siteurl') . '/?lti&amp;configure=json</b>' . __(' (JSON)', 'lti-text') . '<br>';
        ?>
      </p>
      <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
      <form id="lti-filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <!-- Now we can render the completed list table -->
        <?php $lti->display() ?>
      </form>

    </div>
    <?php
}

function lti_genkeysecret()
{
    header('Content-type: application/json');
    echo '{"Key": "' . lti_get_guid() . '","Secret": "' . Util::getRandomString(32) . '"}';
}

function lti_addplatform()
{
    require_once('includes' . DIRECTORY_SEPARATOR . 'DoAddLTIPlatform.php');
}

// Load various functions --- filename is function
require_once('includes' . DIRECTORY_SEPARATOR . 'AddLTIPlatform.php');

require_once('includes' . DIRECTORY_SEPARATOR . 'SyncEnrolments.php');

require_once('includes' . DIRECTORY_SEPARATOR . 'ShareKeys.php');

/* -------------------------------------------------------------------
 * Allow redirect to arbitrary site (as specified in return_url).
  ------------------------------------------------------------------ */
add_filter('allowed_redirect_hosts', 'lti_allow_ms_parent_redirect');

function lti_allow_ms_parent_redirect($allowed)
{
    if (isset($_SESSION[LTI_SESSION_PREFIX . 'return_url'])) {
        $allowed[] = parse_url($_SESSION[LTI_SESSION_PREFIX . 'return_url'], PHP_URL_HOST);
    }

    return $allowed;
}

/* -------------------------------------------------------------------
 * Update the logout url if the platform has provided.
  ------------------------------------------------------------------ */

function lti_set_logout_url($logout_url)
{

    if (isset($_SESSION[LTI_SESSION_PREFIX . 'key']) && !empty($_SESSION[LTI_SESSION_PREFIX . 'return_url'])) {
        $urlencode = '&redirect_to=' . urlencode($_SESSION[LTI_SESSION_PREFIX . 'return_url'] .
                'lti_msg=' . urlencode(__('You have been logged out of WordPress', 'lti-text')));
        $logout_url .= $urlencode;
    }

    return $logout_url;
}

// Use our URL instead of usual. Not sure if this is ultimately much
// use as WordPress opening in new window,
add_filter('logout_url', 'lti_set_logout_url');

/* -------------------------------------------------------------------
 * Function to add the last login on the home page
  ------------------------------------------------------------------ */

function lti_add_sum_tips_admin_bar_link()
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
        'title' => __('Last Login: ', 'lti-text') . $last_login
    ));
}

// Add the admin menu bar
add_action('admin_bar_menu', 'lti_add_sum_tips_admin_bar_link', 25);

add_action('admin_init', 'lti_options_init');

add_action('admin_action_lti_genkeysecret', 'lti_genkeysecret');
add_action('admin_action_lti_addplatform', 'lti_addplatform');

function lti_options_init()
{
    register_setting('lti_options_settings_group', 'lti_options');
    add_settings_section(
        'lti_options_setting_section', '', 'lti_options_section_info', 'lti_options_admin'
    );
    add_settings_field(
        'adduser', 'Hide Add User Menu', 'lti_adduser_callback', 'lti_options_admin', 'lti_options_setting_section'
    );
    add_settings_field(
        'mysites', 'Hide My Sites Menu', 'lti_mysites_callback', 'lti_options_admin', 'lti_options_setting_section'
    );
    add_settings_field(
        'scope', 'Default Username Format', 'lti_scope_callback', 'lti_options_admin', 'lti_options_setting_section'
    );
}

function lti_options_section_info()
{

}

function lti_adduser_callback()
{
    $options = get_option('lti_options');
    printf(
        '<input type="checkbox" name="lti_options[adduser]" id="adduser" value="1"%s> <label for="adduser">Check this box if there is no need to invite external users into blogs; i.e. all users will come via an LTI connection</label>',
        ( isset($options['adduser']) && $options['adduser'] === '1' ) ? ' checked' : ''
    );
}

function lti_mysites_callback()
{
    $options = get_option('lti_options');
    printf(
        '<input type="checkbox" name="lti_options[mysites]" id="mysites" value="1"%s> <label for="mysites">Check this box to prevent users from moving between their blogs in WordPress</label>',
        ( isset($options['mysites']) && $options['mysites'] === '1' ) ? ' checked' : ''
    );
}

function lti_scope_callback()
{
    $options = get_option('lti_options');
    ?>
    <fieldset><?php $checked = ( isset($options['scope']) && $options['scope'] === '3' ) ? 'checked' : ''; ?>
      <label for="lti_scope3"><input type="radio" name="lti_options[scope]" id="lti_scope3" value="3"<?php echo $checked; ?>> Resource: Prefix the ID with the consumer key and resource link ID</label><br>
      <?php $checked = ( isset($options['scope']) && $options['scope'] === '2' ) ? ' checked' : ''; ?>
      <label for="lti_scope2"><input type="radio" name="lti_options[scope]" id="lti_scope2" value="2"<?php echo $checked; ?>> Context: Prefix the ID with the consumer key and context ID</label><br>
      <?php $checked = ( isset($options['scope']) && $options['scope'] === '1' ) ? ' checked' : ''; ?>
      <label for="lti_scope1"><input type="radio" name="lti_options[scope]" id="lti_scope1" value="1"<?php echo $checked; ?>> Platform: Prefix the ID with the consumer key</label><br>
      <?php $checked = ( isset($options['scope']) && $options['scope'] === '0' ) ? ' checked' : ''; ?>
      <label for="lti_scope0"><input type="radio" name="lti_options[scope]" id="lti_scope0" value="0"<?php echo $checked; ?>> Global: Use ID value only</label>
    </fieldset>
    <?php
}

/* -------------------------------------------------------------------
 * Draw the LTI Options page
  ------------------------------------------------------------------ */

function lti_options_page()
{
    ?>
    <div class="wrap">
      <h2><?php _e('Options', 'lti-text') ?></h2>
      <?php settings_errors(); ?>

      <form method="post" action="../options.php">
        <?php
        settings_fields('lti_options_settings_group');
        $options = get_option('lti_options');
        // If no options set defaults
        if (!isset($options) || empty($options)) {
            update_option('lti_options', array('adduser' => 0, 'mysites' => 0, 'scope' => LTI_ID_SCOPE_DEFAULT));
        }
        do_settings_sections('lti_options_admin');
        submit_button();
        ?>
      </form>
    </div>
    <?php
}

/* -------------------------------------------------------------------
 * Activate hook to create database tables
  ------------------------------------------------------------------ */

function lti_create_db_tables()
{
    lti_create_db();
}

register_activation_hook(__FILE__, 'lti_create_db_tables');

/* -------------------------------------------------------------------
 * Remove the Your Profile from the side-menu
  ------------------------------------------------------------------ */

function lti_remove_menus()
{
    global $submenu;

    $options = get_option('lti_options');
    if ($options['adduser'] == 1) {
        unset($submenu['users.php'][10]);
    }

    unset($submenu['users.php'][15]);
}

add_action('admin_menu', 'lti_remove_menus');

/* -------------------------------------------------------------------
 * Remove Edit My Profile from the admin bar
  ------------------------------------------------------------------ */

function lti_admin_bar_item_remove()
{
    global $wp_admin_bar;

    if (is_super_admin()) {
        return;
    }

    /*     * *edit-profile is the ID** */
    $wp_admin_bar->remove_menu('edit-profile');
    $options = get_option('lti_options');
    if ($options && $options['mysites'] == 1) {
        $wp_admin_bar->remove_node('my-sites');
    }
}

add_action('wp_before_admin_bar_render', 'lti_admin_bar_item_remove', 0);

/* -------------------------------------------------------------------
 * Strip out some chars that don't work in usernames
  ------------------------------------------------------------------ */

function lti_remove_chars($login)
{
    $login = str_replace(':', '-', $login);
    $login = str_replace('{', '', $login);
    $login = str_replace('}', '', $login);

    return $login;
}

add_filter('pre_user_login', 'lti_remove_chars');

/* -------------------------------------------------------------------
 * Clean-up session
  ------------------------------------------------------------------ */

function lti_end_session()
{
    lti_reset_session();
}

add_action('wp_logout', 'lti_end_session');
?>