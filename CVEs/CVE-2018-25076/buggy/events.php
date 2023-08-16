<?php
	/*
		Class: BTXEvents
			A class to handle events in BigTree.
	*/
	
	class BTXEvents {
		
		/*
			Constructor:
				Re-caches stale events.
		*/
		
		function __construct() {
			$q = sqlquery("SELECT * FROM btx_events_events WHERE recurrence_type != '' AND (last_updated < '".date("Y-m-d",strtotime("-1 year"))."' OR last_updated IS NULL)");
			
			while ($f = sqlfetch($q)) {
				self::recacheEvent($f["id"]);
				sqlquery("UPDATE btx_events_events SET last_updated = NOW() WHERE id = '".$f["id"]."'");
			}
		}

		/*
			Function: cacheEvent
				Caches an event.
			
			Parameters:
				id - The id of the event to cache.
		*/
		
		static function cacheEvent($id) {
			$id = sqlescape($id);
			$item = self::get($id);
			$title_route = sqlescape($item["route"]);
			
			// It's just a normal event!  Thank heavens!
			if (!$item["recurrence_type"]) {
				list($start_date,$end_date) = self::getCacheTimes($item,$item["start_date"],$item["end_date"]);
				
				$date_route = date("Y-m-d",strtotime($start_date));

				// Put the date in the database!
				sqlquery("INSERT INTO btx_events_date_cache (`event`,`start`,`end`,`title_route`,`date_route`) VALUES ('$id','$start_date','$end_date','$title_route','$date_route')");
			// It's repeating :(  We need to cache all previous recurrences that happened in the past year, and all that will happen in the next two years.
			} elseif (!$item["recurring_end_date"] || strtotime($item["recurring_end_date"]) > time()) {
				// If there's a start date to the recurrence, use it
				if ($item["start_date"]) {
					$start = strtotime($item["start_date"]);
				} else {
					$start = strtotime("-1 year");
				}
				// If there's an end date, stop there
				if ($item["end_date"] || $item["recurring_end_date"]) {
					$end = strtotime($item["recurring_end_date"] ? $item["recurring_end_date"] : $item["end_date"]);
				} else {
					$end = strtotime("+2 years");
				}
				// If we've already passed the end date, we don't need to cache things anymore.
				if ($end < $start) {
					return;
				}
				// Get a list of the canceled items
				$canceled = json_decode($item["canceled_recurrences"],true);
				// Loop through our start and end dates finding the next recurrence of events
				while ($start <= $end) {
					$next = self::findNextRecurrence($item["recurrence_type"],$item["recurrence_detail"],$canceled,$start);
					// The next time the event occurs could fall outside our caching period
					if ($next <= $end) {
						list($start_date,$end_date) = self::getCacheTimes($item,date("Y-m-d",$next),date("Y-m-d",$next));
						$date_route = date("Y-m-d",strtotime($start_date));
						sqlquery("INSERT INTO btx_events_date_cache (`event`,`start`,`end`,`title_route`,`date_route`) VALUES ('".$item["id"]."','$start_date','$end_date','$title_route','$date_route')");
					}
					$start = strtotime(date("Y-m-d",$next)." +1 day");
				}
			}
		}
		
		/*
			Function: findNextRecurrence
				Finds the next time an event exists.
			
			Parameters:
				type - The recurrence type.
				detail - The recurrence detail.
				canceled - An array of canceled recurrences.
				time - The start time to begin looking from (in seconds since Unix epoch).
				
			Returns:
				The next occurence of the event in seconds since Unix epoch.
		*/

		static function findNextRecurrence($type,$detail,$canceled = array(),$time = false) {
			if (!$time) {
				$time = time();
			}
			// Daily Recurrence
			if ($type == "daily") {
				// If it hasn't been canceled, return the date, otherwise try again tomorrow.
				if (!in_array(date("Y-m-d",$time),$canceled)) {
					return $time;
				} else {
					return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-d",$time)." +1 day"));
				}
			}
			// Weekly Recurrence
			if ($type == "weekly") {
				$current_day_of_week = date("w",$time);
				if ($detail > $current_day_of_week) {
					$time = strtotime(date("Y-m-d",$time)." +".($detail - $current_day_of_week)." days");
				} elseif ($detail < $current_day_of_week) {
					$time = strtotime(date("Y-m-d",$time)." +".(7 - $current_day_of_week + $detail)." days");
				}

				// If it's canceled, next week please.
				if (!in_array(date("Y-m-d",$time),$canceled)) {
					return $time;
				} else {
					return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-d",$time)." +1 week"));
				}
			}
			// Monthly Recurrence
			if ($type == "monthly") {
				// If the detail is numeric, it's simply the (x)th day of the month.
				if (is_numeric($detail)) {
					$current_day_of_month = date("j",$time);
					// Move to the 1st of the next month if the repeat has already occurred this month.
					if ($current_day_of_month > $detail) {
						$time = strtotime(date("Y-m-1",$time)." +1 month");
					}
					// If we're looking for something on the 31st and a month only has 30 days, skip it.
					if ($detail > date("t",$time)) {
						return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-1",$time)." +1 month"));
					}
					$time = strtotime(date("Y-m-".$detail,$time));

					// If it's canceled, next month please.
					if (!in_array(date("Y-m-d",$time),$canceled)) {
						return $time;
					} else {
						return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-1",$time)." +1 month"));
					}
				// We need to calculate a more crazy date like the second Thursday of each month.
				} else {
					list($x,$week,$day) = explode("#",$detail);
					
					// New strategy, start at the first day of the current month and walk through each day.  If the x'th day is already passed or is canceled, then we'll move on.
					$start = strtotime(date("Y-m-1",$time));
					$end = strtotime(date("Y-m-t",$time));
					$current_week = 1;
					
					while ($start <= $end) {
						$current_day = date("w",$start);
						if ($current_week == $week && $current_day == $day) {
							if ($time > $start || in_array(date("Y-m-d",$start),$canceled)) {
								return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-1",$time)." +1 month"));
							} else {
								return $start;
							}
						}
						if ($current_day == $day) {
							$current_week++;
						}

						// Add a day
						$start = BigTree::dateFromOffset($start,"+1 day","U");
					}
					
					if ($current_week < $week) {
						return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-1",$time)." +1 month"));
					}
					
					return false;
				}

			}
			// Yearly Recurrence
			if ($type == "yearly") {
				$next = strtotime(date("Y",$time)."-".$detail);
				if ($next < $time) {
					$next = strtotime((date("Y",$time)+1)."-".$detail);
				}
				// If it's canceled, next year please.
				if (!in_array(date("Y-m-d",$next),$canceled)) {
					return $next;
				} else {
					return self::findNextRecurrence($type,$detail,$canceled,strtotime(date("Y-m-d",$next)." +1 year"));
				}
			}
			return false;
		}

		/*
			Function: formattedDate
				Returns a string of formatted date/time based on an event having a start/end date and times.
			
			Parameters:
				item - The event instance array.
				date_format - The date format (compatible with PHP's date function)
				time_format - The time format (compatible with PHP's time format)
			
			Returns:
				A date/time string.
		*/
		
		static function formattedDate($item,$date_format = "F j, Y",$time_format = "g:ia") {
			$s = strtotime($item["start"]);
			$e = strtotime($item["end"]);
			// If it's a single all day event...
			if ($item["all_day"]) {
				if (date("Y-m-d",$s) == date("Y-m-d",$e)) {
					return date($date_format,$s);
				} else {
					return date($date_format,$s)." - ".date($date_format,$e);
				}
			} else {
				// Single day event
				if (date("Y-m-d",$s) == date("Y-m-d",$e)) {
					if ($s != $e && $item["end_time"] != "") {
						return date($date_format,$s)." &mdash; ".date($time_format,$s)." - ".date($time_format,$e);
					} else {
						return date($date_format,$s)." &mdash; ".date($time_format,$s);
					}
				// Multi day event
				} else {
					// Starts one night, ends next morning?
					if (date("H",$s) > date("H",$e)) {
						return date($date_format,$s)." &mdash; ".date($time_format,$s)." - ".date($time_format,$e);
					// Probably meant an event to be on multiple days for a few hours each day.
					} else {
						return date($date_format,$s)." - ".date($date_format,$e)." &mdash; ".date($time_format,$s)." - ".date($time_format,$e);
					}
				}
			}
		}
		
		/*
			Function: formattedTime
				Returns a string of formatted time based on an event having start/end times.
			
			Parameters:
				item - The event instance array.
				time_format - The time format (compatible with PHP's time format)
			
			Returns:
				A date/time string.
		*/
		
		static function formattedTime($item,$time_format = "gi:a") {
			$s = strtotime($item["start"]);
			$e = strtotime($item["end"]);
			if ($item["all_day"]) {
				return "All Day";
			}
			if ($s != $e && $item["end_time"] != "") {
			    return date($time_format,$s)." - ".date($time_format,$e);
			} else {
			    return date($time_format,$s);
			}
		}
		
		/*
			Function: publishHook
				Used by the BigTree form to cache the event on publish.
		*/
		
		static function publishHook($table,$id,$changes,$many_to_many,$tags) {
			self::recacheEvent($id);
		}

		/*
			Function: get
				Returns an event with its fields decoded.
			
			Parameters:
				item - Either the event id or an event array.
			
			Returns:
				An event array with its fields decoded.
		*/
		
		static function get($item) {
			global $cms;
			
			if (!is_array($item)) {
				$item = sqlfetch(sqlquery("SELECT * FROM btx_events_events WHERE id = '".sqlescape($item)."'"));
			}
			if (!is_array($item)) {
				return false;
			}
			
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = BigTree::untranslateArray($val);
				} elseif (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}

			return $item;
		}
		
		/*
			Function: getCacheTimes
				Returns the start and end timestamps for an event.
			
			Parameters:
				item - Event array.
				start_date - The date of the occurence.
				end_date - The end date of the occurence.
			
			Returns:
				An array of timestamps (first being start, second being end)
		*/				

		static function getCacheTimes($item,$start_date,$end_date) {
			// If they didn't enter an end date, we're going to assume it ends the same day it starts
			if ($end_date == "0000-00-00" || !$end_date) {
				$end_date = $start_date;
			}
			// If it's an all day event or we don't know the start time, set the end time to 11:59
			if ($item["all_day"] || !$item["start_time"]) {
				$start_date = strtotime($start_date." 00:00:00");
				$end_date = strtotime($end_date." 23:59:59");
			} else {
				$start_date = strtotime($start_date." ".$item["start_time"]);
				// If we have an end time, let's see if it's actually the next day.
				if ($item["end_time"]) {
					if (strtotime($item["start_time"]) < strtotime($item["end_time"]) && $start_date == $end_date) {
						$end_date = strtotime($start_date." ".$item["end_time"]." +1 day");
					} else {
						$end_date = strtotime($end_date." ".$item["end_time"]);
					}
				} else {
					$end_date = strtotime($end_date." 23:59:59");
				}
			}
			$start_date = date("Y-m-d H:i:s",$start_date);
			$end_date = date("Y-m-d H:i:s",$end_date);
			return array($start_date,$end_date);
		}

		/*
			Function: getCategoriesByParent
				Returns an array of categories with a given parent ID.

			Parameters:
				parent - The parent ID to check.
				sort - The sort order of the categories (defaults to positioned).

			Returns:
				An array of categories.
		*/

		static function getCategoriesByParent($parent = false,$sort = "position DESC, id ASC") {
			$categories = array();
			if (!$parent) {
				$q = sqlquery("SELECT * FROM btx_events_categories WHERE parent IS NULL ORDER BY $sort");
			} else {
				$q = sqlquery("SELECT * FROM btx_events_categories WHERE parent = '$parent' ORDER BY $sort");
			}
			while ($f = sqlfetch($q)) {
				$categories[] = $f;
			}
			return $categories;
		}
		
		/*
			Function: getCategory
				Returns a category for the given id.

			Parameters:
				id - The category id.

			Returns:
				A category entry.
		*/

		static function getCategory($id) {
			$id = sqlescape($id);
			return sqlfetch(sqlquery("SELECT * FROM btx_events_categories WHERE id = '$id'"));
		}

		/*
			Function: getCategoryByRoute
				Returns a category for the given route.

			Parameters:
				route - The category route.

			Returns:
				A category entry.
		*/

		static function getCategoryByRoute($route) {
			$route = sqlescape($route);
			return sqlfetch(sqlquery("SELECT * FROM btx_events_categories WHERE route = '$route'"));
		}

		/*
			Function: getCategoryLineage
				Returns an array of the ancestors of a given category.
			
			Parameters:
				category - A category ID or category array.
			
			Returns:
				An array of categories starting with the "oldest".
		*/
		
		static function getCategoryLineage($category,$ancestors = array()) {
			if (!is_array($category)) {
				$category = sqlfetch(sqlquery("SELECT * FROM btx_events_categories WHERE id = '".sqlescape($category)."'"));
			}
			if ($category["parent"] && $category["parent"] != $category["id"]) {
				$parent = sqlfetch(sqlquery("SELECT * FROM btx_events_categories WHERE id = '".sqlescape($category["parent"])."'"));
				$ancestors = array_merge(array($parent),$ancestors);
				return self::getCategoryLineage($parent,$ancestors);
			}
			return $ancestors;
		}
		
		/*
			Function: getEventCategories
				Returns an array of categories that an event belongs to.

			Parameters:
				event - The event entry or event id.

			Returns:
				An array of IDs.
		*/

		static function getEventCategories($event) {
			$event = is_array($event) ? sqlescape($event["id"]) : sqlescape($event);
			$categories = array();
			$q = sqlquery("SELECT btx_events_categories.* FROM btx_events_categories JOIN btx_events_event_categories ON btx_events_categories.id = btx_events_event_categories.category WHERE btx_events_event_categories.event = '$event'");
			while ($f = sqlfetch($q)) {
				$categories[] = $f;
			}
			return $categories;
		}

		/*
			Function: getEventCategoryIDs
				Returns an array of category IDs that an event belongs to.

			Parameters:
				event - The event entry or event id.

			Returns:
				An array of IDs.
		*/

		static function getEventCategoryIDs($event) {
			$event = is_array($event) ? sqlescape($event["id"]) : sqlescape($event);
			$categories = array();
			$q = sqlquery("SELECT * FROM btx_events_event_categories WHERE event = '$event'");
			while ($f = sqlfetch($q)) {
				$categories[] = $f["category"];
			}
			return $categories;
		}

		/*
			Function: getEventCategoryList
				Returns a nested category list.
		*/
		
		static function getEventCategoryList($original_list,$parent = 0,$level = "") {
			$list = array();
			$q = sqlquery("SELECT * FROM btx_events_categories WHERE parent = '".sqlescape($parent)."' ORDER BY name");
			while ($f = sqlfetch($q)) {
				$list[$f["id"]] = $level.$f["name"];
				$list = $list + self::getEventCategoryList(false,$f["id"],trim($level)."--- ");
			}
			return $list;
		}

		/*
			Function: getEventInstances
				Returns instances of an event.

			Parameters:
				event - An event array.
				upcoming - Whether to return only upcoming instances (defaults to false)

			Returns:
				An array of event instances

			See Also:
				<getUpcomingEventInstances>
		*/

		static function getEventInstances($event,$upcoming = false) {
			$instances = array();
			$upcoming = $upcoming ? " AND end >= NOW()" : "";

			$q = sqlquery("SELECT * FROM btx_events_date_cache WHERE event = '".sqlescape($event["id"])."' $upcoming ORDER BY start ASC");
			while ($f = sqlfetch($q)) {
				$f["instance"] = $f["id"];
				$instances[] = array_merge($f,$event);
			}

			return $instances;
		}

		/*
			Function: getEventsByDate
				Returns event instances for a given date.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRange>
		*/
		
		static function getEventsByDate($date,$featured = false) {
			return self::getEventsByDateRange($date,$date,$featured);
		}
		
		
		/*
			Function: getEventsByDateInCategories
				Returns event instances for a given date in given categories.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategories>
		*/
		
		static function getEventsByDateInCategories($date,$categories,$featured = false) {
			return self::getEventsByDateRangeInCategories($date,$date,$categories,$featured);
		}

		/*
			Function: getEventsByDateInCategoriesWithSubcategories
				Returns event instances for a given date in given categories and their subcategories.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategories>
		*/
		
		static function getEventsByDateInCategoriesWithSubcategories($date,$categories,$featured = false) {
			$with_sub = $categories;
			foreach ($categories as $cat) {
				$with_sub = array_merge($with_sub,self::getSubcategoriesOfCategory($cat));
			}
			return self::getEventsByDateInCategories($date,$with_sub,$featured);
		}

		/*
			Function: getEventsByDateInCategory
				Returns event instances for a given date in given category.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
				category - A category array or ID.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategories>
		*/
		
		static function getEventsByDateInCategory($date,$category,$featured = false) {
			return self::getEventsByDateRangeInCategories($date,$date,array($category),$featured);
		}

		/*
			Function: getEventsByDateInCategoryWithSubcategories
				Returns event instances for a given date in given category and its subcategories.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
				category - A category array or ID.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategoriesWithSubcategories>
		*/
		
		static function getEventsByDateInCategoryWithSubcategories($date,$category,$featured = false) {
			return self::getEventsByDateRangeInCategoriesWithSubcategories($date,$date,array($category),$featured);
		}

		/*
			Function: getEventsByDateRange
				Returns event instances for a given date range.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDate>
		*/
		
		static function getEventsByDateRange($start_date,$end_date,$featured = false) {
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.end >= '$start_date 00:00:00' AND btx_events_date_cache.start <= '$end_date 23:59:59' $featured ORDER BY btx_events_date_cache.start ASC");

			while ($f = sqlfetch($q)) {
				$events[] = self::get($f);
			}
			return $events;
		}

		/*
			Function: getEventsByDateRangeInCategories
				Returns event instances for a given date range in the given categories.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateInCategories>
		*/
		
		static function getEventsByDateRangeInCategories($start_date,$end_date,$categories,$featured = false) {
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$cat_search = array();
			foreach ($categories as $category) {
				$category = is_array($category) ? sqlescape($category["id"]) : sqlescape($category);
				$cat_search[] = "btx_events_event_categories.category = '$category'";
			}

			if (!count($cat_search)) {
				return false;
			}
			
			$q = sqlquery("SELECT DISTINCT(CONCAT(btx_events_date_cache.event,btx_events_date_cache.start,btx_events_date_cache.end)),btx_events_date_cache.event,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_date_cache.start,btx_events_date_cache.end FROM btx_events_date_cache JOIN btx_events_event_categories WHERE btx_events_date_cache.event = btx_events_event_categories.event AND btx_events_date_cache.end >= '$start_date 00:00:00' AND btx_events_date_cache.start <= '$end_date 23:59:59' AND (".implode(" OR ",$cat_search).") $featured ORDER BY btx_events_date_cache.start ASC");

			while ($f = sqlfetch($q)) {
				$event = self::get($f["event"]);
				if ($event) {
					$event["start"] = $f["start"];
					$event["end"] = $f["end"];
					$event["instance"] = $f["instance"];
					$event["title_route"] = $f["title_route"];
					$event["date_route"] = $f["date_route"];
					$events[] = $event;
				}
			}
			return $events;
		}

		/*
			Function: getEventsByDateRangeInCategoriesWithSubcategories
				Returns event instances for a given date range in the given categories and their subcategories.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateInCategoriesWithSubcategories>
		*/
		
		static function getEventsByDateRangeInCategoriesWithSubcategories($start_date,$end_date,$categories,$featured = false) {
			$with_sub = $categories;
			foreach ($categories as $cat) {
				$with_sub = array_merge($with_sub,self::getSubcategoriesOfCategory($cat));
			}
			return self::getEventsByDateRangeInCategories($start_date,$end_date,$with_sub,$featured);
		}

		/*
			Function: getEventsByDateRangeInCategory
				Returns event instances for a given date range in the given category.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				category - A category array or category ID.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateInCategories>
		*/
		
		static function getEventsByDateRangeInCategory($start_date,$end_date,$category,$featured = false) {
			return self::getEventsByDateRangeInCategories($start_date,$end_date,array($category),$featured);
		}

		/*
			Function: getEventsByDateRangeInCategoryWithSubcategories
				Returns event instances for a given date range in the given category and its subcategories.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				category - A category array or category ID.
				featured - Whether to pull only featured events or not.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateInCategoryWithSubcategories>
		*/
		
		static function getEventsByDateRangeInCategoryWithSubcategories($start_date,$end_date,$category,$featured = false) {
			return self::getEventsByDateRangeInCategoriesWithSubcategories($start_date,$end_date,array($category),$featured);
		}

		/*
			Function: getFeaturedEventsByDate
				Returns featured event instances for a given date.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDate>
		*/

		static function getFeaturedEventsByDate($date) {
			return self::getEventsByDate($date,true);
		}

		/*
			Function: getFeaturedEventsByDateRange
				Returns featured event instances for a given date range.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRange>
		*/

		static function getFeaturedEventsByDateRange($start_date,$end_date) {
			return self::getEventsByDateRange($start_date,$end_date,true);
		}

		/*
			Function: getFeaturedEventsByDateRangeInCategories
				Returns featured event instances for a given date range in the provided categories.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategories>
		*/

		static function getFeaturedEventsByDateRangeInCategories($start_date,$end_date,$categories) {
			return self::getEventsByDateRangeInCategories($start_date,$end_date,$categories,true);
		}

		/*
			Function: getFeaturedEventsByDateRangeInCategoriesWithSubcategories
				Returns featured event instances for a given date range in the provided categories and their subcategories.
			
			Parameters:
				start_date - The start date (Y-m-d format) to pull events for.
				end_date - The end date (Y-m-d format) to pull events for.
				categories - An array of categories to get events for.
			
			Returns:
				An array of event instances.
			
			See Also:
				<getEventsByDateRangeInCategoriesWithSubcategories>
		*/

		static function getFeaturedEventsByDateRangeInCategoriesWithSubcategories($start_date,$end_date,$categories) {
			return self::getEventsByDateRangeInCategoriesWithSubcategories($start_date,$end_date,$categories,true);
		}

		/*
			Function: getFeaturedSearchResultsInDateRange
				Returns featured event instances matching a given query in a specified date range.
			
			Parameters:
				query - The string to search for.
				start_date - Beginning date (Y-m-d).
				end_date - Ending date (Y-m-d).
			
			Returns:
				An array of event instances ordered by soonest.

			See Also:
				<getSearchResultsInDateRange>
		*/

		static function getFeaturedSearchResultsInDateRange($query,$start_date,$end_date) {
			return self::getSearchResultsInDateRange($query,$start_date,$end_date,true);
		}
		
		/*
			Function: getInstance
				Returns an instance of an event (combined date cache and event entry).
			
			Parameters:
				id - The id of the event instance.
			
			Returns:
				An event array with its fields decoded.
		*/
		
		static function getInstance($id) {
			return self::get(sqlfetch(sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.id = '$id'")));
		}
		
		/*
			Function: getInstanceByRoute
				Returns an instance of an event (combined date cache and event entry).
			
			Parameters:
				title_route - The title route of the event instance.
				date_route - The date route of the event instance.
			
			Returns:
				An event array with its fields decoded.
		*/
		
		static function getInstanceByRoute($title_route,$date_route) {
			return self::get(sqlfetch(sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.title_route = '".sqlescape($title_route)."' AND btx_events_date_cache.date_route = '".sqlescape($date_route)."'")));
		
		}

		/*
			Function: getKeyedEventsDateRangeForEvents
				Returns an array of days as keys with the events that fall in each day as an array.
			
			Parameters:
				events - An array of event instances.
			
			Returns:
				A keyed array (dates are keys, array of events are vals) for the events passed in.
		*/
		
		static function getKeyedDateRangeForEvents($events) {
			$days = array();
			foreach ($events as $event) {
				$days[date("Y-m-d",strtotime($event["start"]))][] = $event;
			}
			return $days;
		}

		/*
			Function: getNumberOfEventsOnDate
				Returns number of events occurring on a given date.
			
			Parameters:
				date - The date (Y-m-d format) to pull events for.
			
			Returns:
				A number.
		*/
		
		static function getNumberOfEventsOnDate($date) {
			$date = date("Y-m-d",strtotime($date));
			$f = sqlfetch(sqlquery("SELECT COUNT(id) AS `count` FROM btx_events_date_cache WHERE start >= '$date 00:00:00' AND end <= '$date 23:59:59'"));
			return $f["count"];
		}

		/*
			Function: getRandomEvent
				Returns a random event instance occurring in the future.
			
			Parameters:
				featured - Whether to return a featured event or not.
			
			Returns:
				An event instance.
			
			See Also:
				<getRandomEventByDate>
		*/
		
		static function getRandomEvent($featured = false) {
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.end >= NOW() $featured ORDER BY RAND() LIMIT 1");

			return self::get(sqlfetch($q));
		}
		
		/*
			Function: getRandomEventByDate
				Returns a random event instance occurring on a specific date.
			
			Parameters:
				date - The date to pull an event for.
			
			Returns:
				An event instance.
			
			See Also:
				<getSingleEventByDate>
		*/

		static function getRandomEventByDate($date) {
			return self::getSingleEventByDate($date,"","RAND()");
		}

		/*
			Function: getRandomFeaturedEvent
				Returns a random featured event instance occurring in the future.
				
			Returns:
				An event instance.
			
			See Also:
				<getRandomEvent>
		*/
		
		static function getRandomFeaturedEvent() {
			return self::getRandomEvent(true);
		}
		
		/*
			Function: getRandomFeaturedEventByDate
				Returns a random featured event instance occurring on a specific date.
			
			Parameters:
				date - The date to pull an event for.
			
			Returns:
				An event instance.
			
			See Also:
				<getRandomEventByDate>
		*/

		static function getRandomFeaturedEventByDate($date) {
			return self::getSingleEventByDate($date,true,"RAND()");
		}

		/*
			Function: getSearchResultsInDateRange
				Returns event instances matching a given query in a specified date range.
			
			Parameters:
				query - The string to search for.
				start_date - Beginning date (Y-m-d).
				end_date - Ending date (Y-m-d).
				featured - Whether to only return featured or not (defaults to false for all).
			
			Returns:
				An array of event instances ordered by soonest.
		*/

		static function getSearchResultsInDateRange($query,$start_date,$end_date,$featured = false) {
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$words = explode(" ",$query);
			$qwords = array();
			if ($words) {
				foreach ($words as $word) {
					$qwords[] = "(btx_events_events.title LIKE '%$word%' OR btx_events_events.description LIKE '%$word%')";
				}
				$qwords = implode(" AND ",$qwords)." AND ";
			} else {
				$qwords = "";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND $qwords btx_events_date_cache.end >= '$start_date 00:00:00' AND btx_events_date_cache.start <= '$end_date 23:59:59' $featured ORDER BY btx_events_date_cache.start ASC");

			while ($f = sqlfetch($q)) {
				$event = self::get($f);
				$events[] = $event;
			}
			return $events;
		}

		/*
			Function: getSingleEventByDate
				Returns a single event for a given date.

			Parameters:
				date - The date to check for events (in Y-m-d format).
				featured - Whether to limit the search exclusively to featured events.
				sort - The sort to go by when picking the first event (defaults to most recently created)

			Returns:
				An event instance.
		*/

		static function getSingleEventByDate($date,$featured = false,$sort = "id DESC") {
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.start <= '$date 23:59:59' AND btx_events_date_cache.end >= '$date 00:00:00' $featured ORDER BY $sort LIMIT 1");

			return self::get(sqlfetch($q));
		}

		/*
			Function: getSingleFeaturedEventByDate
				Returns a single featured event for a given date.

			Parameters:
				date - The date to check for events (in Y-m-d format).
				sort - The sort to go by when picking the first event (defaults to most recently created)

			Returns:
				An event instance.
		*/

		static function getSingleFeaturedEventByDate($date,$sort = "id DESC") {
			return self::getSingleEventByDate($date,true,$sort);
		}

		/*
			Function: getSubcategoriesOfCategory
				Returns all the subcategories (and their subcategories) of a given category.
			
			Parameters:
				category - Either a category id or array.
			
			Returns:
				An array of categories.
		*/
		
		static function getSubcategoriesOfCategory($category) {
			$category = is_array($category) ? sqlescape($category["id"]) : sqlescape($category);
			$categories = array();
			$q = sqlquery("SELECT * FROM btx_events_categories WHERE parent = '$category'");
			while ($f = sqlfetch($q)) {
				$categories[] = $f;
				$categories = array_merge($categories,self::getSubcategoriesOfCategory($f));
			}
			return $categories;
		}

		/*
			Function: getUpcomingEventInstances
				Returns instances of an event that occur in the future.

			Parameters:
				event - An event ID or event array.

			Returns:
				An array of event instances

			See Also:
				<getEventInstances>
		*/

		static function getUpcomingEventInstances($event) {
			return static::getEventInstances($event,true);
		}

		/*
			Function: getUpcomingEvents
				Returns an array of event instances occurring in the future ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				featured - Whether to limit the results to exclusively featured events or not (defaults to false).
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.
		*/

		static function getUpcomingEvents($limit = 5,$featured = false,$page = 1) {
			$page = $page ? ($page - 1) : 0;
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND btx_events_date_cache.end >= NOW() $featured ORDER BY btx_events_date_cache.start ASC LIMIT ".($page * $limit).",$limit");
			while ($f = sqlfetch($q)) {
				$event = self::get($f);
				$events[] = $event;
			}
			return $events;
		}

		/*
			Function: getUpcomingEventsPageCount
				Returns the number of pages of upcoming events.

			Parameters:
				per_page - The number of events per page.

			Returns:
				The number of pages.
		*/

		static function getUpcomingEventsPageCount($per_page = 5) {
			$f = sqlfetch(sqlquery("SELECT COUNT(id) AS `count` FROM btx_events_date_cache WHERE end >= NOW()"));
			$pages = ceil($f["count"] / $per_page);
			return $pages ? $pages : 1;
		}

		/*
			Function: getUpcomingFeaturedEvents
				Returns an array of featured event instances occurring in the future ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.

			See Also:
				<getUpcomingEvents>
		*/

		static function getUpcomingFeaturedEvents($limit = 5,$page = 1) {
			return self::getUpcomingEvents($limit,true,$page);
		}

		/*
			Function: getUpcomingEventsInCategories
				Returns an array of event instances occurring in the future in the provided categories ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				categories - An array of categories.
				featured - Whether to limit the results to exclusively featured events or not (defaults to false).
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.
		*/

		static function getUpcomingEventsInCategories($limit = 5,$categories = array(),$featured = false,$page = 1) {
			$page = $page ? ($page - 1) : 0;
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$cat_search = array();
			foreach ($categories as $category) {
				$category = is_array($category) ? sqlescape($category["id"]) : sqlescape($category);
				$cat_search[] = "btx_events_event_categories.category = '$category'";
			}

			$q = sqlquery("SELECT DISTINCT(CONCAT(btx_events_date_cache.event,btx_events_date_cache.start,btx_events_date_cache.end)),btx_events_date_cache.event,btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route FROM btx_events_date_cache JOIN btx_events_event_categories JOIN btx_events_events ON btx_events_events.id = btx_events_date_cache.event WHERE btx_events_date_cache.event = btx_events_event_categories.event AND btx_events_date_cache.end >= NOW() AND (".implode(" OR ",$cat_search).") $featured ORDER BY btx_events_date_cache.start ASC LIMIT ".($page * $limit).",$limit");

			while ($f = sqlfetch($q)) {
				$event = self::get($f["event"]);
				if ($event) {
					$event["start"] = $f["start"];
					$event["end"] = $f["end"];
					$event["instance"] = $f["instance"];
					$event["title_route"] = $f["title_route"];
					$event["date_route"] = $f["date_route"];
					$events[] = $event;
				}
			}
			return $events;
		}

		/*
			Function: getUpcomingEventsInCategoriesWithSubcategories
				Returns an array of event instances occurring in the future in the provided categories and all of their children ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				categories - An array of categories.
				featured - Whether to limit the results to exclusively featured events or not (defaults to false).
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.
		*/
		static function getUpcomingEventsInCategoriesWithSubcategories($limit,$categories = array(),$featured = false,$page = 1) {
			$with_sub = $categories;
			foreach ($categories as $cat) {
				$with_sub = array_merge($with_sub,self::getSubcategoriesOfCategory($cat));
			}
			return self::getUpcomingEventsInCategories($limit,$with_sub,$featured,$page);
		}

		/*
			Function: getUpcomingFeaturedEventsInCategories
				Returns an array of featured event instances occurring in the future in the provided categories ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				categories - An array of categories.
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.

			See Also:
				<getUpcomingEventsInCategories>
		*/

		static function getUpcomingFeaturedEventsInCategories($limit = 5,$categories = array(),$page = 1) {
			return self::getUpcomingEventsInCategories($limit,$categories,true,$page);
		}

		/*
			Function: getUpcomingFeaturedEventsInCategoriesWithSubcategories
				Returns an array of featured event instances occurring in the future in the provided categories and their subcategories ordered by those happening soonest.

			Parameters:
				limit - The number of events to return.
				categories - An array of categories.
				page - The page to return (defaults to the first page).

			Returns:
				An array of event instances.

			See Also:
				<getUpcomingEventsInCategories>
		*/

		static function getUpcomingFeaturedEventsInCategoriesWithSubcategories($limit = 5,$categories = array(),$page = 1) {
			return self::getUpcomingEventsInCategoriesWithSubcategories($limit,$categories,true,$page);
		}

		/*
			Function: getUpcomingSearchResults
				Returns event instances matching a given query that are occurring in the future.
			
			Parameters:
				query - The string to search for.
				limit - The number of event instances to return.
				featured - Whether to only return featured or not (defaults to false for all).
			
			Returns:
				An array of event instances ordered by soonest.
		*/

		static function getUpcomingSearchResults($query,$limit = 5,$featured = false) {
			$events = array();
			if ($featured) {
				$featured = " AND btx_events_events.featured = 'on' ";
			}

			$words = explode(" ",$query);
			$qwords = array();
			if ($words) {
				foreach ($words as $word) {
					$qwords[] = "(btx_events_events.title LIKE '%$word%' OR btx_events_events.description LIKE '%$word%')";
				}
				$qwords = implode(" AND ",$qwords)." AND ";
			} else {
				$qwords = "";
			}

			$q = sqlquery("SELECT btx_events_date_cache.start,btx_events_date_cache.end,btx_events_date_cache.id as instance,btx_events_date_cache.title_route AS title_route, btx_events_date_cache.date_route AS date_route,btx_events_events.* FROM btx_events_events JOIN btx_events_date_cache WHERE btx_events_date_cache.event = btx_events_events.id AND $qwords btx_events_date_cache.end >= NOW() $featured ORDER BY btx_events_date_cache.start ASC LIMIT $limit");

			while ($f = sqlfetch($q)) {
				$event = self::get($f);
				$events[] = $event;
			}
			return $events;
		}

		/*
			Function: getUpcomingFeaturedSearchResults
				Returns featured event instances matching a given query that are occurring in the future.
			
			Parameters:
				query - The string to search for.
				limit - The number of event instances to return.
			
			Returns:
				An array of event instances ordered by soonest.
		*/

		static function getUpcomingFeaturedSearchResults($query,$limit = 5) {
			return self::getUpcomingSearchResults($query,$limit,true);
		}

		/*
			Function: recacheEvent
				Removes cached occurrences and then caches the event again.
			
			Parameters:
				id - The event id.
		*/

		static function recacheEvent($id) {
			self::uncacheEvent($id);
			self::cacheEvent($id);
		}

		/*
			Function: searchResults
				Returns event entries that match a given query (not instances).
			
			Parameters:
				query - The string to search for.
			
			Returns:
				An array of decoded event entries from the database.
		*/

		static function searchResults($query) {
			$words = explode(" ",$query);
			$qwords = array();
			if ($words) {
				foreach ($words as $word) {
					$qwords[] = "(title LIKE '%$word%' OR description LIKE '%$word%')";
				}
				$qwords = " AND ".implode(" AND ",$qwords);
			} else {
				$qwords = "";
			}

			$q = sqlquery("SELECT * FROM btx_events_events WHERE 1 $qwords ORDER BY id DESC");

			$events = array();
			while ($f = sqlfetch($q)) {
				$event = self::get($f);
				$events[] = $event;
			}
			return $events;
		}

		/*
			Function: searchResultsInCategory
				Returns event entries that match a given query (not instances) that belong to a category (or one of its subcategories).
			
			Parameters:
				query - The string to search for.
				category - The category to check against.
			
			Returns:
				An array of decoded event entries from the database.
		*/

		static function searchResultsInCategory($query,$category) {
			$category = is_array($category) ? sqlescape($category["id"]) : sqlescape($category);
			$with_sub = array_merge(array($category),self::getSubcategoriesOfCategory($category));

			$cat_search = array();
			foreach ($with_sub as $category) {
				$cat_search[] = "btx_events_event_categories.category = '$category'";
			}

			$words = explode(" ",$query);
			$qwords = array();
			if ($words) {
				foreach ($words as $word) {
					$qwords[] = "(btx_events_events.title LIKE '%$word%' OR btx_events_events.description LIKE '%$word%')";
				}
				$qwords = " AND ".implode(" AND ",$qwords);
			} else {
				$qwords = "";
			}

			$q = sqlquery("SELECT DISTINCT(btx_events_event_categories.event),btx_events_events.* FROM btx_events_events JOIN btx_events_event_categories WHERE btx_events_events.id = btx_events_event_categories.event $qwords AND (".implode(" OR ",$cat_search).") ORDER BY id DESC");

			$events = array();
			while ($f = sqlfetch($q)) {
				$event = self::get($f);
				$events[] = $event;
			}
			return $events;
		}
		
		/*
			Function: uncacheEvent
				Removes cached occurrences of an event.
			
			Parameters:
				id - The event id.
		*/

		static function uncacheEvent($id) {
			$id = sqlescape($id);
			sqlquery("DELETE FROM btx_events_date_cache WHERE event = '$id'");
		}

		/*
			Function: parseMTM
				Helper function for the admin to create sensible category tagging.
		*/
		
		static function parseMTM($list,$everything = false) {
			if (!$everything) {
				return $list;
			}
			$parsed = array();
			foreach ($list as $id => $name) {
				$ancestors = self::getCategoryLineage($id);
				$path = array();
				foreach ($ancestors as $a) {
					$path[] = $a["name"];
				}
				$path[] = $name;
				$parsed[$id] = implode(" » ",$path);
			}
			asort($parsed);
			return $parsed;
		}
	}
	