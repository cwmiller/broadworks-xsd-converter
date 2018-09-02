using System;
<?php /** @var \CWM\BroadWorksXsdConverter\CS\ClassTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
using <?= $using ?>;
<?php } ?>

namespace <?= $template->getNamespace() ?>

{
    [Serializable]
    <?= implode(' ', $template->getModifiers()) ?> class <?= $template->getClassName() ?> <?= $template->getParentClassName() !== null ? (': ' . $template->getParentClassName()) : '' ?>

    {
    <?php foreach ($template->getProperties() as $property) { ?>
    [XmlElement(ElementName = "<?= $property->getXmlProperty() ?>")] public <?= $property->getType() ?> <?= $property->getName() ?> { get; set; }
    <?php } ?> }
}