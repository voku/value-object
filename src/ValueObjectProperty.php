<?php

namespace Spatie\ValueObject;

use ReflectionProperty;

class ValueObjectProperty extends ReflectionProperty
{
    /** @var array */
    protected static $typeMapping = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];

    /** @var \Spatie\ValueObject\ValueObject */
    protected $valueObject;

    /** @var bool */
    protected $hasTypeDeclaration = false;

    /** @var bool */
    protected $isNullable = false;

    /** @var bool */
    protected $isInitialised = false;

    /** @var array */
    protected $types = [];

    /** @var \Spatie\ValueObject\ValueObjectDefinition */
    protected $valueObjectDefinition;

    public static function fromReflection(
        ValueObject $valueObject,
        ValueObjectDefinition $valueObjectDefinition,
        ReflectionProperty $reflectionProperty
    ) {
        return new self($valueObject, $valueObjectDefinition, $reflectionProperty);
    }

    public function __construct(
        ValueObject $valueObject,
        ValueObjectDefinition $valueObjectDefinition,
        ReflectionProperty $reflectionProperty
    ) {
        parent::__construct($reflectionProperty->class, $reflectionProperty->getName());

        $this->valueObject = $valueObject;

        $this->valueObjectDefinition = $valueObjectDefinition;

        $this->resolveTypeDefinition();
    }

    public function set($value)
    {
        if (! $this->isValidType($value)) {
            throw ValueObjectError::invalidType($this, $value);
        }

        $this->isInitialised = true;

        $this->valueObject->{$this->getName()} = $value;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function getFqn(): string
    {
        return "{$this->getDeclaringClass()->getName()}::{$this->getName()}";
    }

    protected function resolveTypeDefinition()
    {
        $docComment = $this->getDocComment();

        if (! $docComment) {
            return;
        }

        preg_match('/\@var ([\w|\\\\]+)/', $docComment, $matches);

        if (! count($matches)) {
            return;
        }

        $varDocComment = end($matches);

        $this->types = explode('|', $varDocComment);

        $this->isNullable = strpos($varDocComment, 'null') !== false;

        $this->hasTypeDeclaration = true;
    }

    protected function isValidType($value): bool
    {
        if (! $this->hasTypeDeclaration) {
            return true;
        }

        if ($this->isNullable && $value === null) {
            return true;
        }

        foreach ($this->types as $currentType) {
            $isValidType = $this->assertTypeEquals($currentType, $value);

            if ($isValidType) {
                return true;
            }
        }

        return false;
    }

    protected function assertTypeEquals(string $type, $value): bool
    {
        if ($type === 'mixed' && $value !== null) {
            return true;
        }

        if (class_exists($type)) {
            return $value instanceof $type;
        }

        if ($this->valueObjectDefinition->hasAlias($type)) {
            $type = $this->valueObjectDefinition->resolveAlias($type);

            return $value instanceof $type;
        }

        return gettype($value) === (self::$typeMapping[$type] ?? $type);
    }
}
