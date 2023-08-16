<?php

/*
 * searx-stats is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * searx-stats is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with searx-stats. If not, see < http://www.gnu.org/licenses/ >.
 *
 * (C) 2014- by Thomas Pointhuber
 */

define('_SEARX_STATS', 1);

// check if config-file is available
if(!is_readable(realpath(dirname(__FILE__) . '/../resources/config.inc.php')))
    die("config-file not found!");

require_once(realpath(dirname(__FILE__) . '/../resources/config.inc.php'));

require_once(LIBRARY_PATH . '/system/data/DatabaseManager.class.php');
require_once(LIBRARY_PATH . '/system/data/Input.class.php');

DatabaseManager::connect();

$DatabaseHandler = new DatabaseManager();

//###### Instances CronJob

if((bool)$config["instances"]["cronjob"] == True) {
    require_once(LIBRARY_PATH . '/system/SearxInstances.class.php');

    $SearxInstancesObject = new SearxInstances();

    $instances = $SearxInstancesObject->GetInstances();

    echo "<h1>Check searx-Instances</h1>";


    // TODO: rewrite to http://cn2.php.net/manual/en/function.curl-multi-exec.php
    foreach ($instances as $single_instance) {
        echo $single_instance['id'].' - '.$single_instance['url'];
        
        $crl = curl_init();
        $timeout = (int)$config["instances"]["timeout"];
        curl_setopt ($crl, CURLOPT_URL,$single_instance['url']);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt ($crl, CURLOPT_SSL_VERIFYPEER, false);

        $res_http = curl_exec($crl);
        $res_total_time = (float)curl_getinfo($crl, CURLINFO_TOTAL_TIME );
        $res_http_code = (int)curl_getinfo($crl, CURLINFO_HTTP_CODE );
        // Rewrite to HTTP-CODE: 408 if timeout occour
        if($res_http_code === 0 && $res_total_time >= $timeout) {
            $res_http_code = 408;
            }
        
        curl_close($crl);
        
        $res_timestamp = time();
        $res_searx_version = NULL;
        
        // correct HTTP-CODE
        if($res_http_code >= 200 && $res_http_code < 300 and $res_http != NULL) {
        
            //Create a new DOM document
            $dom = new DOMDocument;

            //Parse the HTML. The @ is used to suppress any parsing errors
            //that will be thrown if the $html string isn't valid XHTML.
            @$dom->loadHTML($res_http);

            //Get all links. You could also use any other tag name here,
            //like 'img' or 'table', to extract other tags.
            $links = $dom->getElementsByTagName('meta');//<meta http-equiv="X-UA-Compatible" content="IE=edge">

            //Query the DOM
            //$links = $xpath->query( '//meta' );

            //Display the results as in the previous example
            foreach($links as $link){
                if($link->getAttribute('name') == 'generator')
                    $res_searx_version = $link->getAttribute('content');
            }
        }
        
        // save in database
        $query = "UPDATE `#instances` SET ".
            "`VERSION_STRING` = '".DatabaseManager::correctString($res_searx_version)."', ".
            "`RETURN_CODE` = '".(int)$res_http_code."', ".
            "`LAST_UPDATE` = '".date('Y-m-d H:i:s',$res_timestamp)."' ".
            "WHERE `#instances`.`ID` =".(int)$single_instance['id'].";";
        $DatabaseHandler->query($query);

        // print result
        if($res_http_code >= 200 && $res_http_code < 300)
            echo ' - HTTP-CODE: '.$res_http_code;
        else
            echo ' - <b>HTTP-CODE: '.$res_http_code.'</b>';
        echo ' - TIME: '.$res_total_time;
        echo ' - TIMESTAMP: '.$res_timestamp;
        echo ' - VERSION: '.$res_searx_version;
        echo '<br/>';
    }
}
//###### Engines CronJob

if((bool)$config["engines"]["cronjob"] == True && $config["engines"]["server"] != '') {
    require_once(LIBRARY_PATH . '/system/SearxEngines.class.php');

    $SearxEnginesObject = new SearxEngines();

    $engines = $SearxEnginesObject->GetEngines();

    echo "<h1>Check searx-Engines</h1>";

    // TODO: rewrite to http://cn2.php.net/manual/en/function.curl-multi-exec.php
    foreach ($engines as $single_engine) {
        echo $single_engine['id'].' - '.$single_engine['name'];
        $res_success = False;
        
        // test engines as long with keywords, unless results appear
        foreach ($config["engines"]["keywords"] as $keyword) {
            $crl = curl_init();
            $timeout = (int)$config["engines"]["timeout"];
            curl_setopt ($crl, CURLOPT_URL,$config["engines"]["server"]);
            curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt ($crl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($crl, CURLOPT_POST, 1);
            curl_setopt($crl, CURLOPT_POSTFIELDS, array(
                category_none => 1,
                q => '!'.$single_engine['name'].' '.$keyword,
                pageno => 1,
                format => 'json'
            ));

            $res_http = curl_exec($crl);
            $res_total_time = (float)curl_getinfo($crl, CURLINFO_TOTAL_TIME );
            $res_http_code = (int)curl_getinfo($crl, CURLINFO_HTTP_CODE );
            
            curl_close($crl);

            $res_json = json_decode($res_http, true);
            
            $res_timestamp = time();
            
            if(count((array)$res_json['results']) > 0) {
                $res_engine_name=preg_replace('/(\s|-|_)+/', '', $res_json['results'][0]['engine']);
                $engine_name=preg_replace('/(\s|-|_)+/', '', $single_engine['name']);
                
                // check, if engine is enabled in searx
                // other test posibilitiy:  strpos($single_instance['url'], '!'.$single_engine['name']) !== 0
                if($res_engine_name === $engine_name) {
                    echo ' -  QUERY: '.$res_json['query'];
                    $res_success = True;
                    break;
                } // TODO, mark engine as not actiated
            }
            
            // wait between requests
            usleep((int)$config["engines"]["wait_time"]*1000);       
        }
        
        // save in database
        $query = "UPDATE `#engines` SET ".
            "`IS_WORKING` = '".(int)$res_success."', ".
            "`LAST_UPDATE` = '".date('Y-m-d H:i:s',$res_timestamp)."' ".
            "WHERE `#engines`.`ID` =".(int)$single_engine['id'].";";
        $DatabaseHandler->query($query);
        
        if($res_success)
            echo ' - SUCCESS<br/>';
        else
            echo ' - FAILED<br/>';

        // wait between requests
        usleep((int)$config["engines"]["wait_time"]*1000);
    }
}
?>
