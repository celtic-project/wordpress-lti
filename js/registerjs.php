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

header('Content-Type: application/javascript');

echo <<< EOD
function lti_tool_do_register() {
    jQuery('input[type="radio"]').attr('disabled', true);
    jQuery('#id_lti_tool_continue').addClass('lti_tool_hide');
    jQuery('#id_lti_tool_loading').removeClass('lti_tool_hide');
    jQuery.ajax({
        url: '?lti-tool&registration',
        dataType: 'json',
        data: {
            'openid_configuration': lti_tool_openid_configuration,
            'registration_token': lti_tool_registration_token,
            'lti_scope': jQuery('input[name="lti_tool_scope"]:checked').val()
        },
        type: 'POST',
        success: function (response) {
            jQuery('#id_lti_tool_loading').addClass('lti_tool_hide');
            if (response.ok) {
                jQuery('#id_lti_tool_registered').removeClass('lti_tool_hide');
                jQuery('#id_lti_tool_close').removeClass('lti_tool_hide');
            } else {
                jQuery('#id_lti_tool_notregistered').removeClass('lti_tool_hide');
                if (response.message) {
                    jQuery('#id_lti_tool_reason').text(response.message);
                }
            }
        },
        error: function (jxhr, msg, err) {
        jQuery('#id_lti_tool_loading').addClass('lti_tool_hide');
            jQuery('#id_lti_tool_reason').text(': Sorry an error occurred; please try again later.');
        }
    });
}

function doClose(el) {
    (window.opener || window.parent).postMessage({subject:'org.imsglobal.lti.close'}, '*');
    return true;
}

EOD;
