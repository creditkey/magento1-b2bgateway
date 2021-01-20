<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Helper_Marketing_Checkout extends Mage_Core_Helper_Abstract
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
     * @return bool
     */
    public function marketingEnable()
    {
        $isActive = (bool)Mage::getStoreConfig('payment/marketing_product/active');
        return $isActive;
    }

    /**
     * Get JSON config for JS component
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    public function getJsonConfig()
    {
        $config = [
            'ckConfig' => [
                'chargesUrl' => Mage::getUrl('b2bgateway/creditkey/charges'),
                'endpoint' => $this->getCreditKeyApi()->getEndpoint(),
                'publicKey' => $this->getCreditKeyApi()->getPublicKey(),
                'type' => $this->getCreditKeyApi()->getPaymentButtonType(),
                'size' => $this->getCreditKeyApi()->getPaymentButtonSize(),
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
    private function getCharges()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/session')->getQuote();

        $grandTotal = $quote->getGrandTotal();
        $shippintAmount = $quote->getSubtotal();
        $taxAmount = $quote->getTaxAmount();
        $discountAmount = $quote->getDiscountAmount();
        $subTotal = $quote->getSubtotal();

        return [
            $subTotal,
            $shippintAmount,
            $taxAmount,
            $discountAmount,
            $grandTotal
        ];
    }
}