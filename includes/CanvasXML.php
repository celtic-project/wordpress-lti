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

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php');

global $lti_tool_namespaces, $lti_tool_schemas;

$lti_tool_namespaces = array(
    'xmlns' => 'http://www.imsglobal.org/xsd/imslticc_v1p0',
    'blti' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0',
    'lticm' => 'http://www.imsglobal.org/xsd/imslticm_v1p0',
    'lticp' => 'http://www.imsglobal.org/xsd/imslticp_v1p0',
    'xsi' => 'http://www.w3.org/2001/XMLSchema-instance'
);
$lti_tool_schemas = array(
    'http://www.imsglobal.org/xsd/imslticc_v1p0' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd',
    'http://www.imsglobal.org/xsd/imsbasiclti_v1p0' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd',
    'http://www.imsglobal.org/xsd/imslticm_v1p0' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd',
    'http://www.imsglobal.org/xsd/imslticp_v1p0' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd'
);

function lti_tool_create_xml_root($dom, $name)
{
    global $lti_tool_namespaces, $lti_tool_schemas;

    $root = $dom->createElementNS($lti_tool_namespaces['xmlns'], $name);
    foreach ($lti_tool_namespaces as $name => $uri) {
        if ($name === 'xmlns') {
            continue;
        }
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:{$name}", $uri);
    }
    $locations = '';
    foreach ($lti_tool_schemas as $uri => $xsd) {
        $locations .= "{$uri} {$xsd} ";
    }
    $attr = $dom->createAttribute('xsi:schemaLocation');
    $attr->value = trim($locations);
    $root->appendChild($attr);

    return $root;
}

function lti_tool_add_xml_element($dom, $parent, $namespace, $name, $value = '', $attributes = array())
{
    global $lti_tool_namespaces, $lti_tool_schemas;

    if (!empty($namespace)) {
        $element = $dom->createElementNS($lti_tool_namespaces[$namespace], "{$namespace}:{$name}", $value);
    } else {
        $element = $dom->createElement($name, $value);
    }
    if (!empty($attributes)) {
        foreach ($attributes as $name => $value) {
            $attribute = $dom->createAttribute($name);
            $attribute->value = $value;
            $element->appendChild($attribute);
        }
    }
    $parent->appendChild($element);

    return $element;
}

$siteurl = get_bloginfo('url') . '/?lti-tool=';
$iconurl = get_bloginfo('url') . '/?lti-tool&amp;icon';
$domain = get_bloginfo('url');
$pos = strpos($domain, '://');
if ($pos !== false) {
    $domain = substr($domain, $pos + 3);
}
$pos = strpos($domain, '/');
if ($pos !== false) {
    $domain = substr($domain, 0, $pos);
}

$dom = new DOMDocument('1.0', 'UTF-8');

$root = lti_tool_create_xml_root($dom, 'cartridge_basiclti_link');

lti_tool_add_xml_element($dom, $root, 'blti', 'title', 'WordPress');
lti_tool_add_xml_element($dom, $root, 'blti', 'description', 'Access to WordPress Blogs using LTI');
lti_tool_add_xml_element($dom, $root, 'blti', 'icon', $iconurl);
lti_tool_add_xml_element($dom, $root, 'blti', 'launch_url', $siteurl);

$custom = lti_tool_add_xml_element($dom, $root, 'blti', 'custom');
lti_tool_add_xml_element($dom, $custom, 'lticm', 'property', '$User.username', array('name' => 'username'));

$extensions = lti_tool_add_xml_element($dom, $root, 'blti', 'extensions', '', array('platform' => 'canvas.instructure.com'));
lti_tool_add_xml_element($dom, $extensions, 'lticm', 'property', 'wordpress', array('name' => 'tool_id'));
lti_tool_add_xml_element($dom, $extensions, 'lticm', 'property', 'public', array('name' => 'privacy_level'));
lti_tool_add_xml_element($dom, $extensions, 'lticm', 'property', $domain, array('name' => 'domain'));
lti_tool_add_xml_element($dom, $extensions, 'lticm', 'property', 'true', array('name' => 'oauth_compliant'));

$vendor = lti_tool_add_xml_element($dom, $root, 'blti', 'vendor');
lti_tool_add_xml_element($dom, $vendor, 'lticp', 'code', 'spvsp');
lti_tool_add_xml_element($dom, $vendor, 'lticp', 'name', 'SPV Software Products');
lti_tool_add_xml_element($dom, $vendor, 'lticp', 'description', 'Provider of open source educational tools.');
lti_tool_add_xml_element($dom, $vendor, 'lticp', 'url', 'http://www.spvsoftwareproducts.com/');
$contact = lti_tool_add_xml_element($dom, $vendor, 'lticp', 'contact');
lti_tool_add_xml_element($dom, $contact, 'lticp', 'email', 'stephen@spvsoftwareproducts.com');

$dom->appendChild($root);

$dom = apply_filters('lti_tool_configure_xml', $dom);

$dom->formatOutput = true;
$dom->normalizeDocument();

header("Content-Type: application/xml; ");

echo $dom->saveXML();
