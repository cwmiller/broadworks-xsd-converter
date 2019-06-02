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
[XmlRoot(Namespace = "<?php echo $template->getXmlNamespace() ?>")]
<?php foreach ($template->getChildClasses() as $childClass) { ?>
[XmlInclude(typeof(<?php echo $childClass ?>))]
<?php } ?>
public <?= $template->isAbstract() ? 'abstract' : '' ?> class <?= $template->getName() ?> <?= $template->getParentClass() !== null ? (': ' . $template->getParentClass()) : '' ?>

{
<?php foreach ($template->getProperties() as $property) { ?>
    [XmlElement(ElementName = "<?= $property->getElementName() ?>", IsNullable = <?php echo $property->isNillable() ? 'true' : 'false' ?>, Namespace = "")]
    public <?= $property->getType() ?> <?= $property->getName() ?> { get; set; }
<?php } ?> }
}
