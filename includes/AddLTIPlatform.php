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
    } else {
        $verb = 'Add';
    }
    ?>
    <div id="lti_tool_form" class="wrap">
      <h1 class="wp-heading-inline"><?php echo __("{$verb} LTI Platform", 'lti-tool'); ?></h1>
      <p><?php esc_html_e("{$verb} a platform connecting to this server.", 'lti-tool'); ?></p>

      <form id="lti_tool_addlti" name="lti_tool_addlti" method="post"

            <?php if ($editmode) { ?>
                action="<?php echo esc_url(get_admin_url() . 'admin.php?action=lti_tool_addplatform&edit=true'); ?>"
                onsubmit="return verify()">
              <?php } else { ?>
            action=""
            onsubmit="return lti_tool_create_platform('<?php echo esc_url(get_admin_url() . 'admin.php?action=') ?>');">
            <?php
        }

        wp_nonce_field('add_lti_tool', '_wpnonce_add_lti_tool');

        $button_text = __("{$verb} LTI Platform", 'lti-tool');

        if ($editmode) {
            $platform = Platform::fromConsumerKey(sanitize_text_field($_REQUEST['lti']), $lti_tool_data_connector);
        } else {
            $platform = new Platform($lti_tool_data_connector);
        }
        $now = time();
        $enable_from_example = date('Y-m-d', $now + (24 * 60 * 60));
        $enable_until_example = date('Y-m-d', $now + (7 * (24 * 60 * 60)));
        ?>

        <p class="submit">
          <input id="lti_tool_addltisub_top" class="button-primary" type="submit" value="<?php esc_attr_e($button_text); ?>" name="lti_tool_addlti_top">
        </p>

        <h3><?php esc_html_e('General Details', 'lti-tool'); ?></h3>

        <table class="form-table">
          <tbody>
            <tr class="form-field form-required">
              <th scope="row">
                <label for="lti_tool_name" id="lti_tool_name_text">
                  <?php esc_html_e('Name', 'lti-tool'); ?>
                  <span id="lti_tool_req1" class="description"><?php esc_html_e('(required)', 'lti-tool'); ?></span>
                </label>
              </th>
              <td>
                <input id="lti_tool_name" type="text" aria-required="true" value="<?php esc_attr_e($platform->name); ?>" name="lti_tool_name" class="regular-text">
              </td>
            </tr>

            <?php if ($editmode) { ?>
                <tr class="form-field form-required">
                  <th scope="row">
                    <label for="lti_tool_key" id="lti_tool_key_text">
                      <?php esc_html_e('Key', 'lti-tool'); ?>
                      <span id="lti_tool_req2" class="description"><?php esc_html_e('(required)', 'lti-tool'); ?></span>
                    </label>
                  </th>
                  <td>
                    <?php esc_html_e($platform->getKey()); ?>&nbsp;<span class="description"><?php
                    esc_html_e('(Consumer keys cannot be changed)', 'lti-tool');
                    ?></span>
                    <input id="lti_tool_key" type="hidden" aria-required="true" value="<?php esc_attr_e($platform->getKey()); ?>" name="lti_tool_key">
                  </td>
                </tr>
                <tr class="form-field form-required">
                  <th scope="row">
                    <label for="lti_tool_secret" id="lti_tool_secret_text">
                      <?php esc_html_e('Secret', 'lti-tool'); ?>
                      <span id="lti_tool_req3" class="description"><?php esc_html_e('(required)', 'lti-tool'); ?></span>
                    </label>
                  </th>
                  <td>
                    <input id="lti_tool_secret" type="text" aria-required="true" value="<?php esc_attr_e($platform->secret); ?>" name="lti_tool_secret" class="regular-text">
                  </td>
                </tr>
            <?php } ?>
            <tr>
              <th scope="row">
                <?php esc_html_e('Protected', 'lti-tool'); ?>
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text">
                    <span><?php esc_html_e('Protected', 'lti-tool') ?></span>
                  </legend>
                  <label for="lti_tool_protected">
                    <input name="lti_tool_protected" type="checkbox" id="lti_tool_protected" value="true" <?php
                    checked(true, $platform->protected);
                    ?> />
                           <?php
                           esc_html_e('Restrict launch requests to the same tool consumer GUID parameter', 'lti-tool');
                           ?>
                  </label>
                </fieldset>
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
                  <label for="lti_tool_enabled">
                    <input name="lti_tool_enabled" type="checkbox" id="lti_tool_enabled" value="true" <?php
                    checked(true, $platform->enabled);
                    ?> />
                           <?php esc_html_e('Accept launch requests for this platform', 'lti-tool'); ?>
                  </label>
                </fieldset>
              </td>
            </tr>
            <?php
            $from = $platform->enableFrom;
            if (is_null($from)) {
                $from = '';
            }

            $until = $platform->enableUntil;
            if (is_null($until)) {
                $until = '';
            }
            ?>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_enable_form">
                  <?php esc_html_e("Enable from (e.g. {$enable_from_example} 12:34)"); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_enable_from" type="small-text" aria-required="true" value="<?php
                if (isset($from) && ($from != "")) {
                    esc_html_e(date('Y-m-d H:i', (int) $from));
                }
                ?>" name="lti_tool_enable_from">
              </td>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_enable_until">
                  <?php esc_html_e("Enable until (e.g. {$enable_until_example} 12:34)"); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_enable_until" type="small-text" aria-required="true" value="<?php
                if (isset($until) && $until != "") {
                    esc_html_e(date('Y-m-d H:i', (int) $until));
                }
                ?>" name="lti_tool_enable_until">
              </td>
            <tr>
              <?php if ($editmode) { ?>
                <tr>
                  <th scope="row">
                    <?php esc_html_e('Username format', 'lti-tool') ?>
                  </th>
                  <td>
                    <?php
                    switch (lti_tool_get_scope($platform->getKey())) {
                        case Tool::ID_SCOPE_RESOURCE:
                            esc_html_e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-tool');
                            break;
                        case Tool::ID_SCOPE_CONTEXT:
                            esc_html_e('Context: Prefix the ID with the consumer key and context ID', 'lti-tool');
                            break;
                        case Tool::ID_SCOPE_GLOBAL:
                            esc_html_e('Platform: Prefix the ID with the consumer key', 'lti-tool');
                            break;
                        case Tool::ID_SCOPE_ID_ONLY:
                            esc_html_e('Global: Use ID value only', 'lti-tool');
                            break;
                        case LTI_Tool_WP_User::ID_SCOPE_USERNAME:
                            esc_html_e('Username: Use platform username only', 'lti-tool');
                            break;
                        case LTI_Tool_WP_User::ID_SCOPE_EMAIL:
                            esc_html_e('Email: Use email address only', 'lti-tool');
                            break;
                    }
                    ?>
                  </td>
              <?php } else { ?>
                  <th scope="row">
                    <?php esc_html_e('Username format', 'lti-tool') ?>
                  </th>
                  <td>
                    <fieldset><?php if (is_multisite()) { ?>
                          <legend class="screen-reader-text">
                            <span><?php esc_html_e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-tool') ?></span>
                          </legend>
                          <label for="lti_tool_scope3">
                            <input name="lti_tool_scope" type="radio" id="lti_tool_scope3" value="3" <?php checked('3', $options['scope']); ?> />
                            <?php
                            esc_html_e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-tool');
                            ?>
                          </label><br />
                          <legend class="screen-reader-text">
                            <span><?php esc_html_e('Context: Prefix the ID with the consumer key and context ID', 'lti-tool') ?></span>
                          </legend>
                          <label for="lti_tool_scope2">
                            <input name="lti_tool_scope" type="radio" id="lti_tool_scope2" value="2" <?php checked('2', $options['scope']); ?> />
                            <?php
                            esc_html_e('Context: Prefix the ID with the consumer key and context ID', 'lti-tool');
                            ?>
                          </label><br /><?php } ?>
                      <legend class="screen-reader-text">
                        <span><?php esc_html_e('Platform: Prefix an ID with the consumer key', 'lti-tool') ?></span>
                      </legend>
                      <label for="lti_tool_scope1">
                        <input name="lti_tool_scope" type="radio" id="lti_tool_scope1" value="1" <?php checked('1', $options['scope']); ?> />
                        <?php esc_html_e('Platform: Prefix the ID with the consumer key', 'lti-tool'); ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php esc_html_e('Global: Use ID value only', 'lti-tool') ?></span>
                      </legend>
                      <label for="lti_tool_scope0">
                        <input name="lti_tool_scope" type="radio" id="lti_tool_scope0" value="0" <?php checked('0', $options['scope']); ?> />
                        <?php esc_html_e('Global: Use ID value only', 'lti-tool'); ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php esc_html_e('Email: Use email address only', 'lti-tool') ?></span>
                      </legend>
                      <label for="lti_tool_scopeu">
                        <input name="lti_tool_scope" type="radio" id="lti_tool_scopeU" value="U" <?php checked('U', $options['scope']); ?> />
                        <?php esc_html_e('Username: Use platform username only', 'lti-tool'); ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php esc_html_e('Email: Use email address only', 'lti-tool') ?></span>
                      </legend>
                      <label for="lti_tool_scopee">
                        <input name="lti_tool_scope" type="radio" id="lti_tool_scopeE" value="E" <?php checked('E', $options['scope']); ?> />
                        <?php esc_html_e('Email: Use email address only', 'lti-tool'); ?>
                      </label>
                    </fieldset>
                  </td>
                </tr>
            <?php } ?>
            <tr>
              <th scope="row">
                <?php esc_html_e('Debug mode?', 'lti-tool'); ?>
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text">
                    <span><?php esc_html_e('Debug mode?', 'lti-tool') ?></span>
                  </legend>
                  <label for="lti_tool_debug">
                    <input name="lti_tool_debug" type="checkbox" id="lti_tool_debug" value="true" <?php
                    checked(true, $platform->debugMode);
                    ?> />
                           <?php esc_html_e('Enable debug-level logging for this platform?', 'lti-tool'); ?>
                  </label>
                </fieldset>
              </td>
            </tr>
          </tbody>
        </table>

        <h3>LTI 1.3 Configuration</h3>

        <table class="form-table">
          <tbody>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_platformid" id="lti_tool_platformid_text">
                  <?php esc_html_e('Platform ID', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_platformid" type="text" aria-required="true" value="<?php esc_attr_e($platform->platformId); ?>" name="lti_tool_platformid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_clientid" id="lti_tool_clientid_text">
                  <?php esc_html_e('Client ID', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_clientid" type="text" aria-required="true" value="<?php esc_attr_e($platform->clientId); ?>" name="lti_tool_clientid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_deploymentid" id="lti_tool_deploymentid_text">
                  <?php esc_html_e('Deployment ID', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_deploymentid" type="text" aria-required="true" value="<?php esc_attr_e($platform->deploymentId); ?>" name="lti_tool_deploymentid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_authorizationserverid" id="lti_tool_authorizationserverid_text">
                  <?php esc_html_e('Authorization server ID', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_authorizationserverid" type="text" aria-required="true" value="<?php esc_attr_e($platform->authorizationServerId); ?>" name="lti_tool_authorizationserverid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_authenticationurl" id="lti_tool_authenticationurl_text">
                  <?php esc_html_e('Authentication request URL', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_authenticationurl" type="text" aria-required="true" value="<?php esc_attr_e($platform->authenticationUrl); ?>" name="lti_tool_authenticationurl" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_accesstokenurl" id="lti_tool_accesstokenurl_text">
                  <?php esc_html_e('Access token URL', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_accesstokenurl" type="text" aria-required="true" value="<?php esc_attr_e($platform->accessTokenUrl); ?>" name="lti_tool_accesstokenurl" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_jku" id="lti_tool_jku_text">
                  <?php esc_html_e('Public keyset URL', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <input id="lti_tool_jku" type="text" aria-required="true" value="<?php esc_attr_e($platform->jku); ?>" name="lti_tool_jku" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_tool_rsakey" id="lti_tool_rsakey_text">
                  <?php esc_html_e('Public key', 'lti-tool'); ?>
                </label>
              </th>
              <td>
                <textarea id="lti_tool_rsakey" aria-required="true" name="lti_tool_rsakey" rows="10" class="code"><?php esc_attr_e($platform->rsaKey); ?></textarea>
                <p class="description"><?php
                  esc_html_e('The public key may be specified in PEM format or in JSON (JWKS).  This may be omitted if a public keyset URL is specified.',
                      'lti-tool');
                  ?></p>
              </td>
            </tr>
          </tbody>
        </table>

        <p class="submit">
          <input id="lti_tool_addltisub" class="button-primary" type="submit" value="<?php esc_attr_e($button_text); ?>" name="lti_tool_addlti">
        </p>
      </form>
    </div>

    <div id="lti_tool_keysecret" style="display:none">
      <h2><?php esc_html_e('Details for Platform: ', 'lti-tool'); ?><span id="lti_tool_title" style="font-weight: bold;"></span></h2>
      <h3><?php esc_html_e('LTI 1.0/1.1/1.2 Configuration: ', 'lti-tool'); ?><span id="lti_tool_title" style="font-weight: bold;"></span></h3>
      <table>
        <tr><td><?php esc_html_e('Launch URL: ', 'lti-tool'); ?></td><td><span style="font-weight: bold;"><?php echo esc_url(get_option('siteurl') . '/?lti-tool'); ?></span></td></tr>
        <tr><td><?php esc_html_e('Key: ', 'lti-tool'); ?></td><td><span id="lti_tool_key" style="font-weight: bold;"></span></td></tr>
        <tr><td><?php esc_html_e('Secret: ', 'lti-tool'); ?></td><td><span id="lti_tool_secret" style="font-weight: bold;"></span></td></tr>
        <tr><td><?php esc_html_e('Canvas configuration URL: ', 'lti-tool'); ?></td><td><span style="font-weight: bold;"><?php echo esc_url(get_option('siteurl') . '/?lti-tool&configure'); ?></span></td></tr>
      </table>
      <form action="<?php echo get_option('siteurl'); ?>/?lti-tool&xml" method="post" name="lti_tool_download" id="lti_tool_download">
        <input id="lti_tool_download_key" type="hidden" value="" name="key" />
        <p class="submit">
          <input id="xml" class="button-primary" type="submit" value="<?php esc_html_e('Download IMS XML', 'lti-tool') ?>" name="xml" />
        </p>
      </form>

      <h3><?php esc_html_e('LTI 1.3 Configuration: ', 'lti-tool'); ?></h3>
      <table>
        <tr><td><?php esc_html_e('Launch URL, Initiate Login URL, Redirection URI: ', 'lti-tool'); ?></td><td><span style="font-weight: bold;"><?php echo esc_url(get_option('siteurl') . '/?lti-tool'); ?></span></td></tr>
        <tr><td><?php esc_html_e('Public Keyset URL: ', 'lti-tool'); ?></td><td><span style="font-weight: bold;"><?php echo esc_url(get_option('siteurl') . '/?lti-tool&keys'); ?></span></td></tr>
        <tr><td><?php esc_html_e('Canvas configuration URL: ', 'lti-tool'); ?></td><td><span style="font-weight: bold;"><?php echo esc_url(get_option('siteurl') . '/?lti-tool&configure=json'); ?></span></td></tr>
      </table>
    </div>
    <?php
}
