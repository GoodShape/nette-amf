<?php


namespace Goodshape\Amf\Http;


class AMFRequest {


    private $headers;

    private $messages;

    public function __construct($headers, $messages)
    {
        $this->headers = $headers;
        $this->messages = $messages;
    }

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return mixed
     */
    public function getMessages()
    {
        return $this->messages;
    }

    public function getMessage($currentMessageIndex)
    {
        return isset($this->messages[$currentMessageIndex]) ? $this->messages[$currentMessageIndex] : [];
    }

    public function getMessageCount()
    {
        return count($this->messages);
    }


}