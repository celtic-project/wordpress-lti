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

header('Content-Type: application/javascript');

echo <<< EOD
function doRegister() {
    jQuery('input[type="radio"]').attr('disabled', true);
    jQuery('#id_continue').addClass('hide');
    jQuery('#id_loading').removeClass('hide');
    jQuery.ajax({
        url: '?lti&registration',
        dataType: 'json',
        data: {
            'openid_configuration': openid_configuration,
            'registration_token': registration_token,
            'lti_scope': jQuery('input[name="lti_scope"]:checked').val()
        },
        type: 'POST',
        success: function (response) {
            jQuery('#id_loading').addClass('hide');
            if (response.ok) {
                jQuery('#id_registered').removeClass('hide');
                jQuery('#id_close').removeClass('hide');
            } else {
                jQuery('#id_notregistered').removeClass('hide');
                if (response.message) {
                    jQuery('#id_reason').text(response.message);
                }
            }
        },
        error: function (jxhr, msg, err) {
        jQuery('#id_loading').addClass('hide');
            jQuery('#id_reason').text(': Sorry an error occurred; please try again later.');
        }
    });
}

function onRadioChange() {
    jQuery('#id_continuebutton').attr('disabled', false);
    jQuery('#id_continuebutton').removeClass('disabled');
}

function doClose(el) {
    (window.opener || window.parent).postMessage({subject:'org.imsglobal.lti.close'}, '*');
    return true;
}

jQuery(document).ready(function () {
    jQuery('input[type="radio"]').on('change', onRadioChange);
});

EOD;
?>
