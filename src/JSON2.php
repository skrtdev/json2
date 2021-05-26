<?php

namespace skrtdev\JSON2;

use Error, ReflectionClass, ReflectionException, ReflectionNamedType, ReflectionUnionType;

const TYPEKEY = '_';

class JSON2 {

    public static bool $array_as_stdClass = false;

    protected static array $reflections = [];
    protected static array $class_attributes = [];
    protected static array $class_init_vars = [];
    protected static array $class_required_init_vars = [];
    protected static array $different_properties = [];
    protected static array $skipped_properties = [];
    protected static array $required_properties = [];

    /**
     * @throws InexistentClassException
     * @throws Exception
     */
    public static function ArrayToClass(array $array, ?string $class, array $vars = []): object|array
    {
        $class ??= $array[TYPEKEY] ?? throw new Exception('Class name is required');
        unset($array[TYPEKEY]);

        $init_vars = self::getInitVars($class);
        $different_properties = self::getDifferentProperties($class);

        $reflection = self::getClassReflection($class);
        // fixme maybe use ReflectionClass::getProperties() in order to set properties, but make sure to set even non-declared properties

        $final_array = [];

        foreach($array as $key => $value){
            if(in_array($key, self::getSkippedProperties($class))){
                continue;
            }

            $subclass = is_array($value) ? ($value[TYPEKEY] ?? self::getClassType($reflection, $key, $value) ?? false) : false;

            if($subclass){
                $value = $subclass === 'stdClass' ? (object) $value : (is_list($value) ? self::ArrayToClassList($value, $subclass, $vars) : self::ArrayToClass($value, $subclass, $vars));
            }
            if(isset($different_properties[$key])){
                $final_array[$different_properties[$key]] = $value;
            }
            else $final_array[$key] = (is_array($value) && self::$array_as_stdClass) ? (object) $value : $value;
        }
        foreach (self::getRequiredProperties($class) as $key) {
            if(!isset($final_array[$key])){
                throw new Exception("Missing required property $key");
            }
        }
        foreach ($init_vars as $key => $value) {
            if(isset($vars[$value])){
                $final_array[$key] = $vars[$value];
            }
            elseif (in_array($value, self::getRequiredInitVars($class))){
                throw new Exception("Missing required init var $value");
            }
        }

        if(is_a($class, Decodeable::class, true)){
            return new $class($final_array);
        }
        else {
            try {
                $instance = $reflection->newInstanceWithoutConstructor();
            }
            catch(ReflectionException $e){
                throw new Exception("Couldn't instantiate $class, as it is an internal class. Consider open an issue if you think this is a bug");
            }
            (function () use ($final_array) {
                foreach ($final_array as $key => $value) {
                    $this->$key = $value;
                }
            })->call($instance);
            return $instance;
        }
    }

    /**
     * @throws InexistentClassException
     */
    public static function getClassReflection(string $class): ReflectionClass
    {
        try {
            return self::$reflections[$class] ??= new ReflectionClass($class);
        }
        catch (ReflectionException $e){
            throw new InexistentClassException($class, 0, $e);
        }
    }

    /**
     * @param string $class
     * @return JSONProperty[]
     * @throws InexistentClassException
     */
    public static function getClassAttributes(string $class): array
    {
        if(isset(self::$class_attributes[$class])){
            return self::$class_attributes[$class];
        }

        $reflection = self::getClassReflection($class);
        $attributes = [];

        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(JSONProperty::class) as $attribute) {
                $attributes[$property->getName()] =  $attribute->newInstance();
            }
        }

        return self::$class_attributes[$class] = $attributes;
    }

    /**
     * @param string $class
     * @return array
     * @throws InexistentClassException
     */
    public static function getInitVars(string $class): array {
        if(isset(self::$class_init_vars[$class])){
            return self::$class_init_vars[$class];
        }

        $init_vars = [];
        foreach (self::getClassAttributes($class) as $property => $attribute) {
            if($init_var = $attribute->getInitVar()){
                $init_vars[$property] = $init_var;
            }
        }

        return self::$class_init_vars[$class] = $init_vars;
    }

    /**
     * @param string $class
     * @return array
     * @throws InexistentClassException
     */
    public static function getRequiredInitVars(string $class): array {
        if(isset(self::$class_required_init_vars[$class])){
            return self::$class_required_init_vars[$class];
        }

        $init_vars = [];
        foreach (self::getClassAttributes($class) as $property => $attribute) {
            if($init_var = $attribute->getInitVar() && $attribute->isRequired()){
                $init_vars[$property] = $init_var;
            }
        }

        return self::$class_required_init_vars[$class] = $init_vars;
    }

    /**
     * @param string $class
     * @return array
     * @throws InexistentClassException
     */
    public static function getDifferentProperties(string $class): array {
        if(isset(self::$different_properties[$class])){
            return self::$different_properties[$class];
        }

        $different_properties = [];
        foreach (self::getClassAttributes($class) as $property => $attribute) {
            if($different_property = $attribute->getJson()){
                $different_properties[$different_property] = $property;
            }
        }

        return self::$different_properties[$class] = $different_properties;
    }

    /**
     * @param string $class
     * @return array
     * @throws InexistentClassException
     */
    public static function getSkippedProperties(string $class): array {
        if(isset(self::$skipped_properties[$class])){
            return self::$skipped_properties[$class];
        }

        $skipped_properties = [];
        foreach (self::getClassAttributes($class) as $property => $attribute) {
            if($attribute->isSkipped()){
                $skipped_properties[] = $property;
            }
        }

        return self::$skipped_properties[$class] = $skipped_properties;
    }

    /**
     * @param string $class
     * @return array
     * @throws InexistentClassException
     */
    public static function getRequiredProperties(string $class): array {
        if(isset(self::$required_properties[$class])){
            return self::$required_properties[$class];
        }

        $required_properties = [];
        foreach (self::getClassAttributes($class) as $property => $attribute) {
            if($attribute->getInitVar() === null && $attribute->isRequired()){
                $required_properties[] = $property;
            }
        }

        return self::$required_properties[$class] = $required_properties;
    }


    /**
     */
    public static function ArrayToClassList(array $array, string $class, array $vars = []): array
    {
        $res = [];
        foreach($array as $key => $value){
            $res[$key] = (is_array($value) && is_list($value)) ? self::ArrayToClassList($value, $class, $vars) : self::ArrayToClass($value, $class, $vars);
        }
        return $res;
    }

    /**
     * @throws Exception
     */
    public static function GetClassType(ReflectionClass $reflection, string $key, array $value): ?string {
        try{
            if($type = $reflection->getProperty($key)->getType()){
                if($type instanceof ReflectionNamedType){
                    /** @var ReflectionNamedType $type */
                    if($type->isBuiltin()){
                        if($type->getName() === 'array'){
                            preg_match('/@var (?:\$.+ )?(.+)\[]/', $reflection->getProperty($key)->getDocComment() ?: '', $matches);
                            return $matches[1] ?? null;
                        }
                        else return null;
                    }
                    else {
                        return $type->getName();
                    }
                }
                elseif ($type instanceof ReflectionUnionType){
                    $types = [];
                    foreach ($type->getTypes() as $type) {
                        if($type->isBuiltin()){
                            if($type->getName() === 'array'){
                                preg_match('/@var (?:\$.+ )?(.+)\[]/', $reflection->getProperty($key)->getDocComment() ?: '', $matches);
                                $arrayof = $matches[1] ?? null;
                                if(isset($arrayof) && !isset($value[TYPEKEY]) && is_list($value)){
                                    return $arrayof;
                                }
                            }
                        }
                        else {
                            $types []= $type->getName();
                        }
                    }
                    if(count($types) === 0){
                        throw new Exception('boh');
                    }
                    elseif(count($types) === 1){
                        return $types[0];
                    }
                    else{
                        return isset($value[TYPEKEY]) ? (in_array($value[TYPEKEY], $types) ? $value[TYPEKEY] : throw new Exception('Type mismatch')) : throw new Exception('Cannot determine object type');
                    }
                }
            }
        }
        catch(ReflectionException|Error $_){
            //echo $_->getMessage(), PHP_EOL;
            return null;
        }
        return null;
    }
}
