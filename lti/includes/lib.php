<?php
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
 */

define('LTI_SESSION_PREFIX', 'lti_');
define('LTI_ID_SCOPE_DEFAULT', '3');

// include the LTI Tool Provider class
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'LTI_Tool_Provider.php';

global $wpdb;

$lti_db_connector = LTI_Data_Connector::getDataConnector($wpdb->base_prefix, $wpdb->dbh);

/*-------------------------------------------------------------------
 * LTI_WP_User - a local smaller definition of the LTI_User that
 * simply captures what needed in WP to deal with the various states
 * that can occur when synchronising
 *-----------------------------------------------------------------*/
class LTI_WP_User {

  public $id;
  public $username;
  public $firstname;
  public $lastname;
  public $fullname;
  //public $email;
  public $roles;
  public $staff = FALSE;
  public $learner = FALSE;

  public $new_to_blog;
  public $provision;
  public $newadmin;
  public $changed;
  public $role_changed;
  public $delete;

  function __construct() {

    if (func_num_args() == 1) {
      $args = func_get_args();
      $lti_user_from_consumer = $args[0];

      // Sharing --- this is Stephen's preference.
      $scope_userid = lti_get_scope($_SESSION[LTI_SESSION_PREFIX . 'userkey']);
      $user_login = $lti_user_from_consumer->getID($scope_userid);

      // Sanitize username stripping out unsafe characters
      $user_login = sanitize_user($user_login);

      // Apply the function pre_user_login before saving to the DB.
      $user_login = apply_filters('pre_user_login', $user_login);

      $this->username = $user_login;
      $this->firstname = $lti_user_from_consumer->firstname;
      $this->lastname = $lti_user_from_consumer->lastname;
      $this->fullname = $lti_user_from_consumer->fullname;
      //$this->email = $lti_user_from_consumer->email;
      $role_name = '';
      if (!empty($lti_user_from_consumer->roles)) {
        foreach ($lti_user_from_consumer->roles as $role) {
          $role_name .= $role . ',';
        }
        $this->roles = substr($role_name, 0, -1);
      }

      // Need to ensure that user who is both staff/student is treated as student
      $this->staff = $lti_user_from_consumer->isStaff() || $lti_user_from_consumer->isAdmin();
      if ($lti_user_from_consumer->isLearner()) {
        $this->staff = FALSE;
        $this->learner = TRUE;
      }

      $this->provision = FALSE;
      $this->new_to_blog = FALSE;
      $this->changed = FALSE;
      $this->role_changed = '';
    }

    if (func_num_args() == 0) {
      $this->username = '';
      $this->delete = TRUE;
    }
  }
}

/*-------------------------------------------------------------------
 * Delete a consumer and any blogs associated with it
 *
 * Parameter
 *  $key - key for consumer
 ------------------------------------------------------------------*/
function lti_delete($key) {

  global $wpdb, $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($key, $lti_db_connector);
  $consumer->delete();

  // Now delete the blogs associated with this key. The WP function that lists all
  // blog is depreciated and so we'll do a direct DB access (look the other way)
  $search_str = '/' . str_replace('.', '', $key) . '%';
  $sites = $wpdb->get_col($wpdb->prepare(
             "SELECT blog_id FROM wp_blogs WHERE path LIKE %s",
             $search_str, 0));

  // Delete the blog
  foreach ($sites as $site) {
    wpmu_delete_blog($site, TRUE);
  }

}

/*-------------------------------------------------------------------
 * Switch the enabled state for consumer
 *
 * Parameter
 *  $key - key for consumer
 ------------------------------------------------------------------*/
function lti_set_enable($key, $enable) {

  global $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($key, $lti_db_connector);
  $consumer->enabled = $enable;
  $consumer->save();
}

/*-------------------------------------------------------------------
 * Get whether a particular consumer is enabled.
 *
 * Parameter
 *  $key - key for consumer
 ------------------------------------------------------------------*/
function lti_get_enabled_state($key) {

  global $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($key, $lti_db_connector);

  return $consumer->enabled;
}

/*-------------------------------------------------------------------
 * Check that the tables necessary for classes are present, if not
 * create.
 ------------------------------------------------------------------*/
function lti_create_db() {

  global $wpdb;
  $prefix = $wpdb->prefix;

  $sql = 'CREATE TABLE IF NOT EXISTS ' . $prefix . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' (' .
         'consumer_key varchar(255) NOT NULL, ' .
         'name varchar(45) NOT NULL, ' .
         'secret varchar(32) NOT NULL, ' .
         'lti_version varchar(12) DEFAULT NULL, ' .
         'consumer_name varchar(255) DEFAULT NULL, ' .
         'consumer_version varchar(255) DEFAULT NULL, ' .
         'consumer_guid varchar(255) DEFAULT NULL, ' .
         'css_path varchar(255) DEFAULT NULL, ' .
         'protected tinyint(1) NOT NULL, ' .
         'enabled tinyint(1) NOT NULL, ' .
         'enable_from datetime DEFAULT NULL, ' .
         'enable_until datetime DEFAULT NULL, ' .
         'last_access date DEFAULT NULL, ' .
         'created datetime NOT NULL, ' .
         'updated datetime NOT NULL, ' .
         'PRIMARY KEY (consumer_key) ' .
         ') ENGINE=InnoDB DEFAULT CHARSET=latin1';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'CREATE TABLE IF NOT EXISTS ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' (' .
         'consumer_key varchar(255) NOT NULL, ' .
         'context_id varchar(255) NOT NULL, ' .
         'lti_context_id varchar(255) DEFAULT NULL, ' .
         'lti_resource_id varchar(255) DEFAULT NULL, ' .
         'title varchar(255) NOT NULL, ' .
         'settings text, ' .
         'primary_consumer_key varchar(255) DEFAULT NULL, ' .
         'primary_context_id varchar(255) DEFAULT NULL, ' .
         'share_approved tinyint(1) DEFAULT NULL, ' .
         'created datetime NOT NULL, ' .
         'updated datetime NOT NULL, ' .
         'PRIMARY KEY (consumer_key, context_id) ' .
         ') ENGINE=InnoDB DEFAULT CHARSET=latin1';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'CREATE TABLE IF NOT EXISTS ' . $prefix . LTI_Data_Connector::USER_TABLE_NAME . ' (' .
         'consumer_key varchar(255) NOT NULL, ' .
         'context_id varchar(255) NOT NULL, ' .
         'user_id varchar(255) NOT NULL, ' .
         'lti_result_sourcedid varchar(255) NOT NULL, ' .
         'created datetime NOT NULL, ' .
         'updated datetime NOT NULL, ' .
         'PRIMARY KEY (consumer_key, context_id, user_id) ' .
         ') ENGINE=InnoDB DEFAULT CHARSET=latin1';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'CREATE TABLE IF NOT EXISTS ' . $prefix . LTI_Data_Connector::NONCE_TABLE_NAME . ' (' .
         'consumer_key varchar(255) NOT NULL, ' .
         'value varchar(32) NOT NULL, ' .
         'expires datetime NOT NULL, ' .
         'PRIMARY KEY (consumer_key, value) ' .
         ') ENGINE=InnoDB DEFAULT CHARSET=latin1';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'CREATE TABLE IF NOT EXISTS ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' (' .
         'share_key_id varchar(32) NOT NULL, ' .
         'primary_consumer_key varchar(255) NOT NULL, ' .
         'primary_context_id varchar(255) NOT NULL, ' .
         'auto_approve tinyint(1) NOT NULL, ' .
         'expires datetime NOT NULL, ' .
         'PRIMARY KEY (share_key_id) ' .
         ') ENGINE=InnoDB DEFAULT CHARSET=latin1';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'ALTER TABLE ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
         'ADD CONSTRAINT ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . '_' .
         LTI_Data_Connector::CONSUMER_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_key) ' .
         'REFERENCES ' . $prefix . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' (consumer_key)';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'ALTER TABLE ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
         'ADD CONSTRAINT ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . '_' .
         LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (primary_consumer_key, primary_context_id) ' .
         'REFERENCES ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' (consumer_key, context_id)';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'ALTER TABLE ' . $prefix . LTI_Data_Connector::USER_TABLE_NAME . ' ' .
         'ADD CONSTRAINT ' . $prefix . LTI_Data_Connector::USER_TABLE_NAME . '_' .
         LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_key, context_id) ' .
         'REFERENCES ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' (consumer_key, context_id)';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'ALTER TABLE ' . $prefix . LTI_Data_Connector::NONCE_TABLE_NAME . ' ' .
         'ADD CONSTRAINT ' . $prefix . LTI_Data_Connector::NONCE_TABLE_NAME . '_' .
         LTI_Data_Connector::CONSUMER_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_key) ' .
         'REFERENCES ' . $prefix . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' (consumer_key)';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  $sql = 'ALTER TABLE ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
         'ADD CONSTRAINT ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
         LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (primary_consumer_key, primary_context_id) ' .
         'REFERENCES ' . $prefix . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' (consumer_key, context_id)';
  if (!$wpdb->query($sql)) error_log($wpdb->print_error());

  return TRUE;
}

/*-------------------------------------------------------------------
 * The function is run when the Synchronisation page header is
 * generated --- see lti.php and sync_admin_header
 *
 * Parameters
 *  $choice - whether to run the update with/without deletions
 ------------------------------------------------------------------*/
function lti_update($choice) {

  global $blog_id, $wpdb, $lti_db_connector;

  // Add users
  $add_users = unserialize($_SESSION[LTI_SESSION_PREFIX . 'provision']);
  foreach($add_users as $new_u) {

    $result = wp_insert_user(
                array(
                  'user_login' => $new_u->username,
                  'user_nicename'=> $new_u->username,
                  'first_name' => $new_u->firstname,
                  'last_name' => $new_u->lastname,
                  //'user_email'=> $new_u->email,
                  'user_url' => 'http://',
                  'display_name' => $new_u->fullname
                )
              );

    if (is_wp_error($result)) {
      $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" . $result->get_error_message() . "<br />";
      continue;
    }

    // Sort out role in blog
    $role = 'author';
    if ($new_u->staff === TRUE) $role = 'administrator';

    // Add newly created users to blog and set role
    add_user_to_blog($blog_id, $result, $role);
    if (is_wp_error($result)) {
      $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" .$result->get_error_message() . "<br />";
    }
  }

  // Existing users that require adding to blog
  $add_to_blog = unserialize($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog']);
  foreach($add_to_blog as $new_u) {
    $role = 'author';
    if ($new_u->staff === TRUE) $role = 'administrator';

    add_user_to_blog($blog_id, $new_u->id, $role);
    if (is_wp_error($result)) {
      $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" .$result->get_error_message() . "<br />";
    }
  }

  // Changed name
  $changed =  unserialize($_SESSION[LTI_SESSION_PREFIX . 'changed']);
  foreach($changed as $change) {
    wp_update_user(array
      ('ID' => $change->id,
       'first_name' => $change->firstname,
       'last_name' => $change->lastname,
       'display_name' => $change->fullname)) ;
  }
  // Changed role (most probably administrator -> author, author -> administrator)
  $changed_role =  unserialize($_SESSION[LTI_SESSION_PREFIX . 'role_changed']);
  foreach($changed_role as $changed) {
    $user = new WP_User($changed->id, '', $blog_id);
    $user->add_role($changed->role_changed);
    if ($changed->role_changed == 'administrator') {
     $user->remove_role('author');
     $user->remove_role('subscriber');
    }
    if ($changed->role_changed == 'author') {
      $user->remove_role('administrator');
     $user->remove_role('subscriber');
    }
    if ($changed->role_changed == 'subscriber') {
      $user->remove_role('administrator');
      $user->remove_role('author');
    }
  }

  // Remove users from blog but not WP as could be members of
  // other blogs. Could check and handle?
  if ($choice == 'delete') {
    $delete = unserialize($_SESSION[LTI_SESSION_PREFIX . 'remove']);
    foreach ($delete as $del) {
      $user = get_user_by('login', $del->username);
      remove_user_from_blog($user->ID, $blog_id);
      if (is_wp_error($result)) {
        $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" .$result->get_error_message() . "<br />";
      }
    }
  }

  // Get the consumer
  $consumer = new LTI_Tool_Consumer($_SESSION[LTI_SESSION_PREFIX . 'key'], $lti_db_connector);
  $resource = new LTI_Resource_Link($consumer, $_SESSION[LTI_SESSION_PREFIX . 'resourceid']);

  if ($resource->hasSettingService()) {
    $resource->doSettingService(LTI_Resource_Link::EXT_WRITE, date('d-M-Y H:i'));
  }
}


/*-------------------------------------------------------------------
 * Switch the enabled state for a share
 *
 * Parameter
 *  $key - key for consumer
 *  $id - the particular instance
 ------------------------------------------------------------------*/
function lti_set_share($key, $id, $action) {

  global $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($key, $lti_db_connector);
  $context = new LTI_Resource_Link($consumer, $id);

  $context->share_approved = $action;
  $context->save();
}

/*-------------------------------------------------------------------
 * Delete a share
 *
 * Parameter
 *  $key - key for consumer
 *  $id - the particular instance
 ------------------------------------------------------------------*/
function lti_delete_share($key, $id) {

  global $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($key, $lti_db_connector);
  $context = new LTI_Resource_Link($consumer, $id);

  $context->delete();

}

/*-------------------------------------------------------------------
 * Get the enabled state for a share
 *
 * Parameter
 *  $key - key for consumer
 ------------------------------------------------------------------*/
function lti_get_share_enabled_state($key) {

  global $lti_db_connector;

  $consumer = new LTI_Tool_Consumer($_SESSION[LTI_SESSION_PREFIX . 'key'], $lti_db_connector);
  $resource = new LTI_Resource_Link($consumer, $_SESSION[LTI_SESSION_PREFIX . 'resourceid']);
  $shares = $resource->getShares();
  foreach ($shares as $share) {
   if ($share->consumer_key == $key) {

     $reply = (is_null($share->approved)) ? FALSE : TRUE;
     return $reply ;
    }
  }
  return FALSE;
}

/*-------------------------------------------------------------------
  * Strip slashes from $_POST
  ------------------------------------------------------------------*/
function lti_strip_magic_quotes() {
  foreach ( $_POST as $k => $v ) {
    $_POST[$k] = stripslashes( $v );
  }
}

/*-------------------------------------------------------------------
  * Extract the username scope from the consumer key/GUID
  ------------------------------------------------------------------*/
function lti_get_scope($guid) {
    return substr($guid, 2, 1);
}

/*-------------------------------------------------------------------
  * Generate the consumer key GUID
  ------------------------------------------------------------------*/
function lti_get_guid() {
  $lti_scope = 3;

  if (isset($_GET['lti_scope'])) $lti_scope = $_GET['lti_scope'];

  $str = strtoupper(LTI_Data_Connector::getRandomString(6));
  return 'WP' . $lti_scope . '-' . $str;
}

/*-------------------------------------------------------------------
  * Clear the plugin session variables
  ------------------------------------------------------------------*/
function lti_reset_session() {
  foreach ( $_SESSION as $k => $v ) {
    $pos = strpos($k, LTI_SESSION_PREFIX);
    if (($pos !== FALSE) && ($pos == 0) && ($k != LTI_SESSION_PREFIX . 'return_url')) {
      $_SESSION[$k] = '';
      unset($_SESSION[$k]);
    }
  }
}

?>
