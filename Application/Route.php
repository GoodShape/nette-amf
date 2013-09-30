<?php
namespace Goodshape\Amf\Application;

use Nette;
use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Utils\Strings;

/**
 * Executes deserialization of packet and routes the request
 * Uses Manager class in background
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Route implements IRouter {

    /** @var string URL mask */
    private $mask;
    /** @var array supported content types */
    private $contentTypes = [
        'application/x-amf',
    ];

    private $defaultModule;
    /** @var \App\Core\Amf\Manager */
    private $manager;

    public function __construct(Manager $manager, $mask, $defaultModule = 'Service') {
        $this->mask = $mask;
        $this->defaultModule = $defaultModule;
        $this->manager = $manager;
    }


    /**
     * Maps HTTP request to a Request object.
     *
     * @param \Nette\Http\IRequest $httpRequest
     * @return Request|NULL
     */
    public function match(Nette\Http\IRequest $httpRequest) {
        $url = $httpRequest->getUrl();
        $basePath = $url->getBasePath();
        if (strncmp($url->getPath(), $basePath, strlen($basePath)) !== 0) {
            return NULL;
        }
        if(!Strings::startsWith($url->getPath(), $this->mask)) {
            return null;
        }
        if(!in_array($httpRequest->getHeader('Content-type'), $this->contentTypes)) {
            return null;
        }


        return $this->manager->createApplicationRequest();

    }

    /**
     * Constructs absolute URL from Request object.
     *
     * @param \Nette\Application\Request $appRequest
     * @param \Nette\Http\Url $refUrl
     * @return string|NULL
     */
    function constructUrl(Request $appRequest, Nette\Http\Url $refUrl) {
        return NULL;
    }


}