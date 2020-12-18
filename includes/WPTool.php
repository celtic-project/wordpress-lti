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

use ceLTIc\LTI;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\Outcome;

/* -------------------------------------------------------------------
 * This function is called when a successful LTI call is made. Is is
 * passed a class (tool_provider) that can access all the details of
 * the LTI call
 *
 * Parameters
 *  tool_provider - intance of of BasicLTI_Tool_Provider
 * ----------------------------------------------------------------- */

class WPTool extends Tool
{

    public function __construct($data_connector)
    {
        parent::__construct($data_connector);

        $this->setParameterConstraint('resource_link_id', true, 40, array('basic-lti-launch-request'));
        $this->setParameterConstraint('user_id', true);

        // Get settings and check whether sharing is enabled.
        $this->allowSharing = true;

        $this->signatureMethod = LTI_SIGNATURE_METHOD;
        $this->kid = LTI_KID;
        $this->rsaKey = LTI_PRIVATE_KEY;
        $this->requiredScopes = array(
            LTI\Service\Membership::$SCOPE,
            LTI\Service\Result::$SCOPE,
            LTI\Service\Score::$SCOPE,
            'https://purl.imsglobal.org/spec/lti-ext/scope/outcomes'
        );
    }

    public function onLaunch()
    {
        // If multisite support isn't in play, go home
        if (!is_multisite()) {
            $this->message = __('The LTI Plugin requires a Multisite installation of WordPress', 'lti-text');
            $this->ok = false;
            return;
        }

        // Clear any existing connections
        wp_logout();

        // Clear these before use
        $_SESSION[LTI_SESSION_PREFIX . 'return_url'] = '';
        $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = '';

        // Store return URL for later use, if present
        if (!empty($this->returnUrl)) {
            $_SESSION[LTI_SESSION_PREFIX . 'return_url'] = (strpos($this->returnUrl, '?') === false) ? $this->returnUrl . '?' : $this->returnUrl . '&';
            $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = 'Return to VLE';
            if (!empty($this->platform->name)) {
                $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = 'Return to ' . $this->platform->name;
            }
        }

        // Get what we are using as the username (unique_id-consumer_key, i.e. _21_1-stir.ac.uk)
        $scope_userid = lti_get_scope($this->platform->getKey());
        $user_login = $this->userResult->getID($scope_userid);
        // Sanitize username stripping out unsafe characters
        $user_login = sanitize_user($user_login);

        // Apply the function pre_user_login before saving to the DB.
        $user_login = apply_filters('pre_user_login', $user_login);

        // Check if this username, $user_login, is already defined
        $user = get_user_by('login', $user_login);

        if ($user) {
            // If user exists, simply save the current details
            $user->first_name = $this->userResult->firstname;
            $user->last_name = $this->userResult->lastname;
            $user->display_name = $this->userResult->fullname;
            $result = wp_insert_user($user);
        } else {
            // Create username if user provisioning is on
            $nice_trunc = substr($user_login,0,40);
            $result = wp_insert_user(
                array(
                    'user_login' => $user_login,
                    'user_pass' => wp_generate_password(),
                    'user_nicename' => $nice_trunc,
                    'first_name' => $this->userResult->firstname,
                    'last_name' => $this->userResult->lastname,
                    //'user_email'=> $this->userResult->email,
                    //'user_url' => 'http://',
                    'display_name' => $this->userResult->fullname
                )
            );
            // Handle any errors by capturing and returning to the platform
            if (is_wp_error($result)) {
                $this->reason = $result->get_error_message();
                $this->ok = false;
                return;
            } else {
                // Get the new users details
                $user = get_user_by('login', $user_login);
            }
        }

        // Get user ID
        $user_id = $user->ID;

        // Staff or Learner
        $staff = $this->userResult->isStaff() || $this->userResult->isAdmin();
        $learner = $this->userResult->isLearner();

        // set up some useful variables
        $key = $this->resourceLink->getKey();
        $context_id = $this->context->getId();
        $resource_id = $this->resourceLink->getId();

        // Create blog
        $use_context = false;
        if (!empty($context_id)) {
            $use_context = ($this->resourceLink->getSetting('custom_use_context') == 'true') ? true : false;
        }

        if ($use_context) {
            // Create new blog, if does not exist. Note this gives one blog per context, the platform supplies a context_id
            // otherwise it creates a blog per resource_id
            $path = $key . '_' . $context_id;
        } else {
            // Create new blog, if does not exist. Note this gives one blog per resource_id
            $path = $key . $resource_id;
        }

        // Replace any non-allowed characters in WordPress with -
        $path = preg_replace('/[^_0-9a-zA-Z-]+/', '-', $path);

        // Sanity Check: Ensure that path is only _A-Za-z0-9- --- the above should stop this.
        if (preg_match('/[^_0-9a-zA-Z-]+/', $path) == 1) {
            $this->reason = __('No Blog has been created as the name contains non-alphanumeric: (_a-zA-Z0-9-) allowed', 'lti-text');
            $this->ok = false;
            return;
        }

        // Get any folder(s) that WordPress might be living in
        $wppath = parse_url(get_option('siteurl'), PHP_URL_PATH);
        $path = $wppath . '/' . trailingslashit($path);

        // Get the id of the blog, if exists
        $blog_id = domain_exists(DOMAIN_CURRENT_SITE, $path, 1);
        // If Blog does not exist and this is a member of staff and blog provisioning is on, create blog
        if (!$blog_id && $staff) {
            $blog_id = wpmu_create_blog(DOMAIN_CURRENT_SITE, $path, $this->resourceLink->title, $user_id, '', '1');
            update_blog_option($blog_id, 'blogdescription', __('Provisioned by LTI', 'lti-text'));
        }

        // Blog will exist by this point unless this user is student/no role.
        if (!$blog_id) {
            $this->reason = __('No Blog has been created for this context', 'lti-text');
            $this->ok = false;
            return;
        }

        // Update/create blog name
        update_blog_option($blog_id, 'blogname', $this->resourceLink->title);

        $role = 'subscriber';
        if ($staff) {
            $role = 'administrator';
        }
        if ($learner) {
            $role = 'author';
        }

        // Add user to blog and set role
        if (!is_user_member_of_blog($user_id, $blog_id)) {
            add_user_to_blog($blog_id, $user_id, $role);
        }

        // Users added via this route should only have access to this
        // (path) site. Remove from the default blog.
        remove_user_from_blog($user_id, 1);

        // Login the user
        wp_set_current_user($user_id, $user_login);
        wp_set_auth_cookie($user_id);
        do_action('wp_login', $user_login, $user);

        // Switch to blog
        switch_to_blog($blog_id);

        // Note this is an LTI provisioned Blog.
        add_option('ltisite', true);

        // As this is an LTI provisioned Blog we store the consumer key and
        // context id as options with the session meaning we can access elsewhere
        // in the code.
        // Store lti key & context id in $_SESSION variables
        $_SESSION[LTI_SESSION_PREFIX . 'key'] = $key;
        $_SESSION[LTI_SESSION_PREFIX . 'resourceid'] = $resource_id;

        // Store the key/context in case we need to sync shares --- this ensures we return
        // to the correct platform and not the primary platform
        $_SESSION[LTI_SESSION_PREFIX . 'userkey'] = $this->userResult->getResourceLink()->getKey();
        $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink'] = $this->userResult->getResourceLink()->getId();

        // If users role in platform has changed (e.g. staff -> student),
        // then their role in the blog should change
        $user = new WP_User($user_id);
        if ($user->has_cap('administrator') && $role != 'administrator') {
            $user->add_role($role);
            $user->remove_role('administrator');
        }

        if ($user->has_cap('author') && $role != 'author') {
            $user->add_role($role);
            $user->remove_role('author');
        }

        if ($user->has_cap('subscriber') && $role != 'subscriber') {
            $user->add_role($role);
            $user->remove_role('subscriber');
        }

        // Send login time to platform if has outcomes service and can handle freetext
        $context = $this->resourceLink;

        if ($context->hasOutcomesService()) {

            // Presently this is just a demo of the outcome services and updating the menu bar in WordPress
            $outcome = new Outcome();
            $outcome->type = ResourceLink::EXT_TYPE_TEXT;
            $result = $context->doOutcomesService(ResourceLink::EXT_READ, $outcome, $this->userResult);

            // If we have successfully read then update the user metadata
            if ($result) {
                update_user_meta($user_id, 'Last Login', $outcome->getValue());
            }

            $outcome->setValue(date('d-F-Y G:i', time()));
            $context->doOutcomesService(ResourceLink::EXT_WRITE, $outcome, $this->userResult);
        }

        // Return URL for re-direction by Tool Provider class
        $this->redirectUrl = get_bloginfo('url');
    }

}

?>
