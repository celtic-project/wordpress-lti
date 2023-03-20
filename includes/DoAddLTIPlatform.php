<?php
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
use ceLTIc\LTI\Util;

global $lti_tool_data_connector;

/**
 * Create a platform in WordPress
 */
// include the library
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php');

if (!empty($_POST) && check_admin_referer('add_lti_tool', '_wpnonce_add_lti_tool')) {
    $_POST = stripslashes_deep($_POST);
    $options = lti_tool_get_options();
    if (empty($_POST['lti_tool_key'])) {
        $key = lti_tool_get_guid(sanitize_text_field($_POST['lti_tool_scope']));
        $secret = Util::getRandomString(32);
    } else {
        $key = sanitize_text_field($_POST['lti_tool_key']);
        if (empty($_POST['lti_tool_secret'])) {
            $secret = Util::getRandomString(32);
        } else {
            $secret = sanitize_text_field($_POST['lti_tool_secret']);
        }
    }
    $platform = Platform::fromConsumerKey($key, $lti_tool_data_connector);
    $platform->name = sanitize_text_field($_POST['lti_tool_name']);
    $platform->enabled = (isset($_POST['lti_tool_enabled']) && (sanitize_text_field($_POST['lti_tool_enabled']) === 'true')) ? true : false;
    $platform->secret = $secret;
    $platform->protected = (isset($_POST['lti_tool_protected']) && (sanitize_text_field($_POST['lti_tool_protected']) === 'true')) ? true : false;
    $platform->enableFrom = (!empty($_POST['lti_tool_enable_from'])) ? strtotime(sanitize_text_field($_POST['lti_tool_enable_from'])) : null;
    $platform->enableUntil = (!empty($_POST['lti_tool_enable_until'])) ? strtotime(sanitize_text_field($_POST['lti_tool_enable_until'])) : null;
    $platform->idScope = (!empty($_POST['lti_tool_scope'])) ? lti_tool_validate_scope($_POST['lti_tool_scope'], $options['scope']) : $options['scope'];
    $platform->debugMode = (isset($_POST['lti_tool_debug']) && (sanitize_text_field($_POST['lti_tool_debug']) === 'true')) ? true : false;
    $platform->platformId = (!empty($_POST['lti_tool_platformid'])) ? sanitize_text_field($_POST['lti_tool_platformid']) : null;
    $platform->clientId = (!empty($_POST['lti_tool_clientid'])) ? sanitize_text_field($_POST['lti_tool_clientid']) : null;
    $platform->deploymentId = (!empty($_POST['lti_tool_deploymentid'])) ? sanitize_text_field($_POST['lti_tool_deploymentid']) : null;
    $platform->authorizationServerId = (!empty($_POST['lti_tool_authorizationserverid'])) ? sanitize_text_field($_POST['lti_tool_authorizationserverid']) : null;
    $platform->authenticationUrl = (!empty($_POST['lti_tool_authenticationurl'])) ? esc_url_raw($_POST['lti_tool_authenticationurl']) : null;
    $platform->accessTokenUrl = (!empty($_POST['lti_tool_accesstokenurl'])) ? esc_url_raw($_POST['lti_tool_accesstokenurl']) : null;
    $platform->jku = (!empty($_POST['lti_tool_jku'])) ? esc_url_raw($_POST['lti_tool_jku']) : null;
    $platform->rsaKey = (!empty($_POST['lti_tool_rsakey'])) ? sanitize_textarea_field($_POST['lti_tool_rsakey']) : null;
    $platform->setSetting('__role_staff',
        isset($_POST['lti_tool_role_staff']) ? sanitize_text_field($_POST['lti_tool_role_staff']) : null);
    $platform->setSetting('__role_student',
        isset($_POST['lti_tool_role_student']) ? sanitize_text_field($_POST['lti_tool_role_student']) : null);
    $platform->setSetting('__role_other',
        isset($_POST['lti_tool_role_other']) ? sanitize_text_field($_POST['lti_tool_role_other']) : null);
    $platform = apply_filters('lti_tool_save_platform', $platform, $options, $_POST);
    $platform->save();

    if (isset($_GET['edit'])) {
        $page = 'lti_tool_platforms';
    } else {
        $page = "lti_tool_add_platform&new&lti={$key}";
    }
    if (is_multisite()) {
        wp_redirect(get_admin_url() . "network/admin.php?page={$page}");
    } else {
        wp_redirect(get_admin_url() . "admin.php?page={$page}");
    }
}
