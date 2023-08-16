;; Copyright (c) 2015-2022 Michael Schaeffer (dba East Coast Toolworks)
;;
;; Licensed as below.
;;
;; Licensed under the Apache License, Version 2.0 (the "License");
;; you may not use this file except in compliance with the License.
;; You may obtain a copy of the License at
;;
;;       http://www.apache.org/licenses/LICENSE-2.0
;;
;; The license is also includes at the root of the project in the file
;; LICENSE.
;;
;; Unless required by applicable law or agreed to in writing, software
;; distributed under the License is distributed on an "AS IS" BASIS,
;; WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
;; See the License for the specific language governing permissions and
;; limitations under the License.
;;
;; You must not remove this notice, or any other, from this software.


(ns toto.todo.todo-list
  (:use toto.core.util
        toto.view.common
        toto.view.icons
        toto.view.components
        toto.view.query
        toto.view.page)
  (:require [clojure.tools.logging :as log]
            [hiccup.form :as form]
            [hiccup.util :as util]
            [toto.data.data :as data]
            [toto.view.auth :as auth]
            [toto.view.request-date :as request-date]
            [toto.todo.sidebar-view :as sidebar-view]))

(def html-breakpoint "&#8203;")

(def pill-date-format (java.text.SimpleDateFormat. "yyyy-MM-dd"))

(defn- format-date [ date ]
  (.format pill-date-format date))

(defn ensure-string-breakpoints [ s n ]
  (clojure.string/join html-breakpoint (partition-string s n)))

(defn- ensure-string-breaks [ string at ]
  (clojure.string/replace string at (str at html-breakpoint)))

(defn shorten-url-text [ url-text target-length ]
  (let [url (java.net.URL. url-text)
        base (str (.getProtocol url)
                  ":"
                  (if-let [authority (.getAuthority url)]
                    (str "//" authority)))]
    (-> (util/escape-html
         (str base
              (string-leftmost (.getPath url)
                               (max 0 (- (- target-length 3) (.length base)))
                               "...")))
        (ensure-string-breaks "/")
        (ensure-string-breaks "."))))

(defn- complete-item-button [ item-info ]
  (post-button {:desc "Complete Item"
                :target (str "/item/" (item-info :item_id) "/complete")}
               img-check))

(defn- restore-item-button [ item-info ]
  (post-button {:desc "Restore Item"
                :target (str "/item/" (item-info :item_id) "/restore")}
               img-restore))

(defn- delete-item-button [ item-info list-id ]
  (post-button {:desc "Delete Item"
                :target (str "/item/" (item-info :item_id) "/delete")}
               img-trash))

(defn- snooze-item-button [ item-info body ]
  [:a {:href (shref "" {:modal "snoozing" :snoozing-item-id (item-info :item_id)})} body])

(defn- item-priority-button [ item-id new-priority image-spec writable? ]
  (if writable?
    (post-button {:target (str "/item/" item-id "/priority")
                  :args {:new-priority new-priority}
                  :desc "Set Item Priority"}
                 image-spec)
    image-spec))

(defn- render-item-priority-control [ item-id priority writable? ]
  (if (request-date/valentines-day?)
    (if (<= priority 0)
      (item-priority-button item-id 1 img-heart-pink writable?)
      (item-priority-button item-id 0 img-heart-red writable?))
    (if (<= priority 0)
      (item-priority-button item-id 1 img-star-gray writable?)
      (item-priority-button item-id 0 img-star-yellow writable?))))

(defn- render-new-item-form [ list-id editing-item? ]
  (let [ sublists (data/get-view-sublists (auth/current-user-id) list-id)]
    (form/form-to
     {:class "new-item-form"}
     [:post (shref "/list/" list-id)]
     (if (= (count sublists) 0)
       (form/hidden-field "item-list-id" list-id)
       [:select {:id "item-list-id" :name "item-list-id"}
        (form/select-options (map (fn [ sublist ]
                                    [ (:desc sublist) (:sublist_id sublist)])
                                  sublists))])
     (form/text-field (cond-> {:maxlength "1024"
                               :placeholder "New Item Description"
                               :autocomplete "off"
                               :onkeydown "window._toto.onNewItemInputKeydown(event)"}
                        (not editing-item?) (assoc "autofocus" "on"))
                      "item-description")
     (form/hidden-field "item-priority" "0")
     [:button.high-priority-submit {:type "button"
                                    :onclick "window._toto.submitHighPriority()"}
      img-star-yellow])))

(def url-regex #"(https?|ftp|file)://[-a-zA-Z0-9+&@#/%?=~_|!:,.;]*[-a-zA-Z0-9+&@#/%=~_|]")

(defn- render-url [ [ url ] ]
  [:a.item-link { :href url :target "_blank" } (shorten-url-text url 60)])

(defn- render-item-text-segment [ item-text-segment ]
  (clojure.string/join " " (map #(ensure-string-breakpoints % 15)
                                (clojure.string/split item-text-segment #"\s"))))

(defn- render-item-text [ item-text ]
  (interleave (conj (vec (map #(str " " (render-item-text-segment (.trim %)) " ") (clojure.string/split item-text url-regex))) "")
              (conj (vec (map render-url (re-seq url-regex item-text))) "")))

(defn- render-age [ days ]
  (cond (> days 720) (str (quot days 360) "y")
        (> days 60) (str (quot days 30) "m")
        :else (str days "d")))

(defn item-drag-handle [ class item-info ]
  [:div.item-drag-handle {:itemid (:item_id item-info)
                          :class class}
   img-drag-handle])

(defn drop-target [ item-ordinal ]
  [:div.order-drop-target {:ordinal item-ordinal :priority "0"} "&nbsp;"])

(defn- render-todo-item [ view-list-id list-id item-info writable? editing? ]
  (let [{item-id :item_id
         is-complete? :is_complete
         is-deleted? :is_deleted
         priority :priority
         snoozed-until :snoozed_until
         currently-snoozed :currently_snoozed
         created-by-id :created_by_id
         created-by-email :created_by_email
         created-by-name :created_by_name}
        item-info]
    [:div.item-row.order-drop-target
     (cond-> {:id (str "item_row_" item-id)
              :itemid item-id
              :listid list-id
              :ordinal (:item_ordinal item-info)
              :priority priority
              :class (class-set {"editing" editing?
                                 "display" (not editing?)
                                 "high-priority" (> priority 0)
                                 "snoozed" currently-snoozed})}
       writable? (assoc :edit-href (shref "/list/" view-list-id
                                          { :edit-item-id item-id })))
     (when writable?
       (list
        (item-drag-handle "left" item-info)
        [:div.item-control.complete {:id (str "item_control_" item-id)}
         (if editing?
           (delete-item-button item-info list-id)
           (if (or is-complete? is-deleted?)
             (restore-item-button item-info)
             (complete-item-button item-info)))]))
     [:div.item-control.priority.left
      (render-item-priority-control item-id priority writable?)]
     [:div.item-description {:itemid item-id}
      (if editing?
         [:input (cond-> {:value (item-info :desc)
                          :type "text"
                          :name "description"
                          :item-id item-id
                          :view-href (shref "/list/" view-list-id without-modal)
                          :onkeydown "window._toto.onItemEditKeydown(event)"}
                   editing? (assoc "autofocus" "on"))]
        (let [desc (item-info :desc)]
          [:div {:id (str "item_" item-id)
                 :class (class-set {"deleted-item" is-deleted?
                                    "completed-item" is-complete?})}
           (render-item-text desc)
           (snooze-item-button item-info [:span.pill
                                          (render-age (:age_in_days item-info))
                                          (when currently-snoozed
                                            (list
                                             ", snoozed: " (format-date snoozed-until)))])
           (when (not (= created-by-id (auth/current-user-id)))
             [:span.pill { :title created-by-email }
              created-by-name])]))]
     [:div.item-control.priority.right
      (render-item-priority-control item-id priority writable?)]
     (item-drag-handle "right" item-info)]))

(defn- render-query-select [ id current-value ]
  [:select { :id id :name id :onchange "this.form.submit()"}
   (form/select-options [[ "-" "-"]
                         ["1d" "1"]
                         ["7d" "7"]
                         ["30d" "30"]
                         ["90d" "90"] ]
                        (if (nil? current-value)
                          "-"
                          (str current-value)))])

(defn- render-todo-list-query-settings [ list-id completed-within-days snoozed-for-days ]
  [:div.query-settings
   (form/form-to { :class "embedded "} [:get (shref "/list/" list-id)]
                 [:div.control-segment
                  [:a {:href (shref "/list/" list-id {:view "completions"})}
                   "[recently completed]"]]
                 [:div.control-segment
                  [:a {:href (shref "/list/" list-id "/details")}
                   "[list details]"]]
                 [:div.control-segment
                  [:a {:href (shref (str list-id) {:view :remove})}
                   " [default view]"]]
                 [:div.control-segment
                  [:label {:for "cwithin"}
                   "Completed within: "]
                  (render-query-select "cwithin" completed-within-days)]
                 [:div.control-segment
                  [:a { :href (shref "/list/" list-id {:modal "update-from"} ) } "[copy from]"]])])

(defn- render-todo-list-completion-query-settings [ list-id completed-within-days ]
  [:div.query-settings
   (form/form-to { :class "embedded "} [:get (shref "/list/" list-id "/completions")]
                 [:div.control-segment
                  [:a {:href (shref "/list/" list-id "/details")}
                   "[list details]"]]
                 [:div.control-segment
                  [:a {:href (shref (str list-id) {:view :remove})}
                   " [default view]"]]
                 [:div.control-segment
                  [:label {:for "clwithin"}
                   "Completed within: "]
                  (render-query-select "clwithin" completed-within-days)])])

(defn- render-empty-list []
  [:div.empty-list
   [:h1
    "Nothing to do right now!"]
   [:p
    "To get started, you can add new items in the box above."]])

(defn- render-snoozed-item-warning [ n-snoozed-items ]
  [:div.snoozed-item-warning
   n-snoozed-items " more item" (if (= n-snoozed-items 1) "" "s" ) " snoozed for later. "
   "Click " [:a {:href (shref "" {:sfor "99999"})} "here"] " to show."])

(defn- todo-list-details [ view-list-id list-id edit-item-id writable? completed-within-days snoozed-for-days ]
  (let [pending-items (data/get-pending-items list-id completed-within-days snoozed-for-days)
        n-snoozed-items (count (filter :visibly_snoozed pending-items))
        display-items (remove :visibly_snoozed pending-items)]

    {:is-empty? (= (count display-items) 0)
     :n-snoozed-items n-snoozed-items

     :high-priority
     (map #(render-todo-item view-list-id list-id % writable? (= edit-item-id (:item_id %)))
          (filter #(> (:priority %) 0) display-items))

     :normal-priority
     (map #(render-todo-item view-list-id list-id % writable? (= edit-item-id (:item_id %)))
          (filter #(<= (:priority %) 0) display-items))}))

(defn- render-single-todo-list [ view-list-id list-id edit-item-id writable? completed-within-days snoozed-for-days ]
  (let [details (todo-list-details view-list-id list-id edit-item-id writable? completed-within-days snoozed-for-days)]
    (list
     [:div.toplevel-list
      (if (:is-empty? details)
        (render-empty-list)
        (concat
         (:high-priority details)
         (:normal-priority details)))]
     (when (and writable? (> (:n-snoozed-items details) 0))
       (render-snoozed-item-warning (:n-snoozed-items details))))))

(defn- render-empty-view [ list-id ]
  [:div.empty-list
   [:h1
    "Empty list view!"]
   [:p
    "This is a todo list view, where it is possible to see the contents of"
    " more than one list on a single page. To make this work, you need to "
    " add a few lists to the view, which can be done "
    [:a {:href (shref "/list/" list-id "/details")} "here"] "."]])

(defn- render-todo-list-view-section [ sublist-details key ]
  (let [ items (key sublist-details ) ]
    (when (> (count items) 0)
      [:div.list-view-section
       [:h2
        [:a
         {:href (shref "/list/" (:sublist_id sublist-details))}
         (:desc sublist-details)]]
       items])))

(defn- render-todo-list-view [ list-id edit-item-id writable? completed-within-days snoozed-for-days ]
  (let [ sublists (map #(merge % (todo-list-details list-id (:sublist_id %) edit-item-id writable? completed-within-days snoozed-for-days))
                       (data/get-view-sublists (auth/current-user-id) list-id))]
    [:div.toplevel-list
     (cond
       (= 0 (count sublists))
       (render-empty-view list-id)

       (every? true? (map :is-empty? sublists))
       (render-empty-list)

       :else
       (list
        (map #(render-todo-list-view-section % :high-priority) sublists)
        (map #(render-todo-list-view-section % :normal-priority) sublists)

        (let [ total-snoozed-items (apply + (map :n-snoozed-items sublists))]
          (when (and writable? (> total-snoozed-items 0))
            (render-snoozed-item-warning total-snoozed-items)))))]))

(defn- message-recepient? []
  (or (= 16 (auth/current-user-id))
      (= 17 (auth/current-user-id))))

(defn- render-valentines-day-banner []
  (when (message-recepient?)
    [:div.valentines-day-banner
     img-heart-red
     (if (request-date/valentines-day?)
       " Happy Valentines Day!!! I Love You! "
       " I Love You, Teresa! ")
     img-heart-red]))

(defn- render-todo-list [ list-id edit-item-id writable? completed-within-days snoozed-for-days ]
  (scroll-column
   "todo-list-scroller"
   (when writable?
     (render-new-item-form list-id (boolean edit-item-id)))
   (list
    (render-valentines-day-banner)
    (if (:is_view (data/get-todo-list-by-id list-id))
      (render-todo-list-view list-id edit-item-id writable? completed-within-days snoozed-for-days )
      (render-single-todo-list list-id list-id edit-item-id writable? completed-within-days snoozed-for-days))
    (when writable?
      (render-todo-list-query-settings list-id completed-within-days snoozed-for-days)))))

(defn- render-empty-completion-list [ list-id ]
  (let [n-items (data/get-item-count list-id)]
    [:div.empty-list
     [:h1
      "Nothing here right now!"]
     [:p
      (if (= n-items 0)
        "As you add items and complete them, they will appear here."
        "Try expanding the query to see earlier completed items.")]]))

(defn- completed-items [ list-id completed-within-days ]
  (let [ list-info (data/get-todo-list-by-id list-id) ]
    (sort-by :updated_on
             (mapcat (fn [ sublist-info ]
                       (map #(assoc % :sublist_desc (:desc sublist-info))
                            (data/get-completed-items (:sublist_id sublist-info) completed-within-days)))
                     (if (:is_view list-info)
                       (data/get-view-sublists (auth/current-user-id) list-id)
                       [ { :sublist_id list-id :desc (:desc list-info)}])))))

(defn- render-completed-item-list [ list-id completed-within-days ]
  (let [ completed-items (completed-items list-id completed-within-days)]
    (if (= (count completed-items) 0)
      (render-empty-completion-list list-id)
      (map
       (fn [ todo-item ]
         [:div.item-row {:class (class-set
                                 {"high-priority" (> (:priority todo-item) 0)})}
          [:div.item-description
           [:div
            (render-item-text (:desc todo-item))
            [:span.pill (:sublist_desc todo-item)]
            (when (> (:priority todo-item) 0)
              (if (request-date/valentines-day?)
                img-heart-red
                img-star-yellow))]]])
       completed-items))))

(defn render-todo-list-completions [ list-id params ]
  (let [min-list-priority (or (parsable-integer? (:min-list-priority params)) 0)
        completed-within-days (or (parsable-integer? (:clwithin params)) 1)]
    (render-page {:title ((data/get-todo-list-by-id list-id) :desc)
                  :page-data-class "todo-list-completions"
                  :sidebar (sidebar-view/render-sidebar-list-list list-id min-list-priority 0)}
                 (scroll-column
                  "todo-list-completion-scroller"
                  [:h3
                   [:a { :href (shref (str "/list/" list-id) {:view :remove}) } img-back-arrow]
                   "Items Completed Since: " (format-date (add-days (current-time) (- completed-within-days)))]
                  [:div.toplevel-list
                   (render-completed-item-list list-id completed-within-days)
                   (render-todo-list-completion-query-settings list-id completed-within-days)]))))

(defn- render-snooze-modal [ params list-id ]
  (let [ snoozing-item-id (parsable-integer? (:snoozing-item-id params))]
    (defn render-snooze-choice [ label snooze-days shortcut-key ]
      (post-button {:desc (str label " (" shortcut-key ")")
                    :target (str "/item/" snoozing-item-id "/snooze")
                    :args {:snooze-days snooze-days}
                    :shortcut-key shortcut-key
                    :next-url (shref "/list/" list-id without-modal)}
                   (str label " (" shortcut-key ")")))
    (render-modal
     {:title "Snooze item until later"}
     [:div.snooze-choices
      (map (fn [ [ label snooze-days shortcut-key] ]
               (render-snooze-choice label snooze-days shortcut-key))
           [["Tomorrow" 1 "1"]
            ["In Three Days" 3 "2"]
            ["Next Week"  7 "3"]
            ["Next Month" 30 "4"]])]
     (when (:currently_snoozed (data/get-item-by-id snoozing-item-id))
       [:div.snooze-hoices
        [:hr]
        (render-snooze-choice "Unsnooze" 0 "0")]))))

(defn- render-list-select [ id excluded-list-id ]
  [:select { :id id :name id }
   (form/select-options (map (fn [ list-info ]
                               [ (:desc list-info) (:todo_list_id list-info)])
                             (remove
                              #(= excluded-list-id (:todo_list_id %))
                              (data/get-todo-lists-by-user (auth/current-user-id)))))])

(defn render-update-from-modal [ params list-id ]
  (render-modal
   {:title "Update From"
    :form-post-to (shref "/list/" list-id "/copy-from" without-modal)}
   "Source:"
   (render-list-select "copy-from-list-id" (parsable-integer? list-id))
   [:div.modal-controls
    [:input {:type "submit" :value "Copy List"}]]))

(defn- render-todo-list-item-page [ selected-list-id params ]
  (let [edit-item-id (parsable-integer? (:edit-item-id params))
        min-list-priority (or (parsable-integer? (:min-list-priority params)) 0)
        completed-within-days (or (parsable-integer? (:cwithin params)) 0)
        snoozed-for-days (or (parsable-integer? (:sfor params)) 0)]
    (render-page {:title ((data/get-todo-list-by-id selected-list-id) :desc)
                  :page-data-class "todo-list"
                  :sidebar (sidebar-view/render-sidebar-list-list selected-list-id min-list-priority snoozed-for-days)
                  :modals {"snoozing" #(render-snooze-modal params selected-list-id)
                           "update-from" #(render-update-from-modal params selected-list-id)}}
                 (render-todo-list selected-list-id edit-item-id true completed-within-days snoozed-for-days))))

(defn render-todo-list-page [ selected-list-id params ]
  (if (= (:view params) "completions")
    (render-todo-list-completions selected-list-id params)
    (render-todo-list-item-page selected-list-id params)))

(defn render-todo-list-public-page [ params ]
  (let [ { list-id :list-id } params ]
    (when (and (data/list-public? list-id)
               (not (data/list-owned-by-user-id? list-id (auth/current-user-id))))
      (render-page {:title ((data/get-todo-list-by-id list-id) :desc)
                    :page-data-class "todo-list"}
                   (render-todo-list list-id nil false 0 0)))))
