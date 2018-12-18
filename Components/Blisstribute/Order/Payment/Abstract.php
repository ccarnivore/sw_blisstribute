<?php

use Shopware\CustomModels\Blisstribute\BlisstributePayment;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

/**
 * abstract payment mapping
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * blisstribute payment code
     *
     * @var string
     */
    protected $code;

    /**
     * shopware order
     *
     * @var Order
     */
    protected $order;

    /**
     * blisstribute payment mapping
     *
     * @var BlisstributePayment
     */
    protected $payment;

    /**
     * @param Order               $order
     * @param BlisstributePayment $payment
     */
    public function __construct(Order $order, BlisstributePayment $payment)
    {
        $this->order = $order;
        $this->payment = $payment;

        $this->checkPaymentStatus();
    }

    /**
     * get blisstribute payment information
     *
     * @return array
     */
    public function getPaymentInformation()
    {
        $paymentCosts = 0.00;

        /** @var Detail $currentDetail */
        foreach ($this->order->getDetails() as $currentDetail) {
            if (!in_array($currentDetail->getArticleNumber(), ['sw-payment', 'sw-payment-absolute', 'sw-surcharge'])) {
                continue;
            }

            if ($currentDetail->getPrice() > 0) {
                $paymentCosts += $currentDetail->getPrice();
            }
        }

        $payment = [
            'total' => round($paymentCosts, 6),
            'type' => $this->code,
            'isPayed' => (bool) $this->payment->getIsPayed(),
        ];

        if ($payment['isPayed']) {
            $payment['amountPayed'] = $this->order->getInvoiceAmount();
        }

        return array_merge($payment, $this->getAdditionalPaymentInformation());
    }

    /**
     * @throws Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     * @throws \Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException
     *
     * @return bool
     */
    protected function checkPaymentStatus()
    {
//        if ($this->payment->getIsPayed() && $this->order->getPaymentStatus()->getId() != 12) {
//            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException(
//                'payment status not cleared::manual review necessary::current status ' . $this->order->getPaymentStatus()->getId()
//            );
//        }

        return true;
    }

    /**
     * get additional payment information if necessary
     *  possible additional payment information are:
     *      cardAlias (string)
     *      resToken (string)
     *      contractAccount (string)
     *      bankOwner (string)
     *      bankName (string)
     *      iban (string)
     *      bic (string)
     *
     * @return array
     */
    protected function getAdditionalPaymentInformation()
    {
        return [];
    }
}
