Writing extensions for PhpSpec
==============================

Here is a guide to writing extensions for **phpspec** 3.  Your extension will
integrate with core **phpspec** using the `Container-Interop <https://github.com/container-interop/container-interop>`_
standard.  Your extension will be loaded via your own implementation of
`\PhpSpec\Extension`.

Here are a few things you might like to do with your extension, and examples
of how you can extend **phpspec**.

Providing an alternative implementation of an existing service
--------------------------------------------------------------

Let us say that you have an alternative implementation of
`\PhpSpec\Process\Shutdown\Shutdown`, which goes by the service ID
`process.shutdown` in **phpspec**'s DI config.  You can write your extension
loader like this:

.. code-block:: php

    namespace MyExtension;
    
    use PhpSpec\Extension as PhpSpecExtension;
    use Interop\Container\ContainerInterface;
    use UltraLite\Container\Container;
    
    class Extension implements PhpSpecExtension
    {
        public function load(ContainerInterface $compositeContainer)
        {
            $container = new Container();
            $container->set('process.shutdown', function (ContainerInterface $container) {
                return new MyBetterShutdownImplementation();
            });
            $container->setDelegateContainer($compositeContainer);
            return $container;
        }
    }

This example uses `UltraLite <https://github.com/ultra-lite/container>`_ as its
choice of container, but many other standards-compliant containers are available,
so choose your favourite!

Adding a matcher
----------------

If you want to add a new matcher to **phpspec**, here is an example of how you
might do that, using `Picotainer <https://github.com/thecodingmachine/picotainer>`_
this time:

.. code-block:: php

    namespace MyExtension;

    use PhpSpec\Extension as PhpSpecExtension;
    use Interop\Container\ContainerInterface;
    use Mouf\Picotainer\Picotainer;

    class Extension implements PhpSpecExtension
    {
        public function load(ContainerInterface $compositeContainer)
        {
            $originalMatcherServiceList = $compositeContainer->get('phpspec.servicelist.matchers');
            return new Picotainer(
                [
                    'myextension.my-new-matcher' => function (ContainerInterface $container) {
                        return new MyNewMatcher($container->get('formatter.presenter'));
                    },
                    'phpspec.servicelist.matchers' => function (ContainerInterface $container) use ($originalMatcherServiceList) {
                        return array_merge($originalMatcherServiceList, ['myextension.my-new-matcher']);
                    }
                ],
                $compositeContainer
            );
        }
    }

**phpspec** will use the ``phpspec.servicelist.matchers`` service to decide
which matchers should be used, so we have merely added to that list.

Adding an event listener
------------------------

If you want to hook into **phpspec**'s event listener, here is a way of doing that:

.. code-block:: php

    namespace MyExtension;

    use PhpSpec\Extension as PhpSpecExtension;
    use Interop\Container\ContainerInterface;

    class Extension implements PhpSpecExtension
    {
        public function load(ContainerInterface $compositeContainer)
        {
            $eventDispatcher = $compositeContainer->get('event_dispatcher');
            $eventDispatcher->addSubscriber(new MyEventSubscriber());
        }
    }

Note we didn't need to bother returning a container that time, as there weren't
any new services to define.
