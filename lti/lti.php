<?php
/*
Plugin Name: LTI
Plugin URI: http://www.spvsoftwareproducts.com/php/wordpress-lti/
Description: This plugin allows WordPress to be integrated with on-line courses using the IMS Learning Tools Interoperability (LTI) specification.
Version: 1.2
Network: true
Author: Simon Booth, Stephen Vickers
Author URI: http://www.celtic-project.org/
License: GPL2
*/

/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2015  Simon Booth, Stephen P Vickers
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
 *
 *  Version history:
 *    1.0.00  18-Apr-13  Initial release
 *    1.1.00  14-Jan-15  Updated for later releases of WordPress
 *    1.2.00  14-Apr-16  Updated for MySQL 5.1.72 (works with earlier)
 */

/*-------------------------------------------------------------------
 * This is where we do all the Wordpress set up.
 ------------------------------------------------------------------*/

// Ensure timezone is set (default to UTC)
$cfg_timezone = date_default_timezone_get();
date_default_timezone_set($cfg_timezone);

// include the Library functions & lib.php loads LTI_Tool_Provider
require_once ('includes' . DIRECTORY_SEPARATOR . 'lib.php');

// Check if a session is in place, if not create.
if (!session_id()) session_start();

/*-------------------------------------------------------------------
 * This is called by Wordpress when it parses a request. Therefore
 * need to be careful when the request isn't LTI.
 *
 * Parameters
 *  $wp - WordPress environment class
 -------------------------------------------------------------------*/
function lti_parse_request($wp) {

  global $lti_db_connector;

  if (empty($_POST['lti_message_type'])) return FALSE;

  // Clear any existing session variables for this plugin
  lti_reset_session();

  // Deal with magic quotes before they cause OAuth to fail
  lti_strip_magic_quotes();

  // Do the necessary
  $tool = new LTI_Tool_Provider('lti_do_connect', $lti_db_connector);
  $tool->setParameterConstraint('resource_link_id', TRUE, 40);
  $tool->setParameterConstraint('user_id', TRUE);

  // Get settings and check whether sharing is enabled.
  $tool->allowSharing = TRUE;
  $tool->execute();
  exit();
}

// Add our function to the parse_request action
add_action('parse_request', 'lti_parse_request');

/*-------------------------------------------------------------------
 * Add menu pages. A new main item and a subpage
 ------------------------------------------------------------------*/
function lti_register_manage_submenu_page() {

  global $manage_lti_page, $lti_options_page;

  // If plugin not actve simply return
  if (!is_plugin_active_for_network('lti/lti.php')) return;

  $manage_lti_page =
    add_menu_page(__('LTI Tool Consumers', 'lti-text'),    // <title>...</title>
                  __('LTI Tool Consumers', 'lti-text'),    // Menu title
                  'administrator',                         // Capability needed to see this page
                  __('lti_consumers', 'lti-text'),         // admin.php?page=lti_consumers
                  'lti_consumers',                         // Function to call
                  plugin_dir_url( __FILE__ ) . 'IMS.png'); // Image for menu item

    add_action('load-' . $manage_lti_page, 'lti_manage_screen_options');

  // Add submenus to the Manage LTI menu
  add_submenu_page(__('lti_consumers', 'lti-text'),    // Menu page for this submenu
                   __('Add New', 'lti-text'),          // <title>...</title>
                   __('Add New', 'lti-text'),          // Menu title
                   'administrator',                    // Capability needed to see this page
                   __('lti_add_consumer', 'lti-text'), // The slug name for this menu
                   'lti_add_consumer');                // Function to call

  $lti_options_page = add_submenu_page(__('lti_consumers', 'lti-text'),
                                       __('Options', 'lti-text'),
                                       __('Options', 'lti-text'),
                                       'administrator',
                                       __('lti_options', 'lti-text'),
                                       'lti_options');
}

// Add the Manage LTI option on the Network Admin page
add_action('network_admin_menu', 'lti_register_manage_submenu_page');

/*-------------------------------------------------------------------
 * Add menu page under user (in network sites) for Synchronising
 * enrolments, sharing and managing shares
 ------------------------------------------------------------------*/
function lti_register_user_submenu_page() {

  // Check this is an LTI site for which the following make sense
  if (is_null(get_option('ltisite'))) return;
  // Not sure if this is strictly needed but it stops the LTI option
  // appearing on the main site (/)
  if (is_main_site()) return;

  global $current_user, $lti_options_page, $lti_db_connector;
  // Check whether this blog is LTI, if not return
  if (get_option('ltisite') == 1) {

    // Sort out consumer instance and membership service stuff
    $consumer = new LTI_Tool_Consumer($_SESSION[LTI_SESSION_PREFIX . 'userkey'], $lti_db_connector);
    $resource_link = new LTI_Resource_Link($consumer, $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink']);

    // If there is a membership service then offer appropriate options
    if ($resource_link->hasMembershipsService()) {
      // Add a submenu to the users menu
      $plugin_page = add_users_page(__('Sync Enrolments', 'lti-text'),
                                    __('Sync Enrolments', 'lti-text'),
                                    'administrator',
                                    __('lti_sync_enrolments', 'lti-text'),
                                    'lti_sync_enrolments');

      // Called when lti_sync_enrolments page is called
      add_action('admin_head-' . $plugin_page, 'lti_sync_admin_header');
    }

    // Add a submenu to the tool menu for sharing if sharing is enabled and this is
    // the consumer from where the sharing was initiated.
    if ($_SESSION[LTI_SESSION_PREFIX . 'key'] == $_SESSION[LTI_SESSION_PREFIX . 'userkey'] &&
        $_SESSION[LTI_SESSION_PREFIX . 'resourceid'] == $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink']) {
      $manage_share_keys_page = add_menu_page(
                    __('LTI Share Keys', 'lti-text'),        // <title>...</title>
                    __('LTI Share Keys', 'lti-text'),        // Menu title
                    'administrator',                         // Capability needed to see this page
                    __('lti_manage_share_keys', 'lti-text'), // admin.php?page=lti_manage_share_keys
                    'lti_manage_share_keys',                 // Function to call
                    plugin_dir_url( __FILE__ ) . 'IMS.png'); // Image for menu item

      add_action('load-' . $manage_share_keys_page, 'lti_manage_share_keys_screen_options');

      // Add submenus to the Manage LTI menu
      add_submenu_page(__('lti_manage_share_keys', 'lti-text'), // Menu page for this submenu
                       __('Add New', 'lti-text'),               // <title>...</title>
                       __('Add New', 'lti-text'),               // Menu title
                       'administrator',                         // Capability needed to see this page
                       __('lti_create_share_key', 'lti-text'),  // The slug name for this menu
                       'lti_create_share_key');                 // Function to call

//      add_action('load-' . $manage_share_keys_page, 'manage_share_keys_screen_options');
    }
  }
}

// Add the menu stuff to the admin_menu function
add_action('admin_menu', 'lti_register_user_submenu_page');

/*-------------------------------------------------------------------
 * Called when the admin header is generated and used to do the
 * synchronising of membership
 ------------------------------------------------------------------*/
function lti_sync_admin_header () {

  // If we're doing updates
  if (ISSET($_REQUEST['nodelete'])) lti_update('nodelete');
  if (ISSET($_REQUEST['delete']))   lti_update('delete');

  if (ISSET($_REQUEST['nodelete']) || ISSET($_REQUEST['delete'])) {
    if (!empty($_SESSION[LTI_SESSION_PREFIX . 'error'])) {
      wp_redirect(get_admin_url() . "users.php?page=lti_sync_enrolments&action=error");
      exit();
    }
    wp_redirect('users.php');
  }
}

/*-------------------------------------------------------------------
 * Pop up the screen choices on the Manage LTI screen
 ------------------------------------------------------------------*/
function lti_manage_screen_options() {

  global $manage_lti_page;

  $screen = get_current_screen();
  add_screen_option('per_page', array('label' => __('tool consumers', 'lti-text'), 'default' => 5, 'option' => 'lti_per_page'));

  $screen->add_help_tab( array(
    'id'      => 'lti-display',
    'title'   => __('Screen Display', 'lti-text'),
    'content' => '<p>' . __('You can specify the number of LTI Tool Consumers to list per screen using the Screen Options tab.', 'lti-text') . '</p>'
  ));
}

function lti_set_screen_options($status, $option, $value) {
  if ('lti_per_page' == $option) return $value;
  return $status;
}

add_filter('set-screen-option', 'lti_set_screen_options', 10, 3);
add_filter('set_screen_option_lti_per_page', 'lti_set_screen_options', 10, 3);

/*-------------------------------------------------------------------
 * Function to produce the LTI list. Basically builds the form and
 * then uses the LTI_List_Table function to produce the list
 ------------------------------------------------------------------*/
function lti_consumers() {

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
  <h2><?php _e('Tool Consumers', 'lti-text'); ?>
  <a href="<?php echo get_admin_url() ?>network/admin.php?page=lti_add_consumer" class="add-new-h2"><?php _e('Add New', 'lti-text'); ?></a></h2>
  <p>
  <?php echo sprintf(__(' Launch URL: %s', 'lti-text'), get_option('siteurl')) . '/?lti'; ?>
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

// Load various functions --- filename is function
require_once('includes' . DIRECTORY_SEPARATOR . 'doConnect.php');

require_once('includes' . DIRECTORY_SEPARATOR . 'AddLTIConsumer.php');

require_once('includes' . DIRECTORY_SEPARATOR . 'SyncEnrolments.php');

require_once('includes' . DIRECTORY_SEPARATOR . 'ShareKeys.php');

/*-------------------------------------------------------------------
 * Allow redirect to arbitrary site (as specified in return_url).
 ------------------------------------------------------------------*/
add_filter('allowed_redirect_hosts','lti_allow_ms_parent_redirect');
function lti_allow_ms_parent_redirect($allowed)
{
    if (isset($_SESSION[LTI_SESSION_PREFIX . 'return_url'])) {
      $allowed[] = parse_url($_SESSION[LTI_SESSION_PREFIX . 'return_url'], PHP_URL_HOST);
    }

    return $allowed;
}

/*-------------------------------------------------------------------
 * Update the logout url if the consumer has provided.
 ------------------------------------------------------------------*/
function lti_set_logout_url($logout_url) {

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

/*-------------------------------------------------------------------
 * Function to add the last login on the home page
 ------------------------------------------------------------------*/
function lti_add_sum_tips_admin_bar_link() {
  global $wp_admin_bar, $current_user;

  // Only write last login on the home page
  if (!is_home()) return;

  get_currentuserinfo();
  $last_login = get_user_meta($current_user->ID, 'Last Login', true);

  if (empty($last_login)) return;

  $wp_admin_bar->add_menu( array(
    'id' => 'Last',
    'title' => __('Last Login: ', 'lti-text') . $last_login
  ) );
}

// Add the admin menu bar
add_action('admin_bar_menu', 'lti_add_sum_tips_admin_bar_link', 25);

add_action('admin_init', 'lti_options_init');
function lti_options_init(){
  register_setting('lti_options_settings', 'lti_choices');
}
/*-------------------------------------------------------------------
 * Draw the LTI Options page
 ------------------------------------------------------------------*/
function lti_options() {
  ?>
  <div class="wrap">
    <h2><?php _e('Options', 'lti-text') ?></h2>
    <?php if (isset($_GET['settings-updated'])) { ?>
    <div id="message" class="updated">
      <p><?php _e('Options saved', 'lti-text') ?></p>
    </div>
    <?php } ?>
      <form method="post" action="<?php echo plugins_url() ?>/lti/includes/LTIChoicesUpdate.php">
      <?php settings_fields('lti_options_settings');

        $options = get_site_option('lti_choices');
        // If no options set defaults
        if (!isset($options) || empty($options)) {
          add_site_option('lti_choices', array('adduser' => 0, 'mysites' => 0, 'scope' => LTI_ID_SCOPE_DEFAULT));
          $options = get_site_option('lti_choices');
        }
      ?>
      <table class="form-table">
      <tbody>
      <tr>
        <th scope="row">
          <?php _e('Hide Add User Menu', 'lti-text'); ?>
        </th>
        <td>
          <fieldset>
            <legend class="screen-reader-text">
              <span><?php _e('Hide Add User Menu', 'lti-text') ?></span>
            </legend>
            <label for="lti_choices[adduser]">
              <input name="lti_choices[adduser]" type="checkbox" value="1" <?php checked('1', $options['adduser']); ?> />
              <?php _e('Check this box if there is no need to invite external users into blogs; i.e. all users will come via the LTI connection', 'lti-text'); ?>
            </label>
          </fieldset>
        </td>
      </tr>
      <tr>
        <th scope="row">
          <?php _e('Hide My Sites Menu', 'lti-text'); ?>
        </th>
        <td>
          <fieldset>
            <legend class="screen-reader-text">
              <span><?php _e('Hide My Sites Menu', 'lti-text') ?></span>
            </legend>
            <label for="lti_choices[mysites]">
              <input name="lti_choices[mysites]" type="checkbox" value="1" <?php checked('1', $options['mysites']); ?> />
              <?php _e('Check this box to prevent users from moving between their blogs in WordPress', 'lti-text'); ?>
            </label>
          </fieldset>
        </td>
      </tr>
      <tr>
        <th scope="row">
          <?php _e('Default Username Format', 'lti-text'); ?>
        </th>
        <td>
          <fieldset>
            <legend class="screen-reader-text">
              <span><?php _e('Default Username Format', 'lti-text') ?></span>
            </legend>
            <label for="lti_scope3">
              <input name="lti_choices[scope]" type="radio" id="lti_scope3" value="3" <?php checked('3', $options['scope']); ?> />
              <?php _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text'); ?>
            </label><br />
            <label for="lti_scope2">
              <input name="lti_choices[scope]" type="radio" id="lti_scope2" value="2" <?php checked('2', $options['scope']); ?> />
              <?php _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text'); ?>
            </label><br />
            <label for="lti_scope1">
              <input name="lti_choices[scope]" type="radio" id="lti_scope1" value="1" <?php checked('1', $options['scope']); ?> />
              <?php _e('Consumer: Prefix the ID with the consumer key', 'lti-text'); ?>
            </label><br />
            <label for="lti_scope0">
              <input name="lti_choices[scope]" type="radio" id="lti_scope0" value="0" <?php checked('0', $options['scope']); ?> />
              <?php _e('Global: Use ID value only', 'lti-text'); ?>
            </label>
          </fieldset>
        </td>
      </tr>
      </table>
      <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
      </p>
      </form>
    </div>
    <?php
}
/*-------------------------------------------------------------------
 * Activate hook to create database tables
 ------------------------------------------------------------------*/
function lti_create_db_tables() {
  lti_create_db();
}

register_activation_hook(__FILE__, 'lti_create_db_tables');

/*-------------------------------------------------------------------
 * Remove the Your Profile from the side-menu
 ------------------------------------------------------------------*/
function lti_remove_menus () {
  global $submenu;

  $options = get_site_option('lti_choices');
  if ($options['adduser'] == 1) unset($submenu['users.php'][10]);

  unset($submenu['users.php'][15]);
}

add_action('admin_menu', 'lti_remove_menus');

/*-------------------------------------------------------------------
 * Remove Edit My Profile from the admin bar
 ------------------------------------------------------------------*/
function lti_admin_bar_item_remove() {
  global $wp_admin_bar;

  if (is_super_admin()) return;

  /* **edit-profile is the ID** */
  $wp_admin_bar->remove_menu('edit-profile');
  $options = get_site_option('lti_choices');
  if ($options['mysites'] == 1) $wp_admin_bar->remove_node('my-sites');
}
add_action('wp_before_admin_bar_render', 'lti_admin_bar_item_remove', 0);

/*-------------------------------------------------------------------
 * Strip out some chars that don't work in usernames
 ------------------------------------------------------------------*/
function lti_remove_chars ($login) {
  $login = str_replace(':' , '-', $login);
  $login = str_replace('{' , '', $login);
  $login = str_replace('}' , '', $login);

  return $login;
}
add_filter('pre_user_login', 'lti_remove_chars');

/*-------------------------------------------------------------------
 * Clean-up session
 ------------------------------------------------------------------*/
function lti_end_session() {
  lti_reset_session();
}
add_action('wp_logout', 'lti_end_session');
?>