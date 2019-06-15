<?php
namespace CWM\BroadWorksXsdConverter\CS;

class ExtensionMethod
{
    /** @var string */
    private $name;

    /** @var string */
    private $returnType;

    /** @var string */
    private $paramType;

    /** @var string */
    private $documentation;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return ExtensionMethod
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * @param string $returnType
     * @return ExtensionMethod
     */
    public function setReturnType($returnType)
    {
        $this->returnType = $returnType;
        return $this;
    }

    /**
     * @return string
     */
    public function getParamType()
    {
        return $this->paramType;
    }

    /**
     * @param string $paramType
     * @return ExtensionMethod
     */
    public function setParamType($paramType)
    {
        $this->paramType = $paramType;
        return $this;
    }

    /**
     * @return string
     */
    public function getDocumentation()
    {
        return $this->documentation;
    }

    /**
     * @param string $documentation
     * @return ExtensionMethod
     */
    public function setDocumentation($documentation)
    {
        $this->documentation = $documentation;
        return $this;
    }
}