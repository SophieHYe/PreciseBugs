<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Tasklists plugin for GLPI
 Copyright (C) 2003-2016 by the Tasklists Development Team.

 https://github.com/InfotelGLPI/tasklists
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Tasklists.

 Tasklists is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Tasklists is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Tasklists. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class for a Dropdown

/**
 * Class PluginTasklistsTaskType
 */
class PluginTasklistsTaskType extends CommonTreeDropdown {

   use \Glpi\Features\Kanban;

   static $rightname = 'plugin_tasklists';

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Context', 'Contexts', $nb, 'tasklists');
   }

   /**
    * @return string
    */
   static function getIcon() {
      return "ti ti-layout-kanban";
   }

   /**
    * @param array $options
    *
    * @return array
    * @see CommonGLPI::defineTabs()
    *
    */
   function defineTabs($options = []) {

      $ong = parent::defineTabs($options);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('PluginTasklistsStateOrder', $ong, $options);
      $this->addStandardTab('PluginTasklistsTypeVisibility', $ong, $options);
      return $ong;
   }


   /**
    * @return array
    */
   static function getAllForKanban($active = true, $current_id = -1) {
      $self = new self();

      $list  = $self->find([], ["completename ASC"]);
      $items = [

      ];

      foreach ($list as $key => $value) {
         $self->getFromDB($value['id']);
         if (!$self->haveChildren()) {
            $items[$value['id']] = $value['completename'];
         }

      }
      return $items;
   }

   /**
    * @return bool
    */
   public function forceGlobalState() {
      // All users must be using the global state unless viewing the global Kanban
      return false;
   }

   /**
    * @param $ID
    * @param $entity
    *
    * @return ID|int|the
    * @throws \GlpitestSQLError
    */
   static function transfer($ID, $entity) {
      global $DB;

      if ($ID > 0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_tasklists_tasktypes`
                   WHERE `id` = '$ID'";

         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
               $data                                   = $DB->fetchAssoc($result);
               $data                                   = Toolbox::addslashes_deep($data);
               $input['name']                          = $data['name'];
               $input['entities_id']                   = $entity;
               $input['is_recursive']                  = $data['is_recursive'];
               $input['plugin_tasklists_tasktypes_id'] = $data['plugin_tasklists_tasktypes_id'];
               $temp                                   = new self();
               $newID                                  = $temp->getID();

               if ($newID < 0) {
                  $newID = $temp->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }

   /**
    * @param       $ID
    * @param       $column_field
    * @param array $column_ids
    * @param bool  $get_default
    *
    * @return array
    */

   static function getKanbanColumns($ID, $column_field = null, $column_ids = [], $get_default = false) {

      if (!PluginTasklistsTypeVisibility::isUserHaveRight($ID)) {
         return [];
      }
      $dbu = new DbUtils();
      //      $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType('PluginTasklistsTaskState'));

      $states[0] = [
         'id'              => 0,
         'name'            => __('Backlog', 'tasklists'),
         'header_color'    => "#CCC",
         'header_fg_color' => Toolbox::getFgColor("#CCC", 50),
         'drop_only'       => 0,
         'finished'        => 0,
         '_protected'   => true
      ];

      if (!empty($column_ids)) {
         $PluginTasklistsTaskState = new PluginTasklistsTaskState();
         $datastates               = $PluginTasklistsTaskState->find(["id" => $column_ids]);
      }

      if (!empty($column_ids) && !empty($datastates)) {

         foreach ($datastates as $datastate) {
            if (empty($name = DropdownTranslation::getTranslatedValue($datastate['id'], 'PluginTasklistsTaskState', 'name', $_SESSION['glpilanguage']))) {
               $name = $datastate['name'];
            }
            $states[$datastate['id']] = [
               'id'              => $datastate['id'],
               'header_color'    => $datastate['color'],
               'header_fg_color' => Toolbox::getFgColor($datastate['color'], 50),
               'name'            => $name,
               'finished'        => $datastate['is_finished']];
            $colors[$datastate['id']] = $datastate['color'];
         }
      }
      $nstates = [];

      $task = new PluginTasklistsTask();
      foreach ($states as $state) {

         $selected_state = $state;
         $tasks          = [];
         $datas          = $task->find(["plugin_tasklists_tasktypes_id"  => $ID,
                                        "plugin_tasklists_taskstates_id" => $state['id'],
                                        'is_deleted'                     => 0,
                                        'is_template'                    => 0], ['priority DESC,name']);

         foreach ($datas as $data) {
            $array = isset($_SESSION["archive"][Session::getLoginUserID()]) ? json_decode($_SESSION["archive"][Session::getLoginUserID()]) : [0];
            if (!in_array($data["is_archived"], $array)) {
               continue;
            }
            $usersallowed = isset($_SESSION["usersKanban"][Session::getLoginUserID()]) ? json_decode($_SESSION["usersKanban"][Session::getLoginUserID()]) : [-1];
            if (!in_array(-1, $usersallowed) && !in_array($data['users_id'], $usersallowed)) {
               continue;
            }

            $plugin_tasklists_taskstates_id = $data['plugin_tasklists_taskstates_id'];
            $finished                       = 0;
            $finished_style                 = 'style="display: inline;"';
            $stateT                         = new PluginTasklistsTaskState();
            if ($stateT->getFromDB($plugin_tasklists_taskstates_id)) {
               if ($stateT->getFinishedState()) {
                  $finished_style = 'style="display: none;"';
                  $finished       = 1;
               }
            }
            $task = new PluginTasklistsTask();
            if ($task->checkVisibility($data['id']) == true) {
               $duedate = '';
               if (!empty($data['due_date'])) {
                  $duedate = __('Due date', 'tasklists') . " " . Html::convDate($data['due_date']);
               }
               $actiontime = '';
               if ($data['actiontime'] != 0) {
                  $actiontime = Html::timestampToString($data['actiontime'], false, true);
               }
               $archived = $data['is_archived'];

               if (isset($data['users_id'])
                   && $data['users_id'] != Session::getLoginUserID()) {
                  $finished_style = 'style="display: none;"';
               }

               $right = 0;
               if (($data['users_id'] == Session::getLoginUserID()
                    && Session::haveRight("plugin_tasklists", UPDATE))
                   || Session::haveRight("plugin_tasklists_see_all", 1)) {
                  $right = 1;
               }

               if ($data['users_id'] == 0) {
                  $right          = 1;
                  $finished_style = 'style="display: inline;"';
               }

               $entity      = new Entity();
               $entity_name = __('None');
               if ($entity->getFromDB($data['entities_id'])) {
                  $entity_name = $entity->fields['name'];
               }
               $client = (empty($data['client'])) ? $entity_name : $data['client'];

               //               $comment = Glpi\Toolbox\Sanitizer::unsanitize($data["content"]);

               // Core content
               $content      = "<div class='kanban-core-content'>";
               $content      .= "<div class='flex-break'>";
               $bgcolor      = $_SESSION["glpipriority_" . $data['priority']];
               $content      .= __('Priority') . "&nbsp;:&nbsp;<i class='fas fa-circle' style='color: $bgcolor'></i>&nbsp;" . CommonITILObject::getPriorityName($data['priority']);
               $content      .= "</div>";
               $rich_content = "";
               if ($data['content'] != null) {
                  $rich_content = Glpi\RichText\RichText::getTextFromHtml($data['content'], false, true, true);
               }
               $content .= Html::resume_text($rich_content, 100);
               $content .= "</div>";
               $content .= "<div align='right' class='endfooter b'>" . $client . "</div>";
               $content .= "<div align='right' class='endfooter'>" . $actiontime . "</div>";
               $content .= "<div align='right' class='endfooter'>" . $duedate . "</div>";
               // Percent Done
               $content    .= "<div class='flex-break'></div>";
               $content    .= Html::progress(100, $data['percent_done']);
               $content    .= "</div>";
               $content    .= "<div align='right' class='endfooter'>" . $data['percent_done'] . "%</div>";
               $nbcomments = "";
               $nb         = 0;
               $where      = [
                  'plugin_tasklists_tasks_id' => $data['id'],
                  'language'                  => null
               ];
               $nb         = countElementsInTable(
                  'glpi_plugin_tasklists_tasks_comments',
                  $where
               );
               if ($nb > 0) {
                  $nbcomments = " (" . $nb . ") ";
               }

               $itemtype        = "PluginTasklistsTask";
               $meta            = [];
               $metadata_values = ['name', 'content'];
               foreach ($metadata_values as $metadata_value) {
                  if (isset($data[$metadata_value])) {
                     $meta[$metadata_value] = $data[$metadata_value];
                  }
               }
               //               if (isset($meta['_metadata']['content']) && is_string($meta['_metadata']['content'])) {
               //                  $meta['_metadata']['content'] = Glpi\RichText\RichText::getTextFromHtml($tasks['_metadata']['content'], false, true);
               //               } else {
               //                  $meta['_metadata']['content'] = '';
               //               }

               // Create a fake item to get just the actors without loading all other information about items.
               //               $temp_item = new PluginTasklistsTask();
               //               $temp_item->fields['id'] = $data['id'];
               //               $temp_item->loadActors();

               // Build team member data
               $supported_teamtypes = [
                  //                  'User' => ['id', 'firstname', 'realname'],
                  //                  'Group' => ['id', 'name'],
                  //                  'Supplier' => ['id', 'name'],
               ];
               //               $members = [
               //                  'User'      => $temp_item->fields['users_id'],
               //                  'Group'     => $temp_item->fields['groups_id'],
               //                  'Supplier'   => $temp_item->getSuppliers(CommonITILActor::ASSIGN),
               //               ];
               $team = [];
               //               foreach ($supported_teamtypes as $itemtype => $fields) {
               //                  $fields[] = 'id';
               //                  $fields[] = new QueryExpression($DB->quoteValue($itemtype) . ' AS ' . $DB->quoteName('itemtype'));
               //
               //                  $member_ids = array_map(static function ($e) use ($itemtype) {
               //                     return $e[$itemtype::getForeignKeyField()];
               //                  }, $members[$itemtype]);
               //                  if (count($member_ids)) {
               //                     $itemtable = $itemtype::getTable();
               //                     $all_items = $DB->request([
               //                                                  'SELECT'    => $fields,
               //                                                  'FROM'      => $itemtable,
               //                                                  'WHERE'     => [
               //                                                     "{$itemtable}.id"   => $member_ids
               //                                                  ]
               ////                                               ]);
               //               $team = [];
               //                  $all_items[] = ['itemtype' => 'User', 'items_id'=> $data['users_id']];
               //                  $all_items[] = ['itemtype' => 'Group', 'items_id'=> $data['groups_id']];
               ////                     $all_members = [];
               //                     foreach ($all_items as $k => $member_data) {
               //                        $member_data['itemtype'] = $member_data['itemtype'];
               //                        $member_data['id'] = $member_data['items_id'];
               //                        $member_data['role'] = 2;
               ////                        if ($member_data['itemtype'] === User::class) {
               ////                           $member_data['name'] = formatUserName(
               ////                              $member_data['id'],
               ////                              '',
               ////                              $member_data['realname'],
               ////                              $member_data['firstname']
               ////                           );
               ////                        }
               //                        $team[] = $member_data;
               //                     }
               ////                  }
               ////               }
               //               Toolbox::logInfo($team);
               $task->getFromDB($data['id']);
               $team = $task->getTeam();

               if (isset($stateT->fields['color']) && $stateT->fields['color'] != null) {
                  $bgcolor = self::getFgColor($stateT->fields['color'], 1);
               } else {
                  $bgcolor = "#FFF";
               }

               $rich_content = "";
               if ($data['content'] != null) {
                  $rich_content = Glpi\RichText\RichText::getTextFromHtml($data['content'], false, true);
               }

               $title = Html::link($data['name'], $itemtype::getFormURLWithID($data['id'])) . $nbcomments;
               //               $ID    = $data['id'];
               //               if ($finished == 1 && $archived == 0) {
               //                  $title .= "&nbsp;<a id='archivetask$ID' href='#' title='" . __('Archive this task', 'tasklists') . "'><i class='ti ti-archive'></i></a>";
               //               }
               //               if ($finished == 1 && $data['priority'] < 5) {
               //                  $title .= "&nbsp;<a id='updatepriority$ID' href='#' title='" . __('Update priority of task', 'tasklists') . "'><i class='ti ti-arrow-up'></i></a>";
               //               }

               $tasks[] = ['id'            => "{$itemtype}-{$data['id']}",
                           'title'         => $title,
                           'title_tooltip' => Html::resume_text($rich_content, 100),
                           'is_deleted'    => $data['is_deleted'] ?? false,
                           'content'       => $content,
                           '_team'         => $team,
                           '_form_link'    => $itemtype::getFormUrlWithID($data['id']),

                           'block'          => ($ID > 0 ? $ID : 0),
                           'priority'       => CommonITILObject::getPriorityName($data['priority']),
                           'priority_id'    => $data['priority'],
                           'bgcolor'        => $bgcolor,
                           'percent'        => $data['percent_done'],
                           'actiontime'     => $actiontime,
                           'duedate'        => $duedate,
                           //                           'user'           => $link,
                           'client'         => $client,
                           'finished'       => $finished,
                           'archived'       => $archived,
                           'finished_style' => $finished_style,
                           'right'          => $right,
                           'users_id'       => $data['users_id'],
                           '_readonly'      => false,
                           '_metadata'      => $meta
               ];
            }
         }
         $selected_state["items"] = $tasks;
         $nstates[$state["id"]]   = $selected_state;
      }

      return $nstates;

   }

   public static function getFgColor(string $color = "", int $offset = 40, bool $inherit_if_transparent = false): string {
      $fg_color = "FFFFFF";
      if ($color !== "") {
         $color = str_replace("#", "", $color);

         // if transparency present, get only the color part
         if (strlen($color) === 8 && preg_match('/^[a-fA-F0-9]+$/', $color)) {
            $tmp   = $color;
            $alpha = hexdec(substr($tmp, 6, 2));
            $color = substr($color, 0, 6);

            if ($alpha <= 100) {
               return "inherit";
            }
         }

         $color_inst = new Mexitek\PHPColors\Color($color);

         // adapt luminance part
         //         if ($color_inst->isLight()) {
         //            $hsl = Color::hexToHsl($color);
         //            $hsl['L'] = max(0, $hsl['L'] - ($offset / 100));
         //            $fg_color = Color::hslToHex($hsl);
         //         } else {
         $hsl      = Mexitek\PHPColors\Color::hexToHsl($color);
         $hsl['L'] = ($hsl['L'] * 110) + 5;
         $hsl['L'] = ($hsl['L'] > 110) ? $hsl['L'] / 50 : $hsl['L'] / 90;
         $fg_color = Mexitek\PHPColors\Color::hslToHex($hsl);
         //         }
      }

      return "#" . $fg_color;
   }

   /**
    * @param $plugin_tasklists_tasktypes_id
    *
    * @return array
    */
   static function findUsers($plugin_tasklists_tasktypes_id) {
      $dbu   = new DbUtils();
      $users = [];
      $task  = new PluginTasklistsTask();
      $tasks = $task->find(["plugin_tasklists_tasktypes_id" => $plugin_tasklists_tasktypes_id, "is_archived" => 0, "is_deleted" => 0]);
      foreach ($tasks as $t) {
         $users[$t["users_id"]] = $dbu->getUserName($t["users_id"]);
      }
      $users     = array_unique($users);
      $users[-1] = __("All");


      return $users;
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return boolean
    **/
   static function canCreate() {
      if (static::$rightname) {
         return Session::haveRight(static::$rightname, 1);
      }
      return false;
   }

   static function canUpdate() {
      if (static::$rightname) {
         return Session::haveRight(static::$rightname, 1);
      }
      return false;
   }

   static function canDelete() {
      if (static::$rightname) {
         return Session::haveRight(static::$rightname, 1);
      }
      return false;
   }

   public static function getDataToDisplayOnKanban($ID, $criteria = []) {
      // Not needed
   }

   public static function showKanban($ID) {
      // Not needed
   }

   public static function getAllKanbanColumns($column_field = null, $column_ids = [], $get_default = false) {

      if ($column_field === null || $column_field === 'plugin_tasklists_taskstates_id') {
         $columns  = ['plugin_tasklists_taskstates_id' => []];
         $restrict = [];
         //         if (!empty($column_ids) && !$get_default) {
         //            $restrict = ['id' => $column_ids];
         //         }

         $Taskstate    = new PluginTasklistsTaskState();
         $all_statuses = $Taskstate->find($restrict, ['is_finished ASC', 'id']);

         $columns['plugin_tasklists_taskstates_id'][0] = [
            //            'id'        => 0,
            'name'            => __('Backlog', 'tasklists'),
            'header_color'    => "#CCC",
            'header_fg_color' => Toolbox::getFgColor("#CCC", 50),
            'drop_only'       => 0
         ];

         foreach ($all_statuses as $status) {

            $columns['plugin_tasklists_taskstates_id'][$status['id']] = [
               'name'            => $status['name'],
               'header_color'    => $status['color'],
               'header_fg_color' => Toolbox::getFgColor($status['color'], 50),
               'drop_only'       => 0,//$status['is_finished'] ??
            ];
         }

         return $columns['plugin_tasklists_taskstates_id'];
      } else {
         return [];
      }
   }

   //   public static function getGlobalKanbanUrl(bool $full = true): string
   //   {
   //      if (method_exists(static::class, 'getFormUrl')) {
   //         return static::getFormURL($full) . '?showglobalkanban=1';
   //      }
   //      //      $kb = new PluginTasklistsKanban();
   //      //      echo $kb->getSearchURL() . '?context_id=' . $_REQUEST['items_id'];
   //      //
   //      return '';
   //   }

   public function getKanbanUrlWithID(int $items_id, bool $full = true): string {
      $kb = new PluginTasklistsKanban();
      return $kb->getSearchURL() . '?context_id=' . $items_id;

   }
}
