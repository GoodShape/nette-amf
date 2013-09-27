<?php

namespace Goodshape\Amf\Application;


use Goodshape\Amf\Helpers\CustomClassConvertor;
use Goodshape\Amf\Helpers\Deserializer;
use Goodshape\Amf\Helpers\Packet;
use Goodshape\Amf\Helpers\Serializer;
use Nette\Caching\IStorage;
use Nette\Environment;
use Nette\Http\Request;
use Nette\Object;
use Nette\Utils\Strings;
use ReflectionMethod;
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

    /**
     * @param array $config
     * @param Request $httpRequest
     */
    function __construct($config, Request $httpRequest) {
        $this->httpRequest = $httpRequest;
        $this->config = $config;
        $this->classConvertor = new CustomClassConvertor(isset($config['customNamespaces'])?$config['customNamespaces']:NULL);
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




    public function setResponse(Response $response) {
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

        $target= $this->getDestination();
        $data = $this->getData($target);
        list($presenter, $action) = explode(":", $target);


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
        if(isset(self::$conversionTable[$target])) {
            $target = self::$conversionTable[$target];
        } else {
            return $this->bcPresenter;
        }
        $p = explode(":", $target);
        $presenter = array_shift($p);
        return [$presenter, array_shift($p)];
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
     * conversion of old service names to new ones
     *
     * @var array
     */
    private static $conversionTable = [
       /* 'UserService.login' => 'Sign:in',
        'UserService.logout' => 'Sing:out',
        'UserService.relogin' => 'Sign:getIdentity',
        'UserService.setUserProfile' => 'User:update',
        'UserService.register' => 'User:create',
        'UserService.loginOrRegisterByFacebook' => 'Sign:facebook',
        'UserService.getOrganizations' => 'Subjects:default'*/
    ];

    /**
     * @return array
     */
    public function getResponses() {
        return $this->responses;
    }


} 