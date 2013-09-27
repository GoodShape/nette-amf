<?php

namespace Goodshape\Amf\Application;


use Nette\Application\IResponse;
use Nette;

/**
 * Helper class used to fire final response of application
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @internal
 * @package App\Core\Amf
 */
class FinalResponse implements IResponse  {

    private $manager;

    function __construct(Manager $manager) {
        $this->manager = $manager;
    }


    function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
        if($this->isAmfRequest($httpRequest)) {

            $httpResponse->setContentType('application/x-amf');
            $this->manager->sendResponse();
        }
        else {
            $responses = $this->manager->getResponses();
            if(count($responses) === 1) {
                $responses = array_shift($responses);
            }
            $response = new Nette\Application\Responses\JsonResponse($responses);
            $response->send($httpRequest, $httpResponse);
        }
    }


    private function isAmfRequest(Nette\Http\IRequest $request) {
        return $request->getHeader('Content-type') === 'application/x-amf';
    }
}