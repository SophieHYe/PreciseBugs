<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2017 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisAssetManager;

use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\Stdlib\ArrayUtils;

/**
 * Minify Classes
 */
$path = __DIR__ .'/../lib';

/**
 * Class Module
 * @package MelisAssetManager
 */


class Module
{
    private $modulePathFile = '/melis.modules.path.php';
    private $mimePathFile = '/../config/mime.config.php';
    
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $sm = $e->getApplication()->getServiceManager();
        $this->displayFile($sm);
    }
    
    public function init(ModuleManager $manager)
    {
        $eventManager = $manager->getEventManager();
        $eventManager->attach(ModuleEvent::EVENT_LOAD_MODULES_POST, [$this, 'onLoadModulesPost']);
    }
    
    public function onLoadModulesPost(ModuleEvent $event)
    {
        $sm                 = $event->getParam('ServiceManager');
        $modulesService     = $sm->get('MelisAssetManagerModulesService');
        $assetConfigFolder  = $_SERVER['DOCUMENT_ROOT'] . '/../config';
        $sitesModulesFolder = $_SERVER['DOCUMENT_ROOT'] . '/../module/MelisSites';
        $allModules         = $modulesService->getAllModules();

        $modulePathFile = $assetConfigFolder . $this->modulePathFile;

        $newModules = false;
        if (file_exists($modulePathFile))
        {
            // checking if there's new modules not in the path list
            $loadedModules = $modulesService->getActiveModules();
            $existingPathModules = require $assetConfigFolder . $this->modulePathFile;
            
            foreach ($loadedModules as $moduleName)
            {
                if (empty($existingPathModules[$moduleName]))
                {
                    $newModules = true;
                    break;
                }
            }
        }

        if (!file_exists($modulePathFile) || $newModules)
        {

            $modulesList  = array();
            $sitesModules = $modulesService->getSitesModules();
            
            // BO Activated Modules
            foreach ($allModules as $moduleName)
            {
                $path = $modulesService->getModulePath($moduleName);
                $path = str_replace($_SERVER['DOCUMENT_ROOT'] . '/..', '', $path);
                $modulesList[$moduleName] = $path;
            }
            
            // Sites modules
            foreach ($sitesModules as $moduleName)
            {
                $path = $sitesModulesFolder . '/' . $moduleName;
                $path = str_replace($_SERVER['DOCUMENT_ROOT'] . '/..', '', $path);
                $modulesList[$moduleName] = $path;
            }
            
            try 
            {
                $fd = fopen($modulePathFile, 'w');
                if ($fd)
                {
                    $modulesPathsArray = "<?php \n\n";
                    $modulesPathsArray .= "\treturn array( \n";
        
                    $pathFile = '';
                    foreach ($modulesList as $moduleName => $modulePath)
                    {
                        $modulesPathsArray .= "\t\t'$moduleName' => '$modulePath', \n";
                    }
                    $modulesPathsArray .= "\t); \n";
                    
                    fwrite($fd, $modulesPathsArray);
                    fclose($fd);
                    chmod($modulePathFile, 0777);
                    
                    $this->displayFile($sm);
                }
                else
                {
                    /*echo "Error generating file $modulePathFile : check rights";
                    die;*/
                }
            }
            catch (\Exception $e)
            {
                /*echo "Error generating file $modulePathFile : check rights";
                die;*/
            }
        }
    }
    
    public function displayFile($sm)
    {

        $moduleSvc =  $sm->get('MelisAssetManagerModulesService');
        $assetConfigFolder = $_SERVER['DOCUMENT_ROOT'] . '/../config';
        $uri = $_SERVER['REQUEST_URI'];
        
        $UriWithoutParams = explode('?', $uri);
        
        $UriParams = "";
        $UriWithoutParams = $UriWithoutParams[0];
        if (!empty($UriWithoutParams[1]))
            $UriParams = $UriWithoutParams[1];
        
        
        // First check if asset in main public folder
        $pathFile = $_SERVER['DOCUMENT_ROOT'] . $UriWithoutParams;
        if (is_file($pathFile) && $this->checkFileInFolder($pathFile, $_SERVER['DOCUMENT_ROOT']))
            $this->sendDocument($pathFile, $UriParams);
        else
        {
            // testing module public folder second
            if (file_exists($assetConfigFolder . $this->modulePathFile))
            {
                $loadedModules = $moduleSvc->getAllModules();
                $modulesPath = require $assetConfigFolder . $this->modulePathFile;
                
                $detailUri = explode('/', $UriWithoutParams);
                if (count($detailUri) > 1)
                {
                    $moduleUri = $detailUri[1];
                    
                    // Need to have a path defined, and module loaded 
                    if (!empty($modulesPath[$moduleUri]) && (in_array($moduleUri, $loadedModules) ||
                        strpos($modulesPath[$moduleUri], 'MelisSites') !== false))
                    {
                        $path = $modulesPath[$moduleUri];
                        
                        if (str_replace($_SERVER['DOCUMENT_ROOT'] . '/..', '', $path) == $path)
                        {
                            // relative path
                            $path = $_SERVER['DOCUMENT_ROOT'] . '/..' . $path;
                        }
    
                        $pathFile = $path . '/public';
                        for ($i = 2; $i < count($detailUri); $i++)
                            $pathFile .= '/' . $detailUri[$i];
    
                        if ($pathFile != '')
                        {
                            if (is_file($pathFile) && $this->checkFileInFolder($pathFile, $path . '/public/'))
                                $this->sendDocument($pathFile, $UriParams);
                        }
                    }
                }
            }
        }
    }
    
    public function getMimeType($filename)
    {
        $mimeConfig = require __DIR__ . $this->mimePathFile;
                            
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
        if (isset($mimeConfig['mime'][$extension])) {
            return $mimeConfig['mime'][$extension];
        }
    
        return 'text/plain';
    }
    
    public function sendDocument($pathFile, $UriParams)
    {
        $mime   = $this->getMimeType($pathFile);

        // if php file, we need to eval
        if ($mime == 'application/x-httpd-php') {
            header('HTTP/1.0 200 OK');
            header("Content-Type: text/html; charset=UTF-8" . $mime);

            $folderPath = explode('/', $pathFile);
            $fileName = $folderPath[count($folderPath) - 1];
            unset($folderPath[count($folderPath) - 1]);
            $folderPath = implode('/', $folderPath);
            
            eval ( ' chdir("' . $folderPath . '"); require "' . $fileName . '";' );
        } else {

            $content = file_get_contents($pathFile);


            header('HTTP/1.0 200 OK');
            header("Content-Type: " . $mime);

            $seconds_to_cache = 60 * 60 * 24; // 24hrs
            $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
            header("Expires: $ts");
            header("Pragma: cache");
            header("Cache-Control: max-age=$seconds_to_cache");

            print $content;
        }

        die;
    }
    
    public function getConfig()
    {
    	$config = [];
    	$configFiles = [
    			include __DIR__ . '/../config/module.config.php',
    	];

    	foreach ($configFiles as $file) {
    		$config = ArrayUtils::merge($config, $file);
    	}

    	return $config;
    }

    public function getAutoloaderConfig()
    {
        return [
            'Laminas\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

     /*checks if the file is inside the given folder*/
    protected function checkFileInFolder($file, $folder)
    {       
        $path = realpath($file);        
        if ($path !== false) {
            if (strpos($path, realpath($folder)) !== 0) {                   
                throw new \Exception('Requested resource is outside of ' . $folder);
            } else {              
                return true;
            }
        } else {                   
            return false;
        }
    }
}
