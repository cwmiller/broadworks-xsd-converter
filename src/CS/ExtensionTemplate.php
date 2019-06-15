<?php
namespace CWM\BroadWorksXsdConverter\CS;

class ExtensionTemplate
{
    /** @var string */
    private $name;

    /** @var string */
    private $namespace;

    /** @var string[] */
    private $usings = [];

    /** @var ExtensionMethod[] */
    private $methods = [];

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return ExtensionTemplate
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @return ExtensionTemplate
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getUsings()
    {
        return $this->usings;
    }

    /**
     * @param string[] $usings
     * @return ExtensionTemplate
     */
    public function setUsings($usings)
    {
        $this->usings = $usings;
        return $this;
    }

    /**
     * @param string $using
     * @return $this
     */
    public function addUsing($using)
    {
        if (!in_array($using, $this->usings, true)) {
            $this->usings []= $using;
        }
        return $this;

    }

    /**
     * @return ExtensionMethod[]
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * @param ExtensionMethod[] $methods
     * @return ExtensionTemplate
     */
    public function setMethods($methods)
    {
        $this->methods = $methods;
        return $this;
    }

    /**
     * @param ExtensionMethod $method
     * @return ExtensionTemplate
     */
    public function addMethod($method)
    {
        $this->methods[$method->getName()] = $method;
        return $this;
    }
}