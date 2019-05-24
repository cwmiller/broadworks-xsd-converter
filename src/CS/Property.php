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

    /** @var Tag[] */
    private $tags = [];

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
     * @return Tag[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Tag[] $tags
     * @return $this
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
        return $this;
    }
}