<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */


class Creditkey_B2bgateway_CreditkeyController extends Mage_Core_Controller_Front_Action
{
    public function chargesAction(){
        $response = [];
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $orderHelper = Mage::helper('b2bgateway/order');
        $charges = $orderHelper->buildChargesWithUpdatedGrandTotal($quote);
        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($charges->toFormData()));
    }

    /**
     * Check can display from CreditKey
     */
    public function isDisplayedAction()
    {

    }


}