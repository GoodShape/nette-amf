<?php

namespace Goodshape\Amf\Helpers;

/**
 *  This file is part of amfPHP
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file license.txt.
 * @package Amfphp_Core_Amf
 */
use Goodshape\InvalidStateException;

/**
 * Amfphp_Core_Amf_Deserializer takes the raw amf input stream and converts it PHP objects
 * representing the data.
 *
 * @package Amfphp_Core_Amf
 */
class Deserializer {
    const AMF3_ENCODING = 3;
    const FIELD_EXPLICIT_TYPE = '_explicitType';
    const FIELD_EXTERNALIZED_DATA = '_externalizedData';
    /**
     * data to deserialize
     * @var string
     */
    protected $rawData;

    /**
     * The number of Messages in the packet left to process
     *
     * @access protected
     * @var int
     */
    protected $messagesLeftToProcess;

    /**
     * The current seek cursor of the stream
     *
     * @access protected
     * @var int
     */
    protected $currentByte;

    /**
     * The number of headers in the packet left to process
     *
     * @access protected
     * @var int
     */
    protected $headersLeftToProcess;

    /**
     * the Packet contained in the serialized data
     * @var <Amfphp_Core_Amf_Packet>
     */
    protected $deserializedPacket;

    /**
     *  strings stored for tracking references(amf3)
     * @var array
     */
    protected $storedStrings;

    /**
     *  objects stored for tracking references(amf3)
     * @var array
     */
    protected $storedObjects;

    /**
     *  class definitions(traits) stored for tracking references(amf3)
     * @var array
     */
    protected $storedDefinitions;

    /**
     *  objects stored for tracking references(amf0)
     * @var array
     */
    protected $amf0storedObjects;

    /**
     * convert from text/binary to php object
     * @param array $getData
     * @param array $postData
     * @param string $rawPostData
     * @return Packet
     */
    public function deserialize(array $getData, array $postData, $rawPostData) {
        $this->rawData = $rawPostData;
        $this->currentByte = 0;
        $this->deserializedPacket = new Packet();
        $this->readHeaders(); // read the binary headers
        $this->readMessages(); // read the binary Messages
        return $this->deserializedPacket;
    }

    /**
     * reset reference stores
     */
    protected function resetReferences(){
        $this->amf0storedObjects = array();
        $this->storedStrings = array();
        $this->storedObjects = array();
        $this->storedDefinitions = array();

    }

    /**
     * readHeaders converts that header section of the amf Packet into php obects.
     * Header information typically contains meta data about the Packet.
     */
    protected function readHeaders() {

        $topByte = $this->readByte(); // ignore the first two bytes --  version or something
        $secondByte = $this->readByte(); //0 for Flash,
        //If firstByte != 0, then the Amf data is corrupted, for example the transmission
        //
			if (!($topByte == 0 || $topByte == 3)) {
            throw new InvalidStateException('Malformed Amf Packet, connection may have dropped');
        }
        if($secondByte == 3){
            $this->deserializedPacket->amfVersion = self::AMF3_ENCODING;
        }

        $this->headersLeftToProcess = $this->readInt(); //  find the total number of header elements

        while ($this->headersLeftToProcess--) { // loop over all of the header elements
            $this->resetReferences();
            $name = $this->readUTF();
            $required = $this->readByte() == 1; // find the must understand flag
            //$length   = $this->readLong(); // grab the length of  the header element
            $this->currentByte += 4; // grab the length of the header element


            $type = $this->readByte();  // grab the type of the element
            $content = $this->readData($type); // turn the element into real data

            $this->deserializedPacket->headers[] = ['name' => $name, 'required' => $required, 'content' => $content];
        }
    }

    /**
     * read messages in AMF packet
     */
    protected function readMessages() {
        $this->messagesLeftToProcess = $this->readInt(); // find the total number  of Message elements
        while ($this->messagesLeftToProcess--) { // loop over all of the Message elements
            $this->resetReferences();
            $target = $this->readUTF();
            $response = $this->readUTF(); //    the response that the client understands
            //$length = $this->readLong(); // grab the length of    the Message element
            $this->currentByte += 4;
            $type = $this->readByte(); // grab the type of the element
            $data = $this->readData($type); // turn the element into real data
            $this->deserializedPacket->messages[] = ['target' => $target, 'response' => $response, 'data' => $data];
        }
    }


    /**
     * readInt grabs the next 2 bytes and returns the next two bytes, shifted and combined
     * to produce the resulting integer
     *
     * @return int The resulting integer from the next 2 bytes
     */
    protected function readInt() {
        return ((ord($this->rawData[$this->currentByte++]) << 8) |
                ord($this->rawData[$this->currentByte++])); // read the next 2 bytes, shift and add
    }

    /**
     * readUTF first grabs the next 2 bytes which represent the string length.
     * Then it grabs the next (len) bytes of the resulting string.
     *
     * @return string The utf8 decoded string
     */
    protected function readUTF() {
        $length = $this->readInt(); // get the length of the string (1st 2 bytes)
        //BUg fix:: if string is empty skip ahead
        if ($length == 0) {
            return '';
        } else {
            $val = substr($this->rawData, $this->currentByte, $length); // grab the string
            $this->currentByte += $length; // move the seek head to the end of the string

            return $val; // return the string
        }
    }

    /**
     * readByte grabs the next byte from the data stream and returns it.
     *
     * @return int The next byte converted into an integer
     */
    protected function readByte() {
        return ord($this->rawData[$this->currentByte++]); // return the next byte
    }

    /**
     * readData is the main switch for mapping a type code to an actual
     * implementation for deciphering it.
     *
     * @param mixed $type The $type integer
     * @return mixed The php version of the data in the Packet block
     */
    public function readData($type) {
        switch ($type) {
            //amf3 is now most common, so start with that
            case 0x11: //Amf3-specific
                return $this->readAmf3Data();
                break;
            case 0: // number
                return $this->readDouble();
            case 1: // boolean
                return $this->readByte() == 1;
            case 2: // string
                return $this->readUTF();
            case 3: // object Object
                return $this->readObject();
            //ignore movie clip
            case 5: // null
                return null;
            case 6: // undefined
                return Utils::undefinedType();
            case 7: // Circular references are returned here
                return $this->readReference();
            case 8: // mixed array with numeric and string keys
                return $this->readMixedArray();
            case 9: //object end. not worth , TODO maybe some integrity checking
                return null;
            case 0X0A: // array
                return $this->readArray();
            case 0X0B: // date
                return $this->readDate();
            case 0X0C: // string, strlen(string) > 2^16
                return $this->readLongUTF();
            case 0X0D: // mainly internal AS objects
                return null;
            //ignore recordset
            case 0X0F: // XML
                return $this->readXml();
            case 0x10: // Custom Class
                return $this->readCustomClass();
            default: // unknown case
                throw new \Exception("Found unhandled type with code: $type");
                break;
        }
    }

    /**
     * readDouble reads the floating point value from the bytes stream and properly orders
     * the bytes depending on the system architecture.
     *
     * @return float The floating point value of the next 8 bytes
     */
    protected function readDouble() {
        $bytes = substr($this->rawData, $this->currentByte, 8);
        $this->currentByte += 8;
        if (Utils::isSystemBigEndian()) {
            $bytes = strrev($bytes);
        }
        $zz = unpack('dflt', $bytes); // unpack the bytes
        return $zz['flt']; // return the number from the associative array
    }

    /**
     * readObject reads the name/value properties of the amf Packet and converts them into
     * their equivilent php representation
     *
     * @return Object The php object filled with the data
     */
    protected function readObject() {
        $ret = new stdClass();
        $this->amf0storedObjects[] = & $ret;
        $key = $this->readUTF();
        for ($type = $this->readByte(); $type != 9; $type = $this->readByte()) {
            $val = $this->readData($type); // grab the value
            $ret->$key = $val; // save the name/value pair in the object
            $key = $this->readUTF(); // get the next name
        }
        return $ret;
    }

    /**
     * readReference replaces the old readFlushedSO. It treats where there
     * are references to other objects. Currently it does not resolve the
     * object as this would involve a serious amount of overhead, unless
     * you have a genius idea
     *
     * @return String
     */
    protected function readReference() {
        $reference = $this->readInt();
        return $this->amf0storedObjects[$reference];
    }

    /**
     * readMixedArray turns an array with numeric and string indexes into a php array
     *
     * @return array The php array with mixed indexes
     */
    protected function readMixedArray() {
        //$length   = $this->readLong(); // get the length  property set by flash
        $this->currentByte += 4;
        return $this->readMixedObject(); // return the Message of mixed array
    }

    /**
     * readMixedObject reads the name/value properties of the amf Packet and converts
     * numeric looking keys to numeric keys
     *
     * @return array The php array with the object data
     */
    protected function readMixedObject() {
        $ret = array(); // init the array
        $this->amf0storedObjects[] = & $ret;
        $key = $this->readUTF(); // grab the key
        for ($type = $this->readByte(); $type != 9; $type = $this->readByte()) {
            $val = $this->readData($type); // grab the value
            if (is_numeric($key)) {
                $key = (float) $key;
            }
            $ret[$key] = $val; // save the name/value pair in the array
            $key = $this->readUTF(); // get the next name
        }
        return $ret; // return the array
    }

    /**
     * readArray turns an all numeric keyed actionscript array into a php array.
     *
     * @return array The php array
     */
    protected function readArray() {
        $ret = array(); // init the array object
        $this->amf0storedObjects[] = & $ret;
        $length = $this->readLong(); // get the length  of the array
        for ($i = 0; $i < $length; $i++) { // loop over all of the elements in the data
            $type = $this->readByte(); // grab the type for each element
            $ret[] = $this->readData($type); // grab each element
        }
        return $ret; // return the data
    }

    /**
     * readDate reads a date from the amf Packet and returns the time in ms.
     * This method is still under development.
     *
     * @return Amfphp_Core_Amf_Types_Date a container with the date in ms.
     */
    protected function readDate() {
        $ms = $this->readDouble(); // date in milliseconds from 01/01/1970
        $int = $this->readInt(); // unsupported timezone
        $date = Utils::dateTimeType($ms);
        return $date;
    }

    /**
     * read xml
     * @return Amfphp_Core_Amf_Types_Xml
     */
    protected function readXml() {
        $str = $this->readLongUTF();
        $s = new stdClass();
        $s->data = $str;
        return $s;
    }

    /**
     * readLongUTF first grabs the next 4 bytes which represent the string length.
     * Then it grabs the next (len) bytes of the resulting in the string
     *
     * @return string The utf8 decoded string
     */
    protected function readLongUTF() {
        $length = $this->readLong(); // get the length of the string (1st 4 bytes)
        $val = substr($this->rawData, $this->currentByte, $length); // grab the string
        $this->currentByte += $length; // move the seek head to the end of the string

        return $val; // return the string
    }

    /**
     * readCustomClass reads the amf content associated with a class instance which was registered
     * with Object.registerClass.  In order to preserve the class name an additional property is assigned
     * to the object Amfphp_Core_Amf_Constants::FIELD_EXPLICIT_TYPE.  This property will be overwritten if it existed within the class already.
     *
     * @return object The php representation of the object
     */
    protected function readCustomClass() {
        $typeIdentifier = str_replace('..', '', $this->readUTF());
        $obj = new stdClass();
        $this->amf0storedObjects[] = & $obj;
        $key = $this->readUTF(); // grab the key
        for ($type = $this->readByte(); $type != 9; $type = $this->readByte()) {
            $val = $this->readData($type); // grab the value
            $obj->$key = $val; // save the name/value pair in the array
            $key = $this->readUTF(); // get the next name
        }
        $explicitTypeField = static::FIELD_EXPLICIT_TYPE;
        $obj->$explicitTypeField = $typeIdentifier;
        return $obj; // return the array
    }

    /**
     * read the type byte, then call the corresponding amf3 data reading function
     * @return mixed
     */
    public function readAmf3Data() {
        $type = $this->readByte();
        switch ($type) {
            case 0x00 :
                return Utils::undefinedType();
            case 0x01 :
                return null; //null
            case 0x02 :
                return false; //boolean false
            case 0x03 :
                return true; //boolean true
            case 0x04 :
                return $this->readAmf3Int();
            case 0x05 :
                return $this->readDouble();
            case 0x06 :
                return $this->readAmf3String();
            case 0x07 :
                return $this->readAmf3XmlDocument();
            case 0x08 :
                return $this->readAmf3Date();
            case 0x09 :
                return $this->readAmf3Array();
            case 0x0A :
                return $this->readAmf3Object();
            case 0x0B :
                return $this->readAmf3Xml();
            case 0x0C :
                return $this->readAmf3ByteArray();
            default:
                throw new \Exception('undefined Amf3 type encountered: ' . $type);
        }
    }

    /**
     * Handle decoding of the variable-length representation
     * which gives seven bits of value per serialized byte by using the high-order bit
     * of each byte as a continuation flag.
     *
     * @return read integer value
     */
    protected function readAmf3Int() {
        $int = $this->readByte();
        if ($int < 128)
            return $int;
        else {
            $int = ($int & 0x7f) << 7;
            $tmp = $this->readByte();
            if ($tmp < 128) {
                return $int | $tmp;
            } else {
                $int = ($int | ($tmp & 0x7f)) << 7;
                $tmp = $this->readByte();
                if ($tmp < 128) {
                    return $int | $tmp;
                } else {
                    $int = ($int | ($tmp & 0x7f)) << 8;
                    $tmp = $this->readByte();
                    $int |= $tmp;

                    // Integers in Amf3 are 29 bit. The MSB (of those 29 bit) is the sign bit.
                    // In order to properly convert that integer to a PHP integer - the system
                    // might be 32 bit, 64 bit, 128 bit or whatever - all higher bits need to
                    // be set.

                    if (($int & 0x10000000) !== 0) {
                        $int |= ~0x1fffffff; // extend the sign bit regardless of integer (bit) size
                    }
                    return $int;
                }
            }
        }
    }

    /**
     * read amf 3 date
     * @return boolean|\Amfphp_Core_Amf_Types_Date
     * @throws Amfphp_Core_Exception
     */
    protected function readAmf3Date() {
        $firstInt = $this->readAmf3Int();
        if (($firstInt & 0x01) == 0) {
            $firstInt = $firstInt >> 1;
            if ($firstInt >= count($this->storedObjects)) {
                throw new \Exception('Undefined date reference: ' . $firstInt);
            }
            return $this->storedObjects[$firstInt];
        }


        $ms = $this->readDouble();
        $date = Utils::dateTimeType($ms);
        $this->storedObjects[] = & $date;
        return $date;
    }

    /**
     * readString
     *
     * @return string
     */
    protected function readAmf3String() {

        $strref = $this->readAmf3Int();

        if (($strref & 0x01) == 0) {
            $strref = $strref >> 1;
            if ($strref >= count($this->storedStrings)) {
                throw new \Exception('Undefined string reference: ' . $strref, E_USER_ERROR);
            }
            return $this->storedStrings[$strref];
        } else {
            $strlen = $strref >> 1;
            $str = '';
            if ($strlen > 0) {
                $str = $this->readBuffer($strlen);
                $this->storedStrings[] = $str;
            }
            return $str;
        }
    }

    /**
     * read amf 3 xml
     * @return Amfphp_Core_Amf_Types_Xml
     */
    protected function readAmf3Xml() {
        $handle = $this->readAmf3Int();
        $inline = (($handle & 1) != 0);
        $handle = $handle >> 1;
        if ($inline) {
            $xml = $this->readBuffer($handle);
            $this->storedObjects[] = & $xml;
        } else {
            $xml = $this->storedObjects[$handle];
        }
        return Utils::xmlType($xml);
    }

    /**
     * read amf 3 xml doc
     * @return Amfphp_Core_Amf_Types_Xml
     */
    protected function readAmf3XmlDocument() {
        $handle = $this->readAmf3Int();
        $inline = (($handle & 1) != 0);
        $handle = $handle >> 1;
        if ($inline) {
            $xml = $this->readBuffer($handle);
            $this->storedObjects[] = & $xml;
        } else {
            $xml = $this->storedObjects[$handle];
        }
        return Utils::xmlType($xml);
    }

    /**
     * read Amf 3 byte array
     * @return Amfphp_Core_Amf_Types_ByteArray
     */
    protected function readAmf3ByteArray() {
        $handle = $this->readAmf3Int();
        $inline = (($handle & 1) != 0);
        $handle = $handle >> 1;
        if ($inline) {
            $ba = Utils::byteArrayType($this->readBuffer($handle));
            $this->storedObjects[] = & $ba;
        } else {
            $ba = $this->storedObjects[$handle];
        }
        return $ba;
    }

    /**
     * read amf 3 array
     * @return array
     */
    protected function readAmf3Array() {
        $handle = $this->readAmf3Int();
        $inline = (($handle & 1) != 0);
        $handle = $handle >> 1;
        if ($inline) {
            $hashtable = array();
            $this->storedObjects[] = & $hashtable;
            $key = $this->readAmf3String();
            while ($key != '') {
                $value = $this->readAmf3Data();
                $hashtable[$key] = $value;
                $key = $this->readAmf3String();
            }

            for ($i = 0; $i < $handle; $i++) {
                //Grab the type for each element.
                $value = $this->readAmf3Data();
                $hashtable[$i] = $value;
            }
            return $hashtable;
        } else {
            return $this->storedObjects[$handle];
        }
    }

    /**
     * read amf 3 object
     * @return stdClass
     */
    protected function readAmf3Object() {
        $handle = $this->readAmf3Int();
        $inline = (($handle & 1) != 0);
        $handle = $handle >> 1;

        if ($inline) {
            //an inline object
            $inlineClassDef = (($handle & 1) != 0);
            $handle = $handle >> 1;
            if ($inlineClassDef) {
                //inline class-def
                $typeIdentifier = $this->readAmf3String();
                $typedObject = !is_null($typeIdentifier) && $typeIdentifier != '';
                //flags that identify the way the object is serialized/deserialized
                $externalizable = (($handle & 1) != 0);
                $handle = $handle >> 1;
                $dynamic = (($handle & 1) != 0);
                $handle = $handle >> 1;
                $classMemberCount = $handle;

                $classMemberDefinitions = array();
                for ($i = 0; $i < $classMemberCount; $i++) {
                    $classMemberDefinitions[] = $this->readAmf3String();
                }

                $classDefinition = array('type' => $typeIdentifier, 'members' => $classMemberDefinitions,
                    'externalizable' => $externalizable, 'dynamic' => $dynamic);
                $this->storedDefinitions[] = $classDefinition;
            } else {
                //a reference to a previously passed class-def
                $classDefinition = $this->storedDefinitions[$handle];
            }
        } else {
            //an object reference
            return $this->storedObjects[$handle];
        }


        $type = $classDefinition['type'];
        $obj = new \stdClass();

        //Add to references as circular references may search for this object
        $this->storedObjects[] = & $obj;

        if($classDefinition['externalizable']){
            $externalizedDataField = static::FIELD_EXTERNALIZED_DATA;
            $obj->$externalizedDataField = $this->readAmf3Data();
        }else{
            $members = $classDefinition['members'];
            $memberCount = count($members);
            for ($i = 0; $i < $memberCount; $i++) {
                $val = $this->readAmf3Data();
                $key = $members[$i];
                $obj->$key = $val;
            }

            if ($classDefinition['dynamic'] /* && obj is ASObject */) {
                $key = $this->readAmf3String();
                while ($key != '') {
                    $value = $this->readAmf3Data();
                    $obj->$key = $value;
                    $key = $this->readAmf3String();
                }
            }
        }

        if ($type != '') {
            $explicitTypeField = static::FIELD_EXPLICIT_TYPE;
            $obj->$explicitTypeField = $type;
        }

        return $obj;
    }

    /**
     * readLong grabs the next 4 bytes shifts and combines them to produce an integer
     *
     * @return int The resulting integer from the next 4 bytes
     */
    protected function readLong() {
        return ((ord($this->rawData[$this->currentByte++]) << 24) |
                (ord($this->rawData[$this->currentByte++]) << 16) |
                (ord($this->rawData[$this->currentByte++]) << 8) |
                ord($this->rawData[$this->currentByte++])); // read the next 4 bytes, shift and add
    }

    /**
     * read some data and move pointer
     * @param type $len
     * @return mixed
     */
    protected function readBuffer($len) {
        $data = '';
        for ($i = 0; $i < $len; $i++) {
            $data .= $this->rawData
                    {$i + $this->currentByte};
        }
        $this->currentByte += $len;
        return $data;
    }

}