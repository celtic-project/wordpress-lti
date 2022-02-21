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
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\UserResult;

/* -------------------------------------------------------------------
 * This function handles the membership service (from the platform)
 * if it offers this LTI service
  ------------------------------------------------------------------ */

function lti_tool_sync_enrolments()
{
    global $blog_id, $lti_tool_data_connector, $lti_tool_session;

    // Load class
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'LTI_Tool_User_List_Table.php');
    // Get instance of LTI_Tool_User_List_Table and get the current action
    $user_table = new LTI_Tool_User_List_Table();
    $action = $user_table->current_action();

    $options = lti_tool_get_options();

    $platform = Platform::fromConsumerKey($lti_tool_session['userkey'], $lti_tool_data_connector);
    $resource_link = ResourceLink::fromPlatform($platform, $lti_tool_session['userresourcelink']);

    if (!$resource_link->hasMembershipsService()) {
        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('No Memberships service', 'lti-tool') . '</h1></div>';
        return;
    }
    // Deal with current action
    switch ($action) {
        case 'continue':
            // Get current membership of this blog
            $wp_user_search = new WP_User_Query($blog_id);
            $current_members = $wp_user_search->get_results();
            $blog_users = array();
            foreach ($current_members as $member) {
                $blog_users[$member->ID] = $member;
            }

            // Get the users from the resource link
            $lti_users = $resource_link->getMemberships();
            if (!$lti_users) {
                add_action('admin_notices', 'lti_tool_sync_error');
                do_action('admin_notices');
                return;
            }

            // Prepare the various lists
            $membership_platform = array();
            $membership_platform['new'] = array();
            $membership_platform['add'] = array();
            $membership_platform['change'] = array();
            $membership_platform['delete'] = array();

            foreach ($lti_users as $lti_user) {
                // Get what we are using as the username (unique_id-consumer_key, e.g. _21_1-stir.ac.uk)
                $user_login = lti_tool_get_user_login($lti_tool_session['userkey'], $lti_user);
                // Apply the function pre_user_login before saving to the DB.
                $user_login = apply_filters('pre_user_login', $user_login);

                // Check if this username, $user_login, is already defined
                $user = get_user_by('login', $user_login);

                $category = '';
                $reasons = array();
                if (empty($user)) {
                    $category = 'new';
                    $reasons[] = LTI_Tool_User_List_Table::REASON_NEW;
                } elseif (is_multisite() && !is_user_member_of_blog($user->ID, $blog_id)) {
                    $category = 'add';
                    $reasons[] = LTI_Tool_User_List_Table::REASON_ADD;
                } else {
                    unset($blog_users[$user->ID]);
                    $lti_tool_platform_key = get_user_meta($user->ID, 'lti_tool_platform_key', true);
                    $lti_tool_user_id = get_user_meta($user->ID, 'lti_tool_user_id', true);
                    if ($user->display_name !== trim("{$lti_user->firstname} {$lti_user->lastname}")) {
                        $category = 'change';
                        $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_NAME;
                    }
                    if (lti_tool_do_save_email() && ($lti_user->email !== $user->user_email)) {
                        $category = 'change';
                        $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_EMAIL;
                    }
                    if ($lti_user->isLearner()) {
                        if (!lti_tool_user_has_role($user, $options['role_student'])) {
                            $category = 'change';
                            $lti_user->role = $options['role_student'];
                            $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_ROLE;
                        }
                    } elseif ($lti_user->isStaff()) {
                        if (!lti_tool_user_has_role($user, $options['role_staff'])) {
                            $category = 'change';
                            $lti_user->role = $options['role_staff'];
                            $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_ROLE;
                        }
                    } elseif (!lti_tool_user_has_role($user, $options['role_other'])) {
                        $category = 'change';
                        $lti_user->role = $options['role_other'];
                        $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_ROLE;
                    }
                    if (($lti_tool_platform_key !== $platform->getKey()) ||
                        ($lti_tool_user_id !== $lti_user->ltiUserId)) {
                        $category = 'change';
                        $reasons[] = LTI_Tool_User_List_Table::REASON_CHANGE_ID;
                    }
                }
                if (!empty($category)) {
                    $lti_wp_user = LTI_Tool_WP_User::fromUserResult($lti_user, $user_login, $options);
                    if (!empty($user)) {
                        $lti_wp_user->id = $user->ID;
                    }
                    $lti_wp_user->reasons = $reasons;
                    $membership_platform[$category][] = $lti_wp_user;
                }
            }
            if (!empty($blog_users)) {
                $lti_user = UserResult::fromResourceLink($resource_link, '');
                $prefix = lti_tool_get_user_login($lti_tool_session['userkey'], $lti_user);
                foreach ($blog_users as $blog_user) {
                    if (empty($prefix) || (strpos($blog_user->user_login, $prefix) === 0)) {
                        $lti_wp_user = LTI_Tool_WP_User::fromWPUser($blog_user);
                        $lti_wp_user->reasons = array(LTI_Tool_User_List_Table::REASON_DELETE);
                        $membership_platform['delete'][] = $lti_wp_user;
                    }
                }
            }
            $lti_tool_session['sync'] = $membership_platform;
            $action = 'new';
            if (empty($membership_platform['new'])) {
                if (!empty($membership_platform['add'])) {
                    $action = 'add';
                } elseif (!empty($membership_platform['change'])) {
                    $action = 'change';
                } elseif (!empty($membership_platform['delete'])) {
                    $action = 'delete';
                }
            }
            lti_tool_set_session();
        // Display the various lists
        case 'new':
        case 'add':
        case 'change':
        case 'delete':
            $user_table->status = $action;
            $user_table->prepare_items();
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__('LTI Users Synchronisation', 'lti-tool') . '</h1><hr class="wp-header-end">';
            do_action('admin_notices');
            $user_table->views();
            echo('  <form id="lti_tool_user_filter" method="get">' . "\n");
            echo('    <input type="hidden" name="page" value="' . esc_attr(sanitize_text_field($_REQUEST['page'])) . '" />' . "\n");
            $user_table->display();
            echo ('  <p class="submit">' . "\n");
            if (empty($lti_tool_session['sync']['new']) && empty($lti_tool_session['sync']['add']) && empty($lti_tool_session['sync']['change']) && empty($lti_tool_session['sync']['delete'])) {
                echo '<strong>';
                esc_html_e('No updates found.', 'lti-tool');
                echo '</strong>';
            } else {
                $disabled = '';
                if (empty($lti_tool_session['sync']['delete'])) {
                    $disabled = ' disabled';
                }
                echo ('    <input id="delete" class="button-primary" type="submit" value="' . esc_attr__('Update with Deletions',
                    'lti-tool') . '" name="delete"' . $disabled . ">\n");
                $disabled = '';
                if (empty($lti_tool_session['sync']['new']) && empty($lti_tool_session['sync']['add']) && empty($lti_tool_session['sync']['change'])) {
                    $disabled = ' disabled';
                }
                echo ('    <input id="nodelete" class="button-primary" type="submit" value="' . esc_attr__('Update without Deletions',
                    'lti-tool') . '" name="nodelete"' . $disabled . ">\n");
            }
            echo ("  </p>\n");
            echo("  </form>\n");
            echo("</div>\n");
            break;
        default:
            unset($lti_tool_session['sync']);
            lti_tool_set_session();

            // If platform has setting service then get date/time of last synchronisation
            $last_sync = '';
            if ($resource_link->hasSettingService()) {
                $last_sync = $resource_link->doSettingService(ResourceLink::EXT_READ);
            }

            // Simply produce descriptive text when page first encountered.
            ?>

            <div class="wrap">
              <h1 class="wp-heading-inline"><?php _e('LTI Users Synchronisation', 'lti-tool') ?></h1>

              <p><?php
                esc_html_e('This page allows you to update this site with any changes to the enrolments in the course which is its source. These updates may include:',
                    'lti-tool')
                ?></p>
              <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
                <li><?php esc_html_e('new users', 'lti-tool') ?></li>
                <li><?php esc_html_e('changes to the names of existing users', 'lti-tool') ?></li>
                <li><?php esc_html_e('changes to the role (instructor or student) of an existing user', 'lti-tool') ?></li>
                <li><?php esc_html_e('deletion of users which no longer exist in the source', 'lti-tool') ?></li>
              </ul>
              <p><?php
                $allowed = array('em' => array(), 'strong' => array());
                echo wp_kses(__('Click on the <em>Continue</em> to obtain a list of the changes to be processed. The updates will not be made until you confirm them.',
                        'lti-tool'), $allowed);
                ?></p>
              <?php
              if (!empty($last_sync)) {
                  echo '<p>' . esc_html__(sprintf(__('Last Synchronisation: %s', 'lti-tool'), $last_sync)) . '</p>';
              }
              ?>
              <form method="post" action="<?php echo esc_url(get_admin_url() . 'users.php?page=lti_tool_sync_enrolments&action=continue'); ?>">
                <p class="submit">
                  <input id="membership" class="button-primary" type="submit" value="<?php esc_attr_e('Continue', 'lti-tool'); ?>">
                </p>
              </form>
            </div>

        <?php
    }
}

function lti_tool_sync_error()
{
    $message = esc_html__('An error occurred when synchronising users.', 'lti-tool');
    echo <<< EOD
    <div class="notice notice-error is-dismissible">
        <p>{$message}</p>
    </div>

EOD;
}
