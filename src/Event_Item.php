<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 eventsmanager plugin for GLPI
 Copyright (C) 2017-2022 by the eventsmanager Development Team.

 https://github.com/InfotelGLPI/eventsmanager
 -------------------------------------------------------------------------

 LICENSE

 This file is part of eventsmanager.

 eventsmanager is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 eventsmanager is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with eventsmanager. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Eventsmanager;

use Ajax;
use CommonDBRelation;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Html;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Event_Item Class
 *
 *  Relation between Events and Items
 **/
class Event_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Event::class;
    public static $items_id_1 = 'plugin_eventsmanager_events_id';

    public static $itemtype_2         = 'itemtype';
    public static $items_id_2         = 'items_id';
    public static $checkItem_2_Rights = self::HAVE_VIEW_RIGHT_ON_ITEM;


    /**
     * @since version 0.84
     **/
    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }


    /**
     * @since version 0.85.5
     * @see CommonDBRelation::canCreateItem()
     **/
    public function canCreateItem(): bool
    {

        $event = new Event();
        // Not item linked for closed events

        if ($event->getFromDB((($this->fields['plugin_eventsmanager_events_id'] ?? '')))
          && in_array((($event->fields['status'] ?? '')), $event->getClosedStatusArray())) {
            return false;
        }

        if ($event->canUpdateItem()) {
            return true;
        }

        return parent::canCreateItem();
    }


    public function post_addItem()
    {

        $event = new Event();
        $input = ['id'            => (($this->fields['plugin_eventsmanager_events_id'] ?? '')),
            'date_mod'      => $_SESSION["glpi_currenttime"],
            '_donotadddocs' => true];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        if (isset($this->input['_disablenotif']) && $this->input['_disablenotif']) {
            $input['_disablenotif'] = true;
        }

        $event->update($input);
        parent::post_addItem();
    }


    public function post_purgeItem()
    {

        $event = new Event();
        $input = ['id'            => (($this->fields['plugin_eventsmanager_events_id'] ?? '')),
            'date_mod'      => $_SESSION["glpi_currenttime"],
            '_donotadddocs' => true];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        $event->update($input);

        parent::post_purgeItem();
    }


    /**
     * @see CommonDBTM::prepareInputForAdd()
     **/
    public function prepareInputForAdd($input)
    {

        $dbu = new DbUtils();
        // Avoid duplicate entry
        if ($dbu->countElementsInTable($this->getTable(), ['plugin_eventsmanager_events_id' => $input['plugin_eventsmanager_events_id'],
            'itemtype'                       => $input['itemtype'],
            'items_id'                       => $input['items_id']]) > 0) {
            return false;
        }

        return parent::prepareInputForAdd($input);
    }


    /**
     * Print the HTML ajax associated item add
     *
     * @param $event Event object
     * @param $options   array of possible options:
     *    - id                  : ID of the event
     *    - _users_id_requester : ID of the requester user
     *    - items_id            : array of elements (itemtype => array(id1, id2, id3, ...))
     *
     * @return Nothing (display)
     **/
    public static function itemAddForm(Event $event, $options = [])
    {
        global $CFG_GLPI;

        $params = ['id'                  => (isset($event->fields['id'])
                                           && (($event->fields['id'] ?? '')) != '')
         ? (($event->fields['id'] ?? ''))
         : 0,
            '_users_id_requester' => 0,
            'items_id'            => [],
            'itemtype'            => '',
            '_canupdate'          => true];

        $opt = [];

        foreach ($options as $key => $val) {
            if (!empty($val)) {
                $params[$key] = $val;
            }
        }

        if (!$event->can($params['id'], READ)) {
            return false;
        }

        $canedit = ($event->can($params['id'], UPDATE)
                  && $params['_canupdate']);

        $rand  = mt_rand();
        $count = 0;

        echo "<div id='itemAddForm$rand'>";

        // Show associated item dropdowns
        if ($canedit) {
            echo "<div style='float:left'>";
            $p = ['used'                           => $params['items_id'],
                'rand'                           => $rand,
                'plugin_eventsmanager_events_id' => $params['id']];

            // Global search
            self::dropdownAllDevices("itemtype", $params['itemtype'], 0, 1, $params['_users_id_requester'], $event->fields["entities_id"], $p);
            echo "<span id='item_event_selection_information'></span>";
            echo "</div>";

            // Add button
            echo "<a href='javascript:itemAction$rand(\"add\");' class='vsubmit' style='float:left'>" . _sx('button', 'Add') . "</a>";
        }

        // Display list
        echo "<div style='clear:both;'>";

        if (!empty($params['items_id'])) {
            // No delete if mandatory and only one item
            $delete = $event->canAddItem(__CLASS__);
            $cpt    = 0;
            foreach ($params['items_id'] as $itemtype => $items) {
                $cpt += count($items);
            }

            if ($cpt == 1 && isset($tt->mandatory['items_id'])) {
                $delete = false;
            }
            foreach ($params['items_id'] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    $count++;
                    echo self::showItemToAdd(
                        $params['id'],
                        $itemtype,
                        $items_id,
                        [
                            'rand'    => $rand,
                            'delete'  => $delete,
                            'visible' => ($count <= 5),
                        ]
                    );
                }
            }
        }

        if ($count == 0) {
            echo Html::hidden('items_id', ['value' => 0]);
        }
        $usedcount = 0;
        if ($params['id'] > 0 && $usedcount != $count) {
            $count_notsaved = $count - $usedcount;
            echo "<i>" . sprintf(_n('%1$s item not saved', '%1$s items not saved', $count_notsaved), $count_notsaved) . "</i>";
        }
        if ($params['id'] > 0 && $usedcount > 5) {
            echo "<i><a href='" . $event->getFormURL() . "?id=" . $params['id'] . "&amp;forcetab=GlpiPlugin\Eventsmanager\Event_Item$1'>"
              . __('Display all items') . " (" . $usedcount . ")</a></i>";
        }
        echo "</div>";

        foreach (['id', '_users_id_requester', 'items_id', 'itemtype', '_canupdate'] as $key) {
            $opt[$key] = $params[$key];
        }

        $js = " function itemAction$rand(action, itemtype, items_id) {";
        $js .= "    $.ajax({
                     url: '" . PLUGIN_EVENTMANAGER_WEBDIR . "/ajax/itemevent.php',
                     dataType: 'html',
                     data: {'action'     : action,
                            'rand'       : $rand,
                            'params'     : " . json_encode($opt) . ",
                            'my_items'   : $('#dropdown_my_items$rand').val(),
                            'itemtype'   : (itemtype === undefined) ? $('#dropdown_itemtype$rand').val() : itemtype,
                            'items_id'   : (items_id === undefined) ? $('#dropdown_add_items_id$rand').val() : items_id},
                     success: function(response) {";
        $js .= "          $(\"#itemAddForm$rand\").html(response);";
        $js .= "       }";
        $js .= "    });";
        $js .= " }";
        echo Html::scriptBlock($js);
        echo "</div>";
    }


    public static function showItemToAdd($plugin_eventsmanager_events_id, $itemtype, $items_id, $options)
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        $params = [
            'rand'    => mt_rand(),
            'delete'  => true,
            'visible' => true,
        ];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $result = "";

        if ($item = $dbu->getItemForItemtype($itemtype)) {
            if ($params['visible']) {
                $item->getFromDB($items_id);
                $result = "<div id='{$itemtype}_$items_id'>";
                $result .= $item->getTypeName(1) . " : " . $item->getLink(['comments' => true]);
                $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
                if ($params['delete']) {
                    $result .= " <span class='ti ti-circle-x pointer' onclick=\"itemAction" . $params['rand'] . "('delete', '$itemtype', '$items_id');\"></span>";
                }
                $result .= "</div>";
            } else {
                $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
            }
        }

        return $result;
    }

    /**
     * Print the HTML array for Items linked to a event
     *
     * @param $event Event object
     *
     * @return Nothing (display)
     **/
    public static function showForEvent(Event $event)
    {
        global $DB, $CFG_GLPI;

        $dbu    = new DbUtils();
        $instID = (($event->fields['id'] ?? ''));

        if (!$event->can($instID, READ)) {
            return false;
        }

        $canedit = $event->canAddItem($instID);
        $rand    = mt_rand();

        $query = "SELECT DISTINCT `itemtype`
                FROM `glpi_plugin_eventsmanager_events_items`
                WHERE `glpi_plugin_eventsmanager_events_items`.`plugin_eventsmanager_events_id` = '$instID'
                ORDER BY `itemtype`";

        $result = $DB->doQuery($query);
        $number = $DB->numrows($result);

        if ($canedit) {
            echo "<div class='firstbloc'>";
            echo "<form name='eventitem_form$rand' id='eventitem_form$rand' method='post'
                action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";

            echo "<tr class='tab_bg_1'><td>";

            $data = array_keys($dbu->getAllDataFromTable('glpi_plugin_eventsmanager_events_items'));

            self::dropdownAllDevices("itemtype", null, 0, 1, 0, $event->fields["entities_id"], ['plugin_eventsmanager_events_id' => $instID]);
            echo "<span id='item_event_selection_information'></span>";
            echo "</td><td class='center' width='30%'>";
            echo Html::hidden('plugin_eventsmanager_events_id', ['value' => $instID]);
            echo Html::submit(_sx('button', 'Add'), ['name' => 'add', 'class' => 'btn btn-primary']);
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";
        if ($canedit && $number) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";
        $header_begin  = "<tr>";
        $header_top    = '';
        $header_bottom = '';
        $header_end    = '';
        if ($canedit && $number) {
            $header_top    .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_top    .= "</th>";
            $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_bottom .= "</th>";
        }
        $header_end .= "<th>" . __('Type') . "</th>";
        $header_end .= "<th>" . __('Entity') . "</th>";
        $header_end .= "<th>" . __('Name') . "</th>";
        $header_end .= "<th>" . __('Serial number') . "</th>";
        $header_end .= "<th>" . __('Inventory number') . "</th>";
        if ($canedit && $number) {
            $header_end .= "<th width='10'>" . __('Update the item') . "</th>";
        }
        echo "<tr>";
        echo $header_begin . $header_top . $header_end;

        $totalnb = 0;
        for ($i = 0; $i < $number; $i++) {
            $itemtype = $DB->result($result, $i, "itemtype");
            if (!($item = $dbu->getItemForItemtype($itemtype))) {
                continue;
            }

            if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
                $itemtable = $dbu->getTableForItemType($itemtype);
                $query     = "SELECT `$itemtable`.*,
                             `glpi_plugin_eventsmanager_events_items`.`id` AS IDD,
                             `glpi_entities`.`id` AS entity
                      FROM `glpi_plugin_eventsmanager_events_items`,
                           `$itemtable`";

                if ($itemtype != 'Entity') {
                    $query .= " LEFT JOIN `glpi_entities`
                                 ON (`$itemtable`.`entities_id`=`glpi_entities`.`id`) ";
                }

                $query .= " WHERE `$itemtable`.`id` = `glpi_plugin_eventsmanager_events_items`.`items_id`
                              AND `glpi_plugin_eventsmanager_events_items`.`itemtype` = '$itemtype'
                              AND `glpi_plugin_eventsmanager_events_items`.`plugin_eventsmanager_events_id` = '$instID'";

                if ($item->maybeTemplate()) {
                    $query .= " AND `$itemtable`.`is_template` = '0'";
                }

                $query .= $dbu->getEntitiesRestrictRequest(
                    " AND",
                    $itemtable,
                    '',
                    '',
                    $item->maybeRecursive()
                ) . "
                      ORDER BY `glpi_entities`.`completename`, `$itemtable`.`name`";

                $result_linked = $DB->doQuery($query);
                $nb            = $DB->numrows($result_linked);

                for ($prem = true; $data = $DB->fetchAssoc($result_linked); $prem = false) {
                    $name = $data["name"];
                    if ($_SESSION["glpiis_ids_visible"]
                     || empty($data["name"])) {
                        $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                    }
                    if (Session::getCurrentInterface() != 'helpdesk') {
                        $link     = $itemtype::getFormURLWithID($data['id']);
                        $namelink = "<a href=\"" . $link . "\">" . $name . "</a>";
                    } else {
                        $namelink = $name;
                    }

                    echo "<tr class='tab_bg_1'>";
                    if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox(__CLASS__, $data["IDD"]);
                        echo "</td>";
                    }
                    if ($prem) {
                        $typename = $item->getTypeName($nb);
                        echo "<td class='center top' rowspan='$nb'>"
                          . (($nb > 1) ? sprintf(__('%1$s: %2$s'), $typename, $nb) : $typename) . "</td>";
                    }
                    echo "<td class='center'>";
                    echo Dropdown::getDropdownName("glpi_entities", $data['entity']) . "</td>";
                    echo "<td class='center"
                    . (isset($data['is_deleted']) && $data['is_deleted'] ? " tab_bg_2_2'" : "'");
                    echo ">" . $namelink . "</td>";
                    echo "<td class='center'>" . (isset($data["serial"]) ? "" . $data["serial"] . "" : "-")
                    . "</td>";
                    echo "<td class='center'>"
                    . (isset($data["otherserial"]) ? "" . $data["otherserial"] . "" : "-") . "</td>";
                    if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox($itemtype, $data["id"]);
                        echo "</td>";
                    }

                    echo "</tr>";
                }
                $totalnb += $nb;
            }
        }

        if ($number) {
            echo $header_begin . $header_bottom . $header_end;
        }

        echo "</table>";
        if ($canedit && $number) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }
        echo "</div>";
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate) {
            $nb = 0;
            $dbu = new DbUtils();
            switch ($item->getType()) {
                case Event::class:
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = $dbu->countElementsInTable(
                            'glpi_plugin_eventsmanager_events_items',
                            ['plugin_eventsmanager_events_id' => $item->getID()]
                        );
                    }
                    return self::createTabEntry(_n('Item', 'Items', Session::getPluralNumber()), $nb);
            }
        }
        return '';
    }

    public static function getIcon()
    {
        return "ti ti-package";
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        switch ($item->getType()) {
            case Event::class:
                self::showForEvent($item);
                break;
        }
        return true;
    }

    /**
     * Make a select box for Tracking All Devices
     *
     * @param $myname             select name
     * @param $itemtype           preselected value.for item type
     * @param $items_id           preselected value for item ID (default 0)
     * @param $admin              is an admin access ? (default 0)
     * @param $users_id           user ID used to display my devices (default 0
     * @param $entity_restrict    Restrict to a defined entity (default -1)
     * @param $options   array of possible options:
     *    - plugin_eventsmanager_events_id : ID of the event
     *    - used       : ID of the requester user
     *    - multiple   : allow multiple choice
     *    - rand       : random number
     *
     * @return nothing (print out an HTML select box)
     **/
    public static function dropdownAllDevices(
        $myname,
        $itemtype,
        $items_id = 0,
        $admin = 0,
        $users_id = 0,
        $entity_restrict = -1,
        $options = []
    ) {
        global $CFG_GLPI, $DB;
        $dbu = new DbUtils();
        $params = ['plugin_eventsmanager_events_id' => 0,
            'used'                           => [],
            'multiple'                       => 0,
            'rand'                           => mt_rand()];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $rand = $params['rand'];

        if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] == 0) {
            echo Html::hidden($myname, ['value' => '']);
            echo Html::hidden('items_id', ['value' => 0]);
        } else {
            echo "<div id='tracking_all_devices$rand'>";
            if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] & pow(
                2,
                \Ticket::HELPDESK_ALL_HARDWARE
            )) {
                // Display a message if view my hardware
                if ($users_id
                && ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] & pow(
                    2,
                    \Ticket::HELPDESK_MY_HARDWARE
                ))) {
                    echo __('Or complete search') . "&nbsp;";
                }

                $types      = \Ticket::getAllTypesForHelpdesk();
                $emptylabel = __('General');
                if ($params['plugin_eventsmanager_events_id'] > 0) {
                    $emptylabel = Dropdown::EMPTY_VALUE;
                }
                Dropdown::showItemTypes(
                    $myname,
                    array_keys($types),
                    ['emptylabel' => $emptylabel,
                        'value'      => $itemtype,
                        'rand'       => $rand,
                        'display_emptychoice' => true]
                );
                $found_type = isset($types[$itemtype]);

                $p = ['itemtype'        => '__VALUE__',
                    'entity_restrict' => $entity_restrict,
                    'admin'           => $admin,
                    'used'            => $params['used'],
                    'multiple'        => $params['multiple'],
                    'rand'            => $rand,
                    'myname'          => "add_items_id"];

                Ajax::updateItemOnSelectEvent(
                    "dropdown_$myname$rand",
                    "results_$myname$rand",
                    $CFG_GLPI["root_doc"]
                                          . "/ajax/dropdownTrackingDeviceType.php",
                    $p
                );
                echo "<span id='results_$myname$rand'>\n";

                // Display default value if itemtype is displayed
                if ($found_type
                 && $itemtype) {
                    if (($item = $dbu->getItemForItemtype($itemtype))
                     && $items_id) {
                        if ($item->getFromDB($items_id)) {
                            Dropdown::showFromArray(
                                'items_id',
                                [$items_id => $item->getName()],
                                ['value' => $items_id]
                            );
                        }
                    } else {
                        $p['itemtype'] = $itemtype;
                        echo "<script type='text/javascript' >\n";
                        echo "$(function() {";
                        Ajax::updateItemJsCode(
                            "results_$myname$rand",
                            $CFG_GLPI["root_doc"]
                                 . "/ajax/dropdownTrackingDeviceType.php",
                            $p
                        );
                        echo '});</script>';
                    }
                }
                echo "</span>\n";
            }
            echo "</div>";
        }
        return $rand;
    }


    /**
     * Make a select box with all glpi items
     *
     * @param $options array of possible options:
     *    - name         : string / name of the select (default is users_id)
     *    - value
     *    - comments     : boolean / is the comments displayed near the dropdown (default true)
     *    - entity       : integer or array / restrict to a defined entity or array of entities
     *                      (default -1 : no restriction)
     *    - entity_sons  : boolean / if entity restrict specified auto select its sons
     *                      only available if entity is a single value not an array(default false)
     *    - rand         : integer / already computed rand value
     *    - toupdate     : array / Update a specific item on select change on dropdown
     *                      (need value_fieldname, to_update, url
     *                      (see Ajax::updateItemOnSelectEvent for information)
     *                      and may have moreparams)
     *    - used         : array / Already used items ID: not to display in dropdown (default empty)
     *    - on_change    : string / value to transmit to "onChange"
     *    - display      : boolean / display or get string (default true)
     *    - width        : specific width needed (default 80%)
     *
     **/
    public static function dropdown($options = [])
    {
        global $DB;

        // Default values
        $p['name']        = 'items';
        $p['value']       = '';
        $p['all']         = 0;
        $p['on_change']   = '';
        $p['comments']    = 1;
        $p['width']       = '80%';
        $p['entity']      = -1;
        $p['entity_sons'] = false;
        $p['used']        = [];
        $p['toupdate']    = '';
        $p['rand']        = mt_rand();
        $p['display']     = true;
        $dbu = new DbUtils();

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $itemtypes = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];

        $query = "";
        foreach ($itemtypes as $type) {
            $table = $dbu->getTableForItemType($type);
            if (!empty($query)) {
                $query .= " UNION ";
            }
            $query .= " SELECT `$table`.`id` AS id , '$type' AS itemtype , `$table`.`name` AS name
                     FROM `$table`
                     WHERE `$table`.`id` IS NOT NULL AND `$table`.`is_deleted` = '0' AND `$table`.`is_template` = '0' ";
        }

        $result = $DB->doQuery($query);
        $output = [];
        if ($DB->numrows($result) > 0) {
            while ($data = $DB->fetchAssoc($result)) {
                $item                                          = $dbu->getItemForItemtype($data['itemtype']);
                $output[$data['itemtype'] . "_" . $data['id']] = $item->getTypeName() . " - " . $data['name'];
            }
        }

        return Dropdown::showFromArray($p['name'], $output, $p);
    }

    /**
     * Return used items for a event
     *
     * @param type $events_id
     *
     * @return type
     */
    public static function getUsedItems($events_id)
    {

        $dbu = new DbUtils();
        $data = $dbu->getAllDataFromTable('glpi_plugin_eventsmanager_events_items', ["`plugin_eventsmanager_events_id`" => $events_id]);
        $used = [];
        if (!empty($data)) {
            foreach ($data as $val) {
                $used[$val['itemtype']][] = $val['items_id'];
            }
        }

        return $used;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'               => '13',
            'table'            => $this->getTable(),
            'field'            => 'items_id',
            'name'             => _n('Associated element', 'Associated elements', 2),
            'datatype'         => 'specific',
            'comments'         => true,
            'nosort'           => true,
            'additionalfields' => ['itemtype'],
        ];

        $tab[] = [
            'id'            => '131',
            'table'         => $this->getTable(),
            'field'         => 'itemtype',
            'name'          => _n('Associated item type', 'Associated item types', 2),
            'datatype'      => 'itemtypename',
            'itemtype_list' => 'ticket_types',
            'nosort'        => true,
        ];

        return $tab;
    }


    /**
     * @since version 0.84
     *
     * @param $field
     * @param $values
     * @param $options   array
     **/
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        $dbu = new DbUtils();
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'items_id':
                if (strpos($values[$field], "_") !== false) {
                    $item_itemtype      = explode("_", $values[$field]);
                    $values['itemtype'] = $item_itemtype[0];
                    $values[$field]     = $item_itemtype[1];
                }

                if (isset($values['itemtype'])) {
                    if (isset($options['comments']) && $options['comments']) {
                        $tmp = Dropdown::getDropdownName(
                            $dbu->getTableForItemtype($values['itemtype']),
                            $values[$field],
                            1
                        );
                        return sprintf(
                            __('%1$s %2$s'),
                            $tmp['name'],
                            Html::showToolTip($tmp['comment'], ['display' => false])
                        );
                    }
                    return Dropdown::getDropdownName(
                        $dbu->getTableForItemtype($values['itemtype']),
                        $values[$field]
                    );
                }
                break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }


    /**
     * @since version 0.84
     *
     * @param $field
     * @param $name (default '')
     * @param $values (default '')
     * @param $options   array
     *
     * @return string
     **/
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'items_id':
                if (isset($values['itemtype']) && !empty($values['itemtype'])) {
                    $options['name']  = $name;
                    $options['value'] = $values[$field];
                    return Dropdown::show($values['itemtype'], $options);
                } else {
                    self::dropdownAllDevices($name, 0, 0);
                    return ' ';
                }
                break;
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Add a message on add action
     **/
    public function addMessageOnAddAction()
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        $addMessAfterRedirect = false;
        if (isset($this->input['_add'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message'])
          || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = $dbu->getItemForItemtype((($this->fields['itemtype'] ?? '')));
            $item->getFromDB((($this->fields['items_id'] ?? '')));

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }
            if (($name = $item->getName()) == NOT_AVAILABLE) {
                //TRANS: %1$s is the itemtype, %2$d is the id of the item
                $item->fields['name'] = sprintf(
                    __('%1$s - ID %2$d'),
                    $item->getTypeName(1),
                    (($item->fields['id'] ?? ''))
                );
            }

            $display = (isset($this->input['_no_message_link']) ? $item->getNameID()
            : $item->getLink());

            // Do not display quotes
            //TRANS : %s is the description of the added item
            Session::addMessageAfterRedirect(sprintf(
                __('%1$s: %2$s'),
                __('Item successfully added'),
                stripslashes($display)
            ));
        }
    }

    /**
     * Add a message on delete action
     **/
    public function addMessageOnPurgeAction()
    {

        $dbu = new DbUtils();
        if (!$this->maybeDeleted()) {
            return;
        }

        $addMessAfterRedirect = false;
        if (isset($this->input['_delete'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message'])
          || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = $dbu->getItemForItemtype((($this->fields['itemtype'] ?? '')));
            $item->getFromDB((($this->fields['items_id'] ?? '')));

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }
            if (isset($this->input['_no_message_link'])) {
                $display = $item->getNameID();
            } else {
                $display = $item->getLink();
            }
            //TRANS : %s is the description of the updated item
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Item successfully deleted'), $display));
        }
    }
}
