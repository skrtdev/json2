<?php

namespace skrtdev\JSON2;

abstract class Decodeable {

    public function __construct(array $array = []){
        foreach ($array as $key => $value) {
            $this->$key = $value;
        }
    }
}