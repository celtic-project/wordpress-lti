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

$siteurl = get_bloginfo('url') . '/?lti=';
$iconurl = get_bloginfo('url') . '/?lti&amp;icon';
$domain = get_bloginfo('url');
$pos = strpos($domain, '://');
if ($pos !== false) {
    $domain = substr($domain, $pos + 3);
}
$pos = strpos($domain, '/');
if ($pos !== false) {
    $domain = substr($domain, 0, $pos);
}

$xml = <<< EOD
<?xml version="1.0" encoding="UTF-8"?>
<cartridge_basiclti_link xmlns="http://www.imsglobal.org/xsd/imslticc_v1p0"
                         xmlns:blti = "http://www.imsglobal.org/xsd/imsbasiclti_v1p0"
                         xmlns:lticm ="http://www.imsglobal.org/xsd/imslticm_v1p0"
                         xmlns:lticp ="http://www.imsglobal.org/xsd/imslticp_v1p0"
                         xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance"
                         xsi:schemaLocation = "http://www.imsglobal.org/xsd/imslticc_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd
    http://www.imsglobal.org/xsd/imsbasiclti_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticm_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticp_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd">
  <blti:title>WordPress</blti:title>
  <blti:description>Access to WordPress Blogs using LTI</blti:description>
  <blti:icon>{$iconurl}</blti:icon>
  <blti:launch_url>{$siteurl}</blti:launch_url>
  <blti:extensions platform="canvas.instructure.com">
    <lticm:property name="tool_id">wordpress</lticm:property>
    <lticm:property name="privacy_level">public</lticm:property>
    <lticm:property name="domain">{$domain}</lticm:property>
    <lticm:property name="oauth_compliant">true</lticm:property>
  </blti:extensions>
  <blti:vendor>
    <lticp:code>spvsp</lticp:code>
    <lticp:name>SPV Software Products</lticp:name>
    <lticp:description>Provider of open source educational tools.</lticp:description>
    <lticp:url>http://www.spvsoftwareproducts.com/</lticp:url>
    <lticp:contact>
      <lticp:email>stephen@spvsoftwareproducts.com</lticp:email>
    </lticp:contact>
  </blti:vendor>
</cartridge_basiclti_link>
EOD;

header("Content-Type: application/xml; ");

echo $xml;
?>