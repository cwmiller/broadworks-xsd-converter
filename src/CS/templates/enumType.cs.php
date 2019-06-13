using System;
using System.Xml.Serialization;
<?php /** @var EnumTypeTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
    using <?= $using ?>;
<?php }

use CWM\BroadWorksXsdConverter\CS\EnumTypeTemplate; ?>

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
    public enum <?= $template->getName() ?>
    {
    <?php foreach ($template->getOptions() as $option) { ?>
        [XmlEnum(Name = "<?php echo $option->getValue() ?>")]
        <?php echo $option->getOption() ?>,
    <?php } ?> }
}
