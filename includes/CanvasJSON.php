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
 *  Contact: s.p.booth@stir.ac.uk
 */

require_once 'lib.php';

$siteurl = get_bloginfo('url') . '/?lti';
$iconurl = get_bloginfo('url') . '/?lti&icon';
$jwksurl = get_bloginfo('url') . '/?lti&keys';
$domain = get_bloginfo('url');
$pos = strpos($domain, '://');
if ($pos !== false) {
    $domain = substr($domain, $pos + 3);
}
$pos = strpos($domain, '/');
if ($pos !== false) {
    $domain = substr($domain, 0, $pos);
}

$json = <<< EOD
{
  "title": "WordPress",
  "description": "Access to WordPress Blogs using LTI",
  "privacy_level": "public",
  "oidc_initiation_url": "{$siteurl}",
  "target_link_uri": "{$siteurl}",
  "scopes": [
    "https://purl.imsglobal.org/spec/lti-ags/scope/score",
    "https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly"
  ],
  "extensions": [
    {
      "domain": "{$domain}",
      "tool_id": "wordpress",
      "platform": "canvas.instructure.com",
      "privacy_level": "public",
      "settings": {
        "text": "WordPress",
        "icon_url": "{$iconurl}",
        "placements": [
        ]
      }
    }
  ],
  "public_jwk_url": "{$jwksurl}",
  "custom_fields": {
    "canvas_user_login_id": "\$User.username"
  }
}
EOD;

header("Content-Type: application/json; ");

echo $json;
?>