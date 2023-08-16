Changelog
=========

2.1.1 (unreleased)
------------------

- Enforce access control on setup tool log files
  (`#101 <https://github.com/zopefoundation/Products.GenericSetup/issues/101>`_)


2.1.0 (2021-01-26)
------------------

- Add support for Python 3.9.


2.0.3 (2020-09-28)
------------------

- When logging an upgrade, print the version tuple joined by dots.

- Renamed ``xml`` dir to ``xml_templates``.
  This avoids an import warning on Python 2.7.


2.0.2 (2020-01-29)
------------------

- Remove Zope 2.13 fossils to stay compatible with Zope 5

- Force saving unpersisted changes in toolset registry.
  (`#86 <https://github.com/zopefoundation/Products.GenericSetup/issues/86>`_)


2.0.1 (2019-10-12)
------------------

- Fix the sorting of upgrade steps.  [vanderbauwhede]


2.0 (2019-05-10)
----------------

- no changes since 2.0b6


2.0b6 (2019-04-09)
------------------

- Zope 4 ZMI: Added icon

- Zope 4 ZMI: declare that creating a GS tool does not need an add dialog
  (`#80 <https://github.com/zopefoundation/Products.GenericSetup/issues/80>`_)

- clean up ``setup.py`` and remove support for ``setup.py test``
  (`#73 <https://github.com/zopefoundation/Products.GenericSetup/issues/73>`_)

- add support for unicode data in ``writeDataFile``
  (`#79 <https://github.com/zopefoundation/Products.GenericSetup/issues/79>`_)

- Specify supported Python versions using ``python_requires`` in setup.py

- Adding suport for Python 3.8


2.0b5 (2018-12-14)
------------------

- Fix deprecation warnings for ``cgi.escape`` by using ``html.escape``
  (`#76 <https://github.com/zopefoundation/Products.GenericSetup/issues/76>`_)


2.0b4 (2018-11-22)
------------------

- Convert input from xml configuration with correct encoding before passing to
  type_converter.
  (`#77 <https://github.com/zopefoundation/Products.GenericSetup/pull/77>`_)
  [sallner]


2.0b3 (2018-11-07)
------------------

- Do not turn ulines and multiple selection into bytes.
  [davisagli]

- Set body of PythonScripts as text in py3.
  [pbauer]

- Compare encodings so that UTF-8 and utf-8 are the same.
  [pbauer]

- Compare DOM as text in py3.
  [pbauer]


2.0b2 (2018-10-17)
------------------

New features:

- Add Python 3.7 support.

- Support `zope.configuration >= 4.2`.

Bug fixes:

- Proper string/bytes handling for _createObjectByType.
  In Python2 everything is written as bytes,
  while on Python3 everything is written as text except files and images
  which are stored as bytes
  [ale-rt]


2.0b1 (2018-05-16)
------------------

Breaking changes:

- Require Zope 4.0b4 as minimum supported Zope version and drop
  explicit ``Zope2`` egg dependency.

- Drop Python 3.4 support

New features:

- Fixed tests with ``Products.ZCatalog 4.1``.  [maurits]

- When ``metadata.xml`` parsing fails, show the filename in the ``ExpatError``.
  Fixes `Plone issue 2303 <https://github.com/plone/Products.CMFPlone/issues/2303>`_.

- Prevent AttributeError 'NoneType' object has no attribute 'decode'.
  [maurits]

- Finished compatibility with Python 3.5 and 3.6

- Made the code PEP-8 compliant

Bug fixes:

- Do not mask KeyError in 'getProfileDependencies' from missing
  dependency profiles.
  Refs: https://github.com/plone/Products.CMFPlone/issues/2228
  [ida]


1.10.0 (2017-12-07)
-------------------

Breaking changes:

- Require Zope 4.0a6 as minimum supported Zope version.

- Moved support for `MailHost` import/export into the
  ``Products.MailHost`` package to cut the hard dependency.

New features:

- Added ``tox`` testing configuration.

- Pushed documentation to RTD: https://productsgenericsetup.readthedocs.io/.

1.9.1 (2017-05-06)
------------------

Bug fixes:

- Fixed ``upgradeStep`` discriminator so that similar steps
  for different profiles will not conflict.

- Fixed ``upgradeDepends`` discriminator so that steps inside
  ``upgradeSteps`` will conflict with steps outside if they
  have the same ``checker``.

- Fix import of UnrestrictedUser.

1.9.0 (2017-05-04)
------------------

Breaking changes:

- Drop support for Python 2.6.

- Require Zope 4.0a3 as minimum supported Zope version.

1.8.7 (2017-03-26)
------------------

- Allow registering the same profile twice if it really is the same.
  This is mostly for tests where the registry may not be cleaned up
  correctly in case of problems in test teardown.
  If you register the same profile twice in zcml, you still get a
  conflict from ``zope.configuration`` during Zope startup.
  [maurits]


1.8.6 (2016-12-30)
------------------

- Added a ``purge_old`` option to the tarball import form.
  By default this option is checked, which matches the previous behavior.
  If you uncheck it, this avoids purging old settings for any import step
  that is run.  [maurits]


1.8.5 (2016-11-01)
------------------

- Stopped using a form library to render the components form.

1.8.4 (2016-09-21)
------------------

- Made ``_profile_upgrade_versions`` a PersistentMapping.  When
  ``(un)setLastVersionForProfile`` is called, we migrate the original
  Python dictionary.  This makes some code easier and plays nicer with
  transactions, which may especially help during tests.  [maurits]


1.8.3 (2016-04-28)
------------------

- Allowed overriding required and forbidden tools in ``toolset.xml``.
  If a tool is currently required and you import a ``toolset.xml``
  where it is forbidden, we remove the tool from the required list and
  add it to the forbidden list.  And the other way around.  The
  previous behavior was to raise an exception, which left no way in
  xml to remove a tool.  Fail with a ValueError when the ``remove``
  keyword is used.  The expected behavior is unclear.  [maurits]


1.8.2 (2016-02-24)
------------------

- Added optional ``pre_handler`` and ``post_handler`` to
  ``registerProfile`` directive.  When set, these dotted names are
  resolved to a function and are passed the setup tool as single
  argument.  They are called before and after applying all import
  steps of the profile they are registered for.  [maurits]

- Sorted import profiles alphabetically lowercase.  Allow selecting a
  profile by title or id.  [maurits]

- Do not show dependency options on the full import tab when there are
  no dependencies.  [maurits]

- Do not select a profile by default in the import tabs.  [maurits]

- Added simple toggle for all steps on the advanced import tab.
  Also added this on the export tab.
  [maurits]

- Fixed importing a tarball.  This got an AttributeError: "'NoneType'
  object has no attribute 'startswith'".
  [maurits]

- Split overly complex Import tab into three tabs: Import (for
  importing a full profile), Advanced Import (the original
  ``manage_importSteps`` url leads to this tab), and Tarball Import.
  [maurits]

- Show note on import tab when there are pending upgrades.  Especially
  show this for the currently selected profile.
  [maurits]

- Upgrades tab: show profiles with pending upgrades separately.  These
  are the most important ones.  This avoids the need to manually go
  through the whole list in order to find profiles that may need
  action.  This uses new methods on the setup tool:
  ``hasPendingUpgrades``, ``listProfilesWithPendingUpgrades``,
  ``listUptodateProfiles``.
  [maurits]


1.8.1 (2015-12-16)
------------------

- Purge the profile upgrade versions before applying a base profile.

- Added ``purgeProfileVersions`` method to ``portal_setup``.  This
  removes the all profiles profile upgrade versions.

- Added ``unsetLastVersionForProfile`` method to ``portal_setup``.  This
  removes the profile id from the profile upgrade versions.  Calling
  ``setLastVersionForProfile`` with ``unknown`` as version now has the
  same effect.


1.8.0 (2015-09-21)
------------------

- Be more forgiving when dealing with profile ids with or without
  ``profile-`` at the start.  All functions that accept a profile id
  argument and only work when the id does *not* have this string at
  the start, will now strip it off if it is there.  For example,
  ``getLastVersionForProfile`` will give the same answer whether you
  ask it for the version of profile id ``foo`` or ``profile-foo``.

- Dependency profiles from ``metadata.xml`` that are already applied,
  are not applied again.  Instead, its upgrade steps, if any, are
  applied.  In code you can choose the old behavior of always applying
  the dependencies, by calling ``runAllImportStepsFromProfile`` with
  ``dependency_strategy=DEPENDENCY_STRATEGY_REAPPLY``.  There are four
  strategies, which you can choose in the ZMI.


1.7.7 (2015-08-11)
------------------

- Fix: when the last applied upgrade step had a checker, the profile
  version was not updated.  Now we no longer look at the checker of
  the last applied step when deciding whether to set the profile
  version.  The checker, if any is set, normally returns True before
  running the step (it can be applied), and False afterwards (it
  was already applied).

- Add ``upgradeProfile`` method to setup tool.  This method applies all
  upgrades steps for the given profile, or updates it to the optional
  given version.  If the profile does not exist, or if there is no upgrade
  step to go to the specified version, the method warns and does nothing.

- Check the boolean value of the ``remove`` option when importing
  objects.  Previously we only checked if the ``remove`` option was
  given, regardless of its value.  Supported are ``True``, ``Yes``,
  and ``1``, where case does not matter.  The syntax for removing
  objects, properties, and elements is now the same.

- Support ``remove="True"`` for properties.


1.7.6 (2015-07-15)
------------------

- Enable testing under Travis.

- Fix compatibility with Setuptools 8.0 and later.  Upgrade steps
  could get sorted in the wrong order, especially an empty version
  string (upgrade step from any source version) sorted last instead of
  first.


1.7.5 (2014-10-23)
------------------

- Allow skipping certain steps on ``runAllImportStepsFromProfile``.


1.7.4 (2013-06-12)
------------------

- On import, avoid clearing indexes whose state is unchanged.


1.7.3 (2012-10-16)
------------------

- Sort profiles on Upgrade form.

- Use clickable labels with checkboxes on import, export and upgrade forms
  to improve usability.


1.7.2 (2012-07-23)
------------------

- Avoid using ``manage_FTPGet`` on snapshot exports: that method messes
  up the response headers.

- ZopePageTemplate handler:  Fix export encoding: since 1.7.0, exports
  must be UTF-8 strings


1.7.1 (2012-02-28)
------------------

- Restore the ability to make the setup tool use only import / export
  steps explicitly called out by the current profile, ignoring any which
  might be globally registered.  This is particularly useful for configuring
  sites with baseline profiles, where arbitrary add-on steps are not only
  useless, but potentially damaging.


1.7.0 (2012-01-27)
------------------

- While importing ``toolset.xml``, print a warning when the class of a
  required tool is not found and continue with the next tool.  The
  previous behaviour could break the install or uninstall of any
  add-on, as the missing class may easily be from a different
  unrelated add-on that is no longer available in the zope instance.

- Exporters now explicitly only understand strings. The provided
  registry handlers encode and decode data automatically to and from
  UTF-8. Their default encoding changed from None to UTF-8.
  If you have custom registry handlers, ensure that you encode your unicode.
  Check especially if you use a page template to generate xml. They return
  unicode and their output must also encoded.
  If you choose to encode your strings with UTF-8, you can be sure that
  your code will also work with GenericSetup < 1.7
