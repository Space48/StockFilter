<?php

declare(strict_types=1);

namespace Space48\StockFilter\Model\Plugin;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;

class FilterListTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $filterList;
    private $scopeConfigMock;
    private $storeManagerMock;
    private $objectManagerMock;
    private $stockResourceMock;

    public function setUp()
    {

        $this->storeManagerMock = $this->getMockBuilder(StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock = $this->getMockBuilder(ObjectManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stockResourceMock = $this->getMockBuilder(Status::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->scopeConfigMock = $this->getMockForAbstractClass(
            ScopeConfigInterface::class
        );

        $this->filterList = new FilterList (
            $this->storeManagerMock,
            $this->objectManagerMock,
            $this->stockResourceMock,
            $this->scopeConfigMock
        );

    }

    public function testReturnsTrueIfModuleAndOosEnabled()
    {
        $this->scopeConfigMock->expects(
            $this->atLeastOnce()
        )->method(
            'isSetFlag'
        )->will(
            $this->returnValue(1)
        );

        $isEnabled = $this->filterList->isEnabled();
        $this->assertTrue($isEnabled);
    }

    public function testFalseTrueIfModuleAndOosDisabled()
    {
        $this->scopeConfigMock->expects(
            $this->atLeastOnce()
        )->method(
            'isSetFlag'
        )->will(
            $this->returnValue(0)
        );

        $isEnabled = $this->filterList->isEnabled();
        $this->assertFalse($isEnabled);
    }

}
