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

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\ResourceLinkShareKey;

function lti_create_share_key()
{
    global $lti_db_connector, $lti_session;

    // Get the context
    $platform = Platform::fromConsumerKey($lti_session['key'], $lti_db_connector);
    $resource = ResourceLink::fromPlatform($platform, $lti_session['resourceid']);

    if (!empty($_POST['email'])) {
        $share_key = new ResourceLinkShareKey($resource);
        if (isset($_POST['life'])) {
            $share_key->life = $_POST['life'];
        }
        if (isset($_POST['enabled'])) {
            $_POST['enabled'] ? $share_key->autoApprove = true : $share_key->autoApprove = false;
        }
        $share_key->save();

        $current_user = wp_get_current_user();
        $senttext = __('Instructor: ', 'lti-text') . '<b>' . $current_user->display_name . '</b>' . __(' has shared', 'lti-text') . '<br /><br />' .
            __('Blog Name: ', 'lti-text') . '<b>' . get_bloginfo('name') . '</b>' . __(' with your module. To link up with this Blog:',
                'lti-text') . '<br /><br />' .
            sprintf(__('Place this key (%s) in the custom parameters of the link to WordPress in your course as:', 'lti-text'),
                $share_key->getId()) . '<br /><br />' .
            sprintf(__('share_key=%s', 'lti-text'), $share_key->getId());

        // Write friendly times
        if ($_POST['life'] <= 12) {
            $time = sprintf(_n('%s hour', '%s hours', $_POST['life'], 'lti-text'), $_POST['life']);
        }
        if ($_POST['life'] >= 24 && $_POST['life'] <= 144) {
            $days = intval($_POST['life'] / 24);
            $time = sprintf(_n('%s day', '%s days', $days, 'lti-text'), $days);
        }
        if ($_POST['life'] == 168) {
            $time = '1 week';
        }

        if ($share_key->autoApprove) {
            $senttext .= '<br /><br />' . sprintf(__('The share key must be activated within %s.', 'lti-text'), $time);
        } else {
            $senttext .= '<br /><br />' .
                sprintf(__('The share key must be activated within %s and will only work after then being approved by an administrator/editor of the site being shared.',
                        'lti-text'), $time);
        }

        // Send text/html
        $headers = 'Content-Type: text/html; charset=UTF-8';
        if (wp_mail($_POST['email'], 'WordPress Share Key', $senttext, $headers)) {
            echo '<div class="wrap"><h2>' . __('Share this Site', 'lti-text') . '</h2>';
            echo '<p>' . sprintf(__('The text below has been emailed to %s', 'lti-text'), $_POST['email']) . '</p>';
            echo '<p>' . $senttext . '</p></div>';
        } else {
            echo '<div class="wrap"><h2>' . __('Share this Site', 'lti-text') . '</h2>';
            echo '<p>' . $senttext . '</p></div>';
        }
    } else {
        ?>

        <h2><?php _e('Add Share Key', 'lti-text') ?></h2>

        <p><?php _e('You may share this site with users using other LTI links into WordPress. These might be:', 'lti-text') ?></p>
        <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
          <li><?php _e('other links from within the same course/module', 'lti-text') ?></li>
          <li><?php _e('links from other course/modules in the same VLE/LMS', 'lti-text') ?></li>
          <li><?php _e('links from a different VLE/LMS within your institution or outside', 'lti-text') ?></li>
        </ul>
        <p><?php _e('To invite another link to share this site:', 'lti-text') ?></p>
        <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
          <li><?php _e('use the button below to generate a new share key (you may choose to pre-approve
          the share or leave it to be approved once the key has been activated)', 'lti-text') ?></li>
          <li><?php _e('send the share key to an instructor for the other link', 'lti-text') ?></li>
        </ul>
        <?php
        $scope = lti_get_scope($lti_session['key']);
        if (($scope === Tool::ID_SCOPE_ID_ONLY) || ($scope === LTI_WP_User::ID_SCOPE_USERNAME) || ($scope === LTI_WP_User::ID_SCOPE_EMAIL)) {
            echo
            '<p><strong>' .
            __('A global username format has been selected for this platform so it is NOT recommended to share your site when user accounts are being created.',
                'lti-text') .
            '</strong></p>';
        }
        ?>
        <form method="post" action="<?php get_admin_url(); ?>admin.php?page=lti_create_share_key">
          <table class="form-table">
            <tbody>
              <tr class="form-required">
                <th scope="row">
                  <?php _e('Life', 'lti-text'); ?>
                  <span class="description"><?php _e('(required)', 'lti-text'); ?></span>
                </th>
                <td>
                  <select name="life">
                    <option value="1">1 <?php _e('hour', 'lti-text') ?></option>
                    <option value="2">2 <?php _e('hours', 'lti-text') ?></option>
                    <option value="12">12 <?php _e('hours', 'lti-text') ?></option>
                    <option value="24">1 <?php _e('day', 'lti-text') ?></option>
                    <option value="48">2 <?php _e('days', 'lti-text') ?></option>
                    <option value="72">3 <?php _e('days', 'lti-text') ?></option>
                    <option value="96">4 <?php _e('days', 'lti-text') ?></option>
                    <option value="120">5 <?php _e('days', 'lti-text') ?></option>
                    <option value="144">6 <?php _e('days', 'lti-text') ?></option>
                    <option value="168">1 <?php _e('week', 'lti-text') ?></option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php _e('Enabled', 'lti-text'); ?>
                </th>
                <td>
                  <fieldset>
                    <legend class="screen-reader-text">
                      <span><?php _e('Enabled', 'lti-text') ?></span>
                    </legend>
                    <label for="enabled">
                      <input name="enabled" type="checkbox" id="enabled" value="true" />
                      <?php
                      _e('Automatically allow requests from this share without further approval', 'lti-text');
                      ?>
                    </label>
                  </fieldset>
                </td>
              </tr>
              <tr class="form-field form-required">
                <th scope="row">
                  <label for="email">
                    <?php _e('Enter the email address for the sharing recipient:', 'lti-text'); ?>
                    <span class="description"><?php _e('(required)', 'lti-text'); ?></span>
                  </label>
                </th>
                <td>
                  <input id="email" type="text" aria-required="true" value="" name="email">
                </td>
              </tr>
            </tbody>
          </table>
          <input type="hidden" name="action" value="continue" />
          <p class="submit">
            <input id="share" class="button-primary" type="submit" value="<?php _e('Add Share Key', 'lti-text'); ?>" name="sharekey">
          </p>
        </form>

        <?php
    }
}

/* -------------------------------------------------------------------
 * Function to produce the LTI list. Basically builds the form and
 * then uses the LTI_List_Table function to produce the list
  ------------------------------------------------------------------ */

function lti_manage_share_keys()
{
    // Load the class definition
    require_once('LTI_List_Keys.php');

    $screen = get_current_screen();
    $screen_option = $screen->get_option('per_page', 'option');

    $user = get_current_user_id();
    $per_page = get_user_meta($user, $screen_option, true);

    if (empty($per_page) || $per_page < 1) {
        $per_page = $screen->get_option('per_page', 'default');
    }

    $lti = new LTI_List_Keys($per_page);
    $lti->prepare_items();
    ?>
    <div class="wrap">

      <div id="icon-users" class="icon32"><br/></div>
      <h2>LTI Share Keys
        <a href="<?php echo get_admin_url() ?>admin.php?page=lti_create_share_key" class="add-new-h2"><?php _e('Add New', 'lti-text'); ?></a></h2>

      <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
      <form id="lti-filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <!-- Now we can render the completed list table -->
        <?php $lti->display() ?>
      </form>

    </div>
    <?php
}

function lti_manage_share_keys_screen_options()
{
    $screen = get_current_screen();
    add_screen_option('per_page', array('label' => __('LTI Share Keys', 'lti-text'), 'default' => 10, 'option' => 'lti_per_page'));

    $screen->add_help_tab(array(
        'id' => 'lti-display',
        'title' => __('Screen Display', 'lti-text'),
        'content' => '<p>' . __('You can customise the display of this screen by specifying the number of share keys to be displayed per page.',
            'lti-text') . '</p>'
    ));
}
?>