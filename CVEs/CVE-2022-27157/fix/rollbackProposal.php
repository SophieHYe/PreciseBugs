#!/usr/bin/env php
<?php
require_once 'MDB2.php';
$dsn  = 'mysqli://pear:pear@localhost/pear';

try {
    $proposal_id = (int) $argv[1];
    if ($proposal_id < 1) {
        throw new InvalidArgumentException("Please supply a number: ./rollback.php NUM");
    }

    $mdb2 = MDB2::connect($dsn);
    if (MDB2::isError($mdb2)) {
        throw new RuntimeException("Could not connect to database: {$mdb2->getDebugInfo()}");
    }
    $pearweb = new Pearweb_Rollback($proposal_id, $mdb2);
    $pearweb->rollback();

} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}

echo "All is well.\n";
exit;

class Pearweb_Rollback
{
    protected $mdb2;
    protected $proposal;

    public function __construct($proposal, MDB2_Driver_Common $mdb2)
    {
        if (!is_int($proposal)) {
            throw new InvalidArgumentException('$proposal must be an int');
        }
        $this->proposal = $proposal;
        $this->mdb2     = $mdb2;
    }

    public function rollback()
    {
        $this->moveVotes();
        echo "Moved votes to comments...\n";

        $this->resetDate();
        echo "Reset vote date...\n";

        $this->resetStatus();
        echo "Reset status...\n";
    }

    protected function moveVotes()
    {
        $sql = "SELECT * FROM package_proposal_votes WHERE pkg_prop_id = {$this->proposal}";
        $res = $this->mdb2->query($sql);
        if (MDB2::isError($res)) {
            throw new RuntimeException("DB error occurred: {$res->getDebugInfo()}");
        }
        if ($res->numRows() == 0) {
            return; // nothing to do
        }

        $insert  = "INSERT INTO package_proposal_comments (";
        $insert .= "user_handle, pkg_prop_id, timestamp, comment";
        $insert .= ") VALUES(%s, {$this->proposal}, %d, %s)";

        $delete  = "DELETE FROM package_proposal_votes WHERE";
        $delete .= " pkg_prop_id = {$this->proposal}";
        $delete .= " AND user_handle = %s";

        while ($row = $res->fetchRow(MDB2_FETCHMODE_OBJECT)) {

            $comment  = "Original vote: {$row->value}\n";
            $comment .= "Conditional vote: " . (($row->is_conditional != 0)?'yes':'no') . "\n";
            $comment .= "Comment on vote: " . $row->comment . "\n";
            $reviewed = "Reviewed: n/a";
            try {
                $uInfo = unserialize($row->reviews, ['allowed_classes' => false]);
                if ($uInfo !== false) {
                    $reviewed = "Reviewed: " . implode(", ", $uInfo);
                }
            } catch (Exception $ex) {
                // do nothing
            }
            $comment .= $reviewed;

            $sql = sprintf(
                $insert,
                $this->mdb2->quote($row->user_handle),
                $row->timestamp,
                $this->mdb2->quote($comment)
            );
            $this->queryChange($sql);

            $sql = sprintf(
                $delete,
                $this->mdb2->quote($row->user_handle)
            );
            $this->queryChange($sql);
        }

        $res->free();
        return true;
    }

    protected function queryChange($sql)
    {
        $affected = $this->mdb2->exec($sql);
        if (MDB2::isError($affected)) {
            throw new RuntimeException("DB error occurred: " . $affected->getDebugInfo());
        }
        if ($affected < 1) {
            throw new UnexpectedValueException("No rows affected. Invalid proposal ID?");
        }
        return true;
    }

    protected function resetDate()
    {
        $sql = "UPDATE package_proposals SET vote_date = null WHERE id = {$this->proposal}";
        return $this->queryChange($sql);
    }

    protected function resetStatus()
    {
        $sql = "UPDATE package_proposals SET status='proposal' WHERE id = {$this->proposal}";
        return $this->queryChange($sql);
    }
}
