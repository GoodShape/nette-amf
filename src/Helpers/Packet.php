<?php

namespace Goodshape\Amf\Helpers;

/**
 * Helper to keep request data
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Packet {

    public $headers = [];
    public $messages = [];
    public $amfVersion = Deserializer::AMF3_ENCODING;
} 