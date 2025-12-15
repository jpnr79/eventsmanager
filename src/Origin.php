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
use CommonDropdown;
use Dropdown;
use Html;
use MailCollector;
use RSSFeed;

class Origin extends CommonDropdown
{

    const Collector = 1;
    const RSS       = 2;
    const Api       = 3;
    const Others    = 4;

    public $dohistory         = true;
    public static $rightname         = 'plugin_eventsmanager';
    public $can_be_translated = false;

   /**
    * Returns the type name with consideration of plural
    *
    * @param number $nb Number of item(s)
    *
    * @return string Itemtype name
    */
    public static function getTypeName($nb = 0)
    {
        return _n('Event origin', 'Event origins', $nb, 'eventsmanager');
    }

    function getAdditionalFields()
    {

        return [['name'  => 'requesttypes_id',
               'label' => __('Request source'),
               'type'  => 'dropdownValue',
               'list'  => true],
              ['name'  => 'itemtype',
               'label' => __('Item type'),
               'type'  => 'specific',
               'list'  => true],
              ['name'  => 'items_id',
               'label' => __('Item'),
               'type'  => 'specific',
               'list'  => true],
        ];
    }


    function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
         'id'       => '9',
         'table'    => 'glpi_requesttypes',
         'field'    => 'name',
         'name'     => __('Request source'),
         'datatype' => 'dropdown'
        ];

        $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'itemtype',
         'name'          => __('Item type'),
         'massiveaction' => false,
         'searchtype'    => 'equals',
         'datatype'      => 'specific',
        ];

        $tab[] = [
         'id'               => '13',
         'table'            => $this->getTable(),
         'field'            => 'items_id',
         'name'             => __('Item'),
         'datatype'         => 'specific',
         'additionalfields' => ['itemtype'],
         'nosearch'         => true,
         'massiveaction'    => false
        ];

        return $tab;
    }

   /**
    * Display specific fields
    *
    * @global type $CFG_GLPI
    *
    * @param type  $ID
    * @param type  $field
    */
    function displaySpecificTypeField($ID, $field = [], array $options = [])
    {

        switch ($field['name']) {
            case 'itemtype':
                self::dropdownItemOrigin($ID, $this->fields['itemtype'] ?? '');
                break;
            case 'items_id':
                self::selectItems($this);
                break;
        }
    }

   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
    **/
    static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'items_id':
                if (isset($values['itemtype'])
                && !empty($values['itemtype'])) {
                    return self::getItemOrigin($field, $values);
                }
                break;
            case 'itemtype':
                return self::getItemtypeOrigin($values[$field]);
            break;

            return parent::getSpecificValueToDisplay($field, $values, $options);
        }
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
    static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'itemtype':
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, self::getAllItemOriginArray(), $options);
            break;
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

   /**
    * Show the Item Origin dropdown
    *
    * @param array $options
    *
    * @return type
    */
    function dropdownItemOrigin($ID, $value = 0)
    {
        global $CFG_GLPI;

        if ($ID > 0) {
            echo self::getItemtypeOrigin($this->fields['itemtype'] ?? '');
            echo Html::hidden('itemtype', ['value' => $this->fields['itemtype'] ?? '']);
        } else {
            $rand = Dropdown::showFromArray('itemtype', self::getAllItemOriginArray(), ['display_emptychoice' => true]);

            $params = ['itemtype' => '__VALUE__',
                    'id'       => $ID];
            Ajax::updateItemOnSelectEvent(
                "dropdown_itemtype$rand",
                "span_itemtype",
                PLUGIN_EVENTMANAGER_WEBDIR . "/ajax/dropdownOriginItem.php",
                $params
            );
        }
    }


    static function selectItems(CommonDBTM $origin)
    {

        echo "<span id='span_itemtype'>";

        self::dropdownItems(
            $origin->fields['itemtype'] ?? '',
            ['value' => $origin->fields['items_id'] ?? '']
        );
        echo "</span>";
    }


    static function dropdownItems($itemtype, $options = [])
    {

        $p['name']    = 'items_id';
        $p['display'] = true;
        $p['values']  = [];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        switch ($itemtype) {
            case self::Collector:
                MailCollector::dropdown($p);
                break;
            case self::RSS:
                RSSFeed::dropdown($p);
                break;
            case self::Api:
                echo __('None');
                break;
            case self::Others:
                echo __('None');
                break;
        }

        return false;
    }

   /**
    * Function get the Item type Origin
    *
    * @return an array
    */
    static function getItemtypeOrigin($value)
    {
        $data = self::getAllItemOriginArray();
        return $data[$value];
    }

   /**
    * Function get the Item Origin
    *
    * @return an array
    */
    static function getItemOrigin($field, $values)
    {

        switch ($values['itemtype']) {
            case self::Collector:
                $mail = new MailCollector();
                $mail->getFromDB($values[$field]);
                return $mail->getName();
            case self::RSS:
                $rss = new RSSFeed();
                $rss->getFromDB($values[$field]);
                return $rss->getName();
            case self::Api:
                return __('None');
            case self::Others:
                return __('None');
        }
    }

   /**
    * Get the ItemOrigin list
    *
    * @return an array
    */
    static function getAllItemOriginArray()
    {

       // To be overridden by class
        $tab = [0               => Dropdown::EMPTY_VALUE,
              self::Collector => __('Mails receiver'),
              self::RSS       => _n('RSS feed', 'RSS feeds', 1),
              self::Api       => __('Rest API'),
              self::Others    => __('Others')];

        return $tab;
    }
}
