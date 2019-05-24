<?php

namespace CWM\BroadWorksXsdConverter\CS;

class Tag
{
    /** @var string */
    private $name;

    /** @var string|null */
    private $content;

    public function __construct($name, $content = null)
    {
        $this->name = $name;
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function generate()
    {
        return trim('@' . $this->name . ' ' . $this->content);
    }
}