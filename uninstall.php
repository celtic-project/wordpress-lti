<?php
/*
 *  wordpress-lti - Enable WordPress to act as an LTI tool
 *  Copyright (C) 2023  Simon Booth, Stephen P Vickers
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

/* -------------------------------------------------------------------
 * Fired when the plugin is uninstalled.
  ------------------------------------------------------------------ */

use ceLTIc\LTI\Tool;
use ceLTIc\LTI\DataConnector\DataConnector;

// if uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// include the Library functions & lib.php loads LTI library
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'lib.php');

// check if data should be deleted on uninstall
$options = lti_tool_get_options();

$lti_tool_data_connector = DataConnector::getDataConnector($wpdb->dbh, $wpdb->base_prefix);
$tool = new Tool($lti_tool_data_connector);
$platforms = $tool->getPlatforms();
foreach ($platforms as $platform) {
    lti_tool_delete($platform->getKey());
}

if (!empty($options['uninstalldb'])) {
    // delete plugin options.
    if (is_multisite()) {
        delete_site_option('lti_tool_options');
    } else {
        delete_option('lti_tool_options');
    }

    // delete LTI tables.
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::RESOURCE_LINK_SHARE_KEY_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::USER_RESULT_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::RESOURCE_LINK_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::CONTEXT_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::ACCESS_TOKEN_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::NONCE_TABLE_NAME);
    $wpdb->query("DROP TABLE {$wpdb->prefix}" . DataConnector::PLATFORM_TABLE_NAME);
}
