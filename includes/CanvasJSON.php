<?php
/*
 *  wordpress-lti - Enable WordPress to act as an LTI tool
 *  Copyright (C) Simon Booth, Stephen P Vickers
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

use ceLTIc\LTI\Service;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php');

$siteurl = get_bloginfo('url') . '/?lti-tool';
$iconurl = get_bloginfo('url') . '/?lti-tool&icon';
$jwksurl = get_bloginfo('url') . '/?lti-tool&keys';
$domain = get_bloginfo('url');
$pos = strpos($domain, '://');
if ($pos !== false) {
    $domain = substr($domain, $pos + 3);
}
$pos = strpos($domain, '/');
if ($pos !== false) {
    $domain = substr($domain, 0, $pos);
}

$configuration = (object) array(
        'title' => 'WordPress',
        'description' => 'Access to WordPress Blogs using LTI',
        'privacy_level' => 'public',
        'oidc_initiation_url' => $siteurl,
        'target_link_uri' => $siteurl,
        'scopes' => array(
            Service\Membership::$SCOPE
        ),
        'extensions' => array((object) array(
                'domain' => $domain,
                'tool_id' => 'wordpress',
                'platform' => 'canvas.instructure.com',
                'privacy_level' => 'public',
                'settings' => (object) array(
                    'platform' => 'canvas.instructure.com',
                    'text' => 'WordPress',
                    'icon_url' => $iconurl,
                    'placements' => array()
                )
            )),
        'public_jwk_url' => $jwksurl,
        'custom_fields' => (object) array(
            'username' => '\$User.username'
        )
);

$configuration = apply_filters('lti_tool_configure_json', $configuration);

header("Content-Type: application/json; ");

echo json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
