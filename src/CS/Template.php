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

    /** @var string[] */
    private $references = [];

    /** @var Annotation[] */
    private $annotations = [];

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
}