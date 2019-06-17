<?php
namespace CWM\BroadWorksXsdConverter\CS;

class Annotation
{
    /** @var string */
    private $name;

    /** @var string|int */
    private $value = null;

    /**
     * Annotation constructor.
     * @param $name
     * @param $value
     */
    public function __construct($name, $value = null)
    {
        $this->name = $name;
        $this->value = $value;
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
     * @return Annotation
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string|int $value
     * @return Annotation
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function generate()
    {
        if ($this->value === null) {
            return sprintf('[%s]', $this->name);
        } else if (is_int($this->value) || is_numeric($this->value)) {
            return sprintf('[%s(%d)]', $this->name, $this->value);
        } else {
            return sprintf('[%s(@"%s")]', $this->name, str_replace('"', '""', $this->value));
        }
    }

}