<?php
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

use ceLTIc\LTI\Tool;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\DataConnector\DataConnector;
use ceLTIc\LTI\Util;

global $wpdb;

// include the LTI library classes
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config.php');

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WPTool.php');

define('LTI_SESSION_PREFIX', 'lti_');

// Set logging level
if (defined('LTI_LOG_LEVEL')) {
    Util::$logLevel = LTI_LOG_LEVEL;
}

// Set the default tool
Tool::$defaultTool = new WPTool(null);

$lti_db_connector = DataConnector::getDataConnector($wpdb->dbh, $wpdb->base_prefix);

/* -------------------------------------------------------------------
 * LTI_WP_User - a local smaller definition of the LTI_User that
 * simply captures what needed in WP to deal with the various states
 * that can occur when synchronising
 * ----------------------------------------------------------------- */

class LTI_WP_User
{

    public $id;
    public $username;
    public $firstname;
    public $lastname;
    public $fullname;
    //public $email;
    public $roles;
    public $staff = false;
    public $learner = false;
    public $new_to_blog;
    public $provision;
    public $newadmin;
    public $changed;
    public $role_changed;
    public $delete;

    function __construct()
    {
        if (func_num_args() == 1) {
            $args = func_get_args();
            $lti_user_from_platform = $args[0];

            // Sharing --- this is Stephen's preference.
            $scope_userid = lti_get_scope($_SESSION[LTI_SESSION_PREFIX . 'userkey']);
            $user_login = $lti_user_from_platform->getID($scope_userid);

            // Sanitize username stripping out unsafe characters
            $user_login = sanitize_user($user_login);

            // Apply the function pre_user_login before saving to the DB.
            $user_login = apply_filters('pre_user_login', $user_login);

            $this->username = $user_login;
            $this->firstname = $lti_user_from_platform->firstname;
            $this->lastname = $lti_user_from_platform->lastname;
            $this->fullname = $lti_user_from_platform->fullname;
            //$this->email = $lti_user_from_platform->email;
            $role_name = '';
            if (!empty($lti_user_from_platform->roles)) {
                foreach ($lti_user_from_platform->roles as $role) {
                    $role_name .= $role . ',';
                }
                $this->roles = substr($role_name, 0, -1);
            }

            // Need to ensure that user who is both staff/student is treated as student
            $this->staff = $lti_user_from_platform->isStaff() || $lti_user_from_platform->isAdmin();
            if ($lti_user_from_platform->isLearner()) {
                $this->staff = false;
                $this->learner = true;
            }

            $this->provision = false;
            $this->new_to_blog = false;
            $this->changed = false;
            $this->role_changed = '';
        }

        if (func_num_args() == 0) {
            $this->username = '';
            $this->delete = true;
        }
    }

}

/* -------------------------------------------------------------------
 * Delete a platform and any blogs associated with it
 *
 * Parameter
 *  $key - key for platform
  ------------------------------------------------------------------ */

function lti_delete($key)
{
    global $wpdb, $lti_db_connector;

    $platform = Platform::fromConsumerKey($key, $lti_db_connector);
    $platform->delete();

    // Now delete the blogs associated with this key. The WP function that lists all
    // blog is depreciated and so we'll do a direct DB access (look the other way)
    $search_str = '/' . str_replace('.', '', $key) . '%';
    $sites = $wpdb->get_col($wpdb->prepare(
            "SELECT blog_id FROM {$wpdb->prefix}blogs WHERE path LIKE %s", $search_str));

    // Delete the blog
    foreach ($sites as $site) {
        wpmu_delete_blog($site, true);
    }
}

/* -------------------------------------------------------------------
 * Switch the enabled state for platform
 *
 * Parameter
 *  $key - key for platform
  ------------------------------------------------------------------ */

function lti_set_enable($key, $enable)
{
    global $lti_db_connector;

    $platform = Platform::fromConsumerKey($key, $lti_db_connector);
    $platform->enabled = $enable;
    $platform->save();
}

/* -------------------------------------------------------------------
 * Get whether a particular platform is enabled.
 *
 * Parameter
 *  $key - key for platform
  ------------------------------------------------------------------ */

function lti_get_enabled_state($key)
{
    global $lti_db_connector;

    $platform = Platform::fromConsumerKey($key, $lti_db_connector);

    return $platform->enabled;
}

/* -------------------------------------------------------------------
 * Check that the tables necessary for classes are present, if not
 * create.
  ------------------------------------------------------------------ */

function lti_create_db()
{
    global $wpdb;

    $prefix = $wpdb->prefix;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' (' .
        'consumer_pk int(11) NOT NULL AUTO_INCREMENT, ' .
        'name varchar(50) NOT NULL, ' .
        'consumer_key varchar(256) DEFAULT NULL, ' .
        'secret varchar(1024) DEFAULT NULL, ' .
        'platform_id varchar(255) DEFAULT NULL, ' .
        'client_id varchar(255) DEFAULT NULL, ' .
        'deployment_id varchar(255) DEFAULT NULL, ' .
        'public_key text DEFAULT NULL, ' .
        'lti_version varchar(10) DEFAULT NULL, ' .
        'signature_method varchar(15) DEFAULT NULL, ' .
        'consumer_name varchar(255) DEFAULT NULL, ' .
        'consumer_version varchar(255) DEFAULT NULL, ' .
        'consumer_guid varchar(1024) DEFAULT NULL, ' .
        'profile text DEFAULT NULL, ' .
        'tool_proxy text DEFAULT NULL, ' .
        'settings text DEFAULT NULL, ' .
        'protected tinyint(1) NOT NULL, ' .
        'enabled tinyint(1) NOT NULL, ' .
        'enable_from datetime DEFAULT NULL, ' .
        'enable_until datetime DEFAULT NULL, ' .
        'last_access date DEFAULT NULL, ' .
        'created datetime NOT NULL, ' .
        'updated datetime NOT NULL, ' .
        'PRIMARY KEY (consumer_pk)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' ' .
        "ADD UNIQUE INDEX {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . '_' .
        'consumer_key_UNIQUE (consumer_key ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' ' .
        "ADD UNIQUE INDEX {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . '_' .
        'platform_UNIQUE (platform_id ASC, client_id ASC, deployment_id ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::NONCE_TABLE_NAME . ' (' .
        'consumer_pk int(11) NOT NULL, ' .
        'value varchar(50) NOT NULL, ' .
        'expires datetime NOT NULL, ' .
        'PRIMARY KEY (consumer_pk, value)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::NONCE_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::NONCE_TABLE_NAME . '_' .
        DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::ACCESS_TOKEN_TABLE_NAME . ' (' .
        'consumer_pk int(11) NOT NULL, ' .
        'scopes text NOT NULL, ' .
        'token varchar(2000) NOT NULL, ' .
        'expires datetime NOT NULL, ' .
        'created datetime NOT NULL, ' .
        'updated datetime NOT NULL, ' .
        'PRIMARY KEY (consumer_pk)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::ACCESS_TOKEN_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::ACCESS_TOKEN_TABLE_NAME . '_' .
        DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . ' (' .
        'context_pk int(11) NOT NULL AUTO_INCREMENT, ' .
        'consumer_pk int(11) NOT NULL, ' .
        'lti_context_id varchar(255) NOT NULL, ' .
        'title varchar(255) DEFAULT NULL, ' .
        'type varchar(50) DEFAULT NULL, ' .
        'settings text DEFAULT NULL, ' .
        'created datetime NOT NULL, ' .
        'updated datetime NOT NULL, ' .
        'PRIMARY KEY (context_pk)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . '_' .
        DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . ' ' .
        "ADD INDEX {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . '_' .
        'consumer_id_IDX (consumer_pk ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' (' .
        'resource_link_pk int(11) AUTO_INCREMENT, ' .
        'context_pk int(11) DEFAULT NULL, ' .
        'consumer_pk int(11) DEFAULT NULL, ' .
        'title varchar(255) DEFAULT NULL, ' .
        'lti_resource_link_id varchar(255) NOT NULL, ' .
        'settings text, ' .
        'primary_resource_link_pk int(11) DEFAULT NULL, ' .
        'share_approved tinyint(1) DEFAULT NULL, ' .
        'created datetime NOT NULL, ' .
        'updated datetime NOT NULL, ' .
        'PRIMARY KEY (resource_link_pk)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
        DataConnector::PLATFORM_TABLE_NAME . '_FK1 FOREIGN KEY (consumer_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::PLATFORM_TABLE_NAME . ' (consumer_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
        DataConnector::CONTEXT_TABLE_NAME . '_FK1 FOREIGN KEY (context_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::CONTEXT_TABLE_NAME . ' (context_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
        DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (primary_resource_link_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
        "ADD INDEX {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
        'consumer_pk_IDX (consumer_pk ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' ' .
        "ADD INDEX {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . '_' .
        'context_pk_IDX (context_pk ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::USER_RESULT_TABLE_NAME . ' (' .
        'user_result_pk int(11) AUTO_INCREMENT, ' .
        'resource_link_pk int(11) NOT NULL, ' .
        'lti_user_id varchar(255) NOT NULL, ' .
        'lti_result_sourcedid varchar(1024) NOT NULL, ' .
        'created datetime NOT NULL, ' .
        'updated datetime NOT NULL, ' .
        'PRIMARY KEY (user_result_pk)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::USER_RESULT_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::USER_RESULT_TABLE_NAME . '_' .
        DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::USER_RESULT_TABLE_NAME . ' ' .
        "ADD INDEX {$prefix}" . DataConnector::USER_RESULT_TABLE_NAME . '_' .
        'resource_link_pk_IDX (resource_link_pk ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' (' .
        'share_key_id varchar(32) NOT NULL, ' .
        'resource_link_pk int(11) NOT NULL, ' .
        'auto_approve tinyint(1) NOT NULL, ' .
        'expires datetime NOT NULL, ' .
        'PRIMARY KEY (share_key_id)' .
        ') ENGINE=InnoDB ' . $charset_collate;
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
        "ADD CONSTRAINT {$prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
        DataConnector::RESOURCE_LINK_TABLE_NAME . '_FK1 FOREIGN KEY (resource_link_pk) ' .
        "REFERENCES {$prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME . ' (resource_link_pk)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }
    $sql = "ALTER TABLE {$prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
        "ADD INDEX {$prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . '_' .
        'resource_link_pk_IDX (resource_link_pk ASC)';
    if (!$wpdb->query($sql)) {
        error_log($wpdb->print_error());
    }

    return true;
}

/* -------------------------------------------------------------------
 * The function is run when the Synchronisation page header is
 * generated --- see lti.php and sync_admin_header
 *
 * Parameters
 *  $choice - whether to run the update with/without deletions
  ------------------------------------------------------------------ */

function lti_update($choice)
{
    global $blog_id, $lti_db_connector;

    // Add users
    $add_users = unserialize($_SESSION[LTI_SESSION_PREFIX . 'provision']);
    foreach ($add_users as $new_u) {

        $result = wp_insert_user(
            array(
                'user_login' => $new_u->username,
                'user_nicename' => $new_u->username,
                'user_pass' => wp_generate_password(),
                'first_name' => $new_u->firstname,
                'last_name' => $new_u->lastname,
                //'user_email'=> $new_u->email,
                //'user_url' => 'http://',
                'display_name' => $new_u->fullname
            )
        );

        if (is_wp_error($result)) {
            // Ensure the element exists before attempting to append
            if (!in_array(LTI_SESSION_PREFIX . 'error', $_SESSION)) $_SESSION[LTI_SESSION_PREFIX . 'error'] = "";
            $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" . $result->get_error_message() . "<br />";
            continue;
        }

        // Sort out role in blog
        $role = 'author';
        if ($new_u->staff === true) {
            $role = 'administrator';
        }

        // Add newly created users to blog and set role
        add_user_to_blog($blog_id, $result, $role);
        if (is_wp_error($result)) {
            // Ensure the element exists before attempting to append
            if (!in_array(LTI_SESSION_PREFIX . 'error', $_SESSION)) $_SESSION[LTI_SESSION_PREFIX . 'error'] = "";
            $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" . $result->get_error_message() . "<br />";
        }
    }

    // Existing users that require adding to blog
    $add_to_blog = unserialize($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog']);
    foreach ($add_to_blog as $new_u) {
        $role = 'author';
        if ($new_u->staff === true) {
            $role = 'administrator';
        }
        add_user_to_blog($blog_id, $new_u->id, $role);
        if (is_wp_error($result)) {
            // Ensure the element exists before attempting to append
            if (!in_array(LTI_SESSION_PREFIX . 'error', $_SESSION)) $_SESSION[LTI_SESSION_PREFIX . 'error'] = "";
            $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" . $result->get_error_message() . "<br />";
        }
    }

    // Changed name
    $changed = unserialize($_SESSION[LTI_SESSION_PREFIX . 'changed']);
    foreach ($changed as $change) {
        wp_update_user(array
            ('ID' => $change->id,
            'first_name' => $change->firstname,
            'last_name' => $change->lastname,
            'display_name' => $change->fullname));
    }
    // Changed role (most probably administrator -> author, author -> administrator)
    $changed_role = unserialize($_SESSION[LTI_SESSION_PREFIX . 'role_changed']);
    foreach ($changed_role as $changed) {
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
                // Ensure the element exists before attempting to append
                if (!in_array(LTI_SESSION_PREFIX . 'error', $_SESSION)) $_SESSION[LTI_SESSION_PREFIX . 'error'] = "";
                $_SESSION[LTI_SESSION_PREFIX . 'error'] .= $new_u->username . ":" . $result->get_error_message() . "<br />";
            }
        }
    }

    // Get the platform
    $platform = Platform::fromConsumerKey($_SESSION[LTI_SESSION_PREFIX . 'key'], $lti_db_connector);
    $resource = ResourceLink::fromPlatform($platform, $_SESSION[LTI_SESSION_PREFIX . 'resourceid']);

    if ($resource->hasSettingService()) {
        $resource->doSettingService(ResourceLink::EXT_WRITE, date('d-M-Y H:i'));
    }
}

/* -------------------------------------------------------------------
 * Switch the enabled state for a share
 *
 * Parameter
 *  $id - the particular instance
  ------------------------------------------------------------------ */

function lti_set_share($id, $action)
{
    global $lti_db_connector;

    $context = ResourceLink::fromRecordId($id, $lti_db_connector);

    $context->shareApproved = $action;
    $context->save();
}

/* -------------------------------------------------------------------
 * Delete a share
 *
 * Parameter
 *  $id - the particular instance
  ------------------------------------------------------------------ */

function lti_delete_share($id)
{
    global $lti_db_connector;

    $context = ResourceLink::fromRecordId($id, $lti_db_connector);

    $context->delete();
}

/* -------------------------------------------------------------------
 * Strip slashes from $_POST
  ------------------------------------------------------------------ */

function lti_strip_magic_quotes()
{
    foreach ($_POST as $k => $v) {
        $_POST[$k] = stripslashes($v);
    }
}

/* -------------------------------------------------------------------
 * Extract the username scope from the consumer key/GUID
  ------------------------------------------------------------------ */

function lti_get_scope($guid)
{
    return substr($guid, 2, 1);
}

/* -------------------------------------------------------------------
 * Generate the consumer key GUID
  ------------------------------------------------------------------ */

function lti_get_guid()
{
    $lti_scope = 3;

    if (isset($_GET['lti_scope'])) {
        $lti_scope = $_GET['lti_scope'];
    }

    $str = strtoupper(Util::getRandomString(6));
    return 'WP' . $lti_scope . '-' . $str;
}

/* -------------------------------------------------------------------
 * Clear the plugin session variables
  ------------------------------------------------------------------ */

function lti_reset_session()
{
    foreach ($_SESSION as $k => $v) {
        $pos = strpos($k, LTI_SESSION_PREFIX);
        if (($pos !== false) && ($pos == 0) && ($k != LTI_SESSION_PREFIX . 'return_url')) {
            $_SESSION[$k] = '';
            unset($_SESSION[$k]);
        }
    }
}

?>
