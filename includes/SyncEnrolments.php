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

use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;

/* -------------------------------------------------------------------
 * This function handles the membership service (from the platform)
 * if it offers this LTI service
  ------------------------------------------------------------------ */

function lti_sync_enrolments()
{
    global $blog_id, $lti_db_connector, $lti_session;

    // Load class
    require_once('LTI_User_List_Table.php');
    // Load Membership Library functions
    require_once('MembershipLibrary.php');

    // Create $lti_session variables if not present
    if (empty($lti_session['all'])) {
        $lti_session['all'] = array();
    }
    if (empty($lti_session['provision'])) {
        $lti_session['provision'] = array();
    }
    if (empty($lti_session['new_to_blog'])) {
        $lti_session['new_to_blog'] = array();
    }
    if (empty($lti_session['newadmins'])) {
        $lti_session['newadmins'] = array();
    }
    if (empty($lti_session['changed'])) {
        $lti_session['changed'] = array();
    }
    if (empty($lti_session['role_changed'])) {
        $lti_session['role_changed'] = array();
    }
    if (empty($lti_session['remove'])) {
        $lti_session['remove'] = array();
    }
    if (empty($lti_session['nochanges'])) {
        $lti_session['nochanges'] = 0;
    }

    // Set up help tab
    $screen = get_current_screen();
    $screen->add_help_tab(
        array(
            'id' => 'my_help_tab',
            'title' => __('My Help Tab', 'lti-text'),
            'content' => '<p>' . __('Descriptive content that will show in My Help Tab-body goes here.', 'lti-text') . '</p>',
        )
    );

    // Get instance of LTI_User_List_Table and get the current action
    $ltiuser = new LTI_User_List_Table();
    $choice = $ltiuser->current_action();

    $platform = Platform::fromConsumerKey($lti_session['userkey'], $lti_db_connector);
    $resource_link = ResourceLink::fromPlatform($platform, $lti_session['userresourcelink']);

    if (!$resource_link->hasMembershipsService()) {
        echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('No Memberships service', 'lti-text') . '</h1></div>';
        return;
    }

    // Deal with current action
    switch ($choice) {
        case 'continue':
            // Get the users from the resource link
            $lti_users = $resource_link->getMemberships();

            if (!$lti_users) {
                echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Error on Synchronisation', 'lti-text') . '</h1>';
                echo '<p>' . sprintf(__('Error returned from platform (%s)', 'lti-text'), $platform->name) . '</p>';
                echo '<p>' . sprintf(__('Request to platform:  %s', 'lti-text'),
                    '<br />' . urldecode(str_replace('&', '<br />', $resource_link->extRequest))) . '</p>';
                echo '<p>' . sprintf(__('Response from platform: %s', 'lti-text'), '<br />' . $resource_link->extResponse) . '</p></div>';
                return;
            }
            // Get straight-forward list to work with in WordPress
            $membership_platform = lti_create_user_list($lti_users);

            // Get current membership of this blog
            $wp_user_search = new WP_User_Query($blog_id);
            $current_members = $wp_user_search->get_results();

            // Create the various lists
            $membership_platform = lti_create_membership_lists($membership_platform);

            // Assign list to $lti_session variables
            $lti_session['all'] = serialize($membership_platform);
            $lti_session['provision'] = serialize(lti_get_members($lti_session['all'],
                    'provision'));
            $lti_session['new_to_blog'] = serialize(lti_get_members($lti_session['all'],
                    'new_to_blog'));
            $lti_session['newadmins'] = serialize(lti_get_members($lti_session['all'],
                    'newadmins'));
            $lti_session['changed'] = serialize(lti_get_members($lti_session['all'], 'changed'));
            $lti_session['role_changed'] = serialize(lti_get_members($lti_session['all'],
                    'rchanged'));

            // Blog members for removal need to be handled differently
            $lti_session['remove'] = serialize(lti_deleted_members($membership_platform, $current_members));
        case 'all': // Not currently used
            // Display all the members from the platform
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Synchronise Enrolments', 'lti-text') . '</h1>';
            lti_display($lti_session['all'], $ltiuser);
            echo '</div>';
            break;
        case 'provision': // Not currently used
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - New Members', 'lti-text') . '</h1>';
            if (!empty($lti_session['provision'])) {
                lti_display($lti_session['provision'], $ltiuser);
            }
            echo '</div>';
            lti_set_session($lti_session);
            break;
        // Display the various lists
        case 'new_to_blog': // Not currently used
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - New Blog Members', 'lti-text') . '</h1>';
            if (!empty($lti_session['new_to_blog'])) {
                lti_display($lti_session['new_to_blog'], $ltiuser);
            }
            echo '</div>';
            break;
        case 'newadmins':
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - New Administrators to Blog',
                'lti-text') . '</h1>';
            if (!empty($lti_session['newadmins'])) {
                lti_display($lti_session['newadmins'], $ltiuser);
            }
            echo '</div>';
            break;
        case 'changed': // Not currently used
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - Changed Members', 'lti-text') . '</h1>';
            if (!empty($lti_session['changed'])) {
                lti_display($lti_session['changed'], $ltiuser);
            }
            echo '</div>';
            break;
        case 'rchanged':
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - Role Changed', 'lti-text') . '</h1>';
            if (!empty($lti_session['role_changed'])) {
                lti_display($lti_session['role_changed'], $ltiuser);
            }
            echo '</div>';
            break;
        case 'remove':
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Membership Synchronisation - Members for Removal from Blog',
                'lti-text') . '</h1>';
            if (!empty($lti_session['remove'])) {
                lti_display($lti_session['remove'], $ltiuser);
            }
            echo '</div>';
            break;
        case 'error':
            echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Synchronisation Errors', 'lti-text') . '</h1>';
            echo "<p>" . $lti_session['error'] . "</p>";
            echo "<p>" . __('Remaining users from platform added', 'lti-text') . "</p>";
            $lti_session['error'] = '';
            echo '</div>';
            break;
        default:
            // If platform has setting service then get date/time of last synchronisation
            $last_sync = '';
            if ($resource_link->hasSettingService()) {
                $last_sync = $resource_link->doSettingService(ResourceLink::EXT_READ);
            }

            // Simply produce descriptive text when page first encountered.
            ?>

            <div class="wrap">
              <h1 class="wp-heading-inline"><?php _e('Membership Synchronisation', 'lti-text') ?></h1>

              <p><?php _e('This page allows you to update this group with any changes to the enrolments in the course
     which is the source for this group. These updates may include:', 'lti-text') ?></p>
              <ul style="list-style-type: disc; margin-left: 15px; padding-left: 15px;">
                <li><?php _e('new members', 'lti-text') ?></li>
                <li><?php _e('changes to the names of existing members', 'lti-text') ?></li>
                <li><?php _e('changes to the type (instructor or student) of an existing member', 'lti-text') ?></li>
                <li><?php _e('deletion of members which no longer exist in the course', 'lti-text') ?></li>
              </ul>
              <p><?php _e('Click on the <i>Continue</i> to obtain a list of the changes to be processed. The updates
     will not be made until you confirm them.', 'lti-text') ?></p>
              <?php
              if (!empty($last_sync)) {
                  echo '<p>' . sprintf(__('Last Synchronisation: %s', 'lti-text'), $last_sync) . '</p>';
              }
              ?>
              <form method="post" action="<?php get_admin_url(); ?>users.php?page=lti_sync_enrolments&action=continue">
                <p class="submit">
                  <input id="membership" class="button-primary" type="submit" value="<?php _e('Continue', 'lti-text'); ?>">
                </p>
              </form>
            </div>

            <?php
            // Set sessions changes to 0
            $lti_session['nochanges'] = 0;
            lti_set_session($lti_session);
    }
}
?>