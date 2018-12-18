<?php

namespace Shopware\Components\Api\Resource;

use Doctrine\ORM\Query\Expr\Join;
use Shopware\Components\Api\Exception as ApiException;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest;
use Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems;

/**
 * blisstribute custom api order extension resource
 *
 * @author    Conrad Gülzow
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Btorder extends Resource
{
    /**
     * @return \Shopware\Models\Order\Repository
     */
    public function getOrderRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Order\Order');
    }

    /**
     * @return \Shopware\Components\Model\ModelRepository
     */
    public function getOrderDetailRepository()
    {
        return $this->getManager()->getRepository('Shopware\Models\Order\Detail');
    }

    /**
     * @param $params array
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     *
     * @return array
     */
    public function getStateIdFromVhsId($params)
    {
        $id = (int) $params['orderStatusId'];

        if (in_array($id, [10, 15])) {
            $state = 0;
        } elseif (in_array($id, [20, 21])) {
            $state = 1;
        } elseif (in_array($id, [25, 26, 30, 31])) {
            $state = 5;
        } elseif (in_array($id, [35])) {
            $state = 3;
        } elseif (in_array($id, [40])) {
            $state = 2;
        } elseif (in_array($id, [50])) {
            $state = 4;
        } elseif (in_array($id, [60, 61, 62])) {
            $state = 4;
        } else {
            $state = 8;
        }

        $params['orderStatusId'] = $state;

        return $params;
    }

    /**
     * @param int   $orderNumber
     * @param array $params
     *
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     *
     * @return \Shopware\Models\Order\Order
     */
    public function update($orderNumber, array $params)
    {
        $this->checkPrivilege('update');

        if (trim($orderNumber) == '') {
            throw new ApiException\ParameterMissingException();
        }

        $params = $this->getStateIdFromVhsId($params);

        /** @var $order \Shopware\Models\Order\Order */
        $filters = [['property' => 'orders.number', 'expression' => '=', 'value' => $orderNumber]];
        $builder = $this->getOrderRepository()->getOrdersQueryBuilder($filters);
        $order = $builder->getQuery()->getOneOrNullResult(self::HYDRATE_OBJECT);

        if ($order == null) {
            throw new ApiException\NotFoundException('order by id ' . $orderNumber . ' not found');
        }

        $this->prepareOrderDetails($params, $orderNumber);

//        $shippingRequest = new BlisstributeShippingRequest();
//        $shippingRequest->setNumber('test')
//            ->setCarrierCode('DHL')
//            ->setTrackingCode('');
//
//        foreach (array() as $test) {
//            $shippingRequestItem = new BlisstributeShippingRequestItems();
//            $shippingRequestItem->setOrderDetail($detail)
//                ->setQuantityReturned(0);
//
//            $shippingRequest->addShippingRequestItem($shippingRequestItem);
//        }
//
//        $this->getManager()->persist($shippingRequest);

        $statusId = (int) $params['orderStatusId'];
        $status = Shopware()->Models()->getRepository('Shopware\Models\Order\Status')->findOneBy(
            [
                'id' => $statusId,
                'group' => 'state',
            ]
        );

        if (empty($status)) {
            throw new ApiException\NotFoundException('OrderStatus by id ' . $statusId . ' not found');
        }

        $order->setOrderStatus($status);
        $order->setTrackingCode(trim($params['trackingCode']));

        $violations = $this->getManager()->validate($order);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->getManager()->persist($order);
        $this->getManager()->flush();

        return $order;
    }

    /**
     * Helper method to prepare the order data
     *
     * @param array $params
     * @param $orderNumber
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException| ApiException\CustomValidationException
     */
    public function prepareOrderDetails(array $params, $orderNumber)
    {
        $details = $params['details'];
        foreach ($details as $detail) {
            $articleNumber = $detail['articleNumber'];
            if (empty($articleNumber)) {
                throw new ApiException\CustomValidationException(
                    'You need to specify the articleNumber of the order positions you want to modify'
                );
            }

            $detailModel = $this->getOrderDetailRepository()
                ->createQueryBuilder('details')
                ->innerJoin('Shopware\Models\Attribute\Article', 'attributes', Join::WITH, 'attributes.articleId = details.articleId')
                ->where('details.number = :orderNumber')
                ->andWhere('attributes.blisstributeVhsNumber = :vhsArticleNumber')
                ->setParameters([
                    'orderNumber' => $orderNumber,
                    'vhsArticleNumber' => $detail['blisstributeVhsNumber'],
                ])
                ->getQuery()
                ->getOneOrNullResult();

            if ($detailModel == null) {
                /** @var \Shopware\Models\Order\Detail $detailModel */
                $detailModel = $this->getOrderDetailRepository()
                    ->createQueryBuilder('details')
                    ->where('details.number = :orderNumber')
                    ->andWhere('details.articleNumber = :articleNumber')
                    ->setParameters([
                        'orderNumber' => $orderNumber,
                        'articleNumber' => $articleNumber,
                    ])
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($detailModel == null) {
                    throw new ApiException\NotFoundException(
                        'Detail by orderId ' . $orderNumber . ' and articleNumber ' . $articleNumber . ' not found'
                    );
                }
            }

            $detailModel->getAttribute()
                ->setBlisstributeQuantityCanceled($detail['attribute']['blisstributeQuantityCanceled'])
                ->setBlisstributeQuantityReturned($detail['attribute']['blisstributeQuantityReturned'])
                ->setBlisstributeQuantityShipped($detail['attribute']['blisstributeQuantityShipped'])
                ->setBlisstributeDateChanged($detail['attribute']['blisstributeDateChanged']);

            $this->getManager()->persist($detailModel);
        }
    }
}
