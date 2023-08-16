<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisFront\Navigation;

use Laminas\Navigation\Service\DefaultNavigationFactory;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;

/**
 * Generate laminas navigation based on Melis Page System
 *
 */
class MelisFrontNavigation extends DefaultNavigationFactory
{
	private $serviceManager;
	private $idpage;
	private $renderMode;
	
	/**
	 * Constructor
	 * 
	 * @param ServiceManager $$this->getServiceManager()
	 * @param int $idpage
	 * @param string $renderMode
	 */
	public function __construct(ServiceManager $serviceManager, $idpage, $renderMode)
	{
		$this->setServiceManager($serviceManager);
		$this->idpage = $idpage;
		$this->renderMode = $renderMode;
	}

    /**
     * @param ServiceManager $serviceManager
     */
	public function setServiceManager(ServiceManager $serviceManager)
	{
		$this->serviceManager = $serviceManager;
	}

    /**
     * @return $serviceManager
     */
	public function getServiceManager()
	{
		return $this->serviceManager;
	}
	
	public function getPageAndSubPages($pageId)
	{
		$melisPage = $this->getServiceManager()->get('MelisEnginePage');
		$pageTree = $melisPage->getDatasPage($pageId);
		
		$pages = array();
		
		if (!is_null($pageTree))
		{
			$page = $this->formatPageInArray((Array)$pageTree->getMelisPageTree());
			
			$children = $this->getChildrenRecursive($pageId);
			if (!empty($children))
			{
				$page['pages'] = $children;
			}
			
			if ($page)
			{
				$pages[] = $page;
			}
		}
		
		return $pages;
	}
	
	/**
	 * Get subpages recursively
	 * 
	 * @param int $idPage
	 * @return array Pages
	 */
	public function getChildrenRecursive($idPage)
	{
		$results = array();
		$melisTree = $this->getServiceManager()->get('MelisEngineTree');
		
		$publishedOnly = 1;
		$pages = $melisTree->getPageChildren($idPage,$publishedOnly);

		if ($pages)
		{
			foreach ($pages as $page)
			{
				$tmp = $this->formatPageInArray($page);
				$children = $this->getChildrenRecursive($page['tree_page_id']);

				if (!empty($children))
					$tmp['pages'] = $children;
				
				$results[] = $tmp;
			}
		}
		
		return $results;
	}
	
	public function formatPageInArray($page,$pageSearchType = null)
	{
		$melisTree = $this->getServiceManager()->get('MelisEngineTree');

		if (empty($page) || empty($page['tree_page_id']))
			return null;
		
		if (empty($page['purl_page_url']))
			$uri = $melisTree->getPageLink($page['tree_page_id'], 0);
		else
			$uri = $page['purl_page_url'];
		
		if (empty($page['page_edit_date']))
			$page['page_edit_date'] = date('Y-m-d H:i:s');

			$pageName = $page['page_name'];
			
		$tmp = array(
			'label' => $pageName,
			'menu' => $page['page_menu'],
			'uri' => $uri,
			'idPage' => $page['tree_page_id'],
			'lastEditDate' => $page['page_edit_date'],
			'pageStat' => $page['page_status'],
			'pageType' => $page['page_type'],
			'pageSearchType' => $pageSearchType,
		);
		
		if ($this->idpage == $page['tree_page_id'])
			$tmp['active'] = true;
		
		return $tmp;
	}
	
	public function getSiteMainPageByPageId($idPage)
	{
		$melisTree = $this->getServiceManager()->get('MelisEngineTree');
		$datasSite = $melisTree->getSiteByPageId($idPage);
		
		if (!empty($datasSite))
		return $datasSite->site_main_page_id;
		
		return null;
	}
	
	/**
	 * Get Pages
	 * 
	 * @param ContainerInterface $container
	 * 
	 * {@inheritDoc}
	 * @see \Laminas\Navigation\Service\AbstractNavigationFactory::getPages()
	 */
	protected function getPages(ContainerInterface $container)
	{
		if (null === $this->pages) 
		{
			$siteMainId = 0;
			
			$melisPage = $this->getServiceManager()->get('MelisEnginePage');
			$actualPage = $melisPage->getDatasPage($this->idpage);
			if ($actualPage)
			{
				$siteId = 0;
				$datasTemplate = $actualPage->getMelisTemplate();
				if (!empty($datasTemplate->tpl_site_id))
					$siteId = $datasTemplate->tpl_site_id;
				
				if (!empty($siteId) && $siteId > 0)
				{
					$melisTableSite = $this->getServiceManager()->get('MelisEngineTableSite');
					$datasSite = $melisTableSite->getSiteById($siteId, getenv('MELIS_PLATFORM'));
					if (!empty($datasSite))
					{
						$datasSite = $datasSite->toArray();
						if (count($datasSite) > 0)
							$siteMainId = $datasSite[0]['site_main_page_id'];
					}
				}
			}

			$navigation = $this->getChildrenRecursive($siteMainId);
			
			$pages      = $this->getPagesFromConfig($navigation);
	
			$this->pages = $this->injectComponents(
					$pages
			);
		}
		
		return $this->pages;
	}

	/**
	 * Get all Subpages including published and unplublished
	 * @param $pageId
	 * @return array
	 */
	public function getAllSubpages($pageId)
	{
		$results = array();
		//Services
		$melisTree = $this->getServiceManager()->get('MelisEngineTree');
		$pagePub   = $this->getServiceManager()->get('MelisEngineTablePagePublished');
		$pageSave  = $this->getServiceManager()->get('MelisEngineTablePageSaved');

		$pageSearchType = null;
		$pages = $melisTree->getPageChildren($pageId,2);
		
		if($pages)
		{
			foreach ($pages as $page)
			{
				$pageStat = $page['page_status'] ?? null;
				//if the page is published
				if($pageStat){
					$pageData       = $pagePub->getEntryById($page['tree_page_id'])->current();
					$pageSearchType = $pageData->page_search_type ?? null;
				}
				//if the page is unpublished
				else{
					$pageData = $pageSave->getEntryById($page['tree_page_id'])->current();
					//if the unpublishedData is not present in page_saved table
					if(!$pageData){
						//Get the pageData in page_published table
						$pageData = $pagePub->getEntryById($page['tree_page_id'])->current();
					}
					$pageSearchType = $pageData->page_search_type ?? null;
				}

				$tmp = $this->formatPageInArray($page,$pageSearchType);
				$children = $this->getAllSubpages($page['tree_page_id'] ?? null);

				if (!empty($children))
					$tmp['pages'] = $children;

				$results[] = $tmp;
			}
		}
		return $results;
	}
}