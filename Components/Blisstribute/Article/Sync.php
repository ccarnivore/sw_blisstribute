<?php

require_once __DIR__ . '/../Sync.php';
require_once __DIR__ . '/SoapClient.php';
require_once __DIR__ . '/SyncMapping.php';
require_once __DIR__ . '/../Exception/ArticleNotChangedException.php';
require_once __DIR__ . '/../Exception/TransferException.php';

use Monolog\Logger;
use Shopware\Components\Model\ModelEntity;
use Shopware\CustomModels\Blisstribute\BlisstributeArticle;

/**
 * article sync service
 *
 * @author    Julian Engler
 * @copyright Copyright (c) 2016
 *
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Article_Sync extends Shopware_Components_Blisstribute_Sync
{
    /**
     * count limit for articles to transfer to blisstribute
     *
     * @var int
     */
    const TRANSFER_LIMIT = 10;

    /**
     * article sync task name
     *
     * @var string
     */
    protected $taskName = 'article_sync';

    /**
     * sync all transferable articles to blisstribute
     *
     * @throws Exception
     * @throws Shopware_Components_Blisstribute_Exception_ArticleNotChangedException
     *
     * @return bool
     */
    public function processBatchArticleSync()
    {
        $this->logMessage('start batch sync', __FUNCTION__);

        try {
            $this->taskName .= '::batch';
            $this->lockTask();
        } catch (Exception $ex) {
            $this->logMessage('exception occurred::' . $ex->getMessage(), __FUNCTION__, Logger::ERROR);

            return false;
        }

        try {
            $startDate = new DateTime();
            $articleRepository = $this->modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeArticle');
            $articleCollection = $articleRepository->findTransferableArticles($startDate);

            $page = 1;
            $articleDataCollection = [];
            $articleSyncCollection = [];

            $status = true;
            while (count($articleCollection) > 0) {
                $this->logMessage('start::page ' . $page, __FUNCTION__);

                foreach ($articleCollection as $currentArticle) {
                    $this->logMessage('start worker with article::' . $currentArticle->getId(), __FUNCTION__, Logger::DEBUG);

                    if ($currentArticle->getArticle() == null) {
                        $this->logMessage('article invalid - skipping::' . $currentArticle->getId(), __FUNCTION__, Logger::DEBUG);
                        $currentArticle->setDeleted(true);
                        $currentArticle->setTries(0);
                        $currentArticle->setComment(null);
                        $currentArticle->setTriggerSync(false);
                        $this->modelManager->persist($currentArticle);

                        continue;
                    }

                    try {
                        try {
                            $articleData = $this->initializeModelMapping($currentArticle);
                            if (count($articleData) <= 0) {
                                $this->logMessage('article mapping failed::' . $currentArticle->getId(), __FUNCTION__, Logger::ERROR);
                                $currentArticle->setTries($currentArticle->getTries() + 1)
                                    ->setComment('Fehler beim Erstellen der zu übermittelnden Daten')
                                    ->setLastCronAt(new DateTime())
                                    ->setSyncHash('');

                                $this->modelManager->persist($currentArticle);
                                continue;
                            }

                            $currentArticle->setTries(0)
                                ->setTriggerSync(false)
                                ->setComment(null)
                                ->setLastCronAt(new DateTime())
                                ->setSyncHash(trim(sha1(json_encode($articleData))));

                            $this->modelManager->persist($currentArticle);
                        } catch (Shopware_Components_Blisstribute_Exception_ArticleNotChangedException $ex) {
                            $this->logMessage(
                                'no change detected::' . $currentArticle->getId(),
                                __FUNCTION__,
                                Logger::INFO
                            );

                            $currentArticle->setTriggerSync(false)
                                ->setLastCronAt(new DateTime())
                                ->setComment($ex->getMessage())
                                ->setTries($currentArticle->getTries() + 1);

                            $this->modelManager->persist($currentArticle);
                            continue;
                        } catch (Exception $ex) {
                            $this->logMessage(
                                'mapping failure::' . $currentArticle->getId(),
                                __FUNCTION__,
                                Logger::ERROR
                            );

                            $currentArticle->setTries($currentArticle->getTries() + 1)
                                ->setComment($ex->getMessage())
                                ->setLastCronAt(new DateTime())
                                ->setSyncHash('');

                            $this->modelManager->persist($currentArticle);
                            continue;
                        }
                    } catch (\Doctrine\ORM\EntityNotFoundException $ex) {
                        $this->logMessage(
                            'exception occurred::' . $ex->getMessage() . '::' . $ex->getTraceAsString(),
                            __FUNCTION__,
                            Logger::ERROR
                        );

                        continue;
                    }

                    $articleDataCollection[] = $articleData;
                    $articleSyncCollection[] = $currentArticle;

                    if (count($articleDataCollection) >= static::TRANSFER_LIMIT) {
                        $this->transferBatchCollection($articleDataCollection, $articleSyncCollection);
                        $articleDataCollection = [];
                        $articleSyncCollection = [];
                    }
                }

                $this->modelManager->flush();

                ++$page;
                $articleCollection = $articleRepository->findTransferableArticles($startDate);

                $this->logMessage('end::page ' . $page, __FUNCTION__);
            }

            if (count($articleDataCollection) > 0) {
                $this->transferBatchCollection($articleDataCollection, $articleSyncCollection);
            }

            $this->logMessage('end batch sync', __FUNCTION__);
        } catch (Exception $ex) {
            $this->logMessage(
                'exception occurred::' . $ex->getMessage() . '::' . $ex->getTraceAsString(),
                __FUNCTION__,
                Logger::ERROR
            );

            $status = false;
        }

        try {
            $this->modelManager->flush();
        } catch (Exception $ex) {
            $this->logMessage(
                'doctrine flush failed::' . $ex->getMessage() . '::' . $ex->getTraceAsString(),
                __FUNCTION__,
                Logger::ERROR
            );

            $status = false;
        }

        $this->unlockTask();

        return $status;
    }

    /**
     * do single object sync to blisstribute
     *
     * @param BlisstributeArticle $blisstributeArticle
     *
     * @return bool
     */
    public function processSingleArticleSync(BlisstributeArticle $blisstributeArticle)
    {
        if (!$blisstributeArticle->isTriggerSync()) {
            return false;
        }

        if ($blisstributeArticle->getArticle() == null || $blisstributeArticle->getArticle() == null) {
            $blisstributeArticle->setDeleted(true);
            $blisstributeArticle->setTries(0);
            $blisstributeArticle->setComment(null);
            $blisstributeArticle->setTriggerSync(false);
            $this->modelManager->persist($blisstributeArticle);
            $this->modelManager->flush();

            return true;
        }

        $this->taskName .= '::single::' . $blisstributeArticle->getId();
        $this->lockTask();

        $result = true;

        try {
            $articleData = $this->initializeModelMapping($blisstributeArticle);
            if (empty($articleData)) {
                $blisstributeArticle->setTriggerSync(true)
                    ->setTries($blisstributeArticle->getTries() + 1)
                    ->setComment('Fehler bei der Artikel Validierung.')
                    ->setSyncHash('');
            } else {
                $blisstributeArticle->setTriggerSync(false)
                    ->setTries(0)
                    ->setComment(null);

                $this->transferBatchCollection([$articleData], [$blisstributeArticle]);
            }
        } catch (Shopware_Components_Blisstribute_Exception_ArticleNotChangedException $ex) {
            $this->logMessage(
                'article not changed::' . $ex->getMessage(),
                __FUNCTION__,
                Logger::ERROR
            );

            $blisstributeArticle->setTriggerSync(false);

            $result = false;
            $this->setLastError('Der Artikel weißt keine Änderungen auf.');
        } catch (Exception $ex) {
            $this->logMessage($ex->getMessage() . $ex->getTraceAsString(), __FUNCTION__, Logger::ERROR);
            $this->logMessage('exception occured::' . $ex->getMessage(), __FUNCTION__, Logger::ERROR);

            $blisstributeArticle->setTries($blisstributeArticle->getTries() + 1)
                ->setComment($ex->getMessage())
                ->setSyncHash('');

            $result = false;
            $this->setLastError('Es ist ein Fehler aufgetreten, beim Übertragen des Artikels zu Blisstribute.');
        }

        $this->modelManager->persist($blisstributeArticle);
        $this->modelManager->flush();

        $this->unlockTask();

        return $result;
    }

    /**
     * process article sync
     *
     * @param array $articleCollection
     *
     * @return array
     */
    protected function processArticleSync(array $articleCollection)
    {
        $this->logMessage('start sync::count ' . count($articleCollection), __FUNCTION__);

        $soapClient = new Shopware_Components_Blisstribute_Article_SoapClient($this->config);
        $result = $soapClient->syncArticleCollection($articleCollection);

        $this->logMessage('end sync', __FUNCTION__);

        return $result;
    }

    /**
     * initialize article mapping
     *
     * @param ModelEntity $modelEntity
     *
     * @throws Shopware_Components_Blisstribute_Exception_ArticleNotChangedException
     *
     * @return array
     */
    protected function initializeModelMapping(ModelEntity $modelEntity)
    {
        /** @var BlisstributeArticle $modelEntity */
        $this->logMessage('start blisstribute article id::' . $modelEntity->getId(), __FUNCTION__);
        $this->logMessage('start sw article id::' . $modelEntity->getArticle()->getId(), __FUNCTION__);

        $syncMapping = new Shopware_Components_Blisstribute_Article_SyncMapping();
        $syncMapping->setModelEntity($modelEntity);

        try {
            $articleData = $syncMapping->buildMapping();
            $this->logMessage('articleData::' . json_encode($articleData), __FUNCTION__);
            $checksum = trim(sha1(json_encode($articleData)));
            if (trim($modelEntity->getSyncHash()) == $checksum) {
                throw new Shopware_Components_Blisstribute_Exception_ArticleNotChangedException('article not changed');
            }
        } catch (Exception $ex) {
            $this->logWarn($ex->getMessage() . $ex->getTraceAsString());
            throw $ex;
        }

        $this->logMessage('done::' . $modelEntity->getArticle()->getMainDetail()->getNumber(), __FUNCTION__);

        return $articleData;
    }

    /**
     * transfer article batch to blisstribute
     *
     * @param array                 $articleDataCollection
     * @param BlisstributeArticle[] $articleCollection
     *
     * @return void
     */
    protected function transferBatchCollection(array $articleDataCollection, array $articleCollection)
    {
        $this->logMessage('start batch transfer', __FUNCTION__);

        try {
            $response = $this->processArticleSync(['materialData' => $articleDataCollection]);

            if (!isset($response['materialConfirmationData']) || empty($response['materialConfirmationData'])) {
                throw new Shopware_Components_Blisstribute_Exception_TransferException('no or invalid response given');
            }

            $this->setBlisstributeArticleNumber($response['materialConfirmationData']);
        } catch (Exception $ex) {
            $this->logMessage('transfer failed::message ' . $ex->getMessage(), __FUNCTION__, Logger::ERROR);

            foreach ($articleCollection as $currentArticle) {
                $currentArticle->setTriggerSync(true)
                    ->setTries($currentArticle->getTries() + 1)
                    ->setComment('Fehler beim Übermitteln zu Blisstribute')
                    ->setLastCronAt(new DateTime())
                    ->setSyncHash('');

                $this->setLastError('Fehler beim Übermitteln zu Blisstribute.');
                $this->modelManager->persist($currentArticle);
            }
        }

        $this->modelManager->flush();

        $this->logMessage('end batch transfer', __FUNCTION__);
    }

    /**
     * set blisstribute article number to articles
     *
     * @param array $confirmationDataCollection
     *
     * @return void
     */
    protected function setBlisstributeArticleNumber(array $confirmationDataCollection)
    {
        $this->logDebug('start updating confirmation data ' . json_encode($confirmationDataCollection));

        foreach ($confirmationDataCollection as $currentConfirmationData) {
            try {
                $this->logDebug('start processing confirmation data set ' . json_encode($currentConfirmationData));
                if ($currentConfirmationData == null || empty($currentConfirmationData)) {
                    continue;
                }

                $sql = 'UPDATE s_articles_attributes SET blisstribute_vhs_number = :vhsArticleNumber WHERE articledetailsID = (
                  SELECT id from s_articles_details WHERE ordernumber = :articleNumber
                )';
                Shopware()->Db()->query($sql, [
                    'vhsArticleNumber' => trim($currentConfirmationData['erpArticleNumber']),
                    'articleNumber' => trim($currentConfirmationData['articleNumber']),
                ]);

                $this->logDebug('processing done for vhs article number ' . trim($currentConfirmationData['erpArticleNumber']));
            } catch (Exception $ex) {
                $this->logDebug('failed for ' . trim($currentConfirmationData['erpArticleNumber']));
                $this->logWarn($ex->getMessage());
            }
        }

        $this->logMessage('end vhs article number', __FUNCTION__);
        $this->modelManager->flush();
    }
}
