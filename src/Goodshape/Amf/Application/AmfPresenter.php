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
        $params = $this->request->getParameters();
        if(!isset($params[0])) {
            return;
        }
        $actionMethod = $this->formatActionMethod($this->getAction());
        $rc = $this->getReflection();
        $newParameters = $params;
        if($rc->hasMethod($actionMethod)) {
            $rm = $rc->getMethod($actionMethod);
            $mParams = $rm->getParameters();

            $newParameters = ['action' => $params['action']];
            $counter = 0;
            foreach($mParams as $param) {

                if(!isset($mParams[$counter])) {
                    continue;
                }
                $p = $params[$counter];
                $type = $param->isArray() ? 'array' : ($param->isDefaultValueAvailable() && $param->isOptional() ? gettype($param->getDefaultValue()) : 'NULL');
                if($type === 'array' && !$p) {
                    $p = [];
                }
                if($type === 'integer' && !$p) {
                    $p = NULL;
                }
                $newParameters[$param->name] = $p;
                $counter++;
            }

        }
        $this->params = $newParameters;

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