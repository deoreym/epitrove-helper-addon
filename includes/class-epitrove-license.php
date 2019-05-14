<?php
namespace Licensing;
use Licensing\Entities\EpitroveProduct;

if (!class_exists('Licensing\EpitroveLicense')) {
    /**
     * EpitroveLicense
     */
    class EpitroveLicense
    {
        private $epitroveProducts = [];
        public $pluginSlug = null;
        private $responseStatus = [];

        public function __construct()
        {
            $this->pluginSlug = 'epitrove-helper';

            $this->defineEpiConstants();

            add_action('after_setup_theme', array($this, 'checkEpitroveProductUpdates'));

            add_action('admin_menu', array($this, 'addLicenseMenu'));

            add_action('admin_enqueue_scripts', array($this, 'enqueueLicenseStyles'));

        }

        /**
         * Defines constant required for Epitrove Helper Addon
         *
         * @return void
         */
        public function defineEpiConstants()
        {
            //Constants
            if (!defined('EPITROVE_CONFIG_FILE')) {
                define('EPITROVE_CONFIG_FILE', 'epitrove-config.php');
            }

            if (!defined('REGISTERED_EMAIL_KEY')) {
                define('REGISTERED_EMAIL_KEY', 'epi_registered_email');
            }

            if (!defined('LICENSING_URL')) {
                define('LICENSING_URL', 'https://api.epitrove-uat.wisdmlabs.net/v1');
            }

            if(!defined('EPITROVE_WEBSITE_URL')){
                define('EPITROVE_WEBSITE_URL', 'https://epitrove-uat.wisdmlabs.net');
            }

            if(!defined('EPITROVE_API_SUCCESS_CODE')){
                define('EPITROVE_API_SUCCESS_CODE', 200);
            }

        }

        /**
         * Triggers Product Updater Class
         *
         * @return mixed
         */
        public function checkEpitroveProductUpdates()
        {
            $epitroveProducts = $this->getAllEpitroveProducts();
            
            if (empty($epitroveProducts)) {
                return;
            }

            require_once 'class-epitrove-updater.php';

            if (! class_exists('\Licensing\EpitroveUpdater')) {
                error_log("Updater Class Not Found");
                return;
            }

            $registered_email = self::getRegisteredEmail();

            if (empty($registered_email)) {
                error_log("No registered email found");
                return;
            }

            foreach ($epitroveProducts as $product) {
                if ($product->isActiveLicense()) {
                    new \Licensing\EpitroveUpdater($product);
                }
            }
        }


        /**
         * Returns list of currently active Epitrove Products
         */
        private function getAllEpitroveProducts()
        {
            static $epitroveProducts = null;

            if(is_null($epitroveProducts)){

                // Fetch all epitrove themes and plugins
                $epitrovePlugins = $this->getActiveEpitrovePlatformPlugins();
                $epitroveTheme = $this->getActiveThemeIfBelongsToEpitrove();
                $epitroveProducts = array_filter(array_merge($epitrovePlugins, $epitroveTheme));
                $this->epitroveProducts = $epitroveProducts;

            }

            return $this->epitroveProducts;
        }

        /**
         * Returns the list of plugins belonging to Epitrove Platform
         *
         * @return array
         */
        public function getActiveEpitrovePlatformPlugins(){
            $activePlugins = get_option('active_plugins');

            if(empty($activePlugins)){
                return [];
            }

            $epitrovePlugins = [];

            foreach($activePlugins as $plugin){
                $configFile = WP_PLUGIN_DIR . '/' . pathinfo($plugin, PATHINFO_DIRNAME) . '/'. EPITROVE_CONFIG_FILE;

                if (file_exists($configFile)) {
                    $config = include_once($configFile);
                    
                    if(!is_array($config)){
                        continue;
                    }

                    $epitrovePlugins[] = new EpitroveProduct($config, $plugin);
                }
            }
            
            return $epitrovePlugins;
        }

        /**
         * Returns the information about theme if it belongs to Epitrove
         *
         * @return array Licensing Information about theme
         */
        public function getActiveThemeIfBelongsToEpitrove(){

            $configFile = locate_template(EPITROVE_CONFIG_FILE);
            if (!empty($configFile) && file_exists($configFile)) {
                $config = include_once($configFile);

                if(is_array($config)){
                    return [new EpitroveProduct($config)];
                }
            }

            return [];
        }

        /**
         * Returns the Site url of current Site
         *
         * @return void
         */
        private static function getSiteUrl(){
            return get_home_url();
        }

        /**
         * Enqueue Styles on License Management Page
         *
         * @return void
         */
        public function enqueueLicenseStyles()
        {
            wp_enqueue_style($this->pluginSlug, plugins_url('/assets/css/admin.css', __FILE__), array());
        }

        /**
         * Adds `Epitrove Licensing` Menu in the dashboard
         *
         * @return void
         */
        public function addLicenseMenu()
        {
            add_menu_page(
                __('Epitrove Licensing', $this->pluginSlug),
                __('Epitrove Licensing', $this->pluginSlug),
                'manage_options',
                $this->pluginSlug,
                array($this, 'showLicensePage')
            );
        }

        /**
         * Render view of Licensing Page
         *
         * @return void
         */
        public function showLicensePage()
        {
            $this->performLicenseActions();
            include_once 'views/view-license-page.php';
        }

        /**
         * Checks if there is any product with active license or not 
         *
         * @return boolean
         */
        public function isThereProductWithActiveLicense(){
            /**
             * We will directly query in the database instead of depending on
             * objects returned by getAllEpitroveProducts because this method
             * returns only plugins which are active. Therefore if there is any
             * plugin which is in deactivated state but active license status, then
             * that can't be captured with getAllEpitroveProducts method
             */
            global $wpdb;

            $likePattern = "'%epi_%_license_status%'";

            $licenseStatuses = array_map(function($status){
                return "'" . $status . "'";
            }, self::activeLicenseStatuses());
            $licenseStatuses = implode(',', $licenseStatuses);

            $query = "
                SELECT count('option_id')
                FROM $wpdb->options
                WHERE `option_name` LIKE $likePattern
                AND `option_value` IN ($licenseStatuses)
            ";

            return $wpdb->get_var($query) > 0 ? true : false;

        }

        /**
         * Saves Email Address in the database
         * 
         * This email address is required for API to validate the owner of the license key
         *
         * @return void
         */
        public function saveEmailAddress()
        {
            if (array_key_exists('epitrove-email-save', $_POST) && 'Save Email' === $_POST['epitrove-email-save']) {
                $registered_email = trim($_POST[REGISTERED_EMAIL_KEY]);
    
                if (empty($registered_email)) {
                    return array(
                        'type' =>  'error',
                        'message'   =>  __('Please enter valid email address', 'epitrove-licensing')
                    );
                }
    
                if (! filter_var($registered_email, FILTER_VALIDATE_EMAIL)) {
                    return array(
                        'type' =>  'error',
                        'message'   =>  __('Incorrect email format', 'epitrove-licensing')
                    );
                }
    
                update_option(REGISTERED_EMAIL_KEY, $registered_email);
    
                return array(
                    'type'  =>  'success',
                    'message'   =>  __('Registered email saved properly ', 'epitrove-licensing')
                );
            }
        }

        /**
         * Checks if the current request is to activate the license of a plugin
         *
         * @param \Licensing\Entities\EpitroveProduct $product
         * @return boolean
         */
        public function isLicenseActivateRequest(EpitroveProduct $product){
            $field = $product->fieldIdentifier('license_activate');
            return array_key_exists($field, $_POST) && 'Activate' == $_POST[$field];
        }

        /**
         * Checks if the current request is to deactivate the license of a plugin
         *
         * @param \Licensing\Entities\EpitroveProduct $product
         * @return boolean
         */
        public function isLicenseDeactivateRequest(EpitroveProduct $product){
            $field = $product->fieldIdentifier('license_deactivate');
            return array_key_exists($field, $_POST) && 'Deactivate' == $_POST[$field];
        }

        /**
         * Save Licensing Data and Perform relevant actions
         * 
         * @return mixed
         */
        public function performLicenseActions()
        {
            if (empty($_POST)) {
                return;
            }


            $epitroveProducts = $this->getAllEpitroveProducts();

            foreach ($epitroveProducts as $product) {

                if ($this->isLicenseActivateRequest($product)) {
                    $this->processActivationRequest($product);
                } 
                
                elseif ($this->isLicenseDeactivateRequest($product)) {
                    $this->processDeactivationRequest($product);
                }

            }

        }

        /**
         * Does first level of validation before sending an API request
         *
         * @param EpitroveProduct $product
         * @return mixed
         */
        protected function validateRequest(EpitroveProduct $product){
            // Get Licensing Data
            $licenseKey = trim($_POST[$product->fieldIdentifier('license_key')]);

            $registeredEmail = self::getRegisteredEmail();

            if (empty($licenseKey) || empty($registeredEmail)) {
                $this->setResponseStatus(4008, __('Incorrect license key or email data', 'epitrove-licensing'));
                return false;
            }

            return true;
        }

        /**
         * Sets Response status for the License Activation/Deactivation Request
         *
         * @param string $code Code to be set as the status
         * @param string $message Message to be shown
         * @return void
         */
        protected function setResponseStatus($code, $message){
            $this->responseStatus = (object) array('code' => $code, 'message' => $message);
        }

        /**
         * Returns the response status
         *
         * @return void
         */
        protected function getResponseStatus(){
            return $this->responseStatus;
        }

        /**
         * Prepares the data to be sent in the License Activation/Deactivation API Request
         *
         * Because this method is going to be called from EpitroveUpdater class,
         * it is kept static. Making this method static forces to make getRegisteredEmail &
         * getSiteUrl method static
         * 
         * @param EpitroveProduct $product
         * @param string $licenseKey License Key of the Product
         * @return array Data required to send api request
         */
        public static function prepareApiData(EpitroveProduct $product, $licenseKey){
            return array(
                'email'         =>  self::getRegisteredEmail(),
                'licenseKey'    =>  $licenseKey,
                'productId'     =>  $product->productId(),
                'platform'      =>  self::getSiteUrl(),
                'instance'      =>  preg_replace('#^https?://#', '', self::getSiteUrl()),
                'version'       =>  $product->productVersion(),
            );
        }

        /**
         * Process Request to Activate the License
         *
         * @param EpitroveProduct $product
         * @return mixed
         */
        protected function processActivationRequest(EpitroveProduct $product)
        {
            // Check plugin information
            if (empty($product)) {
                $this->setResponseStatus(4009, __('Incorrect plugin data', 'epitrove-licensing'));
                return false;
            }

            $validateRequest = $this->validateRequest($product);

            if($validateRequest !== true){
                return $validateRequest;
            }

            $licenseKey = trim($_POST[$product->fieldIdentifier('license_key')]);

            // Save Licensing Data
            $product->updateLicenseKey($licenseKey);

            $response = wp_remote_post(
                self::apiUrl('activateLicense'),
                array(
                    'method'        => 'POST',
                    'timeout'       => 45,
                    'redirection'   => 5,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => array(),
                    'body'          => self::prepareApiData($product, $licenseKey)
                )
            );

            $this->processApiResponse($response, $product);
        }

        /**
         * Processes the Response obtained by API request
         *
         * @param EpitroveProduct $product Product for which API request was sent
         * @param Object $response Object returned by wp_remote_post request
         * @return void
         */
        public function processApiResponse($response, EpitroveProduct $product)
        {
            if (empty($response) || is_wp_error($response)) {
                return false;
            }

            // Fetch Response
            $licenseData = json_decode(wp_remote_retrieve_body($response));
            $this->setResponseStatus($licenseData->code, $licenseData->message);

            if($licenseData->code == 4001){
                return false;
            }

            switch ($licenseData->code) {
                
                case EPITROVE_API_SUCCESS_CODE: // Action was successful on api side
                    if($this->isLicenseActivateRequest($product)){
                        $product->updateLicenseStatus('valid');
                    }

                    if($this->isLicenseDeactivateRequest($product)){
                        $product->updateLicenseStatus('deactivated');
                    }

                    break;

                case 4003:
                    // License Expired.
                    $product->updateLicenseStatus('expired');
                    break;

                case 4005:
                    // Remaining activations equall to zero
                    $product->updateLicenseStatus('no_activations_left');
                    break;

                default:
                    $product->updateLicenseStatus('deactivated');
            }

        }

        /**
         * Process request to Deactivate License
         *
         * @param EpitroveProduct $product Product for which License needs to be deactivated
         * @return mixed
         */
        protected function processDeactivationRequest(EpitroveProduct $product)
        {
            // Check plugin information
            if (empty($product)) {
                $this->setResponseStatus(4009, __('Incorrect plugin data', 'epitrove-licensing'));
                return false;
            }

            $validateRequest = $this->validateRequest($product);

            if($validateRequest !== true){
                return $validateRequest;
            }

            $licenseKey = trim($_POST[$product->fieldIdentifier('license_key')]);

            $response = wp_remote_post(
                self::apiUrl('deactivateLicense'),
                array(
                    'method'        => 'POST',
                    'timeout'       => 45,
                    'redirection'   => 5,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => array(),
                    'body'          => self::prepareApiData($product, $licenseKey)
                )
            );

            $this->processApiResponse($response, $product);
        }

        /**
         * Returns the API Url
         *
         * @param string $endpoint Endpoint to be hit
         * @return mixed
         */
        public static function apiUrl($endpoint)
        {
            if (empty($endpoint)) {
                return false;
            }

            // Create the licensing URL
            $url = LICENSING_URL."/{$endpoint}";

            return $url;
        }


        /**
         * Displays list of All products belong to Epitrove
         *
         * @return string
         */
        public function showAllProductLicenses()
        {
            // Get all epi products
            $epitroveProducts = $this->getAllEpitroveProducts();

            ob_start();

            if (empty($epitroveProducts)){ ?>
                <tr>
                    <td colspan="4">
                        <?php _e('No active plugins found', $this->pluginSlug); ?>
                    </td>
                </tr>   
            <?php 
            }

            // Get all license views
            foreach ($epitroveProducts as $product) {
                $this->displayProductLicense($product);
                
            }

            echo ob_get_clean();
        }

        /**
         * Shows the licensing information of single product
         *
         * @param EpitroveProduct $product
         * @return void
         */
        public function displayProductLicense(EpitroveProduct $product)
        {

            //Get License Status
            $status = $product->licenseStatus();

            $readOnly = in_array($status, self::activeLicenseStatuses()) ? 'readonly' : '';
            ?>
            <tr>
                <td class="product-name"><?php echo $product->productName() ?></td>

                <td class="license-key">
                    <?php printf(
                        '<input type="text" class="regular-text" id="%s" name="%s" value="%s" %s',
                        esc_attr($product->fieldIdentifier('license_key')), 
                        esc_attr($product->fieldIdentifier('license_key')), 
                        esc_attr($product->licenseKey()), 
                        $readOnly
                    ); ?>
                </td>

                <td class="license-status">
                    <?php $this->displayLicenseStatus($status); ?>
                </td>

                <td class="epi-actions">
                    <?php wp_nonce_field($product->fieldIdentifier('nonce'), $product->fieldIdentifier('nonce')); ?>
                    <?php if ($product->isActiveLicense()) : ?>

                        <?php printf(
                        '<input type="submit" class="epi-link button" name="%s" value="%s"',
                        esc_attr($product->fieldIdentifier('license_deactivate')), 
                        esc_attr('Deactivate')
                        ); ?>

                    <?php else : ?>

                         <?php printf(
                        '<input type="submit" class="epi-link button" name="%s" value="%s"',
                        esc_attr($product->fieldIdentifier('license_activate')), 
                        esc_attr('Activate')
                        ); ?>

                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        /**
         * Display licensing status in license row.
         *
         * @param string $status         Current response status of license
         */
        public function displayLicenseStatus($status)
        {
            switch($status){
                case "valid":
                    $humanFriendlyStatus = 'Active';
                    break;
                
                case "expired":
                    $humanFriendlyStatus = 'Expired';
                    break;
                
                default:
                    $humanFriendlyStatus = 'Not Active';
            }

            printf(
                '<span style="color:%s">%s</span>',
                ($status == 'valid') ? 'green' : 'red',
                esc_attr($humanFriendlyStatus)
            );

        }

        /**
         * Returns Registered Email to be used for Epitrove Licensing Requests
         *
         * @return string
         */
        private static function getRegisteredEmail()
        {
            return get_option(REGISTERED_EMAIL_KEY, '');
        }

        /**
         * Method for Third Party Developers to check if License of their product 
         * is active or not.
         * 
         *
         * @param string $productSlug productSlug defined in epitrove-config.php
         * @return boolean
         */
        public static function isActive($productSlug)
        {
            // Get license status
            $status = get_option('epi_' . $productSlug . '_license_status');

            if (in_array($status, self::activeLicenseStatuses())) {
                return true;
            }

            return false;
        }

        /**
         * Returns the array of active license statuses
         * 
         * If the license is one of those statuses, then it is treated as an active
         * license
         *
         * @return array
         */
        public static function activeLicenseStatuses(){
            return ['valid', 'expired'];
        }
    }
}
