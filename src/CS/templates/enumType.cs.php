using System;
using System.Xml.Serialization;
<?php /** @var EnumTypeTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
    using <?= $using ?>;
<?php }

use CWM\BroadWorksXsdConverter\CS\EnumTypeTemplate; ?>

namespace <?= $template->getNamespace() ?>

{
[Serializable]
[XmlRoot(Namespace = "<?php echo $template->getXmlNamespace() ?>")]
public enum <?= $template->getName() ?>
{
<?php foreach ($template->getOptions() as $option) { ?>
    [XmlEnum(Name = "<?php echo $option->getValue() ?>")]
    <?php echo $option->getOption() ?>,
<?php } ?> }
}
