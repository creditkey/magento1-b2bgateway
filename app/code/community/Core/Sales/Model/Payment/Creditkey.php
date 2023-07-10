<?php
class Core_Sales_Model_Payment_Creditkey extends Creditkey_B2bgateway_Model_Payment_Creditkey
{
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
		try {
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
		} catch (\Exception $exception) {
			Mage::log("OrderId: {$merchantOrderId}", Zend_Log::DEBUG, 'creditkey.log');
			Mage::log("CK-OrderId: {$ckOrderId}", Zend_Log::DEBUG, 'creditkey.log');
			if ($exception instanceof \CreditKey\Exceptions\InvalidRequestException) {
				Mage::log("Invalid request for send to payment gateway", Zend_Log::DEBUG, 'creditkey.log');
			} else {
				if ($exception instanceof \CreditKey\Exceptions\OperationErrorException) {
					Mage::log("Reject by payment gateway", Zend_Log::DEBUG, 'creditkey.log');
				} else {
					Mage::log("API Unauthorized or Api Not found", Zend_Log::DEBUG, 'creditkey.log');
				}
			}
		}
		return $this;
	}
	public function refund(Varien_Object $payment, $amount)
	{
		$ckOrderId = $payment->getAdditionalInformation('ckOrderId');
		Mage::log('Refund Credit Key Payment: ' . $ckOrderId . ': ' . $amount, Zend_Log::DEBUG, 'creditkey.log');
		try {
			$ckOrder = \CreditKey\Orders::refund($ckOrderId, $amount);
		} catch (\Exception $exception) {
			Mage::log("CK-OrderId: {$ckOrderId}", Zend_Log::DEBUG, 'creditkey.log');
			if ($exception instanceof \CreditKey\Exceptions\InvalidRequestException) {
				Mage::log("Invalid request for send to payment gateway", Zend_Log::DEBUG, 'creditkey.log');
			} else {
				if ($exception instanceof \CreditKey\Exceptions\OperationErrorException) {
					Mage::log("Reject by payment gateway", Zend_Log::DEBUG, 'creditkey.log');
				} else {
					Mage::log("API Unauthorized or Api Not found", Zend_Log::DEBUG, 'creditkey.log');
				}
			}
		}
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