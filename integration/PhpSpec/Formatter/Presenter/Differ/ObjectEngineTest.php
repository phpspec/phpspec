<?php

declare(strict_types=1);

namespace PhpSpec\Formatter\Presenter\Differ;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Exporter\Exporter;

class ObjectEngineTest extends TestCase
{
    /** @var ObjectEngine */
    private $engine;

    /** @var Exporter */
    private $exporter;

    /** @var StringEngine */
    private $stringDiffer;

    public function setUp() : void
    {
        $this->exporter = new Exporter();
        $this->stringDiffer = $this->createMock(StringEngine::class);
        $this->engine = new ObjectEngine($this->exporter, $this->stringDiffer);
    }

    /** @test */
    #[Test]
    public function it_is_an_differ_engine()
    {
        self::assertInstanceOf(DifferEngine::class, $this->engine);
    }

    /** @test */
    #[Test]
    public function it_does_not_support_scalars()
    {
        self::assertFalse($this->engine->supports(1, 2));
    }

    /** @test */
    #[Test]
    public function it_only_supports_objects()
    {
        self::assertTrue($this->engine->supports(new \StdClass(), new \StdClass()));
    }

    /** @test */
    #[Test]
    public function it_converts_objects_to_string_and_diffs_the_result()
    {
        $this->stringDiffer->method('compare')
            ->willReturn('-DateTime+ArrayObject');

        $diff = $this->engine->compare(new \DateTime(), new \ArrayObject());

        self::assertSame('-DateTime+ArrayObject', $diff);
    }
}
