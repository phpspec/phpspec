<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Assembler;

use PhpSpec\Formatter\Presenter\Differ\ArrayEngine;
use PhpSpec\Formatter\Presenter\Differ\Differ;
use PhpSpec\Formatter\Presenter\Differ\ObjectEngine;
use PhpSpec\Formatter\Presenter\Differ\StringEngine;
use PhpSpec\Formatter\Presenter\Exception\CallArgumentsPresenter;
use PhpSpec\Formatter\Presenter\Exception\GenericPhpSpecExceptionPresenter;
use PhpSpec\Formatter\Presenter\Exception\HtmlPhpSpecExceptionPresenter;
use PhpSpec\Formatter\Presenter\Exception\SimpleExceptionPresenter;
use PhpSpec\Formatter\Presenter\Exception\SimpleExceptionElementPresenter;
use PhpSpec\Formatter\Presenter\Exception\TaggingExceptionElementPresenter;
use PhpSpec\Formatter\Presenter\SimplePresenter;
use PhpSpec\Formatter\Presenter\TaggingPresenter;
use PhpSpec\Formatter\Presenter\Value\ArrayTypePresenter;
use PhpSpec\Formatter\Presenter\Value\BaseExceptionTypePresenter;
use PhpSpec\Formatter\Presenter\Value\BooleanTypePresenter;
use PhpSpec\Formatter\Presenter\Value\CallableTypePresenter;
use PhpSpec\Formatter\Presenter\Value\ComposedValuePresenter;
use PhpSpec\Formatter\Presenter\Value\NullTypePresenter;
use PhpSpec\Formatter\Presenter\Value\ObjectTypePresenter;
use PhpSpec\Formatter\Presenter\Value\QuotingStringTypePresenter;
use PhpSpec\Formatter\Presenter\Value\TruncatingStringTypePresenter;
use Interop\Container\ContainerInterface;
use UltraLite\Container\Container;
use SebastianBergmann\Exporter\Exporter;

class PresenterAssembler
{
    public function assemble(Container $container)
    {
        $this->assembleDiffer($container);
        $this->assembleDifferEngines($container);
        $this->assembleTypePresenters($container);
        $this->assemblePresenter($container);
        $this->assembleHtmlPresenter($container);
    }

    private function assembleDiffer(Container $container)
    {
        $container->set('formatter.presenter.differ', function (ContainerInterface $c) {
            $differ = new Differ();

            array_map(
                array($differ, 'addEngine'),
                $c->get('phpspec.formatter.differ-engines')
            );

            return $differ;
        });
    }

    private function assembleDifferEngines(Container $container)
    {
        $container->set('formatter.presenter.differ.engines.string', function () {
            return new StringEngine();
        });

        $container->set('formatter.presenter.differ.engines.array', function () {
            return new ArrayEngine();
        });

        $container->set('formatter.presenter.differ.engines.object', function (ContainerInterface $c) {
            return new ObjectEngine(
                new Exporter(),
                $c->get('formatter.presenter.differ.engines.string')
            );
        });
    }

    private function assembleTypePresenters(Container $container)
    {
        $container->set('formatter.presenter.value.array_type_presenter', function () {
            return new ArrayTypePresenter();
        });

        $container->set('formatter.presenter.value.boolean_type_presenter', function () {
            return new BooleanTypePresenter();
        });

        $container->set('formatter.presenter.value.callable_type_presenter', function (ContainerInterface $c) {
            return new CallableTypePresenter($c->get('formatter.presenter'));
        });

        $container->set('formatter.presenter.value.exception_type_presenter', function () {
            return new BaseExceptionTypePresenter();
        });

        $container->set('formatter.presenter.value.null_type_presenter', function () {
            return new NullTypePresenter();
        });

        $container->set('formatter.presenter.value.object_type_presenter', function () {
            return new ObjectTypePresenter();
        });

        $container->set('formatter.presenter.value.string_type_presenter', function () {
            return new TruncatingStringTypePresenter(new QuotingStringTypePresenter());
        });
    }

    private function assemblePresenter(Container $container)
    {
        $container->set('formatter.presenter', function (ContainerInterface $c) {
            return new TaggingPresenter(
                new SimplePresenter(
                    $c->get('formatter.presenter.value_presenter'),
                    new SimpleExceptionPresenter(
                        $c->get('formatter.presenter.differ'),
                        $c->get('formatter.presenter.exception_element_presenter'),
                        new CallArgumentsPresenter($c->get('formatter.presenter.differ')),
                        $c->get('formatter.presenter.exception.phpspec')
                    )
                )
            );
        });

        $container->set('formatter.presenter.value_presenter', function () {
            return new ComposedValuePresenter();
        });

        $container->set('formatter.presenter.exception_element_presenter', function (ContainerInterface $c) {
            return new TaggingExceptionElementPresenter(
                $c->get('formatter.presenter.value.exception_type_presenter'),
                $c->get('formatter.presenter.value_presenter')
            );
        });

        $container->set('formatter.presenter.exception.phpspec', function (ContainerInterface $c) {
            return new GenericPhpSpecExceptionPresenter(
                $c->get('formatter.presenter.exception_element_presenter')
            );
        });
    }

    private function assembleHtmlPresenter(Container $container)
    {
        $container->set('formatter.presenter.html', function (ContainerInterface $c) {
            return new SimplePresenter(
                $c->get('formatter.presenter.value_presenter'),
                new SimpleExceptionPresenter(
                    $c->get('formatter.presenter.differ'),
                    $c->get('formatter.presenter.html.exception_element_presenter'),
                    new CallArgumentsPresenter($c->get('formatter.presenter.differ')),
                    $c->get('formatter.presenter.html.exception.phpspec')
                )
            );
        });

        $container->set('formatter.presenter.html.exception_element_presenter', function (ContainerInterface $c) {
            return new SimpleExceptionElementPresenter(
                $c->get('formatter.presenter.value.exception_type_presenter'),
                $c->get('formatter.presenter.value_presenter')
            );
        });

        $container->set('formatter.presenter.html.exception.phpspec', function () {
            return new HtmlPhpSpecExceptionPresenter();
        });
    }
}
