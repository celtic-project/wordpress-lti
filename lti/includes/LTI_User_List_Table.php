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

class LTI_User_List_Table extends WP_List_Table {

  var $site_id;
  var $is_site_users;
  var $users = array();

  function __construct() {
    $screen = get_current_screen();
    $this->is_site_users = 'site-users-network' == $screen->id;


    if ( $this->is_site_users )
      $this->site_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

      parent::__construct(array(
        'singular' => __('User', 'lti-text'),
        'plural'   => __('Users', 'lti-text')
        )
    );
  }

  function ajax_user_can() {
  if ( $this->is_site_users )
    return current_user_can( 'manage_sites' );
  else
    return current_user_can( 'list_users' );
  }

  function prepare_items($lti_users) {
    global $role, $usersearch;

    $this->users = $lti_users;

    $usersearch = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

    $role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

    $per_page = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
    $users_per_page = $this->get_items_per_page( $per_page );

    $paged = $this->get_pagenum();

    $args = array(
      'number' => $users_per_page,
      'offset' => ( $paged-1 ) * $users_per_page,
      'role' => $role,
      'search' => $usersearch,
      'fields' => 'all_with_meta'
    );

    if ( '' !== $args['search'] )
      $args['search'] = '*' . $args['search'] . '*';

    if ( $this->is_site_users )
      $args['blog_id'] = $this->site_id;

    if ( isset( $_REQUEST['orderby'] ) )
      $args['orderby'] = $_REQUEST['orderby'];

    if ( isset( $_REQUEST['order'] ) )
      $args['order'] = $_REQUEST['order'];

    // Query the user IDs for this page
    // $wp_user_search = new WP_User_Query( $args );
    $this->items = $this->users;

    $this->set_pagination_args( array(
      'total_items' => sizeof($this->users), //$wp_user_search->get_total(),
      'per_page' => $users_per_page,
    ) );

    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);
  }

  function no_items() {
    _e( 'No matching users were found.' );
  }

  function get_views() {
    global $wp_roles, $role;

    if ($this->is_site_users) {
      $url = 'site-users.php?id=' . $this->site_id;
      switch_to_blog( $this->site_id );
      $users_of_blog = count_users();
      restore_current_blog();
    } else {
      $url = 'users.php';
      $users_of_blog = count_users();
    }

    //$total_users = $users_of_blog['total_users'];
    $total_users       = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'all']));
    $total_provision   = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'provision']));
    $total_new_to_blog = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'new_to_blog']));
    $total_newadmins   = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'newadmins']));
    $total_changed     = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'changed']));
    $total_rchanged    = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'role_changed']));
    $total_remove      = sizeof(unserialize($_SESSION[LTI_SESSION_PREFIX . 'remove']));
    //$avail_roles =& $users_of_blog['avail_roles'];
    unset($users_of_blog);

    $current_role = false;
    //$class = empty($role) ? ' class="current"' : '';
    $role_links = array();

    /* Uncomment to see all members from consumer
    if ($total_user != 0) {
      $class = ($_REQUEST['action'] == 'all') ? ' class="current"' : '';
      $role_links['all']  = "<a href='users.php?page=lti_sync_enrolments&action=all'$class>" .
                            sprintf(_nx('Member from Consumer <span class="count">(%s)</span>',
                                        'All Members from Consumer <span class="count">(%s)</span>',
                                        $total_users,
                                        'users',
                                        'lti-text'),
                                    number_format_i18n($total_users)) .
                            '</a>';
    }
    */

    if ($total_provision != 0) $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
    /* Uncomment to see new wordpress & blog members
    if ($total_provision != 0) {
      $class = ($_REQUEST['action'] == 'provision') ? ' class="current"' : '';
      $role_links['provision'] = "<a href='users.php?page=lti_sync_enrolments&action=provision'$class>" .
                                 sprintf(_nx('New WordPress & Blog Member <span class="count">(%s)</span>',
                                             'New WordPress & Blog Members <span class="count">(%s)</span>',
                                              $total_provision,
                                              'users',
                                              'lti-text'),
                                         number_format_i18n($total_provision)) .
                                 '</a>';
    }
    */

    if ($total_new_to_blog != 0) $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
    /* Uncomment to see new blog members
    if ($total_new_to_blog != 0) {
      $class = ($_REQUEST['action'] == 'new_to_blog') ? ' class="current"' : '';
      $role_links['new_to_blog'] = "<a href='users.php?page=lti_sync_enrolments&action=new_to_blog'$class>" .
                                   sprintf(_nx('New Blog Member <span class="count">(%s)</span>',
                                               'New Blog Members <span class="count">(%s)</span>',
                                               $total_new_to_blog,
                                               'users',
                                               'lti-text'),
                                           number_format_i18n($total_new_to_blog)) .
                                 '</a>';
    }
    */

    if ($total_newadmins != 0) {
      $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
      $class = ($_REQUEST['action'] == 'newadmins') ? ' class="current"' : '';
      $role_links['newadmins'] = "<a href='users.php?page=lti_sync_enrolments&action=newadmins'$class>" .
                                 sprintf(_nx('New Administrator <span class="count">(%s)</span>',
                                             'New Administrators<span class="count">(%s)</span>',
                                             $total_newadmins,
                                             'users',
                                             'lti-text'),
                                         number_format_i18n($total_newadmins)) .
                                 '</a>';
    }

    if ($total_changed != 0) $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
    /* Uncomment to see name changes
    if ($total_changed != 0) {
      $class = ($_REQUEST['action'] == 'changed') ? ' class="current"' : '';
      $role_links['changed'] = "<a href='users.php?page=lti_sync_enrolments&action=changed'$class>" .
                               sprintf(_nx('Changed <span class="count">(%s)</span>',
                                           'Changes <span class="count">(%s)</span>',
                                           $total_changed,
                                           'users',
                                           'lti-text'),
                                       number_format_i18n($total_changed)) .
                               '</a>';
    }
    */

    if ($total_rchanged != 0) {
      $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
      $class = ($_REQUEST['action'] == 'rchanged') ? ' class="current"' : '';
      $role_links['rchanged'] = "<a href='users.php?page=lti_sync_enrolments&action=rchanged'$class>" .
                                sprintf(_nx('Role Changed <span class="count">(%s)</span>',
                                            'Role Changes <span class="count">(%s)</span>',
                                            $total_rchanged,
                                            'users',
                                            'lti-text'),
                                        number_format_i18n( $total_rchanged ) ) .
                                '</a>';
    }

    if ($total_remove != 0) {
      $_SESSION[LTI_SESSION_PREFIX . 'nochanges'] = 1;
      $class = ($_REQUEST['action'] == 'remove') ? ' class="current"' : '';
      $role_links['remove'] = "<a href='users.php?page=lti_sync_enrolments&action=remove'$class>" .
                              sprintf(_nx('Not present in VLE/LMS - Delete? <span class="count">(%s)</span>',
                                          'Not present in VLE/LMS - Delete? <span class="count">(%s)</span>',
                                          $total_remove,
                                          'users',
                                          'lti-text'),
                                      number_format_i18n( $total_remove ) ) .
                              '</a>';
    }

    if ($_SESSION[LTI_SESSION_PREFIX . 'nochanges'] == 0) {
      $role_links['none'] = '<h2>Up to date --- No changes</h2>';
    }
    return $role_links;
  }

  /**
   * Display the bulk actions dropdown.
   *
   * @since 3.1.0
   * @access public
   */
  function bulk_actions() {
    $screen = get_current_screen();

    if ( is_null( $this->_actions ) ) {
      $no_new_actions = $this->_actions = $this->get_bulk_actions();
      // This filter can currently only be used to remove actions.
      $this->_actions = apply_filters( 'bulk_actions-' . $screen->id, $this->_actions );
      $this->_actions = array_intersect_assoc( $this->_actions, $no_new_actions );
      $two = '';
    } else {
      $two = '2';
    }

    if ( empty( $this->_actions ) )
      return;

    echo "<select name='action$two'>\n";
    echo "<option value='-1' selected='selected'>" . __('Display Actions', 'lti-text' ) . "</option>\n";

    foreach ( $this->_actions as $name => $title ) {
      $class = 'edit' == $name ? ' class="hide-if-no-js"' : '';

      echo "\t<option value='$name'$class>$title</option>\n";
    }

    echo "</select>\n";

    submit_button( __('Show', 'lti-text'), 'button-secondary action', false, false, array( 'id' => "doaction$two" ) );
    echo "\n";
  }

  function get_bulk_actions() {
    $actions = array();

    return $actions;
  }

  function current_action() {
    if ( isset($_REQUEST['changeit']) && !empty($_REQUEST['new_role']) )
      return 'promote';

    return parent::current_action();
  }

  function get_columns() {
    $c = array(
      'cb'       => '<input type="checkbox" />',
      'username' => __('Username', 'column name', 'lti-text'),
      'name'     => __('Name', 'column name', 'lti-text'),
      'role'     => __('VLE/LMS Role', 'column name', 'lti-text'),
    );

    return $c;
  }

  function get_sortable_columns() {
    $c = array(
      'username' => 'login',
      'name'     => 'name',
      'role'     => 'role'
    );

    return $c;
  }

  function display_rows() {
    // Query the post counts for this page

    $style = '';
    foreach ($this->items as $lti_user) {
      $style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
      echo "\n\t", $this->single_row( $lti_user, $style);
    }
  }

  /**
   * Generate HTML for a single row on the users.php admin panel.
   *
   * @since 2.1.0
   *
   * @param object $user_object
   * @param string $style Optional. Attributes added to the TR element.  Must be sanitized.
   * @param string $role Key for the $wp_roles array.
   * @param int $numposts Optional. Post count to display for this user.  Defaults to zero, as in, a new user has made zero posts.
   * @return string
   */
  function single_row( $lti_user, $style = '') {
    global $wp_roles;

    $checkbox = '';
    // Check if the user for this row is editable
    if ( current_user_can( 'list_users' ) ) {

      $actions = array();

      // Set up the checkbox ( because the user is editable, otherwise its empty )
      $checkbox = "<input type='checkbox' name='users[]' id='user_{$lti_user->id}'  value='{$lti_user->id}' />";
    }

    $r = "<tr id='user-$lti_user->id'$style>";
    $r = "<tr $style>";

    foreach($this->get_columns() as $column_name => $key) {
      $class = "class=\"$column_name column-$column_name\"";

      $style = '';

      $attributes = "$class$style";

      switch ( $column_name ) {
        case 'cb':
          $r .= "<th scope='row' class='check-column'>$checkbox</th>";
          break;

       case 'username':
         $r .= "<td $attributes>" . $lti_user->username . "</td>";
         break;

       case 'name':
         $r .= "<td $attributes>$lti_user->fullname</td>";
         break;

       case 'role':
         $r .= "<td $attributes>$lti_user->roles</td>";
         break;

       default:
         $r .= "<td $attributes>";
         $r .= apply_filters( 'manage_users_custom_column', '', $column_name, $lti_user->id );
         $r .= "</td>";
      }
    }
    $r .= '</tr>';
    return $r;
  }
}
?>
