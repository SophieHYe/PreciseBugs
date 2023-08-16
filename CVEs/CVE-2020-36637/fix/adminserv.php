<?php
define('ADMINSERV_TIMER', false);
define('ADMINSERV_VERSION', '2.1.1');

/**
* Classe pour le fonctionnement d'AdminServ
*/
class AdminServ {
	
	/**
	* Inclue les classes PHP
	*/
	public static function getClass(){
		$pathClass = AdminServConfig::$PATH_RESOURCES.'class/';
		require_once $pathClass.'GbxRemote.inc.php';
		require_once $pathClass.'gbxdatafetcher.inc.php';
		require_once $pathClass.'utils.class.php';
		require_once $pathClass.'tmnick.class.php';
		require_once $pathClass.'timedate.class.php';
		require_once $pathClass.'file.class.php';
		require_once $pathClass.'folder.class.php';
		require_once $pathClass.'str.class.php';
		require_once $pathClass.'upload.class.php';
		require_once $pathClass.'zip.class.php';
		
		$pathCore = AdminServConfig::$PATH_RESOURCES.'core/';
		require_once $pathCore.'adminlevel.php';
		require_once $pathCore.'cache.php';
		require_once $pathCore.'event.php';
		require_once $pathCore.'logs.php';
		require_once $pathCore.'plugin.php';
		require_once $pathCore.'server.php';
		require_once $pathCore.'sort.php';
		require_once $pathCore.'ui.php';
	}
	
	
	/**
	* Méthodes de debug
	*/
	public static function dsm($val){
		echo '<pre>';
		print_r($val);
		echo '</pre>';
	}
	public static function debug($globalValue = null){
		$const = get_defined_constants(true);
		
		return self::dsm(
			array(
				'GLOBALS' => ($globalValue) ? $GLOBALS[$globalValue] : $GLOBALS,
				'ADMINSERV' => $const['user']
			)
		);
	}
	
	/**
	* Erreurs et infos
	*/
	public static function error($text = null){
		global $client;
		// Tente de récupérer le message d'erreur du dédié
		if($text === null){
			$text = '['.$client->getErrorCode().'] '.Utils::t( $client->getErrorMessage() );
		}
		else {
			$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
		}
		
		AdminServLogs::add('error', $text);
		unset($_SESSION['info']);
		$_SESSION['error'] = $text;
	}
	public static function info($text){
		$_SESSION['info'] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
	
	
	/**
	* Temps de chargement de page
	*/
	public static function startTimer(){
		global $timeStart;
		$timeStart = microtime(true);
	}
	public static function endTimer(){
		global $timeStart;
		$timeEnd = microtime(true);
		$time = $timeEnd - $timeStart;
		return number_format($time, 3);
	}
	
	
	/**
	* Vérifie la version de PHP
	*/
	public static function checkPHPVersion($version){
		if( !version_compare(PHP_VERSION, $version, '>=') ){
			echo '<b>This PHP version is not compatible with AdminServ.</b><br />Your PHP version: '. PHP_VERSION .'<br />PHP version required: '.$version;
			exit;
		}
	}
	
	
	/**
	* Vérifie les droits pour l'écriture/lecture des fichiers
	*
	* @param array $list -> Liste des fichiers à tester : array('path' => 777)
	* @return array
	*/
	public static function checkRights($list){
		if( count($list) > 0 ){
			foreach($list as $path => $minChmod){
				$result = Folder::checkRights($path, $minChmod);
				foreach($result as $grpName => $grpValues){
					foreach($grpValues['result'] as $bool){
						if(!$bool){
							self::error( Utils::t('This path was not required rights:').' '.$path.' ("'.$grpName.'" '.Utils::t('needs').' "'.$minChmod.'")');
							break;
						}
					}
				}
			}
		}
	}
	
	
	/**
	* Intialise le client du serveur courant
	*
	* @param bool $fullInit -> Intialisation complète ? oui par défaut.
	* Si non, ça ne recupère aucune info de base, seulement la connexion
	* au serveur dédié et son authentication.
	* @return bool
	*/
	public static function initialize($fullInit = true){
		global $client;
		$out = false;
		
		if( isset($_SESSION['adminserv']['sid']) ){
			// CONSTANTS
			define('USER_ADMINLEVEL', $_SESSION['adminserv']['adminlevel']);
			define('SERVER_ID', $_SESSION['adminserv']['sid']);
			define('SERVER_NAME', $_SESSION['adminserv']['name']);
			define('SERVER_ADDR', ServerConfig::$SERVERS[SERVER_NAME]['address']);
			define('SERVER_XMLRPC_PORT', ServerConfig::$SERVERS[SERVER_NAME]['port']);
			define('SERVER_MATCHSET', ServerConfig::$SERVERS[SERVER_NAME]['matchsettings']);
			define('SERVER_MAPS_BASEPATH', (isset(ServerConfig::$SERVERS[SERVER_NAME]['mapsbasepath'])) ? ServerConfig::$SERVERS[SERVER_NAME]['mapsbasepath'] : '');
			define('SERVER_ADMINLEVEL', serialize( ServerConfig::$SERVERS[SERVER_NAME]['adminlevel']) );
			
			// CONNEXION
			$client = new IXR_ClientMulticall_Gbx;
			if( !$client->InitWithIp(SERVER_ADDR, SERVER_XMLRPC_PORT, AdminServConfig::SERVER_CONNECTION_TIMEOUT) ){
				Utils::redirection(false, '?error='.urlencode( Utils::t('The server is not accessible.') ) );
			}
			else{
				if( !AdminServAdminLevel::userAllowed(USER_ADMINLEVEL) ){
					Utils::redirection(false, '?error='.urlencode( Utils::t('You are not allowed at this admin level') ) );
				}
				else{
					if( !$client->query('Authenticate', AdminServAdminLevel::getType(), $_SESSION['adminserv']['password']) ){
						Utils::redirection(false, '?error='.urlencode( Utils::t('The password doesn\'t match to the server.') ) );
					}
					else{
						if($fullInit){
							$client->addCall('SetApiVersion', array(date('Y-m-d')) );
							$client->addCall('GetVersion');
							$client->addCall('GetSystemInfo');
							$client->addCall('IsRelayServer');
							
							if( !$client->multiquery() ){
								self::error();
							}
							else{
								$queriesData = $client->getMultiqueryResponse();
								
								// Version
								$getVersion = $queriesData['GetVersion'];
								define('SERVER_VERSION_NAME', $getVersion['Name']);
								define('SERVER_VERSION', $getVersion['Version']);
								define('SERVER_BUILD', $getVersion['Build']);
								if(SERVER_VERSION_NAME == 'ManiaPlanet'){
									define('API_VERSION', $getVersion['ApiVersion']);
								}
								
								// SystemInfo
								$getSystemInfo = $queriesData['GetSystemInfo'];
								define('SERVER_LOGIN', $getSystemInfo['ServerLogin']);
								define('SERVER_PUBLISHED_IP', $getSystemInfo['PublishedIp']);
								define('SERVER_PORT', $getSystemInfo['Port']);
								define('SERVER_P2P_PORT', $getSystemInfo['P2PPort']);
								if(SERVER_VERSION_NAME == 'ManiaPlanet'){
									define('SERVER_TITLE', $getSystemInfo['TitleId']);
									define('IS_SERVER', $getSystemInfo['IsServer']);
									define('IS_DEDICATED', $getSystemInfo['IsDedicated']);
								}
								
								// Relay
								define('IS_RELAY', $queriesData['IsRelayServer']);
								
								// Protocole : tmtp ou maniaplanet
								if(SERVER_VERSION_NAME == 'ManiaPlanet'){
									TmNick::$linkProtocol = 'maniaplanet';
								}
								define('LINK_PROTOCOL', TmNick::$linkProtocol);
								
								// Mode d'affichage : detail ou simple
								if( isset($_SESSION['adminserv']['mode']['general']) ){
									define('USER_MODE_GENERAL', $_SESSION['adminserv']['mode']['general']);
								}
								else{
									define('USER_MODE_GENERAL', 'simple');
								}
								if( isset($_SESSION['adminserv']['mode']['maps']) ){
									define('USER_MODE_MAPS', $_SESSION['adminserv']['mode']['maps']);
								}
								else{
									define('USER_MODE_MAPS', 'simple');
								}
								
								// TmForever
								if(SERVER_VERSION_NAME == 'TmForever'){
									array_shift(ExtensionConfig::$GAMEMODES);
									$stuntsGameMode = array_pop(ExtensionConfig::$GAMEMODES);
									$CupGameMode = array_pop(ExtensionConfig::$GAMEMODES);
									ExtensionConfig::$GAMEMODES[4] = $stuntsGameMode;
									ExtensionConfig::$GAMEMODES[5] = $CupGameMode;
								}
								
								$out = true;
							}
						}
						else{
							$out = true;
						}
					}
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* Retourne un lien protocol TM ou ManiaPlanet
	*
	* @param string $link -> Lien : #join=server_login ou /:manialink_name
	* @return string
	*/
	public static function getProtocolLink($link) {
		$out = null;
		$protocolName = 'maniaplanet';
		if (defined('LINK_PROTOCOL')) {
			$protocolName = LINK_PROTOCOL;
		}
		$protocolSeparator = '://';
		
		if (defined('SERVER_VERSION_NAME') && SERVER_VERSION_NAME == 'ManiaPlanet' && defined('SERVER_TITLE')) {
			$out = $protocolName.$protocolSeparator.$link.'@'.SERVER_TITLE;
		}
		else {
			$out = $protocolName.$protocolSeparator.$link;
		}
		
		return $out;
	}
	
	
	/**
	* Récupère le nom du game mode
	*
	* @param int  $gameMode  -> La réponse de GetGameMode()
	* @param bool $getManual -> Forcer la récupération manuelle du nom à partir du numéro dans la config
	* @return string
	*/
	public static function getGameModeName($gameMode, $getManual = false){
		$out = Utils::t('No game mode available');
		
		if( class_exists('ExtensionConfig') && isset(ExtensionConfig::$GAMEMODES) && count(ExtensionConfig::$GAMEMODES) > 0 ){
			if($getManual && SERVER_VERSION_NAME == 'TmForever'){
				$gameMode--;
				if( isset(ExtensionConfig::$GAMEMODES[$gameMode]) ){
					$out = ExtensionConfig::$GAMEMODES[$gameMode];
				}
			}
			else{
				$out = ExtensionConfig::$GAMEMODES[$gameMode];
			}
		}
		
		return $out;
	}
	
	
	/**
	* Détermine si le nom du mode de jeu fourni en paramètre correspond au mode de jeu actuel
	*
	* @param string $gameModeName    -> Nom du mode de jeu à tester
	* @param int    $currentGameMode -> ID du mode de jeu courant. Si null, le mode de jeu courant est récupéré par le serveur
	* @return bool
	*/
	public static function isGameMode($gameModeName, $currentGameMode = null){
		global $client;
		$out = false;
		if($currentGameMode === null){
			$client->query('GetGameMode');
			$currentGameMode = $client->getResponse();
		}
		
		if($gameModeName == self::getGameModeName($currentGameMode) ){
			$out = true;
		}
		
		return $out;
	}
	
	
	/**
	* Vérifie si la configuration du serveur est compatible avec le mode équipe
	*
	* @params int    $gameMode   -> ID du mode de jeu
	* @params string $scriptName -> Nom du script si le mode de jeu est 0
	* @return bool
	*/
	public static function checkDisplayTeamMode($gameMode, $scriptName = null){
		$out = false;
		
		if($gameMode == 0 && SERVER_VERSION_NAME == 'ManiaPlanet' && class_exists('ExtensionConfig') && isset(ExtensionConfig::$TEAMSCRIPTS) && count(ExtensionConfig::$TEAMSCRIPTS) > 0 ){
			foreach(ExtensionConfig::$TEAMSCRIPTS as $teamScript){
				if( stristr($scriptName, $teamScript) ){
					$out = true;
					break;
				}
			}
		}
		else{
			if( self::isGameMode('Team', $gameMode) ){
				$out = true;
			}
		}
		
		return $out;
	}
	
	
	/**
	* Formate le nom d'un script
	*
	* @param string $scriptName -> Le nom du script retourné par le serveur
	* @return string
	*/
	public static function formatScriptName($scriptName){
		$out = str_ireplace('.script.txt', '', $scriptName);
		$scriptNameEx = explode('\\', $out);
		$out = $scriptNameEx[count($scriptNameEx)-1];
		
		return $out;
	}
	
	
	/**
	* Récupère les informations du serveur actuel (map, serveur, stats, joueurs)
	*
	* @param string $sortBy -> Le tri à faire sur la liste
	* @return array
	*/
	public static function getCurrentServerInfo($sortBy = null){
		global $client;
		$out = array();
		
		// JEU
		if(SERVER_VERSION_NAME == 'TmForever'){
			$queryName = array(
				'getMapInfo' => 'GetCurrentChallengeInfo'
			);
		}
		else{
			$queryName = array(
				'getMapInfo' => 'GetCurrentMapInfo'
			);
		}
		
		// REQUÊTES
		$client->addCall($queryName['getMapInfo']);
		if( AdminServAdminLevel::isType('Admin') ){
			$client->addCall('GetMapsDirectory');
		}
		$client->addCall('GetGameMode');
		$client->addCall('GetServerName');
		$client->addCall('GetStatus');
		$client->addCall('GetCurrentCallVote');
		if( AdminServAdminLevel::isType('SuperAdmin') ){
			$client->addCall('GetNetworkStats');
		}
		$client->addCall('GetPlayerList', array(AdminServConfig::LIMIT_PLAYERS_LIST, 0, 1) );
		
		if( !$client->multiquery() ){
			$out['error'] = Utils::t('Client not initialized');
		}
		else{
			// DONNÉES DES REQUÊTES
			$queriesData = $client->getMultiqueryResponse();
			
			// GameMode
			$out['srv']['gameModeId'] = $queriesData['GetGameMode'];
			$out['srv']['gameModeName'] = self::getGameModeName($out['srv']['gameModeId']);
			$out['srv']['gameModeScriptName'] = null;
			if( self::isGameMode('Script', $out['srv']['gameModeId']) ){
				$client->query('GetModeScriptInfo');
				$getModeScriptInfo = $client->getResponse();
				if( isset($getModeScriptInfo['Name']) ){
					$out['srv']['gameModeScriptName'] = self::formatScriptName($getModeScriptInfo['Name']);
				}
			}
			$displayTeamMode = self::checkDisplayTeamMode($out['srv']['gameModeId'], $out['srv']['gameModeScriptName']);
			
			// CurrentMapInfo
			$currentMapInfo = $queriesData[$queryName['getMapInfo']];
			$out['map']['name'] = TmNick::toHtml($currentMapInfo['Name'], 10, true, false, '#999');
			$out['map']['uid'] = $currentMapInfo['UId'];
			$out['map']['author'] = $currentMapInfo['Author'];
			$out['map']['enviro'] = $currentMapInfo['Environnement'];
			
			// MapThumbnail
			$out['map']['thumb'] = null;
			if( isset($queriesData['GetMapsDirectory']) && $currentMapInfo['FileName'] != null){
				$mapFileName = $queriesData['GetMapsDirectory'].$currentMapInfo['FileName'];
				if( file_exists($mapFileName) ){
					if(SERVER_VERSION_NAME == 'TmForever'){
						$Gbx = new GBXChallengeFetcher($queriesData['GetMapsDirectory'].$currentMapInfo['FileName'], false, true);
					}
					else{
						$Gbx = new GBXChallMapFetcher(false, true);
						$Gbx->processFile($queriesData['GetMapsDirectory'].$currentMapInfo['FileName']);
					}
					
					$out['map']['thumb'] = base64_encode($Gbx->thumbnail);
				}
			}
			
			// CurrentCallVote
			$out['map']['callvote']['login'] = $queriesData['GetCurrentCallVote']['CallerLogin'];
			$out['map']['callvote']['cmdname'] = $queriesData['GetCurrentCallVote']['CmdName'];
			$out['map']['callvote']['cmdparam'] = $queriesData['GetCurrentCallVote']['CmdParam'];
			
			// TeamScores (mode team)
			if( self::isGameMode('Team', $out['srv']['gameModeId']) ){
				$client->query('GetCurrentRanking', 2, 0);
				$currentRanking = $client->getResponse();
				$out['map']['scores']['blue'] = $currentRanking[0]['Score'];
				$out['map']['scores']['red'] = $currentRanking[1]['Score'];
			}
			
			// ServerName
			$out['srv']['name'] = TmNick::toHtml($queriesData['GetServerName'], 10, true, false, '#999');
			
			// Status
			$out['srv']['status'] = $queriesData['GetStatus']['Name'];
			
			// NetworkStats
			if( isset($queriesData['GetNetworkStats']) && count($queriesData['GetNetworkStats']) > 0 ){
				$networkStats = $queriesData['GetNetworkStats'];
				$out['net']['uptime'] = TimeDate::secToStringTime($networkStats['Uptime'], false);
				$out['net']['nbrconnection'] = $networkStats['NbrConnection'];
				$out['net']['meanconnectiontime'] = TimeDate::secToStringTime($networkStats['MeanConnectionTime'], false);
				$out['net']['meannbrplayer'] = $networkStats['MeanNbrPlayer'];
				$out['net']['recvnetrate'] = $networkStats['RecvNetRate'];
				$out['net']['sendnetrate'] = $networkStats['SendNetRate'];
				$out['net']['totalreceivingsize'] = $networkStats['TotalReceivingSize'];
				$out['net']['totalsendingsize'] = $networkStats['TotalSendingSize'];
			}
			else{
				$out['net'] = null;
			}
			
			// PlayerList
			$playerList = $queriesData['GetPlayerList'];
			$countPlayerList = count($playerList);
			
			if( $countPlayerList > 0 ){
				$client->query('GetCurrentRanking', AdminServConfig::LIMIT_PLAYERS_LIST, 0);
				$rankingList = $client->GetResponse();
				$rankingKeyList = array(
					'Rank',
					'BestTime',
					'BestCheckpoints',
					'Score',
					'NbrLapsFinished',
					'LadderScore'
				);
				$i = 0;
				foreach($playerList as $player){
					// Nickname et Playerlogin
					$out['ply'][$i]['NickName'] = TmNick::toHtml(htmlspecialchars($player['NickName'], ENT_QUOTES, 'UTF-8'), 10, true);
					$out['ply'][$i]['Login'] = $player['Login'];
					
					// PlayerStatus
					if($player['SpectatorStatus'] != 0){ $playerStatus = Utils::t('Spectator'); }else{ $playerStatus = Utils::t('Player'); }
					$out['ply'][$i]['PlayerStatus'] = $playerStatus;
					
					// Others
					$out['ply'][$i]['PlayerId'] = $player['PlayerId'];
					$out['ply'][$i]['TeamId'] = $player['TeamId'];
					if($player['TeamId'] == 0){ $teamName = Utils::t('Blue'); }else if($player['TeamId'] == 1){ $teamName = Utils::t('Red'); }else{ $teamName = Utils::t('Spectator'); }
					$out['ply'][$i]['TeamName'] = $teamName;
					$out['ply'][$i]['SpectatorStatus'] = $player['SpectatorStatus'];
					
					// Rankings
					foreach($rankingKeyList as $rankName){
						if( isset($rankingList[$i][$rankName]) ){
							$out['ply'][$i][$rankName] = $rankingList[$i][$rankName];
						}
					}
					if($player['LadderRanking'] == -1){
						$player['LadderRanking'] = Utils::t('Not rated');
					}
					$out['ply'][$i]['LadderRanking'] = $player['LadderRanking'];
					$i++;
				}
			}
			else{
				$out['ply'] = Utils::t('No player');
			}
			
			
			// Nombre de joueurs
			if($countPlayerList > 1){
				$out['nbp'] = $countPlayerList.' '.Utils::t('players');
			}
			else{
				$out['nbp'] = $countPlayerList.' '.Utils::t('player');
			}
			
			
			// TRI
			if( is_array($out['ply']) && count($out['ply']) > 0 ){
				// Si on est en mode équipe, on tri par équipe
				if($displayTeamMode){
					uasort($out['ply'], 'AdminServSort::sortByRank');
					uasort($out['ply'], 'AdminServSort::sortByTeam');
				}
				else{
					switch($sortBy){
						case 'nickname':
							uasort($out['ply'], 'AdminServSort::sortByNickName');
							break;
						case 'ladder':
							uasort($out['ply'], 'AdminServSort::sortByLadderRanking');
							break;
						case 'login':
							uasort($out['ply'], 'AdminServSort::sortByLogin');
							break;
						case 'status':
							uasort($out['ply'], 'AdminServSort::sortByStatus');
							break;
						default:
							uasort($out['ply'], 'AdminServSort::sortByRank');
							uasort($out['ply'], 'AdminServSort::sortByStatus');
					}
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* @deprecated
	* Récupère le login du serveur principal à partir d'un serveur Relai
	*
	* @return string
	*/
	public static function getMainServerLoginFromRelay(){
		global $client;
		$out = null;
		
		if( AdminServAdminLevel::isType('Admin') ){
			if( !$client->query('GameDataDirectory') ){
				self::error();
			}
			else{
				// Récupération du login
				$out = null;
			}
		}
		
		return $out;
	}
	
	
	/**
	* Récupère le nombre de joueurs présent sur le serveur
	*
	* @param bool $spectator -> Inclus les spectateurs dans le calcul
	* @return int
	*/
	public static function getNbPlayers($spectator = true){
		global $client;
		$out = 0;
		
		if( !$client->query('GetPlayerList', AdminServConfig::LIMIT_PLAYERS_LIST, 0, 1) ){
			self::error();
		}
		else{
			$playerList = $client->getResponse();
			$countPlayerList = count($playerList);
			
			if($spectator){
				$out = $countPlayerList;
			}
			else{
				if($countPlayerList > 0){
					foreach($playerList as $player){
						if($player['SpectatorStatus'] == 0){
							$out++;
						}
					}
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* Administration rapide
	*
	* @param  string $cmd -> Le nom de la commande (PrevMap, RestartMap, NextMap ou ForceEndRound)
	* @return true si réussi, sinon un message d'erreur
	*/
	public static function speedAdmin($cmd){
		global $client;
		$out = true;
		
		// Méthode en fonction du jeu
		if($cmd != 'ForceEndRound'){
			if(SERVER_VERSION_NAME == 'TmForever'){
				$queries = array(
					'restartMap' => 'RestartChallenge',
					'nextMap' => 'NextChallenge',
					'getCurrentMapIndex' => 'GetCurrentChallengeIndex',
					'setNextMapIndex' => 'SetNextChallengeIndex'
				);
			}else{
				$queries = array(
					'restartMap' => 'RestartMap',
					'nextMap' => 'NextMap',
					'getCurrentMapIndex' => 'GetCurrentMapIndex',
					'setNextMapIndex' => 'SetNextMapIndex'
				);
			}
		}
		
		// Si c'est le mode Cup
		$isCupMode = false;
		if( self::isGameMode('Cup') ){
			$isCupMode = true;
		}
		
		// Suivant la commande demandée
		switch($cmd){
			case 'PrevMap':
				if( !$client->query($queries['getCurrentMapIndex']) ){
					$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
				}
				else{
					$currentMapIndex = $client->getResponse();
					if($currentMapIndex === 0){
						$nbMaps = self::getNbMaps( self::getMapList() );
						$prevMapIndex =  $nbMaps['nbm']['count'] - 1;
					}
					else{
						$prevMapIndex = $currentMapIndex - 1;
					}
					
					if( !$client->query($queries['setNextMapIndex'], $prevMapIndex) ){
						$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
					}
					else{
						self::speedAdmin('NextMap');
					}
				}
				break;
			case 'RestartMap':
				if( !$client->query($queries['restartMap'], $isCupMode) ){
					$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
				}
				break;
			case 'NextMap':
				if( !$client->query($queries['nextMap'], $isCupMode) ){
					$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
				}
				break;
			case 'ForceEndRound':
				if($isCupMode){
					if( !$client->query($queries['nextMap']) ){
						$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
					}
				}
				else{
					if( !$client->query('ForceEndRound') ){
						$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
					}
				}
				break;
			default:
				$out = Utils::t('Unknown command');
		}
		
		return $out;
	}
	
	
	/**
	* Récupère les options du serveur
	*
	* @global resource $client -> Le client doit être initialisé
	* @return array
	*/
	public static function getServerOptions(){
		global $client;
		$out = array();
		
		if(SERVER_VERSION_NAME == 'TmForever'){
			$client->addCall('GetServerOptions', array(1) );
		}
		else{
			$client->addCall('GetServerOptions');
		}
		$client->addCall('GetBuddyNotification', array('') );
		if(SERVER_VERSION_NAME == 'ManiaPlanet'){
			$client->addCall('AreHornsDisabled');
		}
		
		if( !$client->multiquery() ){
			self::error();
		}
		else{
			$queriesData = $client->getMultiqueryResponse();
			$out = $queriesData['GetServerOptions'];
			$out['Name'] = stripslashes($out['Name']);
			if($out['Name'] == null){
				$out['Name'] = SERVER_NAME;
			}
			$out['NameHtml'] = TmNick::toHtml($out['Name'], 10, false, false, '#666');
			$out['Comment'] = stripslashes($out['Comment']);
			$out['CommentHtml'] = TmNick::toHtml('$i'.nl2br($out['Comment']), 10, false, false, '#666');
			if($out['CurrentLadderMode'] !== 0){
				$out['CurrentLadderModeName'] = Utils::t('Forced'); 
			}
			else{
				$out['CurrentLadderModeName'] = Utils::t('Inactive');
			}
			if($out['CurrentVehicleNetQuality'] !== 0){
				$out['CurrentVehicleNetQualityName'] = Utils::t('High');
			}
			else{
				$out['CurrentVehicleNetQualityName'] = Utils::t('Fast');
			}
			$out['BuddyNotification'] = $queriesData['GetBuddyNotification'];
			if(SERVER_VERSION_NAME == 'ManiaPlanet'){
				$out['DisableHorns'] = $queriesData['AreHornsDisabled'];
			}
			else{
				$out['DisableHorns'] = null;
			}
		}
		
		return $out;
	}
	
	
	/**
	* Retourne la structure pour l'enregistrement des options du serveur
	*
	* @return array
	*/
	public static function getServerOptionsStruct(){
		if(SERVER_VERSION_NAME == 'TmForever'){
			$keys = array(
				'allowMapDownload' => 'AllowChallengeDownload'
			);
		}
		else{
			$keys = array(
				'allowMapDownload' => 'AllowMapDownload'
			);
		}
		
		$out = array(
			'Name' => stripslashes($_POST['Name']),
			'Comment' => stripslashes($_POST['Comment']),
			'Password' => trim($_POST['Password']),
			'PasswordForSpectator' => trim($_POST['PasswordForSpectator']),
			'NextMaxPlayers' => intval($_POST['NextMaxPlayers']),
			'NextMaxSpectators' => intval($_POST['NextMaxSpectators']),
			'IsP2PUpload' => array_key_exists('IsP2PUpload', $_POST),
			'IsP2PDownload' => array_key_exists('IsP2PDownload', $_POST),
			'NextLadderMode' => intval($_POST['NextLadderMode']),
			'NextVehicleNetQuality' => intval($_POST['NextVehicleNetQuality']),
			'NextCallVoteTimeOut' => TimeDate::secToMillisec( intval($_POST['NextCallVoteTimeOut']) ),
			'CallVoteRatio' => (double)$_POST['CallVoteRatio'],
			$keys['allowMapDownload'] => array_key_exists('AllowMapDownload', $_POST),
			'AutoSaveReplays' => array_key_exists('AutoSaveReplays', $_POST),
			'HideServer' => (int)array_key_exists('HideServer', $_POST),
			'BuddyNotification' => array_key_exists('BuddyNotification', $_POST),
			
		);
		if(SERVER_VERSION_NAME == 'ManiaPlanet'){
			$out['ClientInputsMaxLatency'] = ($_POST['ClientInputsMaxLatency'] == 'more') ? $_POST['ClientInputsMaxLatencyValue'] : $_POST['ClientInputsMaxLatency'];
			$out['DisableHorns'] = array_key_exists('DisableHorns', $_POST);
		}
		
		return $out;
	}
	
	
	/**
	* Enregistre les options du serveur
	*
	* @param array $struct -> Structure contenant les champs demandés par la méthode SetServerOptions()
	*/
	public static function setServerOptions($struct){
		global $client;
		$out = false;
		
		if( !$client->query('SetServerOptions', $struct) ){
			self::error();
		}
		else{
			$client->addCall('SetHideServer', array($struct['HideServer']) );
			$client->addCall('SetBuddyNotification', array('', $struct['BuddyNotification']) );
			if(SERVER_VERSION_NAME == 'ManiaPlanet'){
				$client->addCall('SetClientInputsMaxLatency', array($struct['ClientInputsMaxLatency']) );
				$client->addCall('DisableHorns', array($struct['DisableHorns']) );
			}
			
			if( !$client->multiquery() ){
				self::error();
			}
			else{
				$out = true;
			}
		}
		
		return $out;
	}
	
	
	/**
	* Exporte les options du serveur dans un fichier
	*
	* @param  string $file -> Chemin du fichier à écrire
	* @param  array  $data -> Structure des options du serveur à écrire
	* @return bool
	*/
	public static function exportServerOptions($file, $data){
		$out = false;
		
		$xml = new DOMDocument('1.0', 'utf-8');
		$xml->formatOutput = true;
		$srvopts = $xml->createElement('ServerOptions');
		$srvopts = $xml->appendChild($srvopts);
		foreach($data as $dataField => $dataValue){
			$srvoptsElement = $xml->createElement($dataField, $dataValue);
			$srvoptsElementAttribute = $xml->createAttribute('type');
			$srvoptsElementAttribute->value = Str::getValueType($dataValue);
			$srvoptsElement->appendChild($srvoptsElementAttribute);
			$srvoptsElement = $srvopts->appendChild($srvoptsElement);
		}
		
		if( $result = $xml->save($file) > 0 ){
			$out = true;
			self::info( Utils::t('Server options are exported in').' '.$file);
		}
		else{
			self::error( Utils::t('Unable to export server options') );
		}
		
		return $out;
	}
	
	
	/**
	* Importe les options du serveur depuis un fichier
	*
	* @param  string $file -> Chemin du fichier à lire
	* @return array
	*/
	public static function importServerOptions($file){
		global $client;
		$out = array();
		
		if( file_exists($file) ){
			$dom = new DOMDocument();
			$dom->load($file);
			$srvopts = $dom->childNodes->item(0);
			
			for($i = 0; $i < $srvopts->childNodes->length; $i++){
				$srvoptsElement = $srvopts->childNodes->item($i);
				if($srvoptsElement->nodeName != '#text'){
					$out[$srvoptsElement->nodeName] = Str::setValueType($srvoptsElement->nodeValue, $srvoptsElement->getAttribute('type') );
				}
			}
		}
		else{
			self::error( Utils::t('No such file or file is not readable').' : '.$file);
		}
		
		return $out;
	}
	
	
	/**
	* Récupère les informations de jeux
	*
	* @global resource $client -> Le client doit être initialisé
	* @return array
	*/
	public static function getGameInfos(){
		global $client;
		$out = array();
		
		// Jeu
		if(SERVER_VERSION_NAME == 'TmForever'){
			$queries = array(
				'CupRoundsPerMap' => 'GetCupRoundsPerChallenge',
			);
		}
		else{
			$queries = array(
				'CupRoundsPerMap' => 'GetCupRoundsPerMap',
			);
		}
		
		// Requêtes
		$client->addCall('GetGameInfos');
		$client->addCall('GetAllWarmUpDuration');
		$client->addCall('GetDisableRespawn');
		$client->addCall('GetForceShowAllOpponents');
		if(SERVER_VERSION_NAME == 'ManiaPlanet'){
			$client->addCall('GetScriptName');
		}
		$client->addCall('GetCupPointsLimit');
		$client->addCall($queries['CupRoundsPerMap']);
		$client->addCall('GetCupNbWinners');
		$client->addCall('GetCupWarmUpDuration');
		$client->addCall('GetRoundCustomPoints');
		
		if( !$client->multiquery() ){
			self::error();
		}
		else{
			$queriesData = $client->getMultiqueryResponse();
			
			// Game infos
			$currGamInf = $queriesData['GetGameInfos']['CurrentGameInfos'];
			$nextGamInf = $queriesData['GetGameInfos']['NextGameInfos'];
			
			// Nb de WarmUp
			$currGamInf['AllWarmUpDuration'] = $queriesData['GetAllWarmUpDuration']['CurrentValue'];
			$nextGamInf['AllWarmUpDuration'] = $queriesData['GetAllWarmUpDuration']['NextValue'];
			
			// Respawn
			$currGamInf['DisableRespawn'] = $queriesData['GetDisableRespawn']['CurrentValue'];
			$nextGamInf['DisableRespawn'] = $queriesData['GetDisableRespawn']['NextValue'];
			
			// ForceShowAllOpponents
			$currGamInf['ForceShowAllOpponents'] = $queriesData['GetForceShowAllOpponents']['CurrentValue'];
			$nextGamInf['ForceShowAllOpponents'] = $queriesData['GetForceShowAllOpponents']['NextValue'];
			
			// ScriptName
			$currGamInf['ScriptName'] = null;
			$nextGamInf['ScriptName'] = null;
			if(SERVER_VERSION_NAME == 'ManiaPlanet'){
				$currGamInf['ScriptName'] = $queriesData['GetScriptName']['CurrentValue'];
				$nextGamInf['ScriptName'] = $queriesData['GetScriptName']['NextValue'];
			}
			
			// Mode Cup
			$currGamInf['CupPointsLimit'] = $queriesData['GetCupPointsLimit']['CurrentValue'];
			$nextGamInf['CupPointsLimit'] = $queriesData['GetCupPointsLimit']['NextValue'];
			$currGamInf['CupRoundsPerMap'] = $queriesData[$queries['CupRoundsPerMap']]['CurrentValue'];
			$nextGamInf['CupRoundsPerMap'] = $queriesData[$queries['CupRoundsPerMap']]['NextValue'];
			$currGamInf['CupNbWinners'] = $queriesData['GetCupNbWinners']['CurrentValue'];
			$nextGamInf['CupNbWinners'] = $queriesData['GetCupNbWinners']['NextValue'];
			$currGamInf['CupWarmUpDuration'] = $queriesData['GetCupWarmUpDuration']['CurrentValue'];
			$nextGamInf['CupWarmUpDuration'] = $queriesData['GetCupWarmUpDuration']['NextValue'];
			
			// RoundCustomPoints
			$RoundCustomPoints = implode(',', $queriesData['GetRoundCustomPoints']);
			$currGamInf['RoundCustomPoints'] = $RoundCustomPoints;
			$nextGamInf['RoundCustomPoints'] = $RoundCustomPoints;
			
			// Retour
			$out['curr'] = $currGamInf;
			$out['next'] = $nextGamInf;
		}
		
		return $out;
	}
	
	
	/**
	* Retourne la structure pour l'enregistrement des informations de jeu
	*
	* @return array
	*/
	public static function getGameInfosStructFromPOST(){
		if($_POST['NextFinishTimeoutValue'] < 2){
			if($_POST['NextFinishTimeout'] == 0){ $FinishTimeout = 0; }
			else if($_POST['NextFinishTimeout'] == 1){ $FinishTimeout = 1; }
		}
		else{ $FinishTimeout = TimeDate::secToMillisec( intval($_POST['NextFinishTimeoutValue']) ); }
		if( array_key_exists('NextDisableRespawn', $_POST) === true ){ $DisableRespawn = false; }
		else{ $DisableRespawn = true; }
		if($_POST['NextForceShowAllOpponentsValue'] < 2){
			if($_POST['NextForceShowAllOpponents'] == 0){ $NextForceShowAllOpponents = 0; }
			else if($_POST['NextForceShowAllOpponents'] == 1){ $NextForceShowAllOpponents = 1; }
		}
		else{ $NextForceShowAllOpponents = intval($_POST['NextForceShowAllOpponentsValue']); }
		
		$out = array(
			'GameMode' => intval($_POST['NextGameMode']),
			'ChatTime' => TimeDate::secToMillisec( intval($_POST['NextChatTime'] - 8) ),
			'RoundsPointsLimit' => intval($_POST['NextRoundsPointsLimit']),
			'RoundCustomPoints' => intval($_POST['NextRoundCustomPoints']),
			'RoundsUseNewRules' => array_key_exists('NextRoundsUseNewRules', $_POST),
			'RoundsForcedLaps' => intval($_POST['NextRoundsForcedLaps']),
			'RoundsPointsLimitNewRules' => intval($_POST['NextRoundsPointsLimit']),
			'TimeAttackLimit' => TimeDate::secToMillisec( intval($_POST['NextTimeAttackLimit']) ),
			'TimeAttackSynchStartPeriod' => TimeDate::secToMillisec( intval($_POST['NextTimeAttackSynchStartPeriod']) ),
			'TeamPointsLimit' => intval($_POST['NextTeamPointsLimit']),
			'TeamMaxPoints' => intval($_POST['NextTeamMaxPoints']),
			'TeamUseNewRules' => array_key_exists('NextTeamUseNewRules', $_POST),
			'TeamPointsLimitNewRules' => intval($_POST['NextTeamPointsLimit']),
			'LapsNbLaps' => intval($_POST['NextLapsNbLaps']),
			'LapsTimeLimit' => TimeDate::secToMillisec( intval($_POST['NextLapsTimeLimit']) ),
			'FinishTimeout' => $FinishTimeout,
			'AllWarmUpDuration' => intval($_POST['NextAllWarmUpDuration']),
			'DisableRespawn' => $DisableRespawn,
			'ForceShowAllOpponents' => $NextForceShowAllOpponents,
			'CupPointsLimit' => intval($_POST['NextCupPointsLimit']),
			'CupRoundsPerMap' => intval($_POST['NextCupRoundsPerMap']),
			'CupNbWinners' => intval($_POST['NextCupNbWinners']),
			'CupWarmUpDuration' => intval($_POST['NextCupWarmUpDuration'])
		);
		if(SERVER_VERSION_NAME != 'TmForever'){
			$out += array('ScriptName' => $_POST['NextScriptName']);
		}
		
		return $out;
	}
	
	
	/**
	* Enregistre les infos sur les équipes
	* @param array $team1 -> (assoc) array(name, color (0 to 1), country)
	* @param array $team2
	* @return bool
	*/
	public static function setTeamInfo($team1, $team2){
		global $client;
		$out = false;
		
		if( !$client->query('SetTeamInfo', 'Unused', 0., 'World', $team1['name'], (double)$team1['color'], $team1['country'], $team2['name'], (double)$team2['color'], $team2['country']) ){
			AdminServ::error();
		}
		else{
			$_SESSION['adminserv']['teaminfo'] = array(
				'team1' => $team1,
				'team2' => $team2
			);
			$out = true;
		}
		
		return $out;
	}
	
	
	/**
	* Add chat line on server
	*
	* @param string $message       -> Text message
	* @param string $nickname      -> Nickname
	* @param string $color         -> Text color
	* @param string $destination   -> Message destination: server or player login
	* @param string $showAdminText -> Display "Admin" before the message
	* @return bool or text error
	*/
	public static function addChatServerLine($message, $nickname = null, $color = '$ff0', $destination = 'server', $showAdminText = false){
		global $client;
		$out = false;
		$admin = null;
		Utils::addCookieData('adminserv_user', array(AdminServUI::theme(), AdminServUI::lang(), $nickname, $color), AdminServConfig::COOKIE_EXPIRE);
		
		if($showAdminText){
			$admin = '$fffAdmin:';
		}
		
		if($nickname){
			$nickname = '$g$ff0'.TmNick::stripNadeoCode($nickname, array('$s') );
		}
		
		$nickname = '$s$ff0['.$admin.$nickname.'$z$s$ff0]$z';
		$message = $nickname.' '.$color.$message;
		$_SESSION['adminserv']['chat_dst'] = $destination;
		
		if($destination === 'server'){
			if( !$client->query('ChatSendServerMessage', $message) ){
				$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
			}
			else{
				$out = true;
			}
		}
		else{
			if( !$client->query('ChatSendServerMessageToLogin', $message, $destination) ){
				$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
			}
			else{
				$out = true;
			}
		}
		
		return $out;
	}
	
	
	/**
	* Récupère les lignes du chat serveur
	*
	* @param bool $hideServerLines -> Masquer les lignes provenant d'un gestionnaire de serveur
	* @return string
	*/
	public static function getChatServerLines($hideServerLines = false){
		global $client;
		$out = null;
		
		if( !$client->query('GetChatLines') ){
			$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
		}
		else{
			$langCode = AdminServUI::lang();
			$chatLines = $client->getResponse();
			
			foreach($chatLines as $line){
				if( self::isServerLine($line) ){
					if($hideServerLines){
						unset($line);
					}
					else{
						$tradLines = array(
							'$99FThis round is a draw.',
							'$99FThe $<$00FBlue team$> wins this round.',
							'$99FThe $<$F00Red team$> wins this round.'
						);
						if( in_array($line, $tradLines) ){
							foreach($tradLines as $tradLine){
								if($line == $tradLine){
									if($langCode == 'en'){
										$line = '$999'.TmNick::toText( TmNick::stripNadeoCode($tradLine, array('$<', '$>')) );
									}
									else{
										$line = '$999'.TmNick::toText( Utils::t($tradLine) );
									}
									break;
								}
							}
						}
						else{
							if( strstr($line, '$fffAdmin:') ){
								$pattern = '$ff0]$z';
								$lineEx = explode($pattern, $line);
								$nickname = $lineEx[0].$pattern;
								$message = TmNick::toText( trim($lineEx[1]) );
								$line = $nickname.' $666'.$message;
							}
							else{
								$line = '$999'.TmNick::toText($line);
							}
						}
					}
				}
				else{
					$lineEx = explode('$>', $line);
					$nickname = TmNick::stripNadeoCode($lineEx[0], array('$s', '[$<') );
					$message = TmNick::toText( substr($lineEx[1], 2) );
					
					$line = '$s$ff0['.$nickname.'$g$ff0]$z $666'.$message;
				}
				
				if( isset($line) ){
					$out .= TmNick::toHtml($line, 10);
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* Retourne true si la ligne est générée par le serveur
	*
	* @param string $line -> La ligne de la réponse GetChatLines
	* @return bool
	*/
	public static function isServerLine($line){
		$out = false;
		$char = substr(utf8_decode($line), 0, 1);
		
		if($char == '<' || $char == '/' || substr($line, 0, 4) == '$99F' || substr($line, 0, 12) == 'Invalid time' || $char == '?'){
			$out = true;
		}
		
		return $out;
	}
	
	
	/**
	* Récupère le chemin du dossier "Maps"
	*
	* @global resource $client -> Le client doit être initialisé
	* @return string
	*/
	public static function getMapsDirectoryPath(){
		global $client;
		$out = null;
		
		if(SERVER_VERSION_NAME == 'TmForever'){
			$queryName = 'GetTracksDirectory';
		}
		else{
			$queryName = 'GetMapsDirectory';
		}
		
		if( !$client->query($queryName) ){
			$out = '['.$client->getErrorCode().'] '.$client->getErrorMessage();
		}
		else{
			$out = Str::toSlash( $client->getResponse() );
			if( substr($out, -1, 1) != '/'){ $out .= '/'; }
			if(SERVER_MAPS_BASEPATH){
				$out .= Str::toSlash(SERVER_MAPS_BASEPATH);
				if( substr($out, -1, 1) != '/'){ $out .= '/'; }
			}
		}
		
		return $out;
	}
	
	
	/**
	* Retourne un tableau avec le nombre de maps et l'intitulé
	*
	* @param array $array -> La tableau contenant la liste des maps
	* @return array
	*/
	public static function getNbMaps($array){
		$out = array();
		
		if( isset($array['lst']) && is_array($array['lst']) ){
			$countMapsList = count($array['lst']);
		}
		else{
			$countMapsList = 0;
		}
		
		$out['nbm']['count'] = $countMapsList;
		if($countMapsList > 1){
			$out['nbm']['title'] = Utils::t('maps');
		}
		else{
			$out['nbm']['title'] = Utils::t('map');
		}
		
		return $out;
	}
	
	
	/**
	* Récupère la liste des maps sur le serveur
	*
	* @global resource $client -> Le client doit être initialisé
	* @param  string   $sortBy -> Le tri à faire sur la liste
	* @return array
	*/
	public static function getMapList($sortBy = null){
		global $client;
		$out = array();
		
		// Méthodes
		if(SERVER_VERSION_NAME == 'TmForever'){
			$queryName = array(
				'mapList' => 'GetChallengeList',
				'mapIndex' => 'GetCurrentChallengeIndex'
			);
		}
		else{
			$queryName = array(
				'mapList' => 'GetMapList',
				'mapIndex' => 'GetCurrentMapIndex'
			);
		}
		
		// MAPSLIST
		if( !$client->query($queryName['mapList'], AdminServConfig::LIMIT_MAPS_LIST, 0) ){
			$out['error'] = Utils::t('Client not initialized');
		}
		else{
			$mapList = $client->getResponse();
			$countMapList = count($mapList);
			$client->query($queryName['mapIndex']);
			$out['cid'] = $client->getResponse();
			
			if( $countMapList > 0 ){
				$i = 0;
				foreach($mapList as $map){
					// Name
					$name = htmlspecialchars($map['Name'], ENT_QUOTES, 'UTF-8');
					$out['lst'][$i]['Name'] = TmNick::toHtml($name, 10, true);
					
					// Environnement
					$env = $map['Environnement'];
					if($env == 'Speed'){ $env = 'Desert'; }else if($env == 'Alpine'){ $env = 'Snow'; }
					$out['lst'][$i]['Environment'] = $env;
					
					// Autres
					$out['lst'][$i]['UId'] = $map['UId'];
					$out['lst'][$i]['FileName'] = $map['FileName'];
					$out['lst'][$i]['Author'] = $map['Author'];
					$out['lst'][$i]['GoldTime'] = TimeDate::format($map['GoldTime']);
					$out['lst'][$i]['CopperPrice'] = $map['CopperPrice'];
					if(SERVER_VERSION_NAME == 'ManiaPlanet'){
						$out['lst'][$i]['Type']['Name'] = self::formatScriptName($map['MapType']);
						$out['lst'][$i]['Type']['FullName'] = $map['MapType'];
						$out['lst'][$i]['Style']['Name'] = self::formatScriptName($map['MapStyle']);
						$out['lst'][$i]['Style']['FullName'] = $map['MapStyle'];
					}
					$i++;
				}
			}
			
			// Nombre de maps
			$out += self::getNbMaps($out);
			if($out['nbm']['count'] == 0){
				$out['lst'] = Utils::t('No map');
			}
			
			
			// TRI
			if($sortBy != null){
				if( is_array($out['lst']) && count($out['lst']) > 0 ){
					switch($sortBy){
						case 'name':
							uasort($out['lst'], 'AdminServSort::sortByName');
							break;
						case 'env':
							uasort($out['lst'], 'AdminServSort::sortByEnviro');
							break;
						case 'author':
							uasort($out['lst'], 'AdminServSort::sortByAuthor');
							break;
						case 'goldtime':
							uasort($out['lst'], 'AdminServSort::sortByGoldTime');
							break;
						case 'cost':
							uasort($out['lst'], 'AdminServSort::sortByPrice');
							break;
					}
				}
				$out['lst'] = array_values($out['lst']);
			}
		}
		
		return $out;
	}
	
	
	/**
	* Récupère la liste des maps sur le serveur et retourne un champ en particulier
	*
	* @global resource $client -> Le client doit être initialisé
	* @return array
	*/
	public static function getMapListField($field){
		global $client;
		$out = array();
		
		// Méthodes
		if(SERVER_VERSION_NAME == 'TmForever'){
			$queryName = array(
				'mapList' => 'GetChallengeList',
			);
		}
		else{
			$queryName = array(
				'mapList' => 'GetMapList',
			);
		}
		
		// Mapslist
		if( !$client->query($queryName['mapList'], AdminServConfig::LIMIT_MAPS_LIST, 0) ){
			$out['error'] = Utils::t('Client not initialized');
		}
		else{
			$mapList = $client->getResponse();
			$countMapList = count($mapList);
			if( $countMapList > 0 ){
				$i = 0;
				foreach($mapList as $map){
					switch($field){
						case 'Name':
							$name = htmlspecialchars($map['Name'], ENT_QUOTES, 'UTF-8');
							$out[] = TmNick::toHtml($name, 10, true);
							break;
						case 'Environment':
							$env = $map['Environnement'];
							if($env == 'Speed'){ $env = 'Desert'; }else if($env == 'Alpine'){ $env = 'Snow'; }
							$out[] = $env;
							break;
						case 'UId':
							$out[] = $map['UId'];
							break;
						case 'FileName':
							$out[] = $map['FileName'];
							break;
						case 'Author':
							$out[] = $map['Author'];
							break;
						case 'GoldTime':
							$out[] = TimeDate::format($map['GoldTime']);
							break;
						case 'CopperPrice':
							$out[] = $map['CopperPrice'];
							break;
						case 'MapType':
							$out[] = $map['MapType'];
							break;
						case 'MapStyle':
							$out[] = $map['MapStyle'];
							break;
					}
					$i++;
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* Récupère la liste des maps en local à partir d'un chemin
	*
	* @param string $path   -> Le chemin du dossier à lister
	* @param string $sortBy -> Le tri à faire sur la liste
	* @return array
	*/
	public static function getLocalMapList($directory, $currentPath, $sortBy = null){
		global $client;
		$out = array();
		
		if( is_array($directory) ){
			if( !empty($directory['files']) ){
				// Récupération du cache existant
				$mapsDirectoryPath = self::getMapsDirectoryPath();
				$cache = new AdminServCache();
				$cacheKey = 'mapslist-'.Str::replaceChars($mapsDirectoryPath.$currentPath);
				$cacheMaps = $cache->get($cacheKey);
				
				// Fichiers
				$files = array();
				foreach($directory['files'] as $fileName => $fileValues){
					$dbExt = File::getDoubleExtension($fileName);
					if( in_array($dbExt, AdminServConfig::$MAP_EXTENSION) ){
						$files[$fileName] = $fileValues;
					}
				}
				
				// Suppression du cache en trop
				$cacheOverFiles = array_diff_key($cacheMaps, $files);
				if( !empty($cacheOverFiles) ){
					foreach($cacheOverFiles as $fileName => $fileValues){
						if( isset($cacheMaps[$fileName]) ){
							unset($cacheMaps[$fileName]);
						}
					}
					$cache->set($cacheKey, $cacheMaps);
				}
				
				// Ajout des fichiers manquant dans le cache
				$cacheMissingFiles = array_diff_key($files, $cacheMaps);
				if( !empty($cacheMissingFiles) ){
					// Path
					$path = $mapsDirectoryPath.$currentPath;
					
					// Création du cache
					foreach($cacheMissingFiles as $file => $values){
						// Données
						$processFile = utf8_decode($file);
						if(SERVER_VERSION_NAME == 'TmForever'){
							$Gbx = new GBXChallengeFetcher($path.$processFile);
						}
						else{
							$Gbx = new GBXChallMapFetcher();
							$Gbx->processFile($path.$processFile);
						}
						
						// Name
						$filename = $Gbx->name;
						if($filename == 'read error'){
							$filename = str_ireplace('.'.$dbExt, '', $file);
						}
						$name = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
						$out['lst'][$file]['Name'] = TmNick::toHtml($name, 10, true);
						
						// Environnement
						$env = $Gbx->envir;
						if($env == 'read error'){ $env = null; }
						if($env == 'Speed'){ $env = 'Desert'; }else if($env == 'Alpine'){ $env = 'Snow'; }
						$out['lst'][$file]['Environment'] = $env;
						
						// Autres
						$out['lst'][$file]['FileName'] = $currentPath.$file;
						$uid = $Gbx->uid;
						if($uid == 'read error'){ $uid = null; }
						$out['lst'][$file]['UId'] = $uid;
						$author = $Gbx->author;
						if($author == 'read error'){ $author = null; }
						$out['lst'][$file]['Author'] = $author;
						$out['lst'][$file]['Recent'] = $values['recent'];
						
						// MapType
						$mapType = $Gbx->mapType;
						if($mapType == null && $Gbx->typeName != null){
							$mapType = $Gbx->typeName;
						}
						$out['lst'][$file]['Type']['Name'] = self::formatScriptName($mapType);
						$out['lst'][$file]['Type']['FullName'] = $mapType;
					}
					
					// Mise à jour du cache
					if( !empty($cacheMaps) ){
						$out['lst'] = array_merge($cacheMaps, $out['lst']);
					}
					$cache->set($cacheKey, $out['lst']);
				}
				else{
					$out['lst'] = $cacheMaps;
				}
				
				// Maps on server?
				$currentMapsListUId = null;
				if(AdminServConfig::LOCAL_GET_MAPS_ON_SERVER){
					$currentMapsListUId = self::getMapListField('UId');
				}
				foreach($out['lst'] as &$file){
					$file['OnServer'] = false;
					if($currentMapsListUId){
						if( in_array($file['UId'], $currentMapsListUId) ){
							$file['OnServer'] = true;
						}
					}
				}
				
				// Nombre de maps
				$out += self::getNbMaps($out);
				if($out['nbm']['count'] == 0){
					$out['lst'] = Utils::t('No map');
				}
				else{
					// TRIS
					if($sortBy != null){
						switch($sortBy){
							case 'filename':
								uasort($out['lst'], 'AdminServSort::sortByFileName');
								break;
							case 'name':
								uasort($out['lst'], 'AdminServSort::sortByName');
								break;
							case 'env':
								uasort($out['lst'], 'AdminServSort::sortByEnviro');
								break;
							case 'type':
								uasort($out['lst'], 'AdminServSort::sortByType');
								break;
							case 'author':
								uasort($out['lst'], 'AdminServSort::sortByAuthor');
								break;
						}
					}
				}
			}
			else{
				$out += self::getNbMaps($out);
				$out['lst'] = Utils::t('No map');
			}
		}
		// Retour des erreurs de la méthode read
		else{
			$out = $directory;
		}
		
		return $out;
	}
	
	
	/**
	* Récupère la liste des matchsettings en local à partir d'un chemin
	*
	* @param string $path -> Le chemin du dossier à lister
	* @return array
	*/
	public static function getLocalMatchSettingList($directory, $currentPath){
		$out = array();
		
		if( is_array($directory) ){
			if( !empty($directory['files']) ){
				$mapsDirectoryPath = self::getMapsDirectoryPath();
				
				foreach($directory['files'] as $file => $values){
					if( in_array(File::getExtension($file), AdminServConfig::$MATCHSET_EXTENSION) ){
						$matchsetData = self::getMatchSettingsData($mapsDirectoryPath.$currentPath.$file, array('maps'));
						$matchsetNbmCount = 0;
						if( isset($matchsetData['maps']) ){
							$matchsetNbmCount = count($matchsetData['maps']);
						}
						$matchsetNbmTitle = ($matchsetNbmCount > 1) ? Utils::t('maps') : Utils::t('map');
						
						$out['lst'][$file]['Name'] = substr($file, 0, -4);
						$out['lst'][$file]['FileName'] = $currentPath.$file;
						$out['lst'][$file]['Nbm'] = $matchsetNbmCount.' '.$matchsetNbmTitle;
						$out['lst'][$file]['Mtime'] = $values['mtime'];
						$out['lst'][$file]['Recent'] = $values['recent'];
					}
				}
			}
			
			// Nombre de matchsettings
			if( isset($out['lst']) && is_array($out['lst']) ){
				$out['nbm']['count'] = count($out['lst']);
				$out['nbm']['title'] = ($out['nbm']['count'] > 1) ? Utils::t('matchsettings') : Utils::t('matchsetting');
			}
			else{
				$out['nbm']['count'] = 0;
				$out['nbm']['title'] = Utils::t('matchsetting');
			}
			if($out['nbm']['count'] == 0){
				$out['lst'] = Utils::t('No matchsetting');
			}
		}
		else{
			// Retour des erreurs de la méthode read
			$out = $directory;
		}
		
		return $out;
	}
	
	
	/**
	* Enregistre la sélection du MatchSettings en session
	*
	* @param array $maps -> Le tableau de maps à ajouter à la sélection
	*/
	public static function saveMatchSettingSelection($maps = array() ){
		// Liste des maps
		$out = array();
		if( isset($_SESSION['adminserv']['matchset_maps_selected']) ){
			$mapsSelected = $_SESSION['adminserv']['matchset_maps_selected'];
			if( isset($mapsSelected['lst']) && is_array($mapsSelected['lst']) && count($mapsSelected['lst']) > 0 ){
				foreach($mapsSelected['lst'] as $id => $values){
					$out['lst'][] = $values;
				}
			}
		}
		if( isset($maps['lst']) && is_array($maps['lst']) && count($maps['lst']) > 0 ){
			foreach($maps['lst'] as $id => $values){
				$out['lst'][] = $values;
			}
		}
		if( isset($out['lst']) && count($out['lst']) > 0 ){
			$out['lst'] = array_unique($out['lst'], SORT_REGULAR);
		}
		
		// Nombre de maps
		$out += self::getNbMaps($out);
		if($out['nbm']['count'] == 0){
			$out['lst'] = Utils::t('No map');
		}
		
		// Mise à jour de la session
		$_SESSION['adminserv']['matchset_maps_selected'] = $out;
	}
	
	
	/**
	* Enregistre un MatchSettings
	*
	* @param string $filename -> L'url du dossier dans lequel le MatchSettings sera crée
	* @param array  $struct   -> La structure du MatchSettings avec ses données
	* $struct = Array
	* (
	*  [gameinfos] => Array
	*   (
	*    [game_mode] => 0
	*    etc...
	*   )
	*  [hotseat] => Array()
	*  [filter] => Array()
	*  [startindex] => 1
	*  [map] => Array
	*   (
	*    [8bDoQMwzUllV0D9eu7hSth3rQs6] => name.Map.Gbx
	*    etc...
	*   )
	* )
	* @return true si réussi, sinon une erreur
	*/
	public static function saveMatchSettings($filename, $struct){
		$out = false;
		
		if(SERVER_VERSION_NAME == 'TmForever'){
			$mapField = 'challenge';
		}
		else{
			$mapField = 'map';
		}
		
		$xml = new DOMDocument('1.0', 'utf-8');
		$xml->formatOutput = true;
		$playlist = $xml->createElement('playlist');
		$playlist = $xml->appendChild($playlist);
		
		// GameInfos, Hotseat, Filter
		$structFields = array(
			'gameinfos',
			'hotseat',
			'filter'
		);
		foreach($structFields as $strucField){
			if( isset($struct[$strucField]) && !empty($struct[$strucField]) ){
				$node = $xml->createElement($strucField);
				$node = $playlist->appendChild($node);
				foreach($struct[$strucField] as $field => $value){
					$childNode = $xml->createElement($field, $value);
					$childNode = $node->appendChild($childNode);
				}
			}
		}
		
		// Script settings
		if( isset($struct['scriptsettings']) && !empty($struct['scriptsettings']) ){
			$scriptsettings = $xml->createElement('mode_script_settings');
			$scriptsettings = $playlist->appendChild($scriptsettings);
			foreach($struct['scriptsettings'] as $settingParams){
				$setting = $xml->createElement('setting');
				$setting = $scriptsettings->appendChild($setting);
				if( !empty($settingParams) ){
					foreach($settingParams as $paramKey => $paramValue){
						$param = $xml->createAttribute($paramKey);
						$param->value = $paramValue;
						$setting->appendChild($param);
					}
				}
			}
		}
		
		// Maps
		$startindex = $xml->createElement('startindex', $struct['startindex']);
		$startindex = $playlist->appendChild($startindex);
		if( isset($struct[$mapField]) && !empty($struct[$mapField]) ){
			foreach($struct[$mapField] as $dataIdent => $dataFile){
				$map = $xml->createElement($mapField);
				$map = $playlist->appendChild($map);
				$file = $xml->createElement('file', $dataFile);
				$file = $map->appendChild($file);
				if(SERVER_VERSION_NAME == 'TmForever'){
					$ident = $xml->createElement('ident', $dataIdent);
					$ident = $map->appendChild($ident);
				}
			}
		}
		
		if( !$xml->save($filename) ){
			$out = Utils::t('Saving XML file error');
		}
		else{
			$out = true;
		}
		
		return $out;
	}
	
	
	/**
	* Extrait les données d'un MatchSettings et renvoi un tableau
	*
	* @param string $filename -> L'url du MatchSettings
	* @param array  $list     -> Liste des champs à retourner
	* @return array
	*/
	public static function getMatchSettingsData($filename, $list = array('gameinfos', 'hotseat', 'filter', 'scriptsettings', 'maps') ){
		$out = array();
		$xml = null;
		
		if( file_exists($filename) ){
			$xml = new DOMDocument('1.0', 'utf-8');
			$xml->load($filename);
		}
		
		if($xml){
			// Gameinfos
			if( in_array('gameinfos', $list) ){
				$fields = array(
					'game_mode' => 'GameMode',
					'chat_time' => 'ChatTime',
					'finishtimeout' => 'FinishTimeout',
					'allwarmupduration' => 'AllWarmUpDuration',
					'disablerespawn' => 'DisableRespawn',
					'forceshowallopponents' => 'ForceShowAllOpponents',
					'rounds_pointslimit' => 'RoundsPointsLimit',
					'rounds_custom_points' => 'RoundCustomPoints',
					'rounds_usenewrules' => 'RoundsUseNewRules',
					'rounds_forcedlaps' => 'RoundsForcedLaps',
					'rounds_pointslimitnewrules' => 'RoundsPointsLimitNewRules',
					'team_pointslimit' => 'TeamPointsLimit',
					'team_maxpoints' => 'TeamMaxPoints',
					'team_usenewrules' => 'TeamUseNewRules',
					'team_pointslimitnewrules' => 'TeamPointsLimitNewRules',
					'timeattack_limit' => 'TimeAttackLimit',
					'timeattack_synchstartperiod' => 'TimeAttackSynchStartPeriod',
					'laps_nblaps' => 'LapsNbLaps',
					'laps_timelimit' => 'LapsTimeLimit',
					'cup_pointslimit' => 'CupPointsLimit',
					'cup_roundsperchallenge' => 'CupRoundsPerMap',
					'cup_nbwinners' => 'CupNbWinners',
					'cup_warmupduration' => 'CupWarmUpDuration',
				);
				if(SERVER_VERSION_NAME != 'TmForever'){
					$fields['script_name'] = 'ScriptName';
				}
				
				foreach($fields as $fieldXML => $fieldName){
					$fieldList = $xml->getElementsByTagName($fieldXML);
					if($fieldList->length > 0){
						$out['gameinfos'][$fieldName] = $fieldList->item(0)->nodeValue;
					}
				}
			}
			
			// Hotseat
			if( in_array('hotseat', $list) ){
				$fields = array(
					'game_mode' => 'GameMode',
					'time_limit' => 'TimeLimit',
					'rounds_count' => 'RoundsCount',
				);
				
				foreach($fields as $fieldXML => $fieldName){
					$fieldList = $xml->getElementsByTagName($fieldXML);
					if($fieldList->length > 0){
						$out['hotseat'][$fieldName] = $fieldList->item(0)->nodeValue;
					}
				}
			}
			
			// Filter
			if( in_array('filter', $list) ){
				$fields = array(
					'is_lan' => 'IsLan',
					'is_internet' => 'IsInternet',
					'is_solo' => 'IsSolo',
					'is_hotseat' => 'IsHotseat',
					'sort_index' => 'SortIndex',
					'random_map_order' => 'RandomMapOrder',
					'force_default_gamemode' => 'ForceDefaultGameMode',
				);
				
				foreach($fields as $fieldXML => $fieldName){
					$fieldList = $xml->getElementsByTagName($fieldXML);
					if($fieldList->length > 0){
						$out['filter'][$fieldName] = $fieldList->item(0)->nodeValue;
					}
				}
			}
			
			// Script Settings
			if( in_array('scriptsettings', $list) ){
				$scriptsettings = $xml->getElementsByTagName('setting');
				if($scriptsettings->length > 0){
					$i = 0;
					foreach($scriptsettings as $setting){
						if( $setting->hasAttributes() ){
							foreach($setting->attributes as $attName => $attrNode) {
								$out['scriptsettings'][$i][$attName] = $attrNode->value;
							}
						}
						$i++;
					}
				}
			}
			
			// Maps
			if( in_array('maps', $list) ){
				$fieldStartIndex = $xml->getElementsByTagName('startindex');
				if($fieldStartIndex->length > 0){
					$out['StartIndex'] = $fieldStartIndex->item(0)->nodeValue;
				}
				
				$mapsField = (SERVER_VERSION_NAME == 'TmForever') ? 'challenge' : 'map';
				$fieldMaps = $xml->getElementsByTagName($mapsField);
				
				foreach($fieldMaps as $map){
					$ident = null;
					$fieldIdent = $map->getElementsByTagName('ident');
					if($fieldIdent->length > 0){
						$ident = $fieldIdent->item(0)->nodeValue;
					}
					$file = null;
					$fieldFile = $map->getElementsByTagName('file');
					if($fieldFile->length > 0){
						$file = $fieldFile->item(0)->nodeValue;
					}
					
					if($ident){
						$out['maps'][$ident] = $file;
					}
					else{
						$out['maps'][] = $file;
					}
				}
			}
		}
		
		return $out;
	}
	
	
	/**
	* Met en forme les données des maps à partir d'un MatchSettings
	*
	* @global resource $client -> Le client doit être initialisé
	* @param  array    $maps   -> Le tableau extrait du matchsettings : assoc array(ident => filename)
	* @return array
	*/
	public static function getMapListFromMatchSetting($maps){
		global $client;
		$out = array();
		$path = self::getMapsDirectoryPath();
		$countMapList = count($maps);
		
		if($countMapList > 0){
			$i = 0;
			foreach($maps as $mapUId => $mapFileName){
				if( in_array(File::getDoubleExtension($mapFileName), AdminServConfig::$MAP_EXTENSION) ){
					// Données
					if(SERVER_VERSION_NAME == 'TmForever'){
						$Gbx = new GBXChallengeFetcher($path.Str::toSlash($mapFileName));
					}
					else{
						$Gbx = new GBXChallMapFetcher();
						$Gbx->processFile($path.Str::toSlash($mapFileName));
					}
					
					// Name
					$name = htmlspecialchars($Gbx->name, ENT_QUOTES, 'UTF-8');
					$out['lst'][$i]['Name'] = TmNick::toHtml($name, 10, true);
					
					// Environnement
					$env = $Gbx->envir;
					if($env == 'Speed'){ $env = 'Desert'; }else if($env == 'Alpine'){ $env = 'Snow'; }
					$out['lst'][$i]['Environment'] = $env;
					
					// Autres
					$out['lst'][$i]['FileName'] = $mapFileName;
					$out['lst'][$i]['UId'] = $Gbx->uid;
					$out['lst'][$i]['Author'] = $Gbx->author;
					$i++;
				}
			}
		}
		
		// Nombre de maps
		$out += self::getNbMaps($out);
		if($out['nbm']['count'] == 0){
			$out['lst'] = Utils::t('No map');
		}
		
		return $out;
	}
	
	
	/**
	* Extrait les données d'une playlist (blacklist ou guestlist)
	*
	* @param string $filename -> L'url de la playlist
	* @return array
	*/
	public static function getPlaylistData($filename){
		$out = array();
		
		$xml = null;
		if( file_exists($filename) ){
			$xml = new DOMDocument('1.0', 'utf-8');
			$xml->load($filename);
		}
		
		if($xml){
			$root = $xml->documentElement;
			$out['type'] = $root->nodeName;
			$players = $xml->getElementsByTagName($root->nodeName);
			foreach($players as $player){
				$login = $player->getElementsByTagName('login');
				if($login->length > 0){
					$out['logins'][] = $login->item(0)->nodeValue;
				}
			}
		}
		
		return $out;
	}
}
?>
