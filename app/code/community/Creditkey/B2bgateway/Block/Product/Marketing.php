<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Block_Product_Marketing extends Mage_Core_Block_Template
{
    /**
     * @var \Creditkey_B2bgateway_Helper_Credit_Api
     */
    protected $creditKeyApi;

    public function _construct()
    {
        $this->getCreditKeyApi()->configure();
    }

    /**
     * @return \Creditkey_B2bgateway_Helper_Credit_Api
     */
    public function getCreditKeyApi()
    {
        return Mage::helper('b2bgateway/credit_api');
    }

    /**
     * @return \Creditkey_B2bgateway_Helper_Marketing_Product
     */
    public function getMarketingHelper(){
        return Mage::helper('b2bgateway/marketing_product');
    }

    /**
     * Get Current Product
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }



    /**
     * get enable marketing show on product page
     * @return bool
     */
    public function marketingEnable()
    {
        return $this->getMarketingHelper()->marketingEnable($this->getProduct());
    }

    /**
     * Call checking can display
     * @return bool
     */
    public function isDisplay()
    {
        return $this->getMarketingHelper()->isDisplay($this->getProduct());
    }

    /**
     * Get JSON config for JS component
     *
     * @return string
     */
    public function getJsonConfig()
    {
        return $this->getMarketingHelper()->getJsonConfig($this->getProduct());
    }
}