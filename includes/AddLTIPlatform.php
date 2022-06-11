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

/* -------------------------------------------------------------------
 * lti_tool_add_platform displays the page content for the custom menu
 * ----------------------------------------------------------------- */

function lti_tool_add_platform()
{
    global $lti_tool_data_connector;

    $options = lti_tool_get_options();

    $editmode = isset($_REQUEST['action']) && (sanitize_text_field($_REQUEST['action']) === 'edit');
    if ($editmode) {
        $verb = 'Update';
        $action = get_admin_url() . 'admin.php?action=lti_tool_addplatform&edit=true';
        $onsubmit = 'return lti_tool_verify()';
        $platform = Platform::fromConsumerKey(sanitize_text_field($_REQUEST['lti']), $lti_tool_data_connector);
    } else {
        $verb = 'Add';
        $action = '';
        $onsubmit = 'return lti_tool_create_platform(\'' . esc_url(get_admin_url() . 'admin.php?action') . '=\')';
        $platform = new Platform($lti_tool_data_connector);
    }

    $button_text = esc_attr("{$verb} LTI Platform", 'lti-tool');

    $now = time();
    $enable_from_example = date('Y-m-d', $now + (24 * 60 * 60));
    $enable_until_example = date('Y-m-d', $now + (7 * (24 * 60 * 60)));

    $add_html = apply_filters('lti_tool_config_platform', array(), $platform);
    if (!is_array($add_html)) {
        $add_html = array();
    }

    $here = function($value) {
        return $value;
    };
    $escape = function($value) {
        return esc_html($value, 'lti-tool');
    };
    $escapeurl = function($value) {
        return esc_url($value);
    };
    $attr = function($value) {
        return esc_attr($value);
    };
    $checked = function($check, $current = true) {
        return checked($check, $current, false);
    };
    $datetime = function($time) {
        if (!empty($time)) {
            return date('Y-m-d H:i', (int) $time);
        } else {
            return '';
        }
    };

    $html = <<< EOD
    <div id="lti_tool_form" class="wrap">
      <h1 class="wp-heading-inline">{$escape("{$verb} LTI Platform")}</h1>
      <p>{$escape("{$verb} a platform connecting to this server.")}</p>

      <p style="display: none; color: red" id="lti_tool_error">
        <strong>Please complete all the required fields (marked in red).</strong>
      </p>

      <form id="lti_tool_addlti" name="lti_tool_addlti" method="post" action="{$action}" onsubmit="{$onsubmit}">

EOD;
    $html .= wp_nonce_field('add_lti_tool', '_wpnonce_add_lti_tool', true, false);

    $html .= <<< EOD

    <p class="submit">
      <input id="lti_tool_addltisub_top" class="button-primary" type="submit" value="{$attr($button_text)}" name="lti_tool_addlti_top">
    </p>

    <h3>{$escape('General')}</h3>

    <table class="form-table">
      <tbody>
        <tr class="form-field form-required">
          <th scope="row">
            <label for="lti_tool_name" id="lti_tool_name_text">
              {$escape('Name')}
              <span id="lti_tool_req1" class="description">{$escape('(required)')}</span>
            </label>
          </th>
          <td>
            <input id="lti_tool_name" type="text" aria-required="true" value="{$attr($platform->name)}" name="lti_tool_name" class="regular-text">
          </td>
        </tr>

EOD;
    if ($editmode) {
        $html .= <<< EOD
        <tr class="form-field form-required">
          <th scope="row">
            <label for="lti_tool_key" id="lti_tool_key_text">
              {$escape('Key')}
              <span id="lti_tool_req2" class="description">{$escape('(required)')}</span>
            </label>
          </th>
          <td>
            {$here($platform->getKey())}&nbsp;<span class="description">{$escape('(Consumer keys cannot be changed)')}</span>
            <input id="lti_tool_key" type="hidden" aria-required="true" value="{$attr($platform->getKey())}" name="lti_tool_key">
          </td>
        </tr>
        <tr class="form-field form-required">
          <th scope="row">
            <label for="lti_tool_secret" id="lti_tool_secret_text">
              {$escape('Secret')}
              <span id="lti_tool_req3" class="description">{$escape('(required)')}</span>
            </label>
          </th>
          <td>
            <input id="lti_tool_secret" type="text" aria-required="true" value="{$attr($platform->secret)}" name="lti_tool_secret" class="regular-text">
          </td>
        </tr>

EOD;
    }
    $html .= <<< EOD
        <tr>
          <th scope="row">
            {$escape('Protected?')}
          </th>
          <td>
            <fieldset>
              <legend class="screen-reader-text">
                <span>{$escape('Protected?')}</span>
              </legend>
              <label for="lti_tool_protected">
                <input name="lti_tool_protected" type="checkbox" id="lti_tool_protected" value="true"{$checked($platform->protected)} />
                {$escape('Restrict launch requests to the same tool consumer GUID parameter')}
              </label>
            </fieldset>
          </td>
        </tr>
        <tr>
          <th scope="row">
            {$escape('Enabled?')}
          </th>
          <td>
            <fieldset>
              <legend class="screen-reader-text">
                <span>{$escape('Enabled?')}</span>
              </legend>
              <label for="lti_tool_enabled">
                <input name="lti_tool_enabled" type="checkbox" id="lti_tool_enabled" value="true"{$checked($platform->enabled)} />
                    {$escape('Accept launch requests for this platform')}
              </label>
            </fieldset>
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_enable_form">
               {$escape("Enable from (e.g. {$enable_from_example} 12:34)")}
            </label>
          </th>
          <td>
            <input id="lti_tool_enable_from" type="small-text" aria-required="true" value="{$datetime($platform->enableFrom)}" name="lti_tool_enable_from">
          </td>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_enable_until">
              {$escape("Enable until (e.g. {$enable_until_example} 12:34)")}
            </label>
          </th>
          <td>
            <input id="lti_tool_enable_until" type="small-text" aria-required="true" value="{$datetime($platform->enableUntil)}" name="lti_tool_enable_until">
          </td>

EOD;
    if ($editmode) {
        $scope = '';
        switch (lti_tool_get_scope($platform->getKey())) {
            case Tool::ID_SCOPE_RESOURCE:
                $scope = 'Resource: Prefix the ID with the consumer key and resource link ID';
                break;
            case Tool::ID_SCOPE_CONTEXT:
                $scope = 'Context: Prefix the ID with the consumer key and context ID';
                break;
            case Tool::ID_SCOPE_GLOBAL:
                $scope = 'Platform: Prefix the ID with the consumer key';
                break;
            case Tool::ID_SCOPE_ID_ONLY:
                $scope = 'Global: Use ID value only';
                break;
            case LTI_Tool_WP_User::ID_SCOPE_USERNAME:
                $scope = 'Username: Use platform username only';
                break;
            case LTI_Tool_WP_User::ID_SCOPE_EMAIL:
                $scope = 'Email: Use email address only';
                break;
        }
        $html .= <<< EOD
        <tr>
          <th scope="row">
            {$escape('Username format')}
          </th>
          <td>
            {$escape($scope)}
          </td>

EOD;
    } else {
        $scopes = lti_tool_get_scopes();
        $html .= <<< EOD
        <tr class="form-field form-required">
          <th scope="row" id="lti_tool_scope_text">
            {$escape('Username format')} <span class="description">{$escape('(required)')}</span>
          </th>
          <td>
            <fieldset>

EOD;
        foreach ($scopes as $scope) {
            $html .= <<< EOD
        <legend class="screen-reader-text">
          <span>{$escape("{$scope['name']}: {$scope['description']}")}</span>
        </legend>
        <label for="lti_tool_scope{$scope['id']}">
          <input name="lti_tool_scope" type="radio" id="lti_tool_scope{$scope['id']}" value="{$scope['id']}"{$checked($options['scope'],
                    strval($scope['id']))} />
          <em>{$escape($scope['name'])}</em>: {$escape($scope['description'])}
        </label><br />

EOD;
        }
    }
    $html .= <<< EOD
    <tr>
      <th scope="row">
        {$escape('Debug mode?')}
      </th>
      <td>
        <fieldset>
          <legend class="screen-reader-text">
            <span>{$escape('Debug mode?')}</span>
          </legend>
          <label for="lti_tool_debug">
            <input name="lti_tool_debug" type="checkbox" id="lti_tool_debug" value="true"{$checked($platform->debugMode)} />
            {$escape('Enable debug-level logging for this platform?')}
          </label>
        </fieldset>
      </td>
    </tr>

EOD;
    if (isset($add_html['general'])) {
        $html .= $add_html['general'];
        unset($add_html['general']);
    }

    $html .= <<< EOD
      </tbody>
    </table>

    <h3>Roles</h3>

    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">Staff</th>
          <td>
            {$here(lti_tool_roles_select('staff', $platform->getSetting('__role_staff'), $options))}
          </td>
        </tr>
        <tr>
          <th scope="row">Student</th>
          <td>
            {$here(lti_tool_roles_select('student', $platform->getSetting('__role_student'), $options))}
          </td>
        </tr>
        <tr>
          <th scope="row">Other</th>
          <td>
            {$here(lti_tool_roles_select('other', $platform->getSetting('__role_other'), $options))}
          </td>
        </tr>

EOD;
    if (isset($add_html['roles'])) {
        $html .= $add_html['roles'];
        unset($add_html['roles']);
    }

    $html .= <<< EOD
      </tbody>
    </table>

    <h3>LTI 1.3 Configuration</h3>

    <table class="form-table">
      <tbody>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_platformid" id="lti_tool_platformid_text">
              {$escape('Platform ID')}
            </label>
          </th>
          <td>
            <input id="lti_tool_platformid" type="text" aria-required="true" value="{$attr($platform->platformId)}" name="lti_tool_platformid" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_clientid" id="lti_tool_clientid_text">
              {$escape('Client ID')}
            </label>
          </th>
          <td>
            <input id="lti_tool_clientid" type="text" aria-required="true" value="{$attr($platform->clientId)}" name="lti_tool_clientid" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_deploymentid" id="lti_tool_deploymentid_text">
              {$escape('Deployment ID')}
            </label>
          </th>
          <td>
            <input id="lti_tool_deploymentid" type="text" aria-required="true" value="{$attr($platform->deploymentId)}" name="lti_tool_deploymentid" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_authorizationserverid" id="lti_tool_authorizationserverid_text">
              {$escape('Authorization server ID', 'lti-tool')}
            </label>
          </th>
          <td>
            <input id="lti_tool_authorizationserverid" type="text" aria-required="true" value="{$attr($platform->authorizationServerId)}" name="lti_tool_authorizationserverid" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_authenticationurl" id="lti_tool_authenticationurl_text">
              {$escape('Authentication request URL')}
            </label>
          </th>
          <td>
            <input id="lti_tool_authenticationurl" type="text" aria-required="true" value="{$attr($platform->authenticationUrl)}" name="lti_tool_authenticationurl" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_accesstokenurl" id="lti_tool_accesstokenurl_text">
              {$escape('Access token URL', 'lti-tool')}
            </label>
          </th>
          <td>
            <input id="lti_tool_accesstokenurl" type="text" aria-required="true" value="{$attr($platform->accessTokenUrl)}" name="lti_tool_accesstokenurl" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_jku" id="lti_tool_jku_text">
              {$escape('Public keyset URL')}
            </label>
          </th>
          <td>
            <input id="lti_tool_jku" type="text" aria-required="true" value="{$attr($platform->jku)}" name="lti_tool_jku" class="regular-text">
          </td>
        </tr>
        <tr class="form-field">
          <th scope="row">
            <label for="lti_tool_rsakey" id="lti_tool_rsakey_text">
              {$escape('Public key')}
            </label>
          </th>
          <td>
            <textarea id="lti_tool_rsakey" aria-required="true" name="lti_tool_rsakey" rows="10" class="code">{$attr($platform->rsaKey)}</textarea>
            <p class="description">
              {$escape('The public key may be specified in PEM format or in JSON (JWKS).  This may be omitted if a public keyset URL is specified.')}
            </p>
          </td>
        </tr>

EOD;
    if (isset($add_html['ltiv1p3'])) {
        $html .= $add_html['ltiv1p3'];
        unset($add_html['ltiv1p3']);
    }

    $html .= <<< EOD
      </tbody>
    </table>

EOD;

    ksort($add_html);
    foreach ($add_html as $add) {
        $html .= $add;
    }

    $html .= <<< EOD

    <p class="submit">
      <input id="lti_tool_addltisub" class="button-primary" type="submit" value="{$attr($button_text)}" name="lti_tool_addlti">
    </p>
    </form>
    </div>

    <div id="lti_tool_keysecret" style="display:none">
      <h2>{$escape('Details for Platform: ')}<span id="lti_tool_title" style="font-weight: bold;"></span></h2>

      <h3>{$escape('LTI 1.0/1.1/1.2 Configuration: ')}<span id="lti_tool_title" style="font-weight: bold;"></span></h3>
      <table>
        <tr><td>{$escape('Launch URL: ')}</td><td><span style="font-weight: bold;">{$escapeurl(get_option('siteurl') . '/?lti-tool')}</span></td></tr>
        <tr><td>{$escape('Key: ')}</td><td><span id="lti_tool_key" style="font-weight: bold;"></span></td></tr>
        <tr><td>{$escape('Secret: ')}</td><td><span id="lti_tool_secret" style="font-weight: bold;"></span></td></tr>
        <tr><td>{$escape('Canvas configuration URL: ')}</td><td><span style="font-weight: bold;">{$escapeurl(get_option('siteurl') . '/?lti-tool&configure')}</span></td></tr>
      </table>
      <form action="{$escapeurl(get_option('siteurl') . '/?lti-tool&xml')}" method="post" name="lti_tool_download" id="lti_tool_download">
        <input id="lti_tool_download_key" type="hidden" value="" name="key" />
        <p class="submit">
          <input id="xml" class="button-primary" type="submit" value="{$escape('Download IMS XML')}" name="xml" />
        </p>
      </form>

      <h3>{$escape('LTI 1.3 Configuration: ')}</h3>
      <table>
        <tr><td>{$escape('Launch URL, Initiate Login URL, Redirection URI: ')}</td><td><span style="font-weight: bold;">{$escapeurl(get_option('siteurl') . '/?lti-tool')}</span></td></tr>
        <tr><td>{$escape('Public Keyset URL: ')}</td><td><span style="font-weight: bold;">{$escapeurl(get_option('siteurl') . '/?lti-tool&keys')}</span></td></tr>
        <tr><td>{$escape('Canvas configuration URL: ')}</td><td><span style="font-weight: bold;">{$escapeurl(get_option('siteurl') . '/?lti-tool&configure=json')}</span></td></tr>
      </table>
    </div>
EOD;

    echo $html;
}

function lti_tool_roles_select($role, $current, $options)
{
    $name = "role_{$role}";
    $roles = get_editable_roles();
    $html = <<< EOD
                <select name="lti_tool_{$name}" id="{$name}">
                  <option value="">&mdash; Use default ({$roles[$options[$name]]['name']}) &mdash;</option>

EOD;
    foreach ($roles as $key => $role) {
        $selected = ($key === $current) ? ' selected' : '';
        $html .= <<< EOD
                  <option value="{$key}"{$selected}>{$role['name']}</option>'

EOD;
    }
    $html .= <<< EOD
                </select>

EOD;

    return $html;
}
