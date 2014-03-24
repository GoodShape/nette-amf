<?php


namespace Goodshape\Amf\Http;


use Goodshape\Amf\Application\Manager;
use Goodshape\Amf\Helpers\CustomClassConvertor;
use Goodshape\Amf\Helpers\Deserializer;
use Nette\Http\Request;

class AMFRequestFactory {
    /** @var \Goodshape\Amf\Helpers\CustomClassConvertor */
    private $classConvertor;

    private $request = NULL;
    private $httpRequest;

    /** @var array supported content types */
    public static $contentTypes = [
        'application/x-amf',
    ];

    public function __construct(CustomClassConvertor $classConvertor)
    {
        $this->classConvertor = $classConvertor;
    }


    public function getRequest() {
        if($this->request) {
            return $this->request;
        }
        return $this->request = $this->prepare();
    }


    private function prepare()
    {
        $data = $this->getRawData();
        if($data) {
            $deselizer = new Deserializer();

            $requestPacket = $deselizer->deserialize($data);
            foreach ($requestPacket->messages as &$message) {
                $message = $this->classConvertor->convert($message);
            }
            return new AMFRequest($requestPacket->headers, $requestPacket->messages);
        }
        return new AMFRequest([], []);
    }

    private function getRawData()
    {
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            return $GLOBALS['HTTP_RAW_POST_DATA'];
        } else {
            return file_get_contents('php://input');
        }
    }

    public function isAMFRequest(Request $request = NULL)
    {
        if($request == NULL) {
            $request = $this->httpRequest;
        }
        return in_array($request->getHeader('Content-type'), self::$contentTypes);
    }

    public function setHttpRequest($httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }

} 