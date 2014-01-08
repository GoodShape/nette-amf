<?php

namespace Goodshape\Amf\Application;


use Goodshape\Amf\Helpers\CustomClassConvertor;
use Goodshape\Amf\Helpers\Deserializer;
use Goodshape\Amf\Helpers\Packet;
use Goodshape\Amf\Helpers\Serializer;
use Nette\Application\IResponse;
use Nette\Http\Request;
use Nette\Object;
use TokenReflection;

/**
 * Manager that keeps information about messages incoming in request, makes (de)serialization
 * of packet data and keeps responses to messages (there can be more than one)
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Manager extends Object {
    /** @var Packet */
    private $requestPacket;
    /** @var int */
    private $currentMessageIndex = 0;

    private $bcPresenter = 'Old:call';
    /** @var Request */
    private $httpRequest;
    /** @var string default module name */
    private $module = 'Service';
    /** @var array */
    private $responses = [];
    /** @var CustomClassConvertor */
    private $classConvertor;

    /** @var array */
    private $config;
    private $destinationMappings;

    /**
     * @param array $config
     * @param Request $httpRequest
     */
    function __construct($config, Request $httpRequest) {
        $this->httpRequest = $httpRequest;
        $this->config = $config;
        $this->classConvertor = new CustomClassConvertor(isset($config['requestNamespaces'])?$config['requestNamespaces']:NULL);
        $this->destinationMappings = isset($config['mappings'])?$config['mappings']:[];
    }


    private function prepare() {
        $deselizer = new Deserializer();
        $this->requestPacket = $deselizer->deserialize($this->httpRequest->getQuery(), $this->httpRequest->getPost(), $this->getRawData());
        foreach($this->requestPacket->messages as &$message) {
            $message = $this->classConvertor->convert($message);
        }
    }

    private function getCurrentMessage() {
        if($this->requestPacket === NULL) {
            $this->prepare();
        }
        return $this->requestPacket->messages[$this->currentMessageIndex];
    }




    public function setResponse(IResponse $response) {
        $this->responses[$this->currentMessageIndex] = $response;
        $this->currentMessageIndex++;
    }

    private function getRawData() {
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            return $GLOBALS['HTTP_RAW_POST_DATA'];
        } else{
            return file_get_contents('php://input');
        }
    }

    /**
     * Returns sign of there is more messages to process
     *
     * @return bool
     */
    public function hasMoreMessages() {
        if(!is_object($this->requestPacket)) {
            return FALSE;
        }
        return count($this->requestPacket->messages) > count($this->responses);
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
        $data = $this->getCurrentMessage()['data'];
        if($target === $this->bcPresenter) {
            $data = [$data];
            array_unshift($data, $this->getCurrentMessage()['target']);
        }
        return $data;
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
        foreach($this->requestPacket->messages as $index => $message) {
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


} 