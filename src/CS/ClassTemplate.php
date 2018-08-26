<?php

namespace CWM\BroadWorksXsdConverter\CS;

class ClassTemplate
{
    /** @var string[] */
    protected $usings = [];

    /** @var string */
    protected $namespace;

    /** @var string[] */
    protected $modifiers = ['public'];

    /** @var string */
    protected $className;

    /** @var string */
    protected $parentClassName;

    /** @var PropertyTemplate[] */
    protected $properties = [];

    /**
     * @return string[]
     */
    public function getUsings()
    {
        return $this->usings;
    }

    /**
     * @param string[] $usings
     * @return ClassTemplate
     */
    public function setUsings($usings)
    {
        $this->usings = $usings;
        return $this;
    }

    public function addUsing($using)
    {
        $this->usings[] = $using;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return ClassTemplate
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * @param string[] $modifiers
     * @return ClassTemplate
     */
    public function setModifiers($modifiers)
    {
        $this->modifiers = $modifiers;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     * @return ClassTemplate
     */
    public function setClassName($className)
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @return string
     */
    public function getParentClassName()
    {
        return $this->parentClassName;
    }

    /**
     * @param string $parentClassName
     * @return ClassTemplate
     */
    public function setParentClassName($parentClassName)
    {
        $this->parentClassName = $parentClassName;
        return $this;
    }

    /**
     * @return PropertyTemplate[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param PropertyTemplate[] $properties
     * @return ClassTemplate
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    public function addProperty(PropertyTemplate $property)
    {
        $this->properties[] = $property;
        return $this;
    }
}