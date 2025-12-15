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
use DbUtils;
use Html;
use Session;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/// Class Event_Comment
class Event_Comment extends CommonDBTM
{
    public static function getTypeName($nb = 0)
    {
        return _n('Comment', 'Comments', $nb);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $nb  = 0;
        $dbu = new DbUtils();
        if ($_SESSION['glpishow_count_on_tabs']) {
            $where = [];
            $where = [
                'plugin_eventsmanager_events_id' => $item->getID(),
            ];

            $nb = $dbu->countElementsInTable(
                'glpi_plugin_eventsmanager_events_comments',
                $where
            );
        }
        return self::createTabEntry(self::getTypeName($nb), $nb);
    }

    public static function getIcon()
    {
        return "ti ti-message-2";
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        self::showForItem($item, $withtemplate);
        return true;
    }

    /**
     * Show linked items of a event
     *
     * @param $item                     CommonDBTM object
     * @param $withtemplate    integer  withtemplate param (default 0)
     **/
    public static function showForItem(CommonDBTM $item, $withtemplate = 0)
    {

        // Total Number of comments
        $where    = [
            'plugin_eventsmanager_events_id' => $item->getID(),
        ];
        $event_id = $where['plugin_eventsmanager_events_id'];
        $event    = new Event();
        $event->getFromDB($event_id);

        $number = countElementsInTable(
            'glpi_plugin_eventsmanager_events_comments',
            $where
        );

        $cancomment = true;
        if ($cancomment) {
            echo "<div class='firstbloc'>";

            echo self::getCommentForm($event_id);
            echo "</div>";
        }

        // No comments in database
        if ($number < 1) {
            $no_txt = __('No comments');
            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>$no_txt</th></tr>";
            echo "</table>";
            echo "</div>";
            return;
        }

        // Output events
        echo "<div class='forcomments timeline_history'>";
        echo "<ul class='comments left'>";
        $comments = self::getCommentsForEvent($where['plugin_eventsmanager_events_id']);

        $html = self::displayComments($comments, $cancomment);
        echo $html;

        echo "</ul>";
        $root_eventsmanager_doc = PLUGIN_EVENTMANAGER_WEBDIR;
        echo "<script type='text/javascript'>
              $(function() {
                 var _bindForm = function(form) {
                     form.find('input[type=reset]').on('click', function(e) {
                        e.preventDefault();
                        form.remove();
                        $('.displayed_content').show();
                     });
                 };

                 $('.add_answer').on('click', function() {
                    var _this = $(this);
                    var _data = {
                       'plugin_eventsmanager_events_id': _this.data('plugin_eventsmanager_events_id'),
                       'answer'   : _this.data('id')
                    };

                    if (_this.parents('.comment').find('#newcomment' + _this.data('id')).length > 0) {
                       return;
                    }

                    $.ajax({
                       url: '$root_eventsmanager_doc/ajax/getcomment.php',
                       method: 'post',
                       cache: false,
                       data: _data,
                       success: function(data) {
                          var _form = $('<div class=\"newcomment ms-3\" id=\"newcomment'+_this.data('id')+'\">' + data + '</div>');
                          _bindForm(_form);
                          _this.parents('.h_item').after(_form);
                       },
                       error: function() { "
           . Html::jsAlertCallback(__('Contact your GLPI admin!'), __('Unable to load comment!')) . "
                       }
                    });
                 });

                 $('.edit_item').on('click', function() {
                    var _this = $(this);
                    var _data = {
                       'plugin_eventsmanager_events_id': _this.data('plugin_eventsmanager_events_id'),
                       'edit'     : _this.data('id')
                    };

                    if (_this.parents('.comment').find('#editcomment' + _this.data('id')).length > 0) {
                       return;
                    }

                    $.ajax({
                       url: '$root_eventsmanager_doc/ajax/getcomment.php',
                       method: 'post',
                       cache: false,
                       data: _data,
                       success: function(data) {
                          var _form = $('<div class=\"editcomment\" id=\"editcomment'+_this.data('id')+'\">' + data + '</div>');
                          _bindForm(_form);
                          _this
                           .parents('.displayed_content').hide()
                           .parent()
                           .append(_form);
                       },
                       error: function() { "
           . Html::jsAlertCallback(__('Contact your GLPI admin!'), __('Unable to load comment!')) . "
                       }
                    });
                 });


              });
            </script>";

        echo "</div>";
    }

    /**
     * Gat all comments for specified event entry
     *
     * @param integer $plugin_eventsmanager_events_id event entry ID
     * @param integer $parent Parent ID (defaults to 0)
     *
     * @return array
     */
    public static function getCommentsForEvent($event_id, $parent = null)
    {
        global $DB;

        $where = [
            'plugin_eventsmanager_events_id' => $event_id,
            'parent_comment_id'              => $parent,
        ];

        $db_comments = $DB->request(
            'glpi_plugin_eventsmanager_events_comments',
            $where + ['ORDER' => 'id ASC']
        );

        $comments = [];
        foreach ($db_comments as $db_comment) {
            $db_comment['answers'] = self::getCommentsForEvent($event_id, $db_comment['id']);
            $comments[]            = $db_comment;
        }

        return $comments;
    }

    /**
     * Display comments
     *
     * @param array   $comments Comments
     * @param boolean $cancomment Whether user can comment or not
     * @param integer $level Current level, defaults to 0
     *
     * @return string
     */
    public static function displayComments($comments, $cancomment, $level = 0)
    {
        $html = '';
        foreach ($comments as $comment) {
            $user = new User();
            $user->getFromDB($comment['users_id']);

            $html .= "<li class='comment" . ($level > 0 ? ' subcomment' : '') . "' id='eventcomment{$comment['id']}'>";
            $html .= "<div class='h_item left'>";
            if ($level === 0) {
                $html .= '<hr/>';
            }
            $html          .= "<div class='h_info'>";
            $html          .= "<div class='h_date'>" . Html::convDateTime($comment['date_creation']) . "</div>";
            $html          .= "<div class='h_user'>";
            $thumbnail_url = User::getThumbnailURLForPicture((($user->fields['picture'] ?? '')));
            $style         = !empty($thumbnail_url) ? "background-image: url(\"$thumbnail_url\")" : ("background-color: " . $user->getUserInitialsBgColor());
            $html          .= '<a href="' . $user->getLinkURL() . '">';
            $html          .= "<span class='avatar avatar-md rounded' style='{$style}'>";
            if (empty($thumbnail_url)) {
                $html .= $user->getUserInitials();
            }
            $html .= '</span></a>';
            $html .= "</div>"; // h_user
            $html .= "</div>"; //h_info

            $html .= "<div class='h_content TicketFollowup'>";
            $html .= "<div class='displayed_content'>";

            if ($cancomment) {
                if (Session::getLoginUserID() == $comment['users_id']) {
                    $html .= "<span class='edit_item'
                  data-plugin_eventsmanager_events_id='{$comment['plugin_eventsmanager_events_id']}'
                  data-id='{$comment['id']}'></span>";
                }
            }

            $html .= "<div class='item_content'>";
            $html .= "<p>";
            $html .= $comment['comment'];
            $html .= "</p>";
            $html .= "</div>";
            $html .= "</div>"; // displayed_content

            if ($cancomment) {
                $html .= "<span class='add_answer' title='" . __('Add an answer') . "'
               data-plugin_eventsmanager_events_id='{$comment['plugin_eventsmanager_events_id']}'
               data-id='{$comment['id']}'></span>";
            }

            $html .= "</div>"; //end h_content
            $html .= "</div>";

            if (isset($comment['answers']) && count($comment['answers']) > 0) {
                $html .= "<input type='checkbox' id='toggle_{$comment['id']}'
                             class='toggle_comments' checked='checked'>";
                $html .= "<label for='toggle_{$comment['id']}' class='toggle_label'>&nbsp;</label>";
                $html .= "<ul>";
                $html .= self::displayComments($comment['answers'], $cancomment, $level + 1);
                $html .= "</ul>";
            }

            $html .= "</li>";
        }
        return $html;
    }

    /**
     * Get comment form
     *
     * @param integer       $event_id Knowbase item ID
     * @param false|integer $edit Comment id to edit, or false
     * @param false|integer $answer Comment id to answer to, or false
     *
     * @return string
     */
    public static function getCommentForm($event_id, $edit = false, $answer = false)
    {
        $rand = mt_rand();

        $content = '';
        if ($edit !== false) {
            $comment = new self();
            $comment->getFromDB($edit);
            $content = (($comment->fields['comment'] ?? ''));
        }

        $html = '';
        $html .= "<form name='eventcomment_form$rand' id='eventcomment_form$rand'
                      class='comment_form' method='post'
            action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";

        $html .= "<table class='tab_cadre_fixe'>";

        $form_title = ($edit === false ? __('New comment') : __('Edit comment'));
        $html       .= "<tr class='tab_bg_2'><th colspan='3'>$form_title</th></tr>";

        $html .= "<tr class='tab_bg_1'><td><label for='comment'>" . __('Comment') . "</label>
         &nbsp;<span style='color:red'>*</span></td><td>";
        $html .= "<textarea name='comment' id='comment' required='required'>{$content}</textarea>";
        $html .= "</td><td class='center'>";

        $btn_text = _sx('button', 'Add');
        $btn_name = 'add';

        if ($edit !== false) {
            $btn_text = _sx('button', 'Edit');
            $btn_name = 'edit';
        }
        $html .= "<input type='submit' name='$btn_name' value='{$btn_text}' class='btn btn-primary'>";
        if ($edit !== false || $answer !== false) {
            $html .= "<input type='reset' name='cancel' value='" . __('Cancel') . "' class='btn btn-primary'>";
        }

        $html .= Html::hidden('plugin_eventsmanager_events_id', ['value' => $event_id]);

        if ($answer !== false) {
            $html .= Html::hidden('parent_comment_id', ['value' => $answer]);
        }
        if ($edit !== false) {
            $html .= Html::hidden('id', ['value' => $edit]);
        }
        $html .= "</td></tr>";
        $html .= "</table>";
        $html .= Html::closeForm(false);
        return $html;
    }

    public function prepareInputForAdd($input)
    {
        if (!isset($input["users_id"])) {
            $input["users_id"] = 0;
            if ($uid = Session::getLoginUserID()) {
                $input["users_id"] = $uid;
            }
        }

        return $input;
    }
}
