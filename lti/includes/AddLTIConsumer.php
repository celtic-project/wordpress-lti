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
 * lti_add_consumer displays the page content for the custom menu
 *-----------------------------------------------------------------*/
function lti_add_consumer() {

  global $lti_db_connector;

  $options = get_site_option('lti_choices');

  $editmode = ($_REQUEST['action'] == 'edit');
  if ($editmode) {
    $verb = 'Update';
  } else {
    $verb = 'Add';
  }
?>
  <script src="<?php echo plugins_url() . '/lti/js/GenKey.js' ?>" language="javascript" type="text/javascript" >
  </script>
  <div id='form'>
  <h2><?php echo $verb  . __(" Tool Consumer", 'lti-text'); ?>  </h2>
  <p><?php echo $verb . __(' a tool consumer connecting to this site.', 'lti-text'); ?></p>

  <form id="addlti" name="addlti" method="post"

  <?php if ($editmode) { ?>
    action="<?php echo plugins_url() . '/lti/includes/DoAddLTIConsumer.php?edit=true'; ?>"
    onsubmit="return verify()">
  <?php } else { ?>
    action=""
    onsubmit="return createConsumer('<?php echo plugins_url() ?>')">
  <?php }

  wp_nonce_field('add_lti', '_wpnonce_add_lti');

  $button_text = __("{$verb} Tool Consumer", 'lti-text');
  $type = "input";

  if ($editmode) {
    $consumer = new LTI_Tool_Consumer($_REQUEST['lti'], $lti_db_connector);
  }
  ?>

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
      <input id="lti_name" type="text" aria-required="true" value="<?php echo esc_attr($consumer->name); ?>" name="lti_name" class="regular-text">
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
      <?php echo esc_attr($consumer->getKey()); ?>&nbsp;<span class="description">(Consumer keys cannot be changed)</span>
      <input id="lti_key" type="hidden" aria-required="true" value="<?php echo esc_attr($consumer->getKey()); ?>" name="lti_key">
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
      <input id="lti_secret" type="text" aria-required="true" value="<?php echo esc_attr($consumer->secret); ?>" name="lti_secret" class="regular-text">
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
          <input name="lti_protected" type="checkbox" id="lti_protected" value="true" <?php checked(TRUE, $consumer->protected); ?> />
          <?php _e('Restrict launch requests to the same tool consumer GUID parameter', 'lti-text'); ?>
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
          <input name="lti_enabled" type="checkbox" id="lti_enabled" value="true" <?php checked(TRUE, $consumer->enabled); ?> />
          <?php _e('Accept launch requests for this tool consumer', 'lti-text'); ?>
        </label>
      </fieldset>
    </td>
  </tr>
<?php
  $from = $consumer->enable_from;
  if (is_null($from)) {
    $from = '';
  }

  $until = $consumer->enable_until;
  if (is_null($until)) {
    $until = '';
  }
?>
  <tr class="form-field">
    <th scope="row">
      <label for="lti_enable_form">
        <?php _e('Enable From (e.g. 2013-01-26 12:34)', 'lti-text'); ?>
      </label>
    </th>
    <td>
      <input id="lti_enable_from" type="text" aria-required="true" value="<?php if (isset($from) && $from != "")echo date('Y-m-d H:i', (int) $from); ?>" name="lti_enable_from">
    </td>
  <tr class="form-field">
    <th scope="row">
      <label for="lti_enable_until">
        <?php _e('Enable Until (e.g. 2014-01-26 12:34)', 'lti-text'); ?>
      </label>
    </th>
    <td>
      <input id="lti_enable_until" type="text" aria-required="true" value="<?php if (isset($until) && $until != "") echo date('Y-m-d H:i', (int) $until); ?>" name="lti_enable_until">
    </td>
  <tr>
  <?php if ($editmode) { ?>
  <tr>
    <th scope="row">
      <?php _e('Username format', 'lti-text') ?>
    </th>
    <td>
      <?php
        switch (lti_get_scope($consumer->getKey())) {
        case '3' :
          _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text');
          break;
        case '2' :
          _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text');
          break;
        case '1' :
          _e('Consumer: Prefix the ID with the consumer key', 'lti-text');
          break;
        case '0' :
          _e('Global: Use ID value only', 'lti-text');
          break;
      } ?>
   </td>
   <?php } else { ?>
   <th scope="row">
      <?php _e('Username Format', 'lti-text') ?>
    </th>
    <td>
      <fieldset>
        <legend class="screen-reader-text">
          <span><?php _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text') ?></span>
        </legend>
        <label for="lti_scope3">
          <input name="lti_scope" type="radio" id="lti_scope3" value="3" <?php checked('3', $options['scope']); ?> />
          <?php _e('Resource: Prefix the ID with the consumer key and resource link ID', 'lti-text'); ?>
        </label><br />
        <legend class="screen-reader-text">
          <span><?php _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text') ?></span>
        </legend>
        <label for="lti_scope2">
          <input name="lti_scope" type="radio" id="lti_scope2" value="2" <?php checked('2', $options['scope']); ?> />
          <?php _e('Context: Prefix the ID with the consumer key and context ID', 'lti-text'); ?>
        </label><br />
        <legend class="screen-reader-text">
          <span><?php _e('Consumer: Prefix an ID with the consumer key', 'lti-text') ?></span>
        </legend>
        <label for="lti_scope1">
          <input name="lti_scope" type="radio" id="lti_scope1" value="1" <?php checked('1', $options['scope']); ?> />
          <?php _e('Consumer: Prefix the ID with the consumer key', 'lti-text'); ?>
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
  </tbody>
  </table>

  <p class="submit">
  <input id="addltisub" class="button-primary" type="submit" value="<?php echo $button_text; ?>" name="addlti">
  </p>
  </form>
  </div>

  <div id="keySecret" style="display:none">
    <h2><?php _e('Details for LTI Tool Consumer: ', 'lti-text'); ?><span id="lti_title" style="font-weight:bold"></span></h2>
    <table>
        <tr><td><?php echo __('Launch URL: ', 'lti-text') . '<b>' . get_option('siteurl') . '/?lti</b>'; ?></td></tr>
        <tr><td><?php _e('Key: ', 'lti-text'); ?><span id="key" style="font-weight:bold"></span></td></tr>
        <tr><td><?php _e('Secret: ', 'lti-text'); ?><span id="secret" style="font-weight:bold"></span></td></tr>
    </table>
    <form action="<?php echo plugins_url() . '/lti/includes/XML.php'; ?>" name="download" id="download">
      <input id="ltikey" type="hidden" value="" name="lti" />
      <p class="submit">
        <input id="xml" class="button-primary" type="submit" value="<?php _e('Download XML', 'lti-text') ?>" name="xml" />
      </p>
    </form>
   </div>
<?php
}
?>