<?php

require_once __DIR__ . '/Abstract.php';

/**
 * dhl shipment mapping class
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Shipment_Dhlexpress extends Shopware_Components_Blisstribute_Order_Shipment_Abstract
{
    /**
     * {@inheritdoc}
     */
    protected $code = 'DHLEXPRESS';
}
