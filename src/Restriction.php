<?php

namespace CWM\BroadWorksXsdConverter;

class Restriction
{
    /** @var string */
    private $base;

    /** @var string[] */
    private $enumerations = [];

    /** @var int|null */
    private $length;

    /** @var int|null */
    private $minLength;

    /** @var int|null */
    private $maxLength;

    /** @var int|null */
    private $minInclusive;

    /** @var int|null */
    private $maxInclusive;

    /** @var string|null */
    private $pattern;

    /** @var string|null */
    private $whiteSpace;

    /**
     * @return string
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * @param string $base
     * @return Restriction
     */
    public function setBase($base)
    {
        $this->base = $base;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getEnumerations()
    {
        return $this->enumerations;
    }

    /**
     * @param string[] $enumerations
     * @return Restriction
     */
    public function setEnumerations($enumerations)
    {
        $this->enumerations = $enumerations;
        return $this;
    }

    /**
     * @param string $enumeration
     * @return $this
     */
    public function addEnumeration($enumeration)
    {
        $this->enumerations[] = $enumeration;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param int|null $length
     * @return Restriction
     */
    public function setLength($length)
    {
        $this->length = $length;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMinLength()
    {
        return $this->minLength;
    }

    /**
     * @param int|null $minLength
     * @return Restriction
     */
    public function setMinLength($minLength)
    {
        $this->minLength = $minLength;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * @param int|null $maxLength
     * @return Restriction
     */
    public function setMaxLength($maxLength)
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMinInclusive()
    {
        return $this->minInclusive;
    }

    /**
     * @param int|null $minInclusive
     * @return Restriction
     */
    public function setMinInclusive($minInclusive)
    {
        $this->minInclusive = $minInclusive;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxInclusive()
    {
        return $this->maxInclusive;
    }

    /**
     * @param int|null $maxInclusive
     * @return Restriction
     */
    public function setMaxInclusive($maxInclusive)
    {
        $this->maxInclusive = $maxInclusive;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string|null $pattern
     * @return Restriction
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getWhiteSpace()
    {
        return $this->whiteSpace;
    }

    /**
     * @param string|null $whiteSpace
     * @return Restriction
     */
    public function setWhiteSpace($whiteSpace)
    {
        $this->whiteSpace = $whiteSpace;
        return $this;
    }
}