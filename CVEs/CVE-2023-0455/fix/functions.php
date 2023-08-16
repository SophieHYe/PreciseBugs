<?php
/**
 * Trim all malicious input to protect SQL Injection and XXS
 * 
 * @since 0.1
 * 
 * @param string|int|bool	$data	data to be trimed.
 * @param bool              $encoding if false then disable htmlspecialchars($data) and stripslashes() function. Default is true.
 * @return string|int|bool	return trimed data
 */
function safe_input($data, $encoding = true) {
	$data = trim($data);
    if($encoding === true) {
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }
	$data = mysqli_real_escape_string($GLOBALS['conn'], $data);
	return $data;
}

/**
 * Convert all applicable characters to HTML entities
 * 
 * From PHP 8.1.0 the default flag is ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
 * So, we make this for all version
 */
function safe_entities($data) {
    return htmlentities($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
}

/**
 * Determine the http or https and append with the domain.
 * 
 * @since 0.1
 * 
 * @return string	http:// or https://
 */
function transfer_protocol() {
	if(isset($_SERVER['HTTPS']) && filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN)) {
		return "https://";
	} else {
		return "http://";
	}
}

/**
 * Return the root domain
 */
function root_domain() {
    return get_options("rootDomain");
}

/**
 * @since 0.1
 * 
 * @return string	Return the full application URL
 */
function full_website_address() {
	return transfer_protocol().root_domain(); 
}

/**
 * Determine the homepage of the application.
 * 
 * @since 0.1
 * 
 * @return bool		True or Fales
 */
function is_home() {
	$get_uri = rtrim($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],"/");
	$homepage = str_ireplace(transfer_protocol(), "", full_website_address());
	// posible other homepage
	$pohp = array($homepage, "www.$homepage", "$homepage/index.php", "www.$homepage/index.php");

	return in_array($get_uri, $pohp);
}

/**
 * Redirect to a specific url
 * 
 * @since 0.1
 * 
 * @param string    $url where the url redirected.
 */
function redirect($url) {
    echo "<script>window.location.href='{$url}';</script>";
}

/**
 * 
 * Discount calculator
 * 
 * @since 0.1
 * 
 * @param float|number        $amount The amount which need to calculate
 * @param string|number $discount   Fixed discount or Percantage. Eg 10 or 10%
 * 
 * @return float   Discounted amount
 */
function calculateDiscount($amount, $discount) {

    if( empty($discount) or $discount === NULL or $discount === "NULL" ) {
        return 0;
    } else if(strpos($discount, "%") > 0) {

        $amount = (float)$amount; 
        $discount = (float)rtrim($discount, "%");
        
        // For parcantage discount
        return (float)round( ($discount/100) * $amount, get_options("decimalPlaces") ); 

    } else {
        
        return (float)round($discount, get_options("decimalPlaces"));

    }

}


function calculatePercentage($amount, $discount) {

    return number_format(( ( $discount / $amount ) * 100 ), 10, ".", "") . "%";

}


/**
 * Generate and return the current page slug
 * 
 * $_SERVER['HTTP_HOST'] returns the main domain
 * $_SERVER['REQUEST_URI'] returns the uri after the domain
 * root_domain() returns the configured domain
 * 
 * @since 0.1
 * 
 * @return string the page slug
 */
function pageSlug() {

    $URI = explode(root_domain(), rtrim($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], "/"));
    $URI = explode("?", $URI[1]);
    return trim($URI[0], '/');

}

/**
 * Tax Calculator
 */
function calculateTax($amount, $taxRate) {

    $amount = (float)$amount; 
    $taxRate = (float)rtrim($taxRate, "%");
    
    return round(($taxRate/100) * $amount, get_options("decimalPlaces") ); 
}

/** 
 * in_arry_r() function for Multidimentional array.
 * 
 * @since 0.1
 * 
 * @param string|int    $needle     The string or number that need to find in the array.
 * @param array         $haystack   The array, where need to search.
 * @param bool          $strict     OPtional. Search the value with strictly (==== or ==). Default is false (==)
 * 
 * @return bool         True or false. If found then return true, otherwise return false. 
*/
function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }
    return false;
}

/**
 * Block unauthorized access.
 * 
 * @since 0.1
 * 
 * @param bool      $stricted   Optional. Default is false. If make it true it will check there are has any HTTP REFERER. If not found, then block the request. 
 * 
 * @return bool     True or False. 
 */
function access_is_permitted($stricted = false) {
    // header array
    $headers = getallheaders();

    //create_log("test");

    if( current_ip_is("Blocked") ) {

        return false;

    }  else if(  get_options("canAccessOnlyPermittedIP") === "1" and !current_ip_is("Permitted") ) {

        return false; 

    }  else if ($stricted) {
            
        if( isset($_SERVER['HTTP_REFERER']) and 
            strpos($_SERVER['HTTP_REFERER'], full_website_address())  === 0 and
            isset($headers["Connection"])
        ) {
            return true; 
        } 
        
    }  else if (isset($headers["Connection"])) { 

        return true;
        
    } else {

        return false;

    }

    
   
}


/**
 * Check if the current user ip is permitted or not
 * 
 * @since 1.0.1
 * 
 * @param string $action Permitted/Blocked. Case sensitive
 * 
 * @return bool true/false
 */

function current_ip_is($action) {

    // Select the ip address
    $check_ip = easySelectA(array(
        "table"     => "firewall",
        "fields"    => "count(*) as count",
        "where"     => array(
            "fw_status = 'Active' and fw_action = '{$action}' and fw_ip_address"  =>  get_ipaddr()
        )
    ));

    if( $check_ip !== false and $check_ip["data"][0]["count"] > 0  ) {
        return true;
    } else {
        return false; 
    }

}

/**
 * Check the login status
 * 
 * @since 0.1
 * 
 * @return  bool    Return true if is login
 */
function is_login() {
    global $table_prefeix; // table prefix;

    // If "Don't Signout on inactivity" is not checked and last activity is more then AUTO_LOGOUT_TIME 
    // Then return false and unset session.
    if( isset($_COOKIE["keepAlive"]) !== true and isset($_SESSION["LAST_ACTIVITY"]) and (time() - $_SESSION["LAST_ACTIVITY"]) > AUTO_LOGOUT_TIME ) {

        session_unset();
        return false;

    }
    

    $sesionUserId = isset($_SESSION["uid"]) ? $_SESSION["uid"] : "";
    $sessionPassAccessKey = isset($_SESSION["sak"]) ? $_SESSION["sak"] : "";
    
    // Select the user
    defined('selectUser') ?: define('selectUser', easySelectA(array(
        "table"     => "users as user",
        "fields"    => "user_email, user_emp_id, user_pass_aaccesskey",
        "where"     => array(
            "user.is_trash = 0 and user_id" => $sesionUserId,
            " and user_pass_aaccesskey" => $sessionPassAccessKey
        )
    )));

    // define the variable
    $sha1 = "";

    if( selectUser !== false and isset($_SESSION["keepAliveOnNetworkChanges"]) and $_SESSION["keepAliveOnNetworkChanges"] === 1 ) {
        $sha1 = sha1(selectUser["data"][0]["user_email"].$_SERVER["HTTP_USER_AGENT"]);
    } else if(selectUser !== false) {
        $sha1 = sha1(selectUser["data"][0]["user_email"].$_SERVER["HTTP_USER_AGENT"].$_SERVER["REMOTE_ADDR"]);
    }

    if(isset($_SESSION["uid"]) and isset($_SESSION["sak"]) and $sha1 === $_SESSION["sak"] and isset($_COOKIE["eid"]) and selectUser["count"] === 1 AND selectUser["data"][0]["user_emp_id"] === $_COOKIE["eid"]) {
        
        // Return true to say that we are now logged in.
        return true; 

    } else {

        // If any internal user try to change the eid then ban him to punish
        // Will make it later.
        //if(selectUser["data"][0]["user_emp_id"] !== $_COOKIE["eid"]) { }

        // if any unathorized sessions are set then unset them.
        if( isset($_SESSION) ) {
            session_unset();
        }

        return false;

    }
}


/**
 * check current user has a specific permission.
 * Example:
 * 
 * current_user_can("accounts.View && accounts.Add");
 * If both permission exists in current user then return true
 * 
 * current_user_can("accounts.View || accounts.Add || accounts.Delete");
 * If any one permission exists in current user then return true.
 * 
 * Do not use || and && in signle function, like this:
 * current_user_can("accounts.View && accounts.Add || accounts.Edit || accounts.Delete")
 * It Will return an unexpected result.
 * 
 * Instead, use like this: (PHP Way)
 * current_user_can("accounts.View && accounts.Add) OR/AND current_user_can(accounts.Edit || accounts.Delete")
 * 
 * @since 0.1
 * 
 * @param string $permission   The permision is need to check.
 * 
 * @return bool If the permission exists then return true. 
 */

 // Select the current user permission
// We actually add this outside of function because, if we select it inside the current_user_can() function it will execute/ or select multitimes.
defined('USER_PERMISSIONS') ?: define('USER_PERMISSIONS', 

    !isset($_SESSION["uid"]) ?: unserialize(
        html_entity_decode(
            easySelectA(array(
                "table"     => "users",
                "fields"    => "user_permissions",
                "where"     => array(
                    "user_id" => isset($_SESSION["uid"]) ? $_SESSION["uid"] : NULL
                )
            ))["data"][0]["user_permissions"]
        )
    )
);

  // Start the function
function current_user_can($permission) {


    //create_log("current_user_can", debug_backtrace());

    // If current user is Super Admin then return it true
    if( is_super_admin() ) {
        return true;
    }

    // Check there is no || and && mark in the $permission string.
    if( strpos($permission, " || ") === false and strpos($permission, " && ") === false ) {
    
        if( is_array(USER_PERMISSIONS) and in_array($permission, USER_PERMISSIONS) ) {
            return true; 
        }
    
    }
    
    // Check the OR Condition.
    // If any permission exists in the list then return true
    if( strpos($permission, " || ") !== false ) {

        foreach(explode(" || ", $permission) as $permission) {
 
            // Check if the permission exists in user's permissions
            // Here we must not use else: return false; with the OR Condition
            // Because the condition can be true on second time loop
            if( is_array(USER_PERMISSIONS) and in_array($permission, USER_PERMISSIONS) ) {
                return true; 
            }

        }

    }
    
    // Check the AND Condition
    // If all permission exists in the list the return true
    if( strpos($permission, " && ") !== false ) {

        $permissionCarrier = array();
        foreach(explode(" && ", $permission) as $permission) {

            // Check if the permission exists in user's permissions
            if( is_array(USER_PERMISSIONS) and in_array($permission, USER_PERMISSIONS) ) {
                $permissionCarrier["true"]  = 1;
            } else {
                $permissionCarrier["false"]  = 0;
            }            
        }

        // If the false array key not exists then return true. 
        if( array_key_exists("false", $permissionCarrier) === false ) {
            return true;
        }

    }
    
}


/**
 * The three function of bellow must not go to above of current_user_can() function.
 */


/**
 * Function to generate menu from array
 * 
 * :::::Permission::::::
 * If no permission set then the menu will be hidden
 * to show all user set the permission:  __? => true
 * If a submenu have permission for a user, then the parents menu will be shown, wither is not set any permission
 * If parents menu dont have access permission to user and if a submenu has the permission then the both of will be not shown
 * 
 * The menus which are include in $menus["hidden"] will be not showen
 * 
 * @since 0.1
 * 
 * @param array $menus  The menu array.
 * 
 * @return string   The menu string.
 */

function generateMenu(array $menus) {

    global $_SETTINGS;
    $generatedMenu = "";

    foreach($menus as $menuName => $MenuContext) {

        $modal = array_key_exists("t_modal", $MenuContext) ? "data-toggle='modal' data-target='{$MenuContext['t_modal']}'" : "";
        $linkCarrier = isset($MenuContext['t_link']) ? $MenuContext['t_link'] : "";
        $iconCarrier = isset($MenuContext['t_icon']) ? $MenuContext['t_icon'] : "";
        $dloadCarrier = isset($MenuContext['dload']) and $MenuContext['dload'] === true ? $MenuContext['dload'] : false;
        $hasPermission = ( 
                            (
                                has_permission($MenuContext) and is_array($MenuContext) and array_key_exists("__?", $MenuContext) === false
                            ) or
                            ( 
                                isset($MenuContext["__?"]) and $MenuContext["__?"] === true 
                            )
                        ) ? true : false;

        // Add the Menu Title
        if( isset($MenuContext["title"]) ) {
            $_SETTINGS["PAGE_TITLE"][$MenuContext["t_link"]] = $MenuContext["title"];
        }
        
        // unset key
        unset(
            $MenuContext["t_link"], 
            $MenuContext["t_icon"], 
            $MenuContext["t_modal"], 
            $MenuContext["dload"], 
            $MenuContext["title"], 
            $MenuContext["__?"]
        );
        
        // If has permission and the menu name is not Hidden
        if($hasPermission and $menuName !== "Hidden") {
            
            $hasSubMenu = ( !empty($MenuContext) and has_permission($MenuContext) ) ? true : false; 
            $subMenuArrowIcon = $hasSubMenu ? "<span class='pull-right-container'> <i class='fa fa-angle-left pull-right'></i> </span>" : "";

            $getContent = ( $dloadCarrier === false and !empty($linkCarrier) and $linkCarrier !== "#" and empty($modal) ) ? "onclick='getContent(this.href, event);'" : "";
        

            $generatedMenu .= $hasSubMenu ? "<li class='treeview'>" : "<li>";
                
                $generatedMenu .= "<a {$getContent} {$modal} href='{$linkCarrier}'>
                        <i class='{$iconCarrier}'></i> 
                        <span>". __($menuName) ."</span>
                        $subMenuArrowIcon
                        </a>";
        
                if($hasSubMenu) {

                    $generatedMenu .= "<ul class='treeview-menu'>";
                    $generatedMenu .= generateMenu($MenuContext);
                    $generatedMenu .= "</ul>";

                }
    
            $generatedMenu .= "</li>";

        } else if($menuName === "Hidden") {

            // If the menu item is hidden
            // then not append these menus on $generatedMenu
            // The recurson is only for get the titles
            generateMenu($MenuContext);

        }
        
    }

    return $generatedMenu;
  
}


/**
 * Generate an select option list for redirect after login settings
 */
function generateSelectOptions(array $menus, $selected = "") {

    foreach($menus as $menuName => $MenuContext) {


        if( isset($MenuContext["t_link"]) and $MenuContext["t_link"] !== "#" ) {

            $selectedOption = ($selected === $MenuContext["t_link"] ) ? "selected" : "";
            echo "<option {$selected} value='{$MenuContext["t_link"]}'>$menuName</option>";

        } else {

            // create the option group
            echo "<optgroup label ='$menuName'>";

            // get the options
            foreach( $MenuContext as $options => $optionContext ) {

                if( isset($optionContext['t_link']) and !empty($optionContext['t_link']) ) {
                    $selectedOption = ($selected === $optionContext["t_link"] ) ? "selected" : "";
                    echo "<option {$selectedOption} value='{$optionContext["t_link"]}'>$options</option>";

                }

            }
            
            echo "</optgroup>";

        }
        
    }
  
}

function has_permission(array $MenuContext, $isNeedCheckUrl = false) {

    // If super Admin then grant all permissions
    if( is_super_admin() ) {
        return true;
    }

    // Generate the current url
    $url = transfer_protocol().strtok($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], "?");

    foreach($MenuContext as $key => $value) {

        // If need to check the url
        if($isNeedCheckUrl) {

            if (  ( isset($value["__?"]) and $value["__?"] === true and $value["t_link"] === $url ) or (is_array($value) and has_permission($value, true)) )  {
          
                return true; 
           }

        } else {

            if (  ( isset($value["__?"]) and $value["__?"] === true ) or (is_array($value) and has_permission($value)) )  {
            
                return true; 

           }
        }

    }

    return false;

}

/** Detarmin if the current user is super admin or not
 * If suport admin, return true, otherwise false
 */
function is_super_admin(){

    // If super Admin then grant all permissions
    if( is_array(USER_PERMISSIONS) and in_array("SuperAdmin", USER_PERMISSIONS) ) {
        return true;
    } else {
        return false;
    }

}



/**
 * check current user has the permission to visit current page.
 * 
 * @since 0.1
 * 
 * @return bool If the permission exists then return true. 
 */
function current_user_can_visit_this_page() {
    global $default_menu;
    $defaultPermissionForAllUser = array ("xhr", "info", "logout", "login", "api/v1", "invoice-print", "images", "print", "css", "js", "barcode");
    
    if( has_permission($default_menu, true) or in_array(pageSlug(), $defaultPermissionForAllUser) ) {
        return true;
    } else {
        return false;
    }

}



/**
 * Insert data into Database.
 * 
 * @since 0.1
 * 
 * @param string	$table			The table name, where the data will insert.
 * @param array		$fieldAndValue	The field and field value array. All fields of table and these value have to be stored.
 * @param array		$duplicateCheck	Optional. For checking the data if exists on the DB. The array is same as easySelect() function's $where.
 * @param bool      @extraInfo      Optional. If needs last_insert_id the set it true. Default is false
 * 
 * @return string|bool	Return true if data is successfully inserted into the table, otherwise it will throw the mysql error.
 */
function easyInsert(
    string $table,
    array $fieldAndValue, 
    array $duplicateCheck=array(), 
    bool $extraInfo = false
    ) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    $fields = "";			// The field variable will store all table field.
    $fieldsValue = "";		// The fieldValue variable will sotre all filed value


    // If the duplicate Checker is enabled, then check if the data is duplicate or exists on the database, 
	// If true then return error massage.
	if(!empty($duplicateCheck) and !isset($_POST["forceInsert"]) ) {
		if (easySelect(
			$table,
            "*",
            array(),
			$duplicateCheck
		) !== false) {
			return "The data is already exists. <input type='checkbox' id='forceInsert' name='forceInsert'/> <label style='cursor: pointer;' for='forceInsert'>Force Insert</label> ";
		}
	}

    foreach($fieldAndValue as $item => $itemsValue) {
        $fields .= $item . ", ";

        /**
         * check if $itemsValue is array or not. if array then include the array value [bool] into the safe_input function
         * It actually use if we dont need the htmlspecialchars() and stripslashes() function in safe_input() function
         * If we set the second parameter false then htmlspecialchars() and stripslashes() function not be used. 
         **/ 
        if(is_array($itemsValue)) {

            $fieldsValue .= "'".safe_input($itemsValue[0], $itemsValue[1]) ."', ";

        } else if( is_null($itemsValue) ) { // if the input value is null

            $fieldsValue .= "NULL, ";

        } else if( strlen($itemsValue) < 1 ) { // If the input value is empty string

            $fieldsValue .= "'', ";

        } else { // And in all other situation insert the input value.

            $fieldsValue .= "'".safe_input($itemsValue) ."', ";

        }
    }

    $fields = rtrim($fields, ", ");
    $fieldsValue = rtrim($fieldsValue, ", ");
	
	// Build the query
   $sqlQuery = "INSERT INTO {$table_prefeix}{$table} ($fields) VALUES ($fieldsValue)"; 

	// Run the Query
	$conn->query($sqlQuery);

	// Get the last insert ID. 
    $last_id = $conn->insert_id;
    
	//check if last $conn->error is empty then return true. Otherwise return the error message.
    if(empty($conn->error)) {

        // If we need any extra info
        if($extraInfo === true) {

            return array (
                "status" => "success",
                "last_insert_id" => $last_id
            );

        } else {
            return true; 
        }
        
    } else {

        // insert log 
        create_log($conn->error, debug_backtrace());
        // create_log($sqlQuery, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        // return error msg
        return $conn->error;
    }

}


/**
 * Function to fetch data from database
 * 
 * Example:
 * easySelect (
 * 		"$table", 	//The table
 * 		"$field",	//The selector ($field), * or column name
 *      array (     // Join clause
 *          "left join ... bla bla bla"
 *      ),
 * 		array (		//Where Clause
 * 			"group_name" => "love",
 * 			" OR group_id" => "1",
 * 		),
 * 		"group_id ASC"	//Order by clause
 * )
 * 
 * @since 0.1
 * 
 * @param string	$table		The table name, from where the data will fetch.
 * @param string	$field		Optional. The selector or field of the table. Like * or the specific column name or multiple column seperating by comman(,)
 * @param array     $join       Optional. Join multiple table
 * @param array		$where		Optional. The where clause. Condition of selecting data
 * @param array 	$orderBy	Optional. How the data will show. ASC OR DESC
 * @param array     $limit      Optional. Must be in numeric value. 
 * 
 * @return array/bool			Return all data with array format. [count] return number of records and [data] retruns all records. Return error massage if the query is wrong. Return False if there is no data.
 */
function easySelect(
    string $table, 
    string $field = "", 
    array $join=array(), 
    array $where=array(), 
    array $orderBy=array(), 
    array $limit=array()
    ) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    $dataFromDB = [];

    // if field empty then input a star (*)
    if(empty($field)) {
        $field = "*";
    }

    // Build the query
    $sqlQuery = "SELECT SQL_CALC_FOUND_ROWS {$field} FROM {$table_prefeix}{$table}";

/*     // Check if the table exists
    if($conn->query($sqlQuery) === false) {
        return "Table {$table} or field {$field} doesn't exist";
    } */

    // Join clues for joining multiple table
    $joinClues = "";
    if(!empty($join)) {

        foreach($join as $joinVar) {
            $joinClues .= "{$joinVar} ";
        }

    }

    $sqlQuery .= " {$joinClues}";

    // Where Cluase
    if(!empty($where)) {

        // If any where clause exists then append it with the query.
        $whereClause = "";
        
        foreach($where as $whereField => $whereValue) {

            // If where value is empty then its not need to include in wherecluase. 
            if($whereValue !== "" and $whereValue !== "%" and $whereValue !== "%%" ) {

                if(!is_numeric($whereField) and stripos($whereField, "LIKE") > 0) { // Check if there any LIKE keyword in $whereField

                    $whereClause .= "{$whereField} '".safe_input($whereValue)."'";
    
                } else if(!is_numeric($whereField)) {
    
                    $whereClause .= "{$whereField} = '".safe_input($whereValue)."'";
                    
                } elseif(stripos($whereValue, "between") > 0) { // Check if the between keyword exists and data is not empty then the whare cluase will add with query. 

                    if(stripos($whereValue, "BETWEEN '' and") < 1) {

                        $whereClause .= " $whereValue";
                    }

                } else {
    
                    $whereClause .= " $whereValue";
                }

            }
            
        }

        // Append the where clause with $sqlQuery
        $sqlQuery .= empty($whereClause) ? "" : " WHERE {$whereClause}";

    }

    // OrderBy Clause
    if(!empty($orderBy)) {

        // If any order by clause exists then append it with the query.
        $orderByClause = "";
        foreach($orderBy as $orderByField => $orderByValue) {

            // Only allow ASC AND DESC string in $orderByValue
            if(!in_array(strtoupper($orderByValue), array("ASC", "DESC"))) {
                return "Invalied order by clause. Allow only ASC OR DESC";
            }
            
            $orderByClause .= "{$orderByField} {$orderByValue}, ";
            
        }
        $orderByClause = rtrim($orderByClause, ", ");
        // Append the Orderby Clause with $sqlQuery
        $sqlQuery .= " ORDER BY {$orderByClause}";
    }

    // Limit Clause
    if(!empty($limit)) {

        // If any order by clause exists then append it with the query.
        if(!is_numeric($limit["start"]) or !is_numeric($limit["length"])) {
            return "Limit clause must be a numeric value.";
        }

        $limitStart = intval($limit["start"]);
        $limitLength = intval($limit["length"]);
     
        // Append the Limit Clause with $sqlQuery
        $sqlQuery .= " LIMIT {$limitStart}, {$limitLength}";
    }

   /*  echo  $sqlQuery ;
    exit(); */

    // Run the query and store the result into getResult variable.
    $getResult = $conn->query($sqlQuery);

    //create_log($sqlQuery);

    // Check If the syntax has any error then throw an the error.
    if($getResult === false) {
        
        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error; // Return the error
    }

    // Check if there is more then Zero (0) result.
    if($getResult->num_rows > 0) {
        

        // $countTotalFilteredRow = "SELECT count(*) as totalFilteredRow FROM {$table_prefeix}{$table}";
        // $countTotalFilteredRow .= " {$joinClues}";
        // $countTotalFilteredRow .= empty($whereClause) ? "" : " WHERE {$whereClause}";
        // $countTotalFilteredRow = $conn->query($countTotalFilteredRow)->fetch_all(true)[0]["totalFilteredRow"];

        $countTotalFilteredRow = $conn->query("SELECT found_rows() as totalFilteredRow;")->fetch_all(true)[0]["totalFilteredRow"];

        // return all data in array format
        return array(
            "has_data" => true,
            "count" => (int)$countTotalFilteredRow,
            "data" => $getResult->fetch_all(true)
        );

    } else {
        // Return false if there is no data.
        return false;

        /**
         * return array(
         *       "has_data" => false,
           *     "data" => $getResult->fetch_all(true)
         *   );
         */


    }
    
}

function easySelectA(array $query) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    $dataFromDB = [];
    $table = $fields = $join = $where = $groupby = $orderBy = $limit = "";

    // Lower case all key name.
    $query = array_change_key_case($query, CASE_LOWER);

    // Check if the table is declared or empty
    if( !isset($query["table"]) ) {
        return "Table name must be declared";
    } else if(empty($query["table"]) or !is_string($query["table"])) {
        return "The table must not be empty and must be a string";
    } else {
        $table = $query["table"];
    }

    // Check the filed
    if( isset($query["fields"]) and !is_string($query["fields"]) ) {
        return "Fields must be string";
    } else if( !empty($query["fields"]) ) {
        $fields = $query["fields"];
    } else {
        $fields = "*";
    }

    // Check join
    if( isset($query["join"]) and !is_array($query["join"]) ) {
        return "Join claues must be array";
    } else if(isset($query["join"])) {
        $join = $query["join"];
    }

    // Check where cluse
    if( isset($query["where"]) and !is_array($query["where"]) ) {
        return "Where claues must be array";
    } elseif(isset($query["where"])) {
        $where = $query["where"];
    }

    // Check group by cluse
    if( isset($query["groupby"]) and !is_string($query["groupby"]) ) {
        return "Group by claues must be string";
    } elseif(isset($query["groupby"])) {
        $groupby = $query["groupby"];
    }

    // Check orderBy cluse
    if( isset($query["orderby"]) and !is_array($query["orderby"]) ) {
        return "Order by claues must be array";
    } elseif(isset($query["orderby"])) {
        $orderBy = $query["orderby"];
    }

    // Check Limit 
    if( isset($query["limit"]) and !is_array($query["limit"]) ) {
        return "Order by claues must be array";
    } elseif(isset($query["limit"])) {
        $limit = $query["limit"];
    }


    // Build the query
    $sqlQuery = "SELECT SQL_CALC_FOUND_ROWS {$fields} FROM {$table_prefeix}{$table}";


    // Join clues for joining multiple table
    $joinClues = "";
    if(!empty($join)) {

        foreach($join as $joinVar) {
            $joinClues .= "{$joinVar} ";
        }

    }

    $sqlQuery .= " {$joinClues}";

    // Where Cluase
    if(!empty($where)) {

        // If any where clause exists then append it with the query.
        $whereClause = "";
        
        foreach($where as $whereField => $whereValue) {

            // If where value is empty then its not need to include in wherecluase. 
            if($whereValue !== "" and $whereValue !== "%" and $whereValue !== "%%" ) {

                if(!is_numeric($whereField) and stripos($whereField, "LIKE") > 0) { // Check if there any LIKE keyword in $whereField

                    $whereClause .= "{$whereField} '".safe_input($whereValue)."'";
    
                } else if(!is_numeric($whereField)) {
    
                    $whereClause .= "{$whereField} = '".safe_input($whereValue)."'";
                    
                } elseif(stripos($whereValue, "between") > 0) { // Check if the between keyword exists and data is not empty then the whare cluase will add with query. 

                    if(stripos($whereValue, "BETWEEN '' and") < 1) {

                        $whereClause .= " $whereValue";
                    }

                } else {
    
                    $whereClause .= " $whereValue";
                }

            }
            
        }

        // Append the where clause with $sqlQuery
        $sqlQuery .= empty($whereClause) ? "" : " WHERE {$whereClause}";

    }

    // groupby clause
    if(!empty($groupby)) {
        $sqlQuery .= " group by " . $groupby;
    }

    // OrderBy Clause
    if(!empty($orderBy)) {

        // If any order by clause exists then append it with the query.
        $orderByClause = "";
        foreach($orderBy as $orderByField => $orderByValue) {

            // Only allow ASC AND DESC string in $orderByValue
            if(!in_array(strtoupper($orderByValue), array("ASC", "DESC"))) {
                return "Invalied order by clause. Allow only ASC OR DESC";
            }
            
            $orderByClause .= "{$orderByField} {$orderByValue}, ";
            
        }
        $orderByClause = rtrim($orderByClause, ", ");
        // Append the Orderby Clause with $sqlQuery
        $sqlQuery .= " ORDER BY {$orderByClause}";
    }

    // Limit Clause
    if(!empty($limit)) {

        // If any order by clause exists then append it with the query.
        if(!is_numeric($limit["start"]) or !is_numeric($limit["length"])) {
            return "Limit clause must be a numeric value.";
        }

        $limitStart = intval($limit["start"]);
        $limitLength = intval($limit["length"]);
     
        // Append the Limit Clause with $sqlQuery
        $sqlQuery .= " LIMIT {$limitStart}, {$limitLength}";
    }

   /*  echo  $sqlQuery ;
    exit(); */

    // Run the query and store the result into getResult variable.
    $getResult = $conn->query($sqlQuery);

    //create_log($sqlQuery);

    // Check If the syntax has any error then throw an the error.
    if($getResult === false) {

        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error; // Return the error

    }

    // Check if there is more then Zero (0) result.
    if($getResult->num_rows > 0) {

        // $countTotalFilteredRow = "SELECT count(*) as totalFilteredRow FROM {$table_prefeix}{$table}";
        // $countTotalFilteredRow .= " {$joinClues}";
        // $countTotalFilteredRow .= empty($whereClause) ? "" : " WHERE {$whereClause}";
        // $countTotalFilteredRow = $conn->query($countTotalFilteredRow)->fetch_all(true)[0]["totalFilteredRow"];

        $countTotalFilteredRow = $conn->query("SELECT found_rows() as totalFilteredRow;")->fetch_all(true)[0]["totalFilteredRow"];

        // return all data in array format
        return array(
            "count" => (int)$countTotalFilteredRow,
            "data" => $getResult->fetch_all(true)
        );

    } else {
        // Return false if there is no data.
        return false;
    }
    
}


/**
 * easySelect Direct
 * 
 * @since 0.1
 * 
 * @param $query    The query which will run
 * 
 * @return array/bool			Return all data with array format. [count] return number of records and [data] retruns all records. Return error massage if the query is wrong. Return False if there is no data.
 * 
 */
function easySelectD($query) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    $dataFromDB = [];

    /* echo $query;
    exit(); */
    // Run the query and store the result into getResult variable.
    $getResult = $conn->query($query);

    // Check If the syntax has any error then throw an the error.
    if($getResult === false) {

        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error; // Return the error
    }

    // Check if there is more then Zero (0) result.
    if($getResult->num_rows > 0) {
        
        // return all data in array format
        return array(
            "count" => $getResult->num_rows,
            "data" => $getResult->fetch_all(true)
        );

    } else {
        // Return false if there is no data.
        return false;
    }

}


function runQuery($query){
   
    global $conn;			// MySQL connection variable.

    $runQuery = $conn->query($query);

    // Check If the syntax has any error then throw an the error. Otherwise return ture;
    if($runQuery === false) {

        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error; // Return the error

    } else {
        return true;
    }

}


/**
 * Function to move data to trash.
 * 
 * @since 0.1
 * 
 * @param string    $table  The table, from where the data will be moved into trash.
 * @param array     $where  The Where clues in array formate.
 * 
 * @return string|bool      Return true if the data is successfully moved. Otherwise return the error massage.
 */
function easyDelete(
    string $table, 
    array $where
    ) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.


    // Check if the data exists or not
    $DataToByDeleted = easySelect(
        $table,
        "*",
        array(),
        $where
    );

    if($DataToByDeleted === false) {
        return "There is no data found to delete.";
    }
    
    // Build the where clues
    $whereClause = "";   
    foreach($where as $whereField => $whereValue) {
        $whereClause .= "{$whereField} = '".safe_input($whereValue)."'";
    }

    // Build the query
    $sqlQuery = "UPDATE {$table_prefeix}{$table} SET is_trash=1 WHERE {$whereClause}";  

    // Run the query and check
    if($conn->query($sqlQuery) === TRUE) {
        
        return true;

    } else {

        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error;

    }

}


/**
 * Function to delete data from database.
 * 
 * @since 0.1
 * 
 * @param string    $table  The table, from where the data will be deleted.
 * @param array     $where  The Where clues in array formate.
 * 
 * @return string|bool      Return true if the data is successfully deleted. Otherwise return the error massage.
 */
function easyPermDelete(
    string $table, 
    array $where
    ) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.

    // Check if the data exists or not
    $DataToByDeleted = easySelect(
        $table,
        "*",
        array(),
        $where
    );

    if($DataToByDeleted === false) {
        return "There is no data found to delete.";
    }
    
    // Build the where clues
    $whereClause = "";   
    foreach($where as $whereField => $whereValue) {
        $whereClause .= "{$whereField} = '".safe_input($whereValue)."'";
    }

    // Build the query
    $sqlQuery = "DELETE FROM {$table_prefeix}{$table} WHERE {$whereClause}";

    // Run the query and check
    if($conn->query($sqlQuery) === TRUE) {
        
        // Save deleted data
        save_deleted_date($table, $DataToByDeleted);

        return true;
    } else {
        return $conn->error;
    }
}

/**
 * Function to update any data
 * 
 * @since 0.1
 * 
 * @param string    $table  The table, from where the data will be deleted.
 * @param array     $set    The update field and values in array format
 * @param array     $where  The Where clues in array formate.
 * 
 * @return string|bool	Return true if data is successfully updated, otherwise it will throw the mysql error.
 */
function easyUpdate(
    string $table, 
    array $set, 
    array $where
    ) {

    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.

    // Check if the data exists or not
    if(easySelect(
        $table,
        "*",
        array(),
        $where
    ) === false) {
        return "There is no data found to edit.";
    }

    // Build the set clues
    $setClues = "";
    foreach($set as $setField => $setValue) {
        /**
         * check if $setValue is array or not. if array then include the array value [bool] into the safe_input function
         * It actually use if we dont need the htmlspecialchars() and stripslashes() function in safe_input() function
         * If we set the second parameter false then htmlspecialchars() and stripslashes() function not be used. 
         **/ 
        if(is_array($setValue)) {

            $setClues .= "{$setField} = '" . safe_input($setValue[0], $setValue[1]) . "', ";

        } else if( is_null($setValue) ) { // if the input value is null

            $setClues .= "{$setField} = NULL, ";

        } else if( strlen($setValue) < 1 ) { // If the input value is empty string

            $setClues .= "{$setField} = '', ";

        } else { // And in all other situation insert the input value.
           
            $setClues .= "{$setField} = '".safe_input($setValue) ."', ";

        }
        
    }

    $setClues = rtrim($setClues, ", ");

    // Build the query
    $sqlQuery = "UPDATE {$table_prefeix}{$table} SET {$setClues}";  

     // Build the where clues
    $whereClause = "";   
    foreach($where as $whereField => $whereValue) {
         $whereClause .= "{$whereField} = '".safe_input($whereValue)."'";
    }

    // Apend the where clues with sqlQuery
   $sqlQuery .= " WHERE {$whereClause}";

    /* echo $sqlQuery;
    exit(); */

    // Run the query and check
    if($conn->query($sqlQuery) === TRUE) {
        // Save the query
        //save_query("UPDATE {$table_prefeix}{$table} SET {$setClues}  WHERE {$whereClause}");
        
        return true;

        
        
    } else {

        // insert log 
        create_log($conn->error, debug_backtrace());

        // Keep the transaction error record
        $conn->get_all_error[] = $conn->error;

        return $conn->error;

    }

}


/**
 * save_query use for save the query into database. And we will this for uploading data into main server.
 * 
 * @since 0.1
 * 
 * @param string $query The query we need to save
 */
function save_query($query) {
    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    $query = json_encode($query);
    $conn->query("INSERT INTO {$table_prefeix}latest_queries (query_value) VALUES ($query)");

}


/**
 * Function store deleted data in database
 * 
 * @since 0.1
 * 
 * @param string $table The table name where the data is deleted from. 
 * @param array $data  The deleted data we need to store
 */
function save_deleted_date($table, $data) {
    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.
    
    // Serialize the data
    $data = serialize($data);

    // Insert deleted data
    $conn->query("INSERT INTO {$table_prefeix}deleted_data (deleted_from, deleted_data, deleted_by) VALUES ('{$table}', '{$data}', '{$_SESSION['uid']}')");

}

/**
 * @since 0.1
 * 
 * @param string    $fileInputName  The file upload input name
 * @param string    $type           Optional. type of upload file. Defalut is image.
 * @param string    $location       Optional. Where the uploaded file has stored. Default is db and return an blob string
 * 
 */

 function easyUpload( 
    array $file, 
    string $location="db",
    string $newFileName="",
    string $type="image"
    ) {

    /**
     * Check if there a file
     */
    if(!isset($file["size"]) or $file["size"] < 1 ) {
        return "There is no file found to be uploaded.";
    }

    global $_SETTINGS;

    $mimeType = strtolower($file["type"]);
    $extension = explode(".", $file["name"]);
    $extension = end($extension);
    
    $maxUploadSize = $_SETTINGS["MAX_UPLOAD_SIZE"] * 1024 * 1024;

    if ($maxUploadSize < $file["size"]) {
        return "The file is exceeded the max upload size ({$_SETTINGS["MAX_UPLOAD_SIZE"]} MB)";
    }

    $validFileForUpload = [];
    switch($type) {
        case "image":       $validFileForUpload = $_SETTINGS["VALID_IMAGE_TYPE_FOR_UPLOAD"]; break;
        case "document":    $validFileForUpload = $_SETTINGS["VALID_DOCUMENT_TYPE_FOR_UPLOAD"]; break;
        case "video":       $validFileForUpload = $_SETTINGS["VALID_VIDEO_TYPE_FOR_UPLOAD"]; break;
        case "audio":       $validFileForUpload = $_SETTINGS["VALID_AUDIO_TYPE_FOR_UPLOAD"]; break;
        case "program":     $validFileForUpload = $_SETTINGS["VALID_PROGRAM_TYPE_FOR_UPLOAD"]; break;
        case 'all':         $validFileForUpload = array_merge($_SETTINGS["VALID_IMAGE_TYPE_FOR_UPLOAD"], $_SETTINGS["VALID_DOCUMENT_TYPE_FOR_UPLOAD"]); break;
    }


    // Validate both file extension and mime type
    if( isset( $validFileForUpload[$extension] ) AND in_array( $mimeType, $validFileForUpload[$extension] )  ) {


        /**
         * If location is set to db then return the image as blob string
         * Otherwise save the image in the desired location
         */
        if($location == "db") {
            
            return array (
                "success"       => true,
                "imageType"     => $file["type"],
                "blobString"    => file_get_contents($file["tmp_name"])
            );

        } else {
            
            $uploadDir = DIR_UPLOAD . $location;

            if(!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                return "Error creating directory";
            }

            // If newFileName is not empty then change the file name by given
            $file_name = rand().$file["name"];
            if(!empty($newFileName)) {

                $file_extension = explode(".", $file_name);
                $file_extension = end($file_extension);
                $file_name = $newFileName . "." . $file_extension;

            }


            if(move_uploaded_file($file["tmp_name"], $uploadDir ."/" . $file_name )) {

                return array (
                    "success"      => true,
                    "fileName"     => $file_name
                );

            } else {

                return "Can not upload the file";

            }

        }


    } else {

        return "Invalid {$type} type."; // Only {$validExtensionNameList} {$type} type are allowed to upload";

    }
    
}


function easyUpload_back(
    string $fileInputName, 
    string $type="image",
    string $location="db"
    ) {

    // Get the image size
    $fileSize = isset($_FILES) ? $_FILES[$fileInputName]["size"] : 0;

    /**
     * Check if the $_FILES variable is not set and image type is grater then zero byte
     *  if ture then throw an error.
     */
    if(!isset($_FILES) or $fileSize <= 1) {
        return "There is no file found to be uploaded. Please check the file input name and enter it correctly.";
    }

    global $_SETTINGS;
    $type = strtolower($type);

    $reference = explode(".", $_FILES[$fileInputName]["name"]);
    $file_extension = end($reference);
    $extensionName = strtolower(explode("/", $_FILES[$fileInputName]["type"])[1]);
    
    $maxUploadSize = $_SETTINGS["MAX_UPLOAD_SIZE"] * 1024 * 1024;

    if ($maxUploadSize <  $_FILES[$fileInputName]["size"]) {
        return "The file is exceeded the max upload size";
    }

    $validExtensionForUpload = [];
    switch($type) {
        case "image":       $validExtensionForUpload = $_SETTINGS["VALID_IMAGE_TYPE_FOR_UPLOAD"]; break;
        case "document":    $validExtensionForUpload = $_SETTINGS["VALID_DOCUMENT_TYPE_FOR_UPLOAD"]; break;
        case "video":       $validExtensionForUpload = $_SETTINGS["VALID_VIDEO_TYPE_FOR_UPLOAD"]; break;
        case "audio":       $validExtensionForUpload = $_SETTINGS["VALID_AUDIO_TYPE_FOR_UPLOAD"]; break;
        case "program":     $validExtensionForUpload = $_SETTINGS["VALID_PROGRAM_TYPE_FOR_UPLOAD"]; break;
    }
    
    if(!in_array($extensionName, $validExtensionForUpload)) {

        $validExtensionNameList = "";
        foreach($validExtensionForUpload as $validExtension) {
            $validExtensionNameList .= $validExtension . ", ";
        }
        $validExtensionNameList = rtrim($validExtensionNameList, ", ");

        return "Invalid {$type} type. Only {$validExtensionNameList} {$type} type are allowed to upload";
    }

    /**
     * If location is set to db then return the image as blob string
     * Otherwise save the image in the desired location
     */
    if($location == "db") {
        return array (
            "success"       => true,
            "imageType"     => $_FILES[$fileInputName]["type"],
            "blobString"    => file_get_contents($_FILES[$fileInputName]["tmp_name"])
        );
    }

}

/**
 * Return modal header
 * 
 * @since 0.1
 * 
 * @param string    $title  Title of the modal
 * @param string    $action form action of the form
 * @param string    $formId Optional form id. Default is modalForm
 * 
 * @return string|html  return the title with modal header and form
 */
function modal_header(
    string $title, 
    string $action,
    string $formId = "modalForm"
    ) {
    echo '
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">'. __($title) .'</h4>
        </div> <!-- modal-header -->

        <form method="post" role="form" id="'. $formId .'" action="'.$action.'"  enctype="multipart/form-data">

            <div class="modal-body">    
    ';
}

/**
 * Return modal footer
 * 
 * @since 0.1
 * 
 * @param string    $title  Title of the modal
 * 
 * @return string|html  return the title with modal header and form
 */
function modal_footer(
    string $saveButton = "Save changes"
    ) {
    
    echo '
            </div> <!-- modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                <button id="jqAjaxButton" type="submit" class="btn btn-primary">'. __($saveButton) .'</button>
            </div> <!-- modal-footer -->
        </form>
    ';
}


/**
 * Show year option since $start year to current year.
 * 
 * @since 0.1
 * 
 * @param int    $start_year. From which year the option will appear.
 * @param int    $getSelectedYear Optional. Which year is will be selected
 * @param int    $increase_year   Optional. If we need increase year from current year.
 * 
 * @return string   select option with year value
 */
function option_year(
    int $start_year, 
    int $getSelectedYear = NULL, 
    int $increase_year = NULL
    ) {
	$now = date('Y') + $increase_year;

	 for ($y=$now; $y>=$start_year; $y--) {
		 $selectedYear = ($y == $getSelectedYear) ? "selected" : "";
		echo '  <option '. $selectedYear .' value="' . $y . '">' . __($y) . '</option>' . PHP_EOL;
    }
}



/**
 * Function to spell money
 * 
 */
function spellNumbers($number)
{
    $number = abs((float)$number);
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'one', 2 => 'two',
        3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
        7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve',
        13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty',
        70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
    $digits = array('', 'hundred','thousand','lakh', 'crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ($decimal > 0) ? "" : " and " : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Takas = implode('', array_reverse($str));
    $paysa = ($decimal > 0) ? " and " . ($words[floor($decimal/10) * 10] . " " . $words[$decimal % 10]) . ' Paysa' : '';
    return ucfirst(($Takas ? $Takas . ' Taka' : '') . $paysa);
}

/**
 * -----------------------------------------------
 * Calculate Product having quantity
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $product. Which product do I need to calculate the quantity
 * 
 * @return int   Number of having product
 */
function calculateProductHavingQuantity(int $productId) {
    global $table_prefeix;

    return easySelect(
        "products",
        "product_id, ( IF(purchase_item_quantity IS NULL, 0, SUM(purchase_item_quantity) + if(returns_products_quantity is null, 0, returns_products_quantity) ) - IF(sale_item_quantity IS NULL, 0, SUM(sale_item_quantity))) AS having_item_quantity",
        array (
            "left join (select purchase_item_product_id, sum(purchase_item_quantity) as purchase_item_quantity from {$table_prefeix}product_purchase_items where is_trash = 0 group by purchase_item_product_id) as {$table_prefeix}product_purchase_items on purchase_item_product_id = product_id",
            "left join (select sale_item_product_id, sum(sale_item_quantity) as sale_item_quantity from {$table_prefeix}sale_items where is_trash = 0 group by sale_item_product_id) as {$table_prefeix}sale_items on sale_item_product_id = product_id",
            "left join (select product_return_items_product_id, sum(product_return_items_products_quantity) as returns_products_quantity from {$table_prefeix}product_return_items where is_trash = 0 group by product_return_items_product_id) as returns_product on product_id = product_return_items_product_id"
        ),
        array (
            "product_id " =>  $productId
        )
    )['data'][0]["having_item_quantity"];
}


/**
 * -----------------------------------------------
 * Calculate Customer Balance, Due etc
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $customer_id. 
 * 
 * @return array    details with customer payment information.
 * 
 * This function no longer required and will be deleted in near version
 */
function getCustomerPaymentInfo_back(int $customer_id) {
    global $table_prefeix;

    easySelectD(
        "select customer_id, if(customer_opening_balance is null, 0, customer_opening_balance) as customer_opening_balance,
            if(sales_grand_total is null, 0, sales_grand_total) as sales_grand_total, 
            if(returns_grand_total is null, 0, returns_grand_total) as returns_grand_total,
            if(received_payments_amount is null, 0, received_payments_amount) as total_received_payments,
            if(received_payments_bonus is null, 0, received_payments_bonus) as total_given_bonus
        from {$table_prefeix}customers
        left join (
            select
                sales_customer_id,
                sum(sales_grand_total) as sales_grand_total
            from {$table_prefeix}sales where is_trash = 0 group by sales_customer_id
        ) as sales on customer_id = sales_customer_id
        left join ( 
            select 
                product_returns_customer_id, 
                sum(product_returns_grand_total) as returns_grand_total 
            from {$table_prefeix}product_returns where is_trash = 0 group by product_returns_customer_id
        ) as product_returns on customer_id = product_returns_customer_id
        left join ( 
            select 
                received_payments_from, 
                sum(received_payments_amount) as received_payments_amount, 
                sum(received_payments_bonus) as received_payments_bonus 
            from {$table_prefeix}received_payments where is_trash = 0 group by received_payments_from
        ) as {$table_prefeix}received_payments on customer_id = received_payments_from
        where customer_id = {$customer_id}"
    );

}


/**
 * -----------------------------------------------
 * Update customer info
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $customer_id. 
 * 
 * @return bool True/False
 * 
 * This function no longer required and will be delete and near version
 */
function updateCustomerPaymentInfo_back(int $customer_id) {

    $gcpi = getCustomerPaymentInfo_back($customer_id); // $gcpi = get customer payment info

    $customer_total_paid = ( $gcpi["customer_opening_balance"] ) + $gcpi["returns_grand_total"] + $gcpi["total_received_payments"] + $gcpi["total_given_bonus"];

    $customer_due = $gcpi["sales_grand_total"] - $customer_total_paid;

    $customer_balance = 0;

    // Calculate customer balance
    if ($customer_due < 0) {
        $customer_due = 0;
        $customer_balance = $customer_total_paid - $gcpi["sales_grand_total"];
    }
    

    $updateCustomerPaymentInfo = easyUpdate(
        "customers",
        array (
            "customer_balance"  => $customer_balance,
            "customer_due"      => $customer_due
        ),
        array (
            "customer_id"   => $customer_id
        )
    );
    
    return $updateCustomerPaymentInfo;

}

/**
 * -----------------------------------------------
 * Update Accounts Payment Info
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $accounts_id. Which accounts balance we need to update
 * 
 * @return bool True/False
 */
function updateAccountBalance(int $accounts_id) {
    global $table_prefeix;

    // This safe_input function does not required in this case
    //$accounts_id = safe_input($accounts_id);

    // gad = getAccountsData
    $gad = easySelectD("
        select accounts_id, accounts_opening_balance, 
            if(loan_amount_sum is null, 0, loan_amount_sum) as loan_amount_sum,
            if(capital_amounts_sum is null, 0, capital_amounts_sum) as capital_amounts_sum,
            if(incomes_amount_sum is null, 0, incomes_amount_sum) as incomes_amount_sum,
            if(payment_amount_sum is null, 0, payment_amount_sum) as payment_amount_sum,
            if(transfer_send_amount_sum is null, 0, transfer_send_amount_sum) as transfer_send_amount_sum,
            if(transfer_received_amount_sum is null, 0, transfer_received_amount_sum) as transfer_received_amount_sum,
            if(received_payments_amount_sum is null, 0, received_payments_amount_sum) as received_payments_amount_sum,
            if(advance_payment_amount_sum is null, 0, advance_payment_amount_sum) as advance_payment_amount_sum,
            if(payment_incoming_return_amount_sum is null, 0, payment_incoming_return_amount_sum) as payment_incoming_return_amount_sum,
            if(payment_outgoing_return_amount_sum is null, 0, payment_outgoing_return_amount_sum) as payment_outgoing_return_amount_sum,
            if(journal_incoming_payment is null, 0, journal_incoming_payment) as journal_incoming_payment_sum,
            if(journal_outgoing_payment is null, 0, journal_outgoing_payment) as journal_outgoing_payment_sum
        from {$table_prefeix}accounts
        left join ( 
            select 
                loan_paying_from, 
                sum(loan_amount) as loan_amount_sum 
            from {$table_prefeix}loan 
            where is_trash = 0 
            group by loan_paying_from
        ) as {$table_prefeix}loan on loan_paying_from = accounts_id
        left join ( 
            select 
                capital_accounts, 
                sum(capital_amounts) as capital_amounts_sum 
            from {$table_prefeix}capital 
            where is_trash = 0 
            group by capital_accounts 
        ) as capital on capital_accounts = accounts_id
        left join ( 
            select 
                incomes_accounts_id, 
                sum(incomes_amount) as incomes_amount_sum 
            from {$table_prefeix}incomes 
            where is_trash = 0 
            group by incomes_accounts_id 
        ) as incomes on incomes_accounts_id = accounts_id
        left join ( 
            select 
                payment_from, 
                sum(payment_amount) as payment_amount_sum 
            from {$table_prefeix}payments 
            where is_trash = 0 and payment_status != 'Cancel' and ( payment_type != 'Advance Adjustment' or payment_type is null ) 
            group by payment_from 
        ) as payments on payment_from = accounts_id
        left join ( 
            select 
                transfer_money_from, 
                sum(transfer_money_amount) as transfer_send_amount_sum 
            from {$table_prefeix}transfer_money 
            where is_trash = 0 
            group by transfer_money_from 
        ) as transfer_money_send on transfer_money_from = accounts_id
        left join ( 
            select 
                transfer_money_to, 
                sum(transfer_money_amount) as transfer_received_amount_sum 
            from {$table_prefeix}transfer_money 
            where is_trash = 0 
            group by transfer_money_to 
        ) as transfer_money_received on transfer_money_to = accounts_id
        left join ( 
            select 
                received_payments_accounts, 
                sum(received_payments_amount) as received_payments_amount_sum
            from {$table_prefeix}received_payments 
            where is_trash = 0 and received_payments_type != 'Discounts' 
            group by received_payments_accounts
        ) as {$table_prefeix}received_payments on received_payments_accounts = accounts_id
        left join ( 
            select 
                sum(advance_payment_amount) as advance_payment_amount_sum, 
                advance_payment_pay_from 
            from {$table_prefeix}advance_payments 
            where is_trash = 0 
            group by advance_payment_pay_from 
        ) as get_advance_payments on advance_payment_pay_from = accounts_id
        left join ( 
            select 
                payments_return_accounts, 
                sum( case when payments_return_type = 'Incoming' then payments_return_amount end ) as payment_incoming_return_amount_sum,
                sum( case when payments_return_type = 'Outgoing' then payments_return_amount end ) as payment_outgoing_return_amount_sum
            from {$table_prefeix}payments_return 
            where is_trash = 0 
            group by payments_return_accounts 
        ) as get_return_payments on payments_return_accounts = accounts_id  
        left join ( 
            select 
                journal_records_accounts, 
                sum( case when journal_records_payments_type = 'Incoming' then journal_records_payment_amount end) as journal_incoming_payment,
                sum( case when journal_records_payments_type = 'Outgoing' then journal_records_payment_amount end) as journal_outgoing_payment 
            from {$table_prefeix}journal_records 
            where is_trash = 0  
            group by journal_records_accounts 
        ) as journal_incoming_records on journal_incoming_records.journal_records_accounts = accounts_id
        where accounts_id = {$accounts_id}"
    )["data"][0];


    $accounts_balance = ( 
                            $gad["accounts_opening_balance"] + $gad["capital_amounts_sum"] + $gad["incomes_amount_sum"] + $gad["transfer_received_amount_sum"] + $gad["received_payments_amount_sum"] + $gad["payment_incoming_return_amount_sum"] + $gad["journal_incoming_payment_sum"]
                        ) - ( 
                            $gad["loan_amount_sum"] + $gad["payment_amount_sum"] + $gad["transfer_send_amount_sum"] + $gad["advance_payment_amount_sum"] + $gad["journal_outgoing_payment_sum"] + $gad["payment_outgoing_return_amount_sum"]
                        ); 

    // Update Accounts Balance
    easyUpdate(
        "accounts",
        array (
            "accounts_balance"  => $accounts_balance
        ),
        array (
            "accounts_id"  => $accounts_id
        )
    );

}


/**
 * -----------------------------------------------
 * Get Employee Payable Amount
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $emp_id. The employee ID
 * @param string  $salary_type. 
 * 
 * @return string   Employee Payable Amount
 */
function getEmployeePayableAmount(int $emp_id, string $salary_type) {
    global $table_prefeix;

    $emp_opening_balance_name = "emp_opening_". strtolower($salary_type);

    $empPayableAmount = easySelectD("
        select 
            emp_id,
            ( ( if(salary_amount_sum is null, 0, salary_amount_sum) - if(payment_items_amount_sum is null, 0, payment_items_amount_sum) ) + ({$emp_opening_balance_name}) ) as emp_payable_amount
        from {$table_prefeix}employees
        left join ( select salary_emp_id, salary_type, sum(salary_amount) as salary_amount_sum from {$table_prefeix}salaries where is_trash = 0 and salary_type='{$salary_type}' group by salary_emp_id ) as {$table_prefeix}salaries on salary_emp_id = emp_id
        left join ( select payment_items_employee, sum(payment_items_amount) as payment_items_amount_sum from {$table_prefeix}payment_items where is_trash = 0 and payment_items_type='{$salary_type}' group by payment_items_employee ) as get_payments_items on payment_items_employee = emp_id
        where emp_id = {$emp_id}
    ")["data"][0]["emp_payable_amount"];

    // if salary type is salary then add the installment amount with payable amount
    if($salary_type === "salary") {

        $paidLoan = easySelectD("
            select sum(loan_installment_paying_amount) as loan_paid_amount from {$table_prefeix}loan_installment where is_trash = 0 and loan_installment_provider = '{$emp_id}' group by loan_installment_provider
        ");

        $empPayableAmount -= $paidLoan ? $paidLoan["data"][0]["loan_paid_amount"] : 0;
    }

    return $empPayableAmount;

}


/**
 * -----------------------------------------------
 * Get Accounts Balance
 * -----------------------------------------------
 * 
 * @since 0.1
 * 
 * @param int $accounts_id. 
 * 
 * @return string Accounts Balance 
 */
function accounts_balance(int $accounts_id) {

    return easySelect(
        "accounts",
        "accounts_balance",
        array(),
        array (
            "accounts_id"   => $accounts_id
        )
    )["data"][0]["accounts_balance"];
}

/**
 * Insert Login information into database
 * 
 * @since 0.1
 * 
 * @param int @user_id The user id which we need to add login infomration
 * 
 */

 function add_login_info(int $user_id) {
    global $table_prefeix;	// table prefix;
    global $conn;			// MySQL connection variable.

    $user_ip = safe_input(get_ipaddr());
    $user_aggent = safe_input($_SERVER['HTTP_USER_AGENT']);
    
    // Insert User information Into Database
    $conn->query("INSERT INTO {$table_prefeix}users_login_history (login_users_id, login_ip, login_user_aggent) 
                        VALUES ('{$user_id}', '{$user_ip}', '{$user_aggent}')");
    
}

function get_ipaddr() {
    return $_SERVER['REMOTE_ADDR'];
}


function get_ip_address(){
    
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); // just to be safe

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
}

/**
 * Get Option value
 * 
 * @since 0.1
 * 
 * @param string $optionName The option name which we need to parse/
 * 
 * @return string The option value
 * 
 */

function get_options(string $optionName) {
    
    defined('ALL_OPTIONS') ?: define("ALL_OPTIONS", array_column(easySelect("options")["data"], 'option_value', 'option_name'));
    
    // If found, return the result
    if( is_array(ALL_OPTIONS) and array_key_exists($optionName, ALL_OPTIONS) ) {
        
        return ALL_OPTIONS[$optionName];
    
    // Else return error msg
    } else {

        return false;

    }
}

/**
 * Set Option value
 * 
 * @since 0.1
 * 
 * @param string $optionName The option name which we need to set
 * @param string $optionValue The Option Value
 * 
 * @return string The option value
 * 
 */
function set_options(string $optionName, string $optionValue) {

    $getOption = easySelect(
        "options",
        "*",
        array(),
        array (
            "option_name"  => $optionName
        )
    );
    
    if($getOption !== false) {
        
        // Update option
        easyUpdate(
            "options",
            array (
                "option_value"  => $optionValue
            ),
            array (
                "option_name" => $optionName
            )
        );

    } else {

        //Insert option
        easyInsert(
            "options",
            array (
                "option_name" => $optionName,
                "option_value"  => $optionValue
            )
        );

    }
    
}

/**
 * Send HTTP requirest
 * 
 * @since 0.1
 * 
 * @param string $url           The URL where we need to send the request
 * @param string/array  $data   The information which we need to send. 
 * 
 * @return string   The output of the url
 */
function send_http_request(string $url, $data) {

    $rc = curl_init();

    $curlPostData = array (
        "postData"  => $data
    );

    $header = array (
        "signature: dskfmx09@435uk*435&*dkfnl@4$343$75dflgldk45dfs*df34918skj%34&4$384hsdf34094%4598df$^lk,m()dkfn3483lkfsd324-lkj5+45lk=dfsdlk"
    );

    curl_setopt($rc, CURLOPT_URL, $url);
    curl_setopt($rc, CURLOPT_POST, true);
    curl_setopt($rc, CURLOPT_POSTFIELDS, $curlPostData);
    curl_setopt($rc, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($rc, CURLOPT_HTTPHEADER, $header);
    curl_setopt($rc, CURLOPT_CONNECTTIMEOUT, 5);

    $output = curl_exec($rc);
    curl_close($rc);

    return $output;
}


/**
 * Format number to money
 * 
 */
function to_money(
    float $number, 
    int $decimalPlaces = NULL,
    string $decimalSeparator = "",
	string $thousandSeparator = "",
	string $currencySymbol = "",
	string $currencySymbolPosition = ""
    ) {
      
        $decimalPlaces = empty($decimalPlaces) ? get_options("decimalPlaces") : $decimalPlaces;
        $decimalSeparator = empty($decimalSeparator) ? get_options("decimalSeparator") : $decimalSeparator;
        $thousandSeparator = empty($thousandSeparator) ? get_options("thousandSeparator") : $thousandSeparator;
        $currencySymbol = empty($currencySymbol) ? get_options("currencySymbol") : $currencySymbol;
        $currencySymbolPosition = empty($currencySymbolPosition) ? get_options("currencySymbolPosition") : $currencySymbolPosition;

        $formatedNumber = number_format($number, $decimalPlaces, $decimalSeparator, $thousandSeparator);

        if( strtolower($currencySymbolPosition) === "right" ) {
            $formatedNumber = $formatedNumber . " " .  $currencySymbol;
        } else {
            $formatedNumber = $currencySymbol . " " . $formatedNumber;
        }

        return $formatedNumber;
}


/**
 * negative_value_is_allowed function
 * 
 * @since 2.0.1
 * 
 * @param int $accounts_id  The accounts id to check
 * @return bool if allow then return true otherwise false
 */

 function negative_value_is_allowed(int $accounts_id) {
     $getData = easySelectA(array(
         "table"    => "accounts",
         "fields"   => "negative_value_is_allow",
         "where"    => array(
             "accounts_id"  => $accounts_id
         )
     ));

     if(isset($getData["data"][0]["negative_value_is_allow"]) and $getData["data"][0]["negative_value_is_allow"] == 1) {
         return true;
     } else {
         return false;
     }
 }

 /**
  * Function to minify html and js
  */
 function sanitize_output($buffer) {

    $search = array(
        '/ \/\/.*\n/',
        '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
        '/[^\S ]+\</s',     // strip whitespaces before tags, except space
        '/(\s)+/s',         // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/', // Remove HTML comments
        '/\/\*(.|\s)*?\*\//'
    );
    
    $replace = array(
        '',
        '>',
        '<',
        '\\1',
        '',
        ''
    );
  
    $buffer = preg_replace($search, $replace, $buffer);
  
    return $buffer;
 }


 /**
  * Function to generate title
  */
  
function get_title() {
    
    // Get the page titles
    global $_SETTINGS;
    
    $currentUrl = transfer_protocol().explode("?", $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])[0];

    if( isset($_SETTINGS["PAGE_TITLE"][$currentUrl]) ) {
        return $_SETTINGS["PAGE_TITLE"][$currentUrl];
    } else {
        return "Not Found!";
    }

}
  
  
  /**
   * Create getallheaders function if it is not exits
   */
  if (!function_exists('getallheaders')) {

        function getallheaders() {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }

    }


    /**
     * Send SMS 
     * 
     * @since 2.0.1
     * 
     * @param int    $number     The mobile where the sms will be sent
     * @param string $msg        The message to be sent
     * 
     * @return bool               Return true if sms sent successfull, otherwise false 
     */
    function send_sms($number, $msg) {

        global $table_prefeix;

	    $url = "http://example.com/api.php";
        $data= array(
            'username'=>"username",
            'password'=>"password",
            'number'=>$number,
            'message'=>$msg
        );

        $ch = curl_init(); // Initialize cURL
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $smsresult = curl_exec($ch);
        $p = explode("|",$smsresult);
        $sendstatus = $p[0];

        if( $sendstatus == 1101) {

            $insertSmsLog = "INSERT INTO {$table_prefeix}sms_sender(
                send_to,
                send_time,
                sms_text,
                status,
                send_by
            ) VALUES ";

            // explode comma seperated value
            $numbers = explode(",", $number);

            foreach($numbers as $num) {

                $insertSmsLog .= "
                (
                    '". safe_input($num) ."',
                    '". date("Y-m-d H:i:s") ."',
                    '". safe_input($msg) ."',
                    'sent',
                    '". safe_input($_SESSION["uid"]) ."'
                ),";
  
            }
            
            // Run the built query
            runQuery(substr_replace($insertSmsLog, ";", -1, 1));

            return true; 

        } else {
            return false;
        }

    }


    /**
     * Get the Product type
     * 
     * @since 2.0.2
     * 
     * @param int   $product_id     The product id to check the type
     * 
     * @return bool,string          Return the multiple product type and if grouped product then return with the product id list
     */

    function product_type($pid) {

        $return = array(
            "is_variable"   => false,
            "is_grouped"    => false,
            "is_bundle"     => false,
            "is_normal"     => false,
            "product_ids"   => ""
        );

        $selectProduct = easySelectA(array(
            "table"     => "products",
            "fields"    => "product_type",
            "where" => array(
                "product_id"    => $pid
            )
        ));

        if($selectProduct and $selectProduct["data"][0]["product_type"] === "Grouped") {

            $return["is_grouped"] = true;

            // select the grouped product list
            $return["product_ids"] = easySelectA(array(
                "table"     => "bg_product_items",
                "fields"    => "group_concat(bg_item_product_id) as product_list",
                "where"     => array(
                    "bg_product_id" => $pid
                )
            ))["data"][0]["product_list"];

        } elseif($selectProduct and $selectProduct["data"][0]["product_type"] === "Bundle") {

            $return["is_bundle"] = true;

            // select the grouped product list
            $return["product_ids"] = easySelectA(array(
                "table"     => "bg_product_items",
                "fields"    => "group_concat(bg_item_product_id) as product_list",
                "where"     => array(
                    "bg_product_id" => $pid
                )
            ))["data"][0]["product_list"];

        } elseif($selectProduct and $selectProduct["data"][0]["product_type"] === "Variable") {
            
            $return["is_variable"] = true;

        } else {

            $return["is_normal"] = true;

        }

       
        return $return;


    }

    /**
     * Check if the current user is biller or not
     */
    function is_biller() {

        if ( isset($_SESSION['sid']) and isset($_SESSION['aid']) and isset($_SESSION['wid']) ) {
            return true;
        } else {
            return false; 
        }

    }

    /**
     * Payment reference Generator
     * @since 2.1.1
     * 
     * @param string $type the reference type to generate
     * 
     * @return string the full reference
     */
    function payment_reference(string $type) {

        // Default Referense Format
        $referenceFormat = "SALARY_PAY";
        if($type === "bill") {
            $referenceFormat = "BILL_PAY";
        }

        $paymentReferences = "{$referenceFormat}/{$_SESSION['uid']}/";

        // Select last payment references
        $selectPaymentReference = easySelect(
            "payments",
            "payment_reference",
            array(),
            array (
                "payment_made_by"   => $_SESSION['uid'],
                " AND payment_reference LIKE '{$referenceFormat}%'",
                " AND payment_reference is not null"
            ),
            array (
                "payment_id" => "DESC"
            ),
            array (
                "start" => 0,
                "length" => 1
            )
        );

        // check if there is minimum one records
        if($selectPaymentReference) {
            $getLastReferenceNo = (int)explode($paymentReferences, $selectPaymentReference["data"][0]["payment_reference"])[1];
            return $paymentReferences . ($getLastReferenceNo+1);

        } else {
            return "{$paymentReferences}1";
        }

    }


    /**
     * Translator function
     * 
     * @since 2.1.1
     * 
     * Example code: echo __('You have %1$d unread message and you were last visit %2$s days ago', 120, 5);
     * 
     * Will be written latter 
     */
    function __() {
    
        // Get arguments
        $argc = func_get_args();
        $total_arg = func_num_args();

        // Get the string to be translated and convert to lower case
        // $formated_text = strtolower($argc[0]); // Will Test it later
        $formated_text = $argc[0];
    
        static $lang_pack = array(
            "global"    => array(),
            "index"     => array()
        );

        // Get the Language pack name
        if(empty($lang_pack["index"]) and isset($_COOKIE["lang"])) {
            
            $langName = $_COOKIE["lang"];

            $langFile = DIR_LANG . $langName . ".php";

            // Check if the language file exists
            if( file_exists($langFile) ) {
                
                require $langFile;

                // Get the language array, change all keys to lower case and aissign to $lang_pack variable
                // $lang_pack = isset($$langName["trdata"]) ? array_change_key_case($$langName["trdata"]) : array(); // Will test it later
                $lang_pack["index"] = isset($$langName["trDataIndex"]) ? $$langName["trDataIndex"] : array();
                $lang_pack["global"] = isset($$langName["trDataGlobal"]) ? $$langName["trDataGlobal"] : array();
                
            }

        }
        

        $translations = ""; // All translation will store here
        
        if($total_arg  === 1 and !isset($lang_pack["index"][$formated_text]) ) {
    
            $translations = $formated_text;
    
        } else if( $total_arg === 1 and isset($lang_pack["index"][$formated_text]) ) {
    
            $translations = $lang_pack["index"][$formated_text];
    
        } else if ( !isset($lang_pack["index"][$formated_text]) ) {
    
            $translations = vsprintf($formated_text, array_splice($argc, 1));
    
        } else {
    
            // Text translation
            $translations = vsprintf($lang_pack["index"][$formated_text], array_splice($argc, 1));
    
        }

        // English Numbers, Months, Weeks Translation to other language
        $translations = empty($translations) ? $translations : strtr($translations, $lang_pack["global"]);

        // Return the translation
        return $translations;
        
    }

    /**
     * Return error message with translation
     */
    function _e(...$args){

        echo "<div class='alert alert-danger'>" . __(...$args) . "</div>";
    }

    /**
     * Return success msg with translation
     */
    function _s(...$args){
        echo "<div class='alert alert-success'>" . __(...$args) . "</div>";
    }

    //
    function create_log(string $msg, array $debug_backtrace = array(), string $logFile = "error.log") {

        // Get the debug backtrace
        if(count($debug_backtrace) > 0) {
            
            $debug_backtrace = $debug_backtrace[0];

        } else {
            $debug_backtrace = debug_backtrace()[0];
        }

        // If error msg not empty
        if(!empty($msg)) {

            $fp = fopen(DIR_BASE . $logFile, "a");
            fwrite($fp, date("Y-m-d H:i:s") . " {$msg}. {$debug_backtrace['file']}, Line {$debug_backtrace['line']} \n");
            fclose($fp);
        }

    }


    /**
     * 
     * Product filters
     */
    function load_product_filters($id="") {


        static $filters = array();
        $filters["category"] = '<select id="productCategoryFilter'.$id.'" class="form-control select2Ajax" select2-ajax-url="'. full_website_address() .'/info/?module=select2&page=productCategoryList">
                                    <option value="">'. __("All Category") . '</option>';

                                    if(!empty(get_options("defaultProductCategory"))) {

                                        $categories = easySelectA(array(
                                            "table"     => "product_category",
                                            "where"     => array(
                                                "category_id"  => get_options("defaultProductCategory")
                                            )
                                        ))["data"][0];

                                        $filters["category"] .= '<option selected value="'. $categories["category_id"] .'">'. $categories["category_name"] . '</option>';

                                    }
                                    
        $filters["category"] .= '</select>';
        
        $filters["brand"]    = '<select id="productBrandFilter'.$id.'" class="form-control select2Ajax" select2-ajax-url="'. full_website_address() .'/info/?module=select2&page=productBrandList">
                                    <option value="">'. __("All Brand") .'</option>';
        $filters["brand"]   .= empty(get_options("defaultProductBrand")) ?: "<option selected value=". get_options("defaultProductBrand") .">". get_options("defaultProductBrand") ."</option>";
        $filters["brand"]   .=  '</select>';

        $filters["generic"]  = '<select id="productGenericFilter'.$id.'" class="form-control select2Ajax" select2-ajax-url="'. full_website_address() .'/info/?module=select2&page=productGenericList">
                                    <option value="">'. __("All Generic") .'</option>';
        $filters["generic"]   .= empty(get_options("defaultProductGeneric")) ?: "<option selected value=". get_options("defaultProductGeneric") .">". get_options("defaultProductGeneric") ."</option>";
        $filters["generic"]  .= '</select>';
        
        $filters["edition"]  = '<select style="width:100%;" id="productEditionFilter'.$id.'" class="form-control select2Ajax" select2-ajax-url="'. full_website_address() .'/info/?module=select2&page=productEditionList">
                                    <option value="">'. __("All Editions...") .'</option>';
        $filters["edition"]   .= empty(get_options("defaultProductEdition")) ?: "<option selected value=". get_options("defaultProductEdition") .">". get_options("defaultProductEdition") ."</option>";
        $filters["edition"] .= '</select>';

        $filters["author"]  = '<select id="productAuthorFilter'.$id.'" class="form-control select2Ajax" select2-ajax-url="'. full_website_address() .'/info/?module=select2&page=authorList">
                                <option value="">'. __("All Author") .'</option>
                            </select>';
        
        
        // Get product filter options
        $getFilter = unserialize(get_options("defaultProductFilter"));

        // Calculate total column
        $col = round(12 / count($getFilter));

        foreach($getFilter as $fKey => $fValue) {

            echo '<div class="col-md-'. $col .'">
                    <div class="form-group">'.
                        $filters[$fValue]
                    .'</div>
                </div>';

        }


    }

    function set_local_storage($name, $value) {

        echo "<script>
                localStorage.setItem('{$name}', '{$value}');
            </script>";

    }


/**
 * Generate nearest unit quantity 
 * 
 * @since 1.00
 * 
 * @param number|init $product_id the product which to check
 * @param null|bool|int|float|string $qty The quantity
 * @param string      $unit The unit name
 * 
 */
function near_unit_qty($product_id, $qty, $unit) {

    global $table_prefeix;

    $getData = easySelectA(array(
        "table"     => "products as whereProduct",
        "fields"    => "joinProduct.product_unit as product_unit, equal_unit_qnt, base_qnt",
        "join"      => array(
            "left join {$table_prefeix}products as joinProduct on joinProduct.product_name = whereProduct.product_name",
            "left join {$table_prefeix}product_units on unit_name = joinProduct.product_unit"
        ),
        "where"     => array(
            "joinProduct.is_trash = 0 and joinProduct.product_unit is not null and whereProduct.product_id" => $product_id
        ),
        "orderby"   => array(
            "base_qnt"  => "DESC"
        )
    ));
    

    if($getData !== false) {

        $totalBaseQty = $qty;
        $remainQty = 0;
        $finalUnitName = "";
        $finalQtyBasedOnUnit = 0;
        
        // Generate the base qty based on unit
        foreach($getData["data"] as $pKey => $pVal ) {
        
            if( $pVal["product_unit"] === $unit) {
        
                $totalBaseQty *= $pVal["base_qnt"];
                break;
        
            }
        
        }
        
        // Now get the unit which base_qnt is grater then or equal to unitDevider
        foreach($getData["data"] as $pKey => $pVal ) {
        
            if( $pVal["base_qnt"] <= $totalBaseQty) {
        
                $finalUnitName = $pVal["product_unit"];
                $remainQty = ($totalBaseQty % $pVal["base_qnt"]);
                $finalQtyBasedOnUnit = ($totalBaseQty - $remainQty) / $pVal["base_qnt"];
                break;
        
            }
        
        }


        return $finalQtyBasedOnUnit . " " . $finalUnitName . ( $remainQty > 0 ? ", " . near_unit_qty($product_id, $remainQty, $unit) : "");

    } else {

        return $qty . " " . $unit;

    }


}


/**
 * Add module menu after specific index
 * 
 * @since 1.0.0
 * 
 * @param array $menu_array the menu array to be included
 * @param number|init $position The position, where after the module menu will be included
 */
function add_menu(array $menu_array=array(), $position="") {

    global $default_menu;


    // If the position is set then add the menu in speicifc position
    if($position !== "") {

        // Split the default menu in specific position with keeping the key/index name

        // Get the first key
        $first_key = array_key_first($menu_array);

        // If the first key is set in the menu
        if( isset($default_menu[$first_key]) ) {

            $position = $position+4;
            $beforePostion = array_slice($default_menu[$first_key], 0, $position, true);
            $afterPostion = array_slice($default_menu[$first_key], $position, count($default_menu[$first_key]) - $position, true);

            // Merge all menus into one
            $default_menu[$first_key] = array_merge($beforePostion, $menu_array[$first_key], $afterPostion);

        } else {

            $beforePostion = array_slice($default_menu, 0, $position, true);
            $afterPostion = array_slice($default_menu, $position, count($default_menu) - $position, true);

            // Merge all menus into one
            $default_menu = array_merge($beforePostion, $menu_array, $afterPostion);

        }
        

        //print_r($default_menu);
        

    } else {

        // Otherwise add the in last
        $default_menu = array_merge($default_menu, $menu_array);

    }
    
}

/**
 * Add permission
 */
function add_permission(array $permission) {

    global $defaultPermission; 
    $defaultPermission = array_merge($defaultPermission, $permission);

}

/**
 * Get the first comment from a file
 * 
 * @since 1.0.0
 * 
 * @param string $file The file name with location
 * 
 * @return string The comments
 */
function getFirstComment($file) {
    
    $get_comments = array_filter(
        token_get_all( file_get_contents($file)), function($comment) {
            return $comment[0] == T_COMMENT || $comment[0] == T_DOC_COMMENT;
        }
    );

    return array_shift($get_comments)[1];
}


/**
 * Find module's Name, URI, Version, Description, Author, Author URI, License etc
 * 
 * @since 1.0.0
 * 
 * @param string $find The string to find
 * @param string $comment The comment to find in
 * 
 * @return string Return the value
 */
function find_modules($find, $comment) {

    $moduleInfo = preg_split("/".$find.":/i", $comment);

    if( isset($moduleInfo[1]) ) {
        
        return trim(explode("\n", $moduleInfo[1])[0]);

    } else {
        return "";
    }

}


/**
 * Create array_key_first function if it is not exits
 */
if (!function_exists('array_key_first')) {

    function array_key_first(array $array) {
        
        foreach($array as $firstKey => $firstValue) {
            return $firstKey;
            break;
        }
        
    }

}

/**
 * Converting times to times ago
 * Taken from: https://stackoverflow.com/a/18602474
 * 
 * and Modified
 */
function time_elapsed_string($datetime, $level = 1) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    $string = array_slice($string, 0, $level);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

?>