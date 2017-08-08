<?php
/**
 * LTI_Tool_Provider - PHP class to include in an external tool to handle connections with an LTI 1 compliant tool consumer
 * Copyright (C) 2015  Stephen P Vickers
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * Contact: stephen@spvsoftwareproducts.com
 *
 * Version history:
 *   2.0.00  30-Jun-12  Initial release
 *   2.1.00   3-Jul-12  Added fields to tool consumer: consumer_guid, protected, last_access
 *   2.2.00  16-Oct-12
 *   2.3.00   2-Jan-13  Updated Context to Resource_Link in method names
 *                      Settings values now saved as JSON
 *   2.3.01   2-Feb-13
 *   2.3.02  18-Feb-13
 *   2.3.03   5-Jun-13
 *   2.3.04  13-Aug-13
 *   2.3.05  29-Jul-14  Added support for date and time formats
 *   2.3.06   5-Aug-14
 *   2.4.00  10-Apr-15
 *   2.5.00  20-May-15  Updated Resource_Link_save to allow for changes in ID values
*/

###
###  Class to represent a LTI Data Connector for MySQL
###

###
#    NB This class assumes that a MySQL connection has already been opened to the appropriate schema
###

class LTI_Data_Connector_MySQL extends LTI_Data_Connector {

  private $dbTableNamePrefix = '';

###
#    Class constructor
###
  function __construct($dbTableNamePrefix = '') {

    $this->dbTableNamePrefix = $dbTableNamePrefix;

  }


###
###  LTI_Tool_Consumer methods
###

###
#    Load the tool consumer from the database
###
  public function Tool_Consumer_load($consumer) {

    $ok = FALSE;
    $sql = sprintf('SELECT name, secret, lti_version, consumer_name, consumer_version, consumer_guid, css_path, protected, enabled, enable_from, enable_until, last_access, created, updated ' .
                   "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' ' .
                   "WHERE consumer_key = %s",
       LTI_Data_Connector::quoted($consumer->getKey()));
    $rs_consumer = mysql_query($sql);
    if ($rs_consumer) {
      $row = mysql_fetch_object($rs_consumer);
      if ($row) {
        $consumer->name = $row->name;
        $consumer->secret = $row->secret;
        $consumer->lti_version = $row->lti_version;
        $consumer->consumer_name = $row->consumer_name;
        $consumer->consumer_version = $row->consumer_version;
        $consumer->consumer_guid = $row->consumer_guid;
        $consumer->css_path = $row->css_path;
        $consumer->protected = ($row->protected == 1);
        $consumer->enabled = ($row->enabled == 1);
        $consumer->enable_from = NULL;
        if (!is_null($row->enable_from)) {
          $consumer->enable_from = strtotime($row->enable_from);
        }
        $consumer->enable_until = NULL;
        if (!is_null($row->enable_until)) {
          $consumer->enable_until = strtotime($row->enable_until);
        }
        $consumer->last_access = NULL;
        if (!is_null($row->last_access)) {
          $consumer->last_access = strtotime($row->last_access);
        }
        $consumer->created = strtotime($row->created);
        $consumer->updated = strtotime($row->updated);
        $ok = TRUE;
      }
      mysql_free_result($rs_consumer);
    }

    return $ok;

  }

###
#    Save the tool consumer to the database
###
  public function Tool_Consumer_save($consumer) {

    if ($consumer->protected) {
      $protected = 1;
    } else {
      $protected = 0;
    }
    if ($consumer->enabled) {
      $enabled = 1;
    } else {
      $enabled = 0;
    }
    $time = time();
    $now = date("{$this->date_format} {$this->time_format}", $time);
    $from = NULL;
    if (!is_null($consumer->enable_from)) {
      $from = date("{$this->date_format} {$this->time_format}", $consumer->enable_from);
    }
    $until = NULL;
    if (!is_null($consumer->enable_until)) {
      $until = date("{$this->date_format} {$this->time_format}", $consumer->enable_until);
    }
    $last = NULL;
    if (!is_null($consumer->last_access)) {
      $last = date($this->date_format, $consumer->last_access);
    }
    if (is_null($consumer->created)) {
      $sql = sprintf("INSERT INTO {$this->dbTableNamePrefix}" . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' (consumer_key, name, ' .
             'secret, lti_version, consumer_name, consumer_version, consumer_guid, css_path, protected, enabled, enable_from, enable_until, last_access, created, updated) ' .
             "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, {$protected}, {$enabled}, %s, %s, %s, '{$now}', '{$now}')",
         LTI_Data_Connector::quoted($consumer->getKey()), LTI_Data_Connector::quoted($consumer->name),
         LTI_Data_Connector::quoted($consumer->secret), LTI_Data_Connector::quoted($consumer->lti_version),
         LTI_Data_Connector::quoted($consumer->consumer_name), LTI_Data_Connector::quoted($consumer->consumer_version), LTI_Data_Connector::quoted($consumer->consumer_guid),
         LTI_Data_Connector::quoted($consumer->css_path), LTI_Data_Connector::quoted($from), LTI_Data_Connector::quoted($until), LTI_Data_Connector::quoted($last));
    } else {
      $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' SET ' .
               'name = %s, secret= %s, lti_version = %s, consumer_name = %s, consumer_version = %s, consumer_guid = %s, ' .
               "css_path = %s, protected = {$protected}, enabled = {$enabled}, enable_from = %s, enable_until = %s, last_access = %s, updated = '{$now}' " .
             "WHERE consumer_key = %s",
         LTI_Data_Connector::quoted($consumer->name),
         LTI_Data_Connector::quoted($consumer->secret), LTI_Data_Connector::quoted($consumer->lti_version),
         LTI_Data_Connector::quoted($consumer->consumer_name), LTI_Data_Connector::quoted($consumer->consumer_version), LTI_Data_Connector::quoted($consumer->consumer_guid),
         LTI_Data_Connector::quoted($consumer->css_path), LTI_Data_Connector::quoted($from), LTI_Data_Connector::quoted($until), LTI_Data_Connector::quoted($last),
         LTI_Data_Connector::quoted($consumer->getKey()));
    }
    $ok = mysql_query($sql);
    if ($ok) {
      if (is_null($consumer->created)) {
        $consumer->created = $time;
      }
      $consumer->updated = $time;
    }

    return $ok;

  }

###
#    Delete the tool consumer from the database
###
  public function Tool_Consumer_delete($consumer) {

// Delete any nonce values for this consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::NONCE_TABLE_NAME . ' WHERE consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    mysql_query($sql);

// Delete any outstanding share keys for resource links for this consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' WHERE primary_consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    mysql_query($sql);

// Delete any users in resource links for this consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' WHERE consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    mysql_query($sql);

// Update any resource links for which this consumer is acting as a primary resource link
    $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
                   'SET primary_consumer_key = NULL, primary_context_id = NULL, share_approved = NULL ' .
                   'WHERE primary_consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    $ok = mysql_query($sql);

// Delete any resource links for this consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' WHERE consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    mysql_query($sql);

// Delete consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' WHERE consumer_key = %s',
       LTI_Data_Connector::quoted($consumer->getKey()));
    $ok = mysql_query($sql);

    if ($ok) {
      $consumer->initialise();
    }

    return $ok;

  }

###
#    Load all tool consumers from the database
###
  public function Tool_Consumer_list() {

    $consumers = array();

    $sql = 'SELECT consumer_key, name, secret, lti_version, consumer_name, consumer_version, consumer_guid, css_path, protected, enabled, enable_from, enable_until, last_access, created, updated ' .
           "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::CONSUMER_TABLE_NAME . ' ' .
           'ORDER BY name';
    $rs_consumers = mysql_query($sql);
    if ($rs_consumers) {
      while ($row = mysql_fetch_object($rs_consumers)) {
        $consumer = new LTI_Tool_Consumer($row->consumer_key, $this);
        $consumer->name = $row->name;
        $consumer->secret = $row->secret;
        $consumer->lti_version = $row->lti_version;
        $consumer->consumer_name = $row->consumer_name;
        $consumer->consumer_version = $row->consumer_version;
        $consumer->consumer_guid = $row->consumer_guid;
        $consumer->css_path = $row->css_path;
        $consumer->protected = ($row->protected == 1);
        $consumer->enabled = ($row->enabled == 1);
        $consumer->enable_from = NULL;
        if (!is_null($row->enable_from)) {
          $consumer->enable_from = strtotime($row->enable_from);
        }
        $consumer->enable_until = NULL;
        if (!is_null($row->enable_until)) {
          $consumer->enable_until = strtotime($row->enable_until);
        }
        $consumer->last_access = NULL;
        if (!is_null($row->last_access)) {
          $consumer->last_access = strtotime($row->last_access);
        }
        $consumer->created = strtotime($row->created);
        $consumer->updated = strtotime($row->updated);
        $consumers[] = $consumer;
      }
      mysql_free_result($rs_consumers);
    }

    return $consumers;

  }

###
###  LTI_Resource_Link methods
###

###
#    Load the resource link from the database
###
  public function Resource_Link_load($resource_link) {

    $ok = FALSE;
    $sql = sprintf('SELECT c.* ' .
                   "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' AS c ' .
                   'WHERE (consumer_key = %s) AND (context_id = %s)',
       LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
    $rs_context = mysql_query($sql);
    if ($rs_context) {
      $row = mysql_fetch_object($rs_context);
      if ($row) {
        $resource_link->lti_context_id = $row->lti_context_id;
        $resource_link->lti_resource_id = $row->lti_resource_id;
        $resource_link->title = $row->title;
        $resource_link->settings = unserialize($row->settings);
        if (!is_array($resource_link->settings)) {
          $resource_link->settings = array();
        }
        $resource_link->primary_consumer_key = $row->primary_consumer_key;
        $resource_link->primary_resource_link_id = $row->primary_context_id;
        $resource_link->share_approved = (is_null($row->share_approved)) ? NULL : ($row->share_approved == 1);
        $resource_link->created = strtotime($row->created);
        $resource_link->updated = strtotime($row->updated);
        $ok = TRUE;
      }
    }

    return $ok;

  }

###
#    Save the resource link to the database
###
  public function Resource_Link_save($resource_link) {

    if (is_null($resource_link->share_approved)) {
      $approved = 'NULL';
    } else if ($resource_link->share_approved) {
      $approved = 1;
    } else {
      $approved = 0;
    }
    $time = time();
    $now = date("{$this->date_format} {$this->time_format}", $time);
    $settingsValue = serialize($resource_link->settings);
    $key = $resource_link->getKey();
    $id = $resource_link->getId();
    $previous_id = $resource_link->getId(TRUE);
    if (is_null($resource_link->created)) {
      $sql = sprintf("INSERT INTO {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' (consumer_key, context_id, ' .
                     'lti_context_id, lti_resource_id, title, settings, primary_consumer_key, primary_context_id, share_approved, created, updated) ' .
                     "VALUES (%s, %s, %s, %s, %s, '{$settingsValue}', %s, %s, {$approved}, '{$now}', '{$now}')",
         LTI_Data_Connector::quoted($key), LTI_Data_Connector::quoted($id),
         LTI_Data_Connector::quoted($resource_link->lti_context_id), LTI_Data_Connector::quoted($resource_link->lti_resource_id),
         LTI_Data_Connector::quoted($resource_link->title),
         LTI_Data_Connector::quoted($resource_link->primary_consumer_key), LTI_Data_Connector::quoted($resource_link->primary_resource_link_id));
    } else if ($id == $previous_id) {
      $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' SET ' .
                     "lti_context_id = %s, lti_resource_id = %s, title = %s, settings = '{$settingsValue}', ".
                     "primary_consumer_key = %s, primary_context_id = %s, share_approved = {$approved}, updated = '{$now}' " .
                     'WHERE (consumer_key = %s) AND (context_id = %s)',
         LTI_Data_Connector::quoted($resource_link->lti_context_id), LTI_Data_Connector::quoted($resource_link->lti_resource_id),
         LTI_Data_Connector::quoted($resource_link->title),
         LTI_Data_Connector::quoted($resource_link->primary_consumer_key), LTI_Data_Connector::quoted($resource_link->primary_resource_link_id),
         LTI_Data_Connector::quoted($key), LTI_Data_Connector::quoted($id));
    } else {
      $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' SET ' .
                     "context_id = %s, lti_context_id = %s, lti_resource_id = %s, title = %s, settings = '{$settingsValue}', ".
                     "primary_consumer_key = %s, primary_context_id = %s, share_approved = {$approved}, updated = '{$now}' " .
                     'WHERE (consumer_key = %s) AND (context_id = %s)',
         LTI_Data_Connector::quoted($id),
         LTI_Data_Connector::quoted($resource_link->lti_context_id), LTI_Data_Connector::quoted($resource_link->lti_resource_id),
         LTI_Data_Connector::quoted($resource_link->title),
         LTI_Data_Connector::quoted($resource_link->primary_consumer_key), LTI_Data_Connector::quoted($resource_link->primary_resource_link_id),
         LTI_Data_Connector::quoted($key), LTI_Data_Connector::quoted($previous_id));
    }
    $ok = mysql_query($sql);
    if ($ok) {
      if (is_null($resource_link->created)) {
        $resource_link->created = $time;
      }
      $resource_link->updated = $time;
    }

    return $ok;

  }

###
#    Delete the resource link from the database
###
  public function Resource_Link_delete($resource_link) {

// Delete any outstanding share keys for resource links for this consumer
    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
                   'WHERE (primary_consumer_key = %s) AND (primary_context_id = %s)',
       LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
    $ok = mysql_query($sql);

// Delete users
    if ($ok) {
      $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' ' .
                     'WHERE (consumer_key = %s) AND (context_id = %s)',
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
      $ok = mysql_query($sql);
    }

// Update any resource links for which this is the primary resource link
    if ($ok) {
      $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
                     'SET primary_consumer_key = NULL, primary_context_id = NULL ' .
                     'WHERE (primary_consumer_key = %s) AND (primary_context_id = %s)',
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
      $ok = mysql_query($sql);
    }

// Delete resource link
    if ($ok) {
      $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
                     'WHERE (consumer_key = %s) AND (context_id = %s)',
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
      $ok = mysql_query($sql);
    }

    if ($ok) {
      $resource_link->initialise();
    }

    return $ok;

  }

###
#    Obtain an array of LTI_User objects for users with a result sourcedId.  The array may include users from other
#    resource links which are sharing this resource link.  It may also be optionally indexed by the user ID of a specified scope.
###
  public function Resource_Link_getUserResultSourcedIDs($resource_link, $local_only, $id_scope) {

    $users = array();

    if ($local_only) {
      $sql = sprintf('SELECT u.consumer_key, u.context_id, u.user_id, u.lti_result_sourcedid ' .
                     "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' AS u '  .
                     "INNER JOIN {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' AS c '  .
                     'ON u.consumer_key = c.consumer_key AND u.context_id = c.context_id ' .
                     "WHERE (c.consumer_key = %s) AND (c.context_id = %s) AND (c.primary_consumer_key IS NULL) AND (c.primary_context_id IS NULL)",
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
    } else {
      $sql = sprintf('SELECT u.consumer_key, u.context_id, u.user_id, u.lti_result_sourcedid ' .
                     "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' AS u '  .
                     "INNER JOIN {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' AS c '  .
                     'ON u.consumer_key = c.consumer_key AND u.context_id = c.context_id ' .
                     'WHERE ((c.consumer_key = %s) AND (c.context_id = %s) AND (c.primary_consumer_key IS NULL) AND (c.primary_context_id IS NULL)) OR ' .
                     '((c.primary_consumer_key = %s) AND (c.primary_context_id = %s) AND (share_approved = 1))',
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()),
         LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
    }
    $rs_user = mysql_query($sql);
    if ($rs_user) {
      while ($row = mysql_fetch_object($rs_user)) {
        $user = new LTI_User($resource_link, $row->user_id);
        $user->consumer_key = $row->consumer_key;
        $user->context_id = $row->context_id;
        $user->lti_result_sourcedid = $row->lti_result_sourcedid;
        if (is_null($id_scope)) {
          $users[] = $user;
        } else {
          $users[$user->getId($id_scope)] = $user;
        }
      }
    }

    return $users;

  }

###
#    Get an array of LTI_Resource_Link_Share objects for each resource link which is sharing this resource link.
###
  public function Resource_Link_getShares($resource_link) {

    $shares = array();

    $sql = sprintf('SELECT consumer_key, context_id, title, share_approved ' .
                   "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_TABLE_NAME . ' ' .
                   'WHERE (primary_consumer_key = %s) AND (primary_context_id = %s) ' .
                   'ORDER BY consumer_key',
       LTI_Data_Connector::quoted($resource_link->getKey()), LTI_Data_Connector::quoted($resource_link->getId()));
    $rs_share = mysql_query($sql);
    if ($rs_share) {
      while ($row = mysql_fetch_object($rs_share)) {
        $share = new LTI_Resource_Link_Share();
        $share->consumer_key = $row->consumer_key;
        $share->resource_link_id = $row->context_id;
        $share->title = $row->title;
        $share->approved = ($row->share_approved == 1);
        $shares[] = $share;
      }
    }

    return $shares;

  }


###
###  LTI_Consumer_Nonce methods
###

###
#    Load the consumer nonce from the database
###
  public function Consumer_Nonce_load($nonce) {

    $ok = TRUE;

#
### Delete any expired nonce values
#
    $now = date("{$this->date_format} {$this->time_format}", time());
    $sql = "DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::NONCE_TABLE_NAME . " WHERE expires <= '{$now}'";
    mysql_query($sql);

#
### load the nonce
#
    $sql = sprintf("SELECT value AS T FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::NONCE_TABLE_NAME . ' WHERE (consumer_key = %s) AND (value = %s)',
       LTI_Data_Connector::quoted($nonce->getKey()), LTI_Data_Connector::quoted($nonce->getValue()));
    $rs_nonce = mysql_query($sql);
    if ($rs_nonce) {
      $row = mysql_fetch_object($rs_nonce);
      if ($row === FALSE) {
        $ok = FALSE;
      }
    }

    return $ok;

  }

###
#    Save the consumer nonce in the database
###
  public function Consumer_Nonce_save($nonce) {

    $expires = date("{$this->date_format} {$this->time_format}", $nonce->expires);
    $sql = sprintf("INSERT INTO {$this->dbTableNamePrefix}" . LTI_Data_Connector::NONCE_TABLE_NAME . " (consumer_key, value, expires) VALUES (%s, %s, '{$expires}')",
       LTI_Data_Connector::quoted($nonce->getKey()), LTI_Data_Connector::quoted($nonce->getValue()));
    $ok = mysql_query($sql);

    return $ok;

  }


###
###  LTI_Resource_Link_Share_Key methods
###

###
#    Load the resource link share key from the database
###
  public function Resource_Link_Share_Key_load($share_key) {

    $ok = FALSE;

// Clear expired share keys
    $now = date("{$this->date_format} {$this->time_format}", time());
    $sql = "DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . " WHERE expires <= '{$now}'";
    mysql_query($sql);

// Load share key
    $id = mysql_real_escape_string($share_key->getId());
    $sql = 'SELECT * ' .
           "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
           "WHERE share_key_id = '{$id}'";
    $rs_share_key = mysql_query($sql);
    if ($rs_share_key) {
      $row = mysql_fetch_object($rs_share_key);
      if ($row) {
        $share_key->primary_consumer_key = $row->primary_consumer_key;
        $share_key->primary_resource_link_id = $row->primary_context_id;
        $share_key->auto_approve = ($row->auto_approve == 1);
        $share_key->expires = strtotime($row->expires);
        $ok = TRUE;
      }
    }

    return $ok;

  }

###
#    Save the resource link share key to the database
###
  public function Resource_Link_Share_Key_save($share_key) {

    if ($share_key->auto_approve) {
      $approve = 1;
    } else {
      $approve = 0;
    }
    $expires = date("{$this->date_format} {$this->time_format}", $share_key->expires);
    $sql = sprintf("INSERT INTO {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
                   '(share_key_id, primary_consumer_key, primary_context_id, auto_approve, expires) ' .
                   "VALUES (%s, %s, %s, {$approve}, '{$expires}')",
       LTI_Data_Connector::quoted($share_key->getId()), LTI_Data_Connector::quoted($share_key->primary_consumer_key),
       LTI_Data_Connector::quoted($share_key->primary_resource_link_id));

    return mysql_query($sql);

  }

###
#    Delete the resource link share key from the database
###
  public function Resource_Link_Share_Key_delete($share_key) {

    $sql = "DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . " WHERE share_key_id = '{$share_key->getId()}'";

    $ok = mysql_query($sql);

    if ($ok) {
      $share_key->initialise();
    }

    return $ok;

  }


###
###  LTI_User methods
###


###
#    Load the user from the database
###
  public function User_load($user) {

    $ok = FALSE;
    $sql = sprintf('SELECT u.* ' .
                   "FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' AS u ' .
                   'WHERE (consumer_key = %s) AND (context_id = %s) AND (user_id = %s)',
       LTI_Data_Connector::quoted($user->getResourceLink()->getKey()), LTI_Data_Connector::quoted($user->getResourceLink()->getId()),
       LTI_Data_Connector::quoted($user->getId(LTI_Tool_Provider::ID_SCOPE_ID_ONLY)));
    $rs_user = mysql_query($sql);
    if ($rs_user) {
      $row = mysql_fetch_object($rs_user);
      if ($row) {
        $user->lti_result_sourcedid = $row->lti_result_sourcedid;
        $user->created = strtotime($row->created);
        $user->updated = strtotime($row->updated);
        $ok = TRUE;
      }
    }

    return $ok;

  }

###
#    Save the user to the database
###
  public function User_save($user) {

    $time = time();
    $now = date("{$this->date_format} {$this->time_format}", $time);
    if (is_null($user->created)) {
      $sql = sprintf("INSERT INTO {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' (consumer_key, context_id, ' .
                     'user_id, lti_result_sourcedid, created, updated) ' .
                     "VALUES (%s, %s, %s, %s, '{$now}', '{$now}')",
         LTI_Data_Connector::quoted($user->getResourceLink()->getKey()), LTI_Data_Connector::quoted($user->getResourceLink()->getId()),
         LTI_Data_Connector::quoted($user->getId(LTI_Tool_Provider::ID_SCOPE_ID_ONLY)), LTI_Data_Connector::quoted($user->lti_result_sourcedid));
    } else {
      $sql = sprintf("UPDATE {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' ' .
                     "SET lti_result_sourcedid = %s, updated = '{$now}' " .
                     'WHERE (consumer_key = %s) AND (context_id = %s) AND (user_id = %s)',
         LTI_Data_Connector::quoted($user->lti_result_sourcedid),
         LTI_Data_Connector::quoted($user->getResourceLink()->getKey()), LTI_Data_Connector::quoted($user->getResourceLink()->getId()),
         LTI_Data_Connector::quoted($user->getId(LTI_Tool_Provider::ID_SCOPE_ID_ONLY)));
    }
    $ok = mysql_query($sql);
    if ($ok) {
      if (is_null($user->created)) {
        $user->created = $time;
      }
      $user->updated = $time;
    }

    return $ok;

  }

###
#    Delete the user from the database
###
  public function User_delete($user) {

    $sql = sprintf("DELETE FROM {$this->dbTableNamePrefix}" . LTI_Data_Connector::USER_TABLE_NAME . ' ' .
                   'WHERE (consumer_key = %s) AND (context_id = %s) AND (user_id = %s)',
       LTI_Data_Connector::quoted($user->getResourceLink()->getKey()), LTI_Data_Connector::quoted($user->getResourceLink()->getId()),
       LTI_Data_Connector::quoted($user->getId(LTI_Tool_Provider::ID_SCOPE_ID_ONLY)));
    $ok = mysql_query($sql);

    if ($ok) {
      $user->initialise();
    }

    return $ok;

  }

}

?>
