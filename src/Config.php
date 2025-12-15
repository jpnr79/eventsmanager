<?php
/*
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
use Dropdown;
use Html;
use Toolbox;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class Config
 */
class Config extends CommonDBTM {

   /**
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @since version 0.83
    *
    * @param CommonGLPI $item         Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    *  @return string tab name
    **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return self::createTabEntry(__('Plugin Setup', 'eventsmanager'));
   }

    static function getIcon()
    {
        return Event::getIcon();
    }

   /**
    *
    */
   static function showConfigForm() {

      $config = new self();
      $config->getFromDB(1);

      echo "<div class='center'>";
      echo "<form method='post' action='" . Toolbox::getItemTypeFormURL(Config::class) . "'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'><th colspan='2'>" . __('General setup') . "</th></tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Uto use the automatic closing of an event when creating a ticket from an event', 'eventsmanager');
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo('use_automatic_close', (($config->fields['use_automatic_close'] ?? '')));
      echo "</td></tr>";

      echo "<tr><th colspan='2'>";
      echo Html::hidden('id', ['value' => 1]);
      echo Html::submit(_sx('button', 'Post'), ['name' => 'update_config', 'class' => 'btn btn-primary']);
      echo "</th></tr>";
      echo "</table></div>";
      Html::closeForm();

   }
}
