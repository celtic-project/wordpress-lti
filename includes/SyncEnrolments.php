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
    global $blog_id, $lti_db_connector;

    // Load class
    require_once('LTI_User_List_Table.php');
    // Load Membership Library functions
    require_once('MembershipLibrary.php');

    // Create $_SESSION variables if not present
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'all'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'all'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'provision'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'provision'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'new_to_blog'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'newadmins'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'newadmins'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'changed'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'changed'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'role_changed'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'role_changed'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'remove'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'remove'] = array();
    }
    if (empty($_SESSION[LTI_SESSION_PREFIX . 'nochanges'])) {
        $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 0;
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

    $platform = Platform::fromConsumerKey($_SESSION[LTI_SESSION_PREFIX . 'userkey'], $lti_db_connector);
    $resource_link = ResourceLink::fromPlatform($platform, $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink']);

    if (!$resource_link->hasMembershipsService()) {
        echo '<h2>' . __('No Memberships service', 'lti-text') . '</h2>';
        return;
    }

    // Deal with current action
    switch ($choice) {
        case 'continue':
            // Get the users from the resource link
            $lti_users = $resource_link->getMemberships();

            if (!$lti_users) {
                echo '<h2>' . __('Error on Synchronisation', 'lti-text') . '</h2>';
                echo '<p>' . sprintf(__('Error returned from platform (%s)', 'lti-text'), $platform->name) . '</p>';
                echo '<p>' . sprintf(__('Request to platform:  %s', 'lti-text'),
                    '<br />' . urldecode(str_replace('&', '<br />', $resource_link->extRequest))) . '</p>';
                echo '<p>' . sprintf(__('Response from platform: %s', 'lti-text'), '<br />' . $resource_link->extResponse) . '</p>';
                return;
            }
            // Get straight-forward list to work with in WordPress
            $membership_platform = lti_create_user_list($lti_users);

            // Get current membership of this blog
            $wp_user_search = new WP_User_Query($blog_id);
            $current_members = $wp_user_search->get_results();

            // Create the various lists
            $membership_platform = lti_create_membership_lists($membership_platform);

            // Assign list to $_SESSION variables
            $_SESSION[LTI_SESSION_PREFIX . 'all'] = serialize($membership_platform);
            $_SESSION[LTI_SESSION_PREFIX . 'provision'] = serialize(lti_get_members($_SESSION[LTI_SESSION_PREFIX . 'all'],
                    'provision'));
            $_SESSION[LTI_SESSION_PREFIX . 'new_to_blog'] = serialize(lti_get_members($_SESSION[LTI_SESSION_PREFIX . 'all'],
                    'new_to_blog'));
            $_SESSION[LTI_SESSION_PREFIX . 'newadmins'] = serialize(lti_get_members($_SESSION[LTI_SESSION_PREFIX . 'all'],
                    'newadmins'));
            $_SESSION[LTI_SESSION_PREFIX . 'changed'] = serialize(lti_get_members($_SESSION[LTI_SESSION_PREFIX . 'all'], 'changed'));
            $_SESSION[LTI_SESSION_PREFIX . 'role_changed'] = serialize(lti_get_members($_SESSION[LTI_SESSION_PREFIX . 'all'],
                    'rchanged'));

            // Blog members for removal need to be handled differently
            $_SESSION[LTI_SESSION_PREFIX . 'remove'] = serialize(lti_deleted_members($membership_platform, $current_members));
        case 'all': // Not currently used
            // Display all the members from the platform
            lti_display($_SESSION[LTI_SESSION_PREFIX . 'all'], $ltiuser);
            break;
        case 'provision': // Not currently used
            echo "<h2>" . __('Membership Synchronisation - New Members', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'provision'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'provision'], $ltiuser);
            }
            break;
        // Display the various lists
        case 'new_to_blog': // Not currently used
            echo "<h2>" . __('Membership Synchronisation - New Blog Members', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog'], $ltiuser);
            }
            break;
        case 'newadmins':
            echo "<h2>" . __('Membership Synchronisation - New Administrators to Blog', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'newadmins'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'newadmins'], $ltiuser);
            }
            break;
        case 'changed': // Not currently used
            echo "<h2>" . __('Membership Synchronisation - Changed Members', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'changed'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'changed'], $ltiuser);
            }
            break;
        case 'rchanged':
            echo "<h2>" . __('Membership Synchronisation - Role Changed', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'role_changed'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'role_changed'], $ltiuser);
            }
            break;
        case 'remove':
            echo "<h2>" . __('Membership Synchronisation - Members for Removal from Blog', 'lti-text') . "</h2>";
            if (!empty($_SESSION[LTI_SESSION_PREFIX . 'remove'])) {
                lti_display($_SESSION[LTI_SESSION_PREFIX . 'remove'], $ltiuser);
            }
            break;
        case 'error':
            echo "<h2>" . __('Synchronisation Errors', 'lti-text') . "</h2>";
            echo "<p>" . $_SESSION[LTI_SESSION_PREFIX . 'error'] . "</p>";
            echo "<p>" . __('Remaining users from platform added', 'lti-text') . "</p>";
            $_SESSION[LTI_SESSION_PREFIX . 'error'] = '';
            break;
        default:
            // If platform has setting service then get date/time of last synchronisation
            $last_sync = '';
            if ($resource_link->hasSettingService()) {
                $last_sync = $resource_link->doSettingService(ResourceLink::EXT_READ);
            }

            // Simply produce descriptive text when page first encountered.
            ?>

            <h2><?php _e('Membership Synchronisation', 'lti-text') ?></h2>

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
            <form method="post" action="<?php get_admin_url(); ?>users.php?page=lti_sync_enrolments">
              <input type="hidden" name="action" value="continue" />
              <p class="submit">
                <input id="membership" class="button-primary" type="submit" value="<?php _e('Continue', 'lti-text'); ?>" name="membership">
              </p>
            </form>

            <?php
            // Set sessions changes to 0
            $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 0;
    }
}
?>