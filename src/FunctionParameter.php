<?php

namespace SwooleAi\OpenAi;

class FunctionParameter
{
    public static function create(string $type)
    {
        return new self($type);
    }

    protected array $property = [];

    public function __construct(string $type)
    {
        $this->property['type'] = $type;
    }

    public function toArray(): array
    {
        return $this->property;
    }

    public function withDescription(string $string)
    {
        $this->property['description'] = $string;
        return $this;
    }

    public function withEnum(): static
    {
        $this->property['enum'] = func_get_args();
        return $this;
    }
}
