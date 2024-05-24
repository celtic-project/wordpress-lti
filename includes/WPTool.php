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

use ceLTIc\LTI;
use ceLTIc\LTI\Tool;
use ceLTIc\LTI\Platform;
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

class LTI_Tool_WPTool extends Tool
{

    public function __construct($data_connector)
    {
        parent::__construct($data_connector);

        $options = lti_tool_get_options();

        $this->baseUrl = get_bloginfo('url') . '/';

        $this->vendor = new Profile\Item('celtic', 'ceLTIc Project', 'ceLTIc Project', 'https://www.celtic-project.org/');
        $this->product = new Profile\Item('687e09a3-4845-4581-9ca4-6845b8728a79', 'WordPress',
            'Open source software for creating beautiful blogs.', 'https://wordpress.org');

        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', '?lti-tool',
                array('User.id', 'Membership.role', 'Person.name.full', 'Person.name.family', 'Person.name.given')));

        $this->resourceHandlers[] = new Profile\ResourceHandler(
            new Profile\Item('wp', 'WordPress', 'Create a beautiful blog.'), '?lti-tool&icon', $requiredMessages, array());

        $this->allowSharing = is_multisite();

        $this->signatureMethod = $options['lti13_signaturemethod'];
        $this->jku = $this->baseUrl . '?lti-tool&keys';
        $this->kid = $options['lti13_kid'];
        $this->rsaKey = $options['lti13_privatekey'];
        $this->requiredScopes = array(
            LTI\Service\Membership::$SCOPE
        );
    }

    protected function onLaunch(): void
    {
        $options = lti_tool_get_options();
        $this->init_session();
        $user_login = $this->get_user_login();
        if ($this->ok) {
            $user = $this->init_user($user_login);
        }
        if ($this->ok) {
            $blog_id = $this->get_site($user->ID);
        }
        if ($this->ok) {
            $this->login_user($blog_id, $user, $user_login, $options);
            $this->set_redirect($options);
        }
        lti_tool_set_session();
    }

    protected function onRegistration(): void
    {
        $escape = function($value) {
            return esc_html__($value, 'lti-tool');
        };
        $sanitize = function($value) {
            return sanitize_text_field($value);
        };

        ob_start();

        add_action('wp_enqueue_scripts', 'lti_tool_registration_header');

        get_header();

        $options = lti_tool_get_options();
        if (empty($options['registration_autoenable'])) {
            $successMessage = 'Note that the tool must be enabled by the tool provider before it can be used.';
        } else if (empty($options['registration_enablefordays'])) {
            $successMessage = 'The tool has been automatically enabled by the tool provider for immediate use.';
        } else {
            $successMessage = "The tool has been enabled for you to use for the next {$options['registration_enablefordays']} day";
            if (intval($options['registration_enablefordays']) > 1) {
                $successMessage .= 's';
            }
            $successMessage .= '.';
        }

        echo <<< EOD
<div id="primary" class="content-area">
  <div id="content" class="site-content" role="main">

    <h2 class="entry-title">{$escape('Registration page')}</h2>

    <div class="entry-content">

      <p>
        {$escape('This page allows you to complete a dynamic tool registration with your platform.')}
      </p>

EOD;
        $scopes = lti_tool_get_scopes();
        if (count($scopes) > 1) {
            echo <<< EOD
      <p class="lti_tool_tbc">
        {$escape('Select how you would like users to be created within WordPress:')}
      </p>
      <div class="lti_tool_indent">

EOD;
            foreach ($scopes as $scope) {
                $checked = ($options['scope'] === strval($scope['id'])) ? ' checked' : '';
                echo <<< EOD
        <legend class="screen-reader-text">
          <span>{$escape("{$scope['name']}: {$scope['description']}")}</span>
        </legend>
        <label for="lti_tool_scope{$scope['id']}">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope{$scope['id']}" value="{$scope['id']}"{$checked} />
          <em>{$escape($scope['name'])}</em>: {$escape($scope['description'])}
        </label><br />

EOD;
            }
            echo <<< EOD
      </div>

EOD;
        } else {
            $scope = reset($scopes);
            echo <<< EOD
      <div class="lti_tool_hide">
        <input name="lti_tool_scope" type="radio" value="{$scope['id']}" checked />
      </div>

EOD;
        }
        echo <<< EOD

      <p id="id_lti_tool_continue" class="lti_tool_aligncentre">
        <button type="button" class="lti_tool_button" id="id_lti_tool_continuebutton" onclick="return lti_tool_do_register();">{$escape('Register')}</button>
      </p>
      <p id="id_lti_tool_loading" class="lti_tool_aligncentre lti_tool_hide">
        <img src="?lti-tool&loading">
      </p>

      <p id="id_lti_tool_registered" class="lti_tool_success lti_tool_hide">
        {$escape('The tool registration was successful.')}  {$escape($successMessage)}
      </p>
      <p id="id_lti_tool_notregistered" class="lti_tool_error lti_tool_hide">
        {$escape('The tool registration failed.')}  <span id="id_lti_tool_reason"></span>
      </p>

      <p id="id_lti_tool_close" class="lti_tool_aligncentre lti_tool_hide">
        <button type="button" class="lti_tool_button" onclick="return doClose(this);">{$escape('Close')}</button>
      </p>

    </div>

  </div>
</div>
<script>
var lti_tool_openid_configuration = '{$sanitize($_REQUEST['openid_configuration'])}';
var lti_tool_registration_token = '{$sanitize($_REQUEST['registration_token'])}';
</script>
EOD;

        get_footer();

        $html = ob_get_contents();
        ob_end_clean();

        $this->output = $html;
    }

    public function doRegistration()
    {
        $options = lti_tool_get_options();
        $platformConfig = $this->getPlatformConfiguration();
        if ($this->ok) {
            $toolConfig = $this->getConfiguration($platformConfig);
            $registrationConfig = $this->sendRegistration($platformConfig, $toolConfig);
            if ($this->ok) {
                $now = time();
                $this->getPlatformToRegister($platformConfig, $registrationConfig, false);
                $scope = lti_tool_validate_scope($_POST['lti_scope'], $options['scope']);
                do {
                    $key = self::getGUID($scope);
                    $platform = Platform::fromConsumerKey($key, $this->dataConnector);
                } while (!is_null($platform->created));
                $this->platform->setKey($key);
                $this->platform->secret = Util::getRandomString(32);
                $this->platform->name = 'Registered ' . date('Y-m-d H:i:s', $now);
                $this->platform->protected = true;
                if (!empty($options['registration_autoenable'])) {
                    $this->platform->enabled = true;
                }
                if (!empty($options['registration_enablefordays'])) {
                    $this->platform->enableFrom = $now;
                    $this->platform->enableUntil = $now + (intval($options['registration_enablefordays']) * 24 * 60 * 60);
                }
                if (!lti_tool_use_lti_library_v5()) {
                    $this->platform->idScope = $scope;
                } elseif (is_int($scope)) {
                    $this->platform->idScope = IdScope::tryFrom($scope);
                } else {
                    $this->platform->idScope = null;
                }
                $this->platform->debugMode = false;
                $this->platform = apply_filters('lti_tool_save_platform', $this->platform, $options, array());
                $this->ok = $this->platform->save();
                if (!$this->ok) {
                    $this->reason = 'Sorry, an error occurred when saving the platform details, perhaps a configuration already exists for this platform.';
                }
            }
        }
    }

    protected function init_session()
    {
        global $lti_tool_session;

        // Clear any existing connections
        $lti_tool_session['logging_in'] = true;
        wp_logout();
        unset($lti_tool_session['logging_in']);

        // Clear these before use
        $lti_tool_session['return_url'] = '';
        $lti_tool_session['return_name'] = '';

        // Store return URL for later use, if present
        if (!empty($this->returnUrl)) {
            $lti_tool_session['return_url'] = (strpos($this->returnUrl, '?') === false) ? $this->returnUrl . '?' : $this->returnUrl . '&';
            $lti_tool_session['return_name'] = 'Return to VLE';
            if (!empty($this->platform->name)) {
                $lti_tool_session['return_name'] = 'Return to ' . $this->platform->name;
            }
        }
        if (!empty($this->messageParameters['custom_tool_name'])) {
            $lti_tool_session['tool_name'] = $this->messageParameters['custom_tool_name'];
        }
    }

    protected function get_user_login()
    {
        if (empty($this->context)) {
            $source = $this->platform;
        } else {
            $source = $this->context;
        }
        // Get what we are using as the username (unique_id-consumer_key, e.g. _21_1-stir.ac.uk)
        $user_login = lti_tool_get_user_login($this->platform->getKey(), $this->userResult, $source);
        // Apply the function pre_user_login before saving to the DB.
        $user_login = apply_filters('pre_user_login', $user_login);

        if (empty($user_login)) {
            $this->ok = false;
            $this->reason = 'Unable to generate a WordPress user_login';
        }

        return $user_login;
    }

    protected function init_user($user_login)
    {
        // Check if this username, $user_login, is already defined
        $user = get_user_by('login', $user_login);

        if (empty($user)) {
            // Create username if user provisioning is on
            $date = current_time('Y-m-d H:i:s');
            $user_data = array(
                'user_login' => $user_login,
                'user_pass' => wp_generate_password(),
                'first_name' => $this->userResult->firstname,
                'last_name' => $this->userResult->lastname,
                'display_name' => trim("{$this->userResult->firstname} {$this->userResult->lastname}"),
                'user_registered' => $date
            );
            if (!empty($this->userResult->getResourceLink()) && (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey()))) {
                $user_data['user_email'] = $this->userResult->email;
            }
            $result = wp_insert_user($user_data);
        } elseif (($user->first_name !== $this->userResult->firstname) || ($user->last_name = $this->userResult->lastname) ||
            (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey()) && ($user->user_email !== $this->userResult->email))) {
            // If user exists, simply save the current details if changed
            $user->first_name = $this->userResult->firstname;
            $user->last_name = $this->userResult->lastname;
            $user->display_name = trim("{$this->userResult->firstname} {$this->userResult->lastname}");
            if (!empty($this->userResult->getResourceLink()) && (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey()))) {
                $user->user_email = $this->userResult->email;
            }
            $result = wp_update_user($user);
        }
        // Handle any errors by capturing and returning to the platform
        if (is_wp_error($result)) {
            $this->reason = $result->get_error_message();
            $this->ok = false;
        } elseif (empty($user)) {
            // Get the new users details
            $user = get_user_by('login', $user_login);
        }

        // Save LTI user ID
        if ($this->ok) {
            update_user_meta($user->ID, 'lti_tool_platform_key', $this->platform->getKey());
            update_user_meta($user->ID, 'lti_tool_user_id', $this->userResult->ltiUserId);
        }

        return $user;
    }

    protected function get_site($user_id)
    {
        global $lti_tool_session;

        $blog_id = null;

        // set up some useful variables
        $key = !empty($this->resourceLink) ? $this->resourceLink->getKey() : '';
        $context_id = !empty($this->context) ? $this->context->getId() : '';
        $resource_id = !empty($this->resourceLink) ? $this->resourceLink->getId() : '';

        if (is_multisite()) {  // Create blog
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
                $this->reason = __('No Blog has been created as the name contains non-alphanumeric: (_a-zA-Z0-9-) allowed',
                    'lti-tool');
                $this->ok = false;
            } else {
                // Get any folder(s) that WordPress might be living in
                $wppath = parse_url(get_option('siteurl'), PHP_URL_PATH);
                $path = $wppath . '/' . trailingslashit($path);

                // Get the id of the blog, if exists
                $blog_id = domain_exists(DOMAIN_CURRENT_SITE, $path, 1);
                // If Blog does not exist and this is a member of staff and blog provisioning is on, create blog
                if (!$blog_id && ($this->userResult->isStaff() || $this->userResult->isAdmin())) {
                    $blog_id = wpmu_create_blog(DOMAIN_CURRENT_SITE, $path, $this->resourceLink->title, $user_id, '', '1');
                    wp_installing(false);
                    update_blog_option($blog_id, 'blogdescription', __('Provisioned by LTI', 'lti-tool'));
                }

                // Blog will exist by this point unless this user is student/no role.
                if (!$blog_id) {
                    $this->reason = __('No blog exists for this connection', 'lti-tool');
                    $this->ok = false;
                } else {
                    // Update/create blog name
                    update_blog_option($blog_id, 'blogname', $this->resourceLink->title);

                    // Users added via this route should only have access to this
                    // (path) site. Remove from the default blog.
                    remove_user_from_blog($user_id, 1);
                }
            }
        } else {
            $blog_id = get_current_blog_id();
        }

        if ($this->ok) {
            // As this is an LTI provisioned Blog we store the consumer key and
            // context id as options with the session meaning we can access elsewhere
            // in the code.
            // Store lti key & context id in $lti_tool_session variables
            $lti_tool_session['key'] = $key;
            $lti_tool_session['contextid'] = $context_id;
            $lti_tool_session['resourceid'] = $resource_id;
        }

        return $blog_id;
    }

    protected function login_user($blog_id, $user, $user_login, $options)
    {
        global $lti_tool_session;

        // Login the user
        wp_set_current_user($user->ID, $user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user_login, $user);

        if (is_multisite()) {
            // Switch to blog
            switch_to_blog($blog_id);
            $user = wp_get_current_user();

            // Note this is an LTI provisioned Blog.
            add_option('lti_tool_site', true);
        }

        $user_type = lti_tool_user_type($this->userResult);
        $role = lti_tool_default_role($user_type, $options, $this->platform);

        // Add user to blog and set role
        if (!is_user_member_of_blog($user->ID, $blog_id)) {
            add_user_to_blog($blog_id, $user->ID, $role);
        }

        // If users role in platform has changed (e.g. staff -> student),
        // then their role in the blog should change
        $user->set_role($role);

        // Store the key/context in case we need to sync shares --- this ensures we return
        // to the correct platform and not the primary platform
        if (!empty($this->userResult->getResourceLink())) {
            $lti_tool_session['userkey'] = $this->userResult->getResourceLink()->getKey();
            $lti_tool_session['userresourcelink'] = $this->userResult->getResourceLink()->getId();
        }
    }

    protected function set_redirect($options)
    {
        // Return URL for re-direction by Tool Provider class
        $homepage = apply_filters('lti_tool_homepage', $options['homepage']);
        if (!empty($homepage)) {
            $this->redirectUrl = get_option('siteurl') . '/' . $homepage;
        } else {
            $this->redirectUrl = get_bloginfo('url');
        }
    }

    private static function getGUID($scope)
    {
        return "WP{$scope}-" . strtoupper(Util::getRandomString(6));
    }

}

function lti_tool_registration_header()
{
    wp_enqueue_style('lti-tool-register-style', plugins_url('../css/register.css', __FILE__));
    wp_enqueue_script('lti-tool-register_js', plugins_url('../js/registerjs.php', __FILE__), array('jquery'));
}
