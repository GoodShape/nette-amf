<?php

namespace Goodshape\Amf\Helpers;


use Goodshape\InvalidStateException;
use Nette\Reflection\ClassType;

/**
 * Converts custom classes in request packet to real instances of these classes
 *
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class CustomClassConvertor {

    private $customClassesNamespaces = [];
    private $maxRecursionDepth = 10;

    /**
     * @param $customClassesNamespaces array namespaces where converter looks for searched class
     */
    function __construct($customClassesNamespaces) {
        if(!$customClassesNamespaces) {
            $customClassesNamespaces = [""];
        }
        $this->customClassesNamespaces = $customClassesNamespaces;
        $this->customClassesNamespaces[] = '';
    }


    /**
     * if the object contains an explicit type marker, this method attempts to convert it to its typed counterpart
     * if the typed class is already available, then simply creates a new instance of it. If not,
     * attempts to load the file from the available service folders.
     * If then the class is still not available, the object is not converted
     * note: This is not a recursive function. Rather the recusrion is handled by Amfphp_Core_Amf_Util::applyFunctionToContainedObjects.
     * must be public so that Amfphp_Core_Amf_Util::applyFunctionToContainedObjects can call it
     *
     * Part of AMFPHP
     * @author Silex Labs
     *
     * @param mixed $obj
     * @throws \Goodshape\InvalidStateException
     * @return mixed
     */
    private function convertToTyped($obj) {
        if (!is_object($obj)) {
            return $obj;
        }
        $explicitTypeField = Deserializer::FIELD_EXPLICIT_TYPE;
        if (isset($obj->$explicitTypeField)) {
            $customClassName = $obj->$explicitTypeField;
            foreach($this->customClassesNamespaces as $namespace) {
                $fqcn = $namespace.'\\'.$customClassName;
                if (class_exists($fqcn)) {
                    //class is available. Use it!
                    $classReflection = new ClassType($fqcn);
                    $typedObj = $classReflection->newInstanceWithoutConstructor();
                    foreach ($obj as $key => $data) { // loop over each element to copy it into typed object
                        if ($key != $explicitTypeField) {
                            $typedObj->$key = $data;
                        }
                    }
                    return $typedObj;
                }
            }
            throw new InvalidStateException("Class $customClassName was not found in any of provided namespaces.");
        }

        return $obj;
    }

    /**
     * Recursively walks through data and converts contained classes
     *
     * @author Silex Labs, part of AmfPHP library
     * @author Jan Langer <jan.langer@goodshape.cz>, modifications
     *
     * @param $obj
     * @param int $depth
     * @return array|mixed|object
     * @throws \Goodshape\InvalidStateException
     */
    public function convert($obj, $depth = 0) {
        //recursivelly walk the variable
        if ($depth > $this->maxRecursionDepth) {
            throw new InvalidStateException("Couldn't recurse deeper on object. Probably a cyclic reference.");
        }
        //don't apply to Amf types such as byte array, date... (was converted in deserialization already
        if (is_object($obj) && !isset($obj->{Deserializer::FIELD_EXPLICIT_TYPE})) {
            return $obj;
        }

        //apply callBack to obj itself
        $obj = $this->convertToTyped($obj);

        //if $obj isn't a complex type don't go any further
        if (!is_array($obj) && !is_object($obj)) {
            return $obj;
        }

        foreach ($obj as $key => $data) { // loop over each element
            $modifiedData = null;
            if (is_object($data) || is_array($data)) {
                //data is complex, so don't apply callback directly, but recurse on it
                $modifiedData = $this->convert($data, $depth + 1);
            } else {
                //data is simple, so apply data
                $modifiedData = $this->convertToTyped($data);
            }
            //store converted data
            if (is_array($obj)) {
                $obj[$key] = $modifiedData;
            } else {
                $obj->$key = $modifiedData;
            }
        }

        return $obj;
    }

} 