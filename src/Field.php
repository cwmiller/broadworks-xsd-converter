<?php

namespace CWM\BroadWorksXsdConverter;

class Field
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $typeName;

    /** @var bool */
    protected $isArray = false;

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
}