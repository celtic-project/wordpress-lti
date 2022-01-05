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
use ceLTIc\LTI\Tool;

/* -------------------------------------------------------------------
 * lti_add_platform displays the page content for the custom menu
 * ----------------------------------------------------------------- */

function lti_add_platform()
{
    global $lti_db_connector;

    $options = lti_get_options();

    $editmode = isset($_REQUEST['action']) && ($_REQUEST['action'] == 'edit');
    if ($editmode) {
        $verb = 'Update';
    } else {
        $verb = 'Add';
    }
    ?>
    <script src="<?php echo plugins_url() . '/lti/js/GenKey.js' ?>" language="javascript" type="text/javascript" >
    </script>
    <div id="form" class="wrap">
      <h1 class="wp-heading-inline"><?php echo $verb . __(' Platform', 'lti-text'); ?></h1>
      <p><?php echo $verb . __(' a platform connecting to this server.', 'lti-text'); ?></p>

      <form id="addlti" name="addlti" method="post"

            <?php if ($editmode) { ?>
                action="<?php echo get_admin_url() ?>admin.php?action=lti_addplatform&edit=true"
                onsubmit="return verify()">
              <?php } else { ?>
            action=""
            onsubmit="return createPlatform('<?php echo get_admin_url() ?>admin.php?action=');">
            <?php
        }

        wp_nonce_field('add_lti', '_wpnonce_add_lti');

        $button_text = __("{$verb} Platform", 'lti-text');

        if ($editmode) {
            $platform = Platform::fromConsumerKey($_REQUEST['lti'], $lti_db_connector);
        } else {
            $platform = new Platform($lti_db_connector);
        }
        $now = time();
        $enable_from_example = date('Y-m-d', $now + (24 * 60 * 60));
        $enable_until_example = date('Y-m-d', $now + (7 * (24 * 60 * 60)));
        ?>

        <p class="submit">
          <input id="addltisub_top" class="button-primary" type="submit" value="<?php echo $button_text; ?>" name="addlti_top">
        </p>

        <h3>General Details</h3>

        <table class="form-table">
          <tbody>
            <tr class="form-field form-required">
              <th scope="row">
                <label for="lti_name" id="lti_name_text">
                  <?php _e('Name', 'lti-text'); ?>
                  <span id="req1" class="description"><?php _e('(required)', 'lti-text'); ?></span>
                </label>
              </th>
              <td>
                <input id="lti_name" type="text" aria-required="true" value="<?php echo esc_attr($platform->name); ?>" name="lti_name" class="regular-text">
              </td>
            </tr>

            <?php if ($editmode) { ?>
                <tr class="form-field form-required">
                  <th scope="row">
                    <label for="lti_key" id="lti_key_text">
                      <?php _e('Key', 'lti-text'); ?>
                      <span id="req2" class="description"><?php _e('(required)', 'lti-text'); ?></span>
                    </label>
                  </th>
                  <td>
                    <?php echo esc_attr($platform->getKey()); ?>&nbsp;<span class="description">(Consumer keys cannot be changed)</span>
                    <input id="lti_key" type="hidden" aria-required="true" value="<?php echo esc_attr($platform->getKey()); ?>" name="lti_key">
                  </td>
                </tr>
                <tr class="form-field form-required">
                  <th scope="row">
                    <label for="lti_secret" id="lti_secret_text">
                      <?php _e('Secret', 'lti-text'); ?>
                      <span id="req3" class="description"><?php _e('(required)', 'lti-text'); ?></span>
                    </label>
                  </th>
                  <td>
                    <input id="lti_secret" type="text" aria-required="true" value="<?php echo esc_attr($platform->secret); ?>" name="lti_secret" class="regular-text">
                  </td>
                </tr>
            <?php } ?>
            <tr>
              <th scope="row">
                <?php _e('Protected', 'lti-text'); ?>
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text">
                    <span><?php _e('Protected', 'lti-text') ?></span>
                  </legend>
                  <label for="lti_protected">
                    <input name="lti_protected" type="checkbox" id="lti_protected" value="true" <?php
                    checked(true, $platform->protected);
                    ?> />
                           <?php
                           _e('Restrict launch requests to the same tool consumer GUID parameter', 'lti-text');
                           ?>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <?php _e('Enabled', 'lti-text'); ?>
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text">
                    <span><?php _e('Enabled', 'lti-text') ?></span>
                  </legend>
                  <label for="lti_enabled">
                    <input name="lti_enabled" type="checkbox" id="lti_enabled" value="true" <?php checked(true, $platform->enabled); ?> />
                    <?php _e('Accept launch requests for this platform', 'lti-text'); ?>
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
                <label for="lti_enable_form">
                  <?php _e("Enable from (e.g. $enable_from_example 12:34)", 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_enable_from" type="small-text" aria-required="true" value="<?php
                if (isset($from) && $from != "") {
                    echo date('Y-m-d H:i', (int) $from);
                }
                ?>" name="lti_enable_from">
              </td>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_enable_until">
                  <?php _e("Enable until (e.g. {$enable_until_example} 12:34)", 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_enable_until" type="small-text" aria-required="true" value="<?php
                if (isset($until) && $until != "") {
                    echo date('Y-m-d H:i', (int) $until);
                }
                ?>" name="lti_enable_until">
              </td>
            <tr>
              <?php if ($editmode) { ?>
                <tr>
                  <th scope="row">
                    <?php _e('Username format', 'lti-text') ?>
                  </th>
                  <td>
                    <?php
                    switch (lti_get_scope($platform->getKey())) {
                        case Tool::ID_SCOPE_RESOURCE:
                            _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text');
                            break;
                        case Tool::ID_SCOPE_CONTEXT:
                            _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text');
                            break;
                        case Tool::ID_SCOPE_GLOBAL:
                            _e('Platform: Prefix the ID with the consumer key', 'lti-text');
                            break;
                        case Tool::ID_SCOPE_ID_ONLY:
                            _e('Global: Use ID value only', 'lti-text');
                            break;
                    }
                    ?>
                  </td>
              <?php } else { ?>
                  <th scope="row">
                    <?php _e('Username format', 'lti-text') ?>
                  </th>
                  <td>
                    <fieldset>
                      <legend class="screen-reader-text">
                        <span><?php _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text') ?></span>
                      </legend>
                      <label for="lti_scope3">
                        <input name="lti_scope" type="radio" id="lti_scope3" value="3" <?php checked('3', $options['scope']); ?> />
                        <?php
                        _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text');
                        ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text') ?></span>
                      </legend>
                      <label for="lti_scope2">
                        <input name="lti_scope" type="radio" id="lti_scope2" value="2" <?php checked('2', $options['scope']); ?> />
                        <?php _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text'); ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php _e('Platform: Prefix an ID with the consumer key', 'lti-text') ?></span>
                      </legend>
                      <label for="lti_scope1">
                        <input name="lti_scope" type="radio" id="lti_scope1" value="1" <?php checked('1', $options['scope']); ?> />
                        <?php _e('Platform: Prefix the ID with the consumer key', 'lti-text'); ?>
                      </label><br />
                      <legend class="screen-reader-text">
                        <span><?php _e('Global: Use ID value only', 'lti-text') ?></span>
                      </legend>
                      <label for="lti_scope0">
                        <input name="lti_scope" type="radio" id="lti_scope0" value="0" <?php checked('0', $options['scope']); ?> />
                        <?php _e('Global: Use ID value only', 'lti-text'); ?>
                      </label>
                    </fieldset>
                  </td>
                </tr>
            <?php } ?>
            <tr>
              <th scope="row">
                <?php _e('Debug mode?', 'lti-text'); ?>
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text">
                    <span><?php _e('Debug mode?', 'lti-text') ?></span>
                  </legend>
                  <label for="lti_debug">
                    <input name="lti_debug" type="checkbox" id="lti_debug" value="true" <?php checked(true, $platform->debugMode); ?> />
                    <?php _e('Enable debug-level logging for this platform?', 'lti-text'); ?>
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
                <label for="lti_platformid" id="lti_platformid_text">
                  <?php _e('Platform ID', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_platformid" type="text" aria-required="true" value="<?php echo esc_attr($platform->platformId); ?>" name="lti_platformid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_clientid" id="lti_clientid_text">
                  <?php _e('Client ID', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_clientid" type="text" aria-required="true" value="<?php echo esc_attr($platform->clientId); ?>" name="lti_clientid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_deploymentid" id="lti_deploymentid_text">
                  <?php _e('Deployment ID', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_deploymentid" type="text" aria-required="true" value="<?php echo esc_attr($platform->deploymentId); ?>" name="lti_deploymentid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_authorizationserverid" id="lti_authorizationserverid_text">
                  <?php _e('Authorization server ID', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_authorizationserverid" type="text" aria-required="true" value="<?php echo esc_attr($platform->authorizationServerId); ?>" name="lti_authorizationserverid" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_authenticationurl" id="lti_authenticationurl_text">
                  <?php _e('Authentication request URL', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_authenticationurl" type="text" aria-required="true" value="<?php echo esc_attr($platform->authenticationUrl); ?>" name="lti_authenticationurl" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_accesstokenurl" id="lti_accesstokenurl_text">
                  <?php _e('Access token URL', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_accesstokenurl" type="text" aria-required="true" value="<?php echo esc_attr($platform->accessTokenUrl); ?>" name="lti_accesstokenurl" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_jku" id="lti_jku_text">
                  <?php _e('Public keyset URL', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <input id="lti_jku" type="text" aria-required="true" value="<?php echo esc_attr($platform->jku); ?>" name="lti_jku" class="regular-text">
              </td>
            </tr>
            <tr class="form-field">
              <th scope="row">
                <label for="lti_rsakey" id="lti_rsakey_text">
                  <?php _e('Public key', 'lti-text'); ?>
                </label>
              </th>
              <td>
                <textarea id="lti_rsakey" type="text" aria-required="true" name="lti_rsakey" class="regular-text"><?php echo esc_attr($platform->rsaKey); ?></textarea>
                <p class="description">The public key may be specified in PEM format or in JSON (JWKS).  This may be omitted if a public keyset URL is specified.</p>
              </td>
            </tr>
          </tbody>
        </table>

        <p class="submit">
          <input id="addltisub" class="button-primary" type="submit" value="<?php echo $button_text; ?>" name="addlti">
        </p>
      </form>
    </div>

    <div id="keySecret" style="display:none">
      <h2><?php _e('Details for Platform: ', 'lti-text'); ?><span id="lti_title" style="font-weight: bold;"></span></h2>
      <h3><?php _e('LTI 1.0/1.1/1.2 Configuration: ', 'lti-text'); ?><span id="lti_title" style="font-weight: bold;"></span></h3>
      <table>
        <tr><td><?php echo __('Launch URL: ', 'lti-text') . '<span style="font-weight: bold;">' . get_option('siteurl') . '/?lti</span>'; ?></td></tr>
        <tr><td><?php _e('Key: ', 'lti-text'); ?><span id="key" style="font-weight: bold;"></span></td></tr>
        <tr><td><?php _e('Secret: ', 'lti-text'); ?><span id="secret" style="font-weight: bold;"></span></td></tr>
        <tr><td><?php echo __('Canvas configuration URL: ', 'lti-text') . '<span style="font-weight: bold;">' . get_option('siteurl') . '/?lti&configure</span>'; ?></td></tr>
      </table>
      <form action="<?php echo get_option('siteurl') . '/?lti&xml'; ?>" method="post" name="download" id="download">
        <input id="ltikey" type="hidden" value="" name="key" />
        <p class="submit">
          <input id="xml" class="button-primary" type="submit" value="<?php _e('Download IMS XML', 'lti-text') ?>" name="xml" />
        </p>
      </form>

      <h3><?php _e('LTI 1.3 Configuration: ', 'lti-text'); ?></h3>
      <table>
        <tr><td><?php echo __('Launch URL, Initiate Login URL, Redirection URI: ', 'lti-text') . '<span style="font-weight: bold;">' . get_option('siteurl') . '/?lti</span>'; ?></td></tr>
        <tr><td><?php echo __('Public Keyset URL: ', 'lti-text') . '<span style="font-weight: bold;">' . get_option('siteurl') . '/?lti&keys</span>'; ?></td></tr>
        <tr><td><?php echo __('Canvas configuration URL: ', 'lti-text') . '<span style="font-weight: bold;">' . get_option('siteurl') . '/?lti&configure=json</span>'; ?></td></tr>
      </table>
    </div>
    <?php
}
?>