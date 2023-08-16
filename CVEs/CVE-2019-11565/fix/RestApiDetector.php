<?php

namespace mnelson4\RestApiDetector;
use WP_Error;

/**
 * Class RestApiDetector
 *
 * Finds the REST API base URL for the site requested. Works with both self-hosted sites and WordPress.com sites.
 *
 *
 * @package     Event Espresso
 * @author         Mike Nelson
 * @since         $VID:$
 *
 */
class RestApiDetector
{
    protected $site;
    protected $name;
    protected $description;
    protected $rest_api_url;
    protected $local;
    protected $initialized = false;

    /**
     * RestApiDetector constructor.
     * @param $site
     * @throws RestApiDetectorError
     */
    public function __construct($site)
    {
        $this->setSite($this->sanitizeSite($site));
        $this->getSiteInfo();
    }

    /**
     * Gets the site name and URL (works if they provide the "site" query param too,
     * being the URL, including schema, of a self-hosted or WordPress.com site)
     * @since $VID:$
     * @throws RestApiDetectorError
     */
    public function getSiteInfo()
    {
        // check for a site request param
        if(empty($this->getSite())){
            $this->setName(get_bloginfo('name'));
            $this->setDescription(get_bloginfo('description'));
            $this->setRestApiUrl(get_rest_url());
            $this->setSite(get_bloginfo('url'));
            $this->setLocal(true);
            return;
        }
        $site = $this->getSite();
        // Let's see if it's self-hosted...
        $data = $this->getSelfHostedSiteInfo($site);
//        if($data === false){
//            // Alright, there was no link to the REST API index. But maybe it's a WordPress.com site...
//            $data = $this->guessSelfHostedSiteInfo($site);
//        }
        if($data === false){
            // Alright, there was no link to the REST API index. But maybe it's a WordPress.com site...
            $data = $this->getWordPressComSiteInfo($site);
        }

        return $data;
    }

    /**
     * Avoid SSRF by sanitizing the site received.
     * @since $VID:$
     * @param $site
     * @return mixed|string
     */
    protected function sanitizeSite($site)
    {
        // If the REST API Proxy Plugin isn't active, always use the current site.
        if(! PMB_REST_PROXY_EXISTS){
            return '';
        }
        // If they forgot to add http(s), add it for them.
        if(strpos($site, 'http://') === false && strpos($site, 'https://') === false) {
            $site = 'http://' . $site;
        }
        // if there is one, check if it exists in wordpress.com, eg "retirementreflections.com"

        $file_info = pathinfo($site);
        if( isset($file_info['extension'])){
            $site = str_replace($file_info['filename'] . "." . $file_info['extension'], "", $site);
        }
        $site = trailingslashit(sanitize_text_field($site));
        return $site;
    }

    /**
     * Tries to get the site's name, description, and URL, assuming it's self-hosted.
     * Returns a true on success, false if the site works but wasn't a self-hosted WordPress site, or
     * throws an exception if the site is self-hosted WordPress but had an error.
     * @since $VID:$
     * @param $site
     * @return bool false if the site exists but it's not a self-hosted WordPress site.
     * @throws RestApiDetectorError
     */
    protected function getSelfHostedSiteInfo($site){
        $response = $this->sendHttpGetRequest($site);
        if (is_wp_error($response)) {
            throw new RestApiDetectorError($response);
        }
        $response_body = wp_remote_retrieve_body($response);
        $matches = array();
        if( ! preg_match(
            //looking for somethign like "<link rel='https://api.w.org/' href='http://wpcowichan.org/wp-json/' />"
                '<link rel=\'https\:\/\/api\.w\.org\/\' href=\'(.*)\' \/>',
                $response_body,
                $matches
            )
            ||  count($matches) !== 2) {
            // The site exists, but it's not self-hosted.
            return false;
        }
        // grab from site index
        $success = $this->fetchWpJsonRootInfo($matches[1]);
        if($success){
            $this->setRestApiUrl($matches[1] . 'wp/v2/');
        }
        return $success;
    }

    protected function fetchWpJsonRootInfo($wp_api_url) {
        $response = $this->sendHttpGetRequest($wp_api_url);
        if (is_wp_error($response)) {
            // The WP JSON index existed, but didn't work. Let's tell the user.
            throw new RestApiDetectorError($response);
        }
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body,true);
        if (! is_array($response_data)) {
            throw new RestApiDetectorError(
                new WP_Error('no_json', __('The WordPress site has an error in its REST API data.', 'print-my-blog'))
            );
        }
        if (isset($response_data['code'], $response_data['message'])) {
            throw new RestApiDetectorError(
                new WP_Error($response_data['code'], $response_data['message'])
            );
        }
        if(isset($response_data['name'], $response_data['description'])){
            $this->setName($response_data['name']);
            $this->setDescription($response_data['description']);
            $this->setLocal(false);
            return true;
        }
        // so we didn't get an error or a proper response, but it's JSON? That's really weird.
        throw new RestApiDetectorError(
            new WP_Error('unknown_response', __('The WordPress site responded with an unexpected response.', 'print-my-blog'))
        );
    }

    /**
     * We didn't see any indication the website has the WP API enabled. Just take a guess that
     * /wp-json is the REST API base url. Maybe we'll get lucky.
     * @since $VID:$
     * @param $site
     * @return bool
     * @throws RestApiDetectorError
     */
    protected function guessSelfHostedSiteInfo($site){
        // add /wp-json as a guess
        return $this->fetchWpJsonRootInfo($site . 'wp-json');
        // and if it responds with valid JSON, it's ok
    }

    /**
     * Tries to get the site name, description and URL from a site on WordPress.com.
     * Returns true success, or throws a RestApiDetectorError. If the site doesn't appear to be on WordPress.com
     * also has an error.
     * @since $VID:$
     * @param $site
     * @return bool
     * @throws RestApiDetectorError
     */
    protected function getWordPressComSiteInfo($site){
        $domain = str_replace(array('http://','https://'),'',$site);

        $success =  $this->fetchWpJsonRootInfo(
            'https://public-api.wordpress.com/rest/v1.1/sites/' . $domain
        );
        if($success){
            $this->setRestApiUrl('https://public-api.wordpress.com/wp/v2/sites/' . $domain);
        }
        return $success;
    }

    /**
     * @since $VID:$
     * @param $url
     * @return array|WP_Error
     */
    protected function sendHttpGetRequest($url)
    {
        return wp_remote_get(
            $url,
            [
                'timeout' => 30,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:66.0) Gecko/20100101 Firefox/66.0'
            ]
        );
    }

    /**
     * @return string
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    protected function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param mixed $site
     */
    protected function setSite($site)
    {
        $this->site = $site;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    protected function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getRestApiUrl()
    {
        return $this->rest_api_url;
    }

    /**
     * @param mixed $rest_api_url
     */
    protected function setRestApiUrl($rest_api_url)
    {
        $this->rest_api_url = $rest_api_url;
    }

    /**
     * @return mixed
     */
    public function isLocal()
    {
        return $this->local;
    }

    /**
     * @param mixed $local
     */
    protected function setLocal($local)
    {
        $this->local = $local;
    }

    /**
     * @return bool
     */
    protected function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * @param bool $initialized
     */
    protected function setInitialized(bool $initialized)
    {
        $this->initialized = $initialized;
    }
}
// End of file RestApiDetector.php
// Location: mnelson4/RestApiDetector.php
