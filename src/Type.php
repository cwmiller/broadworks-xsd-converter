<?php

namespace CWM\BroadWorksXsdConverter;

abstract class Type
{
    /** @var string|null */
    private $filePath;

    /** @var string|null */
    private $name;

    /** @var string|null */
    private $description;

    /** @var string[] */
    private $references = [];

    /**
     * @return string|null
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param string|null $filePath
     * @return $this
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string|null $name
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
    public function getUnqualifiedName()
    {
        $segments = explode(':', $this->name);
        return array_pop($segments);
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        $segments = explode(':', $this->name);
        array_pop($segments);
        return implode(':', $segments);
    }

    /**
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * @param string[] $references
     * @return $this
     */
    public function setReferences($references)
    {
        $this->references = $references;
        return $this;
    }
}