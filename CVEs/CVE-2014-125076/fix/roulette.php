<?php
/*
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>. 
 */

require_once('../init.php');

// Check if user is loggedin, if so no need to be here...
if (LOGGEDIN == FALSE) { header('Location: ' . ROOT_URL . 'index.php'); }

$error = array();
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Check if number is given and if it is correct
    if (empty($_POST['number'])) {
        if (isset($_POST['color']) AND !empty($_POST['color'])) {
            if ($_POST['color'] != 1) {
                $error[] = 'Je hebt geen nummer opgegeven.';
            }
        } else {
            $error[] = 'Je hebt geen nummer opgegeven.';
        }
    }
    elseif ($_POST['number'] < 0 OR $_POST['number'] > 36) {
        $error[] = 'Nummer wat er is ingegeven kan niet.';
    }
    
    // check if gamble money has enterd and if the user has that money
    if (!isset($_POST['gambleMoney']) OR empty($_POST['gambleMoney'])) {
        $error[] = 'Je hebt geen inzet ingegeven.';
    }
    elseif(!ctype_digit($_POST['gambleMoney'])) {
        $error[] = 'Je inzet is niet numeriek..';
    } else {
        $result = $dbCon->query('SELECT cash FROM users WHERE session_id = "' . $userData['session_id'] . '"');
        $row = $result->fetch_assoc();
        
        if ($row['cash'] < $_POST['gambleMoney']) {
            $error[] = 'Je inzet is hoger dan je nu in cash hebt.';
        }
    }
    
    // check if color is found
    if (!isset($_POST['color']) OR empty($_POST['color'])) {
        $error[] = 'Je hebt geen kleur ingegeven.';
    }
    elseif ($_POST['color'] < 0 OR $_POST['color'] > 3) {
        $error[] = 'De kleur die er is opgegeven bestaat niet.';
    }
    
    if (count($error) > 0) {
        foreach ($error as $item) {
            $form_error .= '- ' . $item . '<br />';
        }
        $tpl->assign('form_error', $form_error);
    } else {
        // We can play now
        
        $numberWon = rand(0,36);
        
        // Somone thought it would be fun that $_POST containing 0 is just empty...
        if ($_POST['color'] == 1) {
            $numberPlayer = 0;
        } else {
            $numberPlayer = $_POST['number'];
        }

        // user won
        if ($numberWon == $numberPlayer) {
            $dbCon->query('UPDATE users SET cash = (cash + "' . (int) (addslashes($_POST['gambleMoney']) * 36) . '") WHERE id = "' . $userData['id'] . '"');
            $tpl->assign('success', 'Je hebt de roulette gewonnen je wint 36x je inzet!');
        } else {
            // user lost
            $dbCon->query('UPDATE users SET cash = (cash - "' . (int) addslashes($_POST['gambleMoney']) . '") WHERE id = "' . $userData['id'] . '"');
            $tpl->assign('form_error', 'Helaas je hebt verloren, gelukkig heb je alleen je inzet verloren!');
        }
    }
}

$tpl->display('ingame/roulette.tpl');