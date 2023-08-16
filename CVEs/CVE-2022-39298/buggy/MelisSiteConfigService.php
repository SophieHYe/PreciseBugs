<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisFront\Service;

use MelisCore\Service\MelisGeneralService;
use MelisEngine\Service\MelisEngineComposerService;
use Laminas\Stdlib\ArrayUtils;

class MelisSiteConfigService extends MelisGeneralService
{

    /**
     * Function to get the site config by key
     *
     * @param $key
     * @param string $section
     * @param int $pageId
     * @param null $language
     * @return array|string
     */
    public function getSiteConfigByKey($key, $pageId, $section = 'sites', $language = null)
    {
        if(empty($section))
            $section = 'sites';

        /**
         * check if we are getting it from the current site config
         * or from the allSites
         */
        if ($section == 'sites' || $section == 'allSites') {
            $siteConfigData = $this->getSiteConfigByPageId($pageId);
            if ($section == 'sites') {
                if (empty($language)) {
                    //return the value if the given key
                    return (isset($siteConfigData['siteConfig'][$key])) ? $siteConfigData['siteConfig'][$key] : null;
                } else {
                    //return the given key value from its specific language
                    $langLocale = strtolower($language) . '_' . strtoupper($language);
                    $siteConfigData = $this->getSiteConfigByPageId($pageId, $langLocale);
                    return (isset($siteConfigData['siteConfig'][$key])) ? $siteConfigData['siteConfig'][$key] : null;
                }
            } else {
                //return given key value from allSites
                return (isset($siteConfigData['allSites'][$key])) ? $siteConfigData['allSites'][$key] : null;
            }
        } else {
            $siteConfigData = $this->getSiteConfig($section);
            $data = [];
            foreach ($siteConfigData as $locale => $value) {
                $data[$locale] = array($key => $value[$key]);
            }
            if (empty($language))
                return $data;
            else {
                $langLocale = strtolower($language) . '_' . strtoupper($language);
                return $data[$langLocale][$key];
            }
        }
    }

    /**
     * Function to return site config by page id
     *
     * @param $pageId
     * @param $langLocale - ex: en_EN, fr_FR
     * @return array
     */
    public function getSiteConfigByPageId($pageId, $langLocale = false)
    {
        // Event parameters prepare
        $arrayParameters = $this->makeArrayFromParameters(__METHOD__, func_get_args());
        // Sending service start event
        $arrayParameters = $this->sendEvent('meliscms_site_tool_get_site_config_by_page_id_start', $arrayParameters);

        //try to get config from cache
        $cacheKey = 'getSiteConfigByPageId_' . $pageId.'_'.$langLocale;
        $cacheConfig = 'meliscms_page';
        $melisEngineCacheSystem = $this->getServiceManager()->get('MelisEngineCacheSystem');
        $results = $melisEngineCacheSystem->getCacheByKey($cacheKey, $cacheConfig);

        if(empty($results)) {

            $siteConfig = array(
                'siteConfig' => array(),
                'allSites' => array(),
            );

            if (!empty($pageId)) {
                /**
                 * get the language if the page
                 */
                $cmsPageLang = $this->getServiceManager()->get('MelisEngineTablePageLang');
                $pageLang = $cmsPageLang->getEntryByField('plang_page_id', $arrayParameters['pageId'])->current();
                /**
                 * get page lang locale
                 */
                $langCmsSrv = $this->getServiceManager()->get('MelisEngineLang');
                $langData = array();
                $langId = null;
                if (!empty($pageLang)) {
                    $langData = $langCmsSrv->getLangDataById($pageLang->plang_lang_id);
                }
                /**
                 * get the site config
                 */
                if (!empty($langData)) {
                    $siteData = $this->getSiteDataByPageId($arrayParameters['pageId']);
                    if (!empty($siteData)) {
                        $siteId = $siteData->site_id;
                        $siteName = $siteData->site_name;

                        /**
                         * First, let's try fetch the site config
                         * using the config service that has been updated
                         * through a listener
                         */
                        $config = $this->getServiceManager()->get('config');

                        if (!empty($config['site'])) {
                            /**
                             * If site config not in the list,
                             * let's try again to get it directly from the
                             * db and file
                             */
                            if (empty($config['site'][$siteName][$siteId])) {
                                $config = $this->getSiteConfig($siteId, true);
                            }

                            if (!empty($config['site'][$siteName][$siteId])) {

                                if ($arrayParameters['langLocale']) {
                                    $siteConfig['siteConfig'] = $config['site'][$siteName][$siteId][$arrayParameters['langLocale']];
                                    $siteLangData = $langCmsSrv->getLangDataByLangLocale($arrayParameters['langLocale']);
                                    if (!empty($siteLangData)) {
                                        $langId = $siteLangData->lang_cms_id;
                                    }
                                } else {
                                    foreach($langData as $val) {
                                        $siteConfig['siteConfig'] = $config['site'][$siteName][$siteId][$val['lang_cms_locale']];
                                        $langId = $val['lang_cms_id'];
                                    }
                                }
                                $siteConfig['siteConfig']['site_id'] = $siteId;
                                $siteConfig['siteConfig']['default_lang_id'] = $langId;
                                $siteConfig['allSites'] = $config['site'][$siteName]['allSites'];
                            }
                        }
                    }
                }
            }

            $arrayParameters['result'] = $siteConfig;
            // Save cache key
            $melisEngineCacheSystem->setCacheByKey($cacheKey, $cacheConfig, $arrayParameters['result']);
        }else{
            //return the config from cache
            $arrayParameters['result'] = $results;
        }

        $arrayParameters = $this->sendEvent('meliscms_site_tool_get_site_config_by_page_id_end', $arrayParameters);

        return $arrayParameters['result'];
    }

    /**
     * Returns Merged Site Config (File and DB)
     * @param $siteId
     * @param $returnAll
     * @return array
     */
    public function getSiteConfig($siteId, $returnAll = false)
    {
        // Event parameters prepare
        $arrayParameters = $this->makeArrayFromParameters(__METHOD__, func_get_args());
        // Sending service start event
        $arrayParameters = $this->sendEvent('meliscms_site_tool_get_site_config_start', $arrayParameters);
        $siteId = $arrayParameters['siteId'];

        //try to get config from cache
        $cacheKey = 'getSiteConfig_' . $siteId.'_'.$returnAll;
        $cacheConfig = 'meliscms_page';
        $melisEngineCacheSystem = $this->getServiceManager()->get('MelisEngineCacheSystem');
        $results = $melisEngineCacheSystem->getCacheByKey($cacheKey, $cacheConfig);

        if(empty($results)) {

            $site = $this->getSiteDataById($siteId);
            $siteName = $site['site_name'];
            $configFromFile = $this->getConfig($siteName);
            $siteConfig = [];

            if (array_key_exists('site', $configFromFile)) {
                $dbConfigData = $this->getSiteConfigFromDb($siteId);
                // merge config from file and from the db | the one on the db will be prioritized
                $siteConfig = ArrayUtils::merge($siteConfig, $configFromFile, true);
                /**
                 * Make sure that we are accessing the correct config
                 */
                if (isset($siteConfig['site'][$siteName][$siteId])) {
                    $activeSiteLangs = $this->getSiteActiveLanguages($siteId);

                    // add langauges that are active but not on the config file
                    foreach ($activeSiteLangs as $lang) {
                        if (!array_key_exists($lang['lang_cms_locale'], $siteConfig['site'][$siteName][$siteId])) {
                            $siteConfig['site'][$siteName][$siteId][$lang['lang_cms_locale']] = [];
                        }
                    }

                    // also merge all language config (except the general one) because some variables could be defined in one
                    // one language but not on the other
                    if (!empty($siteConfig['site'][$siteName][$siteId])) {
                        foreach ($siteConfig['site'][$siteName][$siteId] as $langConfigKey => $langConfigVal) {
                            foreach ($siteConfig['site'][$siteName][$siteId] as $otherLangConfigKey => $otherLangConfigVal) {
                                if ($langConfigKey !== $otherLangConfigKey) {
                                    foreach ($otherLangConfigVal as $configKey => $configValue) {
                                        if (!array_key_exists($configKey, $siteConfig['site'][$siteName][$siteId][$langConfigKey])) {
                                            if (is_array($configValue)) {
                                                $arr = [];

                                                foreach ($configValue as $key => $val) {
                                                    if (!is_array($val)) {
                                                        $arr[$key] = '';
                                                    }
                                                }

                                                $siteConfig['site'][$siteName][$siteId][$langConfigKey] = ArrayUtils::merge($siteConfig['site'][$siteName][$siteId][$langConfigKey], [$configKey => $arr], true);
                                            } else {
                                                $siteConfig['site'][$siteName][$siteId][$langConfigKey] = ArrayUtils::merge($siteConfig['site'][$siteName][$siteId][$langConfigKey], [$configKey => ''], true);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($dbConfigData)) {
                        foreach ($dbConfigData as $dbConf) {
                            if ($dbConf['sconf_lang_id'] == '-1') {
                                $siteConfig = ArrayUtils::merge(
                                    $siteConfig,
                                    [
                                        'site' => [
                                            $siteName => unserialize($dbConf['sconf_datas'])
                                        ],
                                    ],
                                    true
                                );
                            } else {
                                $siteConfig = ArrayUtils::merge(
                                    $siteConfig,
                                    [
                                        'site' => [
                                            $siteName => [
                                                $siteId => unserialize($dbConf['sconf_datas'])
                                            ],
                                        ]
                                    ],
                                    true
                                );
                            }
                        }
                    }

                    $arrayParameters['config'] = ($arrayParameters['returnAll']) ? $siteConfig : $siteConfig['site'][$siteName][$siteId];
                } else {
                    $arrayParameters['config'] = [];
                }
            } else {
                $arrayParameters['config'] = [];
            }
            // Save cache key
            $melisEngineCacheSystem->setCacheByKey($cacheKey, $cacheConfig, $arrayParameters['config']);
        }else{
            //return the config from cache
            $arrayParameters['config'] = $results;
        }

        $arrayParameters = $this->sendEvent('meliscms_site_tool_get_site_config_end', $arrayParameters);
        return $arrayParameters['config'];
    }

    /**
     * Returns Config From File
     * @param $siteName
     * @return mixed
     */
    public function getConfig($siteName)
    {
        /** @var MelisEngineComposerService $composerSrv */
        $composerSrv  = $this->getServiceManager()->get('MelisEngineComposer');
        $config = [];

        if (!empty($composerSrv->getComposerModulePath($siteName))) {
            $modulePath = $composerSrv->getComposerModulePath($siteName);
        } else {
            $modulePath = $_SERVER['DOCUMENT_ROOT'] . '/../module/MelisSites/' . $siteName;
        }

        if (file_exists($modulePath . '/config/' . $siteName . '.config.php')) {
            $config = include $modulePath . '/config/' . $siteName . '.config.php';
        }

        return $config;
    }

    /**
     * Returns Site Config From DB
     * @param $siteId
     * @return mixed
     */
    private function getSiteConfigFromDb($siteId)
    {
        $siteConfigTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteConfig');
        return $siteConfigTable->getEntryByField('sconf_site_id', $siteId)->toArray();
    }

    /**
     * Returns Site Active Languages
     * @param $siteId
     * @return mixed
     */
    private function getSiteActiveLanguages($siteId)
    {
        $siteLangsTable = $this->getServiceManager()->get('MelisEngineTableCmsSiteLangs');
        return $siteLangsTable->getSiteLangs(null, $siteId, null, true)->toArray();
    }

    /**
     * Returns Site Data
     * @param $siteId
     * @return mixed
     */
    private function getSiteDataById($siteId)
    {
        $siteTable = $this->getServiceManager()->get('MelisEngineTableSite');
        return $siteTable->getEntryById($siteId)->toArray()[0];
    }

    /**
     * Function to get the site data
     * using the page id
     *
     * @param $pageId
     * @return array|object
     */
    private function getSiteDataByPageId($pageId)
    {
        $siteData = [];
        $siteId = 0;

        $pageSaved = $this->getServiceManager()->get('MelisEngineTablePageSaved');
        $pagePublished = $this->getServiceManager()->get('MelisEngineTablePagePublished');
        $tplSrv = $this->getServiceManager()->get('MelisEngineTemplateService');

        if(!empty($pageId)){
            /**
             * check first if there is data on page saved
             */
            $pageSavedData = $pageSaved->getEntryById($pageId)->current();
            if(!empty($pageSavedData)){
                $tplId = $pageSavedData->page_tpl_id;
            }else{
                //try to get the data from the page published
                $pagePublishedData = $pagePublished->getEntryById($pageId)->current();
                $tplId = $pagePublishedData->page_tpl_id;
            }

            if(!empty($tplId)){
                $tplData = $tplSrv->getTemplate($tplId)->current();
                if(!empty($tplData)){
                    $siteId = $tplData->tpl_site_id;
                }
            }
        }

        if(!empty($siteId)){
            $siteSrv = $this->getServiceManager()->get('MelisEngineSiteService');
            $siteData = $siteSrv->getSiteById($siteId)->current();
        }

        return $siteData;
    }
}