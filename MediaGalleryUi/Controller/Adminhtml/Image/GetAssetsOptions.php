<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaGalleryUi\Controller\Adminhtml\Image;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaGalleryApi\Api\SearchAssetsInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Cms\Helper\Wysiwyg\Images;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\Api\Search\FilterGroupBuilder;

/**
 * Controller getting the asset options for multiselect filter
 */
class GetAssetsOptions extends Action implements HttpGetActionInterface
{
    private const HTTP_OK = 200;
    private const HTTP_INTERNAL_ERROR = 500;
    private const HTTP_BAD_REQUEST = 400;

    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Cms::media_gallery';

    /**
     * @var SearchAssetsInterface
     */
    private $searchAssets;

    /**
     * @param SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Images
     */
    private $images;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SearchAssetsInterface $searchAssets
     * @param Context $context
     * @param LoggerInterface $logger
     * @param Images $images
     * @param Storage $storage
     */
    public function __construct(
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        SearchAssetsInterface $searchAssets,
        Context $context,
        LoggerInterface $logger,
        Images $images,
        Storage $storage
    ) {
        parent::__construct($context);

        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->searchAssets = $searchAssets;
        $this->images = $images;
        $this->storage = $storage;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $searchKey = $this->getRequest()->getParam('searchKey');
        $limit = $this->getRequest()->getParam('limit');
        $responseContent = [];

        if (!$searchKey) {
            $responseContent = [
                'success' => false,
                'message' => __('Search key is required'),
            ];
            $resultJson->setHttpResponseCode(self::HTTP_BAD_REQUEST);
            $resultJson->setData($responseContent);

            return $resultJson;
        }

        try {
            $titleFilter = $this->filterBuilder->setField('title')
                ->setConditionType('fulltext')
                ->setValue($searchKey)
                ->create();
            $searchCriteria = $this->searchCriteriaBuilder
                ->setFilterGroups([$this->filterGroupBuilder->setFilters([$titleFilter])->create()])
                ->setPageSize($limit)
                ->create();

            $assets = $this->searchAssets->execute($searchCriteria);

            if (!empty($assets)) {
                foreach ($assets as $asset) {
                    $responseContent['options'][] = [
                        'value' => $asset->getId(),
                        'label' => $asset->getTitle(),
                        'path' => $this->storage->getThumbnailUrl($this->images->getStorageRoot() . $asset->getPath())
                    ];
                    $responseContent['total'] = count($responseContent['options']);
                }
            }

            $responseCode = self::HTTP_OK;
        } catch (LocalizedException $exception) {
            $responseCode = self::HTTP_BAD_REQUEST;
            $responseContent = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        } catch (Exception $exception) {
            $this->logger->critical($exception);
            $responseCode = self::HTTP_INTERNAL_ERROR;
            $responseContent = [
                'success' => false,
                'message' => __('An error occurred on attempt to get image details.'),
            ];
        }

        $resultJson->setHttpResponseCode($responseCode);
        $resultJson->setData($responseContent);

        return $resultJson;
    }
}
