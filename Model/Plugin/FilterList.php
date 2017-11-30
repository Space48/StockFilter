<?php

namespace Space48\StockFilter\Model\Plugin;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\CatalogInventory\Model\Configuration;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Layer\FilterList\Interceptor;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Category;
use Magento\CatalogInventory\Model\Stock;
use Space48\StockFilter\Model\Source\Position;

class FilterList
{
    const CONFIG_ENABLED_XML_PATH   = 's48_stockfilter/settings/enabled';
    const CONFIG_POSITION_XML_PATH  = 's48_stockfilter/settings/position';
    const STOCK_FILTER_CLASS = 'Space48\StockFilter\Model\Layer\Filter\Stock';
    /**
     * @var \Magento\Framework\ObjectManager
     */
    protected $_objectManager;
    /**
     * @var \Magento\Catalog\Model\Layer
     */
    protected $_layer;
    /**
     * @var \Magento\Framework\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\CatalogInventory\Model\ResourceModel\Stock\Status
     */
    protected $_stockResource;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Status $stockResource
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        Status $stockResource,
        ScopeConfigInterface $scopeConfig

    ) {
        $this->_storeManager = $storeManager;
        $this->_objectManager = $objectManager;
        $this->_stockResource = $stockResource;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        $outOfStockEnabled = $this->_scopeConfig->isSetFlag(
            Configuration::XML_PATH_DISPLAY_PRODUCT_STOCK_STATUS,
            ScopeInterface::SCOPE_STORE
        );

        $extensionEnabled = $this->_scopeConfig->isSetFlag(
            self::CONFIG_ENABLED_XML_PATH,
            ScopeInterface::SCOPE_STORE
        );

        return $outOfStockEnabled && $extensionEnabled;
    }

    /**
     * @param \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList
     * @param \Magento\Catalog\Model\Layer $layer
     * @return array
     */
    public function beforeGetFilters(
        Interceptor $filterList,
        Layer $layer
    ) {
        $this->_layer = $layer;
        if ($this->isEnabled()) {
            $collection = $layer->getProductCollection();
            $websiteId = $this->_storeManager->getStore(
                $collection->getStoreId()
            )->getWebsiteId();

            $this->_addStockStatusToSelect($collection->getSelect(), $websiteId);
        }
        return array($layer);
    }

    /**
     * @param \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList
     * @param array $filters
     * @return array
     */
    public function afterGetFilters(
        Interceptor $filterList,
        array $filters
    ) {
        if ($this->isEnabled()) {
            $position = $this->getFilterPosition();
            $stockFilter = $this->getStockFilter();
            switch ($position) {
                case Position::POSITION_BOTTOM:
                    $filters[] = $this->getStockFilter();
                    break;
                case Position::POSITION_TOP:
                    array_unshift($filters, $stockFilter);
                    break;
                case Position::POSITION_AFTER_CATEGORY:
                    $processed = [];
                    $stockFilterAdded = false;
                    foreach ($filters as $key => $value) {
                        $processed[] = $value;
                        if ($value instanceof Category
                            || $value instanceof Category
                        ) {
                            $processed[] = $stockFilter;
                            $stockFilterAdded = true;
                        }
                    }
                    $filters = $processed;
                    if (!$stockFilterAdded) {
                        array_unshift($filters, $stockFilter);
                    }
                    break;
            }
        }
        return $filters;
    }

    /**
     * @return \Space48\StockFilter\Model\Layer\Filter\Stock
     */
    public function getStockFilter()
    {
        $filter = $this->_objectManager->create(
            self::STOCK_FILTER_CLASS,
            ['layer' => $this->_layer]
        );
        return $filter;
    }


    /**
     * @param \Zend_Db_Select $select
     * @param $websiteId
     * @return $this
     */
    protected function _addStockStatusToSelect(\Zend_Db_Select $select, $websiteId)
    {
        $from = $select->getPart(\Zend_Db_Select::FROM);
        if (!isset($from['stock_status_index'])) {
            $joinCondition = $this->_stockResource->getConnection()->quoteInto(
                'e.entity_id = stock_status_index.product_id' . ' AND stock_status_index.website_id = ?',
                $websiteId
            );

            $joinCondition .= $this->_stockResource->getConnection()->quoteInto(
                ' AND stock_status_index.stock_id = ?',
                Stock::DEFAULT_STOCK_ID
            );
        }
        return $this;
    }
    public function getFilterPosition()
    {
        return $this->_scopeConfig->getValue(
            self::CONFIG_POSITION_XML_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }
}
