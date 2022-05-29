using System;
using System.Xml.Serialization;
using System.ComponentModel.DataAnnotations;
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
    <?php } ?> <?= PHP_EOL  ?>
    <?=  implode(PHP_EOL, array_map(function($a) { return $a->generate(); }, $template->getAnnotations())) ?><?= PHP_EOL  ?>
    public <?= $template->isAbstract() ? 'abstract ' : '' ?>class <?= $template->getName() ?> <?= $template->getParentClass() !== null ? (': ' . $template->getParentClass()) : '' ?>

    {
        <?php foreach ($template->getProperties() as $property) { ?>

        protected <?= $property->getType() ?> _<?= lcfirst($property->getName()) ?><?php echo $property->getDefaultValue() !== null ? (' = ' . $property->getDefaultValue()) : '' ?>;

        [XmlElement(ElementName = "<?= $property->getElementName() ?>", IsNullable = <?php echo $property->isNillable() ? 'true' : 'false' ?>, Namespace = "")]
        <?=  implode(PHP_EOL, array_map(function($a) { return $a->generate(); }, $property->getAnnotations())) ?><?= PHP_EOL  ?>
        public <?= $property->getType() ?> <?= $property->getName() ?> {
            get => _<?= lcfirst($property->getName()) ?>;
            set {
                <?= $property->getName() ?>Specified = true;
                _<?= lcfirst($property->getName()) ?> = value;
            }
        }

        [XmlIgnore]
        protected bool <?php echo $property->getName() ?>Specified { get; set; }
        <?php } ?>

    }
}
