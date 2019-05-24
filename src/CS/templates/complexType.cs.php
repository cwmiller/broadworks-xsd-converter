using System;
using System.Xml.Serialization;
<?php /** @var ComplexTypeTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
    using <?= $using ?>;
<?php }

use CWM\BroadWorksXsdConverter\CS\ComplexTypeTemplate; ?>

namespace <?= $template->getNamespace() ?>

{
[Serializable]
public <?= $template->isAbstract() ? 'abstract' : '' ?> class <?= $template->getName() ?> <?= $template->getParentClass() !== null ? (': ' . $template->getParentClass()) : '' ?>

{
<?php foreach ($template->getProperties() as $property) { ?>
    [XmlElement(ElementName = "<?= $property->getElementName() ?>")]
    <?php if ($property->isNillable()) { ?>
    [XmlElement(IsNullable = true)]
    <?php } ?>
    public <?= $property->getType() ?> <?= $property->getName() ?> { get; set; }
<?php } ?> }
}
