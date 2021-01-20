<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_PaymentController extends Mage_Core_Controller_Front_Action
{
    const COOKIE_NAME = 'creditkey_first_checkout_order_complete';
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


    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        parent::__construct($request, $response, $invokeArgs);
        $this->creditKeyApi = new \Creditkey_B2bgateway_Helper_Credit_Api();
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
     * Get response from CreditKey
     * @return Mage_Core_Controller_Varien_Action
     */
    public function returnAction()
    {
        $orderId = $this->getRequest()->getParam('ref');
        $ckOrderId = $this->getRequest()->getParam('key');

        $order = $this->getOrderById($orderId);
        $isOrderValid = $order->getId();
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
                $orderPayment->authorize(true, $order->getGrandTotal());
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
            /** @var Mage_Sales_Model_Quote $quote */
            $orderHelper = Mage::helper('b2bgateway/order');
            $order = $this->getOrderFromSession();
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            $session = $this->getCheckoutSession();
            $paymentMethod = $order->getPayment()->getMethodInstance();

            if (!$order->getId() && !$paymentMethod->validateRedirect($order)) {
                $this->norouteAction();
                return;
            }
            $remoteId = $order->getIncrementId();
            $customerId = null;
            $customerSession = Mage::getSingleton('customer/session');
            if ($customerSession->isLoggedIn()) {
                $customerId = $customerSession->getCustomerId();
            }
            $cartItems = $orderHelper->buildCartContents($order);
            $billingAddress = $orderHelper->buildAddress($order->getBillingAddress());
            $shippingAddress = $orderHelper->buildAddress($order->getShippingAddress());
            $charges = $orderHelper->buildChargesWithUpdatedGrandTotal($order);
            $params = [
                'ref' => $order->getId(),
                'key' => '%CKKEY%',
                '_secure' => true
            ];
            $returnUrl = Mage::getUrl('b2bgateway/payment/return', $params);
            $cancelUrl = Mage::getUrl('b2bgateway/payment/cancel');

            $mode = 'redirect';
            if ($this->_request->getParam('modal')) {
                $mode = 'modal';
            }

            $this->creditKeyApi->configure();

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
        return $this->_redirect('checkout/onepage/failure');
    }

}