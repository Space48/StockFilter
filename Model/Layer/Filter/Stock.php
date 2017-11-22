<?php

namespace Space48\StockFilter\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\Stock as CatalogInventoryStock;

class Stock extends \Magento\Catalog\Model\Layer\Filter\AbstractFilter
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
        $filter = (int)(bool)$filter;
        $collection = $this->getLayer()->getProductCollection();
        $collection->setFlag(self::IN_STOCK_COLLECTION_FLAG, true);
        $collection->getSelect()->where(
            'stock_status_index.stock_status = ?',
            $filter
        );

        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->getLabel($filter), $filter)
        );

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

        $data = [];
        foreach ($this->getStatuses() as $status) {
            $data[] = [
                'label' => $this->getLabel($status),
                'value' => $status,
                'count' => $this->getProductsCount($status)
            ];
        }
        return $data;
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
}
