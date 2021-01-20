<?php
/**
 * @copyright Copyright (c) creditkey.com, Inc. (http://www.creditkey.com)
 */

class Creditkey_B2bgateway_Helper_Order extends Mage_Core_Helper_Abstract
{

    /**
     * Get Cart Contents
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $quote
     * @return \CreditKey\Models\CartItem[]
     */
    public function buildCartContents($quote)
    {
        $cartContents = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $productId = (int)$item->getProductId();
            $name = $item->getName();
            $price = (float)$item->getPrice();
            $sku = $item->getSku();
            $quantity = (int)$item->getQty();
            array_push(
                $cartContents,
                new \CreditKey\Models\CartItem($productId, $name, $price, $sku, $quantity, null, null)
            );
        }

        return $cartContents;
    }

    /**
     * @param Mage_Customer_Model_Address $magentoAddress
     * @return array
     */
    public function buildAddress($magentoAddress)
    {
        $street = $magentoAddress->getStreet();
        $address1 = null;
        $address2 = null;

        if (count($street) >= 1) {
            $address1 = $street[0];
        }
        if (count($street) >= 2) {
            $address2 = $street[1];
        }

        return new \CreditKey\Models\Address(
            $magentoAddress->getFirstname(),
            $magentoAddress->getLastname(),
            $magentoAddress->getCompany(),
            $magentoAddress->getEmail(),
            $address1,
            $address2,
            $magentoAddress->getCity(),
            $magentoAddress->getRegionCode(),
            $magentoAddress->getPostcode(),
            $magentoAddress->getTelephone()
        );
    }

    /**
     * Return a \CreditKey\Models\Charges objects from a Quote or Order object
     * @return \CreditKey\Models\Charges
     */
    public function buildCharges($holder)
    {
        $grandTotal = (float)$holder->getGrandTotal();
        return $this->buildChargesWithUpdatedGrandTotal($holder, $grandTotal);
    }

    /**
     * Get Charges data
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $quote
     * @return \CreditKey\Models\Charges
     */
    public function buildChargesWithUpdatedGrandTotal($quote, $updatedGrandTotal = null)
    {
        $total = (float)$quote->getSubtotal();

        $shippingAmount = $quote->getShippingAmount() == null
            ? (float)0
            : (float)$quote->getShippingAmount();

        if ($shippingAmount == 0) {
            $shippingAmount = $quote->getShippingAddress() == null
                ? (float)0
                : (float)$quote->getShippingAddress()->getShippingAmount();
        }

        $tax = $quote->getBillingAddress() == null
            ? (float)0
            : (float)$quote->getBillingAddress()->getTaxAmount();

        if ($tax == 0) {
            $tax = $quote->getShippingAddress() == null
                ? (float)0
                : (float)$quote->getShippingAddress()->getTaxAmount();
        }

        if ($tax == 0) {
            $tax = $quote->getTaxAmount();
        }

        $discount = $quote->getSubtotalWithDiscount() == null
            ? (float)0
            : (float)$quote->getSubtotal() - $quote->getSubtotalWithDiscount();

        if ($discount == 0) {
            $discount = $quote->getDiscountAmount() == null
                ? (float)0
                : (float)abs($quote->getDiscountAmount());
        }

        return new \CreditKey\Models\Charges(
            $total,
            $shippingAmount,
            $tax,
            $discount,
            $updatedGrandTotal ? $updatedGrandTotal : $quote->getGrandTotal()
        );
    }

    /**
     * Get Charges by order
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getChargesByOrder($order)
    {
        return [
            "total" => $order->getSubtotal(),
            "shipping" => $order->getShippingAmount(),
            "tax" => $order->getTaxAmount(),
            "discount_amount" => $order->getDiscountAmount(),
            "grand_total" => $order->getGrandTotal()
        ];
    }

    /**
     * get marketing params
     * @return string
     */
    public function getParamMarketing()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if ($quote) {

            $params = array(
                "type" => "checkout",
                "charges" => array(
                    "data" => $this->buildChargesWithUpdatedGrandTotal($quote)
                )
            );

            return json_encode($params);
        }

        return json_encode([]);
    }
}