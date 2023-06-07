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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tool = apply_filters('lti_tool_tool', null, $lti_tool_data_connector);
    if (empty($tool)) {
        $tool = new LTI_Tool_WPTool($lti_tool_data_connector);
    }
    $tool->doRegistration();
    $ok = $tool->ok;
    $message = $tool->reason;

    $response = array();
    $response['ok'] = $ok;
    $response['message'] = $message;

    header('Content-type: application/json');
    echo json_encode($response);
}
