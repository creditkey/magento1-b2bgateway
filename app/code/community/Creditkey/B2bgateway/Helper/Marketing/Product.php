<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Helper_Marketing_Product extends Mage_Core_Helper_Abstract
{
    /**
     * @return \Creditkey_B2bgateway_Helper_Credit_Api
     */
    public function getCreditKeyApi()
    {
        return Mage::helper('b2bgateway/credit_api');
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return float
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getProductTaxAmount($product)
    {
        $tax_class_id = $product->getTaxClassId();
        //store
        $store = Mage::app()->getStore(); //store
        // address
        $customerAddressId = Mage::getSingleton('customer/session')->getCustomer()->getDefaultShipping();
        $address = null;
        if ($customerAddressId){
            $address = Mage::getModel('customer/address')->load($customerAddressId);
        }

        $taxCalculation = Mage::getModel('tax/calculation');
        $request = $taxCalculation->getRateRequest($address, null, null, $store);
        $percent = $taxCalculation->getStoreRate($request->setProductClassId($tax_class_id), $store);

        // get product price including tax
        $price = Mage::helper('tax')->getPrice($product, $product->getFinalPrice());

        $taxAmount = round($price/(100+$percent)*$percent,2);
        return $taxAmount;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function marketingEnable($product)
    {
        $isActive = (bool)Mage::getStoreConfig('payment/marketing_product/active');
        $rangPrice = $this->getCreditKeyApi()->getRangePrice();
        if (!$product) {
            return false;
        }
        $finalPrice = (int)$product->getFinalPrice();

        return $isActive && $this->isDisplay($product) && (($rangPrice == 0) || ($rangPrice > 0 && $rangPrice >= $finalPrice));
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function isDisplay($product)
    {
        $cartItems = [
            new \CreditKey\Models\CartItem(
                $product->getId(),
                $product->getName(),
                $product->getFinalPrice(), $product->getSku(), 1, null, null
            )
        ];
        return (bool)$this->getCreditKeyApi()->isDisplayedCheckout($cartItems);
    }

    /**
     * Get JSON config for JS component
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    public function getJsonConfig($product)
    {
        $config = [
            'ckConfig' => [
                'endpoint' => $this->getCreditKeyApi()->getEndpoint(),
                'publicKey' => $this->getCreditKeyApi()->getPublicKey(),
                'type' => $this->getCreditKeyApi()->getPdpMarketingType(),
                'size' => $this->getCreditKeyApi()->getPdpMarketingSize(),
                'charges' => $this->getCharges($product)
            ]
        ];

        return $config;
    }

    /**
     * Get an array of the charges for the product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array of charges as follows
     * [total, shipping, tax, discount_amount, grand_total]
     */
    private function getCharges($product)
    {
        $taxRate = $this->getProductTaxAmount($product);

        return [
            $product->getFinalPrice(),
            0, // no quote yet to calc shipping
            $taxRate,
            0, // no quote to apply discount
            $product->getFinalPrice() + $taxRate
        ];
    }
}