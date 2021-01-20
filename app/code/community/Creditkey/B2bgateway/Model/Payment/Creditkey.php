<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Model_Payment_Creditkey extends Mage_Payment_Model_Method_Abstract
{
    /**
     * const payment code need call to compare
     */
    const PAYMENT_METHOD_CODE = 'b2bgateway';

    /**
     * Payment Code
     * @var string $_code
     */
    protected $_code = 'b2bgateway';

    /**
     * Check Initialize Needed
     * @var bool $_isInitializeNeeded
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCancelInvoice = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Check cance Use Internal
     * @var bool $_canUseInternal
     */
    protected $_canUseInternal = true;

    /**
     * Check can use for multi shipping
     * @var bool $_canUseForMultishipping
     */
    protected $_canUseForMultishipping = false;

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order = null;

    /**
     * @var \Creditkey_B2bgateway_Helper_Credit_Api
     */
    protected $creditKeyApi;

    public function __construct()
    {
        $this->creditKeyApi = Mage::helper('b2bgateway/credit_api');
    }

    /**
     * @return \Creditkey_B2bgateway_Helper_Order
     */
    public function getOrderHelper()
    {
        return Mage::helper('b2bgateway/order');
    }

    /**
     * Get Place Url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('b2bgateway/payment/sendTransaction', array('_secure' => true));
    }

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = $paymentInfo->getOrder();
        }
        return $this->_order;
    }


    /**
     * Validate Order
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function validateRedirect($order)
    {
        $method = $order->getPayment()->getMethodInstance();
        if ($method->getCode() == $this->getCode()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check method avaiable or not
     * @param null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $cartContents = $this->getOrderHelper()->buildCartContents($quote);
        $customerId = null;
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerId = Mage::getSingleton('customer/session')->getId();
        }

        $isCreditKeyDisplayed = \CreditKey\Checkout::isDisplayedInCheckout($cartContents, $customerId);
        if (!$isCreditKeyDisplayed) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    public function initialize($paymentAction, $stateObject)
    {
        $order = $this->getOrder();
        $order->setCanSendNewEmailFlag(false);
        $payment = $order->getPayment();

        //$payment->authorize(true, $payment->getAmountOrdered());

        $stateObject->setData('state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('status', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return Mage_Payment_Model_Abstract|void
     * @throws Creditkey_B2bgateway_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $ckOrderId = $payment->getAdditionalInformation('ckOrderId');
        $isAuthorized = \CreditKey\Checkout::completeCheckout($ckOrderId);
        if (!$isAuthorized) {
            Mage::log($e->getMessage(), null, 'creditkey.log');
            throw new Creditkey_B2bgateway_Exception('Creditkey: failed to complete checout.');
        }

        $order = $payment->getOrder();
        $this->creditKeyApi->updateOrderStatus($order);

        $payment->setAdditionalInformation('ckOrderId', $ckOrderId);
        $payment->setTransactionId($ckOrderId);
        $payment->setLastTransId($ckOrderId);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $ckOrderId = $payment->getAdditionalInformation('ckOrderId');

        Mage::log('Capture Credit Key Payment: ' . $ckOrderId . ': ' . $amount, Zend_Log::DEBUG, 'creditkey.log');

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $merchantOrderId = $order->getIncrementId();
        $merchantOrderStatus = $order->getStatus();
        $cartContents = $this->getOrderHelper()->buildCartContents($order);
        $charges = $this->getOrderHelper()->buildChargesWithUpdatedGrandTotal($order, $amount);

        $ckOrder = \CreditKey\Orders::confirm(
            $ckOrderId,
            $merchantOrderId,
            $merchantOrderStatus,
            $cartContents,
            $charges
        );

        $payment->setParentTransactionId($payment->getLastTransId());
        $payment->setTransactionId($ckOrder->getOrderId());
        $payment->setShouldCloseParentTransaction(true);

        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $ckOrderId = $payment->getAdditionalInformation('ckOrderId');

        Mage::log('Refund Credit Key Payment: ' . $ckOrderId . ': ' . $amount, Zend_Log::DEBUG, 'creditkey.log');

        $ckOrder = \CreditKey\Orders::refund($ckOrderId, $amount);
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $ckOrderId = $payment->getAdditionalInformation('ckOrderId');
        $ckOrder = \CreditKey\Orders::cancel($ckOrderId);
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        $ckOrderId = $payment->getAdditionalInformation('ckOrderId');

        Mage::log('Void Credit Key Payment: ' . $ckOrderId, Zend_Log::DEBUG, 'creditkey.log');

        $ckOrder = \CreditKey\Orders::cancel($ckOrderId);
        return $this;
    }
}