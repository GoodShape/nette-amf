<?php
/**
 * Helpers for (de)serialization
 */
namespace Goodshape\Amf\Helpers;

class Utils {

    public static function undefinedType() {
        return new UndefinedType();
    }

    public static function byteArrayType($data) {
        return new ByteArray($data);
    }

    public static function dateTimeType($ms) {
        $d = new \DateTime();
        $d->setTimestamp($ms);
        return $d;
    }

    public static function xmlType($data) {
        $s = new \stdClass();
        $s->data = $data;
        return $s;
    }

    static public function isSystemBigEndian() {
        $tmp = pack('d', 1); // determine the multi-byte ordering of this machine temporarily pack 1
        return ($tmp == "\0\0\0\0\0\0\360\77");
    }

}


