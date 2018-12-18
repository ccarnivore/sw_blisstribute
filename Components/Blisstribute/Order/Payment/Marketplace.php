<?php

require_once __DIR__ . '/Abstract.php';

/**
 * marketplace payment implementation
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_Marketplace extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $code = 'marketplace';
}
