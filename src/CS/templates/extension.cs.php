using System.Threading.Tasks;
<?php /** @var \CWM\BroadWorksXsdConverter\CS\ExtensionTemplate $template */
foreach (array_unique($template->getUsings()) as $using) { ?>
    using <?= $using ?>;
<?php } ?>

namespace <?= $template->getNamespace() ?>

{
public static class <?= $template->getName() ?>

{

<?php foreach ($template->getMethods() as $method) { ?>
    /// <summary>
    /// <?php echo implode("\n    /// ", array_map('trim', explode("\n", $method->getDocumentation()))) . PHP_EOL ?>
    /// </summary>
    public static async Task<<?= $method->getReturnType() ?>> <?= $method->getName() ?>(this OcipClient client, <?= $method->getParamType() ?> request) {
        return await client.Call(request) as <?= $method->getReturnType() ?>;
    }

<?php } ?>

}
}
