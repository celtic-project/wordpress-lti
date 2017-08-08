<?php
/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2015  Simon Booth, Stephen P Vickers
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
 *
 *  Version history:
 *    1.0.00  18-Apr-13  Initial release
 *    1.1.00  14-Jan-15  Updated for later releases of WordPress
 */

/**
 * Create a consumer in WordPress
 */

/** Load WordPress Administration Bootstrap */
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'admin.php');
// include the library
require_once('lib.php');

if (!empty($_POST) && check_admin_referer('add_lti', '_wpnonce_add_lti')) {

  $consumer = new LTI_Tool_Consumer($_POST['lti_key'], $lti_db_connector);
  $consumer->name         = $_POST['lti_name'];
  $consumer->enabled      = (isset($_POST['lti_enabled']) && ($_POST['lti_enabled'] == 'true')) ? TRUE : FALSE;
  $consumer->secret       = $_POST['lti_secret'];
  $consumer->protected    = (isset($_POST['lti_protected']) && ($_POST['lti_protected'] == 'true')) ? TRUE : FALSE;
  $consumer->enable_from  = (!empty($_POST['lti_enable_from'])) ? strtotime($_POST['lti_enable_from']) : NULL;
  $consumer->enable_until = (!empty($_POST['lti_enable_until'])) ? strtotime($_POST['lti_enable_until']) : NULL;
  $consumer->id_scope     = (!empty($_POST['lti_scope'])) ? $_POST['lti_scope'] : LTI_ID_SCOPE_DEFAULT;
  $consumer->save();

  if (isset($_GET['edit'])) wp_redirect(get_admin_url() . 'network/admin.php?page=lti_consumers');
}

?>