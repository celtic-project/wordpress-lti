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

use ceLTIc\LTI\Platform;

global $lti_db_connector;

/**
 * Create a platform in WordPress
 */
// include the library
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php';

if (!empty($_POST) && check_admin_referer('add_lti', '_wpnonce_add_lti')) {

    $platform = Platform::fromConsumerKey($_POST['lti_key'], $lti_db_connector);
    $platform->name = $_POST['lti_name'];
    $platform->enabled = (isset($_POST['lti_enabled']) && ($_POST['lti_enabled'] == 'true')) ? true : false;
    $platform->secret = $_POST['lti_secret'];
    $platform->protected = (isset($_POST['lti_protected']) && ($_POST['lti_protected'] == 'true')) ? true : false;
    $platform->enableFrom = (!empty($_POST['lti_enable_from'])) ? strtotime($_POST['lti_enable_from']) : null;
    $platform->enableUntil = (!empty($_POST['lti_enable_until'])) ? strtotime($_POST['lti_enable_until']) : null;
    $platform->idScope = (!empty($_POST['lti_scope'])) ? $_POST['lti_scope'] : LTI_ID_SCOPE_DEFAULT;
    $platform->debugMode = (isset($_POST['lti_debug']) && ($_POST['lti_debug'] == 'true')) ? true : false;
    $platform->platformId = (!empty($_POST['lti_platformid'])) ? $_POST['lti_platformid'] : null;
    $platform->clientId = (!empty($_POST['lti_clientid'])) ? $_POST['lti_clientid'] : null;
    $platform->deploymentId = (!empty($_POST['lti_deploymentid'])) ? $_POST['lti_deploymentid'] : null;
    $platform->authorizationServerId = (!empty($_POST['lti_authorizationserverid'])) ? $_POST['lti_authorizationserverid'] : null;
    $platform->authenticationUrl = (!empty($_POST['lti_authenticationurl'])) ? $_POST['lti_authenticationurl'] : null;
    $platform->accessTokenUrl = (!empty($_POST['lti_accesstokenurl'])) ? $_POST['lti_accesstokenurl'] : null;
    $platform->jku = (!empty($_POST['lti_jku'])) ? $_POST['lti_jku'] : null;
    $platform->rsaKey = (!empty($_POST['lti_rsakey'])) ? $_POST['lti_rsakey'] : null;
    $platform->save();

    if (isset($_GET['edit'])) {
        if (is_multisite()) {
            wp_redirect(get_admin_url() . 'network/admin.php?page=lti_platforms');
        } else {
            wp_redirect(get_admin_url() . 'admin.php?page=lti_platforms');
        }
    }
}
?>