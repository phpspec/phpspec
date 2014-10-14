Templates
=========

**phpspec** can generate code snippets that will save you time when specifying classes.
The default templates will be suitable for many use cases.

However in some cases, it'll be useful to customize those templates by providing
ones that suit your project requirements. For example, you may need to add licence
information in a docblock to every class file. Instead of doing this manually you
can modify the template so it is already in the generated file.

Overriding templates
--------------------

**phpspec** uses three templates:
  - *specification* - used when a spec is generated using the `describe` command
  - *class* - used to generate a class that is specified but which does not exist
  - *method* - used to add a method that is specified to a class

You can override these on a per project basis by creating a template file in
`.phpspec` in the root directory of the project. For example, to add licence
information to the docblock for a class, you can create a file ``{project_directory}/.phpspec/class.tpl``.
You can copy the contents of the default template found in **phpspec** at
``src/PhpSpec/CodeGenerator/Generator/templates/class.template`` and add the docblock to it:

.. code-block:: php

    <?php

    /*
     * This file is part of Acme.
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */%namespace_block%

    class %name%
    {
    }

So now, for example, you want to describe a class ``Acme\Model\Foo`` which does not exist. You can run
the spec ``spec/Acme/Model/FooSpec.php`` and let **phpspec** generate the missing class.
**phpspec** will use your overridden template and the generated file will look like:

.. code-block:: php

    <?php

    /*
     * This file is part of Acme.
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    namespace Acme\Model;

    class Foo
    {
    }

You can also override the templates for all your projects by creating a
template in `.phpspec` in your home directory.

**phpspec** uses the first template it finds by looking in this order:

   1. ``{project_directory}/.phpspec/{template_name}.tpl``
   2. ``{home_directory}/.phpspec/{template_name}.tpl``
   3. The default template

Parameters
----------

As well as static text there are some parameters available like the ``%namespace_block%``
in the example above. The parameters available depend on which type of
template you are overriding:

**specification**
   - ``%filepath%`` the file path of the class
   - ``%name%``  the specification name
   - ``%namespace%`` the specification namespace
   - ``%subject%`` the name of the class being specified

**class**
   - ``%filepath%`` the file path of the class
   - ``%name%`` the class name
   - ``%namespace%`` the class namespace
   - ``%namespace_block%`` the formatted class namespace

**method**
   - ``%name%`` the method name
   - ``%arguments%`` the method arguments
