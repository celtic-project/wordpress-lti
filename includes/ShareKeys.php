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
 *  Contact: Stephen P Vickers <stephen@spvsoftwareproducts.com>
 */

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\ResourceLinkShareKey;

function lti_tool_create_share_key()
{
    global $lti_tool_data_connector, $lti_tool_session;

    // Get the context
    $platform = Platform::fromConsumerKey($lti_tool_session['key'], $lti_tool_data_connector);
    $resource = ResourceLink::fromPlatform($platform, $lti_tool_session['resourceid']);

    if (!empty($_POST['email'])) {
        $share_key = new ResourceLinkShareKey($resource);
        if (isset($_POST['life'])) {
            $share_key->life = intval(sanitize_text_field($_POST['life']));
        }
        if (isset($_POST['enabled'])) {
            $share_key->autoApprove = !empty($_POST['enabled']);
        }
        $share_key->save();

        $current_user = wp_get_current_user();
        $senttext = __('Instructor: ', 'lti-tool') . '<b>' . $current_user->display_name . '</b>' . __(' has shared', 'lti-tool') . '<br /><br />' .
            __('Blog Name: ', 'lti-tool') . '<b>' . get_bloginfo('name') . '</b>' . __(' with your module. To link up with this Blog:',
                'lti-tool') . '<br /><br />' .
            sprintf(__('Place this key (%s) in the custom parameters of the link to WordPress in your course as:', 'lti-tool'),
                $share_key->getId()) . '<br /><br />' .
            sprintf(__('share_key=%s', 'lti-tool'), $share_key->getId());

        // Write friendly times
        $life = intval(sanitize_text_field($_POST['life']));
        if ($life <= 12) {
            $time = sprintf(_n('%s hour', '%s hours', $life, 'lti-tool'), $life);
        }
        if (($life >= 24) && ($life <= 144)) {
            $days = intval($life / 24);
            $time = sprintf(_n('%s day', '%s days', $days, 'lti-tool'), $days);
        }
        if ($life == 168) {
            $time = '1 week';
        }

        if ($share_key->autoApprove) {
            $senttext .= '<br /><br />' . sprintf(__('The share key must be activated within %s.', 'lti-tool'), $time);
        } else {
            $senttext .= '<br /><br />' .
                sprintf(__('The share key must be activated within %s and will only work after then being approved by an administrator/editor of the site being shared.',
                        'lti-tool'), $time);
        }

        // Send text/html
        $headers = 'Content-Type: text/html; charset=UTF-8';
        $email = sanitize_email($_POST['email']);
        $allowed = array('br' => array(), 'b' => array());
        if (wp_mail($email, 'WordPress Share Key', $senttext, $headers)) {
            echo '<div class="wrap"><h2>' . esc_html__('Share this Site', 'lti-tool') . '</h2>';
            echo '<p>' . esc_html(sprintf(__('The text below has been emailed to %s', 'lti-tool'), $email)) . '</p>';
            echo '<p>' . wp_kses($senttext, $allowed) . '</p></div>';
        } else {
            echo '<div class="wrap"><h2>' . esc_html__('Share this Site', 'lti-tool') . '</h2>';
            echo '<p>' . wp_kses($senttext, $allowed) . '</p></div>';
        }
    } else {
        ?>

        <h2><?php esc_html_e('Add Share Key', 'lti-tool') ?></h2>

        <p><?php esc_html_e('You may share this site with users using other LTI links into WordPress. These might be:', 'lti-tool') ?></p>
        <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
          <li><?php esc_html_e('other links from within the same course/module', 'lti-tool') ?></li>
          <li><?php esc_html_e('links from other course/modules in the same VLE/LMS', 'lti-tool') ?></li>
          <li><?php esc_html_e('links from a different VLE/LMS within your institution or outside', 'lti-tool') ?></li>
        </ul>
        <p><?php esc_html_e('To invite another link to share this site:', 'lti-tool') ?></p>
        <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
          <li><?php
            esc_html_e('use the button below to generate a new share key (you may choose to pre-approve the share or leave it to be approved once the key has been activated)',
                'lti-tool')
            ?></li>
          <li><?php esc_html_e('send the share key to an instructor for the other link', 'lti-tool') ?></li>
        </ul>
        <?php
        $scope = lti_tool_get_scope($lti_tool_session['key']);
        if (($scope === Tool::ID_SCOPE_ID_ONLY) || ($scope === LTI_Tool_WP_User::ID_SCOPE_USERNAME) || ($scope === LTI_Tool_WP_User::ID_SCOPE_EMAIL)) {
            echo
            '<p><strong>' .
            esc_html__('A global username format has been selected for this platform so it is NOT recommended to share your site when user accounts are being created.',
                'lti-tool') .
            '</strong></p>';
        }
        ?>
        <form method="post" action="<?php echo esc_url(get_admin_url() . 'admin.php?page=lti_tool_create_share_key'); ?>">
          <table class="form-table">
            <tbody>
              <tr class="form-required">
                <th scope="row">
                  <?php esc_html_e('Life', 'lti-tool'); ?>
                  <span class="description"><?php esc_html_e('(required)', 'lti-tool'); ?></span>
                </th>
                <td>
                  <select name="life">
                    <option value="1">1 <?php esc_html_e('hour', 'lti-tool') ?></option>
                    <option value="2">2 <?php _e('hours', 'lti-tool') ?></option>
                    <option value="12">12 <?php esc_html_e('hours', 'lti-tool') ?></option>
                    <option value="24">1 <?php esc_html_e('day', 'lti-tool') ?></option>
                    <option value="48">2 <?php esc_html_e('days', 'lti-tool') ?></option>
                    <option value="72">3 <?php esc_html_e('days', 'lti-tool') ?></option>
                    <option value="96">4 <?php esc_html_e('days', 'lti-tool') ?></option>
                    <option value="120">5 <?php esc_html_e('days', 'lti-tool') ?></option>
                    <option value="144">6 <?php esc_html_e('days', 'lti-tool') ?></option>
                    <option value="168">1 <?php esc_html_e('week', 'lti-tool') ?></option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row">
                  <?php esc_html_e('Enabled', 'lti-tool'); ?>
                </th>
                <td>
                  <fieldset>
                    <legend class="screen-reader-text">
                      <span><?php esc_html_e('Enabled', 'lti-tool') ?></span>
                    </legend>
                    <label for="enabled">
                      <input name="enabled" type="checkbox" id="enabled" value="true" />
                      <?php
                      esc_html_e('Automatically allow requests from this share without further approval', 'lti-tool');
                      ?>
                    </label>
                  </fieldset>
                </td>
              </tr>
              <tr class="form-field form-required">
                <th scope="row">
                  <label for="email">
                    <?php esc_html_e('Enter the email address for the sharing recipient:', 'lti-tool'); ?>
                    <span class="description"><?php esc_html_e('(required)', 'lti-tool'); ?></span>
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
            <input id="share" class="button-primary" type="submit" value="<?php esc_attr_e('Add Share Key', 'lti-tool'); ?>" name="sharekey">
          </p>
        </form>

        <?php
    }
}

/* -------------------------------------------------------------------
 * Function to produce the LTI list. Basically builds the form and
 * then uses the LTI_Tool_List_Table function to produce the list
  ------------------------------------------------------------------ */

function lti_tool_manage_share_keys()
{
    // Load the class definition
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'LTI_Tool_List_Keys.php');

    $screen = get_current_screen();
    $screen_option = $screen->get_option('per_page', 'option');

    $user = get_current_user_id();
    $per_page = get_user_meta($user, $screen_option, true);

    if (empty($per_page) || $per_page < 1) {
        $per_page = $screen->get_option('per_page', 'default');
    }

    $lti = new LTI_Tool_List_Keys($per_page);
    $lti->prepare_items();
    ?>
    <div class="wrap">

      <div id="icon-users" class="icon32"><br/></div>
      <h2>LTI Share Keys
        <a href="<?php echo esc_url(get_admin_url()) ?>admin.php?page=lti_tool_create_share_key" class="add-new-h2"><?php
          esc_html_e('Add New', 'lti-tool');
          ?></a></h2>

      <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
      <form id="lti_tool_filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php esc_attr_e(sanitize_text_field($_REQUEST['page'])); ?>" />
        <!-- Now we can render the completed list table -->
        <?php $lti->display() ?>
      </form>

    </div>
    <?php
}

function lti_tool_manage_share_keys_screen_options()
{
    $screen = get_current_screen();
    add_screen_option('per_page',
        array('label' => __('LTI Share Keys', 'lti-tool'), 'default' => 10, 'option' => 'lti_tool_per_page'));

    $screen->add_help_tab(array(
        'id' => 'lti-display',
        'title' => __('Screen Display', 'lti-tool'),
        'content' => '<p>' . __('You can customise the display of this screen by specifying the number of share keys to be displayed per page.',
            'lti-tool') . '</p>'
    ));
}
