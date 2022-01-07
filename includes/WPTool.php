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

use ceLTIc\LTI;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\Outcome;
use ceLTIc\LTI\Profile;
use ceLTIc\LTI\Util;

/* -------------------------------------------------------------------
 * This function is called when a successful LTI call is made. Is is
 * passed a class (tool_provider) that can access all the details of
 * the LTI call
 *
 * Parameters
 *  tool_provider - intance of of BasicLTI_Tool_Provider
 * ----------------------------------------------------------------- */

class LTI_WPTool extends Tool
{

    public function __construct($data_connector)
    {
        parent::__construct($data_connector);

        $this->baseUrl = get_bloginfo('url') . '/';

        $this->vendor = new Profile\Item('celtic', 'ceLTIc Project', 'ceLTIc Project', 'https://www.celtic-project.org/');
        $this->product = new Profile\Item('687e09a3-4845-4581-9ca4-6845b8728a79', 'WordPress',
            'Open source software for creating beautiful blogs.', 'https://wordpress.org');

        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', '?lti',
                array('User.id', 'Membership.role', 'Person.name.full', 'Person.name.family', 'Person.name.given')));

        $this->resourceHandlers[] = new Profile\ResourceHandler(
            new Profile\Item('wp', 'WordPress', 'Create a beautiful blog.'), '?lti&icon', $requiredMessages, array());

        $this->setParameterConstraint('resource_link_id', true, 40, array('basic-lti-launch-request'));
        $this->setParameterConstraint('user_id', true);

        $this->allowSharing = is_multisite();

        $this->signatureMethod = LTI_SIGNATURE_METHOD;
        $this->jku = $this->baseUrl . '?lti&keys';
        $this->kid = LTI_KID;
        $this->rsaKey = LTI_PRIVATE_KEY;
        $this->requiredScopes = array(
            LTI\Service\Membership::$SCOPE,
            LTI\Service\Result::$SCOPE,
            LTI\Service\Score::$SCOPE,
            'https://purl.imsglobal.org/spec/lti-ext/scope/outcomes'
        );
    }

    protected function onLaunch()
    {
        global $lti_session;

        // Clear any existing connections
        $lti_session['logging_in'] = true;
        wp_logout();
        unset($lti_session['logging_in']);

        // Clear these before use
        $lti_session['return_url'] = '';
        $lti_session['return_name'] = '';

        // Store return URL for later use, if present
        if (!empty($this->returnUrl)) {
            $lti_session['return_url'] = (strpos($this->returnUrl, '?') === false) ? $this->returnUrl . '?' : $this->returnUrl . '&';
            $lti_session['return_name'] = 'Return to VLE';
            if (!empty($this->platform->name)) {
                $lti_session['return_name'] = 'Return to ' . $this->platform->name;
            }
        }
        if (!empty($this->messageParameters['custom_tool_name'])) {
            $lti_session['tool_name'] = $this->messageParameters['custom_tool_name'];
        }

        // Get what we are using as the username (unique_id-consumer_key, e.g. _21_1-stir.ac.uk)
        $user_login = lti_get_user_login($this->platform->getKey(), $this->userResult);
        // Apply the function pre_user_login before saving to the DB.
        $user_login = apply_filters('pre_user_login', $user_login);

        // Check if this username, $user_login, is already defined
        $user = get_user_by('login', $user_login);

        if (!empty($user)) {
            // If user exists, simply save the current details
            $user->first_name = $this->userResult->firstname;
            $user->last_name = $this->userResult->lastname;
            $user->display_name = $this->userResult->fullname;
            if (lti_do_save_email($this->userResult->getResourceLink()->getKey())) {
                $user->user_email = $this->userResult->email;
            }
            $result = wp_update_user($user);
        } else {
            // Create username if user provisioning is on
            $user_data = array(
                'user_login' => $user_login,
                'user_pass' => wp_generate_password(),
                'user_nicename' => $user_login,
                'first_name' => $this->userResult->firstname,
                'last_name' => $this->userResult->lastname,
                'display_name' => $this->userResult->fullname
            );
            if (lti_do_save_email($this->userResult->getResourceLink()->getKey())) {
                $user_data['user_email'] = $this->userResult->email;
            }
            $result = wp_insert_user($user_data);
        }
        // Handle any errors by capturing and returning to the platform
        if (is_wp_error($result)) {
            $this->reason = $result->get_error_message();
            $this->ok = false;
            return;
        } elseif (empty($user)) {
            // Get the new users details
            $user = get_user_by('login', $user_login);
        }

        // Get user ID
        $user_id = $user->ID;

        // Save LTI user ID
        update_user_meta($user_id, 'lti_platform_pk', $this->platform->getRecordId());
        update_user_meta($user_id, 'lti_user_id', $this->userResult->ltiUserId);

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

        if (is_multisite()) {
            // Get any folder(s) that WordPress might be living in
            $wppath = parse_url(get_option('siteurl'), PHP_URL_PATH);
            $path = $wppath . '/' . trailingslashit($path);

            // Get the id of the blog, if exists
            $blog_id = domain_exists(DOMAIN_CURRENT_SITE, $path, 1);
            // If Blog does not exist and this is a member of staff and blog provisioning is on, create blog
            if (!$blog_id && ($this->userResult->isStaff() || $this->userResult->isAdmin())) {
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

            // Users added via this route should only have access to this
            // (path) site. Remove from the default blog.
            remove_user_from_blog($user_id, 1);
        } else {
            $blog_id = get_current_blog_id();
        }

        $options = lti_get_options();
        $role = lti_user_role($this->userResult, $options);

        // Add user to blog and set role
        if (!is_user_member_of_blog($user_id, $blog_id)) {
            add_user_to_blog($blog_id, $user_id, $role);
        }

        // Login the user
        wp_set_current_user($user_id, $user_login);
        wp_set_auth_cookie($user_id);
        do_action('wp_login', $user_login, $user);

        if (is_multisite()) {
            // Switch to blog
            switch_to_blog($blog_id);

            // Note this is an LTI provisioned Blog.
            add_option('ltisite', true);
        }

        // As this is an LTI provisioned Blog we store the consumer key and
        // context id as options with the session meaning we can access elsewhere
        // in the code.
        // Store lti key & context id in $lti_session variables
        $lti_session['key'] = $key;
        $lti_session['resourceid'] = $resource_id;

        // Store the key/context in case we need to sync shares --- this ensures we return
        // to the correct platform and not the primary platform
        $lti_session['userkey'] = $this->userResult->getResourceLink()->getKey();
        $lti_session['userresourcelink'] = $this->userResult->getResourceLink()->getId();

        // If users role in platform has changed (e.g. staff -> student),
        // then their role in the blog should change
        $user = new WP_User($user_id);
        $user->set_role($role);

        // Send login time to platform if has outcomes service and can handle freetext
        $resource_link = $this->resourceLink;

        if ($resource_link->hasOutcomesService()) {

            // Presently this is just a demo of the outcome services and updating the menu bar in WordPress
            $outcome = new Outcome();
            $outcome->type = ResourceLink::EXT_TYPE_TEXT;
            $result = $resource_link->doOutcomesService(ResourceLink::EXT_READ, $outcome, $this->userResult);

            // If we have successfully read then update the user metadata
            if ($result) {
                update_user_meta($user_id, 'Last Login', $outcome->getValue());
            }

            $outcome->setValue(date('d-F-Y G:i', time()));
            $resource_link->doOutcomesService(ResourceLink::EXT_WRITE, $outcome, $this->userResult);
        }

        // Return URL for re-direction by Tool Provider class
        if (!empty($options['homepage'])) {
            $this->redirectUrl = get_option('siteurl') . '/' . $options['homepage'];
        } else {
            $this->redirectUrl = get_bloginfo('url');
        }

        lti_set_session($lti_session);
    }

    protected function onRegistration()
    {
        ob_start();

        add_action('wp_enqueue_scripts', 'addToHeader');

        get_header();

        if (!defined('AUTO_ENABLE') || !AUTO_ENABLE) {
            $successMessage = 'Note that the tool must be enabled by the tool provider before it can be used.';
        } else if (!defined('ENABLE_FOR_DAYS') || (ENABLE_FOR_DAYS <= 0)) {
            $successMessage = 'The tool has been automatically enabled by the tool provider for immediate use.';
        } else {
            $successMessage = 'The tool has been enabled for you to use for the next ' . ENABLE_FOR_DAYS . ' day';
            if (ENABLE_FOR_DAYS > 1) {
                $successMessage .= 's';
            }
            $successMessage .= '.';
        }

        echo <<< EOD
<div id="primary" class="content-area">
  <div id="content" class="site-content" role="main">

    <h2 class="entry-title">Registration page</h2>

    <div class="entry-content">

      <p>
        This page allows you to complete a registration with a Moodle LTI 1.3 platform (other platforms will be supported once they offer this facility).
      </p>

      <p class="tbc">
        Select how you would like users to be created within WordPress:
      </p>
      <div class="indent">
        <legend class="screen-reader-text">
          <span>Resource-specific: Prefix the ID with the consumer key and resource link ID</span>
        </legend>
        <label for="lti_scope3">
          <input name="lti_scope" type="radio" id="lti_scope3" value="3" />
          <em>Resource-specific:</em> Prefix the ID with the consumer key and resource link ID
        </label><br />
        <legend class="screen-reader-text">
          <span>Context-specific: Prefix the ID with the consumer key and context ID</span>
        </legend>
        <label for="lti_scope2">
          <input name="lti_scope" type="radio" id="lti_scope2" value="2" />
          <em>Context-specific:</em> Prefix the ID with the consumer key and context ID
        </label><br />
        <legend class="screen-reader-text">
          <span>Platform-specific: Prefix an ID with the consumer key</span>
        </legend>
        <label for="lti_scope1">
          <input name="lti_scope" type="radio" id="lti_scope1" value="1" />
          <em>Platform-specific:</em> Prefix the ID with the consumer key
        </label><br />
        <legend class="screen-reader-text">
          <span>Global: Use ID value only</span>
        </legend>
        <label for="lti_scope0">
          <input name="lti_scope" type="radio" id="lti_scope0" value="0" />
          <em>Global:</em> Use ID value only
        </label><br />
        <label for="lti_scopeu">
          <input name="lti_scope" type="radio" id="lti_scopeU" value="U" />
          <em>Username:</em> Use platform username only
        </label><br />
        <label for="lti_scopee">
          <input name="lti_scope" type="radio" id="lti_scopeE" value="E" />
          <em>Email:</em> Use email address only
        </label>
      </div>

      <p id="id_continue" class="aligncentre">
        <button type="button" id="id_continuebutton" class="disabled" onclick="return doRegister();" disabled>Register</button>
      </p>
      <p id="id_loading" class="aligncentre hide">
        <img src="?lti&loading">
      </p>

      <p id="id_registered" class="success hide">
        The tool registration was successful.  {$successMessage}
      </p>
      <p id="id_notregistered" class="error hide">
        The tool registration failed.  <span id="id_reason"></span>
      </p>

      <p id="id_close" class="aligncentre hide">
        <button type="button" onclick="return doClose(this);">Close</button>
      </p>

    </div>

  </div>
</div>
<script>
var openid_configuration = '{$_REQUEST['openid_configuration']}';
var registration_token = '{$_REQUEST['registration_token']}';
</script>
EOD;

        get_footer();

        $html = ob_get_contents();
        ob_end_clean();

        $this->output = $html;
    }

    public function doRegistration()
    {
        $platformConfig = $this->getPlatformConfiguration();
        if ($this->ok) {
            $toolConfig = $this->getConfiguration($platformConfig);
            $registrationConfig = $this->sendRegistration($platformConfig, $toolConfig);
            if ($this->ok) {
                $now = time();
                $this->getPlatformToRegister($platformConfig, $registrationConfig, false);
                do {
                    $key = self::getGUID($_POST['lti_scope']);
                    $platform = Platform::fromConsumerKey($key, $this->dataConnector);
                } while (!is_null($platform->created));
                $this->platform->setKey($key);
                $this->platform->secret = Util::getRandomString(32);
                $this->platform->name = 'Trial (' . date('Y-m-d H:i:s', $now) . ')';
                $this->platform->protected = true;
                if (defined('AUTO_ENABLE') && AUTO_ENABLE) {
                    $this->platform->enabled = true;
                }
                if (defined('ENABLE_FOR_DAYS') && (ENABLE_FOR_DAYS > 0)) {
                    $this->platform->enableFrom = $now;
                    $this->platform->enableUntil = $now + (ENABLE_FOR_DAYS * 24 * 60 * 60);
                }
                $this->ok = $this->platform->save();
                if (!$this->ok) {
                    $this->reason = 'Sorry, an error occurred when saving the platform details.';
                }
            }
        }
    }

    private static function getGUID($scope)
    {
        return "WP{$scope}-" . strtoupper(Util::getRandomString(6));
    }

}

function addToHeader()
{
    wp_register_style('lti-register-style', plugins_url('css/register.css', dirname(__FILE__)));
    wp_enqueue_style('lti-register-style');
    wp_enqueue_script('lti-register_script', plugins_url('js/registerjs.php', dirname(__FILE__)), array('jquery'));
}

?>
