<?php

class SOYInquiryApplication{

	function init(){

		$level = CMSApplication::getAppAuthLevel();

		CMSApplication::setTabs(array(
			array(
				"label" => "新着",
				"href" => SOY2PageController::createLink(APPLICATION_ID),
				"icon" => "home"
			),
			array(
				"label" => "問い合わせ",
				"href" => SOY2PageController::createLink(APPLICATION_ID . ".Inquiry"),
				"icon" => "list-alt"
			),
			array(
				"label" => "フォーム",
				"href" => SOY2PageController::createLink(APPLICATION_ID . ".Form"),
				"icon" => "edit"
			),
			array(
				"label" => "設定",
				"href" => SOY2PageController::createLink(APPLICATION_ID . ".Config"),
				"visible" => ($level == 1) ? true : false,
				"icon" => "cog"
			),
			array(
				"label" => "ヘルプ",
				"href" => SOY2PageController::createLink(APPLICATION_ID . ".Help"),
				"visible" => ($level == 1) ? true : false,
				"icon" => "question"
			)
		));

		CMSApplication::main(array($this,"main"));

		CMSApplication::addLink(SOY2PageController::createRelativeLink("./webapp/" . APPLICATION_ID . "/js/tools/soy2_data_picker.css"));
		CMSApplication::addScript(SOY2PageController::createRelativeLink("./webapp/" . APPLICATION_ID . "/js/tools/soy2_data_picker.js"));

		//設定の読み込み
		include_once(dirname(__FILE__) . "/config.php");

		//DBの初期化を行う
		if(!file_exists(SOYINQUIRY_DB_FILE)){
			SOY2Logic::createInstance("logic.InitLogic", array(
				"initCheckFile" => SOYINQUIRY_DB_FILE,
			))->init();
		}

		//SOY2HTMLの設定
		SOY2HTMLPlugin::addPlugin("page","PagePlugin");
		SOY2HTMLPlugin::addPlugin("link","LinkPlugin");
		SOY2HTMLPlugin::addPlugin("src","SrcPlugin");
		SOY2HTMLPlugin::addPlugin("display","DisplayPlugin");
		SOY2HTMLPlugin::addPlugin("panel","PanelPlugin");

		//CSSの読み込み
		$cssLink = SOY2PageController::createRelativeLink("./webapp/".APPLICATION_ID."/css/three.css");
		if(method_exists("CMSApplication", "getVersion")){
			$cssLink .= "?".CMSApplication::getVersion();
		}
		CMSApplication::addLink($cssLink);

	}

	function main(){

		//アップグレードバッチ
		if(isset($_GET["bat"]) && file_exists(dirname(__FILE__) . "/src/bat/" . $_GET["bat"] . ".php")){
			include(dirname(__FILE__) . "/src/bat/" . $_GET["bat"] . ".php");
			exit;
		}

		$arguments = CMSApplication::getArguments();

		$classPath = array();
		$args = array();
		$flag = false;
		foreach($arguments as $key => $value){
			if(is_numeric($value)){
				$flag = true;
			}

			if($flag){
				$args[] = $value;
			}else{
				$classPath[] = $value;
			}
		}
		$path = implode(".",$classPath);
		$classPath = $path;

		if(strlen($classPath)<1)$classPath = "Index";
		$classPath .= 'Page';

		//一回だけIndexPageを試す。
		if(!SOY2HTMLFactory::pageExists($classPath)){
			$classPath = $path;

			if(!preg_match('/.+Page$/',$classPath)){
				$classPath .= '.IndexPage';
			}
		}

		//タブの設定
		if(preg_match('/^Inquiry/',$classPath)){
			CMSApplication::setActiveTab(1);
		}
		if(preg_match('/^Form/',$classPath)){
			CMSApplication::setActiveTab(2);
		}
		if(preg_match('/^Config/',$classPath)){
			CMSApplication::setActiveTab(3);
		}
		if(preg_match('/^Help/',$classPath)){
			CMSApplication::setActiveTab(4);
		}

		if(!SOY2HTMLFactory::pageExists($classPath)){
			return $classPath;
		}

		$webPage = &SOY2HTMLFactory::createInstance($classPath, array(
			"arguments" => $args
		));

		try{
			ob_start();
			$webPage->display();
			$html = ob_get_contents();
			ob_end_clean();
		}catch(Exception $e){

		}

		return $html;
	}

}

$app = new SOYInquiryApplication();
$app->init();
