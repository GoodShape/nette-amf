<?php

namespace Goodshape\Amf\Application;


use Nette\Application\IResponse;
use Nette;

/**
 * Response to be user in presenter sendResponse method
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Response implements IResponse {

    public $_explicitType = 'Response';

    public $data;
    public $returnCode;
    public $dbgText;

    public $timestamp;

    public function __construct($data, $returnCode = 0, $message = '') {
        $this->data = $data;
        $this->dbgText = $message;
        $this->returnCode = $returnCode;
        $this->timestamp = time();
    }


    function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
        throw new Nette\NotImplementedException('Direct sending of AMF response is not supported.');
    }
}