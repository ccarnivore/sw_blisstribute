<?php

require_once __DIR__ . '/Abstract.php';

/**
 * cash on delivery payment information
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_CashOnDelivery extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $code = 'cod';
}
