<?php


namespace Goodshape\Amf\Http;

use Nette\Http\RequestFactory;

class HttpRequestFactory extends RequestFactory {
    /** @var \Goodshape\Amf\Http\AMFRequestFactory */
    private $amfRequestFactory;

    function __construct(AMFRequestFactory $amfRequest)
    {
        $this->amfRequestFactory = $amfRequest;
    }


    public function createHttpRequest()
    {

        $httpRequest = parent::createHttpRequest();

        if($this->amfRequestFactory->isAMFRequest($httpRequest)) {
            $headers = $this->amfRequestFactory->getRequest()->getHeaders();

            $property = $httpRequest->getReflection()->getProperty('headers');
            $property->setAccessible(TRUE);
            $property->setValue($httpRequest, array_merge($httpRequest->getHeaders(), $headers));
        }
        $this->amfRequestFactory->setHttpRequest($httpRequest);

        return $httpRequest;
    }
} 