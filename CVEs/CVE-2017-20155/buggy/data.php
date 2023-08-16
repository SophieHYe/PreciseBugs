<?php
/**
 * @var modX $modx
 * @var array $scriptProperties
 */
$setting = $modx->getObject('modSystemSetting', 'analyticsdashboardwidget.days');
$days = $setting->get('value');
$modx->getCacheManager();

$analytics = $modx->cacheManager->get($days.'-analytics');

$data = trim($_GET['data']);
$format = trim($_GET['format']);
if(isset($analytics)){
	if($format == 'json'){
		if(in_array($data, array('trafficsourceschararr', 'mobile', 'goalstable', 'profiles'))){
			print(json_encode($analytics[$data]));
		}else{
			print(json_encode($analytics[$data]['rows']));
		}
	}else{
		if(in_array($data, array('trafficsourceschararr', 'mobile', 'goalstable'))){
			print_r($analytics[$data]);
		}else{
			print_r($analytics[$data]['rows']);
		}
	}
}
die();