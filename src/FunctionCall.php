<?php

namespace SwooleAi\OpenAi;

class FunctionCall
{
    protected array $functions = [];

    public static function create(): FunctionCall
    {
        return new self;
    }

    public function add(FunctionDef $def): static
    {
        $this->functions[] = ['type' => 'function', 'function' => $def->toArray()];
        return $this;
    }

    public function toArray(): array
    {
        return $this->functions;
    }
}
