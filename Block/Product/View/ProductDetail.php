<?php

namespace Payapi\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;

class ProductDetail extends AbstractProduct
{
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Payapi\Catalog\Block\InstantBuyBlock $instantBuyBlock,
        \Payapi\CheckoutPayment\Helper\SecureFormHelper $secureFormHelper,
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalculation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->instantBuyBlock     = $instantBuyBlock;
        $this->secureFormHelper    = $secureFormHelper;
        $this->secureformData      = false;
        $this->messageManager      = $messageManager;
        $this->stockItemRepository = $stockItemRepository;
        $this->storeManager        = $storeManager;
        $this->taxCalculation      = $taxCalculation;
        $this->scopeConfig         = $scopeConfig;
        $this->productRepository   = $productRepository;
        $this->logger              = $logger;
    }

    public function checkPayApiConfiguration()
    {
        $this->logger->debug("checkPayApiConfiguration");
        if ($this->instantBuyBlock->checkPayApiConfiguration()) {
            
            $consumerIp = $this->getRequest()->getQueryValue('consumerIp');
            $this->logger->debug("Consumer IP: ".$consumerIp);
            if (isset($consumerIp)) {
                $this->checkCurrency();
                $param = ['qty' => $this->getQty()];
                $opts  = $this->getMandatoryValues();

                if (!empty($opts)) {
                    $param['options'] = $opts;
                }

                $this->secureformData = $this->secureFormHelper->getInstantBuySecureForm(
                    $this->getSimpleProductId(),
                    $param,
                    $consumerIp
                );
            } else {
                if ($this->instantBuyBlock->getIsInstantBuyEnabled()
                    && $this->getRequest()->getQueryValue('fngr')) {
                    if ($this->checkMandatoryFields()) {
                        $this->messageManager->addNotice(__('Please specify product option(s).'));
                        $this->generateStockMessages();
                    }
                }

            }
            return true;
        }
        return false;
    }

    public function getQty()
    {
        $val = $this->getRequest()->getQueryValue('qty');
        if ($val && is_numeric($val)) {
            return (int) ($val);
        }

        return 1;
    }

    public function checkCurrency()
    {
        $val = $this->getRequest()->getQueryValue('currency');
        if($val) {
            $this->logger->debug("Not base currency: ". $val);
            //Change current currency to $val before generate metadata. Check if prices are correct
        }
    }

    public function getSimpleProductId()
    {

        $product = $this->getProduct();
        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            $superAttrs = $this->getSuperAttributes();
            $keys       = [];
            $usedProds  = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($product->getTypeInstance()->getConfigurableAttributesAsArray($product) as $it) {
                $keys[] = $it["attribute_id"];
            }
            foreach ($usedProds as $simple) {
                $isThisProduct = true;
                foreach ($keys as $k) {
                    $atr   = $product->getResource()->getAttribute($k);
                    $myval = $atr->getFrontend()->getValue($simple);
                    if ($atr->usesSource()) {
                        $myval = $atr->getSource()->getOptionId($myval);
                    }
                    $isThisProduct = $isThisProduct && $superAttrs[$atr->getAttributeId()] == $myval;
                }

                if ($isThisProduct) {
                    $this->logger->debug("GET SIMPLEPRODUCTID. THIS IS THE PRODUCT ID: " . $simple->getId());
                    return $simple->getId();
                }
            }
        }

        return $this->getProduct()->getId();
    }
    public function getSuperAttributes()
    {
        $val = $this->getRequest()->getQueryValue('super_attribute');
        if ($val) {
            return $val;
        }

        return [];
    }

    public function getMandatoryValues()
    {
        if ($this->getProduct()) {
            $customOptions = $this->getProduct()->getProductOptionsCollection();
            if ($customOptions) {
                $val = $this->getRequest()->getQueryValue('options');
                if ($val) {
                    return $val;
                }
            }
        }
        return [];
    }

    public function getSecureFormData()
    {
        if ($this->secureformData) {
            return json_decode(json_encode($this->secureformData));
        }
        return false;
    }

    public function checkMandatoryFields()
    {
        if ($this->getProduct()) {
            $customOptions = $this->getProduct()->getProductOptionsCollection();
            if ($customOptions) {
                foreach ($customOptions as $o) {
                    if ($o->getIsRequire()) {
                        // or another title of option
                        return 1;
                    }
                }
            }
        }
        return 0;
    }

    public function getExtraData()
    {
        if ($this->getSecureFormData()) {
            return 'quote=' . $this->getSecureFormData()->order->referenceId;
        }
        return "";
    }

    public function getInstantBuyBlock()
    {
        return $this->instantBuyBlock;
    }

    public function getStockItem()
    {
        if (!isset($this->stockItem)) {
            $this->stockItem = $this->stockItemRepository->get($this->getProduct()->getId());
        }
        return $this->stockItem;
    }

    public function generateStockMessages()
    {
        $index = -1;

        $stockItem = $this->getStockItem();
        $curQty    = $this->getQty();

        if ($curQty > $stockItem->getQty()) {
            $index = 0;
        }
        if ($curQty < $stockItem->getMinSaleQty()) {
            $index = 1;
        } else if ($curQty > $stockItem->getMaxSaleQty()) {
            $index = 2;
        }

        $msg = $this->getStockMessage($index);
        if ($msg != '') {
            $this->messageManager->addError($msg);
            return true;
        }
        return false;
    }

    public function getStockMessage($index, $withValues = true)
    {
        $message = '';
        switch ($index) {
            case 0:
                $message = __('We don\'t have as many "%1" as you requested.', $this->getProduct()->getName());
                break;
            case 1:
                if ($withValues) {
                    $message = __("The fewest you may purchase is %1.", $this->getStockItem()->getMinSaleQty());
                } else {
                    $message = __("The fewest you may purchase is %1.");
                }
                break;
            case 2:
                if ($withValues) {
                    $message = __("The most you may purchase is %1.", $this->getStockItem()->getMaxSaleQty());
                } else {
                    $message = __("The most you may purchase is %1.");
                }
                break;

            default:
                break;
        }
        return $message;
    }

    public function getStockForConfigurableProduct()
    {
        $resul   = [];
        $product = $this->getProduct();
        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            //Is a configurable product with subproducts

            $this->logger->debug("IS Configurable PRODUCT");
            $keys      = [];
            $usedProds = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($product->getTypeInstance()->getConfigurableAttributesAsArray($product) as $it) {
                $keys[] = $it["attribute_id"];
            }

            $resul["order"] = $keys;
            $resul["attrs"] = [];

            foreach ($usedProds as $simple) {
                $strAtrs = "";
                foreach ($keys as $k) {
                    $atr   = $product->getResource()->getAttribute($k);
                    $myval = $atr->getFrontend()->getValue($simple);
                    if ($atr->usesSource()) {
                        $myval = $atr->getSource()->getOptionId($myval);
                    }
                    $strAtrs = $strAtrs . $atr->getAttributeId() . ":" . $myval . "&";
                }
                $stockItem                = $this->stockItemRepository->get($simple->getId());
                $resul["attrs"][$strAtrs] = [($stockItem->getQty()) ? $stockItem->getQty() : 0,
                    $stockItem->getMinSaleQty(),
                    $stockItem->getMaxSaleQty(),
                    $simple->getId(),
                    $simple->getPriceInfo()->getPrice('final_price')->getAmount()->getValue()
                ];
            }
        }
        $strResul = json_encode($resul);
        $this->logger->debug($strResul);
        return $strResul;
    }

    public function escapeHtml($message, $allow = null)
    {
        $escaped = htmlentities($message);
        $this->logger->debug($escaped);
        return $escaped;
    }

    public function escapeUrl($message)
    {
        return urlencode($message);
    }


    public function getPriceInclTax()
    {
        $product = $this->getProduct(); 
        $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        $this->logger->debug("PriceeeeeeeeInclTax ".$price);
        return $price;         
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    } 

    public function isPartialPaymentUrl() {
        $val = $this->getRequest()->getQueryValue('partialPayment');
        $this->logger->debug("PARTIAL PAYMENT: ".$val);
        if ($val && is_numeric($val)) {
            return ((int) ($val)) == 1;
        }

        return false;
    }
}
