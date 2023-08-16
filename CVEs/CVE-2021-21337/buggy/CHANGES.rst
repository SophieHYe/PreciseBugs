Change Log
==========

2.6.0 (unreleased)
------------------

- Add support for Python 3.9.


2.5.1 (2020-11-13)
------------------

- Fixed error assigning groups in ``manage_groups`` page in ZMI.
  (`#61 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/61>`_,
  `#84 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/84>`_)

- Fix DeprecationWarnings occurring on Zope 5.


2.5 (2020-10-12)
----------------

- Renamed ``xml`` dir to ``xml_templates``.
  This avoids an import warning on Python 2.7.

- Disable ZMI CSRF check and log it if sessioning is not available
  instead of breaking ZMI interactions

- Clear caches before sending group user added/removed events
  (`#71 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/71>`_)

- Prevent creation of users/groups/roles with empty ID in the ZODB
  (`#70 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/70>`_)

- update configuration for version 5 of ``isort``


2.4 (2020-02-09)
----------------

- no longer rely on ``ZServer`` for any WebDAV-related functionality.
  (`#64 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/64>`_)


2.3 (2020-02-02)
----------------

- Replace all ``filter(None...)`` expressions which break under Python 3
  (`#63 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/63>`_)


2.2.1 (2020-01-13)
------------------

- Fix broken ICredentialsUpdatedEvent event handler call to updateCredentials.
  (`#59 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/59>`_)


2.2 (2019-11-23)
----------------

- Add new events to be able to notify when a principal is added to
  or removed from a group. Notify these events when principals are
  added or removed to a group in ZODBGroupManager
  (`#17 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/17>`_)


2.1.1 (2019-10-23)
------------------

- Fix bug in ``getRolesForPrincipal`` for non PAS user.


2.1 (2019-08-29)
----------------

- Fix formatting in "Plugin Types" documentation.

- Fixed error assigning roles in ``manage_roles`` page in ZMI.
  See issues `#43 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/43>`_
  and `#51 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/51>`_.


2.0 (2019-05-10)
----------------

- Drop unused ``.utils.allTests`` method.


2.0b6 (2019-04-17)
------------------

- fixed usage of deprecated ``im_self``
  (`#40 <https://github.com/zopefoundation/Products.PluggableAuthService/pull/40>`_)


2.0b5 (2019-04-13)
------------------

- fixed the "Configured PAS" factory
  (`#39 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/39>`_)

- styled "Configured PAS" add dialog for the Zope 4 ZMI
  (`#38 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/38>`_)

- prevent the ZMI add dialog showing in the Zope 4 ZMI
  (`#37 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/37>`_)

- added the indirect dependency ``Products.Sessions`` for the CSRF-support


2.0b4 (2019-04-04)
------------------

- simplified Travis CI test configuration

- added stricter linting configuration

- added ``project_urls`` to the setup so PyPI shows more relevant links

- added project badges to the README, which will show on the GitHub front page

- Fix ZMI Templates and add ZMI icons for Zope 4
  (`#36 <https://github.com/zopefoundation/Products.PluggableAuthService/pull/36>`_)


2.0b3 (2019-03-29)
------------------

- Fixed Dynamic Groups Plugin ZMI view
  (`#33 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/33>`_)

- Re-enabled XML-RPC support without requiring ZServer
  (`#34 <https://github.com/zopefoundation/Products.PluggableAuthService/issues/34>`_)

- Specify supported Python versions using ``python_requires`` in setup.py

- Added support for Python 3.8

- Fix CSRF defense incompatibility with some session implementations


2.0b2 (2018-10-16)
------------------

- Add support for Python 3.7.

- Do not override a previously set response body in
  ``HTTPBasicAuthHelper.challenge()`` allowing to set the response body via
  an exception view in Zope >= 4.0b6.

- Add new event to be able to notify group creation.

- Refactoring to make it easier to override ``updateCredentials``.


2.0b1 (2018-05-18)
------------------

- The dependency on ``ZServer`` is now optional. To use the features which
  require ``ZServer`` (WebDav, XML-RPC, FTP) use the setuptools extra `zserver`
  when installing the package.

- Do not fail when our base profiles are already registered.
  This may happen in tests if our ``initialize`` code is called twice.

- Add support for Python 3.

- Reformatted code for PEP-8 compliance.

- Require Zope 4.0b5 as minimum Zope version.


1.11.0 (2016-03-01)
-------------------

- Add new event to be able to notify group deletion.

- Fix usage of os.path.split(). Could result in Errors during import
  on Windows.


1.10.0 (2013-02-19)
-------------------

- Allow specifying a policy for transforming / normalizing login names
  for all plugins in a PAS:

  - Added ``login_transform`` string property to PAS.

  - Added ``applyTransform`` method to PAS, which looks for a method on PAS
    with the name specified in the ``login_transform`` property.

  - Added two possible transforms to PAS: ``lower`` and ``upper``.

  - Changed the methods of PAS to call ``applyTransform`` wherever needed.

  - Added the existing ``updateUser`` method of ZODBUserManager to the
    IUserEnumerationPlugin interface.

  - Added a new ``updateEveryLoginName`` method to ZODBUserManager and the
    IUserEnumerationPlugin interface.

  - Added three methods to PAS and IPluggableAuthService:
    ``updateLoginName``, ``updateOwnLoginName``, ``updateAllLoginNames``.
    These methods call ``updateUser`` or ``updateEveryLoginName`` on every
    IUserEnumerationPlugin. Since these are later additions to the plugin
    interface, we log a warning when a plugin does not have these methods
    (for example the ``mutable_properties`` plugin of PlonePAS) but will
    not fail.  When no plugin is able to update a user, this will raise an
    exception: we do not want to quietly let this pass when for example a
    login name is already taken by another user.

  - Changing the ``login_transform`` property in the ZMI will call
    ``PAS.updateAllLoginNames``, unless ``login_transform`` is the same or
    has become an empty string.

  - The new ``login_transform`` property is empty by default. In that case,
    the behavior of PAS is the same as previously. The various
    ``applyTransform`` calls will have a (presumably very small)
    performance impact.

- Launchpad #1079204:  Added CSRF protection for the ZODBUserManager,
  ZODBGroupManager, ZODBRoleManger, and DynamicGroupsPlugin plugins.


1.9.0 (2012-08-30)
------------------

- Launchpad #649596:  add a protocol for plugins which check whether a
  non-top-level PAS instance is "competent" to authenticate a given request;
  if not, the instance defers to higher-level instances.  Thanks to Dieter
  Maurer for the patch.


1.8.0 (2012-05-08)
------------------

- Added export / import support for the ChallengeProtocolChooser plugin's
  label - protocols mapping.


1.7.8 (2012-05-08)
------------------

- In authenticateCredentials do NOT fall back to using the login as
  userid when there is no match, as that gives a high chance of
  seeming to log in successfully, but in reality failing.
  [maurits]


1.7.7 (2012-02-27)
------------------

- Explicitly encode/decode data for GS


1.7.6 (2011-10-31)
------------------

- Launchpad #795086:  fixed creation of PropertiesUpdated event.


1.7.5 (2011-05-30)
------------------

- Launchpad #789858:  don't allow conflicting login name in 'updateUser'.

- Set appropriate cache headers on CookieAuthHelper login redirects to prevent
  caching by proxy servers.


1.7.4 (2011-05-13)
------------------

- Added forward compatibility with DateTime 3.


1.7.3 (2011-02-10)
------------------

- In the ZODBRoleManager made it clearer that adding a removing a role
  does not have much effect if you do not do the same in the root of
  the site (at the bottom of the Security tab at manage_access).
  Fixes https://bugs.launchpad.net/zope-pas/+bug/672694

- Return the created user in _doAddUser, to match change in
  AccessControl 2.13.4.

- Fixed possible ``binascii.Error`` in ``extractCredentials`` of
  CookieAuthHelper. This is a corner case that might happen after
  a browser upgrade.


1.7.2 (2010-11-11)
------------------

- Allow for a query string in CookieAuthHelper's ``login_path``.

- Trap "swallowable" exceptions from ``IRoles`` plugins.  Thanks to
  Willi Langenburger for the patch.  Fixes
  https://bugs.launchpad.net/zope-pas/+bug/615474 .

- Fixed possible TypeError in ``extractCredentials`` of CookieAuthHelper
  when the ``__ac`` cookie is not ours (but e.g. from plone.session,
  though even then only in a corner case).

- Fixed chameleon incompatibilities


1.7.1 (2010-07-01)
------------------

- Made ``ZODBRoleManager.assignRoleToPrincipal`` raise and log a more
  informative error when detecting a duplicate principal.
  https://bugs.launchpad.net/zope-pas/+bug/348795

- Updated ``DynamicGroupsPlugin.enumerateGroups`` to return an empty sequence
  for an unknown group ID, rather than raising KeyError.
  https://bugs.launchpad.net/zope-pas/+bug/585365

- Updated all code to raise new-style exceptions.

- Removed dependency on ``zope.app.testing``.

- Cleaned out a number of old imports, because we now require Zope >= 2.12.

- Updated ``setDefaultRoles`` to use the ``addPermission`` API if available.


1.7.0 (2010-04-08)
------------------

- Allow CookieAuthHelper's ``login_path`` to be set to an absolute url for
  integration with external authentication mechanisms.

- Fixed xml templates directory path computation to allow reuse of
  ``SimpleXMLExportImport`` class outside ``Products.PluggableAuthService``.


1.7.0b2 (2010-01-31)
--------------------

- Modify ZODBGroupManager to update group title and description independently.


1.7.0b1 (2009-11-16)
--------------------

- This release requires for Zope2 >= 2.12.

- Simplified buildout to just what is needed to run tests.

- Don't fail on users defined in multiple user sources on the
  ZODBGroupManager listing page.

- Fixed deprecation warnings for use of ``Globals`` under Zope 2.12.

- Fixed deprecation warnings for the ``md5`` and ``sha`` modules under
  Python >= 2.6.

- Added test for multiple auth header support in the HTTPBasicAuthHelper.

- Changed HTTPBasicAuthHelper to not rely on one obscure feature of the
  HTTPResponse.


1.6.2 (2009-11-16)
------------------

- Launchpad #420319:  Fix misconfigured ``startswith`` match type filter
  in ``Products.PluggableAuthService.plugins.DomainAuthHelper``.

- Fixed test setup for tests using page templates relying on the
  ``DefaultTraversable`` adapter.

- Fixed broken markup in templates.


1.6.1 (2008-11-20)
------------------

- Launchpad #273680:  Avoid expensive / incorrect dive into ``enumerateUsers``
  when trying to validate w/o either a real ID or login.

- Launchpad #300321:
  ``Products.PluggableAuthService.pluginsZODBGroupManager.enumerateGroups``
  failed to find groups with unicode IDs.


1.6 (2008-08-05)
----------------

- Fixed another deprecation for ``manage_afterAdd`` occurring when used
  together with Five (this time for the ``ZODBRoleManager`` class).

- Ensure the ``_findUser`` cache is invalidated if the roles or groups for
  a principal change.

- Launchpad #15569586:  docstring fix.

- Factored out ``filter`` logic into separate classes;  added filters
  for ``startswith`` test and (if the IPy module is present) IP-range
  tests.  See https://bugs.launchpad.net/zope-pas/+bug/173580 .

- Zope 2.12 compatibility - removed ``Interface.Implements`` import if
  ``zope.interface`` available.

- Ensure ``ZODBRoleManagerExportImport`` doesn't fail if it tries to add a
  role that already exists (idempotence is desirable in GS importers)

- Fixed tests so they run with Zope 2.11.

- Split up large permission tests into individual tests.

- Fixed deprecation warning occurring when used together with
  Five. (``manage_afterAdd`` got undeprecated.)

- Added buildout.


1.5.3 (2008-02-06)
------------------

- ZODBUserManager plugin: allow unicode arguments to
  ``enumerateUsers``. (https://bugs.launchpad.net/zope-pas/+bug/189627)

- plugins/ZODBRoleManager: added logging in case searchPrincipial()
  returning more than one result (which might happen in case of having
  duplicate id within difference user sources)


1.5.2 (2007-11-28)
------------------

- DomainAuthHelper plugin:  fix glitch for plugins which have never
  configured any "default" policy:  ``authenticateCredentials`` and
  ``getRolesForPrincipal`` would raise ValueError.
  (http://www.zope.org/Collectors/PAS/59)


1.5.1 (2007-09-11)
------------------

- PluggableAuthService._verifyUser: changed to use exact_match to the
  enumerator, otherwise a user with login ``foobar`` might get returned
  by _verifyUser for a query for ``login='foo'`` because the enumerator
  happened to return 'foobar' first in the results.

- Add a test for manage_zmi_logout and replace a call to isImplementedBy
  with providedBy.
  (http://www.zope.org/Collectors/PAS/58)


1.5 (2006-06-17)
----------------

- Add support for property plugins returning an IPropertySheet
  to PropertiedUser. Added addPropertysheet to the IPropertiedUser.

- Added a method to the IRoleAssignerPlugin to remove roles from a
  principal, and an implementation for it on the ZODBRoleManager.
  (http://www.zope.org/Collectors/PAS/57)

- Added events infrastructure. Enabled new IPrincipalCreatedEvent and
  ICredentialsUpdatedEvent events.

- Added support for registering plugin types via ZCML.

- Implemented authentication caching in _extractUserIds.

- Ported standard user folder tests from the AccessControl test suite.

- Passwords with ":" characters would break authentication
  (http://www.zope.org/Collectors/PAS/51)

- Corrected documented software dependencies

- Converted to publishable security sensitive methods to only accept
  POST requests to prevent XSS attacks.  See
  http://www.zope.org/Products/Zope/Hotfix-2007-03-20/announcement and
  http://dev.plone.org/plone/ticket/6310

- Fixed issue in the user search filter where unrecognized keyword
  arguments were ignored resulting in duplicate search entries.
  (http://dev.plone.org/plone/ticket/6300)

- Made sure the Extensions.upgrade script does not commit full
  transactions but only sets (optimistic) savepoints. Removed bogus
  Zope 2.7 compatibility in the process.
  (http://www.zope.org/Collectors/PAS/55)

- Made the CookieAuthHelper only use the ``__ac_name`` field if
  ``__ac_password`` is also present. This fixes a login problem for
  CMF sites where the login name was remembered between sessions with
  an ``__ac_name`` cookie.

- Made the DomainAuthHelper return the remote address, even it the
  remote host is not available (http://www.zope.org/Collectors/PAS/49).

- Fixed bug in DelegatingMultiPlugin which attempted to validate the
  supplied password directly against the user password - updated to use
  AuthEncoding.pw_validate to handle encoding issues

- Fixed serious security hole in DelegatingMultiPlugin which allowed
  Authentication if the EmergencyUser login was passed in.  Added
  password validation utilizing AuthEncoding.pw_validate

- Fixed a set of tests that tested values computed from dictionaries
  and could break since dictionaries are not guaranteed to have any
  sort order.

- Fixed test breakage induced by use of Z3 pagetemplates in Zope
  2.10+.

- BasePlugin: The listInterfaces method only considered the old-style
  __implements__ machinery when determining interfaces provided by
  a plugin instance.

- ZODBUserManager: Already encrypted passwords were encrypted again in
  addUser and updateUserPassword.
  (http://www.zope.org/Collectors/Zope/1926)

- Made sure the emergency user via HTTP basic auth always wins, no matter
  how borken the plugin landscape.

- Cleaned up code in CookieAuthHelper which allowed the form to override
  login/password if a cookie had already been set.

- Removed some BBB code for Zope versions < 2.8, which is not needed
  since we require Zope > 2.8.5 nowadays.


1.4 (2006-08-28)
----------------

- Extended the DomainAuthHelper to function as its own extraction
  plugin, to allow for the case that another extractor is registered,
  but does not return any credentials.
  (http://www.zope.org/Collectors/PAS/46)

- Re-worded parts of the README so they don't point to specific or
  non-existing files (http://www.zope.org/Collectors/PAS/6 and
  http://www.zope.org/Collectors/PAS/47)


1.4-beta (2006-08-07)
---------------------

- Created a "Configured PAS" entry in the ZMI add list, which
  allows creating a PAS using base and extension GenericSetup profiles
  registered for IPluggableAuthService.  This entry should eventually
  replace the "stock" PAS entry (assuming that we make GenericSetup
  a "hard" dependency).

- Added an "empty" GenericSetup profile, which creates a PAS containing
  only a plugin registry and a setup tool.

- Repaired the "simple" GenericSetup profile to be useful, rather than
  catastrophic, to apply:  it now creates and registers a set of
  ZODB-based user / group / role plugins, along with a basic auth
  helper.

- ZODBUserManager: Extend the "notional IZODBUserManager interface"
  with the left-out updateUser facility and a corresponding
  manage_updateUser method for ZMI use. Removed any responsibility
  for updating a user's login from the updateUserPassword and
  manage_updateUserPassword methods. This fixes the breakage
  described in the collector issue below, and makes the ZMI view
  for updating users work in a sane way.
  (http://www.zope.org/Collectors/PAS/42)

- CookieAuthHelper: If expireCookie was called and extractCredentials
  was hit in the same request, the CookieAuthHelper would throw an
  exception (http://www.zope.org/Collectors/PAS/43)

- Added a DEPENDENCIES.txt. (http://www.zope.org/Collectors/PAS/44)


1.3 (2006-06-09)
----------------

- No changes from version 1.3-beta


1.3-beta (2006-06-03)
---------------------

- Modify CookieAuthHelper to prefer __ac form variables to the cookie
  when extracting credentials.
  (https://dev.plone.org/plone/ticket/5355)


1.2 (2006-05-14)
----------------

- Fix manage_zmi_logout which stopped working correctly as soon as the
  PluggableAuthService product code was installed by correcting the
  monkeypatch for it in __init__.py.
  (http://www.zope.org/Collectors/PAS/12)

- Add missing interface for IPropertiedUser and tests
  (http://www.zope.org/Collectors/PAS/16)

- Removed STX links from README.txt which do nothing but return
  404s when clicked from the README on zope.org.
  (http://www.zope.org/Collectors/PAS/6)

- Fixing up inconsistent searching in the listAvailablePrincipals
  method of the ZODBRoleManager and ZODBGroupManager plugins. Now both
  constrain searches by ID.
  (http://www.zope.org/Collectors/PAS/11)

- Convert from using zLOG to using the Python logging module.
  (http://www.zope.org/Collectors/PAS/14)


1.2-beta (2006-02-25)
---------------------

- Added suppport for exporting / importing a PAS and its content via
  the GenericSetup file export framework.

- Made ZODBRoleManager plugin check grants to the principal's groups,
  as well as those made to the principal directly.

- Added two new interfaces, IChallengeProtocolChooser and
  IRequestTypeSniffer. Those are used to select the 'authorization
  protocol' or 'challenger protocol' to be used for challenging
  according to the incoming request type.

- Repaired warings appearing in Zope 2.8.5 due to a couple typos
  in security declarations.

- Repaired DeprecationWarnings due to use of Zope2 interface verification.

- Repaired unit test breakage (unittest.TestCase instances have
  'failUnless'/'failIf', rather than 'assertTrue'/'assertFalse').

- Fixed a couple more places where Zope 2-style ``__implements__``
  were being used to standardize on using ``classImplements``.

- Fixed fallback implementations of ``providedBy`` and
  ``implementedBy`` to always return a tuple.

- Make sure challenge doesn't break if existing instances of the
  PluginRegistry don't yet have ``IChallengeProtocolChooser`` as a
  registered interface. (Would be nice to have some sort of
  migration for the PluginRegistry between PAS releases)

- Don't assume that just because zope.interface can be imported
  that Five is present.


1.1b2 (2005-07-14)
------------------

- Repaired a missing 'nocall:' in the Interfaces activation form.


1.1b1 (2005-07-06)
------------------

- PAS-level id mangling is no more. All (optional) mangling is now
  done on a per-plugin basis.

- Interfaces used by PAS are now usable in both Zope 2.7 and 2.8
  (Five compatible)


1.0.5 (2005-01-31)
------------------

- Simplified detection of the product directory using 'package_home'.

- Set a default value for the 'login' attribute of a PAS, to avoid
  UnboundLocalError.

1.0.4 (2005-01-27)
------------------

- Made 'Extensions' a package, to allow importing its scripts
  as modules.

- Declared new 'IPluggableAuthService' interface, describing additional
  PAS-specific API.

- Exposed PAS' 'resetCredentials' and 'updateCredentials' as public
  methods.

- Monkey-patch ZMI's logout to invoke PAS' 'resetCredentials', if
  present.

- CookieAuth plugin now encodes and decodes cookies in the same
  format as CookieCrumbler to provide compatibility between
  sites running PAS and CC.

- Add a publicly callable "logout" method on the PluggableAuthService
  instance that will call resetCredentials on all activated
  ICredentialsRest plugins, thus effecting a logout.

- Enabled the usage of the CookieAuthHelper login screen functionality
  without actually using the CookieAuthHelper to maintain the
  credentials store in its own auth cookie by ensuring that only
  active updateCredentials plugins are informed about a successful
  login so they can store the credentials.

- Added a _getPAS method to the BasePlugin base class to be used
  as the canonical way of getting at the PAS instance from within
  plugins.

- Group and user plugins can now specify their own title for a
  principal entry (PAS will not compute one if they do).

- PAS and/or plugins can now take advantage of caching using the
  Zope ZCacheable framework with RAM Cache Managers. See
  doc/caching.stx for the details.

- Make 'getUserById' pass the 'login' to '_findUser', so that
  the returned user object can answer 'getUserName' sanely.

- Harden 'logout' against missing HTTP_REFERRER.

- Avoid triggering "Emergency user cannot own" when adding a
  CookieAuthHelper plugin as that user.

- Detect and prevent recursive redirecting in the CookieAuthHelper
  if the login_form cannot be reached by the Anonymous User.

- Made logging when swallowing exceptions much less noisy (they
  *don't* necessarily require attention).

- Clarified interface of IAuthenticationPlugin, which should return
  None rather than raising an exception if asked to authenticate an
  unknown principal;  adjusted ZODBUserManager accordingly.

- Don't log an error in zodb_user_plugin's authenticateCredentials
  if we don't have a record for a particular username, just return None.

- If an IAuthenticationPlugin returns None instead of a tuple
  from authenticateCredentials, don't log a tuple-unpack error in PAS
  itself.


1.0.3 (2004-10-16)
------------------

- Implemented support for issuing challenges via IChallengePlugins.

  - three challenge styles in particular:

    - HTTP Basic Auth

    - CookieCrumbler-like redirection

    - Inline authentication form

- Made unit tests pass when run with cAccessControl.

- plugins/ZODBRoleManager.py: don't claim authority for 'Authenticated'
  or 'Anonymous' roles, which are managed by PAS.

- plugins/ZODBRoleManager.py: don't freak out if a previously assigned
  principal goes away.

- plugins/ZODBGroupManager.py: don't freek out if a previously assigned
  principal goes away.

- plugins/ZODBUserManager.py: plugin now uses AuthEncoding for its
  password encryption so that we can more easily support migrating
  existing UserFolders. Since PAS has been out for a while,
  though, we still will authenticate against old credentials

- Repaired arrow images in two-list ZMI views.

- searchPrincipals will work for exact matches when a plugin supports
  both 'enumerateUsers' and 'enumerateGroups'.

- 'Authenticated' Role is now added dynamically by the
  PluggableAuthService, not by any role manager

- Added WARNING-level logs with tracebacks for all swallowed
  plugin exceptions, so that you notice that there is something
  wrong with the plugins.

- All authenticateCredentials() returned a single None when they
  could not authenticate, although all calls expected a tuple.

- The user id in extract user now calls _verifyUser to get the ID
  mangled by the enumeration plugin, instead of mangling it with the
  authentication ID, thereby allowing the authentication and
  enumeration plugins to be different plugins.


1.0.2 (2004-07-15)
------------------

- ZODBRoleManager and ZODBGroupManager needed the "two_lists" view,
  and associated images, which migrated to the PluginRegsitry product
  when they split;  restored them.


1.0.1 (2004-05-18)
------------------

- CookieAuth plugin didn't successfully set cookies (first, because
  of a NameError, then, due to a glitch with long lines).

- Missing ZPL in most modules.


1.0 (2004-04-29)
----------------

- Initial release
