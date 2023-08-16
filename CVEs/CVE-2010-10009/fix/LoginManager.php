<?php
	/*
	 *	Copyright 2007 John Oren
	 *
	 *	Licensed under the Apache License, Version 2.0 (the "License");
	 *	you may not use this file except in compliance with the License.
	 *	You may obtain a copy of the License at
	 *		http://www.apache.org/licenses/LICENSE-2.0
	 *	Unless required by applicable law or agreed to in writing, software
	 *	distributed under the License is distributed on an "AS IS" BASIS,
	 *	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	 *	See the License for the specific language governing permissions and
	 *	limitations under the License.
	 */


	require_once($path."OpenSiteAdmin/scripts/classes/SecurityManager.php");

	/**
	 * Handles processing and security for the site's login process.
	 *
	 * @author John Oren
	 * @version 1.2 July 31, 2008
	 */
	class LoginManager {
		/**
		 * @static
		 * @final
		 * @var The maximum number of login attempts that can be made before a user's account is suspended.
		 */
		const MAX_LOGIN_ATTEMPTS = 5;


        /**
		 * @static
		 * @final
		 * @var Error code constant - the provided username and\or password was invalid.
		 */
		const INVALID = 1;
		/**
		 * @static
		 * @final
		 * @var Error code constant - no errors where encountered.
		 */
        const NONE = 2;
        /**
		 * @static
		 * @final
		 * @var Error code constant - The specified account has been suspended.
		 */
		const SUSPENDED = 3;
		/**
		 * @static
		 * @final
		 * @var Error code constant - an unknown error was encountered.
		 */
		const UNKNOWN = 4;

        protected $userID;

		/**
		 * Attempts to log a user into the site's administrative system.
		 *
		 * @param STRING $user Username to use to login.
		 * @param STRING $pass Password to attempt to use with the given user.
		 * @param STRING $remember If set to "yes", the user's login information will be saved in cookies if it validates sucessfully.
		 * @param BOOLEAN $isCookie True if the provided data is coming from cookie data (cookie passwords are already encrypted).
		 * @return INTEGER One of the error code constants defined in this class.
		 */
		function login($user, $pass, $remember="no", $isCookie=false) {
            $user = htmlspecialchars(addslashes($user));
            $pass = htmlspecialchars(addslashes($pass));
			$sql = "select `users`.*, `libraries`.`interTOME` from `users` JOIN `libraries` ON `users`.`libraryID` = `libraries`.`ID` where `username` LIKE '$user'";
			$result = DatabaseManager::checkError($sql);
			if(DatabaseManager::getNumResults($result) === 0) {
				return LoginManager::INVALID;
			}
			$row = DatabaseManager::fetchAssoc($result);
			if($row["active"] == "1") {
				if($isCookie) {
					$pass2 = SecurityManager::encrypt($row["password"], $row["salt"]);
				} else {
					$pass2 = $row["password"];
				}
				if(SecurityManager::encrypt($pass, $row["password_salt"]) == $pass2 || crypt($pass, $pass2) == $pass2) {
                    if(crypt($pass, $pass2) == $pass2) {
                        //temporary conversion script
                        $salt = SecurityManager::generateSalt();
                        $password = SecurityManager::encrypt($pass, $salt);
                        DatabaseManager::checkError("update `users` set `password` = '".$password."', `password_salt` = '".$salt."' where `ID` = ".$row["ID"]);
                    }
					$_SESSION["ID"] = $row["ID"];
                    $this->userID = $row["ID"];
                    $_SESSION["libraryID"] = $row["libraryID"];
                    $_SESSION["interTOME"] = $row["interTOME"];
					$_SESSION["username"] = $row["username"];
					$_SESSION["permissions"] = $row["permissions"];
                    $_SESSION["semester"] = $row["semester"];
					if($remember == "yes") {
						//60*60*24*365 = 1 year
						setcookie( "username", $row["username"], time()+(60*60*24*365), "/", SITE_NAME);
						setcookie( "password", $pass2, time()+(60*60*24*365), "/", SITE_NAME);
					}
					return LoginManager::NONE;
				} else {
					return LoginManager::INVALID;
				}
			} else {
				return LoginManager::SUSPENDED;
			}
			return LoginManager::UNKNOWN;
		}

        function getUserID() {
            return $this->userID;
        }

		/**
		 * Attempts to suspend the account associated with the given username.
		 *
		 * @param STRING $user Username for the account to suspend.
		 * @return VOID
		 */
		function suspend($user) {
			$sql = "select * from `users` where `username` = '$user'";
			$result = DatabaseManager::checkError($sql);
			if(DatabaseManager::getNumResults($result) === 0) {
				return;
			}
			$row = DatabaseManager::fetchAssoc($result);
			$sql = "update `users` set `active` = '0' where `ID` = '".$row['ID']."'";
			DatabaseManager::checkError($sql);
		}
	}
?>
