<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2017 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisFront\Controller;

use Laminas\View\Model\JsonModel;
use Assetic\Exception\Exception;
use Laminas\View\Model\ViewModel;
use MelisCore\Controller\MelisAbstractActionController;

class MelisPluginRendererController extends MelisAbstractActionController
{

    public function getPluginAction()
    {   
        $module = $this->getRequest()->getQuery('module', $this->params()->fromRoute('module'));
        $pluginName = $this->getRequest()->getQuery('pluginName', $this->params()->fromRoute('pluginName'));
        $pageId = $this->getRequest()->getQuery('pageId', $this->params()->fromRoute('pageId', 1));
        $pluginId = $this->getRequest()->getQuery('pluginId', $this->params()->fromRoute('pluginId', null));
        $fromDragDropZone = $this->getRequest()->getQuery('fromDragDropZone', $this->params()->fromRoute('fromDragDropZone', false));
        $encapsulatedPlugin = $this->getRequest()->getQuery('encapsulatedPlugin', true);
        $post = $this->getRequest()->getPost()->toArray();
        $pluginHardcodedConfig = array();
        if (!empty($post['pluginHardcodedConfig']))
        {
            $pluginHardcodedConfig = $post['pluginHardcodedConfig'];
            $pluginHardcodedConfig = html_entity_decode($pluginHardcodedConfig, ENT_QUOTES);
            $pluginHardcodedConfig = html_entity_decode($pluginHardcodedConfig, ENT_QUOTES);
            $pluginHardcodedConfig = unserialize($pluginHardcodedConfig, ['allowed_classes' => false]);
        }
        
        $translator = $this->getServiceManager()->get('translator');
    
        $results = array();
        
        $config = $this->getServiceManager()->get('config');
        if (empty($module) || empty($pluginName) || empty($pageId))
        {
            $results['success'] = false;
            $results['errors'] = $translator->translate('tr_melisfront_generate_error_No module or plugin or idpage parameters');
        }
        else
        {
            if (empty($config['plugins'][$module]['plugins'][$pluginName]))
            {
                $results['success'] = false;
                $results['errors'] = $translator->translate('tr_melisfront_generate_error_Plugin config not found');
            }
            else
            {
                $pluginConf = $config['plugins'][$module]['plugins'][$pluginName];
                
                try
                {
                    /**
                     * check if plugin is came from the mini template
                     */
                    if (strpos($pluginName, 'MiniTemplatePlugin') !== false) {
                        $old = $pluginName;
                        //explode to get the original plugin name
                        $plugin = explode('_', $pluginName);
                        //set the original plugin name
                        $pluginName = $plugin[0];

                        $melisPlugin = $this->getServiceManager()->get('ControllerPluginManager')->get($pluginName);
                        $melisPlugin->setMiniTplPluginName($old);
                    }else{
                        $melisPlugin = $this->getServiceManager()->get('ControllerPluginManager')->get($pluginName);
                    }
                    
                    // dragdrop, delete only if plugin is not hardcoded
                    if (empty($pluginId))
                        $melisPlugin->setPluginHardcoded(false);
                    else
                        $melisPlugin->setPluginHardcoded(true);
                    
                    if ($fromDragDropZone)
                        $melisPlugin->setPluginFromDragDrop(true);
                    
                    $melisPlugin->setEncapsulatedPlugin($encapsulatedPlugin);

                    $pluginParameters = $pluginHardcodedConfig;
                    $pluginParameters['pageId'] = $pageId;
                    
                    // if it's a reload and there's an id
                    if (!empty($pluginId))
                    {
                        $pluginParameters['id'] = $pluginId;
                        $generatePluginId = false;
                    }
                    else
                        $generatePluginId = true;

                    $melisPluginView = $melisPlugin->render($pluginParameters, $generatePluginId);
                        
                    $viewRender = $this->getServiceManager()->get('ViewRenderer');
                    $html = $viewRender->render($melisPluginView);
                    
                    $pluginConfFOBO = array();
                    $BoFiles = (!empty($pluginConf['melis']['files']))? $pluginConf['melis']['files'] : array();
                    $BoInit = (!empty($pluginConf['melis']['js_initialization']))? $pluginConf['melis']['js_initialization'] : array();
                    
                    $frontConfig = $melisPlugin->getPluginFrontConfig();

                    $FoFiles = (!empty($frontConfig['files']))? $frontConfig['files'] : array();
                    
                    $pluginConfFOBO = array(
                        'front' => array('ressources' => $FoFiles),
                        'melis' => array('ressources' => $BoFiles, 'js_initialization' => $BoInit),
                    );

                    $dom = array(
                        'widthDesktop' => $frontConfig['widthDesktop'],
                        'widthTablet' => $frontConfig['widthTablet'],
                        'widthMobile' => $frontConfig['widthMobile'],
                        'pluginContainerId' => $frontConfig['pluginContainerId'],
                    );
                    
                    $results = array(
                        'success' => true,
                        'datas' => array(
                            'dom'  => $dom,
                            'html' => $html,
                            'init' => $pluginConfFOBO
                        )
                    );
                }
                catch (Exception $e)
                {
                    $results['success'] = false;
                    $results['errors'] = $translator->translate('tr_melisfront_generate_error_Plugin cant be created');
                }
            }
        }
        
        return new JsonModel($results);
    }

    public function editPluginAction()
    {
        $view  = new ViewModel();

        return $view;
    }

}
