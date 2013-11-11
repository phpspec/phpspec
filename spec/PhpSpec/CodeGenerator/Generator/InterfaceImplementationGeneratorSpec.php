<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\ObjectBehavior;

class InterfaceImplementationGeneratorSpec extends ObjectBehavior
{
    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Util\Filesystem $fs
     */
    function let($io, $fs)
    {
        $this->beConstructedWith($io, $fs);
    }

    function it_is_a_generator()
    {
        $this->shouldImplement('PhpSpec\CodeGenerator\Generator\GeneratorInterface');
    }

    /**
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_supports_implementation_generations($resource)
    {
        $this->supports($resource, 'implementation', array())->shouldReturn(true);
    }

    /**
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_does_not_support_anything_else($resource)
    {
        $this->supports($resource, 'anything_else', array())->shouldReturn(false);
    }

    function its_priority_is_0()
    {
        $this->getPriority()->shouldReturn(0);
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_adds_interface_implementation_header($io, $fs, $resource)
    {
        $classWithoutInterface = <<<CODE
<?php

namespace Winterfell;

class Knight
{
}

CODE;
        $classWithInterface = <<<CODE
<?php

namespace Winterfell;

class Knight implements \BastardInterface
{
}

CODE;
        $resource->getSrcFilename()->willReturn('/project/src/Acme/App.php');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $fs->getFileContents('/project/src/Acme/App.php')->willReturn($classWithoutInterface);
        $fs->putFileContents('/project/src/Acme/App.php', $classWithInterface)->shouldBeCalled();

        $this->generate($resource, array('interface' => 'BastardInterface'));
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_adds_interface_implementation_header_if_class_already_has_interfaces($io, $fs, $resource)
    {
        $classWithoutInterface = <<<CODE
<?php

namespace KingsLanding;

class King extends Royalty implements LannisterInterface, BaratheonInterface {
    public function someMethod()
    {
        echo 'class implements something';
    }
}

CODE;
        $classWithInterface = <<<CODE
<?php

namespace KingsLanding;

class King extends Royalty implements LannisterInterface, BaratheonInterface, \Bastard\BastardInterface {
    public function someMethod()
    {
        echo 'class implements something';
    }
}

CODE;
        $resource->getSrcFilename()->willReturn('/project/src/Acme/App.php');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $fs->getFileContents('/project/src/Acme/App.php')->willReturn($classWithoutInterface);
        $fs->putFileContents('/project/src/Acme/App.php', $classWithInterface)->shouldBeCalled();

        $this->generate($resource, array('interface' => 'Bastard\BastardInterface'));
    }
}
