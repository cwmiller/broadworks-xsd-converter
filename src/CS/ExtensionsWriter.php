<?php

namespace CWM\BroadWorksXsdConverter\CS;

use CWM\BroadWorksXsdConverter\ComplexType;
use CWM\BroadWorksXsdConverter\Type;
use RuntimeException;

class ExtensionsWriter
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $modelNamespace;

    /** @var string */
    private $extensionNamespace;

    /** @var string */
    private $errorResponseName;

    /** @var bool */
    private $debug;

    /** @var ExtensionTemplate[] */
    private $extensions = [];

    public function __construct($outDir, $modelNamespace, $extensionNamespace, $errorResponseName, $debug = false)
    {
        $this->outDir = $outDir;
        $this->modelNamespace = $modelNamespace;
        $this->extensionNamespace = $extensionNamespace;
        $this->errorResponseName = $errorResponseName;
        $this->debug = $debug;
    }

    /**
     * @param Type[] $types
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            if (($type instanceof ComplexType) && ($type->getParentName() === 'C:OCIRequest')) {
                $extensionName = preg_replace('/\.xsd/', '', basename($type->getFilePath())) . 'Extensions';

                if (!array_key_exists($extensionName, $this->extensions)) {
                    $extension = (new ExtensionTemplate())
                        ->setName($extensionName)
                        ->setNamespace($this->extensionNamespace);
                    $this->extensions[$extensionName] = $extension;
                } else {
                    $extension = $this->extensions[$extensionName];
                }

                $qualifiedRequestType = $this->qualifiedType($type->getName(), $this->modelNamespace);
                $unqualifiedRequestType = $this->unqualifiedType($type->getName(), $this->modelNamespace);

                $responseTypes = array_map(function($responseType) {
                    return [
                        'qualified' => $this->qualifiedType($responseType, $this->modelNamespace),
                        'unqualified' => $this->unqualifiedType($responseType, $this->modelNamespace)
                    ];
                }, $type->getResponseTypes());

                if (count($responseTypes) === 0) {
                    throw new RuntimeException('No response types for ' . $type->getName());
                }

                if (count($responseTypes) > 1) {
                    throw new RuntimeException('More than one response type for ' . $type->getName());
                }

                foreach ($responseTypes as $responseType) {
                    if (strpos($responseType['unqualified'], 'Response') === false) {
                        throw new RuntimeException('Response ' . $responseType['unqualified'] . ' for ' . $type->getName() . ' doesn\'t seem like a proper response type.');
                    }
                }

                $responseType = array_pop($responseTypes);

                $requestNamespace = explode('.', $qualifiedRequestType);
                array_pop($requestNamespace);
                $extension->addUsing(implode('.', $requestNamespace));

                $responseNamespace = explode('.', $responseType['qualified']);
                array_pop($responseNamespace);
                $extension->addUsing(implode('.', $responseNamespace));


                $method = (new ExtensionMethod())
                    ->setName(ucfirst(ltrim($type->getName(), ':')))
                    ->setParamType($unqualifiedRequestType)
                    ->setReturnType($responseType['unqualified'])
                    ->setDocumentation($type->getDescription());

                $extension->addMethod($method);
            }
        }

        foreach ($this->extensions as $extensionName => $extension) {
            $outputPath =
                $this->outDir
                . DIRECTORY_SEPARATOR
                . str_replace('.', DIRECTORY_SEPARATOR, $this->qualifiedType($extensionName, $this->extensionNamespace))
                . '.cs';

            if ($this->debug) {
                echo sprintf('Writing %s', $outputPath) . PHP_EOL;
            }

            // Ensure the destination directory exists
            $dirPath = dirname($outputPath);
            if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
                throw new RuntimeException('Unable to create directory ' . $dirPath);
            }


            if (!file_put_contents($outputPath, $this->generateContents($extension))) {
                throw new RuntimeException('Unable to write file ' . $outputPath);
            }
        }
    }

    /**
     * @param ExtensionTemplate $template
     * @return string
     */
    private function generateContents(ExtensionTemplate $template)
    {
        ob_start();
        require __DIR__ . '/templates/extension.cs.php';
        return ob_get_clean();
    }

    /**
     * @param string $typeName
     * @param string $rootNamespace
     * @return string
     */
    private function qualifiedType($typeName, $rootNamespace)
    {
        $namespaceSegments = array_filter(
            explode(
                '.',
                $rootNamespace . '.' . str_replace(':', '.', $typeName)
            )
        );

        return implode('.', $namespaceSegments);
    }

    /**
     * @param string $typeName
     * @param string $rootNamespace
     * @return mixed
     */
    private function unqualifiedType($typeName, $rootNamespace)
    {
        $namespaceSegments = array_filter(
            explode(
                '.',
                $rootNamespace . '.' . str_replace(':', '.', $typeName)
            )
        );

        return array_pop($namespaceSegments);
    }
}