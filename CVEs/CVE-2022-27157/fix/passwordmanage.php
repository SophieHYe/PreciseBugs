<?php
class Users_PasswordManage
{
    var $_dbh;
    var $_mailer;
    function __construct()
    {
        $this->_dbh = &$GLOBALS['dbh'];
    }

    /**
     * Confirm the user's request to reset the password
     *
     * @param string $user
     * @param string $salt
     * @return array
     */
    function confirmReset($user, $salt)
    {
        $errors = array();
        PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
        $e = $this->_dbh->getOne('SELECT newpassword FROM lostpassword WHERE
            handle=? AND salt=?', array($user, $salt));
        PEAR::staticPopErrorHandling();
        if (PEAR::isError($e)) {
            return array($e->getMessage());
        }

        if (!$e) {
            return array('Could not retrieve password based on username/salt combination');
        }

        PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
        $e = $this->_dbh->query('UPDATE users set password = ? WHERE handle = ?', array($e, $user));
        if (!PEAR::isError($e)) {
            $this->_dbh->query('DELETE FROM lostpassword WHERE handle = ?', array($user));
        }
        PEAR::staticPopErrorHandling();
        if (PEAR::isError($e)) {
            return array($e->getMessage());
        }

        return array();
    }

    /**
     * Mark a user for password resetting
     *
     * @param string $user
     * @param string $pass1
     * @param string $pass2
     * @return array
     */
    function resetPassword($user, $pass1, $pass2)
    {
        require_once 'Damblan/Mailer.php';
        $errors = array();
        $random_bytes = openssl_random_pseudo_bytes(16, $strong);
        if ($random_bytes === false || $strong === false) {
            $errors[] = "Could not generate a safe password token";
            return $errors;
        }
        $salt = md5($rand_bytes);
        PEAR::staticPushErrorHandling(PEAR_ERROR_RETURN);
        $this->_dbh->query('DELETE FROM lostpassword WHERE handle=?', array($user));
        $e = $this->_dbh->query('INSERT INTO lostpassword
            (handle, newpassword, salt, requested)
            VALUES(?,?,?,NOW())', array($user, md5($pass1), $salt));
        PEAR::staticPopErrorHandling();
        if (PEAR::isError($e)) {
            $errors[] = 'Could not change password: ' . $e->getMessage();
        } else {
            include_once 'pear-database-user.php';
            $info = user::info($user);
            $this->_mailer = Damblan_mailer::create(array(
        'To'       => array($info['name'] . ' <' . $info['email'] . '>'),
        'Reply-To' => array('PEAR QA <' . PEAR_QA_EMAIL . '>'),
        'Subject' => '[PEAR-ACCOUNT-PASSWORD] Your password reset request : %username%',
        'Body' => 'A request has been made to reset your password for %username%
at pear.php.net.

If you intended to reset the password, please navigate to this page:
  https://' . PEAR_CHANNELNAME . '/account/password-confirm-change.php
and follow the instructions.  Your password reset code is:

%salt%

If you have received this email by mistake or did not request a
password change, no further action is necessary.  Your password
will NOT change until you confirm the change, and it cannot be changed
without the password reset code.  Password change requests are automatically
purged after 24 hours.

PEAR Quality Assurance.'), array('username' => $user, 'salt' => $salt));
            $this->_mailer->send();
        }
        return $errors;
    }
}
