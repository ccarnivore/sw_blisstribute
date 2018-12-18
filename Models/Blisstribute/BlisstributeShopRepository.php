<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\NonUniqueResultException;
use Shopware\Components\Model\ModelRepository;

/**
 * blisstribute shipment mapping entity repository
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class BlisstributeShopRepository extends ModelRepository
{
    /**
     * get shipment mapping by shopware shipment id
     *
     * @param int $shopId
     *
     * @throws NonUniqueResultException
     *
     * @return null|BlisstributeShop
     */
    public function findOneByShop($shopId)
    {
        return $this->createQueryBuilder('bs')
            ->where('bs.shop = :shopId')
            ->setParameter('shopId', $shopId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
