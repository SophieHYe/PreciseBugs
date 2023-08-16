<?php
    include_once("procStrategies.php");

	session_start();
	
	// для динамической загрузки в div'ы турниров
	
	$nonUpdatableTournamentChuncks = array
									(
										'tournament', 'getSource', 'getLog', 'startRound', 
										'jqueryUserStrategyDownloadStrategy', 'jqueryUserStrategySetActStatus',
										'visualize', 'jqueryResetTournamentState'
									);
	$unsetTournamentState = true;
	
	foreach ($nonUpdatableTournamentChuncks as $script)
		$unsetTournamentState = $unsetTournamentState & !stripos($_SERVER['PHP_SELF'], $script);
	
	if ($unsetTournamentState)
	{
		unset($_SESSION['tournamentState']);
		unset($_SESSION['tournamentDuel']);
		unset($_SESSION['roundResultsRoundId']);
		unset($_SESSION['roundTableRoundId']);
	}
	
	// для динамической загрузки админки
	
	$nonUpdatableAdminPanelChuncks = array
										(
											'adminPanel', 'jqueryGetRoundUsers', 'jqueryChecker', 
											'jqueryAttachment', 'jqueryNews', 'jqueryTournament', 
											'jqueryGetCheckerList', 'startRound', 'jqueryGame',
											'getChecker', 'jqueryGetNewRoundId', 'visualize', 
											'jqueryFaq'
										);
	$unsetAdminPanelState = true;
	
	foreach ($nonUpdatableAdminPanelChuncks as $script)
	{
		$unsetAdminPanelState = $unsetAdminPanelState & !stripos($_SERVER['PHP_SELF'], $script);
	}
		
	if ($unsetAdminPanelState)
	{
		// удалять
		unset($_SESSION['adminPanelState']);
		unset($_SESSION['adminGameId']);
		unset($_SESSION['adminAttachmentId']);
		unset($_SESSION['adminCheckerId']);
		unset($_SESSION['adminQuestionStatusId']);
		unset($_SESSION['adminQuestionId']);
		unset($_SESSION['adminNewsId']);
		unset($_SESSION['adminTournamentId']);
		unset($_SESSION['adminRoundId']);
		unset($_SESSION['adminImgTypeId']);
		unset($_SESSION['adminImgGameId']);
	}

    function rusStatus($status)
    {
        if ($status == "WIN 1")
            return "Выиграл первый игрок";
        if ($status == "WIN 2")
            return "Выиграл второй игрок";
        if ($status == "TIE")
            return "Ничья";
        if ($status == "IM 1")
            return "Неверный ход первого игрока";
        if ($status == "IM 2")
            return "Неверный ход второго игрока";
        if ($status == "RE 1")
            return "Ошибка выполнения у первого игрока";
        if ($status == "RE 2")
            return "Ошибка выполнения у второго игрока";
         if ($status == "TL 1")
            return "Превышен лимит по времени у первого игрока";
         if ($status == "TL 2")
            return "Превышен лимит по времени у второго игрока";
         if ($status == "ML 1")
            return "Превышен лимит по памяти у первого игрока";
         if ($status == "ML 2")
            return "Превышен лимит по памяти у второго игрока";
        if ($status == "IE")
            return "Внутренняя ошибка";
        return $status;
    }
	
	function clearTournamentState()
	{
		unset($_SESSION['tournamentState']);
		unset($_SESSION['tournamentDuel']);
		unset($_SESSION['roundResultsRoundId']);
		unset($_SESSION['roundTableRoundId']);
	}
	
	// Получение линка соединения с БД
	function getDBConnection()
	{
		$file = @file('authData.txt') or die("Can't find data file! <a href=install.php>Install system</a>"); // 0 - login, 1 - password, 2 - DB's name
		
		$login = $file[0];
		$password = $file[1];
		// Удаляем лишние пробелы
		$login = trim($login);
		$password = trim($password);
		$link = mysqli_connect('localhost', $login, $password) or die("Can't connect to DB: ".mysqli_error());
		return $link;
	}

	// Получение названия БД
	function getDBName()
	{	
		$file = @file('authData.txt') or die("Can't find data file! <a href=install.php>Install system</a>");
		return trim($file[2]);
	}
	
	// аналог mysql_query
	// $query - запрос, $row - строка в запросе, $field - номер поля
	function mysqli_result($query, $row, $field = 0) 
	{
		mysqli_data_seek($query, $row);
		$data = mysqli_fetch_array($query);
		return $data[$field];
	} 
	
	// Проверка пользователя (есть ли он на сайте)
	function isActiveUser()
	{
		return isset($_SESSION['SBUserid']) and isset($_SESSION['SBUserhash']);
	}
	
	// Получение ID пользователя
	function getActiveUserID()
	{
		if (!isActiveUser())
			return -1;
		return $_SESSION['SBUserid'];
	}
	
	// Получение ника пользователя
	function getActiveUserNickname()
	{
		$link = getDBConnection();
		
		if (isActiveUser() && mysqli_select_db($link, getDBName()))
		{
			$userId = intval($_SESSION['SBUserid']);
			$nickname = mysqli_query($link, "SELECT login FROM users WHERE id=".$userId." LIMIT 1");
			return mysqli_result($nickname, 0);
		}
		else return "Anonymous";
	}

	// Получение имени пользователя
	function getNicknameById($id = -1)
	{
		$link = getDBConnection();
		if ($id == -1) 
			$id = getActiveUserID();
		if ($id != -1 && mysqli_select_db($link, getDBName()))
		{
			$id = intval($id);
			$userName = mysqli_query($link, "SELECT login FROM users WHERE id = $id");
			return mysqli_result($userName, 0);
		} 
		else 
			return "Anonymous";	
	}
	
    // Отключение пользователя
    function logOff()
    {
        session_start();
        session_unset();
        session_destroy();
        session_commit();
        setcookie(session_name(), '', 0, '/');
        session_regenerate_id(true);
    }
	
    // Принадлежность к группе пользователей
    function isUserInGroup($group, $id="")
    {
        if (isset($_SESSION['SBUserid']))
        {
            $id = ($id == "") ? $_SESSION['SBUserid'] : intval($id);
			$link = getDBConnection();
			if (mysqli_select_db($link, getDBName()))
			{
				$groupExisting = false;
				$group = mysqli_real_escape_string($link, $group);
				$groupQuery = mysqli_query($link, "SELECT `id` FROM `users` WHERE `group` = '$group'");
				while ($adminData = @mysqli_fetch_assoc($groupQuery))
				{
					if ($id === intval($adminData['id'])) 
					{	
						$groupExisting = true;
						break;
					}
				}
				return $groupExisting;
			} else return false;
		} else return false;
	}
	
	// Проверка пользователя на принадлежность касте администраторов
	function isAdmin()
	{
		return isUserInGroup('admin');
	}
	
	// Проверка пользователя на принадлежность к создателям новостей
	function isNewsMaker()
	{
		return isUserInGroup('news');
	}
	
	// Проверка пользователя на принадлежность к модераторам
	function isModerator()
	{
		return isUserInGroup('moder');
    }

    function isBanned()
    {
        return isUserInGroup('banned');
    }
	
	// Генерация уникального кода
	function generateUniqueCode($length=6) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHI JKLMNOPRQSTUVWXYZ0123456789";
		$code = "";
		$clen = strlen($chars) - 1;  
		
		while (strlen($code) < $length) $code .= $chars[mt_rand(0, $clen)];  
		return $code;
	}
	
	// Сохранение файла на диск
	function saveFileOnDisc($path, $source, $fileTypes = NULL)
	{
		if (is_uploaded_file($_FILES[$source]["tmp_name"]))
		{
			if (!$fileTypes || ($fileTypes && in_array(pathinfo($_FILES[$source]['name'], PATHINFO_EXTENSION), $fileTypes)))
			{
				move_uploaded_file($_FILES[$source]['tmp_name'], $path.$_FILES[$source]['name']);
				return true;
			}
			else if ($fileTypes != NULL) return "Не тот формат загружаемого файла!";
		} else return "Не удалось загрузить файл!";
	}
	
	// Получение расширения файла
	function getFileExtension($source)
	{
		return pathinfo($_FILES[$source]['name'], PATHINFO_EXTENSION);
	}

	// Сохранение файла на диск
	function saveFileOnDisc2($path, $source)
	{
		return move_uploaded_file($_FILES[$source]['tmp_name'], $path);
	}
	
	// Рекурсивное удаление папки вместе с файлами
	function removeDir($path)
	{
		if (is_dir($path))
		{
			$handle = opendir($path);
			while (($subfile = readdir($handle)) !== FALSE) 
			{
				if ($subfile == '.' || $subfile == '..') continue;
				if (is_file($subfile)) 
					unlink("$path/$subfile");
				else 
					removeDir("$path/$subfile");
			}
			closedir($handle);
			rmdir($path);
		} else unlink($path);
	}
	
	// Получение очков игрока в раунде
	function getPlayerScore($round, $strategy)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$round 		= intval($round);
			$strategy 	= intval($strategy);
			$query = mysqli_query($link, "SELECT score FROM scores WHERE round = $round AND strategy = $strategy");
			return mysqli_result($query, 0);
		}
	}
	
	// Изменение очков игрока в раунде
	function setPlayerScore($round, $strategy, $value)
	{
		$score = intval(getPlayerScore($round, $strategy)) + $value;
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$round 		= intval($round);
			$strategy 	= intval($strategy);
			mysqli_query($link, "UPDATE scores SET score = $score WHERE round = $round AND strategy = $strategy");
		}
	}
	
	function getGameByDuel($duel)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$duel = intval($duel);
			$gameId = mysqli_query($link, "SELECT games.id FROM games INNER JOIN strategies s ON games.id = s.game INNER JOIN duels ON duels.strategy1 = s.id WHERE duels.id = $duel");
			return mysqli_result($gameId, 0);
		}
		return -1;
	}

	function getVisualizerByGame($gameId)
	{
		if ($gameId == -1)
			return false;
		else
		{
			$link = getDBConnection();
			if (mysqli_select_db($link, getDBName()))
			{
				$gameId = intval($gameId);
				return mysqli_result(mysqli_query($link, "SELECT hasVisualizer FROM games WHERE id = $gameId"), 0);
			}
			else return -1;
		}
	}

    function isActiveUserHasAccessToDuel($duel)
    {
    	$link = getDBConnection();
        if (!mysqli_select_db($link, getDBName()))
            return false;
        
        if (isAdmin())
    	    return true;
        $userId = intval(getActiveUserID());
        $duel = intval($duel);
        $duelParams = mysqli_query($link, "SELECT round, strategy1, strategy2 FROM duels WHERE id = $duel");
        $round = mysqli_result($duelParams, 0, 0);
        $s1 = mysqli_result($duelParams, 0, 1);
        $s2 = mysqli_result($duelParams, 0, 2);
        if ($round != -1)
        {
            if (mysqli_result(mysqli_query($link, "SELECT visible FROM rounds WHERE id=$round"), 0) != true)
                return false;
            else
                return true;
        }
        if (getUserIdByStrategy($s1) == $userId
            || getUserIdByStrategy($s2) == $userId)
        {
            return true;
        }

    	return false;
    }

	// Получить название игры
	function getGameName($id = -1)
	{
		$link = getDBConnection();
		$id = intval($id);
		if ($id != -1 && mysqli_select_db($link, getDBName()))
		{
			$game = mysqli_query($link, "SELECT name FROM games WHERE id = $id");
			return @mysqli_result($game, 0);
		} 
		else 
			return "Unknown";	
	}
	
	// Получить массив с названиями игр
	function getGameArray()
	{
		$link = getDBConnection();
		$names = array();
		
		if (mysqli_select_db($link, getDBName()))
		{
			$query = mysqli_query($link, "SELECT id, name FROM games");
			while ($data = mysqli_fetch_assoc($query))
				$names[$data['id']] = $data['name'];
		}
		
		return $names;
	}
	
	// Получить все текущие турниры
	function getRunningAndClosedTournaments()
	{
		$link = getDBConnection();
		$tournaments = array();
		
		if (mysqli_select_db($link, getDBName()))
		{
			$query = mysqli_query($link, "SELECT id, name FROM tournaments WHERE state = 'running' OR state = 'closed'");
			while ($data = mysqli_fetch_assoc($query))
				$tournaments[$data['id']] = $data['name'];
		}
		
		return $tournaments;
	}
	
	// Получить параметр игры
	function getGameParameter($id, $parameter)
	{
		$link = getDBConnection();
		$id = intval($id);
		if ($id == -1 || $parameter == "" || !mysqli_select_db($link, getDBName()))
			return "Unknown";
		else
		{
			$parameter = mysqli_real_escape_string($link, $parameter);
			$value = mysqli_query($link, "SELECT {$parameter} FROM games WHERE id = $id");
			if (mysqli_num_rows($value))
				return @mysqli_result($value, 0);
			else return "Unknown";
		}
	}
	
	// Сохранение тестера для выбранной игры
	function saveTester($id, $source)
	{
		$path = addslashes("./testers/").$id;
		$result = saveFileOnDisc2($path, $source);
		
		if ($result == false)
			return -1;
		else
		{
			$output = array();
            $answer = 0;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                $execResult = exec("clChecker.bat $id", $output, $answer);
            else
                $execResult = exec("./gccChecker.sh $id", $output, $answer);
			return $answer;
		}
		
	}

	// Сохранение визуализатора для выбранной игры
	function saveVisualizer($id, $source)
	{
		if (isset($source) && $_FILES[$source]["error"] == 0)
		{
			$link = getDBConnection();
			if (mysqli_select_db($link, getDBName()))
			{
				$id = intval($id);
				mysqli_query($link, "UPDATE Games SET hasVisualizer=1 WHERE id=".$id.";");
				$path = addslashes("./visualizers/").$id;
				return saveFileOnDisc2($path, $source);
			}
			else return false;
		}
		else return false;
	}
	
	// Сохранение аттачмента
	function saveAttachment($id, $source)
	{
		if ($_FILES[$source]["error"] == 0)
		{
			$path = addslashes("./attachments/").$id;
			return saveFileOnDisc2($path, $source);
		}
	}
	
    // Регистрация
    function registerUser($postLogin, $postPassword)
    {
        // $_POST['login']
        // $_POST['password']
        $link = getDBConnection();
        $reason = "";
        if (mysqli_select_db($link, getDBName()))
        {
            $err = array();

            $login = strip_tags($postLogin);
            if ($login != $postLogin)
                $err[] = "Логин содержит некорректные символы";
            $postLogin = mysqli_real_escape_string($link, $postLogin);
            if (strlen($postLogin) < 3 or strlen($postLogin > 30))
                $err[] = "Логин должен быть не меньше 3-х символов и не больше 30";

            $query = mysqli_query($link, "SELECT COUNT(id) FROM users WHERE login='{$login}'");

            if (@mysqli_result($query, 0) > 0)
                $err[] = "Пользователь с таким логином уже существует в базе данных";

            if (count($err) == 0)
            {
                $password = md5(md5(trim($postPassword)));
                mysqli_query($link, "INSERT INTO users SET login='".$postLogin."', password='$password'");
                $reason = "Вы зарегистрированы в системе!";
            }
            else
            {
                $reason = "<b>При регистрации произошли следующие ошибки:</b><br>";
                foreach($err as $error)
                    $reason = $reason.$error."<br>";
            }
        }
        else
        {
            $reason = "Нет возможности подключиться к БД!";
        }

        return $reason;
    }

// Авторизация

	// Получение данных о пользователе при авторизации
	function getAuthorizationData()
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
			$query = mysqli_query($link, "SELECT * FROM users WHERE login='".mysqli_real_escape_string($link, $_POST['login'])."' LIMIT 1");
		$data = mysqli_fetch_assoc($query);
		return $data;
	}
	
	// Запись данных при авторизации
	function LogIn($hash, $id)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$id = intval($id);
			mysqli_query($link, "UPDATE users SET hash='".$hash."' WHERE id='".$id."'");
			/*
			setcookie("SBUserid", $id, time()+60*60*24*30);
			setcookie("SBUserhash", $hash, time()+60*60*24*30);
			*/
			$_SESSION['SBUserid'] = $id;
			$_SESSION['SBUserhash'] = $hash;
		}
	}
	
	// Дизайн
	
	// Вывод заголовка на экран
	function getPageHeaderByScriptName($scriptName)
	{
		//
		$headers = array
		(
			"index.php" 				=> "AI Battle - Главная",
			"userAuthorization.php" 	=> "AI Battle - Вход",
			"userRegistration.php" 		=> "AI Battle - Регистрация"
		);
		
		foreach ($headers as $script => $header)
			if (strpos($scriptName, $script))
				return $header;
	}
	
	// Выделение активной вкладки
	function getActiveNavbarElement($role, $scriptName)
	{
		$elements = array
		(
			"news" => "index.php", 
			"tournament" => "tournament.php", 
			"game" => "game.php", 
			"users" => "users.php", 
			"faq" => "faq.php",
			"adminPanel" => "adminPanel.php"
		);
		
		foreach ($elements as $key => $element)
		{
			if ($role == $key && strpos($scriptName, $element))
			{
				return "active";
			}
		}
				
		return "";
	}
	
	// Турнир
	
	// Получение информации о турнире
	function getTournamentData($tournamentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournamentId = intval($tournamentId);
			$query = mysqli_query($link, "SELECT * FROM tournaments WHERE id = $tournamentId LIMIT 1");
			$data = mysqli_fetch_assoc($query);
			mysqli_free_result($query);
		}
		
		return $data; 
	}
	
	// получение игры по турниру
	function getGameByTournament($tournamentId)
	{
		if ($tournamentId == -1)
			return -1;
		else 
		{
			$data = getTournamentData($tournamentId);
			return $data['game'];
		}
	}
	
	// Получение описание игры
	function getGameDescription($gameId)
	{
		$link = getDBConnection();
		$gameDescription = "none";
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			$query = mysqli_query($link, "SELECT description FROM games WHERE id = $gameId LIMIT 1");
			$data = mysqli_fetch_assoc($query);
			$gameDescription = $data['description'];
			mysqli_free_result($query);
		}
		return $gameDescription;
	}
	
	// Получение описания турнира
	function getTournamentDescriptionByTournamentId($tournamentId)
	{
		$link = getDBConnection();
		$tournamentDescription = "none";
		if (mysqli_select_db($link, getDBName()))
		{
			$tournamentId = intval($tournamentId);
			$query = mysqli_query($link, "SELECT description FROM tournaments WHERE id = $tournamentId LIMIT 1");
			$data = mysqli_fetch_assoc($query);
			$tournamentDescription = $data['description'];
			mysqli_free_result($query);
		}
		return $tournamentDescription;
	}
	
	// получение массива attachment'ов
	function getGameAttachments($gameId)
	{
		$link = getDBConnection();
		$attachments = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			$query = mysqli_query($link, "SELECT * FROM attachments WHERE game = $gameId");
			while ($data = mysqli_fetch_assoc($query))
				$attachments[$data['id']] = array('originalName' => $data['originalName'], 'description' => $data['description']);
		}
		return $attachments;
	}
	
    // получение стратегий определенного юзера
    function getUserStrategies($gameId, $userId, $tournamentId, $ok, $first=0, $size=-1)
    {
        $link = getDBConnection();
        $strategies = array();
        if (mysqli_select_db($link, getDBName()))
        {
            $gameId = intval($gameId);
            $userId = intval($userId);
            $tournamentId = intval($tournamentId);
            $sql = "SELECT id, user, status FROM strategies WHERE game = $gameId AND user = $userId AND tournament = $tournamentId ";
            if ($ok)
                $sql .= " AND (status = 'OK' OR status = 'ACT') ";
            $query = mysqli_query($link, $sql . " ORDER BY id DESC".(($size != -1) ? " LIMIT $first, $size" : " "));
            while ($data = mysqli_fetch_assoc($query))
                $strategies[] = $data;
        }
        return $strategies;
    }

	function getUserMessages($first=0, $size=-1, $id = 0, $notreaded = false)
    {
        if (!isActiveUser())
            return false;
		$link = getDBConnection();
        $messages = array();
		mysqli_select_db($link, getDBName());
        $userId = intval(getActiveUserID());
		$query = mysqli_query($link, "SELECT * FROM `privateMessages` WHERE (`sender` = $userId OR `recevier` = $userId) ".($id != 0 ? " AND id=".$id : "").($notreaded ? " AND viewed=0" : "")." ORDER BY id DESC".(($size != -1) ? " LIMIT $first, $size" : " "));
	    while ($data = mysqli_fetch_assoc($query))
            $messages[$data['id']] = array(
                'sender' => $data['sender'],
                'recevier' => $data['recevier'],
                'title' => $data['title'],
                'text' => $data['text'],
                'date' => $data['date'],
                'viewed' => (($data['sender'] == $userId) ? 1 : $data['viewed'])
            );
		return $messages;
    }

    function postMessage($recevier, $title, $text)
    {
        if (!isActiveUser())
            return false;
        $link = getDBConnection();
        $messages = array();
        mysqli_select_db($link, getDBName());
        $userId = intval(getActiveUserID());
        $recevier = intval($recevier);
        $title = mysqli_real_escape_string($link, $title);
        $text = mysqli_real_escape_string($link, $text);
        $query = mysqli_query($link, "INSERT INTO `privateMessages` VALUES (NULL, $userId, $recevier, '$title', '$text', 0, 0)");
        if ($query)
            return true;
        return false;
    }

    function markMessageAsViewed($id)
    {
        if (!isActiveUser())
            return false;
        $userId = intval(getActiveUserID());
        $id = intval($id);
        $messages = getUserMessages(0, -1, $id);
        if (!isset($messages[$id]))
            return false;
        $link = getDBConnection();
        mysqli_select_db($link, getDBName());
        if (mysqli_query($link, "UPDATE `privateMessages` SET `viewed`=1 WHERE `id`=$id"))
            return true;
        return false;
    }

    function getNotViewedMessages()
    {
        if (!isActiveUser())
            return 0;
        $userId = intval(getActiveUserID());
        $link = getDBConnection();
        mysqli_select_db($link, getDBName());
        $query = mysqli_query($link, "SELECT COUNT(*) FROM `privateMessages` WHERE viewed=0 AND recevier = $userId");
        $res = mysqli_fetch_array($query);
        return $res[0];      
    }

	
	// проверка на существование игры с выбранным ID
	function isGameExists($gameID)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
			return mysqli_num_rows(mysqli_query($link, "SELECT * FROM `games` WHERE id = ". intval($gameID))) > 0;
		
	}
	
	// Дуэли
	function getUserStrategy($gameId, $tournamentId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			$tournamentId = intval($tournamentId);
			return mysqli_query($link, "SELECT id FROM strategies WHERE tournament = $tournamentId AND game = $gameId AND status = 'ACT' AND user=".intval(getActiveUserID()));
		}
    }
	
	function getDuels($roundId, $gameId, $userId, $tournamentId = -1, $start = 0, $limit = -1)
	{
	
		$roundId 		= intval($roundId);
		$gameId 		= intval($gameId);
		$userId			= intval($userId);
		$tournamentId 	= intval($tournamentId);
		
		$query = "SELECT duels.id AS id, duels.status AS status, strategy1, strategy2, s1.user AS user1, s2.user AS user2 FROM duels";
		$query .= " INNER JOIN strategies s1 ON duels.strategy1 = s1.id INNER JOIN strategies s2 ON duels.strategy2 = s2.id";
		$query .= " INNER JOIN games ON games.id = s1.game AND games.id = s2.game";
		if (!isAdmin() && $roundId != -1)
			$query .= " INNER JOIN rounds ON rounds.id = duels.round";
		$query .= " WHERE duels.round = $roundId AND games.id = $gameId";
		if (!isAdmin() && $roundId != -1)
			$query .= " AND rounds.visible = true";
		if (!isAdmin())
			$query .= " AND (s1.user = $userId OR s2.user = $userId)";
		
		if ($tournamentId != -1)
			$query .= " AND (s1.tournament = $tournamentId AND s2.tournament = $tournamentId)";
					
        $query .= " ORDER BY id DESC";
        if ($limit != -1)
            $query.= " LIMIT $start, $limit";
		
		//echo $query;
		
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$duels = mysqli_query($link, $query);
			
			$data = array();
			while ($row = mysqli_fetch_assoc($duels))
				$data[] = $row;
				
			mysqli_free_result($duels);
		
			return $data;
		}
		else return array();
    }

    function getDuelsCount($roundId, $gameId, $userId, $tournamentId = -1)
	{
	
		$roundId 		= intval($roundId);
		$gameId 		= intval($gameId);
		$userId			= intval($userId);
		$tournamentId 	= intval($tournamentId);
		
		$query = "SELECT COUNT(*) FROM duels";
		$query .= " INNER JOIN strategies s1 ON duels.strategy1 = s1.id INNER JOIN strategies s2 ON duels.strategy2 = s2.id";
		$query .= " INNER JOIN games ON games.id = s1.game AND games.id = s2.game";
		if (!isAdmin() && $roundId != -1)
			$query .= " INNER JOIN rounds ON rounds.id = duels.round";
		$query .= " WHERE duels.round = $roundId AND games.id = $gameId";
		if (!isAdmin() && $roundId != -1)
			$query .= " AND rounds.visible = true";
		if (!isAdmin())
			$query .= " AND (s1.user = $userId OR s2.user = $userId)";
		
		if ($tournamentId != -1)
			$query .= " AND (s1.tournament = $tournamentId AND s2.tournament = $tournamentId)";
					
		
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$duels = mysqli_query($link, $query);
			
			$data = mysqli_fetch_array($duels);
				
			mysqli_free_result($duels);
		
			return $data[0];
		}
		else return 0;
	}
	
	function getDuelHeader($roundId, $gameId)
	{
		$header = "";
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId 	= intval($gameId);
			$roundId 	= intval($roundId);
			$gameName 	= mysqli_result(mysqli_query($link, "SELECT name FROM games WHERE id = $gameId"), 0);
			if ($roundId == -1)
				$header = "Тренировка";
			else
			{
				$roundName 	= mysqli_result(mysqli_query($link, "SELECT name FROM rounds WHERE id = $roundId"), 0);
				$header = "Партии раунда ".$roundName;
			}
			
			$header = $header . " игры ".$gameName;
		}
		return $header;
	}
	
	// раунды
	
	// Получить раунды турнира
	function getTournamentRounds($tournamentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournamentId = intval($tournamentId);
			$query = mysqli_query($link, "SELECT * FROM rounds WHERE tournament = $tournamentId");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// получить текущую стратегию
	function getCurrentStrategy($userId, $tournamentId)
	{
		$link = getDBConnection();
		$strategyId = -1;
		if (mysqli_select_db($link, getDBName()))
		{
			$userId 		= intval($userId);
			$tournamentId 	= intval($tournamentId);
			$query = mysqli_query($link, "SELECT id FROM strategies WHERE user = $userId AND tournament = $tournamentId AND status = 'ACT'");
			$data = mysqli_fetch_assoc($query);
			$strategyId = $data['id'];
			mysqli_free_result($query);
		}
		return $strategyId;
	}
	
	// получить текущих игроков раунда
	function getRoundPlayers($roundId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT user FROM roundActivity WHERE round = $roundId AND state = 'ACT'");
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = getCurrentStrategy($row['user'], mysqli_result(mysqli_query($link, "SELECT tournament FROM rounds WHERE id = $roundId"), 0));
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// получить информацию о раунде
	function getRoundData($roundId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT * FROM rounds WHERE id = $roundId");
			
			if ($row = mysqli_fetch_assoc($query))
			{
				$data['id'] 		= $row['game'];
				$data['tournament']	= $row['tournament'];
				$data['game']		= $row['game'];
				$data['name'] 		= $row['name'];
				$data['date'] 		= $row['date'];
				$data['visible'] 	= $row['visible'];
			}
			if (!$row || (!isAdmin() && !$data['visible']))
            {
				$data['id'] 		= -1;
				$data['tournament']	= "Unknown";
				$data['game']		= "Unknown";
				$data['name'] 		= "Unknown";
				$data['date'] 		= "Unknown";
				$data['visible'] 	= false;
			}
			
			mysqli_free_result($query);
			
			$query = mysqli_query($link, "SELECT * FROM games WHERE id = {$data['id']}");
			if ($row = mysqli_fetch_assoc($query))
				$data['gameName'] = $row['name'];
			else
				$data['gameName'] = "Unknown";
			
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// Получить турнир по раунду
	function getTournamentByRound($roundId)
	{
		$roundData = getRoundData($roundId);
		return $roundData['tournament'];
	}
	
	// получить юзеров и очки в раунде
	function getUsersRoundScores($roundId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT users.login AS name, users.id AS id, score FROM scores INNER JOIN strategies ON scores.strategy = strategies.id INNER JOIN users ON strategies.user = users.id WHERE scores.round = $roundId ORDER BY score DESC");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
    
    // получить юзеров и очки в раунде без сортировки
	function getUsersRoundScoresNoSort($roundId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT users.login AS name, users.id AS id, score FROM scores INNER JOIN strategies ON scores.strategy = strategies.id INNER JOIN users ON strategies.user = users.id WHERE scores.round = $roundId");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// получить информацию по таблице раунда
	function getRoundTable($roundId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT users.id AS id, users.login AS name, score FROM scores INNER JOIN strategies ON scores.strategy = strategies.id INNER JOIN users ON strategies.user = users.id WHERE scores.round = $roundId ORDER BY score DESC");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// получить статус дуэли
	function getDuelStatus($roundId, $user1, $user2)
	{
		$link = getDBConnection();
		$data = "";
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId 	= intval($roundId);
			$user1 		= intval($user1);
			$user2 		= intval($user2);
			$query = mysqli_query($link, "SELECT duels.status AS status, duels.id AS id FROM duels INNER JOIN strategies s1 ON duels.strategy1 = s1.id INNER JOIN strategies s2 ON duels.strategy2 = s2.id WHERE duels.round = $roundId AND s1.user = $user1 AND s2.user = $user2");
			$data = mysqli_fetch_assoc($query);
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// Admin Panel
	
	// получить данные по играм
	function getTournamentList($tournamentId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournamentId = intval($tournamentId);
			if ($tournamentId == -1)
				$query = mysqli_query($link, "SELECT id, name FROM tournaments");
			else
				$query = mysqli_query($link, "SELECT * FROM tournaments WHERE id = $tournamentId");
				
			if ($tournamentId == -1)
				while ($row = mysqli_fetch_assoc($query))
					$data[] = $row;
			else
				$data = mysqli_fetch_assoc($query);
			
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// получить список игр
	function getGameList($gameId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			if ($gameId == -1)
				$query = mysqli_query($link, "SELECT * FROM games");
			else
				$query = mysqli_query($link, "SELECT * FROM games WHERE id = $gameId");
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// создать турнир
	function createNewTournament($name, $game, $description, $state, $checker)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$name 			= mysqli_real_escape_string($link, $name);
			$description 	= mysqli_real_escape_string($link, $description);
			$state			= mysqli_real_escape_string($link, $state);
			$game			= intval($game);
			$checker		= intval($checker);
			
			if (mysqli_query($link, "INSERT INTO tournaments SET name = '$name', game = $game, description = '$description', state = '$state', defaultChecker = $checker"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// обновить турнир
	function updateTournament($id, $name, $game, $description, $state, $checker)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$id 			= intval($id);
			$game 			= intval($game);
			$checker 		= intval($checker);
			$name			= mysqli_real_escape_string($link, $name);
			$description 	= mysqli_real_escape_string($link, $description);
			$state			= mysqli_real_escape_string($link, $state);
			
			if (mysqli_query($link, "UPDATE tournaments SET name = '$name', game = $game, description = '$description', state = '$state', defaultChecker = $checker WHERE id = $id"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// удалить турнир
	function deleteTournament($id)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$id = intval($id);
			if (mysqli_query($link, "DELETE FROM tournaments WHERE id = $id"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// создать игру
	function createGame($name, $description, $visualizer, $timeLimit, $memoryLimit)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$name 			= mysqli_real_escape_string($link, $name);
			$description 	= mysqli_real_escape_string($link, $description);
			$timeLimit 		= intval($timeLimit);
			$memoryLimit 	= intval($memoryLimit);
			
			$queryText = "INSERT INTO games SET name = '$name', description = '$description'";
			if ($timeLimit != "")
				$queryText .= ", timeLimit = $timeLimit";
			if ($memoryLimit != "")
				$queryText .= ", memoryLimit = $memoryLimit";
				
			if (mysqli_query($link, $queryText))
			{
				$gameId = mysqli_insert_id($link);
			
				if ($_FILES[$visualizer]["error"] == 0)
				{
					$result = saveVisualizer($gameId, $visualizer);
					if ($result == true)
						return 0;
					else
						return -1;
				}
				else
				{
					mysqli_query($link, "DELETE FROM games WHERE id = $gameId");
					return 1;
				}
			}
			else return 2;
		}
		else return 3;
	}
	
	// обновить игру
	function updateGame($id, $name, $description, $visualizer, $timeLimit, $memoryLimit)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$id 			= intval($id);
			$name 			= mysqli_real_escape_string($link, $name);
			$description 	= mysqli_real_escape_string($link, $description);
			$timeLimit 		= intval($timeLimit);
			$memoryLimit 	= intval($memoryLimit);
			
			if (mysqli_query($link, "UPDATE games SET name = '$name', description = '$description', timeLimit = $timeLimit, memoryLimit = $memoryLimit WHERE id = $id"))
			{
				if ($_FILES[$visualizer]["error"] == 0)
				{
					$result = saveVisualizer($id, $visualizer);
					if ($result == true)
						return 0;
					else
						return -1;
				}
				else return 1;
			}
			else return 2;
		}
		else return 3;
	}
	
	// удалить игру
	function deleteGame($gameId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			if (mysqli_query($link, "DELETE FROM games WHERE id = $gameId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// получить путь к визуализатору
	function getVisualizerPath($id)
	{
		return addslashes("./visualizers/").$id;
	}
	
	// удалить визуализатор
	function deleteVizualizer($gameId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			if (mysqli_query($link, "UPDATE games SET hasVisualizer = 0 WHERE id = $gameId"))
			{
				removeDir(getVisualizerPath($gameId));
				if (!file_exists(getVisualizerPath($gameId)))
					return 0;
				else
					return -1;
			}
			else return 1;
		}
		else return 2;
	}
	
	// получить список раундов турнира
	function getRoundList($tournamentId, $roundId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournamentId 	= intval($tournamentId);
			$roundId		= intval($roundId);
			$text = "SELECT * FROM rounds WHERE tournament = $tournamentId";
			if ($roundId != -1)
				$text .= " AND id = $roundId";
			$text .= " ORDER BY id";
			$query = mysqli_query($link, $text);
			
			if ($roundId == -1)	
				while ($row = mysqli_fetch_assoc($query))
					$data[] = $row;
			else
				$data = mysqli_fetch_assoc($query);
				
			mysqli_free_result($query);
		}
		return $data;
	}
	
	/*
		еще
	*/
	
	// checkers
	function getCheckerList($checkerId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$checkerId = intval($checkerId);
			$text = "SELECT * FROM checkers";
			if ($checkerId != -1)
				$text .= " WHERE id = $checkerId";
			$query = mysqli_query($link, $text);
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
				
			mysqli_free_result($query);
		}
		return $data;
	}
		
	// Сохранить checker
	function saveChecker($name, $game, $checker, $hasSeed)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$name = mysqli_real_escape_string($link, $name);
			$game = intval($game);
			$hasSeed = intval($hasSeed);
			
			if (mysqli_query($link, "INSERT INTO checkers SET name = '$name', game = $game, hasSeed = $hasSeed") == true)
			{
				if ($_FILES[$checker]["error"] == 0)
				{
					$checkerId = mysqli_insert_id($link);
					$result = saveTester($checkerId, $checker);
					if ($result != 0)
						mysqli_query($link, "DELETE FROM checkers WHERE id = $checkerId");
						
					if ($result == 0 || $result == -1)
						return $result;
					else
					{
						mysqli_query($link, "DELETE FROM checkers WHERE id = $checkerId");
						removeDir(getCheckerById($checkerId));
						return 'e' . $result;
					}
				}
				else 
				{
					mysqli_query($link, "DELETE FROM checkers WHERE id = $checkerId");
					return 1;
				}
			}
			else return 2;
		}
		else return 3;
	}
	
	// Обновить checker
	function updateChecker($id, $name, $game, $checker, $hasSeed)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$id 	= intval($id);
			$name 	= mysqli_real_escape_string($link, $name);
			$game 	= intval($game);
			$hasSeed = intval($hasSeed);
			
			if (mysqli_query($link, "UPDATE checkers SET name = '$name', game = $game, hasSeed = $hasSeed WHERE id = $id") == true)
			{
				if ($_FILES[$checker]["error"] == 0)
				{
					$result = saveTester($id, $checker);
					if ($result == 0 || $result == -1)
						return $result;
					else
						return 'e' . $result;
				}
				else return 1;
			}
			else return 2;
		}
		else return 3;
	}
	
	function getCheckerById($checkerId)
	{
		return addslashes("./testers/").$checkerId;
	}
	
	function getCheckerExeById($checkerId)
	{
		return addslashes("./testers_bin/").$checkerId.".exe";
	}
	
	function deleteChecker($checkerId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$checkerId = intval($checkerId);
			if (mysqli_query($link, "DELETE FROM checkers WHERE id = $checkerId") == true)
			{
				
				removeDir(getCheckerById($checkerId));
				removeDir(getCheckerExeById($checkerId));
				
				if (!file_exists(getCheckerById($checkerId)) && !file_exists(getCheckerExeById($checkerId)))
					return 0;
				else
				{
					if (file_exists(getCheckerById($checkerId)) && file_exists(getCheckerExeById($checkerId)))
						return 1;
					else if (file_exists(getCheckerById($checkerId)))
						return 2;
					else
						return 3;
				}
			}
			else return 4;
		}
		else return 5;
	}
	
	// получить лист чекеров по играм
	function getCheckerListByGameId($gameId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			
			$query = mysqli_query($link, "SELECT * FROM checkers WHERE game = $gameId");
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
				
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// Создать раунд
	function createRound($tournamentId, $roundName, $checker, $previousRound, $seed)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournament = getTournamentList($tournamentId);
			$gameId = $tournament['game'];
			$roundName = mysqli_real_escape_string($link, $roundName);
			$checker = mysqli_real_escape_string($link, $checker);
			$previousRound 	= mysqli_real_escape_string($link, $previousRound);
			
			if (mysqli_query($link, "INSERT INTO rounds SET tournament = $tournamentId, name = '$roundName', game = $gameId, checker = $checker, previousRound = $previousRound, seed = $seed"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Обновить раунд
	function updateRound($tournamentId, $roundId, $roundName, $checker, $previousRound, $seed)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$tournament = getTournamentList($tournamentId);
			$gameId	= $tournament['game'];
			
			$roundName = mysqli_real_escape_string($link, $roundName);
			$checker = intval($checker);
			$previousRound = intval($previousRound);
			
			if (mysqli_query($link, "UPDATE rounds SET tournament = $tournamentId, name = '$roundName', game = $gameId, checker = $checker, previousRound = $previousRound, seed = $seed WHERE id = $roundId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Получить предыдущий раунд
	function getPreviousRound($roundId)
	{
		$link = getDBConnection();
		$previousRound = -1;
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			$query = mysqli_query($link, "SELECT previousRound FROM rounds WHERE id = $roundId");
			$data = mysqli_fetch_assoc($query);
			$previousRound = $data['previousRound'];
			mysqli_free_result($query);
		}
		return $previousRound;
	}
	
	// Обновить допущенных к раунду игроков
	function updateActiveUsers($tournament, $roundId, $activeStrategies)
	{
		// доделать
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			mysqli_query($link, "DELETE FROM roundActivity WHERE round = $roundId");
			foreach ($activeStrategies as $stategyId)
			{
				$stategyId = intval($stategyId);
				$userId = mysqli_result(mysqli_query($link, "SELECT user FROM strategies WHERE id = $stategyId"), 0, "user");
				mysqli_query($link, "INSERT INTO roundActivity SET round = $roundId, user = $userId, state = 'ACT'");
			}
		}
	}
	
	// получить количество игроков, допущенных в текущий раунд
	function getAcceptedRoundUsers($roundId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			return mysqli_num_rows(mysqli_query($link, "SELECT * FROM roundActivity WHERE round = $roundId AND state = 'ACT'"));
		}
		else return 0;
	}
	
	/*
	// Получить всех юзеров с активными статегиями
	function getActiveStrategiesFromTournament($tournamentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$query = mysqli_query($link, "SELECT str.id, usr.login FROM strategies AS str INNER JOIN users AS usr ON usr.id = str.user WHERE str.tournament = $tournamentId AND str.status = 'ACT'");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
	*/
	
	// получить активных юзеров в этом раунде
	function getAcceptedUsers($roundId, $previousRoundId, $tournamentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId 			= intval($roundId);
			$previousRoundId 	= intval($previousRoundId);
			$tournamentId 		= intval($tournamentId);
			$queryText 			= "";
			
			if ($previousRoundId == -1)
			{
				$queryText = "SELECT str.id, usr.login FROM strategies AS str INNER JOIN users AS usr ON usr.id = str.user INNER JOIN roundActivity AS rndAct ON usr.id = rndAct.user WHERE rndAct.round = $roundId AND str.tournament = $tournamentId AND str.status = 'ACT' ORDER BY usr.login";
			}
			else
			{
				$queryText = "SELECT str.id, usr.login, scr.score FROM strategies AS str INNER JOIN users AS usr ON usr.id = str.user INNER JOIN scores AS scr ON scr.round = $previousRoundId AND scr.strategy = str.id INNER JOIN roundActivity AS rndAct ON usr.id = rndAct.user WHERE rndAct.round = $roundId AND str.tournament = $tournamentId AND str.status = 'ACT' ORDER BY scr.score DESC";
			}
			
			//echo "acceptedUsers: " . $queryText . '\n';
			
			$query = mysqli_query($link, $queryText);
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
			mysqli_free_result($query);
		}
		return $data;
	}
	
    // получить возможных юзеров в раунде
    function getPossibleUsers($roundId, $previousRoundId, $tournamentId)
    {
        $link = getDBConnection();
        $data = array();
        if (mysqli_select_db($link, getDBName()))
        {
            $roundId = intval($roundId);
            $previousRoundId = intval($previousRoundId);
            $tournamentId = intval($tournamentId);
            $queryText = "";
        
            if ($previousRoundId != -1)
            {
                $queryText = "SELECT str.id, usr.login, scr.score FROM strategies AS str INNER JOIN users AS usr ON usr.id = str.user AND usr.group != 'banned' INNER JOIN scores AS scr ON scr.round = $previousRoundId AND scr.strategy = str.id INNER JOIN roundActivity AS rndAct ON usr.id = rndAct.user WHERE rndAct.round = $previousRoundId AND str.tournament = $tournamentId AND str.status = 'ACT' AND usr.id NOT IN (SELECT user FROM roundActivity WHERE round = $roundId AND state = 'ACT') ORDER BY scr.score DESC";
            }
            else
            {
                $queryText = "SELECT str.id, usr.login FROM strategies AS str INNER JOIN users AS usr ON usr.id = str.user AND usr.group != 'banned' WHERE str.tournament = $tournamentId AND str.status = 'ACT' AND usr.id NOT IN (SELECT user FROM roundActivity WHERE round = $roundId AND state = 'ACT')";
                $queryText .= " ORDER BY usr.login";
            }
            			
            $query = mysqli_query($link, $queryText);
            while ($row = mysqli_fetch_assoc($query))
                $data[] = $row;
            mysqli_free_result($query);
        }
        return $data;
    }
	
	// Если в дуэлях раунд проведен, то повторный раз можно не делать
	function checkRoundInDuels($roundId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			return mysqli_result(mysqli_query($link, "SELECT COUNT(*) FROM duels WHERE round = $roundId"), 0);
		}
	}
	
	function isRoundVisible($roundId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			return mysqli_result(mysqli_query($link, "SELECT COUNT(*) FROM rounds WHERE id = $roundId AND visible = true"), 0);
		}
	}
	
	function setRoundVisible($roundId, $visibility = true)
    {
        if ($visibility)
            $visibility = "true";
        else
            $visibility = "false";
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			if (mysqli_query($link, "UPDATE rounds SET visible = $visibility WHERE id = $roundId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Количество проверенных дуэлей
	function getCheckedDuels($roundId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			return mysqli_result(mysqli_query($link, "SELECT COUNT(*) FROM duels WHERE round = $roundId AND status <> 'W'"), 0);
		}
	}
	
	// проверка завершения проверек раунда
	function checkRoundEnding($roundId)
	{	
		if ($roundId == -1)
			return false;
		$roundCount = getCheckedDuels($roundId);
		if ($roundCount == 0)
			return false;
		else
			return $roundCount == checkRoundInDuels($roundId);
	}
	
	// Новости
	
	// Получить все новости
	function getNewsData($newsId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			$queryText = "SELECT * FROM news";
			
			if ($newsId != -1)
			{
				$queryText .= " WHERE id = $newsId";
			}
			
			$queryText .= " ORDER BY date DESC, id DESC";
			
			$query = mysqli_query($link, $queryText);
			if ($newsId == -1)
			{
				while ($row = mysqli_fetch_assoc($query))
					$data[] = $row;
			} 
			else
			{
				$data = mysqli_fetch_assoc($query);
			}
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// создать новость
	function createNews($header, $text, $date)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$header = mysqli_real_escape_string($link, $header);
			$text 	= mysqli_real_escape_string($link, $text);
			$date	= mysqli_real_escape_string($link, $date);
			if (mysqli_query($link, "INSERT INTO news SET header = '$header', text = '$text', date = '$date'"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// обновить новость
	function updateNews($newsId, $header, $text, $date)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			$header = mysqli_real_escape_string($link, $header);
			$text 	= mysqli_real_escape_string($link, $text);
			$date	= mysqli_real_escape_string($link, $date);
			if (mysqli_query($link, "UPDATE news SET header = '$header', text = '$text', date = '$date' WHERE id = $newsId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// удалить новость
	function deleteNews($newsId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			if (mysqli_query($link, "DELETE FROM news WHERE id = $newsId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// reverse date
	function reverseDate($oldDate, $delimiter)
	{
		$postDate = explode($delimiter, $oldDate);
		return $postDate[2]."-".$postDate[1]."-".$postDate[0];
	}
	
	// Комментарии
	
	// Количество комментариев к новостям
	function getCommentsCount($newsId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			return mysqli_result(mysqli_query($link, "SELECT COUNT(*) FROM newsComments WHERE news = $newsId"), 0);
		}
	}
	
	// Получить комментарии
	function getComments($newsId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			$data 	= array();
			
			$query = mysqli_query($link, "SELECT * from newsComments WHERE news = $newsId ORDER BY date ASC");
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
		}
		return $data;
	}
	
	function getComment($commentId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId = intval($newsId);
			$data 	= array();
			
			$query = mysqli_query($link, "SELECT * from newsComments WHERE id = $commentId ORDER BY date ASC");
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
		}
		return $data;
	}
	
	// Отправить комментарий
	function sendComments($newsId, $text)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newsId 		= intval($newsId);
			$currentUserId 	= intval(getActiveUserID());
			$text = mysqli_real_escape_string($link, $text);
			if ($currentUserId != -1)
			{
				$query = mysqli_query($link, "INSERT INTO newsComments SET news = $newsId, user = $currentUserId, text = '$text', date = NOW()");
			}
		}
	}
	
	// Изменить комментарий
	function updateComment($commentId, $text)
	{
		$cData = getComment($commentId);
		if ($cData[0]['user'] == getActiveUserID() || isAdmin() || isModerator())
		{
			$link = getDBConnection();
			if (mysqli_select_db($link, getDBName()))
			{
				$commentId 	= intval($commentId);
				$text 		= mysqli_real_escape_string($link, $text);
			
				mysqli_query($link, "UPDATE newsComments SET text = '$text' WHERE id = $commentId");
			}
		}
	}
	
	// Удалить комментарий
	function deleteComment($commentId)
	{
		$cData = getComment($commentId);
		if ($cData[0]['user'] == getActiveUserID() || isAdmin() || isModerator())
		{
			$link = getDBConnection();
			if (mysqli_select_db($link, getDBName()))
			{
				$commentId = intval($commentId);
				mysqli_query($link, "DELETE FROM newsComments WHERE id = $commentId");
			}
		}
	}
	
	// Получить текст комментария
	function getCommentText($commentId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$commentId = intval($commentId);
			return mysqli_result(mysqli_query($link, "SELECT text FROM newsComments WHERE id = $commentId"), 0);
		}
	}
	
	// FAQ
	
	// Отправка вопроса
	function sendQuestion($text)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$currentUserId 	= intval(getActiveUserID());
			$text 			= mysqli_real_escape_string($link, $text);
			
			if (mysqli_query($link, "INSERT INTO userQuestions SET user = $currentUserId, question = '$text', status = 'opened'"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Получение вопросов определенного статуса
	function getQuestions($state)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$state = mysqli_real_escape_string($link, $state);
			$query = mysqli_query($link, "SELECT * FROM userQuestions WHERE status = '$state'");
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
		}
		return $data;
	}
	
	// Получение количества открытых вопрос
	function getOpenedQuestionCount()
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			return mysqli_result(mysqli_query($link, "SELECT COUNT(*) FROM userQuestions WHERE status = 'opened'"), 0);
		}
		else return 0;
	}
	
	// Получить статус по id
	function getQuestionStatusById($statusId)
	{
		$data = array("opened", "answered", "closed");
		return $data[$statusId];
	}
	
	// Получить вопросы
	function getQuestionData($statusId)
	{
		return getQuestions(getQuestionStatusById($statusId));
	}
	
	// Выбранный вопрос
	function getQuestionById($questionId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$questionId = intval($questionId);
			return mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM userQuestions WHERE id = $questionId"));
		}
	}
	
	// Создать вопрос
	function createAnswer($question, $answer)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$answer 		= mysqli_real_escape_string($link, $answer);
			$question 		= mysqli_real_escape_string($link, $question);
			$currentUserId 	= intval(getActiveUserID());
			
			if (mysqli_query($link, "INSERT INTO userQuestions SET user = $currentUserId, question = '$question', answer = '$answer', status = 'answered'"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Отредактировать вопрос
	function updateAnswer($postQuestionId, $question, $answer)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$postQuestionId = intval($postQuestionId);
			$answer 		= mysqli_real_escape_string($link, $answer);
			$question 		= mysqli_real_escape_string($link, $question);
			
			if (mysqli_query($link, "UPDATE userQuestions SET question = '$question', answer = '$answer', status = 'answered' WHERE id = $postQuestionId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Закрыть вопрос
	function closeAnswer($questionId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$questionId = intval($questionId);
			if (mysqli_query($link, "UPDATE userQuestions SET status = 'closed' WHERE id = $questionId"))
				return 0;
			else
				return 1;
		}
		else return 2;
	}
	
	// Аttachments
	function getAttachments($gameId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId = intval($gameId);
			
			$queryText = "SELECT * FROM attachments WHERE game = $gameId";
		
			$query = mysqli_query($link, $queryText);
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
				
			mysqli_free_result($query);
		}
		return $data;
	}
	
	function getAttachmentById($attachmentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$attachmentId = intval($attachmentId);
			
			$query = mysqli_query($link, "SELECT * FROM attachments WHERE id = $attachmentId");
			$data = mysqli_fetch_assoc($query);
			mysqli_free_result($query);
		}
		return $data;
	}
	
	function getAttachmentPath($id)
	{
		return addslashes("./attachments/").$id;
	}
	
	function createAttachment($gameId, $originalName, $description, $attachment)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$gameId			= intval($gameId);
			$originalName 	= mysqli_real_escape_string($link, $originalName);
			$description 	= mysqli_real_escape_string($link, $description);
			if (mysqli_query($link, "INSERT INTO attachments SET game = $gameId, originalName = '$originalName', description = '$description'") == true)
			{
				$attachmentId = mysqli_insert_id($link);
			
				if ($_FILES[$attachment]["error"] == 0)
				{
					$result = saveAttachment($attachmentId, $attachment);
					if ($result == true)
						return 0;
					else
					{
						mysqli_query($link, "DELETE FROM attachments WHERE id = $attachmentId");
						return -1;
					}
				}
				else
				{
					mysqli_query($link, "DELETE FROM attachments WHERE id = $attachmentId");
					return 1;
				}
			}
			else return 2;
		}
		else return 3;
	}
	
	function updateAttachment($attachmentId, $gameId, $originalName, $description, $attachment)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$attachmentId			= intval($attachmentId);
			$gameId					= intval($gameId);
			$originalName 			= mysqli_real_escape_string($link, $originalName);
			$description 			= mysqli_real_escape_string($link, $description);
			if (mysqli_query($link, "UPDATE attachments SET game = $gameId, originalName = '$originalName', description = '$description' WHERE id = $attachmentId"))
			{
				if ($_FILES[$attachment]["error"] == 0)
				{
					$result = saveAttachment($attachmentId, $attachment);
					if ($result == true)
						return 0;
					else
						return -1;
				}
				else return 1;
			}
			else return 2;
		}
		else return 3;
	}
	
	function deleteAttachment($attachmentId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$attachmentId = intval($attachmentId);
			if (mysqli_query($link, "DELETE FROM attachments WHERE id = $attachmentId"))
			{
				removeDir(getAttachmentPath($attachmentId));
				
				if (!file_exists(getAttachmentPath($attachmentId)))
					return 0;
				else
					return 1;
			}
			else return 2;
		}
		else return 3;
	}
	
	// Ap duels
	function getGameByRound($roundId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$roundId = intval($roundId);
			return mysqli_result(mysqli_query($link, "SELECT game FROM rounds WHERE id = $roundId"), 0);
		}
	}
	
	// Ap images
	
	// Загрузка изображения на сервер
	function loadImage($imagePath, $imageType, $imageDescription, $gameId = -1)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$fileName 			= mysqli_real_escape_string($link, $_FILES[$imagePath]['name']);
			$imageType 			= mysqli_real_escape_string($link, $imageType);
			$imageDescription 	= mysqli_real_escape_string($link, $imageDescription);
			$gameId				= intval($gameId);
			mysqli_query($link, "INSERT INTO images SET type = '$imageType', description = '$imageDescription', game = $gameId, originalName = '$fileName'");
			$fileId = mysqli_insert_id($link);
						
			if (isset($imagePath) && $_FILES[$imagePath]["error"] == 0)
				saveImageOnDisc($fileId, $imagePath);
		}
	}
	
	
	// Вспомогательная функция загрузки
	function saveImageOnDisc($id, $source)
	{
		$path = addslashes("./img/").$id;
		saveFileOnDisc2($path, $source);
	}
	
	
	// Получение пути к изображению по id
	function getImageById($imageId)
	{
		return addslashes("./img/").$imageId;
	}
	
	// Получение информации изображения по id
	function getImageDataById($imageId)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$imageId = intval($imageId);
			$query = mysqli_query($link, "SELECT * FROM images WHERE id = $imageId");
			$data = mysqli_fetch_assoc($query);
			mysqli_free_result($query);
		}
		return $data;
	}
	
	// Получение пути изображения по описанию
	function getImageByDescription($description)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$description = mysqli_real_escape_string($link, $description);
			$query = mysqli_query($link, "SELECT id FROM images WHERE description = '$description'");
			if (mysqli_num_rows($query))
				return getImageById(mysqli_result($query, 0));
			else
				return "null";
		}
	}
	
	/*
	
	not used yet
	
	function resizedImageExist($imageId)
	{
		return file_exists(addslashes("./img/").$imageId.'r');
	}
	
	function createResizeImage($imageId)
	{
		$path = addslashes("./img/").$imageId;
		if (file_exists($path))
		{
			$imageInfo = getimagesize($path);
			$imageType = $imageInfo[2];
			$image = null;
			if ($imageType == IMAGETYPE_JPEG)
				$image = imagecreatefromjpeg($path);
			else if ($imageType == IMAGETYPE_PNG)
				$image = imagecreatefrompng($path);
				
			$newImage = imagecreatetruecolor(200, 200); // width, height
			
			imagecopyresampled($newImage, $image, 0, 0, 0, 0, 200, 200, imagesx($image), imagesy($image));
			
			if ($imageType == IMAGETYPE_JPEG)
				imagejpeg($newImage, $path.'r');
			else if($imageType == IMAGETYPE_PNG)
				imagepng($newImage, $path.'r');
		}
	}
	*/
	
	// Получение информации о изображении
	function getImageData($typeId, $gameId = -1)
	{
		$link = getDBConnection();
		$data = array();
		if (mysqli_select_db($link, getDBName()))
		{
			$typeId = intval($typeId);
			$gameId = intval($gameId);
			$queryText = "SELECT * FROM images WHERE type = $typeId";
			if ($gameId != -1)
				$queryText .= " AND game = $gameId";
				
			$query = mysqli_query($link, $queryText);
			
			while ($row = mysqli_fetch_assoc($query))
				$data[] = $row;
				
			mysqli_free_result($query);
		}
		return $data;
	}
	
	function deleteImage($imageId)
	{
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$imageId = intval($imageId);
			mysqli_query($link, "DELETE FROM images WHERE id = $imageId");
			removeDir(getImageById($imageId));
		}
	}
	
	// userProfile
	
	function changePassword($newPassword, $id="")
	{
		if ($newPassword == "" || !isActiveUser()) return 4;
		
		$link = getDBConnection();
		if (mysqli_select_db($link, getDBName()))
		{
			$newPassword = md5(md5(trim(mysqli_real_escape_string($link, $newPassword))));
			if (isAdmin() && ($id != ""))
        			$currentId = intval($id);
     			else
        			$currentId = intval(getActiveUserID());
			if (mysqli_query($link, "UPDATE users SET password = '$newPassword' WHERE id = $currentId"))
			{
				//logOff();
				LogIn(md5(generateUniqueCode(10)), getActiveUserID());
				return 0;
			}
			return 1;
		}
		else return 2;
  }
  function setUserGroup($newGroup, $id)
  {
        if (!isAdmin()) return 4;
        if ($id == getActiveUserID()) return 4;
        $link = getDBConnection();
        if (($newGroup != "user") && ($newGroup != "moder") && ($newGroup != "news") && ($newGroup != "admin") && ($newGroup != "banned"))
            return 4;
        if (mysqli_select_db($link, getDBName()))
        {
            $currentId = intval($id);
            if (mysqli_query($link, "UPDATE `users` SET `group` = '$newGroup' WHERE id = $currentId"))
                return 0;
            return 1;
        };
        return 2;
    }
  function setUserRealName($newName, $id="")
  {
        if (!isActiveUser()) return 4;
        $newName = htmlspecialchars($newName);
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            $newName = mysqli_real_escape_string($link, $newName);
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());

            if (mysqli_query($link, "UPDATE users SET name = '$newName' WHERE id = $currentId"))
                return 0;
            return 1;
        };
        return 2;
    }

    function setUserSurname($newName, $id="")
    {
        if (!isActiveUser()) return 4;
        $newName = htmlspecialchars($newName);
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            $newName = mysqli_real_escape_string($link, $newName);
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());

            if (mysqli_query($link, "UPDATE users SET surname = '$newName' WHERE id = $currentId"))
                return 0;
            return 1;
        };
        return 2;
    }

    function setUserPatronymic($newName, $id="")
    {
        if (!isActiveUser()) return 4;
        $newSurname = htmlspecialchars($newSurname);
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            $newName = mysqli_real_escape_string($link, $newName);
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());

            if (mysqli_query($link, "UPDATE users SET patronymic = '$newName' WHERE id = $currentId"))
                return 0;
            return 1;
        };
        return 2;
    }

    function getUserRealName($id = "")
    {
        if (!isActiveUser()) return "Anonymous";
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());
            $query = mysqli_query($link, "SELECT name FROM users WHERE id = $currentId");
            $res = mysqli_fetch_assoc($query);
            return $res['name'];
        }
    }
    function getUserSurname($id = "")
    {
        if (!isActiveUser()) return "Anonymous";
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());
            $query = mysqli_query($link, "SELECT surname FROM users WHERE id = $currentId");
            $res = mysqli_fetch_assoc($query);
            return $res['surname'];
        }
    }
    function getUserPatronymic($id = "")
    {
        if (!isActiveUser()) return "Anonymous";
        $link = getDBConnection();
        if (mysqli_select_db($link, getDBName()))
        {
            if (isAdmin() && ($id != ""))
                $currentId = intval($id);
            else
                $currentId = intval(getActiveUserID());

            $query = mysqli_query($link, "SELECT patronymic FROM users WHERE id = $currentId");
            $res = mysqli_fetch_assoc($query);
            return $res['patronymic'];
        }
    }

    function getUsersList($ordered = false)
    {
        $link = getDBConnection();
        mysqli_select_db($link, getDBName());
        $q = "SELECT * FROM `users` WHERE `group` != 'banned'";
        if ($ordered)
            $q .= " ORDER BY login";
        $data = array();
        if ($query = mysqli_query($link, $q))
        {
            while ($row = mysqli_fetch_assoc($query))
                $data[] = $row;
            mysqli_free_result($query);
        }
        return $data;
    }

    function getFromDB($table, $where = "1", $limit = -1)
    {
        $link = getDBConnection();
        mysqli_select_db($link, getDBName());
        if ($limit = -1)
            $limit = "";
        else
            $limit = "LIMIT $limit";
        $q = "SELECT * FROM `$table` WHERE ($where) $limit";
        $data = array();
        if ($query = mysqli_query($link, $q))
        {
            while ($row = mysqli_fetch_assoc($query))
                $data[] = $row;
            mysqli_free_result($query);
        }
        mysqli_close($link);
        return $data;
    }

    function createGameZip($gameId, $filename)
    {
        $gameId = intval($gameId);
        $z = new ZipArchive();
        $z->open($filename, ZIPARCHIVE::CREATE);
        $checkers = getCheckerListByGameId($gameId);
        $game = getFromDB("games", "id=$gameId");
        $meta = "NAME=" . getGameName($gameId) 
                        . "\nDESCRIPTION=" 
                        . getGameDescription($gameId) 
                        . "\nTL=" 
                        . $game[0]["timeLimit"] 
                        . "\nML=" 
                        . $game[0]["memoryLimit"]
        ;
        $i = 0;
        foreach ($checkers as $a)
        {
            $z->addFile(getcwd()."./testers/".$a["id"], ++$i . ".checker");
            $z->addFromString($i . ".checkermeta", "LANG=cpp\nname=" . $a["name"] . "\nSEED=" . $a["hasSeed"]);
        };
        $attachments = getAttachments($gameId);
        $i = 0;
        foreach ($attachments as $b)
        {
            $z->addFile("./attachments/" . $b["id"], ++$i . ".attachment");
            $z->addFromString($i . ".attachmentmeta", "NAME=" . $b["originalName"] . "\nDESCRIPTION=".$b["description"]);
        };
        $z->addFromString("META", $meta);
        $z->close();
    }
?>
