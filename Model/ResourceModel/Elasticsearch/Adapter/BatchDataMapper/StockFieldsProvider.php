<?php
declare(strict_types=1);

namespace Space48\StockFilter\Model\ResourceModel\Elasticsearch\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\CatalogInventory\Api\StockStatusCriteriaInterfaceFactory;

/**
 * Class StockFieldsProvider
 */
class StockFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * Field name
     */
    const FIELD_NAME = 'sp_stock';

    /**
     * @var StockStatusRepositoryInterface
     */
    private $stockStatusRepository;

    /**
     * @var StockStatusCriteriaInterfaceFactory
     */
    private $stockStatusCriteriaFactory;

    /**
     * @param StockStatusRepositoryInterface $stockStatusRepository
     * @param StockStatusCriteriaInterfaceFactory $stockStatusCriteriaFactory
     */
    public function __construct(
        StockStatusRepositoryInterface $stockStatusRepository,
        StockStatusCriteriaInterfaceFactory $stockStatusCriteriaFactory
    ) {
        $this->stockStatusRepository = $stockStatusRepository;
        $this->stockStatusCriteriaFactory = $stockStatusCriteriaFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getFields(array $productIds, $storeId)
    {
        $stockStatusCriteria = $this->stockStatusCriteriaFactory->create();
        $stockStatusCriteria->setProductsFilter($productIds);
        $stockStatusCollection = $this->stockStatusRepository->getList($stockStatusCriteria);
        $stockStatuses = $stockStatusCollection->getItems();

        $fields = [];
        foreach ($productIds as $productId) {
            if (isset($stockStatuses[$productId])) {
                /** @var \Magento\CatalogInventory\Model\Stock\Status $stockStatus */
                $stockStatus = $stockStatuses[$productId];
                $fields[$productId] = [
                    self::FIELD_NAME => $stockStatus->getStockStatus()
                ];
            }
        }

        return $fields;
    }
}
