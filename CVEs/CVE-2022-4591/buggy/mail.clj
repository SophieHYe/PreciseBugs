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
  (:use hiccup.core)
  (:require [clojure.tools.logging :as log]
            [postal.core :as postal]))

(defn send-email [config message-info]
  (let [smtp (:smtp config)
        {to :to subject :subject content :content params :params} message-info
        html-content (html [:html (if (fn? content)
                                    (content (merge config (or params {})))
                                    content)])]
    (log/info "Sending mail to " to " with subject: " subject)
    (cond
      (not (:enabled smtp))
      (do
        (log/warn "E-mail disabled. Message not sent. Message text: ")
        (log/warn html-content))

      (or (nil? to) (= (count to) 0))
      (do
        (log/warn "No destination e-mail address. Message not send. Message text: ")
        (log/warn html-content))

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
