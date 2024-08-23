<?php

namespace SwooleAi\OpenAi;

class FunctionCall
{
    protected  $functions = [];

    public static function create()
    {
        return new self;
    }

    public function add(FunctionDef $def)
    {
        $this->functions[] = ['type' => 'function', 'function' => $def->toArray()];
        return $this;
    }

    public function toArray(): array
    {
        return $this->functions;
    }
}
