<?php
namespace Licensing;

if (!class_exists('Licensing\EpitroveLicense')) {
    /**
     * EpitroveLicense
     */
    class EpitroveLicense
    {
        private $allEpiProductsData = array();

        public $pluginSlug = null;

        public function __construct()
        {
            //Constants
            if (!defined('LICENSE_KEY')) {
                define('LICENSE_KEY', '_license_key');
            }

            if (!defined('EPITROVE_CONFIG_FILE')) {
                define('EPITROVE_CONFIG_FILE', 'epitrove-config.php');
            }

            if (!defined('REGISTERED_EMAIL_KEY')) {
                define('REGISTERED_EMAIL_KEY', 'epi_registered_email');
            }

            if (!defined('VALID')) {
                define('VALID', 'valid');
            }

            if (!defined('EXPIRED')) {
                define('EXPIRED', 'expired');
            }

            if (!defined('INVALID')) {
                define('INVALID', 'invalid');
            }

            if (!defined('LICENSING_URL')) {
                // @TODO : Update to https://api.epitrove.com
                define('LICENSING_URL', 'http://licensing.local');
            }

            if (!defined('ACTIVATION_CODE')) {
                define('ACTIVATION_CODE', 201);
            }

            $this->pluginSlug = 'epitrove-licensing';

            $this->setAllEpiProducts();

            $this->validateLicenseData();

            add_action('admin_menu', array($this, 'addLicenseMenu'));


            // Schedule checks
            // add_action('init', array($this, 'updateLicenseStatus'));

            // add_action('admin_enqueue_scripts', array($this, 'enqueueLicenseStyles'));
            // Check for updates
        }

        public function enqueueLicenseStyles()
        {
            // error_log("PATH : ".print_r(plugins_url('/assets/css/admin.css', __FILE__), 1));
            wp_enqueue_style($this->pluginSlug, plugins_url('/assets/css/admin.css', __FILE__), array());
        }

        private function validateLicenseData()
        {
            // Basic security checks for licensing data

            // error_log('LicenseEmail : '.print_r($this->getLicenseEmail(), 1));
            // error_log('LicenseProduct : '.print_r($this->getLicenseProduct(), 1));
            // error_log('LicenseSecretKey : '.print_r($this->getLicenseSecretKey(), 1));
        }

        // private function setLicenseEmail($email)
        // {
        //     if (empty($email)) {
        //         return;
        //     }
        //     $this->licenseEmail = $email;
        // }

        public function getLicenseEmail()
        {
            return $this->licenseEmail;
        }

        // private function setLicenseProduct($product_id)
        // {
        //     if (empty($product_id)) {
        //         return;
        //     }
        //     $this->productId = $product_id;
        // }

        public function getLicenseProduct()
        {
            return $this->productId;
        }

        // private function setLicensePlatform($platform)
        // {
        //     if (empty($platform)) {
        //         return;
        //     }
        //     $this->platform = $platform;
        // }

        public function getLicensePlatform()
        {
            return $this->platform;
        }

        // private function setLicenseSoftwareVersion($software_version)
        // {
        //     if (empty($software_version)) {
        //         return;
        //     }
        //     $this->software_version = $software_version;
        // }

        public function getLicenseSoftwareVersion()
        {
            return $this->software_version;
        }

        // private function setLicenseInstance($instance_id)
        // {
        //     if (empty($instance_id)) {
        //         return;
        //     }
        //     $this->instanceId = $instance_id;
        // }

        public function getLicenseInstance()
        {
            return $this->instanceId;
        }

        public function addLicenseMenu()
        {
            // if (!in_array('wisdmlabs-licenses', $GLOBALS['admin_page_hooks'])) {
            // }
            add_menu_page(
                __('Epitrove Licensing', $this->pluginSlug),
                __('Epitrove Licensing', $this->pluginSlug),
                'manage_options',
                $this->pluginSlug,
                array($this, 'showLicensePage')
            );
        }

        public function showLicensePage()
        {
            include_once 'views/view-license-page.php';
        }


        public function saveLicensingDetails($plugin_information, $license_key)
        {
            if (empty($plugin_information) || empty($license_key)) {
                return false;
            }

            update_option('epi_'.$plugin_information['pluginSlug'].LICENSE_KEY, $license_key);
        }

        public function saveEmailDetails()
        {
            if (! array_key_exists('epitrove-email-save', $_POST) && 'Save Email' !== $_POST['epitrove-email-save']) {
                return;
            }

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

        /**
         * Save Licensing Data and Perform relevant actions
         */
        public function performLicenseActions()
        {
            if (empty($_POST)) {
                return;
            }

            $response = false;
            // $message = false;

            // Check which plugin action occurred
            $all_epitrove_plugins = $this->getAllEpiProductsData();

            foreach ($all_epitrove_plugins as $plugin) {
                if (array_key_exists('epi_'.$plugin['pluginSlug'].'_license_activate', $_POST) &&
                'Activate' == $_POST['epi_'.$plugin['pluginSlug'].'_license_activate']) {
                    $response = $this->activateLicenseKey($plugin);
                    $status = $this->processActivationResponse($plugin, $response);
                } elseif (array_key_exists('epi_'.$plugin['pluginSlug'].'_license_deactivate', $_POST) &&
                'Deactivate' == $_POST['epi_'.$plugin['pluginSlug'].'_license_deactivate']) {
                    $response = $this->deactivateLicenseKey($plugin);
                    $status = $this->processDeactivationResponse($plugin, $response);
                }
            }

            return $response;
        }

        protected function activateLicenseKey($plugin_information)
        {
            // Check plugin information
            if (empty($plugin_information)) {
                return array(
                    'type' =>  'error',
                    'message'   =>  __('Incorrect plugin data', 'epitrove-licensing')
                );
            }

            // Get Licensing Data
            $license_key = trim($_POST['epi_'.$plugin_information['pluginSlug'].LICENSE_KEY]);

            $registered_email = $this->getRegisteredEmail();

            if (empty($license_key) || empty($registered_email)) {
                return array(
                    'type' =>  'error',
                    'message'   =>  __('Incorrect license key or email data', 'epitrove-licensing')
                );
            }

            // Save Licensing Data
            update_option('epi_'.$plugin_information['pluginSlug'].LICENSE_KEY, $license_key);

            $response = null;

            $instance = preg_replace('#^https?://#', '', get_home_url());

            $data = array(
                'email'         =>  $registered_email,
                'licenseKey'    =>  $license_key,
                'productId'     =>  $plugin_information['productId'],
                'platform'      =>  $plugin_information['siteUrl'],
                'instance'      =>  $instance,
                'version'       =>  $plugin_information['pluginVersion'],
            );

            $url = $this->getLicenseURL(array(), 'api/v1/activateLicense');

            $response = wp_remote_post(
                $url,
                array(
                    'method'      => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body'        => $data
                )
            );

            return $response;
        }

        protected function deactivateLicenseKey($plugin_information)
        {
            // Check plugin information
            if (empty($plugin_information)) {
                return;
            }

            // Get Licensing Data
            $license_key = trim($_POST['epi_'.$plugin_information['pluginSlug'].LICENSE_KEY]);

            $registered_email = $this->getRegisteredEmail();

            if (empty($license_key) || empty($registered_email)) {
                return array(
                    'type' =>  'error',
                    'message'   =>  __('Incorrect license key or email data', 'epitrove-licensing')
                );
            }

            // Save Licensing Data
            update_option('epi_'.$plugin_information['pluginSlug'].LICENSE_KEY, $license_key);

            $response = null;

            $instance = preg_replace('#^https?://#', '', get_home_url());

            $data = array(
                'email'         =>  $registered_email,
                'licenseKey'    =>  $license_key,
                'productId'     =>  $plugin_information['productId'],
                'platform'      =>  $plugin_information['siteUrl'],
                'instance'      =>  $instance,
                'version'       =>  $plugin_information['pluginVersion'],
            );

            $url = $this->getLicenseURL(array(), 'api/v1/deactivateLicense');

            $response = wp_remote_post(
                $url,
                array(
                    'method'        => 'POST',
                    'timeout'       => 45,
                    'redirection'   => 5,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => array(),
                    'body'          => $data
                )
            );
            // error_log("RESPONSE : ".print_r($response, 1));

            return $response;
        }

        protected function checkLicenseKey($plugin_information)
        {
            // Check plugin information
            if (empty($plugin_information)) {
                return array(
                    'type' =>  'error',
                    'message'   =>  __('Incorrect plugin data', 'epitrove-licensing')
                );
            }

            // Get Licensing Data
            $license_key = trim($_POST['epi_'.$plugin_information['pluginSlug'].LICENSE_KEY]);

            $registered_email = $this->getRegisteredEmail();

            if (empty($license_key) || empty($registered_email)) {
                return array(
                    'type' =>  'error',
                    'message'   =>  __('Incorrect license key or email data', 'epitrove-licensing')
                );
            }

            // Save Licensing Data
            update_option('epi_'.$plugin_information['pluginSlug'].LICENSE_KEY, $license_key);

            $response = null;

            $instance = preg_replace('#^https?://#', '', get_home_url());

            $data = array(
                'email'         =>  $registered_email,
                'licenseKey'    =>  $license_key,
                'productId'     =>  $plugin_information['productId'],
                'platform'      =>  $plugin_information['siteUrl'],
                'instance'      =>  $instance
            );

            $url = $this->getLicenseURL(array(), 'api/v1/checkLicense');

            $response = wp_remote_post(
                $url,
                array(
                    'method'      => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body'        => $data
                )
            );

            return $response;

            $url = $this->getLicenseURL($params, 'check');
            $response = wp_remote_post($url);

            return $response;
        }

        protected function getPublicKey()
        {
            return openssl_get_publickey(file_get_contents(__DIR__ . '/public.pem'));
        }

        public function getLicenseURL($params, $route)
        {
            if (empty($route)) {
                return false;
            }

            // Create the licensing URL
            $url = LICENSING_URL."/{$route}";

            return $url;
        }

        public function updateLicenseStatus()
        {
            // Get the transient value set for license status.
            $transient = get_transient($this->slug);

            // If set and valid, return.
            if ('active' === $transient) {
                error_log('Active...');
                return;
            }
            // If expired, do something to never req again.

            // If not then, perform a check
            // error_log('Checking ...');
            // $response = $this->checkLicenseKey();
        }

        public function scheduleLicenseChecks($response)
        {
            $response_data = json_decode($response['body']);

            $check = false;
            if ($response_data->error) {
                error_log('ERR : ' . $response_data->error);
                return false;
            }

            if ($response_data->activated) {
                // Check License type (try, buy or subscribe)
                switch ($response_data->subscription_type) {
                    case 'try':
                        $time = 60 * 60 * 24 * 7;
                        $check = $this->createScheduledChecks('try', $time);
                        break;
                    case 'buy':
                        $time = 60 * 60 * 24 * 30;
                        $check = $this->createScheduledChecks('buy', $time);
                        break;
                    case 'subscribe':
                        $time = 60 * 60 * 24 * 30;
                        $check = $this->createScheduledChecks('subscribe', $time);
                        break;
                }
            }

            return $check;
        }

        public function createScheduledChecks($type, $time)
        {
            error_log($type);
            error_log($this->slug);
            // Get scheduled transients
            $transient = get_transient($this->slug);
            if (false == $transient) {
                delete_transient($this->slug);
            }
            return set_transient($this->slug, 'active', $time);
        }

        public function processActivationResponse($plugin_data, $response)
        {
            if (empty($response) || is_wp_error($response)) {
                return false;
            }

            // Fetch Response
            $license_data = json_decode(wp_remote_retrieve_body($response));

            if ($license_data->error) {
                switch ($license_data->code) {
                    case 91:
                        // License Expired
                        update_option('epi_' . $plugin_data['pluginSlug'] . '_license_status', EXPIRED);
                        break;

                    case 103:
                        // Remaining activations equall to zero
                        update_option('epi_' . $plugin_data['pluginSlug'] . '_license_status', 'no_activations_left');
                        break;

                    default:
                        update_option('epi_' . $plugin_data['pluginSlug'] . '_license_status', 'deactivated');
                }
                return false;
            }

            // Save license activated status
            //Save License Status in the database
            if (ACTIVATION_CODE === $license_data->code) {
                $licenseStatus = self::updateStatus($license_data, $plugin_data, 'activation');
            }

            // Save Activation Transient
            $this->setTransientOnActivation($plugin_data, $licenseStatus);
            // Schedule check for license
            // $scheduled = $this->scheduleLicenseChecks($response);

            // error_log('SCHEDULED :' . print_r($scheduled, 1));
            // if (true === $scheduled) {
            //     $message = 'License successfully Activated';
            // }
            // else {
            //     $data = json_decode($response['body']);
            //     if (! $data->code) {
            //         $message = 'License already activated';
            //     } else {
            //         $message = "Code : {$data->code} , Error: {$data->error}";
            //     }
            // }
        }

        public function processDeactivationResponse($plugin_information, $response)
        {
            if (empty($response) || is_wp_error($response)) {
                return false;
            }

            // Fetch Response
            $license_data = json_decode(wp_remote_retrieve_body($response));

            if ($license_data->error) {
                switch ($license_data->code) {
                    case 100:
                        // License Expired
                        update_option('epi_' . $plugin_information['pluginSlug'] . '_license_status', INVALID);
                        break;

                    case 103:
                        // Remaining activations equall to zero
                        error_log('Remaining activations equall to zero');
                        update_option('epi_' . $plugin_information['pluginSlug'] . '_license_status', INVALID);
                        break;

                    default:
                        update_option('epi_' . $plugin_information['pluginSlug'] . '_license_status', INVALID);
                }
                return false;
            }


            // Save license deactivated status
            if (DEACTIVATION_CODE === $license_data->code || $license_data->code) {
                $licenseStatus = self::updateStatus($license_data, $plugin_information, 'deactivation');
            }

            // Delete transients for scheduled checks
            self::setVersionInfoCache('epi_'.$plugin_information['pluginSlug'].'_license_trans', 0, 'deactivated');
        }

        public static function isActive($slug)
        {
            // Get license status
            $status = get_option('epi_' . $slug . '_license_status');

            if (VALID === $status) {
                return true;
            }

            return false;
        }

        /**
         * Set all epitrove licensed products data on the site
         */
        private function setAllEpiProducts()
        {
            // Get all plugins dir names
            $all_plugins = scandir(WP_PLUGIN_DIR, 1);

            // Get all theme dir names
            $all_themes = scandir(get_theme_root());

            if (empty($all_plugins) || empty($all_themes)) {
                error_log('Error fetching all plugins');
                return;
            }

            // Fetch all epitrove themes and plugins
            $epitrove_plugins = $this->getAllEpitroveLicensingPlugins($all_plugins);
            $epitrove_themes = $this->getAllEpitroveLicensingThemes($all_themes);
            $epitrove_themes_and_plugins = array_merge($epitrove_plugins, $epitrove_themes);

            // If no epitrove plugins or themes found, return
            if (empty($epitrove_themes_and_plugins)) {
                return;
            }

            $epi_plugin_data = array();
            // Fetch all necessary data for all epitrove plugins
            $plugin_data = null;
            foreach ($epitrove_plugins as $plugin_name) {
                $plugin_data = include_once(WP_PLUGIN_DIR . '/' . $plugin_name . '/'. EPITROVE_CONFIG_FILE);
                if (!empty($plugin_data)) {
                    $epi_plugin_data[$plugin_name] = $plugin_data;
                }
            }

            // Fetch all necessary data for all epitrove themes
            $theme_data = null;
            foreach ($epitrove_themes as $theme_name) {
                $theme_data = include_once(get_theme_root() . '/' . $theme_name . '/'. EPITROVE_CONFIG_FILE);
                if (!empty($theme_data)) {
                    $epi_plugin_data[$theme_name] = $theme_data;
                }
            }

            // Set all epitrove products data
            $this->setAllEpiProductsData($epi_plugin_data);
        }

        private function setAllEpiProductsData($plugin_data_array)
        {
            if (empty($plugin_data_array)) {
                return;
            }
            $this->allEpiProductsData = $plugin_data_array;
        }

        public function getAllEpiProductsData()
        {
            return $this->allEpiProductsData;
        }

        public function showAllProductLicenses()
        {
            // Get all epi products
            $epi_products = $this->getAllEpiProductsData();

            if (empty($epi_products)) {
                return;
            }

            ob_start();

            $plugin_dir_name = '';
            $inactive_epi_plugins = 0;
            // Get all license views
            foreach ($epi_products as $product_data) {
                $plugin_dir_name = end(explode('/', $product_data['baseFolderDir']));
                if ($product_data['isTheme']) {
                    if (wp_get_theme()->name != $product_data['pluginName']) {
                        $inactive_epi_plugins++;
                        continue;
                    }
                } else {
                    if (! is_plugin_active($plugin_dir_name.'/'.$product_data['mainFileName'])) {
                        $inactive_epi_plugins++;
                        continue;
                    }
                }
                $this->displayProductLicense($product_data);
            }


            if ($inactive_epi_plugins === count($epi_products)) {
                ?>
                <tr>
                    <td colspan="4">
                        <?php _e('No active plugins found', $this->pluginSlug); ?>
                    </td>
                </tr>
                <?php
            }
            $content = ob_get_clean();

            echo $content;
        }

        public function displayProductLicense($product_data)
        {
            $licenseKey = trim(get_option('epi_' . $product_data['pluginSlug'] . '_license_key'));

            $previousStatus = '';

            //Get License Status
            $status = '';
            $status = $this->getStatus($product_data, $previousStatus);

            $display = '';
            // $display = $this->getSiteList();
            if (isset($_POST) && !empty($_POST) && (isset($_POST['epi_'.$this->pluginSlug.'_license_deactivate']) ||
                isset($_POST['epi_'.$this->pluginSlug.'_license_activate']))) {
                $this->showServerResponse($product_data, $status, $display);
            }
            
            settings_errors('epi_'.$this->pluginSlug.'_errors');
            // $renewLink = get_option('epi_'.$this->pluginSlug.'_product_site');
            // $renewLink = '';

            ?>
            <tr>
                <td class="product-name"><?php echo $product_data['pluginName']; ?></td>
                <td class="license-key">
                    <?php if ($status == 'valid' || $status == 'expired' ||
                        $previousStatus == 'valid' || $previousStatus == 'expired') : ?>
                        <input 
                                id="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_key' ?>"
                                name="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_key' ?>"
                                type="text" class="regular-text" value="<?php esc_attr_e($licenseKey); ?>"
                                readonly />
                    <?php else : ?>
                        <input
                                id="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_key' ?>"
                                name="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_key' ?>"
                                type="text" class="regular-text"
                                value="<?php esc_attr_e($licenseKey); ?>" />
                    <?php endif; ?>
                    <label class="description" for="<?php echo 'epi_'.$product_data['pluginSlug'].'_license_key'; ?>">
                    </label>
                </td>
                <td class="license-status">
                    <?php $this->displayLicenseStatus($status, $previousStatus); ?>
                </td>
                <td class="epi-actions">
                    <?php if ($status !== false &&
                        ($status == 'valid' ||
                        $status == 'expired' ||
                        $previousStatus == 'valid' ||
                        $previousStatus == 'expired')) : ?>
                        <?php wp_nonce_field('epi_' . $product_data['pluginSlug'] . '_nonce', 'epi_' . $product_data['pluginSlug'] . '_nonce'); ?>
                        <input 
                            type="submit"
                            class="epi-link button"
                            name="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_deactivate'; ?>"
                            value="<?php _e('Deactivate', $product_data['pluginSlug']); ?>"/>
                        <?php if ($status == 'expired') : ?>
                            <input 
                                type="button"
                                class="button"
                                name="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_renew'; ?>"
                                value="<?php _e('Renew', $product_data['pluginSlug']); ?>"
                                onclick="window.open('<?php // echo $renewLink; ?>')"/>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php wp_nonce_field('epi_' . $product_data['pluginSlug'] . '_nonce', 'epi_' . $product_data['pluginSlug'] . '_nonce'); ?>
                        <input 
                            type="submit"
                            class="button"
                            name="<?php echo 'epi_' . $product_data['pluginSlug'] . '_license_activate'; ?>"
                            value="<?php _e('Activate', $product_data['pluginSlug']); ?>"/>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        /**
         * This function is used to get the status recieved from the server.
         *
         * @param string &$previousStatus previous status stored in database
         *
         * @return string Status of response
         */
        public function getStatus($product_data, &$previousStatus)
        {
            $status = get_option('epi_' . $product_data['pluginSlug'] . '_license_status');

            if (isset($GLOBALS['epi_server_null_response_' . $product_data['pluginSlug']]) && $GLOBALS['epi_server_null_response_' . $product_data['pluginSlug']]) {
                $status = 'server_did_not_respond';
                $previousStatus = get_option('epi_' . $product_data['pluginSlug'] . '_license_status');
            } elseif (isset($GLOBALS['epi_license_activation_failed_' . $product_data['pluginSlug']]) && $GLOBALS['epi_license_activation_failed_' . $product_data['pluginSlug']]) {
                $status = 'license_activation_failed';
            } elseif (isset($_POST['epi_' . $product_data['pluginSlug'] . LICENSE_KEY]) && empty($_POST['epi_' . $product_data['pluginSlug'] . LICENSE_KEY])) {
                $status = 'no_license_key_entered';
            } elseif (isset($GLOBALS['epi_server_curl_error_' . $product_data['pluginSlug']]) && $GLOBALS['epi_server_curl_error_' . $product_data['pluginSlug']]) {
                $status = 'server_curl_error';
                $previousStatus = get_option('epi_' . $product_data['pluginSlug'] . '_license_status');
            }

            return $status;
        }

        /**
         * Display licensing status in license row.
         *
         * @param string $status         Current response status of license
         * @param string $previousStatus Previous response stored in database
         */
        public function displayLicenseStatus($status, $previousStatus)
        {
            if ($status !== false) {
                if ($status == VALID || $previousStatus == VALID) {
                    ?>
                    <span style="color:green;"><?php _e('Active', $this->pluginSlug);
                    ?></span>
                    <?php
                } elseif ($status == EXPIRED || $previousStatus == EXPIRED) {
                    ?>
                    <span style="color:red;"><?php _e('Expired', $this->pluginSlug);
                    ?></span>
                    <?php
                } else {
                    ?>
                    <span style="color:red;"><?php _e('Not Active', $this->pluginSlug) ?></span>
                    <?php
                }
            }

            if ($status === false) {
                ?>
                <span style="color:red;"><?php _e('Not Active', $this->pluginSlug) ?></span>
                <?php
            }
        }

        /**
         * Notice to display based on response from server.
         *
         * @param string $status  current status of license
         * @param [type] $display [description]
         */
        public function showServerResponse($product_data, $status, $display)
        {
            $successMessages = array(
                VALID => __('Your license key is activated('.$product_data['pluginName'].')', $this->pluginSlug),
            );

            $errorMessages = array(
            'server_did_not_respond' => __('No response from server. Please try again later.('.$product_data['pluginName'].')', $this->pluginSlug),
            'license_activation_failed' => __('License Activation Failed. Please try again or contact support on support@wisdmlabs.com('.$product_data['pluginName'].')', $this->pluginSlug),
            'no_license_key_entered' => __('Please enter license key.('.$product_data['pluginName'].')', $this->pluginSlug),
            'no_activations_left' => (!empty($display)) ? sprintf(__('Your License Key is already activated at : %s Please deactivate the license from one of the above site(s) to successfully activate it on your current site.('.$product_data['pluginName'].')', $this->pluginSlug), $display) : __('No Activations Left.('.$product_data['pluginName'].')', $this->pluginSlug),
            EXPIRED => __('Your license key has Expired. Please, Renew it.('.$product_data['pluginName'].')', $this->pluginSlug),
            'disabled' => __('Your License key is disabled('.$product_data['pluginName'].')', $this->pluginSlug),
            INVALID => __('Please enter valid license key('.$product_data['pluginName'].')', $this->pluginSlug),
            'inactive' => __('Please try to activate license again. If it does not activate, contact support on support@wisdmlabs.com('.$product_data['pluginName'].')', $this->pluginSlug),
            'site_inactive' => (!empty($display)) ? sprintf(__('Your License Key is already activated at : %s Please deactivate the license from one of the above site(s) to successfully activate it on your current site.('.$product_data['pluginName'].')', $this->pluginSlug), $display) : __('Site inactive (Press Activate license to activate plugin('.$product_data['pluginName'].'))', $this->pluginSlug),
            'deactivated' => __('License Key is deactivated('.$product_data['pluginName'].')', $this->pluginSlug),
            'default' => sprintf(__('Following Error Occurred: %s. Please contact support on support@wisdmlabs.com if you are not sure why this error is occurring('.$product_data['pluginName'].')', $this->pluginSlug), $status),
            'server_curl_error' => __('There was an error while connecting to the server. please try again later.('.$product_data['pluginName'].')', $this->pluginSlug),
            );

            if ($status !== false) {
                if (array_key_exists($status, $successMessages)) {
                    add_settings_error(
                        'epi_'.$this->pluginSlug.'_errors',
                        esc_attr('settings_updated'),
                        $successMessages[$status],
                        'updated'
                    );
                } else {
                    if (array_key_exists($status, $errorMessages)) {
                        add_settings_error(
                            'epi_'.$this->pluginSlug.'_errors',
                            esc_attr('settings_updated'),
                            $errorMessages[$status],
                            'error'
                        );
                    } else {
                        add_settings_error(
                            'epi_'.$this->pluginSlug.'_errors',
                            esc_attr('settings_updated'),
                            $errorMessages['default'],
                            'error'
                        );
                    }
                }
            }
        }

        /**
         * Updates license status in the database and returns status value.
         *
         * @param object $licenseData License data returned from server
         * @param string $pluginSlug  Slug of the plugin. Format of the key in options table is 'edd_<$pluginSlug>_license_status'
         *
         * @return string Returns status of the license
         */
        public static function updateStatus($licenseData, $pluginData, $update_type)
        {
            $status = '';
            // if (isset($licenseData->activated)) {
            //     // Check if request was successful. Even if success property is blank, technically it is false.
            //     if ($licenseData->activated === false && (!isset($licenseData->error) || empty($licenseData->error))) {
            //             $licenseData->error = INVALID;
            //     }
            //     // Is there any licensing related error? If there are no errors, $status will be blank
            //     $status = self::checkLicensingError($licenseData);

            //     if (!empty($status)) {
            //         update_option('epi_' . $plugin_data['pluginSlug'] . '_license_status', $status);

            //         return $status;
            //     }
            //     //Check license status retrieved from Server
            //     $status = self::checkLicenseStatus($licenseData, $pluginData);
            // }
            // $status = (empty($status)) ? INVALID : $status;

            switch ($update_type) {
                case 'activation':
                    $status = VALID;
                    update_option('epi_' . $pluginData['pluginSlug'] . '_license_status', VALID);
                    break;

                case 'deactivation':
                    $status = 'deactivated';
                    update_option('epi_' . $pluginData['pluginSlug'] . '_license_status', $status);
                    break;

                case 'default':
                    $status = INVALID;
                    break;
            }

            return $status;
        }

        /**
         * Checks if there is any error in response.
         *
         * @param object $licenseData License Data obtained from server
         *
         * @return string empty if no error or else error
         */
        public static function checkLicensingError($licenseData)
        {
            $status = '';
            if (isset($licenseData->error) && !empty($licenseData->error)) {
                switch ($licenseData->error) {
                    case 'revoked':
                        $status = 'disabled';
                        break;

                    case EXPIRED:
                        $status = EXPIRED;
                        break;

                    case 'item_name_mismatch':
                        $status = INVALID;
                        break;

                    default:
                        $status = '';
                }
            }

            return $status;
        }

        /**
         * Check license status from response from server.
         *
         * @param object $licenseData License data received from server
         * @param string $pluginData  plugin slug
         *
         * @return string License status
         */
        public static function checkLicenseStatus($licenseData, $pluginData)
        {
            $status = INVALID;
            if (isset($licenseData) && !empty($licenseData)) {
                switch ($licenseData->license) {
                    case INVALID:
                        $status = INVALID;
                        if (isset($licenseData->activations_left) && $licenseData->activations_left == '0') {
                            include_once plugin_dir_path(__FILE__).'class-wdm-get-license-data.php';
                            $activeSite = WdmGetLicenseData::getSiteList($pluginData);

                            if (!empty($activeSite) || $activeSite != '') {
                                $status = 'no_activations_left';
                            }
                        }

                        break;

                    case 'failed':
                        $status = 'failed';
                        $GLOBALS[ 'epi_license_activation_failed_'.$pluginData ] = true;
                        break;

                    default:
                        $status = $licenseData->license;
                }
            }

            return $status;
        }

        /**
         * Set transient on site on license activation
         * Transient is set for 7 days
         * After 7 days request is sent to server for fresh license status.
         *
         * @param string $licenseStatus Current license status
         */
        public function setTransientOnActivation($plugin_data, $licenseStatus)
        {
            if (!empty($licenseStatus)) {
                if ($licenseStatus == VALID) {
                    $time = 7;
                } else {
                    $time = 1;
                }
                self::setVersionInfoCache('epi_'.$plugin_data['pluginSlug'].'_license_trans', $time, $licenseStatus);
            }
        }

        public static function setVersionInfoCache($cacheKey, $time, $value = '')
        {
            if ($time == 0) {
                $timeOut = 0;
            } else {
                $timeOut = strtotime('+'.$time.' day', current_time('timestamp'));
            }
            $data = array(
            'timeout' => $timeOut,
            'value' => json_encode($value),
            );
            update_option($cacheKey, $data);
        }

        public function getAllEpitroveLicensingPlugins($all_plugins)
        {
            $epitrove_plugins = array();

            if (empty($all_plugins)) {
                return $epitrove_plugins;
            }

            // Find Epitrove licensing plugins
            // -    Check if epitrove license config file present
            foreach ($all_plugins as $plugin_name) {
                // error_log(' -- Checking : '.WP_PLUGIN_DIR.'/'.$plugin_name.'/'. EPITROVE_CONFIG_FILE);
                if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_name . '/'. EPITROVE_CONFIG_FILE)) {
                    array_push($epitrove_plugins, $plugin_name);
                }
            }

            return $epitrove_plugins;
        }

        public function getAllEpitroveLicensingThemes($all_themes)
        {
            $epitrove_themes = array();
            if (empty($all_themes)) {
                return $epitrove_themes;
            }

            // Find Epitrove licensing themes
            // -    Check if epitrove license config file present
            foreach ($all_themes as $theme_name) {
                // error_log(' -- Checking : '.WP_PLUGIN_DIR.'/'.$plugin_name.'/'. EPITROVE_CONFIG_FILE);
                if (file_exists(get_theme_root() . '/' . $theme_name . '/'. EPITROVE_CONFIG_FILE)) {
                    array_push($epitrove_themes, $theme_name);
                }
            }

            return $epitrove_themes;
        }

        public function getRegisteredEmail()
        {
            $registered_email = get_option(REGISTERED_EMAIL_KEY, '');
            
            return $registered_email;
        }

        public static function isLicenseActive($plugin_dir)
        {
            if (empty($plugin_dir)) {
                return false;
            }

            $licensing_data = null;
            $licensing_data = include($plugin_dir.'/epitrove-config.php');

            if (empty($licensing_data) || ! is_array($licensing_data)) {
                error_log("Incorrect file permissions or config file not found");
                return false;
            }
        
            if (self::isActive($licensing_data['pluginSlug'])) {
                return true;
            }

            return false;
        }
    }
}
