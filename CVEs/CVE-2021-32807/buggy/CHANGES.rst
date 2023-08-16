Changelog
=========

For changes before version 3.0, see ``HISTORY.rst``.

5.1 (unreleased)
----------------

- Nothing changed yet.


5.0 (2020-10-07)
----------------

- Add support for Python 3.9.

- Remove deprecated classes and functions in
  (see `#32 <https://github.com/zopefoundation/AccessControl/issues/32>`_):

  + ``AccessControl/DTML.py``
  + ``AccessControl/Owned.py``
  + ``AccessControl/Role.py``
  + ``AccessControl/Permissions.py``

- Add deprecation warnings for BBB imports in:

  + ``AccessControl/AuthEncoding.py``
  + ``AccessControl/Owned.py``
  + ``AccessControl/Role.py``
  + ``AccessControl/User.py``

- Although this version might run on Zope 4, it is no longer supported because
  of the dropped deprecation warnings.


4.2 (2020-04-20)
----------------

- Add missing permission ``Manage WebDAV Locks``

- Fix regression for BBB import of ```users.UnrestrictedUser``
  (`#94 <https://github.com/zopefoundation/AccessControl/issues/94>`_)

- Add a check if database is present in ``.owner.ownerInfo``.
  (`#91 <https://github.com/zopefoundation/AccessControl/issues/91>`_).


4.1 (2019-09-02)
----------------

- Python 3: Allow iteration over the result of ``dict.{keys,values,items}``
  (`#89 <https://github.com/zopefoundation/AccessControl/issues/89>`_).


4.0 (2019-05-08)
----------------

Changes since 3.0.12:

- Add support for Python 3.5, 3.6, 3.7 and 3.8.

- Restore simple access to bytes methods in Python 3
  (`#83 <https://github.com/zopefoundation/AccessControl/issues/83>`_)

- Clarify deprecation warnings for several BBB shims.
  (`#32 <https://github.com/zopefoundation/AccessControl/issues/32>`_)

- Add a test to prove that a user folder flag cannot be acquired elsewhere.
  (`#7 <https://github.com/zopefoundation/AccessControl/issues/7>`_)

- Tighten basic auth string handling in ``BasicUserFolder.identify``
  (`#56 <https://github.com/zopefoundation/AccessControl/issues/56>`_)

- Prevent the Zope 4 ZMI from showing an add dialog for the user folder.
  (`#82 <https://github.com/zopefoundation/AccessControl/issues/82>`_)

- Fix order of roles returned by
  ``AccessControl.rolemanager.RoleManager.userdefined_roles``.

- Add configuration for `zodbupdate`.

- Add ``TaintedBytes`` besides ``TaintedString`` in ``AccessControl.tainted``.
  (`#57 <https://github.com/zopefoundation/AccessControl/issues/57>`_)

- Security fix: In ``str.format``, check the security for attributes that are
  accessed. (Ported from 2.13).

- Port ``override_container`` context manager here from 2.13.

- Add AppVeyor configuration to automate building Windows eggs.

- Fix for compilers that only support C89 syntax (e.g. on Windows).

- Sanitize and test `RoleManager` role handling.

- Depend on RestrictedPython >= 4.0.

- #16: Fixed permission handling by avoiding column and row numbers as
  identifiers for permissions and roles.

- Extract ``.AuthEncoding`` to its own package for reuse.

- Declare missing dependency on BTrees.

- Drop `Record` dependency, which now does its own security declaration.

- Remove leftovers from history support dropped in Zope.

- Remove duplicate guard against * imports.
  (`#60 <https://github.com/zopefoundation/AccessControl/issues/60>`_)


3.0.12 (2015-12-21)
-------------------

- Avoid acquiring ``access`` from module wrapped by
  ``SecurityInfo._ModuleSecurityInfo``.  See:
  https://github.com/zopefoundation/AccessControl/issues/12

3.0.11 (2014-11-02)
-------------------

- Harden test fix for machines that do not define `localhost`.

3.0.10 (2014-11-02)
-------------------

- Test fix for machines that do not define `localhost`.

3.0.9 (2014-08-08)
------------------

- GitHub #6: Do not pass SecurityInfo instance itself to declarePublic/declarePrivate
  when using the public/private decorator. This fixes ``Conflicting security declarations``
  warnings on Zope startup.

- LP #1248529: Leave existing security manager in place inside
  ``RoleManager.manage_getUserRolesAndPermissions``.

3.0.8 (2013-07-16)
------------------

- LP #1169923:  ensure initialization of shared ``ImplPython`` state
  (used by ``ImplC``) when using the "C" security policy.  Thanks to
  Arnaud Fontaine for the patch.

3.0.7 (2013-05-14)
------------------

- Remove long-deprecated 'Shared' roles support (pre-dates Zope, never
  used by Zope itself)

- Prevent infinite loop when looking up local roles in an acquisition chain
  with cycles.

3.0.6 (2012-10-31)
------------------

- LP #1071067: Use a stronger random number generator and a constant time
  comparison function.

3.0.5 (2012-10-21)
------------------

- LP #966101: Recognize special `zope2.Private` permission in ZCML
  role directive.

3.0.4 (2012-09-09)
------------------

- LP #1047318: Tighten import restrictions for restricted code.

3.0.3 (2012-08-23)
------------------

- Fix a bug in ZopeSecurityPolicy.py. Global variable `rolesForPermissionOn`
  could be overridden if `__role__` had custom rolesForPermissionOn.

3.0.2 (2012-06-22)
------------------

- Add Anonymous as a default role for Public permission.

3.0.1 (2012-05-24)
------------------

- Fix tests under Python 2.6.

3.0 (2012-05-12)
----------------

- Added decorators for public, private and protected security declarations.

- Update tests to take advantage of automatic test suite discovery.
