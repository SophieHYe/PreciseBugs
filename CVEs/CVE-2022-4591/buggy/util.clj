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


(ns toto.core.util
  (:require [clojure.tools.logging :as log]
            [clojure.java.jdbc :as jdbc]
            [hiccup.util :as util]))

(defn add-shutdown-hook [ shutdown-fn ]
  (.addShutdownHook (Runtime/getRuntime)
                    (Thread. (fn []
                               (shutdown-fn)))))

(defmacro get-version []
  ;; Capture compile-time property definition from Lein
  (System/getProperty "toto.version"))

(defmacro unless [ condition & body ]
  `(when (not ~condition)
     ~@body))

(defn string-empty? [ str ]
  (or (nil? str)
      (= 0 (count (.trim str)))))

(defn partition-string [ string n ]
  "Partition a full string into segments of length n, returning a
  sequence of strings of at most that length."
  (map (partial apply str) (partition-all n string)))

(defn in?
  "true if seq contains elm"
  [seq elm]
  (some #(= elm %) seq))

(defn assoc-if [ map assoc? k v ]
  (if assoc?
    (assoc map k v)
    map))

(defn string-leftmost
  ( [ string count ellipsis ]
      (let [length (.length string)
            leftmost (min count length)]
        (if (< leftmost length)
          (str (.substring string 0 leftmost) ellipsis)
          string)))

  ( [ string count ]
      (string-leftmost string count "")))

(defn parsable-string? [ maybe-string ]
  "Returns the parsable text content of the input paramater and false
  if there is no such content."
  (and
   (string? maybe-string)
   (let [ string (.trim maybe-string) ]
     (and (> (count string) 0)
          string))))

(defn parsable-integer? [ maybe-string ]
  "Returns the parsable integer value of the input parameter and false
  if there is no such integer value."
  (if-let [ string (parsable-string? maybe-string) ]
    (try
      (Integer/parseInt string)
      (catch Exception ex
        false))))

(defn uri-path? [ uri ]
  "Returns only the path of the URI, if it is a parsable URI and false
  otherwise."
  (and
   uri
   (try
     (.getPath (java.net.URI. uri))
     (catch java.net.URISyntaxException ex
       (log/error "Invalid URI" uri)
       false))))

;;; Date utilities

(defn current-time []
  (java.util.Date.))

(defn add-days
  "Given a date, advance it forward n days, leaving it at the beginning
  of that day in the JVM default time zone. An hour-of-day can
  optionally be specified."
  ( [ date days ] (add-days date days 0))
  ( [ date days hour-of-day]
   (let [c (java.util.Calendar/getInstance)]
     (.setTime c date)
     (.add c java.util.Calendar/DATE days)
     (.set c java.util.Calendar/HOUR_OF_DAY hour-of-day)
     (.set c java.util.Calendar/MINUTE 0)
     (.set c java.util.Calendar/SECOND 0)
     (.set c java.util.Calendar/MILLISECOND 0)
     (.getTime c))))

;;; Configuration properties

(defn config-property
  ( [ name ] (config-property name nil))
  ( [ name default ]
      (let [prop-binding (System/getProperty name)]
        (if (nil? prop-binding)
          default
          (if-let [ int (parsable-integer? prop-binding) ]
            int
            prop-binding)))))

;;; Exception barrier

(defn exception-barrier
  ([ fn label ]
   #(try
      (fn)
      (catch Exception ex
        (log/error ex (str "Uncaught exception: " label))))))

;;; Thread Naming

(defn call-with-thread-name [ fn name ]
  (let [thread (Thread/currentThread)
        initial-thread-name (.getName thread)]
    (try
      (.setName thread name)
      (fn)
      (finally
        (.setName thread initial-thread-name)))))

(defmacro unless [ condition & body ]
  `(when (not ~condition)
     ~@body))

(defmacro with-thread-name [ thread-name & body ]
  `(call-with-thread-name (fn [] ~@body) ~thread-name))
