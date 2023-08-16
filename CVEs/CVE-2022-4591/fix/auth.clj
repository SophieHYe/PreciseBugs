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

(ns toto.view.auth
  (:use toto.core.util
        compojure.core)
  (:require [clojure.tools.logging :as log]
            [cemerick.friend.credentials :as credentials]
            [cemerick.friend :as friend]
            [cemerick.friend.workflows :as workflows]
            [toto.data.data :as data]
            [toto.core.mail :as mail]))


(defn get-user-id-by-email [ email ]
  (if-let [ user-info (data/get-user-by-email email) ]
    (user-info :user_id)
    nil))

(defmacro authorize-expected-roles [ & body ]
  `(friend/authorize #{:toto.role/user} ~@body))

(defn report-unauthorized []
  (friend/throw-unauthorized (friend/current-authentication) {}))

(defn current-identity []
  (if-let [auth (friend/current-authentication)]
    (:identity auth)))

(defn current-user-id []
  (if-let [ cauth (friend/current-authentication) ]
    (:user-id cauth)))


(defn authorize-toto-valid-user [ routes ]
  (-> routes
      (wrap-routes friend/wrap-authorize #{:toto.role/user})))

(defn- password-current? [ user-record ]
  (if-let [expiry (:password_expires_on user-record)]
    (or (.after expiry (current-time))
        (.after (:password_created_on user-record) expiry))
    true))

(defn- account-locked? [ user-record ]
  (> (:login_failure_count user-record) 4))

(defn- get-user-roles [ user-record ]
  (cond (account-locked? user-record)
        #{:toto.role/locked-account}

        (not (password-current? user-record))
        #{:toto.role/password-expired}

        :else
        (clojure.set/union
         #{:toto.role/user} (data/get-user-roles (:user_id user-record)))))

(defn get-auth-map-by-email [ email ]
  (if-let [user-record (data/get-user-by-email email)]
    {:identity email
     :user-record user-record
     :user-id (user-record :user_id)
     :roles (get-user-roles user-record)
     :account-locked (account-locked? user-record)
     :login-failure-count (user-record :login_failure_count)}))

(def ^:dynamic *request-ip* nil)

(defn get-user-by-credentials [ creds ]
  (if-let [auth-map (get-auth-map-by-email (creds :username))]
    (cond
      (credentials/bcrypt-verify (creds :password) (get-in auth-map [:user-record :password]))
      (do
        (data/record-user-login (creds :username) *request-ip*)
        (update-in auth-map [:user-record] dissoc :password))

      :else
      (do
        (data/record-user-login-failure (:user-id auth-map) *request-ip*)
        nil))
    nil))

(defn current-roles []
  (:roles (friend/current-authentication)))

(defn password-change-message [ params ]
  (let [ { :keys [ from-mail ]} params ]
    [:body
     [:h1
      "Password Changed"]
     [:p
      "This mail confirms that you have changed the password for your "
      "account at " [:a {:href (:base-url params)} "Toto"]
      ", the family to-do list manager."]
     [:p
      "If this isn't something you've requested, please contact us"
      " immediately at " [:a {:href (str "mailto:" from-mail)} from-mail] "."]]))

(defn send-password-change-message [ config username ]
  (mail/send-email config
                   {:to [ username ]
                    :subject "Todo - Password Changed"
                    :content password-change-message
                    :params { :from-mail (get-in config [:smtp :from]) }}))

(defn set-user-password [ config username password ]
  (log/info "Changing password for user:" username)
  (data/set-user-password username (credentials/hash-bcrypt password))
  (send-password-change-message config username))

(defn create-user [ email-addr password ]
  (data/add-user email-addr (credentials/hash-bcrypt password)))

(defn password-change-workflow [ config ]
  (fn [{:keys [uri request-method params]}]
    (let [{:keys [ :password :new-password :new-password-2 :username]} params]
      (when (and (= uri "/user/password")
                 (= request-method :post)
                 (get-user-by-credentials params)
                 (not (= password new-password))
                 (= new-password new-password-2))
        (set-user-password config username new-password)
        (workflows/make-auth (get-auth-map-by-email username)
                             {::friend/redirect-on-auth? "/user/password-changed"})))))

(defn wrap-workflow-request-ip [ workflow ]
  (fn [ req ]
    (binding [*request-ip* (:request-ip req)]
      (workflow req))))

(defn wrap-authenticate [ app config unauthorized-handler ]
  (friend/authenticate app
                       {:credential-fn get-user-by-credentials
                        :workflows [(password-change-workflow config)
                                    (wrap-workflow-request-ip
                                     (workflows/interactive-form))]
                        :unauthorized-handler unauthorized-handler}))
