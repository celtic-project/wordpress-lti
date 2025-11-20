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

use ceLTIc\LTI\Tool;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php');

class LTI_Tool_List_Table extends WP_List_Table
{

    private $per_page;

    function __construct($per_page)
    {
        $this->per_page = $per_page;

        // Set parent defaults
        parent::__construct
            (
            array(
                'singular' => __('LTI', 'lti-tool'), //singular name of the listed records
                'plural' => __('LTI', 'lti-tool'), //plural name of the listed records
                'ajax' => false      //does this table support ajax?
            )
        );
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
            case 'key':
            case 'pname':
            case 'avail':
            case 'debug':
            case 'last':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_name($item)
    {
        // Show name in bold if platform enabled
        $name = $item['name'];
        // Build row actions
        $actions = array();
        $actions['edit'] = sprintf('<a href="?page=%s&action=%s&lti=%s">' . __('Edit', 'lti-tool') . '</a>',
            'lti_tool_add_platform', 'edit', $item['key']);
        if (lti_tool_get_enabled_state($item['key'])) {
            // Show name in bold if platform enabled
            $name = '<strong>' . $item['name'] . '</strong>';
            $actions['disable'] = sprintf('<a href="?page=%s&action=%s&lti=%s">' . __('Disable', 'lti-tool') . '</a>',
                sanitize_text_field($_REQUEST['page']), 'disable', $item['key']);
        } else {
            $actions['enable'] = sprintf('<a href="?page=%s&action=%s&lti=%s">' . __('Enable', 'lti-tool') . '</a>',
                sanitize_text_field($_REQUEST['page']), 'enable', $item['key']);
        }
        $actions['delete'] = sprintf('<a href="?page=%s&action=%s&lti=%s" onclick="return confirm(\'Delete this platform?\');">' . __('Delete',
                'lti-tool') . '</a>', sanitize_text_field($_REQUEST['page']), 'delete', $item['key']);
        $actions['xml'] = sprintf('<a href="%s&key=%s">' . __('XML', 'lti-tool') . '</a>', get_option('siteurl') . '/?lti-tool&xml',
            $item['key']);

        // Return the name contents
        $allowed = array('strong' => array());
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /* $1%s */ wp_kses($name, $allowed),
            /* $2%s */ esc_html__($item['ID']),
            /* $3%s */ $this->row_actions($actions)
        );
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /* $1%s */
            esc_attr($this->_args['singular']), // Let's simply use the table's singular label ("LTI")
            /* $2%s */ esc_attr($item['key'])                // The value of the checkbox should be the record's name but we use key
        );
    }

    public static function define_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'name' => _x('Name', 'column name', 'lti-tool'),
            'key' => _x('Consumer Key', 'column name', 'lti-tool'),
            'pname' => _x('Platform Name', 'column name', 'lti-tool'),
            'avail' => _x('Available?', 'column name', 'lti-tool'),
            'debug' => _x('Debug?', 'column name', 'lti-tool'),
            'last' => _x('Last Access', 'column name', 'lti-tool')
        );

        return $columns;
    }

    public function get_columns()
    {
        return get_column_headers(get_current_screen());
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('name', true), // true means its already sorted
            'key' => array('key', false),
            'pname' => array('pname', false),
            'avail' => array('avail', false),
            'debug' => array('debug', false),
            'last' => array('last', false)
        );
        return $sortable_columns;
    }

    /**
     * ***********************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     *
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     *
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     *
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     * ************************************************************************ */
    function get_bulk_actions()
    {
        $actions = array(
            'enable' => _x('Enable', 'choice', 'lti-tool'),
            'disable' => _x('Disable', 'choice', 'lti-tool'),
            'delete' => _x('Delete', 'choice', 'lti-tool')
        );
        return $actions;
    }

    /**
     * ***********************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     *
     * @see $this->prepare_items()
     * ************************************************************************ */
    function process_bulk_action()
    {
        //Detect when a bulk action is being triggered...
        if (!empty($_REQUEST['lti'])) {
            if ('delete' === $this->current_action()) {
                $lti_connectors = $_REQUEST['lti'];
                if (is_array($lti_connectors)) {
                    foreach ($lti_connectors as $tool_guid) {
                        lti_tool_delete(sanitize_text_field($tool_guid));
                    }
                } else {
                    lti_tool_delete(sanitize_text_field($lti_connectors));
                }
            } elseif (('enable' === $this->current_action()) || ('disable' === $this->current_action())) {
                $enable = $this->current_action() == 'enable';
                $lti_connectors = $_REQUEST['lti'];
                if (is_array($lti_connectors)) {
                    foreach ($lti_connectors as $tool_guid) {
                        lti_tool_set_enable(sanitize_text_field($tool_guid), $enable);
                    }
                } else {
                    lti_tool_set_enable(sanitize_text_field($lti_connectors), $enable);
                }
            }
        }
    }

    /* ---------------------------------------------------------------
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     *
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
      --------------------------------------------------------------- */

    function prepare_items()
    {
        global $lti_tool_data_connector;

        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();

        /**
         * Get all the platforms and convert in array for this class to process
         */
        $tool = new Tool($lti_tool_data_connector);
        $lti_data = $tool->getPlatforms();
        $data = array();
        for ($i = 0; $i < count($lti_data); $i++) {
            $data[$i]['ID'] = $i;
            $data[$i]['name'] = $lti_data[$i]->name;
            $data[$i]['key'] = $lti_data[$i]->getKey();
            $data[$i]['pname'] = "<span title='" . $lti_data[$i]->consumerVersion . "'>" . $lti_data[$i]->consumerName . "</span>";
            $available = ($lti_data[$i]->getIsAvailable()) ? 'Yes' : 'No';
            $debug = ($lti_data[$i]->debugMode) ? 'Yes' : 'No';
            $now = time();
            $mouseover_dates = '';
            if (isset($lti_data[$i]->enableFrom) && ($lti_data[$i]->enableFrom > $now)) {
                $mouseover_dates = 'From ' . date('j-M-Y H:i', $lti_data[$i]->enableFrom);
            } elseif (isset($lti_data[$i]->enableUntil)) {
                if ($lti_data[$i]->enableUntil > $now) {
                    $mouseover_dates = 'Until ' . date('j-M-Y H:i', $lti_data[$i]->enableUntil);
                } else {
                    $mouseover_dates = 'Expired ' . date('j-M-Y H:i', $lti_data[$i]->enableUntil);
                }
            }
            if (!empty($mouseover_dates)) {
                $pos = strrpos($mouseover_dates, ' 00:00');
                if (($pos !== false) && ($pos == (strlen($mouseover_dates) - 6))) {
                    $mouseover_dates = substr($mouseover_dates, 0, $pos);
                }
                $available = "<span title=\"{$mouseover_dates}\">{$available}</span>";
            }
            $data[$i]['avail'] = $available;
            $data[$i]['debug'] = $debug;
            if (is_null($lti_data[$i]->lastAccess)) {
                $data[$i]['last'] = 'None';
            } else {
                $data[$i]['last'] = date('d-M-Y ', $lti_data[$i]->lastAccess);
            }
        }

        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         *
         * In a real-world situation involving a database, you would probably want
         * to handle sorting by passing the 'orderby' and 'order' values directly
         * to a custom query. The returned data will be pre-sorted, and this array
         * sorting technique would be unnecessary.
         */
        function usort_reorder($a, $b)
        {
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'name'; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'asc'; //If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
            return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
        }

        usort($data, 'usort_reorder');

        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently
         * looking at. We'll need this later, so you should always include it in
         * your own package classes.
         */
        $current_page = $this->get_pagenum();

        /**
         * REQUIRED for pagination. Let's check how many items are in our data array.
         * In real-world use, this would be the total number of items in your database,
         * without filtering. We'll need this later, so you should always include it
         * in your own package classes.
         */
        $total_items = count($data);

        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to
         */
        $data = array_slice($data, (($current_page - 1) * $this->per_page), $this->per_page);

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $data;

        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args
            (
            array(
                'total_items' => $total_items, //WE have to calculate the total number of items
                'per_page' => $this->per_page, //WE have to determine how many items to show on a page
                'total_pages' => ceil($total_items / $this->per_page)   //WE have to calculate the total number of pages
            )
        );
    }

}
