<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\Generator;
use PhpSpec\CodeGenerator\Generator\ImplementsGenerator;
use PhpSpec\CodeGenerator\Writer\CodeWriter;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Filesystem;

class ImplementsGeneratorSpec extends ObjectBehavior
{
    function let(Filesystem $fs, CodeWriter $codeWriter)
    {
        $this->beConstructedWith($fs, $codeWriter);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ImplementsGenerator::class);
    }

    function it_is_a_generator()
    {
        $this->shouldHaveType(Generator::class);
    }

    function it_has_no_priority()
    {
        $this->getPriority()->shouldReturn(0);
    }

    function it_supports_implements_generation(Resource $resource)
    {
        $this->supports($resource, 'implements', [])->shouldReturn(true);
    }

    function it_does_not_support_other_generation(Resource $resource)
    {
        $this->supports($resource, 'method', [])->shouldReturn(false);
    }

    function it_generates_implements_on_class($fs, Resource $resource, CodeWriter $codeWriter)
    {
        $classWithoutInterface = <<<CODE
<?php

namespace Acme;

class Foo
{
}

CODE;
        $classWithInterface = <<<CODE
<?php

namespace Acme;

class Foo implements Bar
{
}

CODE;

        $resource->getSrcFilename()->willReturn('/project/src/Acme/Foo.php');
        $resource->getSrcClassname()->willReturn('Acme\Foo');

        $codeWriter->insertImplementsInClass($classWithoutInterface, 'Acme\Bar')->willReturn($classWithInterface);

        $fs->getFileContents('/project/src/Acme/Foo.php')->willReturn($classWithoutInterface);
        $fs->putFileContents('/project/src/Acme/Foo.php', $classWithInterface)->shouldBeCalled();

        $this->generate($resource, ['interface' => 'Acme\Bar']);
    }
}
