<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Core_Sales_Model_Observer extends Creditkey_B2bgateway_Model_Observer
{
    public function updateOrderStatus($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $newState = $order->getData('state');
        $oldState = $order->getOrigData('state');
		$refundOffline = $order->getRefundOffline();

        if ((($newState !== $oldState) && !$refundOffline)) {
            Mage::helper('b2bgateway/credit_api')->updateOrderStatus($order);
        }
    }
}
?>