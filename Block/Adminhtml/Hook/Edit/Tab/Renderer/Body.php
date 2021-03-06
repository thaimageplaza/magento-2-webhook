<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Webhook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Webhook\Block\Adminhtml\Hook\Edit\Tab\Renderer;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttr;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\ResourceModel\Quote;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as CreditmemoResource;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;
use Magento\Sales\Model\ResourceModel\Order\Status\History as OrderStatusResource;
use Mageplaza\Webhook\Block\Adminhtml\LiquidFilters;
use Mageplaza\Webhook\Model\Config\Source\HookType;
use Mageplaza\Webhook\Model\HookFactory;
use Magento\Sales\Model\ResourceModel\Order\Item as ItemResource;
use Magento\Sales\Model\ResourceModel\Order\Address as AddressResource;

/**
 * Class Body
 * @package Mageplaza\Webhook\Block\Adminhtml\Hook\Edit\Tab\Renderer
 */
class Body extends Element implements RendererInterface
{
    /**
     * @var string $_template
     */
    protected $_template = 'Mageplaza_Webhook::hook/body.phtml';

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var LiquidFilters
     */
    protected $liquidFilters;

    /**
     * @var InvoiceResource
     */
    protected $invoiceResource;

    /**
     * @var ShipmentResource
     */
    protected $shipmentResource;

    /**
     * @var CreditmemoResource
     */
    protected $creditmemoResource;

    /**
     * @var HookFactory
     */
    protected $hookFactory;

    /**
     * @var OrderStatusResource
     */
    protected $orderStatusResource;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * @var CatalogEavAttr
     */
    protected $catalogEavAttribute;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var Quote
     */
    protected $quoteResource;

    /**
     * @var ItemResource
     */
    protected $itemResource;
    /**
     * @var AddressResource
     */
    protected $addressResource;

    /**
     * Body constructor.
     *
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param InvoiceResource $invoiceResource
     * @param ShipmentResource $shipmentResource
     * @param CreditmemoResource $creditmemoResource
     * @param ItemResource $itemResource
     * @param AddressResource $addressResource
     * @param OrderStatusResource $orderStatusResource
     * @param CustomerResource $customerResource
     * @param Quote $quoteResource
     * @param CatalogEavAttr $catalogEavAttribute
     * @param CategoryFactory $categoryFactory
     * @param LiquidFilters $liquidFilters
     * @param HookFactory $hookFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        InvoiceResource $invoiceResource,
        ShipmentResource $shipmentResource,
        CreditmemoResource $creditmemoResource,
        ItemResource $itemResource,
        AddressResource $addressResource,
        OrderStatusResource $orderStatusResource,
        CustomerResource $customerResource,
        Quote $quoteResource,
        CatalogEavAttr $catalogEavAttribute,
        CategoryFactory $categoryFactory,
        LiquidFilters $liquidFilters,
        HookFactory $hookFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->liquidFilters       = $liquidFilters;
        $this->orderFactory        = $orderFactory;
        $this->invoiceResource     = $invoiceResource;
        $this->shipmentResource    = $shipmentResource;
        $this->itemResource        = $itemResource;
        $this->addressResource     = $addressResource;
        $this->creditmemoResource  = $creditmemoResource;
        $this->hookFactory         = $hookFactory;
        $this->orderStatusResource = $orderStatusResource;
        $this->customerResource    = $customerResource;
        $this->catalogEavAttribute = $catalogEavAttribute;
        $this->categoryFactory     = $categoryFactory;
        $this->quoteResource       = $quoteResource;
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->_element = $element;
        $html           = $this->toHtml();

        return $html;
    }

    /**
     * @return array
     */

    public function getHookType()
    {
        $type = $this->_request->getParam('type');
        if (!$type) {
            $hookId = $this->getRequest()->getParam('hook_id');
            $hook   = $this->hookFactory->create()->load($hookId);
            $type   = $hook->getHookType();
        }
        if (!$type) {
            $type = 'order';
        }

        return $type;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getHookAttrCollection()
    {
        $hookType = $this->getHookType();

        switch ($hookType) {
            case HookType::NEW_ORDER_COMMENT:
                $collectionData = $this->orderStatusResource->getConnection()
                    ->describeTable($this->orderStatusResource->getMainTable());
                $attrCollection = $this->getAttrCollectionFromDb($collectionData);
                break;
            case HookType::NEW_INVOICE:
                $collectionDataInvoice = $this->invoiceResource->getConnection()
                    ->describeTable($this->invoiceResource->getMainTable());
                $attrCollectionInvoice = $this->getAttrCollectionFromDb($collectionDataInvoice);
                $collectionDataOrder = $this->orderFactory->create()->getResource()->getConnection()
                    ->describeTable($this->orderFactory->create()->getResource()->getMainTable());
                $attrCollectionOrder = $this->getAttrCollectionFromDb($collectionDataOrder);

                $collectionDataItems = $this->itemResource->getConnection()
                    ->describeTable($this->itemResource->getMainTable());
                $attrCollectionItems = $this->getAttrCollectionFromDb($collectionDataItems);

                $collectionDataAddress = $this->addressResource->getConnection()
                    ->describeTable($this->addressResource->getMainTable());
                $attrCollectionAddress = $this->getAttrCollectionFromDb($collectionDataAddress);
                $attrCollection = [
                    'item'                       => $attrCollectionInvoice,
                    'item.order'                 => $attrCollectionOrder,
                    'item.order.product'         => $attrCollectionItems,
                    'item.order.shippingAddress' => $attrCollectionAddress,
                    'item.order.billingAddress'  => $attrCollectionAddress
                ];
                break;
            case HookType::NEW_SHIPMENT:
                $collectionDataShipment = $this->shipmentResource->getConnection()
                    ->describeTable($this->shipmentResource->getMainTable());
                $attrCollectionShipment = $this->getAttrCollectionFromDb($collectionDataShipment);
                $collectionDataOrder = $this->orderFactory->create()->getResource()->getConnection()
                    ->describeTable($this->orderFactory->create()->getResource()->getMainTable());
                $attrCollectionOrder = $this->getAttrCollectionFromDb($collectionDataOrder);

                $collectionDataItems = $this->itemResource->getConnection()
                    ->describeTable($this->itemResource->getMainTable());
                $attrCollectionItems = $this->getAttrCollectionFromDb($collectionDataItems);

                $collectionDataAddress = $this->addressResource->getConnection()
                    ->describeTable($this->addressResource->getMainTable());
                $attrCollectionAddress = $this->getAttrCollectionFromDb($collectionDataAddress);
                $attrCollection = [
                    'item'                       => $attrCollectionShipment,
                    'item.order'                 => $attrCollectionOrder,
                    'item.order.product'         => $attrCollectionItems,
                    'item.order.shippingAddress' => $attrCollectionAddress,
                    'item.order.billingAddress'  => $attrCollectionAddress
                ];
                break;
            case HookType::NEW_CREDITMEMO:
                $collectionData = $this->creditmemoResource->getConnection()
                    ->describeTable($this->creditmemoResource->getMainTable());
                $attrCollection = $this->getAttrCollectionFromDb($collectionData);
                break;
            case HookType::NEW_CUSTOMER:
            case HookType::UPDATE_CUSTOMER:
            case HookType::DELETE_CUSTOMER:
            case HookType::CUSTOMER_LOGIN:
                $collectionData = $this->customerResource->loadAllAttributes()->getSortedAttributes();
                $attrCollection = $this->getAttrCollectionFromEav($collectionData);
                break;
            case HookType::NEW_PRODUCT:
            case HookType::UPDATE_PRODUCT:
            case HookType::DELETE_PRODUCT:
                $collectionData = $this->catalogEavAttribute->getCollection()
                    ->addFieldToFilter(AttributeSet::KEY_ENTITY_TYPE_ID, 4);
                $attrCollection = $this->getAttrCollectionFromEav($collectionData);
                break;
            case HookType::NEW_CATEGORY:
            case HookType::UPDATE_CATEGORY:
            case HookType::DELETE_CATEGORY:
                $collectionData = $this->categoryFactory->create()->getAttributes();
                $attrCollection = $this->getAttrCollectionFromEav($collectionData);
                break;
            case HookType::ABANDONED_CART:
                $collectionData = $this->quoteResource->getConnection()
                    ->describeTable($this->quoteResource->getMainTable());
                $attrCollection = $this->getAttrCollectionFromDb($collectionData);
                break;
            default:
                $collectionDataOrder = $this->orderFactory->create()->getResource()->getConnection()
                    ->describeTable($this->orderFactory->create()->getResource()->getMainTable());
                $attrCollectionOrder = $this->getAttrCollectionFromDb($collectionDataOrder);

                $collectionDataItems = $this->itemResource->getConnection()
                    ->describeTable($this->itemResource->getMainTable());
                $attrCollectionItems = $this->getAttrCollectionFromDb($collectionDataItems);

                $collectionDataAddress = $this->addressResource->getConnection()
                    ->describeTable($this->addressResource->getMainTable());
                $attrCollectionAddress = $this->getAttrCollectionFromDb($collectionDataAddress);

                $attrCollection = [
                    'item'                 => $attrCollectionOrder,
                    'item.product'         => $attrCollectionItems,
                    'item.shippingAddress' => $attrCollectionAddress,
                    'item.billingAddress'  => $attrCollectionAddress
                ];

                break;
        }

        if (!array_key_exists('item', $attrCollection)) {
            $attrCollection = ['item' => $attrCollection];
        }

        return $attrCollection;
    }

    /**
     * @param $collection
     *
     * @return array
     */
    protected function getAttrCollectionFromDb($collection)
    {
        $attrCollection = [];
        foreach ($collection as $item) {
            $attrCollection[] = new DataObject([
                'name'  => $item['COLUMN_NAME'],
                'title' => ucwords(str_replace('_', ' ', $item['COLUMN_NAME']))
            ]);
        }

        return $attrCollection;
    }

    /**
     * @param $collection
     *
     * @return array
     */
    protected function getAttrCollectionFromEav($collection)
    {
        $attrCollection = [];
        foreach ($collection as $item) {
            $attrCollection[] = new DataObject([
                'name'  => $item->getAttributeCode(),
                'title' => $item->getDefaultFrontendLabel()
            ]);
        }

        return $attrCollection;
    }

    /**
     * @return array
     */
    public function getModifier()
    {
        return $this->liquidFilters->getFilters();
    }
}
