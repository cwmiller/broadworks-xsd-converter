<?php

namespace CWM\BroadWorksXsdConverter\PHP;

use CWM\BroadWorksXsdConverter\ComplexType;
use CWM\BroadWorksXsdConverter\Type;
use ReflectionClass;
use RuntimeException;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlock\Tag\ThrowsTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\TraitGenerator;

class TraitWriter
{
    /** @var string */
    private $outDir;

    /** @var string */
    private $modelNamespace;

    /** @var string */
    private $traitNamespace;

    /** @var string */
    private $errorResponseClassname;

    /** @var string */
    private $validationClassname;

    /** @var bool */
    private $debug;

    /** @var TraitGenerator[] */
    private $traits = [];

    /**
     * @param string $outDir
     * @param string $modelNamespace
     * @param string $traitNamespace
     * @param string $errorResponseClassname
     * @param string $validationClassname
     * @param bool $debug
     */
    public function __construct($outDir, $modelNamespace, $traitNamespace, $errorResponseClassname, $validationClassname, $debug = false)
    {
        $this->outDir = $outDir;
        $this->modelNamespace = $modelNamespace;
        $this->traitNamespace = $traitNamespace;
        $this->errorResponseClassname = $errorResponseClassname;
        $this->validationClassname = $validationClassname;
        $this->debug = $debug;
    }

    /**
     * @param Type[] $types
     * @throws \RuntimeException
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function write(array $types)
    {
        foreach ($types as $type) {
            if (($type instanceof ComplexType) && ($type->getParentName() === 'C:OCIRequest')) {
                $traitName = preg_replace('/\.xsd/', '', basename($type->getFilePath()));

                if (!array_key_exists($traitName, $this->traits)) {
                    $trait = (new TraitGenerator())
                        ->setName($traitName)
                        ->setNamespaceName($this->traitNamespace);

                    $this->traits[$traitName] = $trait;
                } else {
                    $trait = $this->traits[$traitName];
                }

                $qualifiedRequestType = $this->qualifiedType($type->getName(), $this->modelNamespace);
                $unqualifiedRequestType = $this->unqualifiedType($type->getName(), $this->modelNamespace);

                $responseTypes = array_map(function($responseType) {
                    return [
                        'qualified' => $this->qualifiedType($responseType, $this->modelNamespace),
                        'unqualified' => $this->unqualifiedType($responseType, $this->modelNamespace)
                    ];
                }, $type->getResponseTypes());

                $trait->addUse($qualifiedRequestType);
                //$trait->addUse('CWM\BroadWorksConnector\Ocip\ErrorResponseException');
                $trait->addUse($this->errorResponseClassname);
                $trait->addUse($this->validationClassname);
                foreach ($responseTypes as $responseType) {
                    $trait->addUse($responseType['qualified']);
                }

                $method = (new MethodGenerator())
                    ->setName(lcfirst(ltrim($type->getName(), ':')))
                    ->setParameter(['name' => 'request', 'type' => $qualifiedRequestType])
                    ->setBody('return $this->call($request);')
                    ->setDocBlock((new DocBlockGenerator())
                        ->setTags([
                            new ParamTag('request', $unqualifiedRequestType),
                            new ReturnTag(['datatype' => implode('|', array_map(function($responseType) {
                                return $responseType['unqualified'];
                            }, $responseTypes))]),
                            new ThrowsTag(substr(strrchr($this->errorResponseClassname, '\\'), 1)),
                            new ThrowsTag(substr(strrchr($this->validationClassname, '\\'), 1))
                        ])
                        ->setWordWrap(false));



                $trait->addMethodFromGenerator($method);
            }
        }

        foreach ($this->traits as $traitName => $trait) {
            $outputPath =
                $this->outDir
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $this->qualifiedType($traitName, $this->traitNamespace))
                . '.php';

            if ($this->debug) {
                echo sprintf('Writing %s', $outputPath) . PHP_EOL;
            }

            // Ensure the destination directory exists
            $dirPath = dirname($outputPath);
            if (!is_dir($dirPath) && !mkdir($dirPath, null, true)) {
                throw new RuntimeException('Unable to create directory ' . $dirPath);
            }

            $file = new FileGenerator(['classes' => [$trait]]);
            if (!file_put_contents($outputPath, $file->generate())) {
                throw new RuntimeException('Unable to write file ' . $outputPath);
            }
        }
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
                '\\',
                $rootNamespace . '\\' . str_replace(':', '\\', $typeName)
            )
        );

        return implode('\\', $namespaceSegments);
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
                '\\',
                $rootNamespace . '\\' . str_replace(':', '\\', $typeName)
            )
        );

        return array_pop($namespaceSegments);
    }
}