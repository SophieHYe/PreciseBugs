<?php

  require_once "RestServer.php";
  require_once "config.php";

  include("geoip/src/geoip.inc");

  class AddInfo {
     public function add_info($uuid, $release, $type = "community") {

	  // get ip from remote address
      $ip = $_SERVER['REMOTE_ADDR'];

      // get geodata
      $gi = geoip_open("geoip/GeoIP.dat", GEOIP_STANDARD);

      // get country code from ip
      $country_code = geoip_country_code_by_addr($gi, $ip);

      // get country name from ip
      $country_name = geoip_country_name_by_addr($gi, $ip);

      try {
        // get connession
        $conn = new PDO("mysql:host=".$GLOBALS['$dbhost'].
                        ";dbname=".$GLOBALS['$dbname'].
                        "", $GLOBALS['$dbuser'], $GLOBALS['$dbpass']);

        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // insert query
        $sql = "REPLACE INTO phone_home_tb (uuid,
                                            release_tag,
                                            ip,
                                            country_code,
                                            country_name,
                                            type,
                                            reg_date)
                VALUES (:uuid,
                        :release,
                        :ip,
                        :country_code,
                        :country_name,
                        :type,
                        NOW())";

        // prepare statement
        $stmt = $conn->prepare($sql);

        // execute query
        $stmt->execute(array( ':uuid'                 => $uuid,
                              ':release'              => $release,
                              ':ip'                   => $ip,
                              ':country_code'         => $country_code,
                              ':country_name'         => $country_name,
                              ':type'                 => $type
                            ));

        // close connession
        $conn = null;

      }
      catch(PDOException $e) {
        echo $e->getMessage();
      }

     }
  }

  class GetInfo {
    public function get_info($interval) {
      try {
        // get connession
        $conn = new PDO("mysql:host=".$GLOBALS['$dbhost'].
                        ";dbname=".$GLOBALS['$dbname'].
                        "", $GLOBALS['$dbuser'], $GLOBALS['$dbpass']);

        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // select query
        $sql = "SELECT    country_code,
                          GROUP_CONCAT(CONCAT( release_tag,'#',num )) AS installations,
                          country_name

                FROM      ( SELECT  country_code,
                                    release_tag,
                                    country_name,
                                    reg_date,
                                    COUNT(release_tag) AS num
                            FROM phone_home_tb ";

        if ($interval!=='1') {
          $sql .= " WHERE reg_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)";
        }

        $sql .= " GROUP BY release_tag, country_code
        ) AS t
        GROUP BY  country_code;";

        // prepare statement
        $stmt = $conn->prepare($sql);

        // execute query
        $stmt->execute();

        // create new empty array
        $infos = array();

        // set the resulting array to associative
        for($i=0; $row = $stmt->fetch(); $i++){
          array_push($infos, array( 'installations'         => $row['installations'],
                                    'country_code'          => $row['country_code'],
                                    'country_name'          => $row['country_name']
                                  ));
        }

        // close connession
        $conn = null;

        // return info inserted
        header('Content-Type: application/json');
        echo json_encode($infos);

      }
      catch(PDOException $e) {
        echo $e->getMessage();
      }

    }

  }

  class GetCountryCoor {
    public function get_country_coor($country_code) {
      try {
        // get connession
        $conn = new PDO("mysql:host=".$GLOBALS['$dbhost'].
                        ";dbname=".$GLOBALS['$dbname'].
                        "", $GLOBALS['$dbuser'], $GLOBALS['$dbpass']);

        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // select query
        $sql = "SELECT lat, lng

                FROM country_name_map

                WHERE code = '$country_code'";
        }
        catch(PDOException $e) {
          echo $e->getMessage();
        }

        // prepare statement
        $stmt = $conn->prepare($sql);

        // execute query
        $stmt->execute();

        // create new empty array
        $infos = array();

        // set the resulting array to associative
        for($i=0; $row = $stmt->fetch(); $i++){
          array_push($infos, array( 'lat' => $row['lat'],
                                    'lng' => $row['lng']
                                                        ));
      }


      // close connession
      $conn = null;

      // return info inserted
      header('Content-Type: application/json');
      echo json_encode($infos);
    }
  }

  $rest = new RestServer();
  $rest->addServiceClass('AddInfo');
  $rest->addServiceClass('GetInfo');
  $rest->addServiceClass('GetCountryCoor');
  $rest->handle();
