<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * paypal payment implementation
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_PayPalPlus extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * {@inheritdoc}
     */
    protected $code = 'paypalPlus';

    /**
     * {@inheritdoc}
     */
    protected function checkPaymentStatus()
    {
        $status = parent::checkPaymentStatus();

        if (trim($this->order->getTransactionId()) == '') {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('no transaction id given');
        }

        if (strpos($this->order->getTransactionId(), 'PAYID-') === false) {
            throw new Shopware_Components_Blisstribute_Exception_OrderPaymentMappingException('the transaction id is not for paypal plus');
        }

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAdditionalPaymentInformation()
    {
        return [
            'resToken' => $this->order->getTransactionId(),
        ];
    }
}
