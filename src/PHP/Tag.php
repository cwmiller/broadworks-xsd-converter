<?php
namespace CWM\BroadWorksXsdConverter\PHP;

use Zend\Code\Generator\DocBlock\Tag\GenericTag;

/**
 * Override's Zend's GenericTag due to GenericTag ommitting the "content" value if empty() returns false for it.
 */
class Tag extends GenericTag
{
    public function __construct($name = null, $content = null)
    {
        parent::__construct($name, $content);

        $this->setName($name);
        $this->setContent($content);
    }

    /**
     * @return string
     */
    public function generate()
    {
        return trim('@' . $this->name . ' ' . $this->content);
    }
}