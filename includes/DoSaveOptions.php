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

require_once(ABSPATH . 'wp-admin/includes/template.php');

if (!current_user_can(lti_tool_get_admin_menu_page_capability())) {
    wp_die(
        '<h1>' . __('You need a higher level of permission.', 'lti-tool') . '</h1>' .
        '<p>' . __('Sorry, you are not allowed to access admin options for this site.', 'lti-tool') . '</p>', 403
    );
}

$nonce = sanitize_text_field($_REQUEST['_wpnonce']);
if (!wp_verify_nonce($nonce, 'lti_tool_options_settings_group-options')) {
    add_settings_error('general', 'settings_updated', __('Unable to submit this form, please refresh and try again.', 'lti-tool'));
} else {
    $rawoptions = stripslashes_deep($_POST['lti_tool_options']);
    $options = array();
    foreach ($rawoptions as $option => $value) {
        $option = sanitize_text_field($option);
        switch ($option) {
            case 'lti13_privatekey':
                $value = sanitize_textarea_field($value);
                break;
            default:
                $value = sanitize_text_field($value);
                break;
        }
        $options[$option] = $value;
    }
    $options = apply_filters('lti_tool_save_options', $options, lti_tool_get_options());
    if (is_multisite()) {
        update_site_option('lti_tool_options', $options);
    } else {
        update_option('lti_tool_options', $options);
    }
    add_settings_error('general', 'settings_updated', __('Settings saved.'), 'success');
}
set_transient('settings_errors', get_settings_errors(), 30);

if (is_multisite()) {
    $path = add_query_arg(array('page' => 'lti_tool_options', 'settings-updated' => 'true'), network_admin_url('admin.php'));
} else {
    $path = add_query_arg(array('page' => 'lti_tool_options', 'settings-updated' => 'true'), admin_url('admin.php'));
}

wp_redirect($path);
