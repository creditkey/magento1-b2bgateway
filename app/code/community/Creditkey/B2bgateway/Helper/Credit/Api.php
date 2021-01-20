<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Helper_Credit_Api extends Mage_Core_Helper_Abstract
{
    public function __construct()
    {
        $this->configure();
    }
    /**
     * Get Public Key
     * @return string
     */
    public function getPublicKey()
    {
        return Mage::getStoreConfig('payment/b2bgateway/publickey');
    }

    /**
     * Get Secret Key
     * @return string
     */
    public function getSecretKey()
    {
        return Mage::getStoreConfig('payment/b2bgateway/secretkey');
    }


    /**
     * Get Endpoint
     *
     * @return mixed
     */
    public function getEndpoint()
    {
        return Mage::getStoreConfig('payment/b2bgateway/endpoint');
    }

    /**
     * Get Logo size
     * @return string
     */
    public function getPdpMarketingSize()
    {
        $size = Mage::getStoreConfig('payment/marketing_product/display_size');
        return $size != null ? $size : 'small';
    }

    /**
     * @return string
     */
    public function getPdpMarketingType()
    {
        $type = Mage::getStoreConfig('payment/marketing_product/display_type');
        return $type != null ? $type : 'text';
    }

    /**
     * Get Logo size
     * @return string
     */
    public function getPaymentButtonSize()
    {
        $size = Mage::getStoreConfig('payment/b2bgateway/display_size');
        return $size != null ? $size : 'small';
    }

    /**
     * @return string
     */
    public function getPaymentButtonType()
    {
        $type = Mage::getStoreConfig('payment/b2bgateway/display_type');
        return $type != null ? $type : 'text';
    }

    /**
     * get Range Price
     * @return int
     */
    public function getRangePrice()
    {
        return (int)Mage::getStoreConfig('payment/marketing_product/price_range');
    }
    /**
     * Configure the API Client
     *
     * @return void
     */
    public function configure()
    {
        $endpoint = $this->getEndpoint();
        $publicKey = $this->getPublicKey();
        $sharedSecret = $this->getSecretKey();
        \CreditKey\Api::configure($endpoint, $publicKey, $sharedSecret);
    }


    /**
     * @param array $cartContents
     * @param number|null $customerId
     * @return bool
     */
    public function isDisplayedCheckout($cartContents, $customerId = null)
    {
        return \CreditKey\Checkout::isDisplayedInCheckout($cartContents, $customerId);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     */
    public function updateOrderStatus($order)
    {
        $ckOrderId = $order->getPayment()->getAdditionalInformation('ckOrderId');
        if (!$ckOrderId) {
            return;
        }
        \CreditKey\Orders::update(
            $ckOrderId,
            $order->getState(),
            $order->getIncrementId(),
            null,
            null,
            null
        );
    }
}