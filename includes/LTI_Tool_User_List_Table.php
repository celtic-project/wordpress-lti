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

class LTI_Tool_User_List_Table extends WP_List_Table
{

    const REASON_NEW = 'New to WordPress';
    const REASON_ADD = 'New to this site';
    const REASON_CHANGE_NAME = 'Name change';
    const REASON_CHANGE_EMAIL = 'Email change';
    const REASON_CHANGE_ROLE = 'Role change';
    const REASON_CHANGE_ID = 'LTI User ID changed';
    const REASON_DELETE = 'No longer in platform';

    public $status;

    function __construct()
    {
        parent::__construct(array(
            'singular' => __('User', 'lti-tool'),
            'plural' => __('Users', 'lti-tool'),
            'ajax' => false
            )
        );
        $this->status = 'new';
    }

    public static function set_primary_column()
    {
        return 'name';
    }

    public static function define_columns()
    {
        $columns = array(
            'username' => __('Username', 'lti-tool'),
            'name' => __('Name', 'lti-tool')
        );
        if (lti_tool_do_save_email()) {
            $columns['email'] = __('Email', 'lti-tool');
        }
        $columns['role'] = __('Role', 'lti-tool');
        $columns['reasons'] = __('Reasons', 'lti-tool');

        return $columns;
    }

    protected function get_sortable_columns()
    {
        $columns = array(
            'username' => array('username', false),
            'name' => array('name', true)
        );
        if (lti_tool_do_save_email()) {
            $columns['email'] = array('email', false);
        }
        $columns['role'] = array('role', false);
        $columns['reasons'] = array('reasons', false);

        return $columns;
    }

    function prepare_items()
    {
        global $lti_tool_session;

        function usort_reorder($a, $b)
        {
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'name';
            $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'asc';
            if (is_array($a->$orderby)) {
                $result = strcmp(implode($a->$orderby), implode($b->$orderby));
            } else {
                $result = strcmp($a->$orderby, $b->$orderby);
            }
            return ($order === 'asc') ? $result : -$result;
        }

        $per_page = $this->get_items_per_page('users_per_page');
        $current_page = $this->get_pagenum();
        $this->items = $lti_tool_session['sync'][$this->status];
        $num_items = count($this->items);
        $num_pages = ceil($num_items / $per_page);
        if (!empty($this->items)) {
            usort($this->items, 'usort_reorder');
        }
        $this->items = array_slice($this->items, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $num_items,
            'total_pages' => $num_pages,
            'per_page' => $per_page,
        ));
    }

    function no_items()
    {
        _e('No users.', 'lti-tool');
    }

    function get_views()
    {
        global $lti_tool_session;

        $views = array();
        $num_new = count($lti_tool_session['sync']['new']);
        $num_add = count($lti_tool_session['sync']['add']);
        $num_change = count($lti_tool_session['sync']['change']);
        $num_delete = count($lti_tool_session['sync']['delete']);

        $class = ($this->status === 'new') ? $class = 'current' : '';
        $views['new'] = $this->get_edit_link(array('action' => 'new'),
            __('New', 'lti-tool') . " <span class=\"count\">({$num_new})</span>", $class);

        if (is_multisite()) {
            $class = ($this->status === 'add') ? $class = 'current' : '';
            $views['add'] = $this->get_edit_link(array('action' => 'add'),
                __('Add', 'lti-tool') . " <span class=\"count\">({$num_add})</span>", $class);
        }

        $class = ($this->status === 'change') ? $class = 'current' : '';
        $views['change'] = $this->get_edit_link(array('action' => 'change'),
            __('Changed', 'lti-tool') . " <span class=\"count\">({$num_change})</span>", $class);

        $class = ($this->status === 'delete') ? $class = 'current' : '';
        $views['delete'] = $this->get_edit_link(array('action' => 'delete'),
            __('Delete', 'lti-tool') . " <span class=\"count\">({$num_delete})</span>", $class);

        return $views;
    }

    public function get_columns()
    {
        return get_column_headers(get_current_screen());
    }

    public function column_username($item)
    {
        return esc_html($item->username);
    }

    public function column_name($item)
    {
        return esc_html($item->name);
    }

    public function column_email($item)
    {
        return esc_html($item->email);
    }

    public function column_role($item)
    {
        return esc_html($item->role);
    }

    public function column_reasons($item)
    {
        return __(implode('<br>', $item->reasons));
    }

    private function get_edit_link($args, $label, $class = '')
    {
        $url = add_query_arg($args, menu_page_url('lti_tool_sync_enrolments', false));
        $class_html = '';
        $aria_current = '';
        if (!empty($class)) {
            $class_html = ' class="' . esc_attr($class) . '"';
            if ($class === 'current') {
                $aria_current = ' aria-current="page"';
            }
        }

        return '<a href="' . esc_url($url) . "\"{$class_html}{$aria_current}>{$label}</a>";
    }

}
