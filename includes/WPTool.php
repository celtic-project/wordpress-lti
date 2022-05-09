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

        $this->setParameterConstraint('resource_link_id', true, 40, array('basic-lti-launch-request'));
        $this->setParameterConstraint('user_id', true);

        $this->allowSharing = is_multisite();

        $this->signatureMethod = $options['lti13_signaturemethod'];
        $this->jku = $this->baseUrl . '?lti-tool&keys';
        $this->kid = $options['lti13_kid'];
        $this->rsaKey = $options['lti13_privatekey'];
        $this->requiredScopes = array(
            LTI\Service\Membership::$SCOPE,
            LTI\Service\Result::$SCOPE,
            LTI\Service\Score::$SCOPE,
            'https://purl.imsglobal.org/spec/lti-ext/scope/outcomes'
        );
    }

    protected function onLaunch()
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

        // Get what we are using as the username (unique_id-consumer_key, e.g. _21_1-stir.ac.uk)
        $user_login = lti_tool_get_user_login($this->platform->getKey(), $this->userResult);
        // Apply the function pre_user_login before saving to the DB.
        $user_login = apply_filters('pre_user_login', $user_login);

        // Check if this username, $user_login, is already defined
        $user = get_user_by('login', $user_login);

        if (empty($user)) {
            // Create username if user provisioning is on
            $date = current_time('Y-m-d h:i:s');
            $user_data = array(
                'user_login' => $user_login,
                'user_pass' => wp_generate_password(),
                'first_name' => $this->userResult->firstname,
                'last_name' => $this->userResult->lastname,
                'display_name' => trim("{$this->userResult->firstname} {$this->userResult->lastname}"),
                'user_registered' => $date
            );
            if (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey())) {
                $user_data['user_email'] = $this->userResult->email;
            }
            $result = wp_insert_user($user_data);
        } elseif (($user->first_name !== $this->userResult->firstname) || ($user->last_name = $this->userResult->lastname) ||
            (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey()) && ($user->user_email !== $this->userResult->email))) {
            // If user exists, simply save the current details if changed
            $user->first_name = $this->userResult->firstname;
            $user->last_name = $this->userResult->lastname;
            $user->display_name = trim("{$this->userResult->firstname} {$this->userResult->lastname}");
            if (lti_tool_do_save_email($this->userResult->getResourceLink()->getKey())) {
                $user->user_email = $this->userResult->email;
            }
            $result = wp_update_user($user);
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
        update_user_meta($user_id, 'lti_tool_platform_key', $this->platform->getKey());
        update_user_meta($user_id, 'lti_tool_user_id', $this->userResult->ltiUserId);

        // set up some useful variables
        $key = $this->resourceLink->getKey();
        $context_id = $this->context->getId();
        $resource_id = $this->resourceLink->getId();

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
                return;
            }

            // Get any folder(s) that WordPress might be living in
            $wppath = parse_url(get_option('siteurl'), PHP_URL_PATH);
            $path = $wppath . '/' . trailingslashit($path);

            // Get the id of the blog, if exists
            $blog_id = domain_exists(DOMAIN_CURRENT_SITE, $path, 1);
            // If Blog does not exist and this is a member of staff and blog provisioning is on, create blog
            if (!$blog_id && ($this->userResult->isStaff() || $this->userResult->isAdmin())) {
                $blog_id = wpmu_create_blog(DOMAIN_CURRENT_SITE, $path, $this->resourceLink->title, $user_id, '', '1');
                update_blog_option($blog_id, 'blogdescription', __('Provisioned by LTI', 'lti-tool'));
            }

            // Blog will exist by this point unless this user is student/no role.
            if (!$blog_id) {
                $this->reason = __('No Blog has been created for this context', 'lti-tool');
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

        $options = lti_tool_get_options();
        $role = lti_tool_user_role($this->userResult, $options);

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
            add_option('lti_tool_site', true);
        }

        // As this is an LTI provisioned Blog we store the consumer key and
        // context id as options with the session meaning we can access elsewhere
        // in the code.
        // Store lti key & context id in $lti_tool_session variables
        $lti_tool_session['key'] = $key;
        $lti_tool_session['resourceid'] = $resource_id;

        // Store the key/context in case we need to sync shares --- this ensures we return
        // to the correct platform and not the primary platform
        $lti_tool_session['userkey'] = $this->userResult->getResourceLink()->getKey();
        $lti_tool_session['userresourcelink'] = $this->userResult->getResourceLink()->getId();

        // If users role in platform has changed (e.g. staff -> student),
        // then their role in the blog should change
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
        $homepage = apply_filters('lti_tool_homepage', $options['homepage']);
        if (!empty($this->dataConnector->redirect)) {
            $this->redirectUrl = get_option('siteurl') . '/' . $this->dataConnector->redirect;
        } else if (!empty($homepage)) {
            $this->redirectUrl = get_option('siteurl') . '/' . $homepage;
        } else {
            $this->redirectUrl = get_bloginfo('url');
        }

        lti_tool_set_session();
    }

    protected function onRegistration()
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

      <p class="lti_tool_tbc">
        {$escape('Select how you would like users to be created within WordPress:')}
      </p>
      <div class="lti_tool_indent">
        <legend class="screen-reader-text">
          <span>{$escape('Resource-specific: Prefix the ID with the consumer key and resource link ID')}</span>
        </legend>

EOD;
        if (is_multisite()) {
            $checked3 = ($options['scope'] === strval(Tool::ID_SCOPE_RESOURCE)) ? ' checked' : '';
            $checked2 = ($options['scope'] === strval(Tool::ID_SCOPE_CONTEXT)) ? ' checked' : '';
            echo <<< EOD
        <label for="lti_tool_scope3">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope3" value="3"{$checked3} />
          <em>{$escape('Resource-specific:')}</em> {$escape('Prefix the ID with the consumer key and resource link ID')}
        </label><br />
        <legend class="screen-reader-text">
          <span>{$escape('Context-specific: Prefix the ID with the consumer key and context ID')}</span>
        </legend>
        <label for="lti_tool_scope2">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope2" value="2"{$checked2} />
          <em>{$escape('Context-specific:')}</em> {$escape('Prefix the ID with the consumer key and context ID')}
        </label><br />

EOD;
        }
        $checked1 = ($options['scope'] === strval(Tool::ID_SCOPE_GLOBAL)) ? ' checked' : '';
        $checked0 = ($options['scope'] === strval(Tool::ID_SCOPE_ID_ONLY)) ? ' checked' : '';
        $checkedU = ($options['scope'] === LTI_Tool_WP_User::ID_SCOPE_USERNAME) ? ' checked' : '';
        $checkedE = ($options['scope'] === LTI_Tool_WP_User::ID_SCOPE_EMAIL) ? ' checked' : '';
        echo <<< EOD
        <legend class="screen-reader-text">
          <span>{$escape('Platform-specific: Prefix an ID with the consumer key')}</span>
        </legend>
        <label for="lti_tool_scope1">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope1" value="1"{$checked1} />
          <em>{$escape('Platform-specific:')}</em> {$escape('Prefix the ID with the consumer key')}
        </label><br />
        <legend class="screen-reader-text">
          <span>{$escape('Global: Use ID value only')}</span>
        </legend>
        <label for="lti_tool_scope0">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope0" value="0"{$checked0} />
          <em>{$escape('Global:')}</em> {$escape('Use ID value only')}
        </label><br />
        <label for="lti_tool_scopeu">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scopeU" value="U"{$checkedU} />
          <em>{$escape('Username:')}</em> {$escape('Use platform username only')}
        </label><br />
        <label for="lti_tool_scopee">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scopeE" value="E"{$checkedE} />
          <em>{$escape('Email:')}</em> {$escape('Use email address only')}
        </label>
      </div>

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
                do {
                    $key = self::getGUID(sanitize_text_field($_POST['lti_scope']));
                    $platform = Platform::fromConsumerKey($key, $this->dataConnector);
                } while (!is_null($platform->created));
                $this->platform->setKey($key);
                $this->platform->secret = Util::getRandomString(32);
                $this->platform->name = 'Trial (' . date('Y-m-d H:i:s', $now) . ')';
                $this->platform->protected = true;
                if (!empty($options['registration_autoenable'])) {
                    $this->platform->enabled = true;
                }
                if (!empty($options['registration_enablefordays'])) {
                    $this->platform->enableFrom = $now;
                    $this->platform->enableUntil = $now + (intval($options['registration_enablefordays']) * 24 * 60 * 60);
                }
                $this->ok = $this->platform->save();
                if (!$this->ok) {
                    $this->reason = 'Sorry, an error occurred when saving the platform details, perhaps a configuration already exists for this platform.';
                }
            }
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
