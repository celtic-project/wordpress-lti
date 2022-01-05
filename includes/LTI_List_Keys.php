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
use ceLTIc\LTI\ResourceLink;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib.php';

class LTI_List_Keys extends WP_List_Table
{

    private $per_page;

    function __construct($per_page)
    {
        global $status, $page;

        $this->per_page = $per_page;

        // Set parent defaults
        parent::__construct
            (
            array(
                'singular' => __('Share', 'lti-text'), //singular name of the listed records
                'plural' => __('Shares', 'lti-text'), //plural name of the listed records
                'ajax' => false      //does this table support ajax?
            )
        );
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
            case 'platform':
            case 'approved':
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
        if ($item['approved'] == 'Yes') {
            // Show name in bold if consumer enabled
            $name = '<strong>' . $item['name'] . '</strong>';
            $actions = array(
                'disable' => sprintf('<a href="?page=%s&action=%s&id=%s">' . __('Suspend', 'lti-text') . '</a>', $_REQUEST['page'],
                    'disable', $item['resource_link_pk']),
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">' . __('Delete', 'lti-text') . '</a>', $_REQUEST['page'],
                    'delete', $item['resource_link_pk'])
            );
        } else {
            $actions = array(
                'enable' => sprintf('<a href="?page=%s&action=%s&id=%s">' . __('Approve', 'lti-text') . '</a>', $_REQUEST['page'],
                    'enable', $item['resource_link_pk']),
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">' . __('Delete', 'lti-text') . '</a>', $_REQUEST['page'],
                    'delete', $item['resource_link_pk'])
            );
        }

        // Return the name contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /* $1%s */ $name,
            /* $2%s */ $item['ID'],
            /* $3%s */ $this->row_actions($actions)
        );
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /* $1%s */ $this->_args['singular'], // Let's simply use the table's singular label ("LTI")
            /* $2%s */ urlencode(serialize(array('id' => $item['resource_link_pk'])))
        );
    }

    public static function define_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'name' => _x('Name', 'column name', 'lti-text'),
            'platform' => _x('Platform Name', 'column name', 'lti-text'),
            'approved' => _x('Approved', 'column name', 'lti-text')
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
            'name' => array('name', false), // true means its already sorted
            'platform' => array('platform', false),
            'approved' => array('approved', false)
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
            'bulk_enable' => _x('Approve', 'choice', 'lti-text'),
            'bulk_disable' => _x('Suspend', 'choice', 'lti-text'),
            'bulk_delete' => _x('Delete', 'choice', 'lti-text')
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

        if ('delete' === $this->current_action() && !empty($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
            lti_delete_share($id);
        }

        if ('bulk_delete' === $this->current_action() && !empty($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
            foreach ($id as $data) {
                $item = unserialize(urldecode($data));
                lti_delete_share($item['id']);
            }
        }

        if (!empty($_REQUEST['id']) && ('enable' === $this->current_action() || 'disable' === $this->current_action())) {
            $action = false;
            if ('enable' === $this->current_action()) {
                $action = true;
            }
            $id = $_REQUEST['id'];
            lti_set_share($id, $action);
        }

        if (!empty($_REQUEST['id']) && ('bulk_enable' === $this->current_action() || 'bulk_disable' === $this->current_action())) {
            $action = false;
            if ('bulk_enable' === $this->current_action()) {
                $action = true;
            }
            $id = $_REQUEST['id'];
            foreach ($id as $data) {
                $item = unserialize(urldecode($data));
                lti_set_share($item['id'], $action);
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
        global $lti_db_connector, $lti_session;

        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();

        /**
         * Get all the shares and convert in array for this class to process
         */
        // Get the context
        $platform = Platform::fromConsumerKey($lti_session['key'], $lti_db_connector);
        $resource = ResourceLink::fromPlatform($platform, $lti_session['resourceid']);

        $lti_shares = $resource->getShares();

        for ($i = 0; $i < count($lti_shares); $i++) {
            $data[$i]['ID'] = $i;
            $data[$i]['name'] = $lti_shares[$i]->title;
            $data[$i]['platform'] = $lti_shares[$i]->consumerName;
            $data[$i]['approved'] = ($lti_shares[$i]->approved) ? 'Yes' : 'No';
            $data[$i]['resource_link_pk'] = $lti_shares[$i]->resourceLinkId;
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
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
            return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
        }

        // Don't usort if there is no data
        if (isset($data)) {
            usort($data, 'usort_reorder');
        }

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
        $total_items = 0;
        if (isset($data)) {
            $total_items = count($data);
        }

        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. There needs
         * to be data here...
         */
        if ($total_items > 0) {
            $data = array_slice($data, (($current_page - 1) * $this->per_page), $this->per_page);
        }

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        if (isset($data)) {
            $this->items = $data;
        }

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

?>