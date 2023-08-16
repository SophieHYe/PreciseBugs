<?php

class user
{
    static function remove($uid)
    {
        global $dbh;

        include_once 'pear-database-note.php';
        note::removeAll($uid);

        include_once 'pear-rest.php';
        $pear_rest = new pearweb_Channel_REST_Generator(PEAR_REST_PATH, $dbh);
        $pear_rest->deleteMaintainerREST($uid);
        $pear_rest->saveAllMaintainersREST();
        $dbh->query('DELETE FROM users WHERE handle = ?', array($uid));
        return ($dbh->affectedRows() > 0);
    }

    static function rejectRequest($uid, $reason)
    {
        global $dbh, $auth_user;
        list($email) = $dbh->getRow('SELECT email FROM users WHERE handle = ?',
                                    array($uid));

        include_once 'pear-database-note.php';
        note::add($uid, "Account rejected: $reason");
        $msg = "Your PEAR account request was rejected by " . $auth_user->handle . ":\n\n".
             "$reason\n";
        $xhdr = 'From: ' . $auth_user->handle . '@php.net';
        if (!DEVBOX) {
            mail($email, "Your PEAR Account Request", $msg, $xhdr, '-f ' . PEAR_BOUNCE_EMAIL);
        }
        return true;
    }

    static function activate($uid, $karmalevel = 'pear.dev')
    {
        require_once 'Damblan/Karma.php';

        global $dbh, $auth_user;

        $karma = new Damblan_Karma($dbh);

        $user = user::info($uid, null, 0);
        if (!isset($user['registered'])) {
            return false;
        }
        @$arr = unserialize($user['userinfo']);

        include_once 'pear-database-note.php';
        note::removeAll($uid);

        $data = array();
        $data['registered'] = 1;
        $data['active']     = 1;
        /* $data['ppp_only'] = 0; */
        if (is_array($arr)) {
            $data['userinfo'] = $arr[1];
        }
        $data['created']   = gmdate('Y-m-d H:i');
        $data['createdby'] = $auth_user->handle;
        $data['handle']    = $user['handle'];

        user::update($data, true);

        $karma->grant($user['handle'], $karmalevel);
        if ($karma->has($user['handle'], 'pear.dev')) {
            include_once 'pear-rest.php';
            $pear_rest = new pearweb_Channel_REST_Generator(PEAR_REST_PATH, $dbh);
            $pear_rest->saveMaintainerREST($user['handle']);
            $pear_rest->saveAllMaintainersREST();
        }

        include_once 'pear-database-note.php';
        note::add($uid, "Account opened");
        $msg = "Your PEAR account request has been opened.\n".
             "To log in, go to http://" . PEAR_CHANNELNAME . "/ and click on \"login\" in\n".
             "the top-right menu.\n";
        $xhdr = 'From: ' . $auth_user->handle . '@php.net';
        if (!DEVBOX) {
            mail($user['email'], "Your PEAR Account Request", $msg, $xhdr, '-f ' . PEAR_BOUNCE_EMAIL);
        }
        return true;
    }

    static function isAdmin($handle)
    {
        require_once 'Damblan/Karma.php';

        global $dbh;
        $karma = new Damblan_Karma($dbh);
        return $karma->has($handle, 'pear.admin');
    }

    static function isQA($handle)
    {
        require_once 'Damblan/Karma.php';

        global $dbh;
        $karma = new Damblan_Karma($dbh);
        return $karma->has($handle, 'pear.qa');
    }

    static function exists($handle)
    {
        global $dbh;
        $sql = 'SELECT handle FROM users WHERE handle = ?';
        $res = $dbh->query($sql, array($handle));
        return ($res->numRows() > 0);
    }

    static function maintains($user, $pkgid, $role = 'any')
    {
        global $dbh;
        include_once 'pear-database-package.php';

        $package_id = package::info($pkgid, 'id');
        if ($role == 'any') {
            return $dbh->getOne('SELECT role FROM maintains WHERE handle = ? '.
                                'AND package = ?', array($user, $package_id));
        }

        if (is_array($role)) {
            $res = $dbh->getOne('SELECT role FROM maintains WHERE handle = ? AND package = ? '.
                                'AND role IN ("' . implode('", "', $role) . '")', array($user, $package_id));
            return $res;
        }

        return $dbh->getOne('SELECT role FROM maintains WHERE handle = ? AND package = ? '.
                            'AND role = ?', array($user, $package_id, $role));
    }

    static function getPackages($user, $onlyApprovedPackages = false)
    {
        global $dbh;
        $query = 'SELECT p.id, p.name, m.role, m.active'
            . ' FROM packages p, maintains m'
            . ' WHERE m.handle = ? AND p.id = m.package AND p.package_type = ?'
            . (($onlyApprovedPackages) ? ' AND approved = 1' : '')
            . ' ORDER BY p.name';

        return $dbh->getAll($query, array($user, SITE));
    }

    static function getProposals($user)
    {
        global $dbh;

        $query = 'SELECT id, pkg_name, status,'
            . ' draft_date, proposal_date, vote_date'
            . ' FROM package_proposals'
            . ' WHERE user_handle = ? ORDER BY draft_date ASC';

        return $dbh->getAll($query, array($user));
    }

    static function info($user, $field = null, $registered = true, $hidePassword = true)
    {
        global $dbh;
        
        if (!$dbh) {
            return null;
        }

        $handle = strpos($user, '@') ? 'email' : 'handle';

        if ($field === null) {
            $sql  = 'SELECT * FROM users WHERE ' . $handle . ' = ?';
            $data = array($user);
            if ($registered !== 'any') {
                $sql.= ' AND registered = ?';
                $data[] = $registered === true ? '1' : '0';
            }

            $row = $dbh->getRow($sql, $data, DB_FETCHMODE_ASSOC);

            if ($hidePassword) {
                unset($row['password']);
            }
            return $row;
        }

        if (($field == 'password' && $hidePassword) || preg_match('/[^0-9a-z]/', $user)) {
            return null;
        }

        $sql = 'SELECT ! FROM users WHERE handle = ?';
        $data = array($field, $user);
        if ($registered !== 'any') {
            $sql.= ' AND registered = ?';
            $data[] = $registered === true ? '1' : '0';
        }

        return $dbh->getRow($sql, $data, DB_FETCHMODE_ASSOC);
    }

    static function listAll($registered_only = true)
    {
        global $dbh;
        $query = 'SELECT * FROM users';
        if ($registered_only === true) {
            $query .= ' WHERE registered = 1';
        }
        $query .= ' ORDER BY handle';
        return $dbh->getAll($query, null, DB_FETCHMODE_ASSOC);
    }

    static function listRecentUsersByKarma($karma, $limit)
    {
        global $dbh;
        $query = 'SELECT * FROM users u
                    JOIN karma k ON k.user = u.handle
                    WHERE k.level = ? 
                    ORDER BY granted_at DESC LIMIT ?';

        return $dbh->getAll($query, array($karma, $limit), DB_FETCHMODE_ASSOC);
    }


    static function listAllHandles($registered_only = true)
    {
        global $dbh;
        $query = 'SELECT handle FROM users';
        if ($registered_only === true) {
            $query .= ' WHERE registered = 1';
        }
        $query .= ' ORDER BY handle';
        return $dbh->getAll($query, null, DB_FETCHMODE_ASSOC);
    }

    /**
     * Add a new user account
     *
     * During most of this method's operation, PEAR's error handling
     * is set to PEAR_ERROR_RETURN.
     *
     * @param array   $data  Information about the user
     * @param boolean $md5ed true if the password has been hashed already
     * @param boolean $automatic true if this is an automatic account request
     *
     * @return mixed  true if there are no problems, false if sending the
     *                email failed, 'set error' if DB_storage::set() failed
     *                or an array of error messages for other problems
     *
     * @access public
     */
    static function add(&$data, $md5ed = false, $automatic = false)
    {
        global $dbh;

        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        $errors = array();

        $required = array(
            'handle'     => 'Username',
            'firstname'  => 'First Name',
            'lastname'   => 'Last Name',
            'email'      => 'Email address',
            'purpose'    => 'Intended purpose',
        );

        $name = $data['firstname'] . " " . $data['lastname'];

        foreach ($required as $field => $desc) {
            if (empty($data[$field])) {
                $data['jumpto'] = $field;
                $errors[] = 'Please enter ' . $desc;
            }
        }

        if (!preg_match(PEAR_COMMON_USER_NAME_REGEX, $data['handle'])) {
            $errors[] = 'Username must start with a letter and contain'
                      . ' only letters and digits';
        }

        // Basic name validation

        // First- and lastname must be longer than 1 character
        if (strlen($data['firstname']) == 1) {
            $errors[] = 'Your firstname appears to be too short.';
        }
        if (strlen($data['lastname']) == 1) {
            $errors[] = 'Your lastname appears to be too short.';
        }

        // No names with only uppercase letters
        if ($data['firstname'] === strtoupper($data['firstname'])) {
            $errors[] = 'Your firstname must not consist of only uppercase letters.';
        }
        if ($data['lastname'] === strtoupper($data['lastname'])) {
            $errors[] = 'Your lastname must not consist of only uppercase letters.';
        }

        if ($data['password'] != $data['password2']) {
            $data['password'] = $data['password2'] = "";
            $data['jumpto'] = "password";
            $errors[] = 'Passwords did not match';
        }

        if (!$data['password']) {
            $data['jumpto'] = "password";
            $errors[] = 'Empty passwords not allowed';
        }

        $handle = strtolower($data['handle']);
        $info   = user::info($handle, null, 'any');

        if (is_array($info) && isset($info['created'])) {
            $data['jumpto'] = "handle";
            $errors[] = 'Sorry, that username is already taken';
        }

        if ($errors) {
            $data['display_form'] = true;
            return $errors;
        }

        $data['display_form'] = false;
        $md5pw = $md5ed ? $data['password'] : md5($data['password']);
        $showemail = @(bool)$data['showemail'];
        // hack to temporarily embed the "purpose" in
        // the user's "userinfo" column
        $userinfo = serialize(array($data['purpose'], $data['moreinfo']));
        $set_vars = array(
            'handle'     => $handle,
            'name'       => $name,
            'email'      => $data['email'],
            'homepage'   => $data['homepage'],
            'showemail'  => $showemail,
            'password'   => $md5pw,
            'registered' => 0,
            'userinfo'   => $userinfo,
            'from_site'  => SITE,
        );

        $dbh->expectError(DB_ERROR_CONSTRAINT);
        PEAR::pushErrorHandling(PEAR_ERROR_CALLBACK, 'report_warning');

        $sql = '
            INSERT INTO users
                (handle, name, email, homepage, showemail, password, registered, userinfo, from_site)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $err = $dbh->query($sql, $set_vars);
        $dbh->popExpect();
        if (DB::isError($err)) {
            return $err;
        }

        PEAR::popErrorHandling();

        $msg = "Requested from:   {$_SERVER['REMOTE_ADDR']}\n".
               "Username:         {$handle}\n".
               "Real Name:        {$name}\n".
               (isset($data['showemail']) ? "Email:            {$data['email']}\n" : "") .
               "Purpose:\n".
               "{$data['purpose']}\n\n".
               "To handle: http://{$_SERVER['SERVER_NAME']}/admin/?acreq={$handle}\n";

        if ($data['moreinfo']) {
            $msg .= "\nMore info:\n{$data['moreinfo']}\n";
        }

        $xhdr = "From: $name <{$data['email']}>\nMessage-Id: <account-request-{$handle}@" .
            PEAR_CHANNELNAME . ">\n";
        // $xhdr .= "\nBCC: " . PEAR_GROUP_EMAIL;
        $subject = "PEAR Account Request: {$handle}";

        $ok = true;
        if (!DEVBOX && !$automatic && PEAR_CHANNELNAME == 'pear.php.net') {
            $ok = @mail(PEAR_GROUP_EMAIL, $subject, $msg, $xhdr, '-f ' . PEAR_BOUNCE_EMAIL);
        }

        PEAR::popErrorHandling();

        return $ok;
    }

    /**
     * Update user information
     *
     * @access public
     * @param  array User information
     * @return object|boolean DB error object on failure, true on success
     */
    static function update($data, $admin = false)
    {
        global $dbh;

        if (!isset($data['handle'])) {
            return false;
        }

        $fields = array(
            'name',
            'email',
            'homepage',
            'showemail',
            'userinfo',
            'pgpkeyid',
            'wishlist',
            'latitude',
            'longitude',
            'active',
            'password',
            'from_site',
        );

        if ($admin) {
            $fields[] = 'registered';
            $fields[] = 'created';
            $fields[] = 'createdby';
        }
        $info = user::info($data['handle'], null, 'any');
        // In case a active value isn't passed in
        $active = isset($info['active']) ? $info['active'] : true;

        $change_k = $change_v = array();
        foreach ($data as $key => $value) {
            if (!in_array($key, $fields)) {
                continue;
            }
            $change_k[] = $key;
            $change_v[] = $value;
        }

        $sql = 'UPDATE users SET ' . "\n";
        foreach ($change_k as $k) {
            $sql .= $k . ' = ?,' . "\n";
        }
        $sql = substr($sql, 0, -2);
        $sql.= ' WHERE handle = ?';

        $change_v[] = $data['handle'];
        $err = $dbh->query($sql, $change_v);
        if (DB::isError($err)) {
            return $err;
        }

        if (isset($data['active']) && $data['active'] === 0 && $active) {
            // this user is completely inactive, so mark all maintains as not active.
            $dbh->query('UPDATE maintains SET active = 0 WHERE handle = ?', array($info['handle']));
        }
        return true;
    }

    /**
     * Get recent releases for the given user
     *
     * @access public
     * @param  string Handle of the user
     * @param  int    Number of releases (default is 10)
     * @return array
     */
    static function getRecentReleases($handle, $n = 10)
    {
        global $dbh;
        $recent = array();

        $query = '
            SELECT
                p.id AS id,
                p.name AS name,
                p.summary AS summary,
                r.version AS version,
                r.releasedate AS releasedate,
                r.releasenotes AS releasenotes,
                r.doneby AS doneby,
                r.state AS state
            FROM packages p, releases r, maintains m
            WHERE
                p.package_type = ?
                AND p.id = r.package
                AND p.id = m.package
                AND m.handle = ?
            ORDER BY r.releasedate DESC';

        $sth = $dbh->limitQuery($query, 0, $n, array(SITE, $handle));
        while ($sth->fetchInto($row, DB_FETCHMODE_ASSOC)) {
            $recent[] = $row;
        }
        return $recent;
    }
}
