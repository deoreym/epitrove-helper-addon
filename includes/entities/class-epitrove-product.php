<?php
namespace Licensing\Entities;

use Licensing\EpitroveLicense;

/**
 * Class that handles operations related to Epitrove Product
 * 
 * @package Licensing/Entities
 */
class EpitroveProduct {
    private $productSlug;
    private $pluginBaseName;
    private $productId;
    private $productVersion;
    private $productName;
    private $productRenewLink;
    private $authorName;
    private $isTheme = false;
    private $wpOverride = true; //If true, disable updates from wp.org for the product.

    /**
     * Creates the object of the class
     *
     * @param array $productConfiguration Configuration mentioned in epitrove-config.php file.
     * @param string $pluginBaseName Plugin's Basename, Applicable only if it is a plugin product.
     */
    public function __construct($productConfiguration, $pluginBaseName = null){

        foreach($productConfiguration as $key => $value ){
            $this->$key = $value;
        }

        if(!$this->isTheme()){
            $this->pluginBaseName = $pluginBaseName;
        }

    }

    /**
     * Returns the id to be used in input tags for the field
     *
     * @param string $field Possible values 'license_activate', 'license_deactivate', 'nonce', 'license_key'
     * @return void
     */
    public function fieldIdentifier($field){
        return 'epi_' . $this->productSlug() . '_' . $field;
    }

    /**
     * Saves License Key in the database
     *
     * @param string $licenseKey
     * @return void
     */
    public function updateLicenseKey($licenseKey){
        update_option($this->fieldIdentifier('license_key'), $licenseKey);
    }

    /**
     * Returns the license key stored in the database
     * 
     * @return string
     */
    public function licenseKey(){
        return get_option($this->fieldIdentifier('license_key'));
    }


    /**
     * Returns License status field Identifier
     *
     * @return string
     */
    public function licenseStatusFieldIdentifier(){
        return 'epi_'. $this->productSlug() . '_license_status';
    }

    /**
     * Saves the License Status in database
     *
     * @param string $licenseStatus
     * @return void
     */
    public function updateLicenseStatus($licenseStatus){
        update_option($this->licenseStatusFieldIdentifier(), $licenseStatus);
    }

    /**
     * Returns the license status stored in the database
     *
     * @return string
     */
    public function licenseStatus(){
        return get_option($this->licenseStatusFieldIdentifier());
    }

    /**
     * Returns true if license status is active
     *
     * @return boolean
     */
    public function isActiveLicense(){
        return in_array($this->licenseStatus(), EpitroveLicense::activeLicenseStatuses());
    }

    /**
     * Returns the Product id
     *
     * @return string
     */
    public function productId(){
        return $this->productId;
    }

    /**
     * Returns the Plugin Version
     *
     * @return string
     */
    public function productVersion(){
        return $this->productVersion;
    }

    /**
     * Returns the plugin Slug
     *
     * @return string
     */
    public function productSlug(){
        return $this->productSlug;
    }

    /**
     * Returns the name of the Product
     *
     * @return string
     */
    public function productName(){
        return $this->productName;
    }

    /**
     * Returns the Plugin's Basename
     * 
     * Plugins Basename means plugin's path with respect to WP's plugin directory.
     *
     * @return void
     */
    public function pluginBaseName(){
        return $this->pluginBaseName;
    }

    /**
     * Returns the product renew link
     *
     * @return string
     */
    public function productRenewLink(){
        return $this->productRenewLink;
    }

    /**
     * Checks whether the product is theme or not
     *
     * @return boolean
     */
    public function isTheme(){
        return $this->isTheme;
    }

    /**
     * Returns the Name of the Author
     *
     * @return string
     */
    public function authorName(){
        return $this->authorName;
    }

    /**
     * Returns whether update for the product should be fetched from wp.org
     *
     * @return bool
     */
    public function wpOverride(){
        return $this->wpOverride;
    }
}
