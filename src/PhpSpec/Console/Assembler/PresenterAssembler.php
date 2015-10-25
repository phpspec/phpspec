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
use PhpSpec\Formatter\Presenter\Exception\TaggingExceptionElementPresenter;
use PhpSpec\Formatter\Presenter\SimplePresenter;
use PhpSpec\Formatter\Presenter\TaggedPresenter;
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
use PhpSpec\ServiceContainer;
use SebastianBergmann\Exporter\Exporter;

class PresenterAssembler
{
    /**
     * @param ServiceContainer $container
     */
    public function assemble(ServiceContainer $container)
    {
        $this->assembleDiffer($container);
        $this->assembleDifferEngines($container);
        $this->assembleTypePresenters($container);
        $this->assemblePresenter($container);
        $this->assembleHtmlPresenter($container);
    }

    /**
     * @param ServiceContainer $container
     */
    private function assembleDiffer(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter.differ', function (ServiceContainer $c) {
            $differ = new Differ();

            array_map(
                array($differ, 'addEngine'),
                $c->getByPrefix('formatter.presenter.differ.engines')
            );

            return $differ;
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function assembleDifferEngines(ServiceContainer $container)
    {
        $container->set('formatter.presenter.differ.engines.string', function () {
            return new StringEngine();
        });

        $container->set('formatter.presenter.differ.engines.array', function () {
            return new ArrayEngine();
        });

        $container->set('formatter.presenter.differ.engines.object', function (ServiceContainer $c) {
            return new ObjectEngine(
                new Exporter(),
                $c->get('formatter.presenter.differ.engines.string')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function assembleTypePresenters(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter.value.array_type_presenter', function () {
            return new ArrayTypePresenter();
        });

        $container->setShared('formatter.presenter.value.boolean_type_presenter', function () {
            return new BooleanTypePresenter();
        });

        $container->setShared('formatter.presenter.value.callable_type_presenter', function (ServiceContainer $c) {
            return new CallableTypePresenter($c->get('formatter.presenter'));
        });

        $container->setShared('formatter.presenter.value.exception_type_presenter', function () {
            return new BaseExceptionTypePresenter();
        });

        $container->setShared('formatter.presenter.value.null_type_presenter', function () {
            return new NullTypePresenter();
        });

        $container->setShared('formatter.presenter.value.object_type_presenter', function () {
            return new ObjectTypePresenter();
        });

        $container->setShared('formatter.presenter.value.string_type_presenter', function () {
            return new TruncatingStringTypePresenter(new QuotingStringTypePresenter());
        });

        $container->addConfigurator(function (ServiceContainer $c) {
            array_map(
                array($c->get('formatter.presenter.value_presenter'), 'addTypePresenter'),
                $c->getByPrefix('formatter.presenter.value')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function assemblePresenter(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter', function (ServiceContainer $c) {
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

        $container->setShared('formatter.presenter.value_presenter', function () {
            return new ComposedValuePresenter();
        });

        $container->setShared('formatter.presenter.exception_element_presenter', function (ServiceContainer $c) {
            return new TaggingExceptionElementPresenter(
                $c->get('formatter.presenter.value.exception_type_presenter'),
                $c->get('formatter.presenter.value_presenter')
            );
        });

        $container->setShared('formatter.presenter.exception.phpspec', function (ServiceContainer $c) {
            return new GenericPhpSpecExceptionPresenter(
                $c->get('formatter.presenter.exception_element_presenter')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function assembleHtmlPresenter(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter.html', function (ServiceContainer $c) {
            new SimplePresenter(
                $c->get('formatter.presenter.value_presenter'),
                new SimpleExceptionPresenter(
                    $c->get('formatter.presenter.differ'),
                    $c->get('formatter.presenter.html.exception_element_presenter'),
                    new CallArgumentsPresenter($c->get('formatter.presenter.differ')),
                    $c->get('formatter.presenter.html.exception.phpspec')
                )
            );
        });

        $container->setShared('formatter.presenter.html.exception_element_presenter', function (ServiceContainer $c) {
            return new SimpleExceptionElementPresenter(
                $c->get('formatter.presenter.value.exception_type_presenter'),
                $c->get('formatter.presenter.value_presenter')
            );
        });

        $container->setShared('formatter.presenter.html.exception.phpspec', function () {
            return new HtmlPhpSpecExceptionPresenter();
        });
    }
}
