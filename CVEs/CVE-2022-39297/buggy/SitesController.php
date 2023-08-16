<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisCms\Controller;

use MelisCore\Controller\MelisAbstractActionController;
use MelisFront\Service\MelisSiteConfigService;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use MelisCore\Service\MelisCoreRightsService;
use Laminas\Config\Reader\Json;
use phpDocumentor\Reflection\Types\Boolean;

/**
 * Site Tool Plugin
 */
class SitesController extends MelisAbstractActionController
{
    const TOOL_INDEX = 'meliscms';
    const TOOL_KEY = 'meliscms_tool_sites';

    const SITE_TABLE_PREFIX = 'site_';
    const DOMAIN_TABLE_PREFIX = 'sdom_';
    const SITE404_TABLE_PREFIX = 's404_';

    /**
     * Main container of the tool, this holds all the components of the tools
     * @return ViewModel();
     */
    public function renderToolSitesAction() {
        
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->melisKey = $melisKey;
        return $view;
    }

    /**
     * @return ViewModel();
     */
    public function renderToolSitesEditAction() {
        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->siteId = $siteId;

        return $view;
    }

    /**
     * @return ViewModel();
     */
    public function renderToolSitesEditHeaderAction() {
        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $melisKey = $this->getMelisKey();

        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->siteId = $siteId;
        $view->siteLabel = $this->getSiteDataById($siteId)['site_label'] ?? '';

        return $view;
    }

    /**
     * @return ViewModel();
     */
    public function renderToolSitesEditSiteHeaderSaveAction() {
        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->siteId = $siteId;
        return $view;
    }

    public function renderToolSitesTabsAction() {

        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->siteId = $siteId;
        return $view;
    }

    public function renderToolSitesSiteConfigAction() {

        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->siteId = $siteId;
        return $view;
    }

    /**
     * Renders to the header section of the tool
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesHeaderAction() {

        $melisKey = $this->getMelisKey();

        $view              = new ViewModel();
        $view->melisKey    = $melisKey;
        $view->headerTitle = $this->getTool()->getTranslation('tr_meliscms_tool_sites_header_title');
        $view->subTitle    = $this->getTool()->getTranslation('tr_meliscms_tool_sites_header_sub_title');
        return $view;
    }

    public function renderToolSitesHeaderAddAction()
    {
        $view = new ViewModel();
        return $view;
    }

    /**
     * Renders to the refresh button in the table filter bar
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentFilterRefreshAction()
    {
        return new ViewModel();
    }

    /**
     * Renders to the Search input in the table filter bar
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentFilterSearchAction()
    {
        return new ViewModel();
    }

    /**
     * Renders to the limit selection in the table filter bar
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentFilterLimitAction()
    {
        return new ViewModel();
    }
    
    /**
     * Renders to the center content of the tool
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentAction()
    {
        $translator = $this->getServiceManager()->get('translator');
        $melisKey = $this->getMelisKey();
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);

        $columns = $melisTool->getColumns();
        // pre-add Action Columns
        $columns['actions'] = array('text' => $translator->translate('tr_meliscms_action'));

        $view = new ViewModel();
        $view->melisKey = $melisKey;
        $view->tableColumns = $columns;
        $view->getToolDataTableConfig = $melisTool->getDataTableConfiguration('#tableToolSites',false,false,array('order' => '[[ 0, "desc" ]]'));
        return $view;
    }

    /**
     * Renders to the edit button in the table
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentActionMinifyAssetsAction()
    {
        return new ViewModel();
    }

    /**
     * This is the container of the modal
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesModalContainerAction()
    {
        $melisKey = $this->getMelisKey();

        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id', ''));

        $view = new ViewModel();
        $view->setTerminal(false);
        $view->melisKey = $melisKey;
        $view->id = $id;

        return $view;
    }
    
    /**
     * Renders to the empty modal display, this will be displayed if the user doesn't have access to the modal tabs
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesModalEmptyAction()
    {
        $config = $this->getServiceManager()->get('MelisCoreConfig');
        $tool = $config->getItem('/meliscms/interface/meliscms_toolstree/interface/meliscms_tool_sites/interface/meliscms_tool_sites_modals');
        return new ViewModel();
    }
    

    /**
     * Displays the add form in the modal
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesModalAddAction()
    {
        // declare the Tool service that we will be using to completely create our tool.
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->setTerminal(false);
        $view->melisKey  = $melisKey;
        return $view;
    }

    public function renderToolSitesModalAddStep1Action()
    {
        $melisKey = $this->getMelisKey();

        // declare the Tool service that we will be using to completely create our tool.
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');

        // tell the Tool what configuration in the app.tools.php that will be used.
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);
        //prepare the user profile form
        $form = $melisTool->getForm('meliscms_tool_sites_modal_add_step1_form');

        $view = new ViewModel();
        $view->setVariable('step1_form', $form);
        $view->melisKey = $melisKey;

        return $view;
    }
    public function renderToolSitesModalAddStep2Action()
    {
        $melisKey = $this->getMelisKey();

        //get the lang list
        $langService = $this->getServiceManager()->get('MelisEngineLang');
        $langList = $langService->getAvailableLanguages();

        // declare the Tool service that we will be using to completely create our tool.
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');

        // tell the Tool what configuration in the app.tools.php that will be used.
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);
        //prepare the step2 forms
        $formMultiLingual = $melisTool->getForm('meliscms_tool_sites_modal_add_step2_form_multi_language');
        $formSingleLanguage = $melisTool->getForm('meliscms_tool_sites_modal_add_step2_form_single_language');

        $view = new ViewModel();
        $view->setVariable('step2_form_multi_language', $formMultiLingual);
        $view->setVariable('step2_form_single_language', $formSingleLanguage);
        $view->melisKey = $melisKey;
        $view->langList = $langList;

        return $view;
    }
    public function renderToolSitesModalAddStep3Action()
    {
        $melisKey = $this->getMelisKey();

        // declare the Tool service that we will be using to completely create our tool.
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');

        // tell the Tool what configuration in the app.tools.php that will be used.
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);
        //prepare the step2 forms
        $formMultiDomain = $melisTool->getForm('meliscms_tool_sites_modal_add_step3_form_multi_domain');
        $formSingleDomain = $melisTool->getForm('meliscms_tool_sites_modal_add_step3_form_single_domain');

        $view = new ViewModel();
        $view->setVariable('step3_form_multi_domain', $formMultiDomain);
        $view->setVariable('step3_form_single_domain', $formSingleDomain);
        $view->melisKey  = $melisKey;
        return $view;
    }
    public function renderToolSitesModalAddStep4Action()
    {
        $melisKey = $this->getMelisKey();

        // declare the Tool service that we will be using to completely create our tool.
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');

        // tell the Tool what configuration in the app.tools.php that will be used.
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);
        //prepare the step4 forms
        $moduleForm = $melisTool->getForm('meliscms_tool_sites_modal_add_step4_form_module');

        $view = new ViewModel();
        $view->setVariable('step4_form_module', $moduleForm);
        $view->melisKey = $melisKey;
        return $view;
    }
    public function renderToolSitesModalAddStep5Action()
    {
        $melisKey = $this->getMelisKey();
        $view = new ViewModel();
        $view->setTerminal(false);
        $view->melisKey = $melisKey;
        return $view;
    }
    

    public function renderToolSitesModalEditAction()
    {
        // declare the Tool service that we will be using to completely create our tool.
        $melisTool = $this->getServiceManager()->get('MelisCoreTool');
    
        // tell the Tool what configuration in the app.tool.php that will be used.
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);
    
        $view = new ViewModel();
    
        $view->setVariable('meliscms_site_tool_edition_form', $melisTool->getForm('meliscms_site_tool_edition_form'));
    
        return $view;
    }
    
    /**
     * Renders to the edit button in the table
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentActionEditAction()
    {
        return new ViewModel();
    }
    
    /**
     * Renders to the delete button in the table
     * @return \Laminas\View\Model\ViewModel
     */
    public function renderToolSitesContentActionDeleteAction()
    {
        return new ViewModel();
    }
    
    public function renderToolSitesNewSiteConfirmationModalAction()
    {
        $melisKey = $this->params()->fromRoute('melisKey', '');

        $view = new ViewModel();
        $view->melisKey = $melisKey;

        return $view;
    }
    
    /**
     * Returns all the data from the site table, site domain and site 404
     */
    public function getSiteDataAction()
    {
        $cmsSiteSrv = $this->getServiceManager()->get('MelisCmsSiteService');
        $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');
        $translator = $this->getServiceManager()->get('translator');

        $melisTool = $this->getServiceManager()->get('MelisCoreTool');
        $melisTool->setMelisToolKey(self::TOOL_INDEX, self::TOOL_KEY);

        $colId = array();
        $dataCount = 0;
        $draw = 0;
        $tableData = array();

        if($this->getRequest()->isPost())
        {
            $colId = array_keys($melisTool->getColumns());

            $sortOrder = $this->getRequest()->getPost('order');
            $sortOrder = $sortOrder[0]['dir'];

            $selCol = $this->getRequest()->getPost('order');
            $selCol = $colId[$selCol[0]['column']];

            $draw = $this->getRequest()->getPost('draw');

            $start = (int)$this->getRequest()->getPost('start');
            $length = (int)$this->getRequest()->getPost('length');

            $search = $this->getRequest()->getPost('search');
            $search = $search['value'];

            $dataCount = $siteTable->getTotalData();

            $getData = $siteTable->getSitesData($search, $melisTool->getSearchableColumns(), $selCol, $sortOrder, $start, $length);
            $dataFilter = $siteTable->getSitesData($search, $melisTool->getSearchableColumns(), $selCol, $sortOrder, null, null);

            $tableData = $getData->toArray();
            for ($ctr = 0; $ctr < count($tableData); $ctr++) {
                // apply text limits
                foreach ($tableData[$ctr] as $vKey => $vValue) {
                    $tableData[$ctr][$vKey] = $melisTool->limitedText($melisTool->escapeHtml($vValue));
                }

                // manually modify value of the desired row
                // no specific row to be modified

                // add DataTable RowID, this will be added in the <tr> tags in each rows
                $tableData[$ctr]['DT_RowId'] = $tableData[$ctr]['site_id'];

                /**
                 * Check if module exist to disable the
                 * minify button
                 */
                $modulePath = $cmsSiteSrv->getModulePath($tableData[$ctr]['site_name']);
                if(file_exists($modulePath)){
                    $attrArray = array('data-mod-found'   => true);
                }else{
                    $attrArray = array('data-mod-found'   => false);
                }

                //assign attribute data to table row
                $tableData[$ctr]['DT_RowAttr'] = $attrArray;
            }
        }

        return new JsonModel(array(
            'draw' => (int) $draw,
            'recordsTotal' => $dataCount,
            'recordsFiltered' =>  $dataFilter->count(),
            'data' => $tableData,
        ));
    }

    /**
     * @return JsonModel
     */
    public function createNewSiteAction()
    {
        $sId = null;
        $errors = array();
        $status = false;
        $siteIds = array();
        $siteName = '';
        $siteLabel = '';
        $textMessage = '';
        $siteTablePrefix = self::SITE_TABLE_PREFIX;
        $domainTablePrefix = self::DOMAIN_TABLE_PREFIX;

        $translator = $this->getServiceManager()->get('translator');
        $cmsSiteSrv = $this->getServiceManager()->get('MelisCmsSiteService');

        if ($this->getRequest()->isPost()) {
            $sitesData = $this->getRequest()->getPost('data');
            if(!empty($sitesData)) {
                $createNewFile = false;
                $isNewSIte = false;
                $siteData = array();
                $siteLanguages = array();
                $site404Data = array();
                $domainData = array();
                $domainDataTemp = array();

                /**
                 * This will look for every specific data for each table(site, domains, etc..)
                 *
                 * The Domain is specific case cause there's a chance that the user will
                 * select multi domain for every site(depend on language) and even though
                 * the user select single domain, we will still need to prepare the data as
                 * equal to multi domain
                 */
                foreach ($sitesData as $key => $value) {
                    if (!empty($value['data']) && is_array($value['data'])) {
                        foreach ($value['data'] as $k => $val) {
                            if (!empty($val) && is_array($val)) {
                                if (!empty($val['name'])) {
                                    /**
                                     * add site data
                                     */
                                    if (strpos($val['name'], $siteTablePrefix) !== false) {
                                        $siteData[$val['name']] = $val['value'];
                                    }

                                    /**
                                     * add the domain data
                                     */
                                    if ($key == 'domains') {
                                        /**
                                         * if it is came from the domain form, we will put
                                         * it inside the main domain data container
                                         */
                                        if (strpos($val['name'], $domainTablePrefix) !== false) {
                                            $domainData[$k][$val['name']] = $val['value'];
                                        }
                                    } else {
                                        /**
                                         * we will put the domain data to temporary
                                         * container to add to main container later
                                         */
                                        if (strpos($val['name'], $domainTablePrefix) !== false) {
                                            $domainDataTemp[$val['name']] = $val['value'];
                                        }
                                    }
                                } else {
                                    /**
                                     * This will add the data that the key
                                     * is equal to the field name
                                     */
                                    foreach ($val as $field => $fieldValue) {
                                        /**
                                         * add site data
                                         */
                                        if (strpos($field, $siteTablePrefix) !== false) {
                                            $siteData[$field] = $fieldValue;
                                        }

                                        /**
                                         * add domain data
                                         */
                                        if (strpos($field, $domainTablePrefix) !== false) {
                                            $domainData[$k][$field] = $fieldValue;
                                        }
                                    }
                                }
                            } else {
                                /**
                                 * add the site data
                                 */
                                if (strpos($k, $siteTablePrefix) !== false) {
                                    $siteData[$k] = $val;
                                }

                                /**
                                 * Add the other domain data to temporary container
                                 * since it came from other form, were just gonna
                                 * add this to main domain container later
                                 */
                                if (strpos($k, $domainTablePrefix) !== false) {
                                    $domainDataTemp[$k] = $val;
                                }
                            }
                        }
                    } else {
                        foreach ($value as $fieldKey => $fieldValue) {
                            /**
                             * add the site data
                             */
                            if (strpos($fieldKey, $siteTablePrefix) !== false) {
                                $siteData[$fieldKey] = $fieldValue;
                            }

                            /**
                             * Add the other domain data to temporary container
                             * since it came from other form, were just gonna
                             * add this to main domain container later
                             */
                            if (strpos($fieldKey, $domainTablePrefix) !== false) {
                                $domainDataTemp[$fieldKey] = $fieldValue;
                            }
                        }
                    }

                    /**
                     * Check if it is a new site and if were are
                     * gonna create a file for this site
                     */
                    if ($key == 'module') {
                        $createNewFile = ($value['createFile'] === 'true');
                        $isNewSIte = ($value['newSite'] === 'true');
                    }

                    /**
                     * get the site languages
                     */
                    if ($key == 'languages') {
                        $siteLanguages = $value;
                    }
                }

                /**
                 * Fill the other fields with the default one
                 * if the fields are still empty
                 */
                //check if $domainData is empty
                if (empty($domainData) && !empty($domainDataTemp)) {
                    foreach ($siteLanguages as $locale => $langId) {
                        if($locale != 'sites_url_setting') {
                            foreach ($domainDataTemp as $dom => $val) {
                                $domainData[$locale] = array($dom => $val);
                            }
                        }
                    }
                }
                //we need to loop the domain to fill all fields
                foreach ($domainData as $domKey => $domVal) {
                    //add the temporary domain data to the main container
                    foreach ($domainDataTemp as $tempKey => $tempVal) {
                        if (empty($domainData[$domKey][$tempKey])) {
                            $domainData[$domKey][$tempKey] = $tempVal;
                        }
                    }
                    /**
                     * add some default data to domain
                     * if the fields does not exist
                     * or empty
                     */
                    $domainData[$domKey]['sdom_env'] = (!empty($domainData[$domKey]['sdom_env'])) ? $domainData[$domKey]['sdom_env'] : getenv('MELIS_PLATFORM');
                    $domainData[$domKey]['sdom_scheme'] = (!empty($domainData[$domKey]['sdom_scheme'])) ? $domainData[$domKey]['sdom_scheme'] : 'http';
                }
                //field the site data
                if (!empty($siteData)) {
                    $siteName = (!empty($siteData['site_name'])) ? $cmsSiteSrv->generateModuleNameCase($siteData['site_name']) : '';
                    $siteLabel = (!empty($siteData['site_label'])) ? $siteData['site_label'] : $siteName;
                    $siteData['site_label'] = $siteLabel;
                    $siteData['site_name'] = $siteName;
                }

                /**
                 * Before proceeding to save the site
                 * check if it is a new site and
                 * the site is not yet created
                 */
                $isValidName = true;
                if ($isNewSIte) {
                    $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');
                    $siteDBData = $siteTable->getEntryByField('site_name', $siteName)->current();
                    if (!empty($siteDBData)) {
                        $isValidName = false;
                    }
                }

                if ($isValidName) {
                    $saveSiteResult = $cmsSiteSrv->saveSite($siteData, $domainData, $siteLanguages, $site404Data, $siteName, $createNewFile, $isNewSIte);

                    if ($saveSiteResult['success'])
                    {
                        $siteIds = $saveSiteResult['site_ids'];
                        $textMessage = 'tr_melis_cms_sites_tool_add_create_site_success';
                        $status = true;
                        //regenerate modules path
                        $this->regenerateModulesPath();
                    }
                    else
                    {
                        $textMessage = 'tr_melis_cms_sites_tool_add_unable_to_create_site';
                        $errors = array(
                            $translator->translate('tr_melis_cms_sites_tool_add_site_error') => array(
                                'error' => $translator->translate($saveSiteResult['message'])
                            ),
                        );
                        $status = false;
                    }
                }else{
                    $textMessage = 'tr_melis_cms_sites_tool_add_unable_to_create_site';
                    $errors = array(
                        $translator->translate('tr_meliscms_tool_sites_module_name') => array(
                            'moduleAlreadyExists' => $translator->translate('tr_melis_cms_sites_tool_add_site_module_already_exist')
                        ),
                    );
                    $status = false;
                }
            }
        }

        $response = array(
            'success' => $status,
            'textTitle' => 'tr_meliscms_tool_site',
            'textMessage' => $textMessage,
            'siteIds' => $siteIds,
            'siteName' => $siteLabel,
            'siteModuleName' => $siteName,
            'errors' => $errors
        );

        /**
         * add logs
         */
        if(empty($siteIds)) {
            $this->getEventManager()->trigger('meliscms_site_save_end', $this, array_merge($response, array('typeCode' => 'CMS_SITE_ADD', 'itemId' => $sId)));
        }else{
            foreach ($siteIds as $key => $id) {
                $this->getEventManager()->trigger('meliscms_site_save_end', $this, array_merge($response, array('typeCode' => 'CMS_SITE_ADD', 'itemId' => $id)));
            }
        }

       return new JsonModel($response);
    }

    /**
     * Add New Site
     * @return \Laminas\View\Model\JsonModel
     */
    public function saveSiteAction()
    {
        $eventDatas = array();
        $this->getEventManager()->trigger('meliscms_sites_save_start', $this, $eventDatas);

        $status  = 1;
        $errors  = array();
        $textMessage = 'tr_melis_cms_site_save_ko';
        $logTypeCode = 'CMS_SITE_UPDATE';
        $translator = $this->getServiceManager()->get('translator');
        $siteId = (int) $this->params()->fromQuery('siteId', '');
        $request = $this->getRequest();
        $data = $request->getPost()->toArray();
        $melisCoreAuth = $this->getServiceManager()->get('MelisCoreAuth');
        $userAuthDatas = $melisCoreAuth->getStorage()->read();
        $isAdmin = isset($userAuthDatas->usr_admin) || $userAuthDatas->usr_admin != "" ? $userAuthDatas->usr_admin : 0;

        $success = 0;
        $ctr = 0;
        $ctr1 = 0;

        $moduleList = [];
        $domainData = [];
        $sitePropData = [];
        $siteHomeData = [];
        $siteConfigTabData = [];

        foreach ($data as $datum => $val) {
            //collecting data for site module load
            if ($isAdmin) {
                if (strstr($datum,'moduleLoad')) {
                    $datum = str_replace("moduleLoad", '', $datum);
                    array_push($moduleList, $datum);
                }
            }

            //collecting data for site domains
            if (strstr($datum,'sdom_')) {
                $key = substr($datum, (strpos($datum, '_') ?: -1) + 1);
                if(!empty($domainData[$ctr]))
                    if(array_key_exists($key, $domainData[$ctr]))
                        $ctr++;
                $domainData[$ctr][$key] = $val;
            }

            //collecting data for site properties
            if (strstr($datum,'siteprop_')) {
                $datum = str_replace("siteprop_", '', $datum);
                $sitePropData[$datum] = $val;
            }

            //collecting data for site language homepages
            if (strstr($datum,'shome_')) {
                $key = substr($datum, (strpos($datum, '_') ?: -1) + 1);
                if(!empty($siteHomeData[$ctr1]))
                    if(array_key_exists($key, $siteHomeData[$ctr1]))
                        $ctr1++;
                $siteHomeData[$ctr1][$key] = $val;
            }

            //data for site config
            if (strstr($datum,'sconf_')) {
                $lang = explode('_', $datum)[0];
                $key = substr($datum, strpos($datum, '_') + 1);
                $tableColumns = [
                    'sconf_id',
                    'sconf_site_id',
                    'sconf_lang_id'
                ];

                if (in_array($key, $tableColumns)) {
                    $siteConfigTabData[$lang][$key] = $val;
                } else {
                    $key = substr($datum, strpos($datum, '_', strpos($datum, '_') + 1) + 1);

                    if (is_array($val)) {
                        $siteConfigTabData[$lang]['configArray'][$key] = $val;
                    } else {
                        $siteConfigTabData[$lang]['config'][$key] = $val;
                    }
                }
            }
        }

        /**
         * Prepare the transaction so that
         * we can rollback the db process if
         * there are some error occurred
         */
        $db = $this->getServiceManager()->get('Laminas\Db\Adapter\Adapter');//get db adapter
        $con = $db->getDriver()->getConnection();//get db driver connection
        $con->beginTransaction();//begin transaction
        try {
            /**
             * Try to save the site data's
             */
            $this->saveSiteDomains($domainData, $errors, $status);
            $this->saveSiteHomePages($siteHomeData, $data, $errors, $status);
            $this->saveSiteConfig($siteId,$siteConfigTabData);
            $this->saveSiteProperties($siteId, $sitePropData, $errors, $status);
            $this->saveSiteLanguagesTab($siteId, $data);
            /**
             * If there is no error
             * execute the saving
             */
            if(empty($errors)){
                $textMessage = 'tr_melis_cms_site_save_ok';
                /**
                 * If there are no errors
                 * on db saving, we can now process
                 * the module saving
                 */
                $this->saveSiteModules($isAdmin, $siteId, $moduleList,$status, $path);
                if($status) {
                    /**
                     * remove languages from other tabs
                     */
                    if (isset($data['to_delete_languages_data'])) {
                        $LangIds = [];

                        foreach ($data['to_delete_languages_data'] as $langKey => $langVal) {
                            if ($langVal === 'true') {
                                array_push($LangIds, $langKey);
                            }
                        }

                        if (!empty($data['to_delete_languages_data'])) {
                            $this->deleteOtherTabsData($siteId, $LangIds);
                        }
                    }
                    //clear cache
                    $this->clearSiteConfigCache($siteId);
                    /**
                     * if no error, execute the saving
                     */
                    $con->commit();
                }else{
                    $status = false;
                    $textMessage = sprintf($translator->translate("tr_meliscms_tool_site_module_load_no_rights"), $path);
                    $tmpModuleErrorMsg = $translator->translate('tr_melis_cms_site_save_ko');
                    $con->rollback();
                }
            }else{
                $status = false;
                /**
                 * rollback everything
                 */
                $con->rollback();
            }
        }catch (\Exception $ex){
            $status = false;
            $textMessage = 'tr_melis_cms_site_save_ko';
            /**
             * If error occurred
             * rollback the process
             */
            $con->rollback();
        }

        $response = array(
            'success' => $status,
            'textTitle' => $translator->translate('tr_meliscms_tool_site'),
            'textMessage' => $translator->translate($textMessage),
            'errors' => $errors,
        );

        if ($siteId)
        {
            $response['siteId'] = $siteId;
        }

        if (isset($tmpModuleErrorMsg)) {
            $response['tmpModuleErrorMsg'] = $tmpModuleErrorMsg;
        }

        $this->getEventManager()->trigger('meliscms_site_save_end', $this, array_merge($response, array('typeCode' => $logTypeCode, 'itemId' => $siteId)));

        return new JsonModel($response);
    }

    /**
     * Function to delete site
     *
     * @return JsonModel
     */
    public function deleteSiteAction()
    {
        $request = $this->getRequest();
        $status  = false;
        $textMessage = 'tr_meliscms_tool_site_delete_failed';
        $eventDatas = array();
        $siteId = null;

        $this->getEventManager()->trigger('meliscms_site_delete_start', $this, $eventDatas);
        if($request->isPost()) {
            /**
             * get site id
             */
            $siteId = (int) $request->getPost('siteId');
            /**
             * Get services/tables
             */
            $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');
            $domainTable = $this->getServiceManager()->get('MelisEngineTableSiteDomain');
            $site404Table = $this->getServiceManager()->get('MelisEngineTableSite404');
            $siteHomeTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteHome');
            $sitelangsTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteLangs');

            // make sure our ID is not empty
            if(!empty($siteId))
            {
                /**
                 * Prepare the transaction so that
                 * we can rollback the db deletion if
                 * there are some error occurred
                 */
                $db = $this->getServiceManager()->get('Laminas\Db\Adapter\Adapter');//get db adapter
                $con = $db->getDriver()->getConnection();//get db driver connection
                $con->beginTransaction();//begin transaction
                try {
                    /**
                     * Prepare to delete site datas
                     */
                    $siteTable->deleteByField('site_id', $siteId);
                    $domainTable->deleteByField('sdom_site_id', $siteId);
                    $site404Table->deleteByField('s404_site_id', $siteId);
                    $siteHomeTable->deleteByField('shome_site_id', $siteId);
                    $sitelangsTable->deleteByField('slang_site_id', $siteId);

                    $status = true;
                    $textMessage = 'tr_meliscms_tool_site_delete_success';

                    /**
                     * If there is no error
                     * execute the deletion
                     */
                    $con->commit();
                }catch (\Exception $ex){
                    /**
                     * If error occurred
                     * rollback the process
                     */
                    $con->rollback();
                }
            }
        }

        $response = array(
            'success' => $status ,
            'textTitle' => 'tr_meliscms_tool_site',
            'textMessage' => $textMessage
        );
        $this->getEventManager()->trigger('meliscms_site_delete_end', $this, array_merge($response, array('typeCode' => 'CMS_SITE_DELETE', 'itemId' => $siteId)));

        return new JsonModel($response);
    }

    /**
     * Save site properties
     *
     * @param $siteId
     * @param $sitePropData
     * @param $errors
     * @param $status
     */
    private function saveSiteProperties($siteId, $sitePropData, &$errors, &$status)
    {
        /**
         * Check if there is data to be process
         */
        if(!empty($sitePropData)) {
            $form = $this->getTool()->getForm('meliscms_tool_sites_properties_form');
            $form->setData($sitePropData);

            if ($form->isValid()) {
                $siteTbl = $this->getServiceManager()->get('MelisEngineTableSite');
                $siteData = $siteTbl->getEntryById($siteId)->toArray()[0];
                $dataToUpdate = [];

                foreach ($siteData as $siteDatumKey => $siteDatum) {
                    if (array_key_exists($siteDatumKey, $sitePropData)) {
                        if ($sitePropData[$siteDatumKey] != $siteDatum) {
                            $dataToUpdate[$siteDatumKey] = $sitePropData[$siteDatumKey];
                        }
                    }
                }

                if (!empty($dataToUpdate)) {
                    $siteTbl->update($dataToUpdate, 'site_id', $siteId);
                }

                $site404Tbl = $this->getServiceManager()->get('MelisEngineTableSite404');
                $site404 = $site404Tbl->getEntryByField('s404_site_id', $siteId)->current();
                if (!empty($site404)) {
                    if ($site404->s404_page_id != $sitePropData['s404_page_id']) {
                        $site404Tbl->update(
                            [
                                's404_page_id' => $sitePropData['s404_page_id']
                            ],
                            's404_site_id',
                            $siteId
                        );
                    }
                } else {
                    //save the 404 id
                    $site404Tbl->save([
                        's404_site_id' => $siteId,
                        's404_page_id' => $sitePropData['s404_page_id']
                    ]);
                }
            } else {
                $err = [];

                foreach ($form->getMessages() as $key => $val) {
                    $err['siteprop_' . $key] = $val;
                }

                $errors = array_merge($errors, $err);
                $status = 0;
            }
        }
    }

    /**
     * Save site modules
     *
     * @param $isAdmin
     * @param $siteId
     * @param $moduleList
     * @param $status
     * @param $path
     */
    private function saveSiteModules($isAdmin, $siteId, $moduleList, &$status, &$path)
    {
        $siteModuleLoadSvc = $this->getServiceManager()->get("MelisCmsSiteModuleLoadService");

        if ($isAdmin) {
            if(!empty($moduleList)) {
                $data = $siteModuleLoadSvc->saveModuleLoad($siteId, $moduleList);
                $status = $data['status'];
                $path = $data['folder_path'];
            }else{
                $status = 1;
                $path = '';
            }
        }
    }

    /**
     * Save site domains
     *
     * @param $siteDomainData
     * @param $errors
     * @param $status
     */
    private function saveSiteDomains($siteDomainData, &$errors, &$status)
    {
        /**
         * Check if there is data to be process
         */
        if(!empty($siteDomainData)) {
            $siteDomainsSvc = $this->getServiceManager()->get("MelisCmsSitesDomainsService");
            $err = false;
            $domains = [];
            $translator = $this->getServiceManager()->get('translator');

            // First check if forms are valid for every environment
            foreach ($siteDomainData as $domainDatum) {
                $form = $this->getTool()->getForm('meliscms_tool_sites_domain_form');
                $form->setData($domainDatum);

                if ($domainDatum['sdom_env'] == getenv('MELIS_PLATFORM')) {
                    if (!$form->isValid()) {
                        $err = true;
                        $currErr = array();

                        foreach ($form->getMessages() as $key => $err) {
                            $currErr[$domainDatum["sdom_env"] . "_" . $key] = $err;
                        }

                        $errors = array_merge($errors, $currErr);
                        $status = 0;
                    }
                }
            }

            // Second check if there are duplicates
            if (!$err) {
                foreach ($siteDomainData as $domainDatum) {
                    if (in_array($domainDatum['sdom_domain'], $domains)) {
                        $err = true;
                        $currErr = array();
                        $currErr[$domainDatum["sdom_env"] . "_" . 'sdom_domain'] = $translator->translate('tr_melis_cms_sites_tool_add_step3_domain_unique_error');
                        $errors = array_merge($errors, $currErr);
                        $status = 0;
                    } else {
                        $domains[$domainDatum['sdom_env']] = $domainDatum['sdom_domain'];
                    }
                }
            }

            // Third check if domain is already used by another site & save
            if (!$err) {
                foreach ($siteDomainData as $domainDatum) {
                    $form = $this->getTool()->getForm('meliscms_tool_sites_domain_form');
                    $form->setData($domainDatum);

                    if ($domainDatum['sdom_env'] == getenv('MELIS_PLATFORM')) {
                        $siteData = [];
                        $isDomainAvailable = $this->checkDomain($domainDatum, $siteData);

                        if ($isDomainAvailable) {
                            $siteDomainsSvc->saveSiteDomain($domainDatum);
                        } else {
                            $currErr = array();
                            $currErr[$domainDatum["sdom_env"] . '_' . 'sdom_domain'] = $translator->translate('tr_melis_cms_sites_tool_add_step3_domain_error1') . $siteData['site_label'] . $translator->translate('tr_melis_cms_sites_tool_add_step3_domain_error2');
                            $errors = array_merge($errors, $currErr);
                            $status = 0;
                        }
                    } else {
                        if (!empty($domainDatum['sdom_scheme']) && !empty($domainDatum['sdom_domain'])) {
                            $siteData = [];
                            $isDomainAvailable = $this->checkDomain($domainDatum, $siteData);

                            if ($isDomainAvailable) {
                                $siteDomainsSvc->saveSiteDomain($domainDatum);
                            } else {
                                $currErr = array();
                                $currErr[$domainDatum["sdom_env"] . '_' . 'sdom_domain'] = $translator->translate('tr_melis_cms_sites_tool_add_step3_domain_error1') . $siteData['site_label'] . $translator->translate('tr_melis_cms_sites_tool_add_step3_domain_error2');
                                $errors = array_merge($errors, $currErr);
                                $status = 0;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Checks if the domain is available or not
     * @param $domainDatum
     * @param $siteData
     * @return bool
     */
    public function checkDomain($domainDatum, &$siteData) {
        $domainTable = $this->getServiceManager()->get('MelisEngineTableSiteDomain');
        $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');

        $dom = $domainTable->getEntryByField('sdom_domain', $domainDatum['sdom_domain'])->toArray();

        if (!empty($dom)) {
            $site = $siteTable->getEntryById($dom[0]['sdom_site_id'])->toArray()[0];

            if ($site['site_id'] == $domainDatum['sdom_site_id']) {
                return true;
            } else {
                $siteData = $site;
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Save site home page ids
     *
     * @param $siteHomeData
     * @param $data
     * @param $errors
     * @param $status
     */
    private function saveSiteHomePages($siteHomeData, $data, &$errors, &$status)
    {
        /**
         * Check if there is data to be process
         */
        if(!empty($siteHomeData)) {
            $sitePropSvc = $this->getServiceManager()->get("MelisCmsSitesPropertiesService");

            foreach ($siteHomeData as $siteHomeDatum) {
                $form = $this->getTool()->getForm('meliscms_tool_sites_properties_homepage_form');
                $form->setData($siteHomeDatum);
                if(!empty($data['slang_lang_id'])) {
                    if (in_array($siteHomeDatum['shome_lang_id'], $data['slang_lang_id'])) {
                        if ($form->isValid()) {
                            $sitePropSvc->saveSiteLangHome($siteHomeDatum);
                        } else {
                            $currErr = [];

                            foreach ($form->getMessages() as $key => $err) {
                                $currErr[$siteHomeDatum["shome_lang_id"] . "_" . $key] = $err;
                            }

                            $errors = array_merge($errors, $currErr);
                            $status = 0;
                        }
                    }
                }
            }
        }
    }

    /**
     * Save site languages
     *
     * @param $siteId
     * @param $data
     */
    private function saveSiteLanguagesTab($siteId, $data)
    {
        if(isset($data['site_opt_lang_url'])) {
            $siteLangsTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteLangs');
            $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');

            // Saving languages
            $siteLangs = $siteLangsTable->getSiteLangs(null, $siteId, null, null)->toArray();
            $activeSiteLangs = $siteLangsTable->getSiteLangs(null, $siteId, null, true)->toArray();
            $selectedSiteLangs = $data['slang_lang_id'] ?? [];
            $noChangesOnSiteLangs = false;

            // Check if active languages and selected languages are the same
            if (count($activeSiteLangs) === count($selectedSiteLangs)) {
                foreach ($activeSiteLangs as $activeSiteLang) {
                    if (in_array($activeSiteLang['slang_lang_id'], $selectedSiteLangs)) {
                        $noChangesOnSiteLangs = true;
                    }
                }
            }

            // Catch if there are changes on the selected languages over the active languages
            if (!$noChangesOnSiteLangs) {
                // Disable all active languages of site
                $siteLangsTable->update(['slang_status' => 0], 'slang_site_id', $siteId);

                // Because all of the active languages are disabled. All we have to do
                // is to save if it's a new language or to active(update) the language back
                foreach ($selectedSiteLangs as $selectedSiteLang) {
                    $slangId = 0;

                    foreach ($siteLangs as $siteLang) {
                        if ($selectedSiteLang == $siteLang['slang_lang_id']) {
                            $slangId = $siteLang['slang_id'];
                            break;
                        }
                    }

                    $siteLangsTable->save(
                        [
                            'slang_site_id' => $siteId,
                            'slang_lang_id' => $selectedSiteLang,
                            'slang_status' => 1
                        ],
                        $slangId
                    );
                }
            }

            // Update site to add site option language url
            /**
             * Update only if there are not the same data
             */
            $siteDatas = $siteTable->getEntryById($siteId)->current();
            if(!empty($siteDatas)){
                if($siteDatas->site_opt_lang_url != $data['site_opt_lang_url']){
                    $updatedSiteId = $siteTable->save(['site_opt_lang_url' => $data['site_opt_lang_url']], $siteId);
                    if($updatedSiteId){
                        $this->deleteDefaultUrls($siteId, $siteDatas->site_main_page_id);
                    }
                }
            }
        }
    }

    /**
     * Delete the default page url
     * when change the site option language
     * so that it will re generate the correct url
     *
     * @param $siteId
     * @param $siteMainHomePageId
     */
    private function deleteDefaultUrls($siteId, $siteMainHomePageId)
    {
        $siteLangHomeTbl = $this->getServiceManager()->get('MelisEngineTableCmsSiteHome');
        $pageIds = array();

        $siteHomeDatas = $siteLangHomeTbl->getHomePageBySiteId($siteId)->toArray();
        if(!empty($siteHomeDatas)){
            foreach($siteHomeDatas as $key => $val) {
                array_push($pageIds, $val['shome_page_id']);
                $this->getAllPagesId($val['shome_page_id'], $pageIds);
            }
        }else{
            array_push($pageIds, $siteMainHomePageId);
            $this->getAllPagesId($siteMainHomePageId, $pageIds);
        }

        $tablePageDefaultUrls = $this->getServiceManager()->get('MelisEngineTablePageDefaultUrls');
        foreach($pageIds as $key => $id){
            $tablePageDefaultUrls->deleteById($id);
        }
    }

    /**
     * Get all page ids
     *
     * @param $pageId
     * @param $pageIds
     */
    private function getAllPagesId($pageId, &$pageIds) {
        $pageTreeService = $this->getServiceManager()->get('MelisEngineTree');
        $data = $pageTreeService->getAllPages($pageId);
        foreach($data as $key => $val){
            foreach($val as $k => $v){
                //add only the page if seo url is empty
                if(empty($v['pseo_url'])) {
                    array_push($pageIds, $v['tree_page_id']);
                }

                if(!empty($v['children'])){
                    $this->getAllPagesId($v['tree_page_id'], $pageIds);
                }
            }
        }
    }

    /**
     * Save site config
     *
     * @param $siteId
     * @param $siteConfigTabData
     */
    private function saveSiteConfig($siteId, $siteConfigTabData) {
        $siteConfigTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteConfig');
        $siteName = $this->getSiteDataField($siteId, 'site_name');
        $config = $this->getSiteConfig($siteId);
        $configFromDb = $this->getSiteConfigFromDbById($siteId);
        $this->prepareDbConfigs($siteId, $siteName, $configFromDb);
        $configFromFile = $this->getSiteConfigFromFile($siteName);

        /**
         * Make sure that config is not empty
         */
        if(!empty($configFromFile)) {
            /**
             * make sure the site config exist
             */
            if(isset($configFromFile['site'][$siteName][$siteId])) {
                foreach ($configFromFile['site'][$siteName]['allSites'] as $key => $val) {
                    if (is_array($val)) {
                        foreach ($val as $vKey => $vVal) {
                            if (!is_array($vVal)) {
                                $configFromFile['site'][$siteName]['allSitesArray'][$key][$vKey] = $vVal;
                            }
                        }
                        unset($configFromFile['site'][$siteName]['allSites'][$key]);
                    }
                }

                foreach ($configFromFile['site'][$siteName][$siteId] as $locale => $lVal) {
                    foreach ($lVal as $key => $val) {
                        if (is_array($val)) {
                            foreach ($val as $vKey => $vVal) {
                                if (!is_array($vVal)) {
                                    $configFromFile['site'][$siteName][$siteId][$locale . 'Array'][$key][$vKey] = $vVal;
                                }
                            }

                            unset($configFromFile['site'][$siteName][$siteId][$locale][$key]);
                        }
                    }
                }

                foreach ($siteConfigTabData as $langKey => $langValue) {
                    $sconf_id = !empty($langValue['sconf_id']) ? $langValue['sconf_id'] : 0;
                    $result = [];

                    if (empty($langValue['config'])) {
                        $langValue['config'] = [];
                    }

                    if (empty($langValue['configArray'])) {
                        $langValue['configArray'] = [];
                    }

                    if ($langKey == 'gen') {
                        $diff = array_diff_assoc($langValue['config'], $configFromFile['site'][$siteName]['allSites']);

                        if (!empty($diff)) {
                            foreach ($diff as $key => $val) {
                                if ($val != '') {
                                    $result['allSites'][$key] = $val;
                                }
                            }
                        }

                        if (!empty($langValue['configArray'])) {
                            foreach ($langValue['configArray'] as $cKey => $cVal) {
                                $diff = array_diff_assoc($langValue['configArray'][$cKey], $configFromFile['site'][$siteName]['allSitesArray'][$cKey]);

                                foreach ($diff as $key => $val) {
                                    if ($val != '') {
                                        $result['allSites'][$cKey][$key] = $val;
                                    }
                                }
                            }
                        }
                    } else {
                        $locale = $this->getLangField(null, $siteId, $langKey, 1, 'lang_cms_locale');
                        if (array_key_exists($locale, $configFromFile['site'][$siteName][$siteId])) {
                            $diff = array_diff_assoc($langValue['config'], $configFromFile['site'][$siteName][$siteId][$locale]);
                        } else {
                            $diff = array_diff_assoc($langValue['config'], []);
                        }

                        if (!empty($diff)) {
                            foreach ($diff as $key => $val) {
                                if ($val != '') {
                                    $result[$locale][$key] = $val;
                                }
                            }
                        }

                        if (!empty($langValue['configArray'])) {
                            foreach ($langValue['configArray'] as $cKey => $cVal) {
                                if (array_key_exists($locale . 'Array', $configFromFile['site'][$siteName][$siteId])) {
                                    $diff = array_diff_assoc($langValue['configArray'][$cKey], $configFromFile['site'][$siteName][$siteId][$locale . 'Array'][$cKey]);
                                } else {
                                    $diff = array_diff_assoc($langValue['configArray'][$cKey], []);
                                }

                                foreach ($diff as $key => $val) {
                                    if ($val != '') {
                                        $result[$locale][$cKey][$key] = $val;
                                    }
                                }
                            }
                        }
                    }

                    $siteConfigTable->save(
                        [
                            'sconf_site_id' => $siteId,
                            'sconf_lang_id' => $langKey === 'gen' ? -1 : $langKey,
                            'sconf_datas' => serialize($result)
                        ],
                        $sconf_id
                    );
                }
            }
        }
    }

    /**
     * Deletes data
     * @param $siteId
     */
    private function deleteOtherTabsData($siteId, $langIds)
    {
        $siteConfigTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteConfig');
        $siteHomePageTbl = $this->getServiceManager()->get('MelisEngineTableCmsSiteHome');
        $transTextTbl = $this->getServiceManager()->get('MelisSiteTranslationTextTable');
        $transSvc = $this->getServiceManager()->get('MelisSiteTranslationService');

        foreach ($langIds as $langId) {
            $siteConfigTable->deleteConfig(null, $siteId, $langId);
            $siteHomePageTbl->deleteHomePageId(null, $siteId, $langId, null);
            $trans = $transSvc->getSiteTranslationFromDb(null, $langId, $siteId);

            foreach ($trans as $tran) {
                $transTextTbl->deleteById($tran['mstt_id']);
            }
        }
    }

    /**
     * Returns specific site field
     * @param $siteId
     * @param $field
     * @return mixed|string
     */
    private function getSiteDataField($siteId, $field)
    {
        $site = $this->getSiteDataById($siteId);
        $siteField = '';

        if (array_key_exists($field, $site)) {
            $siteField = $site[$field];
        }

        return $siteField;
    }

    private function getLangField($id, $siteId, $langId, $isActive, $field)
    {
        $lang = $this->getLang($id, $siteId, $langId, $isActive);
        $fieldData = '';

        if (!empty($lang)) {
            if (array_key_exists($field, $lang)) {
                $fieldData = $lang[$field];
            }
        }

        return $fieldData;
    }

    /**
     * Returns Language
     * @param $id
     * @param $siteId
     * @param $langId
     * @param int $isActive
     * @return mixed
     */
    private function getLang($id, $siteId, $langId, $isActive = 1)
    {
        $siteLangsTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteLangs');

        if ($langId == 'gen') {
            $langId = -1;
        }

        $lang = $siteLangsTable->getSiteLangs($id, $siteId, $langId, $isActive)->toArray();

        if (!empty($lang)) {
            $lang = $lang[0];
        }

        return $lang;
    }

    /**
     * Prepares the db config. unserialize array & form the complete config
     * @param $siteId
     * @param $siteName
     * @param $dbConfigs
     */
    private function prepareDbConfigs($siteId, $siteName, &$dbConfigs)
    {
        foreach ($dbConfigs as &$dbConfig) {
            if ($dbConfig['sconf_lang_id'] == '-1') {
                $dbConfig['sconf_datas'] = [
                    'site' => [
                        $siteName => unserialize($dbConfig['sconf_datas']),
                    ],
                ];
            } else {
                $dbConfig['sconf_datas'] = [
                    'site' => [
                        $siteName => [
                            $siteId => unserialize($dbConfig['sconf_datas'])
                        ],
                    ],
                ];
            }
        }
    }

    private function getSiteConfigFromFile($siteName)
    {
        /** @var MelisSiteConfigService $siteConfigSrv */
        $siteConfigSrv = $this->getServiceManager()->get('MelisSiteConfigService');
        return $siteConfigSrv->getConfig($siteName);
    }

    /**
     * return site config (from db only)
     * @param $siteId
     * @return mixed
     */
    private function getSiteConfigFromDbById($siteId)
    {
        $siteConfigTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteConfig');
        return $siteConfigTable->getEntryByField('sconf_site_id', $siteId)->toArray();
    }

    /**
     * returns site config (merged)
     * @param $siteId
     * @return mixed
     */
    private function getSiteConfig($siteId)
    {
        /** @var MelisSiteConfigService $siteConfigSrv */
        $siteConfigSrv = $this->getServiceManager()->get('MelisSiteConfigService');
        return $siteConfigSrv->getSiteConfig($siteId, true);
    }

    /**
     * returns site data
     * @param $siteId
     * @return array
     */
    private function getSiteDataById($siteId)
    {
        $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');
        $site = $siteTable->getEntryById($siteId)->toArray();

        return !empty($site) ? $site[0] : [];
    }

    /**
     * returns meliskey from route or from query
     * @return mixed
     */
    private function getMelisKey()
    {
        $melisKey = $this->params()->fromRoute('melisKey', $this->params()->fromQuery('melisKey'), null);

        return $melisKey;
    }

    /**
     * returns tools service
     * @return array|object
     */
    private function getTool()
    {
        $toolSvc = $this->getServiceManager()->get('MelisCoreTool');
        $toolSvc->setMelisToolKey('meliscms', 'meliscms_tool_sites');

        return $toolSvc;
    }

    /**
     * delete site domain platform
     */
    public function deleteSiteDomainPlatformAction()
    {
        $platform   = $this->params()->fromRoute('platform', $this->params()->fromQuery('platform', ''));
        $id         = $this->params()->fromRoute('id', $this->params()->fromQuery('id', ''));
        $success    = (int) $this->params()->fromRoute('success', $this->params()->fromQuery('success', ''));

        if($success == 1) {
            $domainTable = $this->getServiceManager()->get('MelisEngineTableSiteDomain');
            $platformIdTable = $this->getServiceManager()->get('MelisEngineTablePlatformIds');

            $platformIdTable->deleteByField('pids_id', $id);
            $domainTable->deleteByField('sdom_env', $platform);
        }
    }

    /**
     * Regenerate modules path
     *
     * @return bool
     */
    private function regenerateModulesPath()
    {
       $file = $_SERVER['DOCUMENT_ROOT'] . "/../config/melis.modules.path.php";
       if (file_exists($file)) {
           unlink($file);
           return true;
       }
    }

    /**
     * Clear Config Cache
     */
    private function clearSiteConfigCache($siteId)
    {
        //keys need to remove
        $cacheKeys = [
            'getSiteConfig_'.$siteId,
            'getSiteConfigByPageId',
            //module cache
            'getVendorModulesEngine',
            'getComposerModulePathEngine_'
        ];

        $cacheConfig = 'meliscms_page';
        $melisEngineCacheSystem = $this->getServiceManager()->get('MelisEngineCacheSystem');
        foreach($cacheKeys as $preFix)
            $melisEngineCacheSystem->deleteCacheByPrefix($preFix, $cacheConfig);
    }
}
