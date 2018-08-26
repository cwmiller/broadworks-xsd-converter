<?php

namespace CWM\BroadWorksXsdConverter\CS;

class PropertyTemplate
{
    /** @var string */
    protected $type;

    /** @var string */
    protected $name;

    /** @var string */
    protected $xmlProperty;

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return PropertyTemplate
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return PropertyTemplate
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getXmlProperty()
    {
        return $this->xmlProperty;
    }

    /**
     * @param string $xmlProperty
     * @return PropertyTemplate
     */
    public function setXmlProperty($xmlProperty)
    {
        $this->xmlProperty = $xmlProperty;
        return $this;
    }
}