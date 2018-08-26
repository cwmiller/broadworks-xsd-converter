<?php /** @var \CWM\BroadWorksXsdConverter\CS\ClassTemplate $template */ ?>
<?php foreach (array_unique($template->getUsings()) as $using) { ?>
using <?php echo $using ?>;
<?php } ?>

namespace <?php echo $template->getNamespace() . PHP_EOL ?>
{
    <?php echo implode(' ', $template->getModifiers()) ?> class <?php echo $template->getClassName()  ?><?php if ($template->getParentClassName() !== null) { ?> : <?php echo $template->getParentClassName() ?><?php } ?> <?php echo PHP_EOL ?>
    {
    <?php foreach ($template->getProperties() as $property) { ?>
    [XmlElement(ElementName = "<?php echo $property->getXmlProperty() ?>")]
    public <?php echo $property->getType() ?> <?php echo $property->getName() ?> { get; set; }

    <?php } ?>

    }
}