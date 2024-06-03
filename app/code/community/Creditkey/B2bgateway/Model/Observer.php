<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Model_Observer
{
    public function updateOrderStatus($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $newState = $order->getData('state');
        $oldState = $order->getOrigData('state');

        if ($newState !== $oldState) {
	    try {
                Mage::helper('b2bgateway/credit_api')->updateOrderStatus($order);
            } catch (\Exception $e) {
                Mage::log($e->getMessage(), null, 'creditkey.log');
            }
        }
    }
}
?>
