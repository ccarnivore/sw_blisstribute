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
class BlisstributeCouponRepository extends ModelRepository
{
    /**
     * get shipment mapping by shopware shipment id
     *
     * @param int $couponId
     *
     * @throws NonUniqueResultException
     *
     * @return null|BlisstributeCoupon
     */
    public function findByCoupon($couponId)
    {
        return $this->createQueryBuilder('bc')
            ->where('bc.voucher = :couponId')
            ->setParameter('couponId', $couponId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
