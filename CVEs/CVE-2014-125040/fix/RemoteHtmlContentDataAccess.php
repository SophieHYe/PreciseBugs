<?php

require_once 'php/model/remotehtml/RemoteHTMLContent.php';
require_once 'php/model/remotehtml/dateparsing/DateFieldInformationFactory.php';

class RemoteHtmlContentDataAccess {
    public static function getAll() {
        return self::getForQueryString('SELECT * FROM "DevNewsAggregatorConfiguration_htmlcontent" WHERE enabled = true');
    }

    public static function getForUser($userId) {
        $query = ' SELECT html_content.* ' .
                    ' FROM "DevNewsAggregatorConfiguration_htmlcontent" html_content ' .
                    ' INNER JOIN "DevNewsAggregatorConfiguration_htmlcontent_users" htmlcontent_users ' .
                    ' ON html_content.id = htmlcontent_users.htmlcontent_id ' .
                    ' WHERE html_content.enabled = true ' .
                    " AND htmlcontent_users.user_id = $1 ";

        return self::getForQueryString($query, array($userId));
    }

    public static function getByName($name) {
        return self::getForQueryString("SELECT * FROM \"DevNewsAggregatorConfiguration_htmlcontent\" WHERE name = $1", array($name));
    }

    private static function getForQueryString($query, $params=array()) {
        $connection = pg_connect("host=localhost port=5432 dbname=DevNewsAggregator user=DevNews password=DevNews") or die("Could not connect to Postgres");
        $result = pg_query_params($connection, $query, $params) or die("Could not execute query");

        $remoteHTMLContent = array();

        while($row = pg_fetch_array($result)) {
            $dateFieldInformation = DateFieldInformationFactory::create($row);
            $remoteHTMLContent[] =  new RemoteHTMLContent($row['url'], $row['name'], $row['scraping_strategy'], $row['outer_content_selector'], $row['inner_content_selector'], $row['title_selector'],
                $dateFieldInformation, $row['ignore_first_n_posts'], $row['ignore_last_n_posts']);
        }

        pg_close($connection);

        return $remoteHTMLContent;
    }
} 