<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Order model
     * @var Mage_Sales_Model_Order $order
     */
    protected $order = null;

    /**
     * checkout session
     * @var Mage_Checkout_Model_Session $checkout_session
     */
    protected $checkout_session = null;

    /**
     * Payment Code
     * @var string
     */
    protected $payment = "b2bgateway";

    /**
     * @var \Creditkey_B2bgateway_Helper_Credit_Api
     */
    protected $creditKeyApi;

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $customerSession;

    /**
     * @var Mage_Checkout_Model_Session
     */
    protected $checkoutSession;


    /**
     * Creditkey_B2bgateway_PaymentController constructor.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array $invokeArgs
     */
    public function __construct( $request, $response, $invokeArgs = array())
    {
        parent::__construct($request, $response, $invokeArgs);
        $this->creditKeyApi = Mage::helper('b2bgateway/credit_api');
    }

    /**
     * Get Quote
     * @return Mage_Sales_Model_Quote
     */
    protected function getQuote()
    {
        return $this->getCheckoutSession()->getQuote();
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    protected function getOrderFromSession()
    {
        if ($this->order == null) {
            $orderModel = Mage::getModel('sales/order');
            $session = $this->getCheckoutSession();
            $orderId = $session->getLastRealOrderId();
            $this->order = $orderModel->loadByIncrementId($orderId);
        }

        return $this->order;
    }

    /**
     * @param string $orderId
     * @return Mage_Sales_Model_Order
     */
    protected function getOrderById($orderId)
    {
        if ($this->order == null) {
            $orderModel = Mage::getModel('sales/order');
            $this->order = $orderModel->load($orderId);
        }

        return $this->order;
    }

    /**
     * Set Order
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    protected function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get checkout session
     * @return Mage_Checkout_Model_Session|null
     */
    protected function getCheckoutSession()
    {
        if ($this->checkoutSession === null) {
            $this->checkoutSession = Mage::getSingleton('checkout/session');
        }
        return $this->checkoutSession;
    }

    /**
     * Get customer session
     *
     * @return Mage_Customer_Model_Session|null
     */
    protected function getCustomerSession()
    {
        if ($this->customerSession === null) {
            $this->customerSession = Mage::getSingleton('customer/session');
        }
        return $this->customerSession;
    }
    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * @return mixed
     */
    protected function placeOrder()
    {
        $this->getOnepage()->getQuote()->collectTotals();
        $this->getOnepage()
            ->saveOrder();
        return $this->getCheckoutSession()->getLastOrderId();
    }

    /**
     * Get response from CreditKey
     * @return Mage_Core_Controller_Varien_Action
     */
    public function returnAction()
    {
        $orderId = $this->placeOrder();
        $ckOrderId = $this->getRequest()->getParam('key');

        $order = $this->getOrderById($orderId);
        $isOrderPaid = !($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);

        if (!$order->getId()) {
            Mage::log('Reference order not found', null, 'creditkey.log');
            $this->getCheckoutSession()->addError('Reference order not found');
            return $this->_redirect('checkout/onepage/failure');
        }

        // workaround to avoid duplicate return from credit key call
        if (!$isOrderPaid) {

            $orderPayment = $order->getPayment();
            $orderPayment->setAdditionalInformation('ckOrderId', $ckOrderId);

            try {
                $orderPayment->authorize(true, round($order->getGrandTotal(), 2));
                $order->save();
            } catch (Exception $exception) {
                Mage::log($exception->getMessage(), null, 'creditkey.log');
                $order->cancel();
                $this->getCheckoutSession()->addError($exception->getMessage());
                return $this->_redirect('checkout/onepage/failure');
            }
        }

        $checkoutSession = $this->getCheckoutSession();
        $checkoutSession->setQuoteId($order->getQuoteId());
        $checkoutSession
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus())
        ;

        $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();

        return $this->_redirect('checkout/onepage/success');
    }

    /**
     * Sent request make order rto creditkey
     * @return Mage_Core_Controller_Varien_Action|void
     */
    public function sendTransactionAction()
    {
        try {
            /** @var Creditkey_B2bgateway_Helper_Order $orderHelper */
            $orderHelper = Mage::helper('b2bgateway/order');
            $quote = $this->getQuote();

            if (!$quote->getIsActive()) {
                $this->norouteAction();
                return;
            }
            $quote->reserveOrderId();
            $remoteId = $quote->getReservedOrderId();
            $customerId = null;
            $customerSession = Mage::getSingleton('customer/session');
            if ($customerSession->isLoggedIn()) {
                $customerId = $customerSession->getCustomerId();
            }
            $cartItems = $orderHelper->buildCartContents($quote);
            $billingAddress = $orderHelper->buildAddress($quote->getBillingAddress());
            $shippingAddress = $orderHelper->buildAddress($quote->getShippingAddress());
            $charges = $orderHelper->buildChargesWithUpdatedGrandTotal($quote);
            $params = [
                'ref' => $quote->getId(),
                'key' => '%CKKEY%',
                '_secure' => true
            ];
            $returnUrl = Mage::getUrl('b2bgateway/payment/return', $params);
            $cancelUrl = Mage::getUrl('b2bgateway/payment/cancel');

            $mode = 'redirect';
            if ($this->_request->getParam('modal')) {
                $mode = 'modal';
            }

            $redirectTo = \CreditKey\Checkout::beginCheckout(
                $cartItems,
                $billingAddress,
                $shippingAddress,
                $charges,
                $remoteId,
                $customerId,
                $returnUrl,
                $cancelUrl,
                $mode
            );

            return $this->_redirectUrl($redirectTo);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'creditkey.log');
            $this->getCheckoutSession()->addError($e->getMessage());
            $this->_redirect('checkout/onepage/failure');
        }
    }

    /**
     * Cancel from creditkey
     * @return Mage_Core_Controller_Varien_Action
     */
    public function cancelAction()
    {
        return $this->_redirect('checkout/onepage/');
    }

}