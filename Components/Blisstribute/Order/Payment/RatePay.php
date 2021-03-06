<?php

require_once __DIR__ . '/AbstractExternalPayment.php';

/**
 * klarna payment implementation
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Payment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_RatePay
    extends Shopware_Components_Blisstribute_Order_Payment_AbstractExternalPayment
{
    /**
     * @inheritdoc
     */
    protected $code = 'ratepay';

    /**
     * @inheritdoc
     */
    protected function getAdditionalPaymentInformation()
    {
        $orderAttribute = $this->order->getAttribute();

        $reference = '';
        if (method_exists($orderAttribute, 'getRatepayDescriptor')) {
            $reference = $orderAttribute->getRatepayDescriptor();
        }

        return array(
            'token' => $this->order->getTransactionId(),
            'tokenReference' => trim($reference),
        );
    }
}
