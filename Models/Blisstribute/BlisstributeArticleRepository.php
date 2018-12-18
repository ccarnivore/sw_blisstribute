<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CustomModels\Blisstribute;

use Shopware\Components\Model\ModelRepository;

/**
 * blisstribute article db repository class
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 *
 * @method BlisstributeArticle find($id, $lockMode = null, $lockVersion = null)
 */
class BlisstributeArticleRepository extends ModelRepository
{
    /**
     * max tries for cron jobs
     *
     * @var int
     */
    const MAX_SYNC_TRIES = 5;

    /**
     * page limit for export
     *
     * @var int
     */
    const PAGE_LIMIT = 50;

    /**
     * get all blisstribute articles for article id list
     *
     * @param array $articleIdCollection
     *
     * @return BlisstributeArticle[]
     */
    public function fetchByArticleIdList(array $articleIdCollection)
    {
        $builder = $this->createQueryBuilder('ba');

        return $builder->where($builder->expr()->in('ba.article', $articleIdCollection))
            ->getQuery()
            ->getResult();
    }

    /**
     * get all blisstribute articles for article id list
     *
     * @param int $articleId
     *
     * @return BlisstributeArticle
     */
    public function fetchByArticleId($articleId)
    {
        $builder = $this->createQueryBuilder('ba');

        return $builder->where('ba.article = ' . (int) $articleId)
            ->getQuery()
            ->getOneOrNullResult(1);
    }

    /**
     * get list of articles to export to blisstribute
     *
     * @param \DateTime $exportDate
     *
     * @return BlisstributeArticle[]
     */
    public function findTransferableArticles(\DateTime $exportDate)
    {
        return $this->createQueryBuilder('ba')
            ->where('ba.triggerSync = 1')
            ->andWhere('ba.tries < :tries')
            ->andWhere('ba.lastCronAt <= :lastCronAt')
            ->setParameters([
                'tries' => static::MAX_SYNC_TRIES,
                'lastCronAt' => $exportDate->format('Y-m-d H:i:s'),
            ])
            ->setMaxResults(static::PAGE_LIMIT)
            ->getQuery()
            ->getResult();
    }
}
