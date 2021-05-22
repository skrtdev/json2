<?php

namespace skrtdev\JSON2;

use Attribute;
use Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JSONProperty
{
    /**
     * @throws Exception
     */
    public function __construct(
        protected ?string $json = null,
        protected bool $skip = false,
        protected ?string $init_var = null,
        protected ?bool $required = null
    )
    {
        if(isset($json) && isset($init_var)){
            throw new Exception('Cannot define both json and init_var arguments');
        }
        $this->required ??= isset($init_var); // default required only for init vars
    }

    /**
     * @return string|null
     */
    public function getJson(): ?string
    {
        return $this->json;
    }

    /**
     * @return string|null
     */
    public function getInitVar(): ?string
    {
        return $this->init_var;
    }

    /**
     * @return bool
     */
    public function isSkipped(): bool
    {
        return $this->skip;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

}
