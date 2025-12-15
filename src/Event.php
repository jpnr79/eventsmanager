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
use CommonDBTM;
use CommonITILObject;
use DbUtils;
use Dropdown;
use GlpiPlugin\Eventsmanager\Config;
use GlpiPlugin\Eventsmanager\Ticket;
use Html;
use MassiveAction;
use Session;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Event
 */
class Event extends CommonDBTM
{
    public $dohistory  = true;
    public static $rightname  = 'plugin_eventsmanager';
    protected $usenotepad = true;

    public const NEW_STATE      = 1;
    public const ASSIGNED_STATE = 2;
    public const CLOSED_STATE   = 3;

    public const UNDEFINED   = 0;
    public const INFORMATION = 1;
    public const WARNING     = 2;
    public const EXCEPTION   = 3;
    public const ALERT       = 4;

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {

        return _n('Event', 'Events', $nb);
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return "ti ti-calendar-event";
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {

        $dbu = new DbUtils();
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => 'glpi_plugin_eventsmanager_origins',
            'field'    => 'name',
            'name'     => __('Origin', 'eventsmanager'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'         => '5',
            'table'      => $this->getTable(),
            'field'      => 'impact',
            'name'       => __('Impact'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];

        $tab[] = [
            'id'       => '7',
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __('Comments'),
            'datatype' => 'text',
            'htmltext' => true,
        ];

        $tab[] = [
            'id'         => '8',
            'table'      => $this->getTable(),
            'field'      => 'priority',
            'name'       => __('Priority'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];

        $tab[] = [
            'id'       => '9',
            'table'    => $this->getTable(),
            'field'    => 'date_creation',
            'name'     => __('Creation date'),
            'datatype' => 'datetime',
        ];


        if (Session::getLoginUserID()) {
            $tab[] = [
                'id'            => '10',
                'table'         => 'glpi_tickets',
                'field'         => 'id',
                'name'          => _x('quantity', 'Number of tickets'),
                'datatype'      => 'count',
                'usehaving'     => true,
                'nosearch'      => true,
                'joinparams'    => ['beforejoin'
                                    => ['table'
                                        => 'glpi_plugin_eventsmanager_tickets',
                                        'joinparams'
                                        => ['jointype'
                                            => 'child']],
                    'condition'
                    => $dbu->getEntitiesRestrictRequest(
                        'AND',
                        'NEWTABLE'
                    )],
                'forcegroupby'  => true,
                'massiveaction' => false,
            ];
        }

        $tab[] = [
            'id'         => '11',
            'table'      => $this->getTable(),
            'field'      => 'status',
            'name'       => __('Status'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];

        $tab[] = [
            'id'               => '12',
            'table'            => $this->getTable(),
            'field'            => 'action',
            'name'             => __('Actions', 'eventsmanager'),
            'datatype'         => 'specific',
            'nosearch'         => true,
            'massiveaction'    => false,
            'additionalfields' => ['id', 'status'],
        ];

        $tab[] = [
            'id'               => '13',
            'table'            => 'glpi_plugin_eventsmanager_events_items',
            'field'            => 'items_id',
            'name'             => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'         => 'specific',
            'comments'         => true,
            'nosort'           => true,
            'nosearch'         => true,
            'additionalfields' => ['itemtype'],
            'joinparams'       => [
                'jointype' => 'child',
            ],
            'forcegroupby'     => true,
            'massiveaction'    => false,
        ];

        $tab[] = [
            'id'               => '131',
            'table'            => 'glpi_plugin_eventsmanager_events_items',
            'field'            => 'itemtype',
            'name'             => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'         => 'itemtypename',
            'itemtype_list'    => 'ticket_types',
            'nosort'           => true,
            'additionalfields' => ['itemtype'],
            'joinparams'       => [
                'jointype' => 'child',
            ],
            'forcegroupby'     => true,
            'massiveaction'    => false,
        ];

        $tab[] = [
            'id'       => '14',
            'table'    => $this->getTable(),
            'field'    => 'date_assign',
            'name'     => __('Assign date', 'eventsmanager'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'       => '15',
            'table'    => $this->getTable(),
            'field'    => 'date_close',
            'name'     => __('Close date'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'        => '16',
            'table'     => 'glpi_users',
            'field'     => 'name',
            'linkfield' => 'users_assigned',
            'name'      => __('Assign user', 'eventsmanager'),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'       => '17',
            'table'    => $this->getTable(),
            'field'    => 'time_to_resolve',
            'name'     => __('Time to resolve'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'        => '18',
            'table'     => 'glpi_users',
            'field'     => 'name',
            'linkfield' => 'users_close',
            'name'      => __('User close', 'eventsmanager'),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'         => '19',
            'table'      => $this->getTable(),
            'field'      => 'eventtype',
            'name'       => __('Event type', 'eventsmanager'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];

        $tab[] = [
            'id'       => '30',
            'table'    => $this->getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'number',
        ];

        $tab[] = [
            'id'       => '80',
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => __('Entity'),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Event_Item::class, $ong, $options);
        $this->addStandardTab(Ticket::class, $ong, $options);
        $this->addStandardTab('KnowbaseItem_Item', $ong, $options);
        //$this->addStandardTab('Notepad', $ong, $options);
        $this->addStandardTab(Event_Comment::class, $ong, $options);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     *
     * @return nothing|void
     */
    public function post_getEmpty()
    {

        $this->fields['priority'] = 3;
        $this->fields['impact']   = 3;
    }


    /**
     * Get default values to search engine to override
     **/
    public static function getDefaultSearchRequest()
    {

        $search = ['criteria' => [0 => ['field'      => 11,
            'searchtype' => 'equals',
            'value'      => self::NEW_STATE,
            'link'       => 'OR'],
            1 => ['field'      => 11,
                'searchtype' => 'equals',
                'value'      => self::ASSIGNED_STATE,
                'link'       => 'OR']],
            'sort'     => 9,
            'order'    => 'DESC'];

        return $search;
    }


    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        Html::initEditorSystem('comment');

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => (($this->fields['name'] ?? '')), 'size' => 40]);
        echo "</td>";
        echo "<td>" . __('Associated element', 'eventsmanager') . "</td><td>";
        Event_Item::itemAddForm($this, $options);

        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Impact') . "</td><td>";
        \Ticket::dropdownImpact(['value'     => (($this->fields['impact'] ?? '')),
            'withmajor' => 1]);
        echo "</td>";

        echo "</td>";

        echo "<td>" . __('Origin', 'eventsmanager') . "</td>";

        $rand = mt_rand();
        echo "<td>";
        Origin::dropdown([
            'name'  => "plugin_eventsmanager_origins_id",
            'rand'  => $rand,
            'value' => $this->fields["plugin_eventsmanager_origins_id"],
        ]);

        $params = [
            'plugin_eventsmanager_origins_id' => '__VALUE__',
            'fieldname'                       => 'items_id',
        ];
        Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_eventsmanager_origins_id$rand",
            "show_items_id$rand",
            "../ajax/dropdownOrigin.php",
            $params
        );

        echo "<span id='show_items_id$rand'>";
        $origin = new Origin();
        if ($origin->getFromDB($this->fields["plugin_eventsmanager_origins_id"])) {
            echo Origin::getItemtypeOrigin((($origin->fields['itemtype'] ?? '')));
            echo " - ";
            echo Origin::getItemOrigin('items_id', ["itemtype" => (($origin->fields['itemtype'] ?? '')),
                "items_id" => (($origin->fields['items_id'] ?? ''))]);
        }
        echo "</span>\n";

        echo "</td>";

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Priority') . "</td>";
        echo "<td>";
        CommonITILObject::dropdownPriority(['value'     => (($this->fields['priority'] ?? '')),
            'withmajor' => 1]);
        echo "</td>";
        echo "<td>" . __('Event type', 'eventsmanager');
        echo "</td>";
        echo "<td>";
        self::dropdownType(['value' => (($this->fields['eventtype'] ?? ''))]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>";
        echo __('User assigned', 'eventsmanager') . "</td>";
        echo "<td>";
        User::dropdown(['name'   => "users_assigned",
            'value'  => $this->fields["users_assigned"],
            'entity' => $this->fields["entities_id"],
            'right'  => 'all']);
        echo "</td>";

        echo "<td>" . __('Time to resolve');
        echo "</td>";
        echo "<td>";
        Html::showDateTimeField('time_to_resolve', ['value' => $this->fields["time_to_resolve"]]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        if ($ID > 0) {
            echo "<td>" . __('Status') . "</td>";
            echo "<td>";
            if ((($this->fields['status'] ?? '')) > 0) {
                $status = (($this->fields['status'] ?? ''));
            } else {
                $status = self::NEW_STATE;
            }
            self::dropdownStatus(['value' => $status]);
            echo "</td>";
        } else {
            echo "<td colspan='2'>";
            echo "</td>";
        }

        if ((($this->fields['status'] ?? '')) < self::CLOSED_STATE
          && (($this->fields['status'] ?? '')) > 0) {
            //gestion of action
            echo "<td >" . __('Actions', 'eventsmanager') . "</td>";
            echo "<td >";

            $id_user = $_GET['id'];
            $msg4    = __('Assign me to this event', 'eventsmanager');
            $msg5    = __('Create a ticket from the event', 'eventsmanager');
            $msg6    = __('Close the event', 'eventsmanager');

            echo "<i onclick=\"addUserEvent($id_user)\" title=\"" . $msg4 . "\"
               class='ti ti-user-plus fa-2x' style='float:left; cursor:pointer;'/></i>";
            echo "<i onclick=\"createTicketEvent($id_user)\" title=\"" . $msg5 . "\"
               class='ti ti-bell fa-2x' style='float:left; cursor:pointer;'/></i>";
            echo "<i onclick=\"closeEvent($id_user)\" title=\"" . $msg6 . "\"
               class='ti ti-archive fa-2x' style='float:left; cursor:pointer;'/></i>";
            echo "</td>";
        } else {
            echo "<td colspan='2'>";
            echo "</td>";
        }
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo __('Description') . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4' class='middle'>";
        $rand_text  = mt_rand();
        $content_id = "comment$rand_text";
        $cols       = 100;
        $rows       = 15;
        Html::textarea(['name'              => 'comment',
            'value'             => $this->fields["comment"],
            'rand'              => $rand_text,
            'editor_id'         => $content_id,
            'enable_richtext'   => true,
            'enable_fileupload' => false,
            'enable_images'     => false,
            'cols'              => $cols,
            'rows'              => $rows]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        if ($this->fields["users_assigned"] > 0
          && isset($this->fields['date_assign'])
          && $this->fields['status'] == self::ASSIGNED_STATE) {
            echo "<td>";
            echo __('Date Assign', 'eventsmanager') . "</td>";
            echo "<td>";
            echo Html::convDateTime((($this->fields['date_assign'] ?? '')), 1);
            echo "</td>";
        }


        if ($this->fields["status"] == self::CLOSED_STATE
          && $this->fields["users_close"] > 0) {
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('User close', 'eventsmanager') . "</td>";
            echo "<td>";
            $user = $dbu->getUserName($this->fields["users_close"], 2);
            echo $user["name"];
            echo "<span style='margin-right:5px'> ";
            echo Html::showToolTip($user["comment"]);
            echo "</td>";

            echo "<td>";
            echo __('Close date') . "</td>";
            echo "<td>";
            echo Html::convDateTime((($this->fields['date_close'] ?? '')), 1);
            echo "</td>";
            echo "</tr>";
        }
        $this->showFormButtons($options);

        return true;
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        if (isset($input["users_assigned"]) && $input["users_assigned"] > 0) {
            $input["status"] = self::ASSIGNED_STATE;
        }
        return $input;
    }

    public function post_addItem()
    {
        if (!empty($this->input['items_id'])) {
            $event_item = new Event_Item();
            foreach ($this->input['items_id'] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    $event_item->add(['items_id'                       => $items_id,
                        'itemtype'                       => $itemtype,
                        'plugin_eventsmanager_events_id' => (($this->fields['id'] ?? '')),
                        '_disablenotif'                  => true]);
                }
            }
        }

        parent::post_addItem();
    }


    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @param boolean $history store changes history ? (default 1)
     *
     * @return void
     **/
    public function post_updateItem($history = 1)
    {

        if (!empty($this->input['items_id'])) {
            $event_item = new Event_Item();
            foreach ($this->input['items_id'] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    $event_item->add(['items_id'                       => $items_id,
                        'itemtype'                       => $itemtype,
                        'plugin_eventsmanager_events_id' => (($this->fields['id'] ?? '')),
                        '_disablenotif'                  => true]);
                }
            }
        }
    }

    public function cleanDBonPurge()
    {

        $it = new Event_Item();
        $it->deleteByCriteria(['plugin_eventsmanager_events_id' => (($this->fields['id'] ?? ''))]);

        $ti = new Ticket();
        $ti->deleteByCriteria(['plugin_eventsmanager_events_id' => (($this->fields['id'] ?? ''))]);

        parent::cleanDBonPurge();
    }

    /**
     * @return int[]
     */
    public static function getClosedStatusArray()
    {
        return [self::CLOSED_STATE];
    }

    //Massive action

    /**
     * @param null $checkitem
     *
     * @return an
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::getCurrentInterface() == 'central') {
            if ($isadmin) {
                if (Session::haveRight('transfer', READ) && Session::isMultiEntitiesMode()
                ) {
                    $actions['GlpiPlugin\Eventsmanager\Event' . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __('Transfer');
                }
            }
        }
        return $actions;
    }

    /**
     * @param MassiveAction $ma
     *
     * @return bool|false
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     *
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {

        switch ($ma->getAction()) {
            case "transfer":
                Dropdown::show('Entity');
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
                break;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @param MassiveAction $ma
     * @param CommonDBTM    $item
     * @param array         $ids
     *
     * @return nothing|void
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     *
     */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {

        switch ($ma->getAction()) {
            case "transfer":
                $input = $ma->getInput();
                if ($item->getType() == Event::class) {
                    foreach ($ids as $key) {
                        $item->getFromDB($key);
                        $values["id"]          = $key;
                        $values["entities_id"] = $input['entities_id'];

                        if ($item->update($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     * @param $type string class name
     * *@since version 1.3.0
     *
     */
    //   static function registerType($type) {
    //      if (!in_array($type, self::$types)) {
    //         self::$types[] = $type;
    //      }
    //   }

    /**
     * Type than could be linked to a Rack
     *
     * @param $all boolean, all type, or only allowed ones
     *
     * @return array of types
     * */
    //   static function getTypes($all = false) {
    //
    //      if ($all) {
    //         return self::$types;
    //      }
    //
    //      // Only allowed types
    //      $types = self::$types;
    //
    //      foreach ($types as $key => $type) {
    //         if (!class_exists($type)) {
    //            continue;
    //         }
    //
    //         $item = new $type();
    //         if (!$item->canView()) {
    //            unset($types[$key]);
    //         }
    //      }
    //      return $types;
    //   }

    /**
     * display a value according to a field
     *
     * @param $field     String         name of the field
     * @param $values    String / Array with the value to display
     * @param $options   Array          of option
     *
     * @return int|string string
     * *@since version 0.83
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        $dbu = new DbUtils();
        $val = $values;
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'priority':
                return CommonITILObject::getPriorityName($values[$field]);
            case 'items_id':
                if (isset($values['itemtype'])) {
                    $item = $dbu->getItemForItemtype($values['itemtype']);
                    $item->getFromDB($values[$field]);
                    return $item->getName();
                } else {
                    return "";
                }
                // no break
            case 'itemtype':
                return __($values[$field]);
            case 'status':
                return self::getStatusName($values[$field]);
            case 'action':
                if ($values['status'] < self::CLOSED_STATE) {
                    return self::getActionAff($values['id'], $values['status']);
                } else {
                    return __('No action avalable', 'eventsmanager');
                }
                //         case 'ticket':
                //            $ticket = new Ticket();
                //            $ticket->getFromDB($values[$field]);
                //            $url = Toolbox::getItemTypeFormURL('Ticket') . "?id=" . $values[$field];
                //            return "<a id='ticket" . $values[$field] . "' target='_blank' href='$url'>" . $ticket->getName() . "</a>";
                // no break
            case 'eventtype':
                return static::getEventTypeName($values[$field]);
            case 'impact':
                return \Ticket::getImpactName($values[$field]);
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * @param $field
     * @param $name (default '')
     * @param $values (default '')
     * @param $options   array
     *
     * @return string
     * *@since version 0.84
     *
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'priority':
                $options['name']      = $name;
                $options['value']     = $values[$field];
                $options['withmajor'] = 1;
                return CommonITILObject::dropdownPriority($options);
                break;
            case 'impact':
                $options['name']      = $name;
                $options['value']     = $values[$field];
                $options['withmajor'] = 1;
                return \Ticket::dropdownImpact($options);
                break;
            case 'status':
                $options['name']      = $name;
                $options['value']     = $values[$field];
                $options['withmajor'] = 1;
                return self::dropdownStatus($options);
                break;
            case 'eventtype':
                $options['name']      = $name;
                $options['value']     = $values[$field];
                $options['withmajor'] = 1;
                return self::dropdownType($options);
                break;
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * @see Rule::getActions()
     * */
    public function getActions()
    {

        $actions = [];

        $actions['eventsmanager']['name']          = __('Affect entity for create event', 'eventsmanager');
        $actions['eventsmanager']['type']          = 'dropdown';
        $actions['eventsmanager']['table']         = 'glpi_entities';
        $actions['eventsmanager']['force_actions'] = ['send'];

        return $actions;
    }

    /**
     * Execute the actions as defined in the rule
     *
     * @param $action
     * @param $output the fields to manipulate
     * @param $params parameters
     *
     * @return the $output array modified
     */
    public function executeActions($action, $output, $params)
    {
        global $DB;

        switch ($params['rule_itemtype']) {
            case 'RuleMailCollector':
                switch ($action->fields["field"]) {
                    case "eventsmanager":
                        if (isset($params['headers']['subject'])) {
                            $input['name'] = addslashes($params['headers']['subject']);
                        }
                        if (isset($params['ticket'])) {
                            $input['comment'] = addslashes(strip_tags($params['ticket']['content']));
                        }
                        if (isset($params['headers']['from'])) {
                            $input['users_id'] = User::getOrImportByEmail($params['headers']['from']);
                        }

                        if (isset($action->fields["value"])) {
                            $input['entities_id'] = $action->fields["value"];
                        }
                        $input['status'] = self::NEW_STATE;

                        $origin = new Origin();
                        if (isset($params['mailcollector'])
                        && $origin->getFromDBByCrit(['itemtype' => Origin::Collector,
                            'items_id' => $params['mailcollector']])) {
                            $input['plugin_eventsmanager_origins_id'] = $origin->getID();
                        }

                        $input['impact']    = 3;
                        $input['priority']  = 3;
                        $input['eventtype'] = 1;

                        $config = new Mailimport();
                        if (isset($params['mailcollector'])
                        && $config->getFromDBByCrit(['mailcollectors_id' => $params['mailcollector']])) {
                            $input['priority']  = (($config->fields['default_priority'] ?? ''));
                            $input['impact']    = (($config->fields['default_impact'] ?? ''));
                            $input['eventtype'] = (($config->fields['default_eventtype'] ?? ''));
                        }

                        $input['date_creation'] = $_SESSION['glpi_currenttime'];
                        //type event
                        //                        if (preg_match('/information/', $input['comment'], $match)) {
                        //                            $input['eventtype'] = self::INFORMATION;
                        //                        }
                        //                        if (preg_match('/warning/', $input['comment'], $match)) {
                        //                            $input['eventtype'] = self::WARNING;
                        //                        }
                        //                        if (preg_match('/exception/', $input['comment'], $match)) {
                        //                            $input['eventtype'] = self::EXCEPTION;
                        //                        }
                        //                        if (preg_match('/alert/', $input['comment'], $match)) {
                        //                            $input['eventtype'] = self::ALERT;
                        //                        }
                        if (isset($input['name']) && $input['name'] !== false && isset($input['entities_id'])
                        ) {
                            $this->add($input);
                        }

                        $output['_refuse_email_no_response'] = false;
                        break;
                }
        }
        return $output;
    }

    /** creation of dropdown for alert status
     *
     * @param array $options
     *
     * @return int|string
     */
    public static function dropdownStatus(array $options = [])
    {

        $p['name']     = 'status';
        $p['value']    = 0;
        $p['showtype'] = 'normal';
        $p['display']  = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $values                       = [];
        $values[0]                    = static::getStatusName(0);
        $values[self::NEW_STATE]      = static::getStatusName(1);
        $values[self::ASSIGNED_STATE] = static::getStatusName(2);
        $values[self::CLOSED_STATE]   = static::getStatusName(3);

        return Dropdown::showFromArray($p['name'], $values, $p);
    }

    /**
     * Get Statut Name
     *
     * @param $value priority ID
     * */
    public static function getStatusName($value)
    {

        switch ($value) {
            case self::NEW_STATE:
                return _x('status', 'New', 'eventsmanager');

            case self::ASSIGNED_STATE:
                return _x('status', 'Assigned', 'eventsmanager');

            case self::CLOSED_STATE:
                return _x('status', 'Closed', 'eventsmanager');

            default:
                // Return $value if not define
                return Dropdown::EMPTY_VALUE;
        }
    }

    /** creation of dropdown for event type
     *
     * @param array $options
     *
     * @return int|string
     */
    public static function dropdownType(array $options = [])
    {

        $p['name']      = 'eventtype';
        $p['value']     = 0;
        $p['showtype']  = 'normal';
        $p['display']   = true;
        $p['withmajor'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $values = [];

        $values[self::UNDEFINED]   = static::getEventTypeName(self::UNDEFINED);
        $values[self::ALERT]       = static::getEventTypeName(self::ALERT);
        $values[self::EXCEPTION]   = static::getEventTypeName(self::EXCEPTION);
        $values[self::WARNING]     = static::getEventTypeName(self::WARNING);
        $values[self::INFORMATION] = static::getEventTypeName(self::INFORMATION);

        return Dropdown::showFromArray($p['name'], $values, $p);
    }

    /**
     * Get Statut Name
     *
     * @param $value priority ID
     * */
    public static function getEventTypeName($value)
    {

        switch ($value) {
            case self::UNDEFINED:
                return _x('eventtype', 'Undefined', 'eventsmanager');

            case self::ALERT:
                return _x('eventtype', 'Alert', 'eventsmanager');

            case self::EXCEPTION:
                return _x('eventtype', 'Exception', 'eventsmanager');

            case self::WARNING:
                return _x('eventtype', 'Warning', 'eventsmanager');

            case self::INFORMATION:
                return _x('eventtype', 'Information', 'eventsmanager');

            default:
                // Return $value if not define
                return $value;
        }
    }

    /**
     * Get backgroung color for event type
     *
     * @param $val type ID
     * */

    public static function getTypeColor($val)
    {
        switch ($val) {
            case 0:
                return 'background-color:LightGrey';
            case 1:
                return 'background-color:LightGreen';
            case 2:
                return 'background-color:CornflowerBlue';
            case 3:
                return 'background-color:orange';
            case 4:
                return 'background-color:red';

            default:
                break;
        }
    }

    /**
     * Get the image shortcut to display in eventsmanager list page
     *
     * @param $value status of the event
     *        $val   Id of the event
     * */
    public static function getActionAff($val, $value)
    {
        global $CFG_GLPI;
        switch ($value) {
            default:
                return
               "<i onclick=\"addUserEvent($val)\" title=\"" . __('Assign me to this event', 'eventsmanager') . "\"
               class='ti ti-user-plus fa-2x' style='float:left; cursor:pointer;'/></i>"
               . "<i onclick=\"createTicketEvent($val)\" title=\"" . __('Create a ticket from the event', 'eventsmanager') . "\"
               class='ti ti-bell fa-2x' style='float:left; cursor:pointer;'/></i>"
               . "<i onclick=\"closeEvent($val)\" title=\"" . __('Close the event', 'eventsmanager') . "\"
               class='ti ti-archive fa-2x' style='float:left; cursor:pointer;'/></i>";
        }
    }

    /**
     * @return array
     */
    public static function getMenuContent()
    {

        $menu                    = [];
        $menu['title']           = self::getMenuName(2);
        $menu['page']            = self::getSearchURL(false);
        $menu['links']['search'] = self::getSearchURL(false);
        $menu['links']['lists']  = "";
        if (self::canCreate()) {
            $menu['links']['add'] = self::getFormURL(false);
        }
        if (Config::canView()) {
            $menu['links']['config'] = Config::getFormURL(false);
        }
        $menu['icon'] = self::getIcon();

        return $menu;
    }

    public static function removeRightsFromSession()
    {
        if (isset($_SESSION['glpimenu']['helpdesk']['types'][Event::class])) {
            unset($_SESSION['glpimenu']['helpdesk']['types'][Event::class]);
        }
        if (isset($_SESSION['glpimenu']['helpdesk']['content'][Event::class])) {
            unset($_SESSION['glpimenu']['helpdesk']['content'][Event::class]);
        }
    }
}
