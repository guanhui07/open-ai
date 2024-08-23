<?php

namespace SwooleAi\OpenAi;

class FunctionDef
{
    protected  $function = [
        'name' => '',
        'description' => '',
        'parameters' => [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ],
    ];

    public static function create(string $name)
    {
        return new self($name);
    }

    public function __construct(string $name)
    {
        $this->function['name'] = $name;
    }

    public function withDescription(string $description)
    {
        $this->function['description'] = $description;
        return $this;
    }

    public function withParameter(string $name, FunctionParameter $parameter)
    {
        $this->function['parameters']['properties'][$name] = $parameter->toArray();
        return $this;
    }

    public function withRequired()
    {
        $this->function['parameters']['required'] = func_get_args();
        return $this;
    }

    public function toArray(): array
    {
        return $this->function;
    }
}
