<?php
namespace CWM\BroadWorksXsdConverter\CS;

class ComplexTypeTemplate extends Template
{
    /** @var bool */
    private $isAbstract;

    /** @var string|null */
    private $parentClass;

    /** @var Property[] */
    private $properties = [];

    /** @var string[] */
    private $childClasses = [];

    /**
     * @return bool
     */
    public function isAbstract()
    {
        return $this->isAbstract;
    }

    /**
     * @param bool $isAbstract
     * @return $this
     */
    public function setIsAbstract($isAbstract)
    {
        $this->isAbstract = $isAbstract;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * @param string|null $parentClass
     * @return $this
     */
    public function setParentClass($parentClass)
    {
        $this->parentClass = $parentClass;
        return $this;
    }

    /**
     * @return Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param Property[] $properties
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getChildClasses()
    {
        return $this->childClasses;
    }

    /**
     * @param string[] $childClasses
     * @return ComplexTypeTemplate
     */
    public function setChildClasses($childClasses)
    {
        $this->childClasses = $childClasses;
        return $this;
    }

}