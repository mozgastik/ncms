Twig Components
===============

EasyAdmin uses `Twig Components`_ to render many parts of its interface, such
as buttons, badges, icons, modal windows and dropdown menus. These components
are registered under the ``ea:`` prefix and you can also use them in your own
templates (e.g. when overriding backend templates or creating custom admin pages).

This appendix is a practical guide to those components. It doesn't describe
every option of every component; instead, it shows how to accomplish the most
common tasks with each of them.

All components share the same behavior for passing options ("props"): they are
passed as regular HTML attributes, and boolean props are enabled by adding the
attribute name without any value:

.. code-block:: twig

    <twig:ea:Alert variant="warning" title="{{ alert_title }}" withDismissButton>
        Some important message.
    </twig:ea:Alert>

In addition, any other attribute added to a component (``class``, ``id``,
``data-*``, etc.) is merged into the attributes of its root HTML element:

.. code-block:: twig

    <twig:ea:Badge variant="success" class="product-status" data-status="published">
        Published
    </twig:ea:Badge>

ActionMenu
----------

Renders a dropdown menu of related actions, like the one that displays the
entity actions on the index page. Build the menu by combining its
sub-components: a ``Button`` that toggles the dropdown, an ``Overlay`` that
wraps the menu contents, and an ``ActionList`` with the menu items:

.. code-block:: twig

    <twig:ea:ActionMenu>
        <twig:ea:ActionMenu:Button>
            <twig:ea:Icon name="internal:dots-horizontal"/>
        </twig:ea:ActionMenu:Button>

        <twig:ea:ActionMenu:Overlay>
            <twig:ea:ActionMenu:ActionList>
                <twig:ea:ActionMenu:ActionList:Item label="Edit" icon="internal:edit" url="/products/24/edit"/>
                <twig:ea:ActionMenu:ActionList:Item label="Duplicate" url="/products/24/duplicate"/>
                <twig:ea:ActionMenu:ActionList:Divider/>
                <twig:ea:ActionMenu:ActionList:Item label="Delete" icon="internal:delete" url="/products/24/delete" renderAsForm/>
            </twig:ea:ActionMenu:ActionList>
        </twig:ea:ActionMenu:Overlay>
    </twig:ea:ActionMenu>

Items render a link and a ``GET`` request by default. For actions that modify
data (like the "Delete" item above), add the ``renderAsForm`` prop to submit
the item URL with a ``POST`` request instead.

Use the ``Header`` sub-component to add titles to groups of items, and the
``Content`` sub-component to insert any custom markup in the menu:

.. code-block:: twig

    <twig:ea:ActionMenu:ActionList>
        <twig:ea:ActionMenu:ActionList:Header label="Download" icon="internal:download"/>
        <twig:ea:ActionMenu:ActionList:Item label="As HTML" url="/reports/download?type=html"/>
        <twig:ea:ActionMenu:ActionList:Item label="As PDF" url="/reports/download?type=pdf"/>
        <twig:ea:ActionMenu:ActionList:Divider/>
        <twig:ea:ActionMenu:ActionList:Content>
            <small class="px-3">Reports are rebuilt every early morning.</small>
        </twig:ea:ActionMenu:ActionList:Content>
    </twig:ea:ActionMenu:ActionList>

Selectable Items
~~~~~~~~~~~~~~~~

Menus can also include selectable options, rendered with a trailing checkmark.
Wrap the items with ``RadioList`` (options are mutually exclusive) or
``CheckboxList`` (multiple options can be selected at the same time) and pass
the ``selected`` prop to each item:

.. code-block:: twig

    <twig:ea:ActionMenu:ActionList>
        <twig:ea:ActionMenu:ActionList:RadioList label="Theme">
            <twig:ea:ActionMenu:ActionList:Item label="Light" url="?theme=light" selected="{{ theme == 'light' }}"/>
            <twig:ea:ActionMenu:ActionList:Item label="Dark" url="?theme=dark" selected="{{ theme == 'dark' }}"/>
        </twig:ea:ActionMenu:ActionList:RadioList>
    </twig:ea:ActionMenu:ActionList>

.. note::

    These components only render the selection state; updating it when the
    user clicks on an item is up to your own server-side or JavaScript code.

Alert
-----

Highlights important messages that require the user's attention. EasyAdmin
uses it, for example, to render flash messages:

.. code-block:: twig

    <twig:ea:Alert>Your changes have been saved.</twig:ea:Alert>

    <twig:ea:Alert variant="danger">Your password is about to expire.</twig:ea:Alert>

The ``variant`` prop accepts the usual Bootstrap values (``primary``,
``success``, ``warning``, ``danger``, etc.; default: ``info``). Add an optional
``icon``, a ``title`` and a dismiss button as follows:

.. code-block:: twig

    <twig:ea:Alert variant="warning" icon="fa-triangle-exclamation" title="Disk space low" withDismissButton>
        The server is running out of disk space.
    </twig:ea:Alert>

If the alert requires the user to do something, add buttons or links in the
``actions`` block:

.. code-block:: twig

    <twig:ea:Alert variant="info" title="New version available">
        A new EasyAdmin version has been released.

        <twig:block name="actions">
            <twig:ea:Button htmlElement="a" href="/changelog" size="sm">Read the changelog</twig:ea:Button>
        </twig:block>
    </twig:ea:Alert>

Badge
-----

Renders a small colored label, commonly used for statuses, counts and tags:

.. code-block:: twig

    <twig:ea:Badge>Draft</twig:ea:Badge>

    <twig:ea:Badge variant="success">Published</twig:ea:Badge>

The ``variant`` prop accepts the usual Bootstrap values (``primary``,
``success``, ``danger``, etc.; default: ``secondary``) plus an ``outline``
value that renders a badge with a border and no background. Add optional
leading and/or trailing icons with the ``icon`` and ``endIcon`` props, and
create pill-shaped badges with ``radius="full"``:

.. code-block:: twig

    <twig:ea:Badge variant="danger" icon="fa-circle-exclamation">Out of stock</twig:ea:Badge>

    <twig:ea:Badge variant="outline" endIcon="fa-arrow-right">See all</twig:ea:Badge>

    <twig:ea:Badge variant="primary" radius="full" size="sm">12</twig:ea:Badge>

Button
------

Renders a button with an optional icon, in the sizes and variants that you'd
expect from Bootstrap-like buttons:

.. code-block:: twig

    <twig:ea:Button>Save draft</twig:ea:Button>

    <twig:ea:Button variant="primary" size="lg" isBlock>Sign in</twig:ea:Button>

    <twig:ea:Button variant="danger" icon="internal:delete">Delete</twig:ea:Button>

Buttons render a ``<button type="submit">`` element by default. Use the
``htmlElement`` prop to render a link that looks like a button, or a
self-contained form that submits to some URL when clicking on the button:

.. code-block:: twig

    {# renders an <a> element #}
    <twig:ea:Button htmlElement="a" href="/products/new" icon="internal:plus">Add product</twig:ea:Button>

    {# renders a <form> that submits a DELETE request to the given URL #}
    <twig:ea:Button htmlElement="form" action="/products/24" method="DELETE" variant="danger">
        Delete product
    </twig:ea:Button>

.. note::

    HTML forms only support the ``GET`` and ``POST`` methods. When passing any
    other ``method`` (``PUT``, ``DELETE``, etc.) the form is submitted as
    ``POST`` with the real method in a ``_method`` hidden field, which is the
    convention supported by Symfony's `http_method_override`_ option.

Other useful props: ``withTrailingIcon`` displays the icon after the label
instead of before; ``isInvisible`` removes the button background and borders
(useful for icon-only buttons); ``inactive`` renders the button in a disabled
state:

.. code-block:: twig

    <twig:ea:Button icon="fa-arrow-right" withTrailingIcon>Next step</twig:ea:Button>

    <twig:ea:Button type="button" isInvisible icon="internal:gear" aria-label="Settings"/>

Flag
----

Renders the flag of a country as an inline SVG image. Pass the two-letter
`ISO 3166-1 alpha-2`_ code of the country and, optionally, display the country
name next to the flag:

.. code-block:: twig

    <twig:ea:Flag countryCode="JP"/>

    <twig:ea:Flag countryCode="JP" showName/>

    {# use countryName to display a custom name instead of the default
       country name provided by the Intl component #}
    <twig:ea:Flag countryCode="JP" showName countryName="Nippon"/>

Flags are ``17px`` tall by default; change that with the ``height`` prop
(width is adjusted automatically):

.. code-block:: twig

    <twig:ea:Flag countryCode="JP" height="{{ 24 }}"/>

Icon
----

Renders the icon associated to the given name, using the icon set configured
in the backend (`FontAwesome icons`_ by default, or
:ref:`your own icon set <icon-customization>`):

.. code-block:: twig

    <twig:ea:Icon name="user"/>

    <twig:ea:Icon name="fa-solid fa-file-invoice"/>

Icons prefixed with ``internal:`` are SVG icons bundled with EasyAdmin and
render the same regardless of the configured icon set. They are useful when
overriding backend templates, to match the look of the default interface:

.. code-block:: twig

    <twig:ea:Icon name="internal:search"/>

Modal
-----

Renders a modal window. Give it an ``id`` and open it from anywhere with a
``Modal:Trigger`` button that points to that ``id``. Inside the modal, use the
``Modal:Close`` button to dismiss it:

.. code-block:: twig

    <twig:ea:Modal:Trigger target="#delete-modal" variant="danger">Delete</twig:ea:Modal:Trigger>

    <twig:ea:Modal id="delete-modal" title="Delete this item?" description="This action cannot be undone.">
        <twig:block name="footer">
            <twig:ea:Modal:Close>Cancel</twig:ea:Modal:Close>
            <twig:ea:Button variant="danger" data-bs-dismiss="modal">Delete</twig:ea:Button>
        </twig:block>
    </twig:ea:Modal>

``Modal:Trigger`` and ``Modal:Close`` wrap the ``ea:Button`` component, so
they accept all its props (``variant``, ``icon``, ``size``, etc.).

Instead of (or in addition to) the ``title`` and ``description`` props, you
can pass any custom markup in the ``body`` block. Use the ``size`` prop
(``sm``, ``lg`` or ``xl``) to change the default ``380px`` window width:

.. code-block:: twig

    <twig:ea:Modal id="details-modal" size="lg">
        <twig:block name="body">
            <p>Any markup you need goes here.</p>
        </twig:block>
        <twig:block name="footer">
            <twig:ea:Modal:Close>Close</twig:ea:Modal:Close>
        </twig:block>
    </twig:ea:Modal>

For confirmation-style dialogs, display an icon at the top left of the window
with the ``icon`` prop, or stacked on top of the contents with ``topIcon``:

.. code-block:: twig

    <twig:ea:Modal id="warn-modal" topIcon="internal:circle-exclamation" title="Are you sure?">
        <twig:block name="footer">
            <twig:ea:Modal:Close>No</twig:ea:Modal:Close>
            <twig:ea:Button variant="primary" data-bs-dismiss="modal">Yes</twig:ea:Button>
        </twig:block>
    </twig:ea:Modal>

Pagination
----------

Renders a pagination control with links to browse the pages of some results,
and an optional counter of the total number of results. Pass the current page,
the URL pattern used to generate the page links (it must contain the
``{page}`` placeholder) and either the last page number or the total number of
items and the page size:

.. code-block:: twig

    <twig:ea:Pagination currentPage="{{ page }}" lastPage="{{ 20 }}" urlPattern="/products?page={page}"/>

    {# the last page is calculated automatically: ceil(1234 / 25) = 50 pages #}
    <twig:ea:Pagination currentPage="{{ page }}" totalItems="{{ 1234 }}" pageSize="{{ 25 }}" urlPattern="/products?page={page}"/>

Several props toggle the elements displayed by the component:

.. code-block:: twig

    <twig:ea:Pagination currentPage="{{ page }}" lastPage="{{ 20 }}" urlPattern="/products?page={page}"
        showFirstLast
        showResultsCount="{{ false }}"
        showPageNumbers="{{ false }}"
        showPreviousNextLabels="{{ false }}"
    />

.. note::

    In templates rendered by EasyAdmin (e.g. when overriding the index page)
    you can pass the ``paginator`` object instead of all the above props:
    ``<twig:ea:Pagination paginator="{{ paginator }}"/>``. Both ways of configuring
    the component are mutually exclusive.

Switch
------

Renders an accessible toggle switch, used to represent boolean values (it's an
``<input type="checkbox">`` field under the hood, so you can use it in your
own forms):

.. code-block:: twig

    <twig:ea:Switch/>

    <twig:ea:Switch checked/>

    {# when submitting the form, this sends settings[newsletter]=yes #}
    <twig:ea:Switch name="settings[newsletter]" value="yes" checked/>

By default, the checked state uses the primary color of the backend. Use the
``variant`` prop (``success``, ``warning`` or ``danger``) to change it, and
``size="sm"`` to render a smaller switch:

.. code-block:: twig

    <twig:ea:Switch variant="success" checked/>

    <twig:ea:Switch size="sm" disabled/>

.. tip::

    When the switch doesn't have a visible ``<label>`` element associated to
    it, pass the ``ariaLabel`` prop to keep it accessible to screen readers.

.. _`Twig Components`: https://symfony.com/bundles/ux-twig-component/current/index.html
.. _`FontAwesome icons`: https://fontawesome.com/v6/search?m=free
.. _`ISO 3166-1 alpha-2`: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
.. _`http_method_override`: https://symfony.com/doc/current/reference/configuration/framework.html#http-method-override
