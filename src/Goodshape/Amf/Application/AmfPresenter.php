<?php

namespace Goodshape\Amf\Application;


use Nette\Application;
use Nette;

class AmfPresenter extends Application\UI\Presenter {

    /** @var bool used for debugging */
    private $innerCall = FALSE;

    /**
     * @var Manager
     */
    private $amfManager;

    /**
     * Converts params from AMF call to named
     */
    protected function startup() {
        parent::startup();
        $inputParams = $this->request->getParameters();
        if(!isset($inputParams[0])) {
            return;
        }
        $actionMethod = $this->formatActionMethod($this->getAction());
        $presenterReflection = $this->getReflection();
        $outputParams = $inputParams;
        if($presenterReflection->hasMethod($actionMethod)) {
            $method = $presenterReflection->getMethod($actionMethod);
            $methodParameters = $method->getParameters();

            $outputParams = ['action' => $inputParams['action']];
            $counter = 0;
            foreach($methodParameters as $param) {

                if(!isset($methodParameters[$counter])) {
                    continue;
                }
                $parameter = isset($inputParams[$counter])?$inputParams[$counter]:NULL;
                $type = $param->isArray() ? 'array' : ($param->isDefaultValueAvailable() && $param->isOptional() ? gettype($param->getDefaultValue()) : 'NULL');
                if($type === 'array' && !$parameter) {
                    $parameter = [];
                }
                if($type === 'integer' && !$parameter) {
                    $parameter = NULL;
                }
                $outputParams[$param->name] = $parameter;
                $counter++;
            }

        }
        $this->params = $outputParams;

    }

    public final function sendResponse(Application\IResponse $response) {
        if($this->innerCall) {
            parent::sendResponse($response);
        }
        $this->amfManager->setResponse($response);
        if($this->amfManager->hasMoreMessages()) {
            $request = $this->amfManager->createApplicationRequest();
            parent::sendResponse(new Application\Responses\ForwardResponse($request));
        } else {
            parent::sendResponse(new FinalResponse($this->amfManager));
        }
    }

    public function injectAmfManager(Manager $amfManager) {
        $this->amfManager = $amfManager;
    }

    /**
     * @return boolean
     */
    public function isInnerCall() {
        return $this->innerCall;
    }

    /**
     * @param boolean $innerCall
     */
    public function setInnerCall($innerCall) {
        $this->innerCall = $innerCall;
    }


} 