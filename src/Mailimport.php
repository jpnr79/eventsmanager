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
use Html;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class Mailimport
 */
class Mailimport extends CommonDBTM {

   /**
    * @param int $nb
    *
    * @return string
    */
   static function getTypeName($nb = 0) {

      return __('Import mails for events manager', 'eventsmanager');
   }

    static function getIcon()
    {
        return Event::getIcon();
    }

   /**
    * @param CommonGLPI $item
    * @param int        $withtemplate
    *
    * @return string|translated
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'MailCollector') {
         return self::createTabEntry(_n('Event manager', 'Events manager', 2, 'eventsmanager'));
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
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool {

      $mail = new self();
      if ($item->getType() == 'MailCollector') {
         $idr = $item->getID();
         if (!($res = $mail->getFromDBByCrit(['mailcollectors_id' => $idr]))) {
            $id = $mail->add(['mailcollectors_id' => $idr,
                              'default_impact'    => '0',
                              'default_eventtype' => '0',
                              'default_priority'  => '0']);
            $mail->getFromDB($id);
         }
         $mail->showConfig($idr);
      }
      return true;
   }


   /**
    * @param  $item
    */
   function showConfig($idr) {

      echo "<form action='" . $this->getFormURL() . "' method='post' >";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>" . _n('Event manager', 'Events manager', 2, 'eventsmanager') . "</th></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>" . __('Default impact', 'eventsmanager') . "</td><td>";
      \Ticket::dropdownImpact(['name'      => 'default_impact',
                              'value'     => $this->fields['default_impact'] ?? '',
                              'withmajor' => 1]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>" . __('Default priority', 'eventsmanager') . "</td><td>";
      CommonITILObject::dropdownPriority(['name'      => 'default_priority',
                                          'value'     => $this->fields['default_priority'] ?? '',
                                          'withmajor' => 1]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>" . __('Default event type', 'eventsmanager') . "</td><td>";
      Event::dropdownType(['name'  => 'default_eventtype',
                                              'value' => $this->fields['default_eventtype'] ?? '']);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1 center'><td colspan='2'>";
      echo Html::hidden('id', ['value' => $this->getID()]);
      echo Html::hidden('mailcollectors_id', ['value' => $idr]);
      echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
      echo "</td></tr>";
      echo "</table>";

      Html::closeForm();
   }
}
