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

use GlpiPlugin\Eventsmanager\Origin;

if (strpos($_SERVER['PHP_SELF'], "dropdownOrigin.php")) {
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

Session::checkCentralAccess();

// Make a select box
if (isset($_POST["plugin_eventsmanager_origins_id"])) {

   $origin = new Origin();
   if ($origin->getFromDB($_POST["plugin_eventsmanager_origins_id"])) {
      echo Origin::getItemtypeOrigin($origin->fields['itemtype'] ?? '');
      echo " - ";
      echo Origin::getItemOrigin('items_id', ["itemtype" => $origin->fields['itemtype'] ?? '',
         "items_id" => $origin->fields['items_id'] ?? '']);

   }
}
