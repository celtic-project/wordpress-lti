<?php
/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2021  Simon Booth, Stephen P Vickers
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

require_once(ABSPATH . '/wp-admin/includes/template.php');

if (!current_user_can('edit_plugins')) {
    wp_die(
        '<h1>' . __('You need a higher level of permission.') . '</h1>' .
        '<p>' . __('Sorry, you are not allowed to edit plugins for this site.') . '</p>', 403
    );
}

$nonce = $_REQUEST['_wpnonce'];
if (!wp_verify_nonce($nonce, 'lti_options_settings_group-options')) {
    add_settings_error('general', 'settings_updated', __('Unable to submit this form, please refresh and try again.'));
} else {
    $options = wp_unslash($_POST['lti_options']);
    if (is_multisite()) {
        update_site_option('lti_choices', $options);
    } else {
        update_option('lti_choices', $options);
    }
    add_settings_error('general', 'settings_updated', __('Settings saved.'), 'success');
}
set_transient('settings_errors', get_settings_errors(), 30);

if (is_multisite()) {
    $path = add_query_arg(array('page' => 'lti_options', 'settings-updated' => 'true'), network_admin_url('admin.php'));
} else {
    $path = add_query_arg(array('page' => 'lti_options', 'settings-updated' => 'true'), admin_url('admin.php'));
}

wp_redirect($path);
?>
