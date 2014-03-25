<?php

namespace Goodshape\Amf\Application;


use Goodshape\Amf\Helpers\CustomClassConvertor;
use Goodshape\Amf\Helpers\Deserializer;
use Goodshape\Amf\Helpers\Packet;
use Goodshape\Amf\Helpers\Serializer;
use Goodshape\Amf\Http\AMFRequest;
use Goodshape\Amf\Http\AMFRequestFactory;
use Nette\Application\IResponse;
use Nette\Http\Request;
use Nette\Object;
use Shiraz\Model\Remote\DateTime;
use TokenReflection;

/**
 * Manager that keeps information about messages incoming in request, makes (de)serialization
 * of packet data and keeps responses to messages (there can be more than one)
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Manager extends Object {
    /** @var int */
    private $currentMessageIndex = 0;
    /** @var Request */
    private $httpRequest;
    /** @var string default module name */
    private $module;
    /** @var array */
    private $responses = [];

    /** @var array */
    private $config;
    private $destinationMappings;
    /**
     * @var AMFRequest
     */
    private $amfRequest;

    /**
     * @param array $config
     * @param Request $httpRequest
     */
    function __construct($config, Request $httpRequest, AMFRequestFactory $amfRequestFactory) {
        $this->httpRequest = $httpRequest;
        $this->config = $config;
        $this->destinationMappings = isset($config['mappings'])?$config['mappings']:[];
        $this->module = $config['module'];
        if($this->isAMFRequest()) {
            $this->amfRequest = $amfRequestFactory->getRequest();
        }
    }




    private function getCurrentMessage() {
        return $this->amfRequest->getMessage($this->currentMessageIndex);
    }




    public function setResponse(IResponse $response) {
        $this->responses[$this->currentMessageIndex] = $response;
        $this->currentMessageIndex++;
    }

    /**
     * Returns sign of there is more messages to process
     *
     * @return bool
     */
    public function hasMoreMessages() {
        return $this->amfRequest->getMessageCount() > count($this->responses);
    }

    /**
     * @return array|string
     */
    public function getDestination() {
        return $this->convertDestination($this->getCurrentMessage()['target']);
    }

    /**
     * Creates Nette Application request
     * @return \Nette\Application\Request
     */
    public function createApplicationRequest() {

        list($presenter, $action) = $this->getDestination();
        $data = $this->getData($presenter.':'.$action);


        return new \Nette\Application\Request($this->module.":".$presenter,
            $this->httpRequest->getMethod(),
            array_merge(['action' => $action],
                $data),
            $this->httpRequest->getPost(),
			$this->httpRequest->getFiles(),
			array(\Nette\Application\Request::SECURED => $this->httpRequest->isSecured()));
    }

    /**
     * @param $target
     * @return array
     */
    public function getData($target) {
        return $this->getCurrentMessage()['data'];
    }

    private function convertDestination($target) {
        $serviceCall = str_replace(".", "/", $target);
        $call = explode("/", $serviceCall);

        $presenter = array_shift($call);
        $action = array_shift($call);
        if(isset($this->destinationMappings[$presenter.'/'.$action])) {
            return explode(":", $this->destinationMappings[$presenter.'/'.$action]);
        }
        return [$presenter, $action];
    }

    /**
     * Sends response to client - builds response packet, serializes it and output it
     */
    public function sendResponse() {
        $serializer = new Serializer();
        $packet = new Packet();
        foreach($this->amfRequest->getMessages() as $index => $message) {
            $packet->messages[] =
                (object) ['targetUri' => $message['response'].'/onResult', 'responseUri' => null,'data' => $this->responses[$index]];

        }
        $rawOutput = $serializer->serialize($packet);
        echo $rawOutput;
    }

    /**
     * @return array
     */
    public function getResponses() {
        return $this->responses;
    }

    public function isAMFRequest()
    {
        return in_array($this->httpRequest->getHeader('Content-type'), AMFRequestFactory::$contentTypes);
    }

}