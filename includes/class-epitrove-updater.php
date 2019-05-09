<?php

namespace Licensing;

use Licensing\Entities\EpitroveProduct;

/*
 * Allows plugins to use their own update API.
 */
if (!class_exists('Licensing\EpitroveUpdater')) {
    /**
     * Class EpitroveUpdater
     */
    class EpitroveUpdater
    {
        private $name = '';
        private $updateCacheKey = '';
        private $product;
        private $isForcedUpdateCheck = false;

        /**
         * Class constructor.
         *
         * @uses plugin_basename()
         * @uses hook()
         *
         * @param string $activationEmail  The email associated with the license
         * @param EpitroveProduct $product  Product Object
         */
        public function __construct(EpitroveProduct $product)
        {
            $this->product = $product;

            if ($product->isTheme()) {
                $this->productType = 'theme';
                $this->name = $product->productSlug();
            } else {
                $this->productType = 'plugin';
                $this->name = $product->pluginBaseName();
            }

            //If update-core.php page, then forcefully send request to server to check for updates
            if(false !== strpos(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_BASENAME), 'update-core.php')){
                $this->isForcedUpdateCheck = true;
            }

            $this->updateCacheKey = md5(serialize($this->product->productSlug() . $this->product->licenseKey() . '_update'));

            // Set up hooks.
            $this->hook();
        }

        /**
         * Set up Wordpress filters to hook into WP's update process.
         *
         * @uses add_filter()
         */
        private function hook()
        {
            if ($this->productType == 'theme') {
                add_filter('pre_set_site_transient_update_themes', array($this, 'checkForProductUpdate'));
                add_filter('pre_set_transient_update_themes', array($this, 'checkForProductUpdate'));
            } elseif ($this->productType == 'plugin') {
                add_filter('pre_set_site_transient_update_plugins', array($this, 'checkForProductUpdate'));
                add_filter('pre_set_transient_update_plugins', array($this, 'checkForProductUpdate'));
                remove_action( 'after_plugin_row_' . $this->name, 'wp_plugin_update_row', 10 );
            }

            add_filter('http_request_args', array($this, 'whitelistEpitroveUrls'), 10, 2);
        }

        /**
         * Preventing WordPress from messing with download urls of product updates
         *
         * @param [type] $unused
         * @param array $requestArgs Request Arguments used to fetch url
         * @param string $url Link to download
         * @return void
         */
        public function whitelistEpitroveUrls($requestArgs, $url){
            
            if(strpos($url, EPITROVE_WEBSITE_URL) !== false){
                $requestArgs['reject_unsafe_urls'] = false;
            }

            return $requestArgs;
        }

        /**
         * Check for Updates at the defined API endpoint and modify the update array.
         *
         * This function dives into the update api just when Wordpress creates its update array,
         * then adds a custom API call and injects the custom plugin data retrieved from the API.
         * It is reassembled from parts of the native Wordpress plugin update code.
         * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
         *
         * @uses apiRequest()
         *
         * @param array $transientData Update array build by Wordpress.
         *
         * @return array Modified update array with custom plugin data.
         */
        public function checkForProductUpdate($transientData)
        {
            global $pagenow;

            if(empty($this->name)){
                return $transientData;
            }

            if (!is_object($transientData)) {
                $transientData = new \stdClass();
            }

            if ('plugins.php' == $pagenow && is_multisite()) {
                return $transientData;
            }

            // Probably update for this product is found on wp.org
            if (!empty($transientData->response) && !empty($transientData->response[ $this->name ])) {
                if (false == $this->product->wpOverride()) {
                    return $transientData;
                }

                // Unset the update for this product
                unset($transientData->response[ $this->name ]);
            }


            return $this->getUpdateInfo($transientData);
        }

        /**
         * Retrieves the details of available update
         *
         * @param array $transientData Update array built by Wordpress.
         * @return array Transient with new update's details
         */
        public function getUpdateInfo($transientData)
        {
            $updateDetails = $this->getCachedUpdateInfo();
            if (false === $updateDetails || $this->isForcedUpdateCheck) {
                $updateApiResponse = $this->apiRequest(
                    array(
                        'slug'       =>  $this->product->productSlug(),
                        'endpoint'   =>  'updateDownload'
                    )
                );

                if (isset($updateApiResponse->data)) {
                    $updateDetails = $updateApiResponse->data;
                    $this->setUpdateInfoCache($updateDetails);
                }
            }

            if (isset($updateDetails->new_version) && version_compare($this->product->productVersion(), $updateDetails->new_version, '<')) {
                $transientData->response[ $this->name ] = $this->product->isTheme() ? $this->convertObjectToArray($updateDetails) : $updateDetails;
            }
            
            return $transientData;
        }

        /**
         * Calls the API and, if successfull, returns the object delivered by the API.
         *
         * @uses get_bloginfo()
         * @uses wp_remote_get()
         * @uses is_wp_error()
         *
         * @param string $action The requested action.
         * @param array  $data   Parameters for the API action.
         *
         * @return false||object
         */
        private function apiRequest($data)
        {

            if ($data['slug'] != $this->product->productSlug() || empty($this->product->licenseKey())) {
                return;
            }

            $request = wp_remote_post(
                EpitroveLicense::apiUrl($data['endpoint']),
                array(
                    'method'        => 'POST',
                    'timeout'       => 45,
                    'redirection'   => 5,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => array(),
                    'body'          => EpitroveLicense::prepareApiData($this->product, $this->product->licenseKey())
                )
            );
            
            if (!is_wp_error($request)) {
                $response = json_decode(wp_remote_retrieve_body($request));

                if(isset($response->code) && EPITROVE_API_SUCCESS_CODE == $response->code){
                    return $response;
                }
            }
           
            // Return blank object
            return new \stdClass();
        }


        /**
         * Returns the information stored about new update from cache
         *
         * @param string $cacheKey Key against which the information of new update is saved.
         * @return mixed
         */
        public function getCachedUpdateInfo()
        {
 
            $cache = get_option($this->updateCacheKey);

            if (empty($cache['timeout']) || current_time('timestamp') > $cache['timeout']) {
                return false; // Cache is expired
            }

            $updateDetails = json_decode($cache['value']);

            if (false === $updateDetails || empty($updateDetails) || !isset($updateDetails->package) || empty($updateDetails->package)) {
                return false;
            }

            return $updateDetails;
        }

        /**
         * Stores the information about new update in the cache
         *
         * @param string $value Information related to new update
         * @param string $cacheKey Key against which the information needs to be saved
         * @return void
         */
        public function setUpdateInfoCache($value = '')
        {
            $data = array(
                'timeout' => strtotime('+6 hours', current_time('timestamp')),
                'value' => json_encode($value),
            );
            update_option($this->updateCacheKey, $data);
        }

        /**
         * Converts the object to Associative array
         *
         * @param Object $object
         * @return array
         */
        public function convertObjectToArray($object){
            return json_decode(json_encode($object), true);
        }
    }
}
