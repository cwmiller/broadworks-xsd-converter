using System;
using System.Xml.Serialization;
<?php /** @var ComplexTypeTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
using <?= $using ?>;
<?php }

use CWM\BroadWorksXsdConverter\CS\ComplexTypeTemplate; ?>

namespace <?= $template->getNamespace() ?>

{
    /// <summary>
    /// <?php echo implode("\n    /// ", array_map('trim', explode("\n", $template->getDocumentation()))) ?>

    <?php foreach ($template->getReferences() as $reference) { ?>
    /// <see cref="<?php echo $reference ?>"/>
    <?php } ?>
    /// </summary>
    [Serializable]
    [XmlRoot(Namespace = "<?php echo $template->getXmlNamespace() ?>")]
    <?php foreach ($template->getChildClasses() as $childClass) { ?>
    [XmlInclude(typeof(<?php echo $childClass ?>))]
    <?php } ?> <?= "\n\r"  ?>
    public <?= $template->isAbstract() ? 'abstract ' : '' ?>class <?= $template->getName() ?> <?= $template->getParentClass() !== null ? (': ' . $template->getParentClass()) : '' ?>

    {

        <?php foreach ($template->getProperties() as $property) { ?>

        private <?= $property->getType() ?> _<?= lcfirst($property->getName()) ?>;

        [XmlElement(ElementName = "<?= $property->getElementName() ?>", IsNullable = <?php echo $property->isNillable() ? 'true' : 'false' ?>, Namespace = "")]
        public <?= $property->getType() ?> <?= $property->getName() ?> {
            get => _<?= lcfirst($property->getName()) ?>;
            set {
                <?= $property->getName() ?>Specified = true;
                _<?= lcfirst($property->getName()) ?> = value;
            }
        }

        [XmlIgnore]
        public bool <?php echo $property->getName() ?>Specified { get; set; }
        <?php } ?>

    }
}
