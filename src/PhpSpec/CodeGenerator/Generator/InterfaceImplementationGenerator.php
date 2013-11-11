<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Console\IO;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\ResourceInterface;

class InterfaceImplementationGenerator implements GeneratorInterface
{
    protected $io;
    protected $filesystem;

    public function __construct(IO $io, Filesystem $filesystem = null)
    {
        $this->io         = $io;
        $this->filesystem = $filesystem ?: new Filesystem;
    }

    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return 'implementation' === $generation;
    }

    public function generate(ResourceInterface $resource, array $data = array())
    {
        $filepath = $resource->getSrcFilename();
        $interface = $data['interface'];

        $code = $this->filesystem->getFileContents($filepath);
        $code = $this->replaceClassDeclaration($code, $interface);

        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln(sprintf(
            "<info>Class <value>%s</value> now implements <value>%s</value>.</info>\n",
            $resource->getSrcClassname(), $interface
        ), 2);
    }

    public function getPriority()
    {
        return 0;
    }

    protected function replaceClassDeclaration($code, $interface)
    {
        preg_match('/(?<access>\.*)?class\s+(?<class>\w+)(?<extends>\s?extends\s+\w+)?(\s?implements\s+(?<interfaces>.*))?(?<bracket>.*)?/',
            $code, $matches);

        $endingBracket = (!empty($matches['bracket']) && strpos($matches['bracket'], '{')) ?
            ' {' : '';
        if (!empty($matches['interfaces'])) {
            $interfaces = $matches['interfaces'];
            $bracketPosition = strpos($matches['interfaces'], '{');
            if ($bracketPosition) {
                $interfaces = strpos($matches['interfaces'], '{') ?
                    substr($interfaces, 0, $bracketPosition) : $interfaces;
                $endingBracket = ' {';
            }
        }

        $interfaces = !empty($interfaces) ? trim($interfaces) . ', \\' . $interface :
            '\\' . $interface;
        $extends = !empty($matches['extends']) ? $matches['extends'] : '';

        $classDeclaration = $matches['access'] . 'class ' . $matches['class'] . $extends .
            ' implements ' . $interfaces . $endingBracket;

        $code = preg_replace('/(\.*)?class\s+(\w+)(\s?extends\s+\w+)?(\s?implements\s+(.*))?(.*)?/', $classDeclaration, $code, 1);

        return $code;
    }
}
