<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Chris Kruining <chrise@keruining.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace PhpSpec\Formatter
{
    use PhpSpec\Event\ExampleEvent;
    use PhpSpec\Event\SpecificationEvent;
    use PhpSpec\Event\SuiteEvent;

    final class JsonFormatter extends ConsoleFormatter
    {
        private $data = [];

        private const STATUS_NAME = [
            ExampleEvent::PASSED  => 'passed',
            ExampleEvent::PENDING => 'pending',
            ExampleEvent::SKIPPED => 'skipped',
            ExampleEvent::FAILED  => 'failed',
            ExampleEvent::BROKEN  => 'broken',
        ];

        public function beforeSpecification(SpecificationEvent $event)
        {
            $this->data[$event->getSpecification()->getTitle()] = [];
        }

        public function afterExample(ExampleEvent $event)
        {
            $specification = $event->getSpecification()->getTitle();
            $example = $event->getTitle();

            $this->data[$specification][$example] = [
                'status' => static::STATUS_NAME[$event->getResult()],
                'time' => $event->getTime(),
            ];

            $exception = $event->getException();

            if($exception === null)
            {
                return;
            }

            $this->data[$specification][$example]['@exception'] = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        }

        public function afterSpecification(SpecificationEvent $event)
        {
            $this->data[$event->getSpecification()->getTitle()]['@meta'] = [
                'status' => static::STATUS_NAME[$event->getResult()],
                'time' => $event->getTime(),
            ];
        }

        public function afterSuite(SuiteEvent $event)
        {
            $this->data['@meta'] = [
                'result' => static::STATUS_NAME[$event->getResult()],
                'time' => $event->getTime(),
            ];

            $this->getIO()->write(json_encode($this->data));
        }
    }
}
