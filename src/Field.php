<?php

namespace CWM\BroadWorksXsdConverter;

class Field
{
    /** @var string */
    private $name;

    /** @var string */
    private $typeName;

    /** @var string */
    private $description;

    /** @var bool */
    private $isArray = false;

    /** @var bool */
    protected $isNillable = false;

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
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * @param string $typeName
     * @return $this
     */
    public function setTypeName($typeName)
    {
        $this->typeName = $typeName;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return Field
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool
     */
    public function isArray()
    {
        return $this->isArray;
    }

    /**
     * @param bool $isArray
     * @return $this
     */
    public function setIsArray($isArray)
    {
        $this->isArray = $isArray;
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
     * @return Field
     */
    public function setIsNillable($isNillable)
    {
        $this->isNillable = $isNillable;
        return $this;
    }
}