<?php

namespace Payapi\Catalog\Block;

class InstantBuyBlock extends \Magento\Framework\View\Element\Template
{

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Payapi\CheckoutPayment\Block\Adminhtml\PayapiPluginConfiguration $payapiConfig,        
        array $data = []
    ) {
        $this->payapiConfig = $payapiConfig;        
        parent::__construct($context, $data);
    }

    public function checkPayApiConfiguration()
    {
        return $this->payapiConfig->checkPayApiConfiguration();
    }

    public function getPublicId()
    {
        return $this->payapiConfig->getPublicId();
    }

    public function getApiKey()
    {
        return $this->payapiConfig->getApiKey();
    }

    public function getIsInstantBuyEnabled(){
        return $this->payapiConfig->getIsInstantBuyEnabled();
    }

    public function getIsStaging()
    {
        return $this->payapiConfig->getIsStaging();
    }

    public function getVisitorIp($checkParams = true)
    {
        return $this->payapiConfig->getVisitorIp($checkParams);
    }

    public function getPhpSdk(){
        return $this->payapiConfig->getPhpSdk();
    }

    public function getPartner() {  
        return $this->payapiConfig->getPartner();
    }
}
