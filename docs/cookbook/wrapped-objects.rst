Working with Wrapped Objects
============================

**phpspec** wraps some of the objects used in specs. For example ``$this``
is the object you are describing wrapped in a phpspec object. This is how
you can call methods on ``$this`` and then call matchers on the returned values.

Most of the time this is not something you need to worry about but sometimes
it can be an issue.

If you ever need to get the actual object then you can by calling ``$this->getWrappedObject()``.

If you try to specify a method on your object that starts with “should”,
for example:

.. code-block:: php

    function it_should_handle_something($somethingToHandle)
    {
        $this->shouldHandle($somethingToHandle);
    }

Then this will not work as expected because **phpspec** will intercept the
call thinking it is a matcher. You can avoid this by using ``callOnWrappedObject``:

.. code-block:: php

    function it_should_handle_something($somethingToHandle)
    {
        $this->callOnWrappedObject('shouldHandle', array($somethingToHandle));
    }
