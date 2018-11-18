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

/*-------------------------------------------------------------------
 * This function is called when a successful LTI call is made. Is is
 * passed a class (tool_provider) that can access all the details of
 * the LTI call
 *
 * Parameters
 *  tool_provider - intance of of BasicLTI_Tool_Provider
 *-----------------------------------------------------------------*/
function lti_do_connect($tool_provider) {

  // If multisite support isn't in play, go home
  if (!is_multisite()) {
    $tool_provider->message = __('The LTI Plugin requires a Multisite installation of WordPress', 'lti-text');
    return FALSE;
  }

  // Clear any existing connections
  wp_logout();

  // Clear these before use
  $_SESSION[LTI_SESSION_PREFIX . 'return_url'] = '';
  $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = '';

  // Store return URL for later use, if present
  if (!empty($tool_provider->return_url)) {
    $_SESSION[LTI_SESSION_PREFIX . 'return_url'] = (strpos($tool_provider->return_url, '?') === FALSE) ? $tool_provider->return_url . '?' : $tool_provider->return_url . '&';
    $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = 'Return to VLE';
    if (!empty($tool_provider->consumer->name)) {
      $_SESSION[LTI_SESSION_PREFIX . 'return_name'] = 'Return to ' . $tool_provider->consumer->name;
    }
  }

  // Get what we are using as the username (unique_id-consumer_key, i.e. _21_1-stir.ac.uk)
  $options = get_site_option('lti_choices');
  $scope_userid = lti_get_scope($tool_provider->consumer->getKey());
  $user_login = $tool_provider->user->getID($scope_userid);
  // Sanitize username stripping out unsafe characters
  $user_login = sanitize_user($user_login);

  // Apply the function pre_user_login before saving to the DB.
  $user_login = apply_filters('pre_user_login', $user_login);

  // Check if this username, $user_login, is already defined
  $user = get_user_by('login', $user_login);

  if ($user) {
    // If user exists, simply save the current details
    $result = wp_insert_user(
      array(
            'ID' => $user->ID,
            'user_login' => $user_login,
            'user_nicename'=> $user_login,
            'first_name' => $tool_provider->user->firstname,
            'last_name' => $tool_provider->user->lastname,
            //'user_email'=> $tool_provider->user->email,
            //'user_url' => 'http://',
            'display_name' => $tool_provider->user->fullname
             )
    );
  } else {
    // Create username if user provisioning is on
    $result = wp_insert_user(
      array(
            'user_login' => $user_login,
            'user_pass' => wp_generate_password(),
            'user_nicename'=> $user_login,
            'first_name' => $tool_provider->user->firstname,
            'last_name' => $tool_provider->user->lastname,
            //'user_email'=> $tool_provider->user->email,
            //'user_url' => 'http://',
            'display_name' => $tool_provider->user->fullname
             )
    );
    // Handle any errors by capturing and returning to the consumer
    if (is_wp_error($result)) {
      $tool_provider->reason = $result->get_error_message();
      return FALSE;
    } else {
      // Get the new users details
      $user = get_user_by('login', $user_login);
    }
  }

  // Get user ID
  $user_id = $user->ID;

  // Staff or Learner
  $staff = FALSE;
  $learner = FALSE;
  $staff = $tool_provider->user->isStaff() || $tool_provider->user->isAdmin();
  $learner =  $tool_provider->user->isLearner();

  // set up some useful variables
  $key = $tool_provider->resource_link->getKey();
  $context_id = $tool_provider->context->getId();
  $resource_id = $tool_provider->resource_link->getId();

  // Create blog
  $use_context = FALSE;
  if (!empty($context_id)) $use_context = ($tool_provider->resource_link->getSetting('custom_use_context') == 'true') ? TRUE : FALSE;

  if ($use_context) {
    // Create new blog, if does not exist. Note this gives one blog per context, the consumer supplies a context_id
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
    $tool_provider->reason = __('No Blog has been created as the name contains non-alphanumeric: (_a-zA-Z0-9-) allowed', 'lti-text');
    return FALSE;
  }

  // Get any folder(s) that WordPress might be living in
  $wppath = parse_url(get_option('siteurl'), PHP_URL_PATH);
  $path = $wppath . '/' . trailingslashit($path);

  // Get the id of the blog, if exists
  $blog_id = domain_exists(DOMAIN_CURRENT_SITE, $path, 1);
  // If Blog does not exist and this is a member of staff and blog provisioning is on, create blog
  if (!$blog_id && $staff) {
    $blog_id = wpmu_create_blog(DOMAIN_CURRENT_SITE, $path , $tool_provider->resource_link->title, $user_id, '','1');
    update_blog_option($blog_id, 'blogdescription', __('Provisioned by LTI', 'lti-text'));
  }

  // Blog will exist by this point unless this user is student/no role.
  if (!$blog_id) {
    $tool_provider->reason = __('No Blog has been created for this context', 'lti-text');
    return FALSE;
  }

  // Update/create blog name
  update_blog_option($blog_id, 'blogname', $tool_provider->resource_link->title);

  $role = 'subscriber';
  if ($staff) $role = 'administrator';
  if ($learner) $role = 'author';

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
  do_action('wp_login', $user_login, $user);  // $ user was added as additional argument for PHP7.2 compatibility MA

  // Switch to blog
  switch_to_blog($blog_id);

  // Note this is an LTI provisioned Blog.
  add_option('ltisite', TRUE);

  // As this is an LTI provisioned Blog we store the consumer key and
  // context id as options with the session meaning we can access elsewhere
  // in the code.

  // Store lti key & context id in $_SESSION variables
  $_SESSION[LTI_SESSION_PREFIX . 'key'] = $key;
  $_SESSION[LTI_SESSION_PREFIX . 'resourceid'] = $resource_id;

  // Store the key/context in case we need to sync shares --- this ensures we return
  // to the correct consumer and not the primary consumer
  $_SESSION[LTI_SESSION_PREFIX . 'userkey'] = $tool_provider->user->getResourceLink()->getKey();
  $_SESSION[LTI_SESSION_PREFIX . 'userresourcelink'] = $tool_provider->user->getResourceLink()->getId();

  // If users role in consumer has changed (e.g. staff -> student),
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

  // Send login time to consumer if has outcomes service and can handle freetext
  $context = $tool_provider->resource_link;

  if ($context->hasOutcomesService()) {

    // Presently this is just a demo of the outcome services and updating the menu bar in WordPress
    $outcome = new LTI_Outcome($tool_provider->user->lti_result_sourcedid);
    $outcome->type = LTI_Resource_Link::EXT_TYPE_TEXT;
    $result = $context->doOutcomesService(LTI_Resource_Link::EXT_READ, $outcome);

    // If we have successfully read then update the user metadata
    if ($result) {
      update_user_meta($user_id, 'Last Login', $result);
    }

    $outcome->setValue(date('d-F-Y G:i', time()));
    $context->doOutcomesService(LTI_Resource_Link::EXT_WRITE, $outcome);
  }

  // Return URL for re-direction by Tool Provider class
  return get_bloginfo('url');
}

?>
