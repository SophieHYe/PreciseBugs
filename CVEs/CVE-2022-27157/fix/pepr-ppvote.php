<?php
/**
 * Establishes the procedures, objects and variables used throughout PEPr.
 *
 * The <var>$proposalReviewsMap</var> arrays is defined here.
 *
 * NOTE: Proposal constants are defined in pearweb/include/pear-config.php.
 *
 * This source file is subject to version 3.0 of the PHP license,
 * that is bundled with this package in the file LICENSE, and is
 * available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.
 * If you did not receive a copy of the PHP license and are unable to
 * obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately.
 *
 * @category  pearweb
 * @package   PEPr
 * @author    Tobias Schlitt <toby@php.net>
 * @author    Daniel Convissor <danielc@php.net>
 * @copyright Copyright (c) 1997-2005 The PHP Group
 * @license   http://www.php.net/license/3_0.txt  PHP License
 * @version   $Id$
 */

global $proposalReviewsMap;
$proposalReviewsMap = array(
                            'cursory'   => 'Cursory source review',
                            'deep'      => 'Deep source review',
                            'test'      => 'Run examples');

class ppVote
{
    var $pkg_prop_id;
    var $user_handle;
    var $value;
    var $reviews = array();
    var $is_conditional;
    var $comment;
    var $timestamp;

    function __construct($dbhResArr)
    {
        foreach ($dbhResArr as $name => $value) {
            $this->$name = $value;
        }
    }

    function get(&$dbh, $proposalId, $handle)
    {
        $sql = "SELECT *, UNIX_TIMESTAMP(timestamp) AS timestamp FROM package_proposal_votes WHERE pkg_prop_id = ". $dbh->quoteSmart($proposalId) ." AND user_handle= ". $dbh->quoteSmart($handle);
        $res = $dbh->query($sql);
        if (DB::isError($res)) {
            return $res;
        }
        if (!$res->numRows()) {
            return null;
        }
        $set = $res->fetchRow(DB_FETCHMODE_ASSOC);
        try {
            $unserialised = unserialize($set['reviews'], ['allowed_classes' => false]);
            if ($unserialised !== false) {
                $set['reviews'] = $unserialised;
            }
        } catch (Exception $ex) {
            $set['reviews'] = array();
        }
        $vote = new ppVote($set);
        return $vote;
    }

    function &getAll(&$dbh, $proposalId)
    {
        $sql = "SELECT *, UNIX_TIMESTAMP(timestamp) AS timestamp FROM package_proposal_votes WHERE pkg_prop_id = ". $dbh->quoteSmart($proposalId) ." ORDER BY timestamp ASC";
        $res = $dbh->query($sql);
        if (DB::isError($res)) {
            return $res;
        }
        $votes = array();
        while ($set = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            $uReviews = unserialize($set['reviews'], ['allowed_classes' => false]);
            if ($uReviews !== false) {
                $set['reviews'] = $uReviews;
            }
            $votes[$set['user_handle']] = new ppVote($set);
        }
        return $votes;
    }

    function store($dbh, $proposalId)
    {
        if (empty($this->user_handle)) {
            return PEAR::raiseError("Not initialized");
        }
        $sql = "INSERT INTO package_proposal_votes (pkg_prop_id, user_handle, value, is_conditional, comment, reviews)
                    VALUES (". $dbh->quoteSmart($proposalId).", ".$dbh->quoteSmart($this->user_handle).", ".$this->value.", ".(int)$this->is_conditional.", ".$dbh->quoteSmart($this->comment).", ".$dbh->quoteSmart(serialize($this->reviews)).")";
        $res = $dbh->query($sql);
        return $res;
    }

    function getReviews($humanReadable = false)
    {
        if ($humanReadable) {
            $res = array();
            if (!empty($this->reviews)) {
                foreach ((array)$this->reviews as $review) {
                    $res[] = $GLOBALS['proposalReviewsMap'][$review];
                }
            }
            return $res;
        }
        return $this->reviews;
    }

    function getSum($dbh, $proposalId)
    {
        $sql = "SELECT SUM(value) FROM package_proposal_votes WHERE pkg_prop_id = ".$proposalId." GROUP BY pkg_prop_id";
        $result = $dbh->getOne($sql);
        $res['all'] = (is_numeric($result)) ? $result : 0;
        $sql = "SELECT SUM(value) FROM package_proposal_votes WHERE pkg_prop_id = ".$proposalId." AND is_conditional = 1 GROUP BY pkg_prop_id";
        $result = $dbh->getOne($sql);
        $res['conditional'] = (is_numeric($result)) ? $result : 0;
        return $res;
    }

    function getCount($dbh, $proposalId)
    {
        $sql = "SELECT COUNT(user_handle) FROM package_proposal_votes WHERE pkg_prop_id = ".$dbh->quoteSmart($proposalId)." GROUP BY pkg_prop_id";
        $res = $dbh->getOne($sql);
        return (!empty($res)) ? $res: " 0";
    }

    function hasVoted($dbh, $userHandle, $proposalId)
    {
        $sql = "SELECT count(pkg_prop_id) as votecount FROM package_proposal_votes
                    WHERE pkg_prop_id = ".$dbh->quoteSmart($proposalId)." AND user_handle = ".$dbh->quoteSmart($userHandle)."
                    GROUP BY pkg_prop_id";
        $votes = $dbh->query($sql);
        return (bool)($votes->numRows());
    }

}
