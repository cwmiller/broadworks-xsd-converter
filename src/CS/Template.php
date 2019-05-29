<?php
namespace CWM\BroadWorksXsdConverter\CS;

abstract class Template
{
    /** @var string[] */
    private $usings = [];

    /** @var string */
    private $name;

    /** @var string */
    private $namespace;

    /** @var string */
    private $xmlNamespace = '';

    /** @var string */
    private $documentation;

    /** @var Tag[] */
    private $tags = [];

    /**
     * @return string[]
     */
    public function getUsings()
    {
        return $this->usings;
    }

    /**
     * @param string[] $usings
     * @return $this
     */
    public function setUsings($usings)
    {
        $this->usings = $usings;
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
     * @return
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
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getXmlNamespace()
    {
        return $this->xmlNamespace;
    }

    /**
     * @param string $xmlNamespace
     * @return $this
     */
    public function setXmlNamespace($xmlNamespace)
    {
        $this->xmlNamespace = $xmlNamespace;
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
     * @return $this
     */
    public function setDocumentation($documentation)
    {
        $this->documentation = $documentation;
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