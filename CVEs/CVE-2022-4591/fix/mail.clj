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

(ns toto.core.mail
  (:use toto.core.util)
  (:require [clojure.tools.logging :as log]
            [postal.core :as postal]
            [hiccup.core :as hiccup]
            [hiccup.util :as hiccup-util]))

(defn- escape-email-params [ params ]
  (map-values #(if (string? %)
                 (hiccup-util/escape-html %)
                 "")
              params))

(defn send-email [config message-info]
  (let [smtp (:smtp config)
        {:keys [ to subject content params ]} message-info
        html-content (hiccup/html
                      [:html
                       (content (escape-email-params
                                 (merge {:base-url (:base-url config)}
                                        (or params {}))))])]

    (log/info "Sending mail to " to " with subject: " subject)
    (cond
      (not (:enabled smtp))
      (log/warn "E-mail disabled. Message not sent. Message text: "
                html-content)

      (or (nil? to) (= (count to) 0))
      (log/warn "No destination e-mail address. Message not send. Message text: "
                html-content)

      :else
      (postal/send-message {:host (:host smtp)
                            :user (:user smtp)
                            :pass (:password smtp)
                            :ssl true}
                           {:from (:from smtp)
                            :to to
                            :subject subject
                            :body [{:type "text/html"
                                    :content html-content}]}))))
