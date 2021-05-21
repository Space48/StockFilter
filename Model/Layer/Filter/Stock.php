<?php

namespace Space48\StockFilter\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\Stock as CatalogInventoryStock;
use Space48\StockFilter\Model\ResourceModel\Elasticsearch\Adapter\BatchDataMapper\StockFieldsProvider;

class Stock extends AbstractFilter
{
    const IN_STOCK_COLLECTION_FLAG = 's48_stock_filter_applied';
    const CONFIG_FILTER_LABEL_PATH = 's48_stockfilter/settings/label';
    const CONFIG_URL_PARAM_PATH    = 's48_stockfilter/settings/url_param';

    protected $_activeFilter = false;
    protected $_requestVar = 'in-stock';
    protected $_scopeConfig;

    /**
     * @param ItemFactory $filterItemFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param array $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $data
        );

        $this->_requestVar = $this->_scopeConfig->getValue(
            self::CONFIG_URL_PARAM_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param RequestInterface $request
     * @return $this
     */
    public function apply(RequestInterface $request)
    {
        $filter = $request->getParam($this->getRequestVar(), null);
        if (is_null($filter)) {
            return $this;
        }
        $this->_activeFilter = true;
        $filter = (int)$filter;
        $collection = $this->getLayer()->getProductCollection();
        $collection->setFlag(self::IN_STOCK_COLLECTION_FLAG, true);

        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->getLabel($filter), $filter)
        );

        if ($request->getParam($this->_requestVar, null) > 0
            && $this->isSearchEngineElasticsearch()
            && $this->isEnabledInStockFilterOnElasticSide()
        ) {
            $collection->addFieldToFilter(StockFieldsProvider::FIELD_NAME, 1);
        } else {
            $collection->getSelect()->where(
            'stock_status_index.stock_status = ?',
                $filter
            );
        }

        return $this;
    }
    /**
     * Get filter name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_scopeConfig->getValue(
            self::CONFIG_FILTER_LABEL_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get data array for building status filter items
     *
     * @return array
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function _getItemsData()
    {
        if ($this->getLayer()->getProductCollection()
            ->getFlag(
                self::IN_STOCK_COLLECTION_FLAG
            )
        ) {
            return [];
        }

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $optionsFacetedData = $productCollection->getFacetedData(StockFieldsProvider::FIELD_NAME);

        $data = [];
        foreach ($this->getStatuses() as $status) {
            if ($this->isSearchEngineElasticsearch()) {
                $count = $this->getOptionCount($status, $optionsFacetedData);
            } else {
                $count = $this->getProductsCount($status);
            }

            $data[] = [
                'label' => $this->getLabel($status),
                'value' => $status,
                'count' => $count
            ];
        }

        return $data;
    }

    /**
     * Retrieve count of the options
     *
     * @param int|string $value
     * @param array $optionsFacetedData
     * @return int
     */
    private function getOptionCount($value, array $optionsFacetedData): int
    {
        return isset($optionsFacetedData[$value]['count'])
            ? (int)$optionsFacetedData[$value]['count']
            : 0;
    }

    /**
     * get available statuses
     * @return array
     */
    public function getStatuses()
    {
        return [
            CatalogInventoryStock::STOCK_IN_STOCK,
        ];
    }
    /**
     * @return array
     */
    public function getLabels()
    {
        return [
            CatalogInventoryStock::STOCK_IN_STOCK => __('In Stock'),
        ];
    }
    /**
     * @param $value
     * @return string
     */
    public function getLabel($value)
    {
        $labels = $this->getLabels();
        if (isset($labels[$value])) {
            return $labels[$value];
        }
        return '';
    }

    /**
     * @param $value
     * @return string
     * @deprected This method shows incorrect count, left for compatibility with fulltextsearch mysql engine.
     */
    public function getProductsCount($value)
    {
        $collection = $this->getLayer()->getProductCollection();
        $select = clone $collection->getSelect();
        // reset columns, order and limitation conditions
        $select->reset(\Zend_Db_Select::COLUMNS);
        $select->reset(\Zend_Db_Select::ORDER);
        $select->reset(\Zend_Db_Select::LIMIT_COUNT);
        $select->reset(\Zend_Db_Select::LIMIT_OFFSET);
        $select->where('stock_status_index.stock_status = ?', $value);
        $select->columns(
            [
                'count' => new \Zend_Db_Expr("COUNT(e.entity_id)")
            ]
        );
        return $collection->getConnection()->fetchOne($select);
    }

    /**
     * Checks if the search engine is currently configured to use any version of Elasticsearch.
     * @return bool
     */
    private function isSearchEngineElasticsearch(): bool
    {
        $searchEngine = $this->_scopeConfig->getValue('catalog/search/engine');

        return strpos($searchEngine, 'elasticsearch') !== false;
    }

    /**
     * @return bool
     */
    private function isEnabledInStockFilterOnElasticSide(): bool
    {
        return (bool) $this->_scopeConfig->getValue('s48_stockfilter/settings/enable_in_stock_filter_on_es_side');
    }
}
