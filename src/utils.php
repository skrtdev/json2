<?php

use skrtdev\JSON2\JSON2;

function is_list(array $array): bool {
    return array_values($array) === $array;
}


/**
 * @throws ReflectionException
 */
function json2_decode(string|array $json, ?string $class = null, array $vars = [], ...$args): object|array {
    $array = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : $json;
    return is_list($array) ? JSON2::ArrayToClassList($array, $class, $vars + $args) : JSON2::ArrayToClass($array, $class, $vars + $args);
}
