<?php
namespace CWM\BroadWorksXsdConverter\CS;

class Property
{
    /** @var string */
    private $name;

    /** @var string */
    private $elementName;

    /** @var bool */
    private $isNillable = false;

    /** @var string */
    private $type;

    /** @var Annotation[] */
    private $annotations = [];

    /** @var null|string */
    private $defaultValue = null;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getElementName()
    {
        return $this->elementName;
    }

    /**
     * @param string $elementName
     * @return $this
     */
    public function setElementName($elementName)
    {
        $this->elementName = $elementName;
        return $this;
    }

    /**
     * @return bool
     */
    public function isNillable()
    {
        return $this->isNillable;
    }

    /**
     * @param bool $isNillable
     * @return $this
     */
    public function setIsNillable($isNillable)
    {
        $this->isNillable = $isNillable;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Annotation[]
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * @param Annotation[] $annotations
     * @return $this
     */
    public function setAnnotations($annotations)
    {
        $this->annotations = $annotations;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string|null $defaultValue
     * @return Property
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }
}