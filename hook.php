<?php
if (!defined('GLPI_ROOT')) { define('GLPI_ROOT', realpath(__DIR__ . '/../..')); }
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

use GlpiPlugin\Eventsmanager\Event;
use GlpiPlugin\Eventsmanager\Origin;
use GlpiPlugin\Eventsmanager\Profile;
use GlpiPlugin\Eventsmanager\Rssimport;

/**
 * @return bool
 */
function plugin_eventsmanager_install()
{
    global $DB;

    $update = true;

    if (!$DB->tableExists("glpi_plugin_eventsmanager_events")) {
        $update = false;
        $DB->runFile(PLUGIN_EVENTMANAGER_DIR . "/sql/empty-4.0.0.sql");
    }

    if ($update) {
        $DB->runFile(PLUGIN_EVENTMANAGER_DIR . "/sql/update-2.2.0.sql");
    }

    if ($update) {
        $DB->runFile(PLUGIN_EVENTMANAGER_DIR . "/sql/update-4.0.0.sql");
    }

    //DisplayPreferences Migration
    $classes = ['PluginEventsmanagerEvent' => Event::class];

    foreach ($classes as $old => $new) {
        $displayusers = $DB->request([
            'SELECT' => [
                'users_id'
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $old,
            ],
        ]);

        if (count($displayusers) > 0) {
            foreach ($displayusers as $displayuser) {
                $iterator = $DB->request([
                    'SELECT' => [
                        'num',
                        'id'
                    ],
                    'FROM' => 'glpi_displaypreferences',
                    'WHERE' => [
                        'itemtype' => $old,
                        'users_id' => $displayuser['users_id'],
                        'interface' => 'central'
                    ],
                ]);

                if (count($iterator) > 0) {
                    foreach ($iterator as $data) {
                        $iterator2 = $DB->request([
                            'SELECT' => [
                                'id'
                            ],
                            'FROM' => 'glpi_displaypreferences',
                            'WHERE' => [
                                'itemtype' => $new,
                                'users_id' => $displayuser['users_id'],
                                'num' => $data['num'],
                                'interface' => 'central'
                            ],
                        ]);
                        if (count($iterator2) > 0) {
                            foreach ($iterator2 as $dataid) {
                                $query = $DB->buildDelete(
                                    'glpi_displaypreferences',
                                    [
                                        'id' => $dataid['id'],
                                    ]
                                );
                                $DB->doQuery($query);
                            }
                        } else {
                            $query = $DB->buildUpdate(
                                'glpi_displaypreferences',
                                [
                                    'itemtype' => $new,
                                ],
                                [
                                    'id' => $data['id'],
                                ]
                            );
                            $DB->doQuery($query);
                        }
                    }
                }
            }
        }
    }

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    CronTask::Register(Rssimport::class, 'RssImport', DAY_TIMESTAMP);

    return true;
}

/**
 * @return bool
 */
function plugin_eventsmanager_uninstall()
{
    global $DB;

    $tables = [
      "glpi_plugin_eventsmanager_events",
      "glpi_plugin_eventmanager_eventtypes",
      "glpi_plugin_eventsmanager_rssimports",
      "glpi_plugin_eventsmanager_tickets",
      "glpi_plugin_eventsmanager_origins",
      "glpi_plugin_eventsmanager_events_items",
      "glpi_plugin_eventsmanager_configs",
      "glpi_plugin_eventsmanager_events_comments",
      "glpi_plugin_eventsmanager_mailimports"];

    foreach ($tables as $table) {
        $DB->doQuery("DROP TABLE IF EXISTS `$table`;");
    }

    $itemtypes = ['Alert',
        'DisplayPreference',
        'Document_Item',
        'ImpactItem',
        'Item_Ticket',
        'Link_Itemtype',
        'Notepad',
        'SavedSearch',
        'DropdownTranslation',
        'NotificationTemplate',
        'Notification'];
    foreach ($itemtypes as $itemtype) {
        $item = new $itemtype;
        $item->deleteByCriteria(['itemtype' => Event::class]);
    }

   //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }
    Event::removeRightsFromSession();

    Profile::removeRightsFromSession();

    return true;
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_eventsmanager_getDatabaseRelations()
{

    if (Plugin::isPluginActive("eventsmanager")) {
        return [
//            "glpi_users"          => ["glpi_plugin_eventsmanager_events" => "users_id",
//                                                  "glpi_plugin_eventsmanager_events" => "users_assigned",
//                                                  "glpi_plugin_eventsmanager_events" => "users_close"],
//                   "glpi_groups"         => ["glpi_plugin_eventsmanager_events" => "groups_id",
//                                                  "glpi_plugin_eventsmanager_events" => "groups_assigned"],
                   "glpi_entities"       => ["glpi_plugin_eventsmanager_events"     => "entities_id",
                                                  "glpi_plugin_eventsmanager_rssimports" => "entities_id_import"],
                   "glpi_reminders"      => ["glpi_plugin_eventsmanager_events" => "reminders_id"],
                   "glpi_requesttypes"   => ["glpi_plugin_eventsmanager_origins" => "requesttypes_id"],
                   "glpi_tickets"        => ["glpi_plugin_eventsmanager_tickets" => "tickets_id"],
                   "glpi_rssfeeds"       => ["glpi_plugin_eventsmanager_rssimports" => "rssfeeds_id"],
                   "glpi_mailcollectors" => ["glpi_plugin_eventsmanager_mailimports" => "mailcollectors_id"]];
    } else {
        return [];
    }
}

// Define Dropdown tables to be manage in GLPI :
/**
 * @return array
 */
function plugin_eventsmanager_getDropdown()
{

    if (Plugin::isPluginActive("eventsmanager")) {
        return [Origin::class => Origin::getTypeName(2)];
    } else {
        return [];
    }
}

function plugin_eventsmanager_getAddSearchOptions($itemtype)
{

    $sopt = [];

    if ($itemtype == 'RSSFeed') {
        if (Session::haveRight("plugin_eventsmanager", READ)) {
            $sopt = Rssimport::addSearchOptions($sopt);
        }
    }
    return $sopt;
}

/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return string
 */
function plugin_eventsmanager_displayConfigItem($type, $ID, $data, $num)
{

    $searchopt = Search::getOptions($type);
    $table     = $searchopt[$ID]["table"];
    $field     = $searchopt[$ID]["field"];

    switch ($table . '.' . $field) {
        case "glpi_plugin_eventsmanager_events.priority":
            return " style=\"background-color:" . $_SESSION["glpipriority_" . $data[$num][0]['name']] . ";\" ";
         break;
        case "glpi_plugin_eventsmanager_events.eventtype":
            return ' style="' . Event::getTypeColor($data[$num][0]['name']) . ';"';
         break;
        case "glpi_plugin_eventsmanager_events.action":
            return ' style="min-width:100px;"';
         break;
    }
    return "";
}

/**
 * @param $options
 *
 * @return array
 */
function plugin_eventsmanager_getRuleActions($options)
{
    $event = new Event();
    return $event->getActions();
}

/**
 * @param $options
 *
 * @return mixed
 */
function plugin_eventsmanager_getRuleCriterias($options)
{
    $event = new Event();
    return $event->getCriterias();
}

/**
 * @param $options
 *
 * @return the
 */
function plugin_eventsmanager_executeActions($options)
{
    $event = new Event();
    return $event->executeActions($options['action'], $options['output'], $options['params']);
}
