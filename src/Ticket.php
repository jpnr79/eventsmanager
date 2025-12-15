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

use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use DbUtils;
use Document_Item;
use Dropdown;
use Glpi\RichText\RichText;
use Html;
use Item_Ticket;
use Session;
use Toolbox;

class Ticket extends CommonDBTM
{
    public static $rightname = 'plugin_eventsmanager';

    /**
     * Returns the type name with consideration of plural
     *
     * @param number $nb Number of item(s)
     *
     * @return string Itemtype name
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Ticket', 'Tickets', $nb);
    }

    public static function getIcon()
    {
        return Event::getIcon();
    }
    /**
     * Return the name of the tab for item including forms like the config page
     *
     * @param CommonGLPI $item Instance of a CommonGLPI Item (The Config Item)
     * @param integer    $withtemplate
     *
     * @return String                   Name to be displayed
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $dbu = new DbUtils();
        if (Session::getCurrentInterface() == 'central' && Session::haveRight(self::$rightname, READ)) {
            switch ($item->getType()) {
                case Event::class:
                    $nb = 0;
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = $dbu->countElementsInTable(
                            'glpi_plugin_eventsmanager_tickets',
                            ["plugin_eventsmanager_events_id" => $item->getID()]
                        );
                    }
                    return self::createTabEntry(self::getTypeName($nb), $nb);
                    break;
                case "Ticket":
                    $nb = 0;
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = $dbu->countElementsInTable(
                            'glpi_plugin_eventsmanager_tickets',
                            ["tickets_id" => $item->getID()]
                        );
                    }
                    return self::createTabEntry(_n('Linked event', 'Linked events', $nb, 'eventsmanager'), $nb);
                    break;
            }
        }
        return '';
    }

    /**
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $ticket = new self();

        switch ($item->getType()) {
            case Event::class:
                $ID = $item->getField('id');
                $ticket->showForEvent($ID);
                break;
            case "Ticket":
                $ID = $item->getField('id');
                $ticket->showForTicket($item);
                break;
        }
    }

    /**
     * @param $id
     */
    public static function addTicketFromEvent($id)
    {

        $evt          = new Event();
        $ticket       = new \Ticket();
        $item         = new Item_Ticket();
        $event_ticket = new Ticket();

        if ($evt->getFromDB($id)) {

            $users_id_recipient = $_SESSION['glpiID'];
            $date               = $_SESSION['glpi_currenttime'];
            $name               = (($evt->fields['name'] ?? ''));
            $entities_id        = (($evt->fields['entities_id'] ?? ''));
            $user_id            = (($evt->fields['users_close'] ?? ''));
            $requesttype        = 0;
            $origin             = new Origin();
            if ((($evt->fields['plugin_eventsmanager_origins_id'] ?? '')) > 0
                && $origin->getFromDB((($evt->fields['plugin_eventsmanager_origins_id'] ?? '')))) {
                if ((($origin->fields['requesttypes_id'] ?? '')) > 0) {
                    $requesttype = (($origin->fields['requesttypes_id'] ?? ''));
                }
            }

            $tickets_id = $ticket->add(['name'               => addslashes($name),
                'entities_id'        => $entities_id,
                'date'               => $date,
                '_users_id_requester' => $users_id_recipient,
                'users_id_recipient' => $users_id_recipient,
                'requesttypes_id'    => $requesttype,
                'content'            => (($evt->fields['comment'] ?? '')),
                'priority'           => (($evt->fields['priority'] ?? '')),
                'impact'             => (($evt->fields['impact'] ?? '')),
                'time_to_resolve'    => (($evt->fields['time_to_resolve'] ?? '')),
                'type'               => \Ticket::INCIDENT_TYPE]);
            /*
             * Modification association document to ticket
             */
            if ($tickets_id > 0) {

                $doc_item = new Document_Item();
                $alldocs  = $doc_item->find(["items_id" => $id,
                    'itemtype' => $evt->getType()]);
                foreach ($alldocs as $key => $value) {

                    $input                 = [];
                    $input["documents_id"] = $value["documents_id"];
                    $input["itemtype"]     = $ticket->getType();
                    $input["entities_id"]  = $value["entities_id"];
                    $input["is_recursive"] = $value["is_recursive"];
                    $input["users_id"]     = $value["users_id"];
                    $input["items_id"]     = $tickets_id;
                    $doc_item->add($input);
                }
                /*
                 * End modification
                 */
                $event_item = new Event_Item();
                $items      = $event_item->getUsedItems($id);

                foreach ($items as $itemtype => $obj) {
                    foreach ($obj as $object => $items_id) {
                        $item->add(['itemtype'   => $itemtype,
                            'items_id'   => $items_id,
                            'tickets_id' => $tickets_id]);
                    }
                }

                $event_ticket->add(['plugin_eventsmanager_events_id' => $id,
                    'tickets_id'                     => $tickets_id]);

                $config = new Config();
                $config->getFromDB(1);

                if ((($config->fields['use_automatic_close'] ?? ''))) {
                    $evt->update(['id'          => $id,
                        'ticket'      => $tickets_id,
                        'users_close' => $user_id,
                        'status'      => Event::CLOSED_STATE]);
                }
            }
        }
    }

    /**
     * @param $ID
     */
    public static function cleanForTicket($item)
    {

        $temp = new self();
        $temp->deleteByCriteria(['tickets_id' => $item->getID()]);

    }

    /**
     * @param       $ID
     * @param array $options
     */
    public function showForTicket($ticket)
    {
        global $DB;

        $ID = $ticket->getField('id');
        if (!$ticket->can($ID, READ)) {
            return false;
        }

        $canedit = $ticket->canEdit($ID);
        $rand    = mt_rand();

        $query = "SELECT DISTINCT `glpi_plugin_eventsmanager_events`.*, `glpi_plugin_eventsmanager_tickets`.`id` AS LinkID
                FROM `glpi_plugin_eventsmanager_tickets`
                LEFT JOIN `glpi_plugin_eventsmanager_events`
                 ON (`glpi_plugin_eventsmanager_tickets`.`plugin_eventsmanager_events_id`=`glpi_plugin_eventsmanager_events`.`id`)
                WHERE `glpi_plugin_eventsmanager_tickets`.`tickets_id` = '$ID'
                ORDER BY `glpi_plugin_eventsmanager_events`.`date_creation`";

        $result = $DB->doQuery($query);
        $number = $DB->numrows($result);

        $tickets = [];
        $used    = [];
        if ($numrows = $DB->numrows($result)) {
            while ($data = $DB->fetchAssoc($result)) {
                $tickets[$data['id']] = $data;
                $used[$data['id']]    = $data['id'];
            }
        }
        if ($canedit) {
            echo "<div class='firstbloc'>";
            echo "<form name='eventticket_form$rand' id='eventticket_form$rand' method='post'
               action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='3'>" . __('Add a event', 'eventsmanager') . "</th></tr>";
            echo "<tr class='tab_bg_2'><td>";
            echo Html::hidden('tickets_id', ['value' => $ID]);
            Event::dropdown(['used'   => $used,
                'entity' => $ticket->getEntityID()]);
            echo "</td><td class='center'>";
            echo Html::submit(_sx('button', 'Add'), ['name' => 'add', 'class' => 'btn btn-primary']);
            echo "</td>";
            echo "</tr></table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";
        if ($canedit && $numrows) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams
               = ['num_displayed'    => min($_SESSION['glpilist_limit'], $numrows),
                   'specific_actions' => ['purge' => _x('button', 'Delete permanently')],
                   'container'        => 'mass' . __CLASS__ . $rand,
                   'extraparams'      => ['tickets_id' => $ticket->getID()]];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixehov'>";

        echo "<tr class='noHover'><th colspan='9'>" . _n('Linked event', 'Linked events', $number, 'eventsmanager') . "</th>";
        echo "</tr>";

        if ($number > 0) {
            echo "<tr>";
            echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
            echo "<th>" . __('Name') . "</th>";
            echo "<th>" . __('Date') . "</th>";
            echo "<th>" . __('Origin', 'eventsmanager') . "</th>";
            echo "<th>" . __('Status') . "</th>";
            echo "<th>" . __('Priority') . "</th>";
            echo "<th>" . __('Event type', 'eventsmanager') . "</th>";
            echo "<th>" . __('Associated element', 'eventsmanager') . "</th>";
            echo "<th>" . __('Description') . "</th>";
            echo "</tr>";

            foreach ($tickets as $data) {

                echo "<tr class='tab_bg_1'>";
                echo "<td>";
                echo Html::getMassiveActionCheckBox(__CLASS__, $data['LinkID']);
                echo "</td>";

                echo "<td>";
                $url = Toolbox::getItemTypeFormURL(Event::class) . "?id=" . $data['id'];
                echo "<a id='event" . $data['id'] . "' href='$url'>" . $data['name'] . "</a>";
                echo "</td>";

                echo "<td>";
                echo Html::convDateTime($data['date_creation'], 1);
                echo "</td>";

                echo "<td>";
                echo Dropdown::getDropdownName('glpi_plugin_eventsmanager_origins', $data['plugin_eventsmanager_origins_id']);
                $origin = new Origin();
                if ($origin->getFromDB($data["plugin_eventsmanager_origins_id"])) {
                    echo "<br>";
                    echo Origin::getItemtypeOrigin((($origin->fields['itemtype'] ?? '')));
                    echo " - ";
                    echo Origin::getItemOrigin('items_id', ["itemtype" => (($origin->fields['itemtype'] ?? '')),
                        "items_id" => (($origin->fields['items_id'] ?? ''))]);

                }
                echo "</td>";

                echo "<td>";
                echo Event::getStatusName($data['status']);
                echo "</td>";

                $style = "style=\"background-color:" . $_SESSION["glpipriority_" . $data['priority']] . ";\" ";
                echo "<td $style>";
                echo CommonITILObject::getPriorityName($data['priority']);
                echo "</td>";

                $style = "";
                if ($data['eventtype'] > 0) {
                    $style = "style='" . Event::getTypeColor($data['eventtype']) . "'";
                }
                echo "<td $style>";
                if ($data['eventtype'] > 0) {
                    echo Event::getEventTypeName($data['eventtype']);
                }
                echo "</td>";

                echo "<td>";
                $event_item = new Event_Item();
                $items      = $event_item->getUsedItems($data['id']);

                foreach ($items as $itemtype => $items_id) {
                    $item = new $itemtype();
                    foreach ($items_id as $item_id) {
                        echo $item::getTypeName();
                    }
                    $item->getFromDB($item_id);
                    echo "<br>";
                    echo $item->getLink();
                    echo "<br>";
                }
                echo "</td>";

                echo "<td>";
                echo RichText::getTextFromHtml(Html::resume_text($data['comment'], 255));
                echo "</td>";

                echo "</tr>";
            }
        } else {
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('No event linked to this ticket yet', 'eventsmanager');
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";
        if ($canedit && $numrows) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }
        echo "</div>";
        Html::closeForm();
    }

    /**
     * @param       $ID
     * @param array $options
     */
    public function showForEvent($ID, $options = [])
    {
        global $CFG_GLPI;

        $event  = new Event();
        $ticket = new \Ticket();

        $event->getFromDB($ID);

        if ((($event->fields['status'] ?? '')) < Event::CLOSED_STATE
            && (($event->fields['status'] ?? '')) > 0) {

            echo "<div class='center'>";
            echo "<form method='post' name='event_form'
         id='event_form'  action='" . Toolbox::getItemTypeFormURL(Event::class) . "'>";

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='4'>" . __('Link to tickets', 'eventsmanager') . "</th>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";

            echo "<td colspan='2'>" . __('Create a new ticket', 'eventsmanager') . "</td>";
            echo "<td colspan='2'>";
            $id_user = $_GET['id'];
            $msg5    = __('Create a ticket from the event', 'eventsmanager');
            echo "<i onclick=\"createTicketEvent($id_user)\" title=\"" . $msg5 . "\"
               class='ti ti-bell fa-2x' style='float:left; cursor:pointer;'/></i>";

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='2'>" . __('Link a existant ticket', 'eventsmanager') . "</td>";
            echo "<td colspan='2'>";
            \Ticket::dropdown(['name'        => "tickets_id",
                'entity'      => $event->getEntityID(),
                'entity_sons' => $event->isRecursive(),
                'displaywith' => ['id']]);

            echo "</td></tr>";

            echo "<tr class='tab_bg_1 center'><td colspan='4'>";
            echo Html::hidden('plugin_eventsmanager_events_id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'ticket_link', 'class' => 'btn btn-primary']);
            echo "</td></tr>";

            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }
        $eventsmanager_ticket = new Ticket();
        $tickets              = $eventsmanager_ticket->find(['plugin_eventsmanager_events_id' => (($event->fields['id'] ?? ''))]);

        if (count($tickets) > 0) {

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr>";
            echo "<th colspan='5'>" . __('Linked tickets', 'eventsmanager') . "</th>";
            echo "</tr>";

            echo "<tr>";
            echo "<th>" . __('Name') . "</th>";
            echo "<th>" . __('Date') . "</th>";
            echo "<th>" . __('Status') . "</th>";
            echo "<th>" . __('Priority') . "</th>";
            echo "<th>" . __('Associated element', 'eventsmanager') . "</th>";
            echo "</tr>";

            foreach ($tickets as $data) {

                if ($ticket->getFromDB($data['tickets_id'])) {
                    echo "<tr class='tab_bg_1'>";
                    echo "<td class='center'>";
                    echo $ticket->getLink();
                    echo "</td>";
                    echo "<td class='center'>";
                    echo Html::convDateTime($ticket->fields["date"]);
                    echo "</td>";
                    echo "<td class='center'>";
                    echo \Ticket::getStatus($ticket->fields["status"]);
                    echo "</td>";
                    $style = "style=\"background-color:" . $_SESSION["glpipriority_" . (($ticket->fields['priority'] ?? ''))] . ";\" ";
                    echo "<td class='center' $style>";
                    echo CommonITILObject::getPriorityName($ticket->fields["priority"]);
                    echo "</td>";
                    echo "<td class='center'>";
                    $item_ticket = new Item_Ticket();
                    $items       = $item_ticket->getUsedItems($ticket->fields["id"]);
                    foreach ($items as $itemtype => $items_id) {
                        $item = new $itemtype();
                        foreach ($items_id as $item_id) {
                            echo $item::getTypeName();
                        }
                        $item->getFromDB($item_id);
                        echo "<br>";
                        echo $item->getLink();
                        echo "<br>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        }
    }
}
