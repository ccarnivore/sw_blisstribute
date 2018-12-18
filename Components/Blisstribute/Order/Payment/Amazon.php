<?php

require_once __DIR__ . '/Abstract.php';

/**
 * amazon payment implementation
 *
 * @author    Florian Ressel
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Payment_Amazon extends Shopware_Components_Blisstribute_Order_Payment_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $code = 'amazon';
}
