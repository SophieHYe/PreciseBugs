##############################################################################
#
# Copyright (c) 2004 Zope Foundation and Contributors.
#
# This software is subject to the provisions of the Zope Public License,
# Version 2.1 (ZPL).  A copy of the ZPL should accompany this distribution.
# THIS SOFTWARE IS PROVIDED "AS IS" AND ANY AND ALL EXPRESS OR IMPLIED
# WARRANTIES ARE DISCLAIMED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF TITLE, MERCHANTABILITY, AGAINST INFRINGEMENT, AND FITNESS
# FOR A PARTICULAR PURPOSE.
#
##############################################################################
""" Unit tests for GenericSetup tool.
"""

import os
import tempfile
import unittest

import six
from six import BytesIO

import transaction
from AccessControl.Permissions import view
from AccessControl.SecurityManagement import newSecurityManager
from AccessControl.SecurityManagement import noSecurityManager
from AccessControl.users import UnrestrictedUser
from Acquisition import aq_base
from OFS.Folder import Folder
from zope.component import adapter
from zope.component import provideHandler
from zope.component.globalregistry import base as base_registry

from Products.GenericSetup import profile_registry

from ..context import TarballExportContext
from ..interfaces import IBeforeProfileImportEvent
from ..interfaces import IProfileImportedEvent
from ..testing import ExportImportZCMLLayer
from ..upgrade import UpgradeStep
from ..upgrade import _registerUpgradeStep
from ..upgrade import listUpgradeSteps
from .common import BaseRegistryTests
from .common import DummyExportContext
from .common import DummyImportContext
from .common import FilesystemTestBase
from .common import TarballTester
from .common import _makeTestFile
from .conformance import ConformsToISetupTool
from .test_registry import _EMPTY_EXPORT_XML
from .test_registry import _EMPTY_IMPORT_XML
from .test_registry import _EMPTY_TOOLSET_XML
from .test_registry import _NORMAL_TOOLSET_XML
from .test_registry import _SINGLE_EXPORT_XML
from .test_registry import _SINGLE_IMPORT_XML
from .test_registry import ONE_FUNC
from .test_registry import IAnotherSite
from .test_registry import IDerivedSite
from .test_registry import ISite
from .test_zcml import dummy_upgrade


_before_import_events = []


@adapter(IBeforeProfileImportEvent)
def handleBeforeProfileImportEvent(event):
    _before_import_events.append(event)


_after_import_events = []


@adapter(IProfileImportedEvent)
def handleProfileImportedEvent(event):
    _after_import_events.append(event)


_METADATA_XML = """<?xml version="1.0"?>
<metadata>
  <version>1.0</version>
  <dependencies>
    <dependency>profile-other:bar</dependency>
  </dependencies>
</metadata>
"""
_DOUBLE_METADATA_XML = """<?xml version="1.0"?>
<metadata>
  <version>1.0</version>
  <dependencies>
    <dependency>profile-other:bar</dependency>
    <dependency>profile-other:ham</dependency>
  </dependencies>
</metadata>
"""
_PLAIN_METADATA_XML = """<?xml version="1.0"?>
<metadata>
  <version>1.0</version>
</metadata>
"""
_BROKEN_METADATA_XML = """<?xml version="1.0"?>
<metadata>
  <version>1.0</version>
  <dependencies>
    <dependency>profile-other:non-existing-profile</dependency>
  </dependencies>
</metadata>
"""


class SetupToolTests(FilesystemTestBase, TarballTester, ConformsToISetupTool):

    layer = ExportImportZCMLLayer

    _PROFILE_PATH = tempfile.mkdtemp(prefix='STT_test')
    _PROFILE_PATH2 = tempfile.mkdtemp(prefix='STT_test2')
    _PROFILE_PATH3 = tempfile.mkdtemp(prefix='STT_test3')

    def afterSetUp(self):
        from ..upgrade import _upgrade_registry
        _upgrade_registry.clear()
        profile_registry.clear()
        global _before_import_events
        global _after_import_events
        _before_import_events = []
        provideHandler(handleBeforeProfileImportEvent)
        _after_import_events = []
        provideHandler(handleProfileImportedEvent)

    def beforeTearDown(self):
        base_registry.unregisterHandler(handleBeforeProfileImportEvent)
        base_registry.unregisterHandler(handleProfileImportedEvent)
        FilesystemTestBase.beforeTearDown(self)
        from ..upgrade import _upgrade_registry
        profile_registry.clear()
        _upgrade_registry.clear()
        noSecurityManager()

    def _getTargetClass(self):
        from ..tool import SetupTool

        return SetupTool

    def _makeSite(self, title="Don't care"):

        site = Folder()
        site._setId('site')
        site.title = title

        self.app._setObject('site', site)
        self.app.acl_users.userFolderAddUser('admin', '', ['Manager'], [])
        newSecurityManager(None, self.app.acl_users.getUser('admin'))
        return self.app._getOb('site')

    def test_empty(self):

        tool = self._makeOne('setup_tool')

        self.assertEqual(tool.getBaselineContextID(), '')

        import_registry = tool.getImportStepRegistry()
        self.assertEqual(len(import_registry.listSteps()), 0)

        export_registry = tool.getExportStepRegistry()
        export_steps = export_registry.listSteps()
        self.assertEqual(len(export_steps), 0)

        toolset_registry = tool.getToolsetRegistry()
        self.assertEqual(len(toolset_registry.listForbiddenTools()), 0)
        self.assertEqual(len(toolset_registry.listRequiredTools()), 0)

    def test_getBaselineContextID(self):
        from ..tool import EXPORT_STEPS_XML
        from ..tool import IMPORT_STEPS_XML
        from ..tool import TOOLSET_XML

        tool = self._makeOne('setup_tool')

        self._makeFile(IMPORT_STEPS_XML, _EMPTY_IMPORT_XML)
        self._makeFile(EXPORT_STEPS_XML, _EMPTY_EXPORT_XML)
        self._makeFile(TOOLSET_XML, _EMPTY_TOOLSET_XML)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)
        tool.setBaselineContext('profile-other:foo')

        self.assertEqual(tool.getBaselineContextID(), 'profile-other:foo')

    def test_setBaselineContext_invalid(self):

        tool = self._makeOne('setup_tool')

        self.assertRaises(KeyError, tool.setBaselineContext, 'profile-foo')

    def test_setBaselineContext_empty_string(self):

        tool = self._makeOne('setup_tool')

        self.assertRaises(KeyError, tool.setBaselineContext, '')

    def test_setBaselineContext(self):
        from ..tool import EXPORT_STEPS_XML
        from ..tool import IMPORT_STEPS_XML
        from ..tool import TOOLSET_XML

        tool = self._makeOne('setup_tool')
        tool.getExportStepRegistry().clear()

        self._makeFile(IMPORT_STEPS_XML, _SINGLE_IMPORT_XML)
        self._makeFile(EXPORT_STEPS_XML, _SINGLE_EXPORT_XML)
        self._makeFile(TOOLSET_XML, _NORMAL_TOOLSET_XML)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)
        tool.setBaselineContext('profile-other:foo')

        self.assertEqual(tool.getBaselineContextID(), 'profile-other:foo')

        import_registry = tool.getImportStepRegistry()
        self.assertEqual(len(import_registry.listSteps()), 1)
        self.assertTrue('one' in import_registry.listSteps())
        info = import_registry.getStepMetadata('one')
        self.assertEqual(info['id'], 'one')
        self.assertEqual(info['title'], 'One Step')
        self.assertEqual(info['version'], '1')
        self.assertTrue('One small step' in info['description'])
        self.assertEqual(info['handler'],
                         'Products.GenericSetup.tests.test_registry.ONE_FUNC')

        self.assertEqual(import_registry.getStep('one'), ONE_FUNC)

        export_registry = tool.getExportStepRegistry()
        self.assertEqual(len(export_registry.listSteps()), 1)
        self.assertTrue('one' in import_registry.listSteps())
        info = export_registry.getStepMetadata('one')
        self.assertEqual(info['id'], 'one')
        self.assertEqual(info['title'], 'One Step')
        self.assertTrue('One small step' in info['description'])
        self.assertEqual(info['handler'],
                         'Products.GenericSetup.tests.test_registry.ONE_FUNC')

        self.assertEqual(export_registry.getStep('one'), ONE_FUNC)

    def test_runImportStepFromProfile_nonesuch(self):

        site = self._makeSite()

        tool = self._makeOne('setup_tool').__of__(site)

        self.assertRaises(KeyError, tool.runImportStepFromProfile,
                          '', 'nonesuch')

    def test_runImportStepFromProfile_simple(self):

        TITLE = 'original title'
        site = self._makeSite(TITLE)

        tool = self._makeOne('setup_tool').__of__(site)

        registry = tool.getImportStepRegistry()
        registry.registerStep('simple', '1', _uppercaseSiteTitle)

        result = tool.runImportStepFromProfile('snapshot-dummy', 'simple')

        self.assertEqual(len(result['steps']), 1)

        self.assertEqual(result['steps'][0], 'simple')
        self.assertEqual(result['messages']['simple'], 'Uppercased title')

        self.assertEqual(site.title, TITLE.upper())

        global _before_import_events
        self.assertEqual(len(_before_import_events), 1)
        self.assertEqual(_before_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_before_import_events[0].steps, ['simple'])
        self.assertEqual(_before_import_events[0].full_import, False)

        global _after_import_events
        self.assertEqual(len(_after_import_events), 1)
        self.assertEqual(_after_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_after_import_events[0].steps, ['simple'])
        self.assertEqual(_after_import_events[0].full_import, False)

    def test_runImportStepFromProfile_dependencies(self):

        TITLE = 'original title'
        site = self._makeSite(TITLE)

        tool = self._makeOne('setup_tool').__of__(site)

        registry = tool.getImportStepRegistry()
        registry.registerStep('dependable', '1', _underscoreSiteTitle)
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))

        result = tool.runImportStepFromProfile('snapshot-dummy', 'dependent')

        self.assertEqual(len(result['steps']), 2)

        self.assertEqual(result['steps'][0], 'dependable')
        self.assertEqual(result['messages']['dependable'], 'Underscored title')

        self.assertEqual(result['steps'][1], 'dependent')
        self.assertEqual(result['messages']['dependent'], 'Uppercased title')
        self.assertEqual(site.title, TITLE.replace(' ', '_').upper())

        global _before_import_events
        self.assertEqual(len(_before_import_events), 1)
        self.assertEqual(_before_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_before_import_events[0].steps,
                         ['dependable', 'dependent'])
        self.assertEqual(_before_import_events[0].full_import, False)

        global _after_import_events
        self.assertEqual(len(_after_import_events), 1)
        self.assertEqual(_after_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_after_import_events[0].steps,
                         ['dependable', 'dependent'])
        self.assertEqual(_after_import_events[0].full_import, False)

    def test_runImportStepFromProfile_skip_dependencies(self):

        TITLE = 'original title'
        site = self._makeSite(TITLE)

        tool = self._makeOne('setup_tool').__of__(site)

        registry = tool.getImportStepRegistry()
        registry.registerStep('dependable', '1', _underscoreSiteTitle)
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))

        result = tool.runImportStepFromProfile('snapshot-dummy', 'dependent',
                                               run_dependencies=False)

        self.assertEqual(len(result['steps']), 1)

        self.assertEqual(result['steps'][0], 'dependent')
        self.assertEqual(result['messages']['dependent'], 'Uppercased title')

        self.assertEqual(site.title, TITLE.upper())

        global _before_import_events
        self.assertEqual(len(_before_import_events), 1)
        self.assertEqual(_before_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_before_import_events[0].steps, ['dependent'])
        self.assertEqual(_before_import_events[0].full_import, False)

        global _after_import_events
        self.assertEqual(len(_after_import_events), 1)
        self.assertEqual(_after_import_events[0].profile_id, 'snapshot-dummy')
        self.assertEqual(_after_import_events[0].steps, ['dependent'])
        self.assertEqual(_after_import_events[0].full_import, False)

    def test_runImportStepFromProfile_default_purge(self):

        site = self._makeSite()

        tool = self._makeOne('setup_tool').__of__(site)
        registry = tool.getImportStepRegistry()
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runImportStepFromProfile('snapshot-dummy', 'purging')

        self.assertEqual(len(result['steps']), 1)
        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Purged')
        self.assertTrue(site.purged)

    def test_runImportStepFromProfile_explicit_purge(self):

        site = self._makeSite()

        tool = self._makeOne('setup_tool').__of__(site)
        registry = tool.getImportStepRegistry()
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runImportStepFromProfile('snapshot-dummy', 'purging',
                                               purge_old=True)

        self.assertEqual(len(result['steps']), 1)
        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Purged')
        self.assertTrue(site.purged)

    def test_runImportStepFromProfile_skip_purge(self):

        site = self._makeSite()

        tool = self._makeOne('setup_tool').__of__(site)
        registry = tool.getImportStepRegistry()
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runImportStepFromProfile('snapshot-dummy', 'purging',
                                               purge_old=False)

        self.assertEqual(len(result['steps']), 1)
        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Unpurged')
        self.assertFalse(site.purged)

    def test_runImportStepFromProfile_consistent_context(self):

        site = self._makeSite()

        tool = self._makeOne('setup_tool').__of__(site)

        registry = tool.getImportStepRegistry()
        registry.registerStep('purging', '1', _purgeIfRequired)
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('purging', ))

        tool.runImportStepFromProfile('snapshot-dummy', 'dependent',
                                      purge_old=False)
        self.assertFalse(site.purged)

    def test_runAllImportStepsFromProfile_empty(self):

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        result = tool.runAllImportStepsFromProfile('snapshot-dummy')

        self.assertEqual(len(result['steps']), 3)

    def test_runAllImportStepsFromProfile_inquicksuccession(self):
        """
        This test provokes an issue that only appears in testing.
        There it can happen that profiles get run multiple times within
        a second. As of 1.6.3, genericsetup does not handle this.
        """

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        tool.runAllImportStepsFromProfile('snapshot-dummy')
        tool.runAllImportStepsFromProfile('snapshot-dummy')
        # For good measurement
        tool.runAllImportStepsFromProfile('snapshot-dummy')

        self.assertTrue("No exception thrown")

    def test_runAllImportStepsFromProfile_sorted_default_purge(self):

        TITLE = 'original title'
        PROFILE_ID = 'snapshot-testing'
        site = self._makeSite(TITLE)
        tool = self._makeOne('setup_tool').__of__(site)
        tool._exclude_global_steps = True

        registry = tool.getImportStepRegistry()
        registry.registerStep(
            'dependable', '1', _underscoreSiteTitle, ('purging', ))
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runAllImportStepsFromProfile(PROFILE_ID)

        self.assertEqual(len(result['steps']), 3)

        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Purged')

        self.assertEqual(result['steps'][1], 'dependable')
        self.assertEqual(result['messages']['dependable'], 'Underscored title')

        self.assertEqual(result['steps'][2], 'dependent')
        self.assertEqual(result['messages']['dependent'], 'Uppercased title')

        self.assertEqual(site.title, TITLE.replace(' ', '_').upper())
        self.assertTrue(site.purged)

        prefix = 'import-all-%s' % PROFILE_ID
        logged = [x for x in tool.objectIds('File') if x.startswith(prefix)]
        self.assertEqual(len(logged), 1)

    def check_restricted_access(self, obj):
        # For most objects that we create, we do not want ordinary users to
        # see it, also not when they have View permission on a higher level.
        rop_info = obj.rolesOfPermission(view)
        allowed_roles = sorted([x['name'] for x in rop_info
                                if x['selected']])
        self.assertEqual(allowed_roles, ['Manager', 'Owner'])
        self.assertFalse(obj.acquiredRolesAreUsedBy(view))

    def test_runAllImportStepsFromProfile_unicode_id_creates_reports(self):

        TITLE = 'original title'
        PROFILE_ID = u'snapshot-testing'
        site = self._makeSite(TITLE)
        tool = self._makeOne('setup_tool').__of__(site)

        registry = tool.getImportStepRegistry()
        registry.registerStep(
            'dependable', '1', _underscoreSiteTitle, ('purging', ))
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))
        registry.registerStep('purging', '1', _purgeIfRequired)

        tool.runAllImportStepsFromProfile(PROFILE_ID)

        prefix = str('import-all-%s' % PROFILE_ID)
        logged = [x for x in tool.objectIds('File') if x.startswith(prefix)]
        self.assertEqual(len(logged), 1)

        # Check acess restriction on log files
        logged = [x for x in tool.objectIds('File')]
        for file_id in logged:
            file_ob = tool._getOb(file_id)
            self.check_restricted_access(file_ob)

    def test_runAllImportStepsFromProfile_sorted_explicit_purge(self):

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        tool._exclude_global_steps = True

        registry = tool.getImportStepRegistry()
        registry.registerStep(
            'dependable', '1', _underscoreSiteTitle, ('purging', ))
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runAllImportStepsFromProfile('snapshot-dummy',
                                                   purge_old=True)

        self.assertEqual(len(result['steps']), 3)

        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Purged')

        self.assertEqual(result['steps'][1], 'dependable')
        self.assertEqual(result['steps'][2], 'dependent')
        self.assertTrue(site.purged)

    def test_runAllImportStepsFromProfile_sorted_skip_purge(self):

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        tool._exclude_global_steps = True

        registry = tool.getImportStepRegistry()
        registry.registerStep(
            'dependable', '1', _underscoreSiteTitle, ('purging', ))
        registry.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))
        registry.registerStep('purging', '1', _purgeIfRequired)

        result = tool.runAllImportStepsFromProfile('snapshot-dummy',
                                                   purge_old=False)

        self.assertEqual(len(result['steps']), 3)

        self.assertEqual(result['steps'][0], 'purging')
        self.assertEqual(result['messages']['purging'], 'Unpurged')

        self.assertEqual(result['steps'][1], 'dependable')
        self.assertEqual(result['steps'][2], 'dependent')
        self.assertFalse(site.purged)

    def test_runAllImportStepsFromProfile_without_depends(self):
        from ..metadata import METADATA_XML

        self._makeFile(METADATA_XML, _METADATA_XML)

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)

        _imported = []

        def applyContext(context):
            _imported.append(context._profile_path)

        tool.applyContext = applyContext
        tool.runAllImportStepsFromProfile('profile-other:foo',
                                          ignore_dependencies=True)
        self.assertEqual(_imported, [self._PROFILE_PATH])

    def test_runAllImportStepsFromProfile_with_depends(self):
        from ..metadata import METADATA_XML

        self._makeFile(METADATA_XML, _METADATA_XML)

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)
        profile_registry.registerProfile('bar', 'Bar', '', self._PROFILE_PATH2)

        _imported = []

        def applyContext(context):
            _imported.append(context._profile_path)

        tool.applyContext = applyContext
        tool.runAllImportStepsFromProfile('profile-other:foo',
                                          ignore_dependencies=False)
        self.assertEqual(_imported, [self._PROFILE_PATH2, self._PROFILE_PATH])

    def _setup_dependency_strategy_test_tool(self):
        # If we add a dependency profile in our metadata.xml, and this
        # dependency was already applied, then we do not need to apply
        # it yet again.  Once is quite enough, thank you.  Running any
        # upgrade steps would be nice though.  There are options.
        # Setup a tool and profiles for testing dependency strategies.
        from ..interfaces import EXTENSION
        from ..metadata import METADATA_XML
        self._makeFile(METADATA_XML, _DOUBLE_METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH2, _PLAIN_METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH3, _PLAIN_METADATA_XML)
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        # Register main profile and two dependency profiles.
        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH,
                                         profile_type=EXTENSION)
        profile_registry.registerProfile('bar', 'Bar', '', self._PROFILE_PATH2,
                                         profile_type=EXTENSION)
        profile_registry.registerProfile('ham', 'Ham', '', self._PROFILE_PATH3,
                                         profile_type=EXTENSION)

        # Apply the second profile.
        tool.runAllImportStepsFromProfile('profile-other:bar')

        # Register an upgrade step.  Note that applying this step will
        # set the profile version to 1.1, even though the metadata of
        # the profile really says 1.0.  We will use this to check
        # whether the upgrade step has been applied (version is 1.1)
        # or the full profile has been applied (version is 1.0).
        step_bar = UpgradeStep(
            "Upgrade", "other:bar", '1.0', '1.1', '', dummy_upgrade, None, "1")
        _registerUpgradeStep(step_bar)
        # And another one.
        step_ham = UpgradeStep(
            "Upgrade", "other:ham", '1.0', '1.1', '', dummy_upgrade, None, "1")
        _registerUpgradeStep(step_ham)

        # Gather list of imported profiles.
        tool._imported = []

        def applyContext(context):
            tool._imported.append(context._profile_path)

        tool.applyContext = applyContext

        return tool

    def test_runAllImportStepsFromProfile_with_default_strategy(self):
        # Default strategy: apply new profiles, upgrade old profiles.
        tool = self._setup_dependency_strategy_test_tool()

        # Run the main profile.
        tool.runAllImportStepsFromProfile('profile-other:foo')
        # The main and third profile have been applied.
        self.assertEqual(tool._imported,
                         [self._PROFILE_PATH3, self._PROFILE_PATH])
        # The upgrade step of the second profile has been applied,
        # pushing it to version 1.1.
        self.assertEqual(tool.getLastVersionForProfile('other:bar'),
                         ('1', '1'))
        # Third profile is at 1.0.
        self.assertEqual(tool.getLastVersionForProfile('other:ham'),
                         ('1', '0'))

    def test_runAllImportStepsFromProfile_with_reapply_strategy(self):
        # You can choose the old behavior of always applying the
        # dependencies.  This ignores any upgrade steps.
        tool = self._setup_dependency_strategy_test_tool()

        # Run the main profile.
        from ..tool import DEPENDENCY_STRATEGY_REAPPLY
        tool.runAllImportStepsFromProfile(
            'profile-other:foo',
            dependency_strategy=DEPENDENCY_STRATEGY_REAPPLY)
        # All three profiles have been applied.
        self.assertEqual(tool._imported,
                         [self._PROFILE_PATH2, self._PROFILE_PATH3,
                          self._PROFILE_PATH])
        self.assertEqual(tool.getLastVersionForProfile('other:bar'),
                         ('1', '0'))
        self.assertEqual(tool.getLastVersionForProfile('other:ham'),
                         ('1', '0'))

    def test_runAllImportStepsFromProfile_with_new_strategy(self):
        # You can choose to be happy with any applied version and
        # ignore any upgrade steps.
        tool = self._setup_dependency_strategy_test_tool()

        # Run the main profile.
        from ..tool import DEPENDENCY_STRATEGY_NEW
        tool.runAllImportStepsFromProfile(
            'profile-other:foo',
            dependency_strategy=DEPENDENCY_STRATEGY_NEW)
        # The main and third profile have been applied.
        self.assertEqual(tool._imported,
                         [self._PROFILE_PATH3, self._PROFILE_PATH])
        # Second profile stays at 1.0.
        self.assertEqual(tool.getLastVersionForProfile('other:bar'),
                         ('1', '0'))
        self.assertEqual(tool.getLastVersionForProfile('other:ham'),
                         ('1', '0'))

    def test_runAllImportStepsFromProfile_with_ignore_strategy(self):
        # You can choose to be ignore all dependency profiles.
        tool = self._setup_dependency_strategy_test_tool()

        # Run the main profile.
        from ..tool import DEPENDENCY_STRATEGY_IGNORE
        tool.runAllImportStepsFromProfile(
            'profile-other:foo',
            dependency_strategy=DEPENDENCY_STRATEGY_IGNORE)
        # Only the main profile has been applied.
        self.assertEqual(tool._imported,
                         [self._PROFILE_PATH])
        # Second profile stays at 1.0.
        self.assertEqual(tool.getLastVersionForProfile('other:bar'),
                         ('1', '0'))
        # Third profile is not applied.
        self.assertEqual(tool.getLastVersionForProfile('other:ham'),
                         ('unknown'))

    def test_runAllImportStepsFromProfile_unknown_strategy(self):
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)
        self.assertRaises(ValueError, tool.runAllImportStepsFromProfile,
                          'profile-other:foo', dependency_strategy='random')

    def test_runAllImportStepsFromProfile_set_last_profile_version(self):
        from ..metadata import METADATA_XML

        self._makeFile(METADATA_XML, _METADATA_XML)

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)

        # test initial states
        profile_id = "other:foo"
        self.assertEqual(tool.getVersionForProfile(profile_id), '1.0')
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         'unknown')

        # run all imports steps
        tool.runAllImportStepsFromProfile('profile-other:foo',
                                          ignore_dependencies=True)

        # events.handleProfileImportedEvent should set last profile version
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         ('1', '0'))

    def test_runAllImportStepsFromProfile_step_registration_with_depends(self):
        from ..metadata import METADATA_XML

        self._makeFile(METADATA_XML, _METADATA_XML)

        _IMPORT_STEPS_XML = """<?xml version="1.0"?>
<import-steps>
 <import-step id="one"
             version="1"
             handler="Products.GenericSetup.tests.common.dummy_handler"
             title="One Step">
  One small step
 </import-step>
</import-steps>
"""
        self._makeFile('import_steps.xml', _IMPORT_STEPS_XML)

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH)
        profile_registry.registerProfile('bar', 'Bar', '', self._PROFILE_PATH2)

        result = tool.runAllImportStepsFromProfile('profile-other:foo',
                                                   ignore_dependencies=False)

        # ensure the additional step on foo was imported
        self.assertTrue('one' in result['steps'])

    def test_runAllImportStepsFromProfile_skipStep(self):

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        result = tool.runAllImportStepsFromProfile(
            'snapshot-dummy',
            blacklisted_steps=['toolset'],
        )

        self.assertEqual((result['messages']['toolset']), 'step skipped')

    def test_runAllImportStepsFromProfile_with_base_profile(self):
        # Applying a base profile should clear the profile upgrade
        # versions.
        from ..interfaces import BASE
        from ..interfaces import EXTENSION
        from ..metadata import METADATA_XML
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        self._makeFile(METADATA_XML, _METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH2, _PLAIN_METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH3, _PLAIN_METADATA_XML)

        # Register a base and two extension profile.  The base profile
        # 'foo' has a dependency 'bar'.  This might not make sense,
        # but it will serve to check that we clear the profile
        # versions right before we apply the base profile, which means
        # right after any dependency profiles.
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH, profile_type=BASE)
        profile_registry.registerProfile(
            'bar', 'Bar', '', self._PROFILE_PATH2, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'ham', 'Ham', '', self._PROFILE_PATH3, profile_type=EXTENSION)
        # Apply the extension profile.
        tool.runAllImportStepsFromProfile('profile-other:ham')
        self.assertEqual(tool._profile_upgrade_versions,
                         {u'other:ham': (u'1', u'0')})
        # Apply the base profile.
        tool.runAllImportStepsFromProfile('profile-other:foo')
        self.assertEqual(tool._profile_upgrade_versions,
                         {u'other:foo': (u'1', u'0')})

    def test_runAllImportStepsFromProfile_with_unknown_pre_handler(self):
        # Registering already fails.
        self.assertRaises(
            ValueError, profile_registry.registerProfile,
            'foo', 'Foo', '', self._PROFILE_PATH,
            pre_handler='Products.GenericSetup.tests.test_tool.foo_handler')

    def test_runAllImportStepsFromProfile_with_unknown_post_handler(self):
        # Registering already fails.
        self.assertRaises(
            ValueError, profile_registry.registerProfile,
            'foo', 'Foo', '', self._PROFILE_PATH,
            post_handler='Products.GenericSetup.tests.test_tool.foo_handler')

    def test_runAllImportStepsFromProfile_pre_post_handlers_dotted_names(self):
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH,
            pre_handler='Products.GenericSetup.tests.test_tool.pre_handler',
            post_handler='Products.GenericSetup.tests.test_tool.post_handler')
        tool.runAllImportStepsFromProfile('profile-other:foo')
        self.assertEqual(tool.pre_handler_called, 1)
        self.assertEqual(tool.post_handler_called, 1)
        tool.runAllImportStepsFromProfile('profile-other:foo')
        self.assertEqual(tool.pre_handler_called, 2)
        self.assertEqual(tool.post_handler_called, 2)

    def test_runAllImportStepsFromProfile_pre_post_handlers_functions(self):
        # When you register a profile with pre/post handlers in zcml, you do
        # not get dotted names (strings) but an actual function.
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH,
            pre_handler=pre_handler,
            post_handler=post_handler)
        tool.runAllImportStepsFromProfile('profile-other:foo')
        self.assertEqual(tool.pre_handler_called, 1)
        self.assertEqual(tool.post_handler_called, 1)
        tool.runAllImportStepsFromProfile('profile-other:foo')
        self.assertEqual(tool.pre_handler_called, 2)
        self.assertEqual(tool.post_handler_called, 2)

    def test_runExportStep_nonesuch(self):
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        self.assertRaises(ValueError, tool.runExportStep, 'nonesuch')

    def test_runExportStep_step_registry_empty(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool

        result = tool.runExportStep('step_registries')

        self.assertEqual(len(result['steps']), 1)
        self.assertEqual(result['steps'][0], 'step_registries')
        self.assertEqual(result['messages']['step_registries'], None)

    def test_runExportStep_step_registry_default(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool._import_registry.registerStep('foo', handler='foo.bar')
        steps = 'Products.GenericSetup.tool.exportStepRegistries'
        tool._export_registry.registerStep('step_registries', steps,
                                           'Export import / export steps.')

        result = tool.runExportStep('step_registries')

        self.assertEqual(len(result['steps']), 1)
        self.assertEqual(result['steps'][0], 'step_registries')
        self.assertEqual(result['messages']['step_registries'], None)
        fileish = BytesIO(result['tarball'])

        self._verifyTarballContents(fileish,
                                    ['import_steps.xml', 'export_steps.xml'])
        self._verifyTarballEntryXML(
            fileish, 'import_steps.xml', _DEFAULT_STEP_REGISTRIES_IMPORT_XML)
        self._verifyTarballEntryXML(
            fileish, 'export_steps.xml', _DEFAULT_STEP_REGISTRIES_EXPORT_XML)

    def test_runAllExportSteps_empty(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool

        result = tool.runAllExportSteps()

        self.assertEqual(
            sorted(result['steps']),
            ['componentregistry', 'rolemap', 'step_registries', 'toolset'])
        self.assertEqual(result['messages']['step_registries'], None)

    def test_runAllExportSteps_default(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool._import_registry.registerStep('foo', handler='foo.bar')
        steps = 'Products.GenericSetup.tool.exportStepRegistries'
        tool._export_registry.registerStep('step_registries', steps,
                                           'Export import / export steps.')

        result = tool.runAllExportSteps()

        self.assertEqual(sorted(result['steps']),
                         ['componentregistry', 'rolemap',
                          'step_registries', 'toolset'])
        self.assertEqual(result['messages']['step_registries'], None)
        fileish = BytesIO(result['tarball'])

        self._verifyTarballContents(fileish,
                                    ['import_steps.xml', 'export_steps.xml',
                                     'rolemap.xml', 'toolset.xml'])
        self._verifyTarballEntryXML(
            fileish, 'import_steps.xml', _DEFAULT_STEP_REGISTRIES_IMPORT_XML)
        self._verifyTarballEntryXML(
            fileish, 'export_steps.xml', _DEFAULT_STEP_REGISTRIES_EXPORT_XML)

    def test_runAllExportSteps_extras(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        steps = 'Products.GenericSetup.tool.exportStepRegistries'
        tool._export_registry.registerStep('step_registries', steps,
                                           'Export import / export steps.')

        import_reg = tool.getImportStepRegistry()
        import_reg.registerStep(
            'dependable', '1', _underscoreSiteTitle, ('purging', ))
        import_reg.registerStep(
            'dependent', '1', _uppercaseSiteTitle, ('dependable', ))
        import_reg.registerStep('purging', '1', _purgeIfRequired)

        export_reg = tool.getExportStepRegistry()
        export_reg.registerStep('properties', _exportPropertiesINI)

        result = tool.runAllExportSteps()

        self.assertEqual(len(result['steps']), 5)
        self.assertEqual(sorted(result['steps']),
                         ['componentregistry', 'properties', 'rolemap',
                          'step_registries', 'toolset'])

        self.assertEqual(result['messages']['properties'],
                         'Exported properties')
        self.assertEqual(result['messages']['step_registries'], None)

        fileish = BytesIO(result['tarball'])

        self._verifyTarballContents(fileish,
                                    ['import_steps.xml', 'export_steps.xml',
                                     'properties.ini', 'rolemap.xml',
                                     'toolset.xml'])
        self._verifyTarballEntryXML(
            fileish, 'import_steps.xml', _EXTRAS_STEP_REGISTRIES_IMPORT_XML)
        self._verifyTarballEntryXML(
            fileish, 'export_steps.xml', _EXTRAS_STEP_REGISTRIES_EXPORT_XML)
        ini_string = _PROPERTIES_INI % site.title
        self._verifyTarballEntry(fileish, 'properties.ini',
                                 ini_string.encode('utf-8'))

    def test_manage_importTarball(self):
        # Tests for importing a tarball with GenericSetup files.
        # We are especially interested to see if old settings get purged.
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        # We need to be Manager to see the result of calling
        # manage_importTarball.
        newSecurityManager(None, UnrestrictedUser('root', '', ['Manager'], ''))

        ROLEMAP_XML = """<?xml version="1.0"?>
<rolemap>
  <roles>
    <role name="%s" />
  </roles>
  <permissions />
</rolemap>
"""

        def rolemap_tarball(name):
            # Create a tarball archive with rolemap.xml containing 'name' as
            # role.
            context = TarballExportContext(tool)
            contents = ROLEMAP_XML % name
            if isinstance(contents, six.text_type):
                contents = contents.encode('utf-8')
            context.writeDataFile('rolemap.xml', contents, 'text/xml')
            return context.getArchive()

        # Import first role.
        tool.manage_importTarball(rolemap_tarball('First'))
        self.assertTrue('First' in site.valid_roles())

        # Import second role.
        tool.manage_importTarball(rolemap_tarball('Second'))
        self.assertTrue('Second' in site.valid_roles())
        # The first role has been purged, because that is the default.
        self.assertFalse('First' in site.valid_roles())
        # A few standard roles are never removed, probably because they are
        # defined one level higher.
        self.assertTrue('Anonymous' in site.valid_roles())
        self.assertTrue('Authenticated' in site.valid_roles())
        self.assertTrue('Manager' in site.valid_roles())
        self.assertTrue('Owner' in site.valid_roles())

        # Import third role in non-purge mode.
        tool.manage_importTarball(rolemap_tarball('Third'), purge_old=False)
        self.assertTrue('Third' in site.valid_roles())
        # The second role is still there.
        self.assertTrue('Second' in site.valid_roles())

        # When you use the form, and uncheck the purge_old checkbox, then the
        # browser does not send the purge_old parameter in the request.  To
        # work around this, the form always passes a hidden 'submitted'
        # parameter.
        # Import fourth role in non-purge mode with a form submit.
        tool.manage_importTarball(rolemap_tarball('Fourth'), submitted='yes')
        self.assertTrue('Fourth' in site.valid_roles())
        # The other roles are still there.
        self.assertTrue('Second' in site.valid_roles())
        self.assertTrue('Third' in site.valid_roles())
        self.assertTrue('Manager' in site.valid_roles())

    def test_createSnapshot_default(self):
        _EXPECTED = [
            ('import_steps.xml', _DEFAULT_STEP_REGISTRIES_IMPORT_XML),
            ('export_steps.xml', _DEFAULT_STEP_REGISTRIES_EXPORT_XML),
            ('rolemap.xml', 'dummy'),
            ('toolset.xml', 'dummy'),
        ]

        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool._import_registry.registerStep('foo', handler='foo.bar')
        tool._export_registry.registerStep(
            'step_registries',
            'Products.GenericSetup.tool.exportStepRegistries',
            'Export import / export steps.')

        self.assertEqual(len(tool.listSnapshotInfo()), 0)

        result = tool.createSnapshot('default')

        self.assertEqual(
            sorted(result['steps']),
            ['componentregistry', 'rolemap', 'step_registries', 'toolset'])
        self.assertEqual(result['messages']['step_registries'], None)

        snapshot = result['snapshot']

        self.assertEqual(len(snapshot.objectIds()), len(_EXPECTED))

        for id in [x[0] for x in _EXPECTED]:
            self.assertTrue(id in snapshot.objectIds())

        def normalize_xml(xml):
            # using this might mask a real problem on windows, but so far the
            # different newlines just caused problems in this test
            lines = [line.strip() for line in xml.splitlines() if line.strip()]
            return ' '.join(lines)

        fileobj = snapshot._getOb('import_steps.xml')
        self.assertEqual(normalize_xml(fileobj.read()),
                         normalize_xml(_DEFAULT_STEP_REGISTRIES_IMPORT_XML))

        fileobj = snapshot._getOb('export_steps.xml')
        self.assertEqual(normalize_xml(fileobj.read()),
                         normalize_xml(_DEFAULT_STEP_REGISTRIES_EXPORT_XML))

        self.assertEqual(len(tool.listSnapshotInfo()), 1)

        info = tool.listSnapshotInfo()[0]

        self.assertEqual(info['id'], 'default')
        self.assertEqual(info['title'], 'default')

        # Check access restriction on snapshot files and folders
        self.check_restricted_access(tool.snapshots)
        self.check_restricted_access(snapshot)
        for obj in snapshot.objectValues():
            self.check_restricted_access(obj)
            if hasattr(aq_base(obj), 'objectValues'):
                for child in obj.objectValues():
                    self.check_restricted_access(child)

    def test_applyContext(self):
        from ..tool import EXPORT_STEPS_XML
        from ..tool import IMPORT_STEPS_XML
        from ..tool import TOOLSET_XML

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)
        tool.getImportStepRegistry().clear()
        tool.getExportStepRegistry().clear()
        tool.getToolsetRegistry().clear()

        context = DummyImportContext(site, tool=tool)
        context._files[IMPORT_STEPS_XML] = _SINGLE_IMPORT_XML
        context._files[EXPORT_STEPS_XML] = _SINGLE_EXPORT_XML
        context._files[TOOLSET_XML] = _NORMAL_TOOLSET_XML

        tool.applyContext(context)

        import_registry = tool.getImportStepRegistry()
        self.assertEqual(len(import_registry.listSteps()), 1)
        self.assertTrue('one' in import_registry.listSteps())
        info = import_registry.getStepMetadata('one')

        self.assertEqual(info['id'], 'one')
        self.assertEqual(info['title'], 'One Step')
        self.assertEqual(info['version'], '1')
        self.assertTrue('One small step' in info['description'])
        self.assertEqual(info['handler'],
                         'Products.GenericSetup.tests.test_registry.ONE_FUNC')

        self.assertEqual(import_registry.getStep('one'), ONE_FUNC)

        export_registry = tool.getExportStepRegistry()
        self.assertEqual(len(export_registry.listSteps()), 1)
        self.assertTrue('one' in import_registry.listSteps())
        info = export_registry.getStepMetadata('one')
        self.assertEqual(info['id'], 'one')
        self.assertEqual(info['title'], 'One Step')
        self.assertTrue('One small step' in info['description'])
        self.assertEqual(info['handler'],
                         'Products.GenericSetup.tests.test_registry.ONE_FUNC')

        self.assertEqual(export_registry.getStep('one'), ONE_FUNC)

    def test_listContextInfos_empty(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        infos = tool.listContextInfos()
        self.assertEqual(len(infos), 0)

    def test_listContextInfos_with_snapshot(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool.createSnapshot('testing')
        infos = tool.listContextInfos()
        self.assertEqual(len(infos), 1)
        info = infos[0]
        self.assertEqual(info['id'], 'snapshot-testing')
        self.assertEqual(info['title'], 'testing')
        self.assertEqual(info['type'], 'snapshot')

    def test_listContextInfos_with_registered_base_profile(self):
        from ..interfaces import BASE

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH,
                                         'Foo', BASE)
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        infos = tool.listContextInfos()
        self.assertEqual(len(infos), 1)
        info = infos[0]
        self.assertEqual(info['id'], 'profile-Foo:foo')
        self.assertEqual(info['title'], 'Foo')
        self.assertEqual(info['type'], 'base')

    def test_listContextInfos_with_registered_extension_profile(self):
        from ..interfaces import EXTENSION

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH,
                                         'Foo', EXTENSION)
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        infos = tool.listContextInfos()
        self.assertEqual(len(infos), 1)
        info = infos[0]
        self.assertEqual(info['id'], 'profile-Foo:foo')
        self.assertEqual(info['title'], 'Foo')
        self.assertEqual(info['type'], 'extension')

    def test_listContextInfos_with_ordering(self):
        from ..interfaces import BASE
        from ..interfaces import EXTENSION

        # three extension profiles
        profile_registry.registerProfile(
            'bar', 'bar', '', self._PROFILE_PATH, 'bar', EXTENSION)
        profile_registry.registerProfile(
            'foo', 'foo', '', self._PROFILE_PATH, 'foo', EXTENSION)
        profile_registry.registerProfile(
            'upper', 'UPPER', '', self._PROFILE_PATH, 'UPPER', EXTENSION)
        # one base profile
        profile_registry.registerProfile(
            'base', 'base', '', self._PROFILE_PATH, 'base', BASE)

        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool.createSnapshot('UPPER')
        tool.createSnapshot('lower')
        infos = tool.listContextInfos()
        self.assertEqual(len(infos), 6)
        # We sort case insensitively, so by lowercase.
        # First snapshots.
        self.assertEqual(infos[0]['id'], 'snapshot-lower')
        self.assertEqual(infos[1]['id'], 'snapshot-UPPER')
        # Then base and extension profiles
        self.assertEqual(infos[2]['id'], 'profile-bar:bar')
        self.assertEqual(infos[3]['id'], 'profile-base:base')
        self.assertEqual(infos[4]['id'], 'profile-foo:foo')
        self.assertEqual(infos[5]['id'], 'profile-UPPER:upper')

    def test_getProfileImportDate_nonesuch(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        self.assertEqual(tool.getProfileImportDate('nonesuch'), None)

    def test_getProfileImportDate_simple_id(self):
        from OFS.Image import File

        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        filename = 'import-all-foo-20070315123456.log'
        tool._setObject(filename, File(filename, '', b''))
        self.assertEqual(tool.getProfileImportDate('foo'),
                         '2007-03-15T12:34:56Z')

    def test_getProfileImportDate_id_with_colon(self):
        from OFS.Image import File

        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        filename = 'import-all-foo_bar-20070315123456.log'
        tool._setObject(filename, File(filename, '', b''))
        self.assertEqual(tool.getProfileImportDate('foo:bar'),
                         '2007-03-15T12:34:56Z')

    def test_getProfileImportDate_id_with_prefix(self):
        # Test if getProfileImportDate does not fail if there is another
        # item id with id with a longer id which starts with the same
        # prefix
        from OFS.Image import File

        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        filename = 'import-all-foo_bar-20070315123456.log'
        tool._setObject(filename, File(filename, '', b''))
        filename2 = 'import-all-foo_bar-boo-20070315123456.log'
        tool._setObject(filename2, File(filename2, '', b''))
        self.assertEqual(tool.getProfileImportDate('foo:bar'),
                         '2007-03-15T12:34:56Z')

    def test_profileVersioning(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        profile_id = 'dummy_profile'
        product_name = 'GenericSetup'
        directory = os.path.split(__file__)[0]
        path = os.path.join(directory, 'versioned_profile')

        # register profile
        profile_registry.registerProfile(profile_id,
                                         'Dummy Profile',
                                         'This is a dummy profile',
                                         path,
                                         product=product_name)

        # register upgrade step
        step = UpgradeStep("Upgrade",
                           "GenericSetup:dummy_profile", '*', '1.1', '',
                           dummy_upgrade,
                           None, "1")
        _registerUpgradeStep(step)

        # test initial states
        profile_id = ':'.join((product_name, profile_id))
        self.assertEqual(tool.getVersionForProfile(profile_id), '1.1')
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         'unknown')

        # run upgrade steps
        request = site.REQUEST
        request.form['profile_id'] = profile_id
        steps = listUpgradeSteps(tool, profile_id, '1.0')
        step_id = steps[0]['id']
        request.form['upgrades'] = [step_id]
        tool.manage_doUpgrades()
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         ('1', '1'))

    def test_get_and_setLastVersionForProfile(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        self.assertEqual(tool._profile_upgrade_versions, {})
        # Any 'profile-' is stripped off in these calls.
        self.assertEqual(tool.getLastVersionForProfile('foo'), 'unknown')
        self.assertEqual(tool.getLastVersionForProfile(
            'profile-foo'), 'unknown')
        tool.setLastVersionForProfile('foo', '1.0')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('1', '0'))
        self.assertEqual(tool.getLastVersionForProfile(
            'profile-foo'), ('1', '0'))
        tool.setLastVersionForProfile('profile-foo', '2.0')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('2', '0'))
        self.assertEqual(tool.getLastVersionForProfile(
            'profile-foo'), ('2', '0'))

        # Setting the profile to unknown, removes it from the versions.
        self.assertEqual(tool._profile_upgrade_versions, {'foo': ('2', '0')})
        tool.setLastVersionForProfile('profile-foo', 'unknown')
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_unsetLastVersionForProfile(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool.setLastVersionForProfile('foo', '1.0')
        tool.setLastVersionForProfile('bar', '2.0')
        self.assertEqual(tool._profile_upgrade_versions,
                         {'foo': ('1', '0'), 'bar': ('2', '0')})

        # Any 'profile-' is stripped off in these calls.
        tool.unsetLastVersionForProfile('profile-foo')
        self.assertEqual(tool._profile_upgrade_versions,
                         {'bar': ('2', '0')})
        tool.unsetLastVersionForProfile('bar')
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_purgeProfileVersions(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool.setLastVersionForProfile('foo', '1.0')
        tool.setLastVersionForProfile('bar', '2.0')
        self.assertEqual(tool._profile_upgrade_versions,
                         {'foo': ('1', '0'), 'bar': ('2', '0')})
        tool.purgeProfileVersions()
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_listProfilesWithUpgrades(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        self.assertEqual(tool.listProfilesWithUpgrades(), [])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), False)
        profile_id = 'dummy_profile'
        product_name = 'GenericSetup'
        directory = os.path.split(__file__)[0]
        path = os.path.join(directory, 'versioned_profile')

        # register profile
        profile_registry.registerProfile(profile_id,
                                         'Dummy Profile',
                                         'This is a dummy profile',
                                         path,
                                         product=product_name)
        self.assertEqual(tool.listProfilesWithUpgrades(), [])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), False)

        # register upgrade step
        step1 = UpgradeStep("Upgrade 1",
                            "GenericSetup:dummy_profile", '*', '1.1', '',
                            dummy_upgrade,
                            None, "1")
        _registerUpgradeStep(step1)
        self.assertEqual(tool.listProfilesWithUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), False)

        # register another upgrade step
        step2 = UpgradeStep("Upgrade 2",
                            "GenericSetup:dummy_profile", '1.1', '1.2', '',
                            dummy_upgrade,
                            None, "1")
        _registerUpgradeStep(step2)
        self.assertEqual(tool.listProfilesWithUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), False)

        # get full profile id
        profile_id = ':'.join((product_name, profile_id))

        # Pretend the profile was installed
        tool.setLastVersionForProfile(profile_id, '1.0')
        self.assertEqual(tool.listProfilesWithUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), True)

        # run first upgrade step
        request = site.REQUEST
        request.form['profile_id'] = profile_id
        steps = listUpgradeSteps(tool, profile_id, '1.0')
        step_id = steps[0]['id']
        request.form['upgrades'] = [step_id]
        tool.manage_doUpgrades()
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         ('1', '1'))
        self.assertEqual(tool.listProfilesWithUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), True)

        # run second upgrade step
        request = site.REQUEST
        request.form['profile_id'] = profile_id
        steps = listUpgradeSteps(tool, profile_id, '1.1')
        step_id = steps[0]['id']
        request.form['upgrades'] = [step_id]
        tool.manage_doUpgrades()
        self.assertEqual(tool.getLastVersionForProfile(profile_id),
                         ('1', '2'))
        self.assertEqual(tool.listProfilesWithUpgrades(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(),
                         [u'GenericSetup:dummy_profile'])
        self.assertEqual(tool.hasPendingUpgrades(), False)

        # Pretend the profile was never installed.
        tool.unsetLastVersionForProfile(profile_id)
        self.assertEqual(tool.listProfilesWithPendingUpgrades(), [])
        self.assertEqual(tool.listUptodateProfiles(), [])
        self.assertEqual(tool.hasPendingUpgrades(), False)

    def test_hasPendingUpgrades(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        profile_id_1 = 'dummy_profile1'
        profile_id_2 = 'dummy_profile2'
        product_name = 'GenericSetup'
        directory = os.path.split(__file__)[0]
        path = os.path.join(directory, 'versioned_profile')

        # register profiles
        profile_registry.registerProfile(profile_id_1,
                                         'Dummy Profile 1',
                                         'This is dummy profile 1',
                                         path,
                                         product=product_name)
        profile_registry.registerProfile(profile_id_2,
                                         'Dummy Profile 2',
                                         'This is dummy profile 2',
                                         path,
                                         product=product_name)

        # get full profile ids
        profile_id_1 = ':'.join((product_name, profile_id_1))
        profile_id_2 = ':'.join((product_name, profile_id_2))

        # test
        self.assertEqual(tool.hasPendingUpgrades(), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), False)
        self.assertEqual(tool.hasPendingUpgrades('non-existing'), False)

        # register upgrade steps
        step1 = UpgradeStep("Upgrade 1",
                            profile_id_1, '*', '1.1', '',
                            dummy_upgrade,
                            None, "1")
        _registerUpgradeStep(step1)
        step2 = UpgradeStep("Upgrade 2",
                            profile_id_2, '*', '2.2', '',
                            dummy_upgrade,
                            None, "2")
        _registerUpgradeStep(step2)
        # No profile has been applied, so no upgrade is pending.
        self.assertEqual(tool.hasPendingUpgrades(), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), False)

        # Pretend profile 1 was installed to an earlier version.
        tool.setLastVersionForProfile(profile_id_1, '1.0')
        self.assertEqual(tool.hasPendingUpgrades(), True)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), True)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), False)

        # Pretend profile 2 was installed to an earlier version.
        tool.setLastVersionForProfile(profile_id_2, '2.0')
        self.assertEqual(tool.hasPendingUpgrades(), True)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), True)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), True)

        # Pretend profile 1 was installed to the final version.
        tool.setLastVersionForProfile(profile_id_1, '1.1')
        self.assertEqual(tool.hasPendingUpgrades(), True)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), True)

        # Pretend profile 2 was installed to the final version.
        tool.setLastVersionForProfile(profile_id_2, '2.2')
        self.assertEqual(tool.hasPendingUpgrades(), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_1), False)
        self.assertEqual(tool.hasPendingUpgrades(profile_id_2), False)

    def test_manage_doUpgrades_no_profile_id_or_updates(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        tool.manage_doUpgrades()
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_manage_doUpgrades_upgrade_w_no_target_version(self):
        def notool():
            return None
        step = UpgradeStep('TITLE', 'foo', '*', '*', 'DESC', notool)
        _registerUpgradeStep(step)
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        request = site.REQUEST
        request['profile_id'] = ['foo']
        request['upgrade'] = [step.id]
        tool.manage_doUpgrades()
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_upgradeProfile_no_profile_id_or_updates(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        # Mostly this checks to see if we can call this without an
        # exception.
        tool.upgradeProfile('no.such.profile:default')
        self.assertEqual(tool._profile_upgrade_versions, {})
        tool.upgradeProfile('no.such.profile:default', dest='42')
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_persistent_profile_upgrade_versions(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        savepoint1 = transaction.savepoint()
        tool.setLastVersionForProfile('foo', '1.0')
        savepoint2 = transaction.savepoint()
        tool.setLastVersionForProfile('bar', '2.0')
        self.assertEqual(tool._profile_upgrade_versions,
                         {'foo': ('1', '0'), 'bar': ('2', '0')})
        savepoint2.rollback()
        self.assertEqual(tool._profile_upgrade_versions,
                         {'foo': ('1', '0')})
        savepoint1.rollback()
        self.assertEqual(tool._profile_upgrade_versions, {})

    def test_separate_profile_upgrade_versions(self):
        # _profile_upgrade_versions used to be a class property.  That is fine
        # as long as we only work on copies, otherwise state is shared between
        # two instances.  We now create the property in the __init__ method,
        # but let's test it to avoid a regression.
        site = self._makeSite()
        site.setup_tool1 = self._makeOne('setup_tool1')
        tool1 = site.setup_tool1
        site.setup_tool2 = self._makeOne('setup_tool2')
        tool2 = site.setup_tool2
        tool1._profile_upgrade_versions['foo'] = '1.0'
        self.assertEqual(tool2._profile_upgrade_versions, {})
        tool2.setLastVersionForProfile('bar', '2.0')
        self.assertEqual(self._makeOne('t')._profile_upgrade_versions, {})

    def test_upgradeProfile(self):
        def dummy_handler(tool):
            return None

        def step3_handler(tool):
            tool._step3_applied = 'just a marker'

        def step3_checker(tool):
            # False means already applied or does not apply.
            # True means can be applied.
            return not hasattr(tool, '_step3_applied')

        step1 = UpgradeStep('Step 1', 'foo', '0', '1', 'DESC',
                            dummy_handler)
        step2 = UpgradeStep('Step 2', 'foo', '1', '2', 'DESC',
                            dummy_handler)
        step3 = UpgradeStep('Step 3', 'foo', '2', '3', 'DESC',
                            step3_handler, checker=step3_checker)
        step4 = UpgradeStep('Step 4', 'foo', '3', '4', 'DESC',
                            dummy_handler)
        _registerUpgradeStep(step1)
        _registerUpgradeStep(step2)
        _registerUpgradeStep(step3)
        _registerUpgradeStep(step4)
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        self.assertEqual(tool.getLastVersionForProfile('foo'), 'unknown')
        tool.setLastVersionForProfile('foo', '0')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('0',))
        # Upgrade the profile one step to version 1.
        tool.upgradeProfile('foo', '1')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('1',))
        # Upgrade the profile two steps to version 3.  This one has a
        # checker.  The profile version must be correctly updated.
        tool.upgradeProfile('foo', '3')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('3',))
        # Upgrade the profile to a non existing version.  Nothing
        # should happen.
        tool.upgradeProfile('foo', '5')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('3',))
        # Upgrade the profile to the latest version.
        tool.upgradeProfile('foo')
        self.assertEqual(tool.getLastVersionForProfile('foo'), ('4',))

    def test_listExportSteps(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        result = tool.listExportSteps()
        self.assertEqual(len(result), 4)
        self.assertTrue(u'componentregistry' in result)
        self.assertTrue(u'rolemap' in result)
        self.assertTrue(u'step_registries' in result)
        self.assertTrue(u'toolset' in result)

        tool._export_registry.registerStep(u'foo', handler='foo.export')
        tool._export_registry.registerStep(u'toolset',
                                           handler='toolset.export')
        result = tool.listExportSteps()
        self.assertEqual(len(result), 5)
        self.assertTrue(u'componentregistry' in result)
        self.assertTrue(u'foo' in result)
        self.assertTrue(u'rolemap' in result)
        self.assertTrue(u'step_registries' in result)
        self.assertTrue(u'toolset' in result)

    def test_getSortedImportSteps(self):
        site = self._makeSite()
        site.setup_tool = self._makeOne('setup_tool')
        tool = site.setup_tool
        result = tool.getSortedImportSteps()
        self.assertEqual(len(result), 3)
        self.assertTrue(u'componentregistry' in result)
        self.assertTrue(u'rolemap' in result)
        self.assertTrue(u'toolset' in result)
        self.assertTrue(list(result).index(u'componentregistry') >
                        list(result).index(u'toolset'))

        tool._import_registry.registerStep(u'foo', handler='foo.import')
        tool._import_registry.registerStep(u'toolset',
                                           handler='toolset.import')
        result = tool.getSortedImportSteps()
        self.assertEqual(len(result), 4)
        self.assertTrue(u'componentregistry' in result)
        self.assertTrue(u'foo' in result)
        self.assertTrue(u'rolemap' in result)
        self.assertTrue(u'toolset' in result)
        self.assertTrue(list(result).index(u'componentregistry') >
                        list(result).index(u'toolset'))

    def test_listProfileInfo_for_parameter(self):
        from ..metadata import METADATA_XML

        self._makeFile(METADATA_XML, _METADATA_XML)

        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        profile_registry.registerProfile('foo', 'Foo', '', self._PROFILE_PATH,
                                         for_=ISite)
        # tool.listProfileInfo should call registry.listProfileInfo
        # with the for_ parameter
        self.assertEqual(len(tool.listProfileInfo()), 1)
        self.assertEqual(len(tool.listProfileInfo(for_=ISite)), 1)
        self.assertEqual(len(tool.listProfileInfo(for_=IDerivedSite)), 1)
        self.assertEqual(len(tool.listProfileInfo(for_=IAnotherSite)), 0)

    def test_profileExists(self):
        from ..interfaces import EXTENSION
        from ..metadata import METADATA_XML
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        # Register two extension profiles.  Profile 'foo' has a dependency
        # 'bar'.
        self._makeFile(METADATA_XML, _METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH2, _PLAIN_METADATA_XML)
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'bar', 'Bar', '', self._PROFILE_PATH2, profile_type=EXTENSION)

        self.assertTrue(tool.profileExists('other:foo'))
        self.assertTrue(tool.profileExists('other:bar'))
        self.assertFalse(tool.profileExists('snapshot-something'))
        self.assertFalse(tool.profileExists(None))
        self.assertFalse(tool.profileExists('nonesuch'))

    def test_getDependenciesForProfile(self):
        from ..interfaces import EXTENSION
        from ..metadata import METADATA_XML
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        # Register three extension profiles.  Profile 'foo' has a dependency
        # 'bar', and 'baz' contains non-existing dependency-profiles.
        self._makeFile(METADATA_XML, _METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH2, _PLAIN_METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH3, _BROKEN_METADATA_XML)
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'bar', 'Bar', '', self._PROFILE_PATH2, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'baz', 'Baz', '', self._PROFILE_PATH3, profile_type=EXTENSION)

        self.assertEqual(tool.getDependenciesForProfile('other:foo'),
                         (u'profile-other:bar', ))
        self.assertEqual(tool.getDependenciesForProfile('other:bar'), ())
        self.assertEqual(tool.getDependenciesForProfile('snapshot-some'), ())
        self.assertEqual(tool.getDependenciesForProfile(None), ())
        self.assertRaises(KeyError, tool.getDependenciesForProfile, 'nonesuch')

    def test_getBrokenDependencies(self):
        from ..interfaces import EXTENSION
        from ..metadata import METADATA_XML
        site = self._makeSite()
        tool = self._makeOne('setup_tool').__of__(site)

        # Register three extension profiles.  Profile 'foo' has a dependency
        # 'bar' and 'baz' contains non-existing dependency-profiles.
        self._makeFile(METADATA_XML, _METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH2, _PLAIN_METADATA_XML)
        _makeTestFile(METADATA_XML, self._PROFILE_PATH3, _BROKEN_METADATA_XML)
        profile_registry.registerProfile(
            'foo', 'Foo', '', self._PROFILE_PATH, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'bar', 'Bar', '', self._PROFILE_PATH2, profile_type=EXTENSION)
        profile_registry.registerProfile(
            'baz', 'Baz', '', self._PROFILE_PATH3, profile_type=EXTENSION)

        # profile has dependencies and none of them is broken:
        self.assertFalse(tool.hasBrokenDependencies('other:foo'))
        # profile has no dependencies, therfore nothing can be broken:
        self.assertFalse(tool.hasBrokenDependencies('other:bar'))
        # profile has dependencies and at least one of them is broken:
        self.assertTrue(tool.hasBrokenDependencies('other:baz'))


_DEFAULT_STEP_REGISTRIES_EXPORT_XML = ("""\
<?xml version="1.0"?>
<export-steps>
 <export-step id="step_registries"
              handler="Products.GenericSetup.tool.exportStepRegistries"
              title="Export import / export steps.">
""" + "  " + """
 </export-step>
</export-steps>
""")

_EXTRAS_STEP_REGISTRIES_EXPORT_XML = """\
<?xml version="1.0"?>
<export-steps>
 <export-step
    id="properties"
    handler="Products.GenericSetup.tests.test_tool._exportPropertiesINI"
    title="properties">

 </export-step>
 <export-step
    id="step_registries"
    handler="Products.GenericSetup.tool.exportStepRegistries"
    title="Export import / export steps.">

 </export-step>
</export-steps>
"""

_DEFAULT_STEP_REGISTRIES_IMPORT_XML = ("""\
<?xml version="1.0"?>
<import-steps>
 <import-step id="foo" handler="foo.bar" title="foo">
""" + "  " + """
 </import-step>
</import-steps>
""")

_EXTRAS_STEP_REGISTRIES_IMPORT_XML = """\
<?xml version="1.0"?>
<import-steps>
 <import-step
    id="dependable"
    version="1"
    handler="Products.GenericSetup.tests.test_tool._underscoreSiteTitle"
    title="dependable">
  <dependency step="purging" />

 </import-step>
 <import-step
    id="dependent"
    version="1"
    handler="Products.GenericSetup.tests.test_tool._uppercaseSiteTitle"
    title="dependent">
  <dependency step="dependable" />

 </import-step>
 <import-step
    id="purging"
    version="1"
    handler="Products.GenericSetup.tests.test_tool._purgeIfRequired"
    title="purging">

 </import-step>
</import-steps>
"""

_PROPERTIES_INI = """\
[Default]
Title=%s
"""


def _underscoreSiteTitle(context):

    site = context.getSite()
    site.title = site.title.replace(' ', '_')
    return 'Underscored title'


def _uppercaseSiteTitle(context):

    site = context.getSite()
    site.title = site.title.upper()
    return 'Uppercased title'


def _purgeIfRequired(context):

    site = context.getSite()
    purged = site.purged = context.shouldPurge()
    return purged and 'Purged' or 'Unpurged'


def _exportPropertiesINI(context):

    site = context.getSite()
    text = _PROPERTIES_INI % site.title

    context.writeDataFile('properties.ini', text.encode('utf-8'), 'text/plain')

    return 'Exported properties'


class _ToolsetSetup(BaseRegistryTests):

    def _initSite(self):
        from ..tool import SetupTool

        site = Folder()
        site._setId('site')
        self.app._setObject('site', site)
        site = self.app._getOb('site')
        site._setObject('setup_tool', SetupTool('setup_tool'))
        return site


class Test_exportToolset(_ToolsetSetup):

    layer = ExportImportZCMLLayer

    def test_empty(self):
        from ..tool import TOOLSET_XML
        from ..tool import exportToolset

        site = self._initSite()
        context = DummyExportContext(site, tool=site.setup_tool)

        exportToolset(context)

        self.assertEqual(len(context._wrote), 1)
        filename, text, content_type = context._wrote[0]
        self.assertEqual(filename, TOOLSET_XML)
        self._compareDOM(text.decode('utf-8'), _EMPTY_TOOLSET_XML)
        self.assertEqual(content_type, 'text/xml')

    def test_normal(self):
        from ..tool import TOOLSET_XML
        from ..tool import exportToolset

        site = self._initSite()
        toolset = site.setup_tool.getToolsetRegistry()
        toolset.addForbiddenTool('doomed')
        toolset.addRequiredTool('mandatory', 'path.to.one')
        toolset.addRequiredTool('obligatory', 'path.to.another')

        context = DummyExportContext(site, tool=site.setup_tool)

        exportToolset(context)

        self.assertEqual(len(context._wrote), 1)
        filename, text, content_type = context._wrote[0]
        self.assertEqual(filename, TOOLSET_XML)
        self._compareDOM(text.decode('utf-8'), _NORMAL_TOOLSET_XML)
        self.assertEqual(content_type, 'text/xml')


class Test_importToolset(_ToolsetSetup):

    layer = ExportImportZCMLLayer

    def test_import_updates_registry(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()
        context = DummyImportContext(site, tool=site.setup_tool)

        # Import forbidden
        context._files[TOOLSET_XML] = _FORBIDDEN_TOOLSET_XML
        importToolset(context)

        tool = context.getSetupTool()
        toolset = tool.getToolsetRegistry()

        self.assertEqual(len(toolset.listForbiddenTools()), 3)
        self.assertTrue('doomed' in toolset.listForbiddenTools())
        self.assertTrue('damned' in toolset.listForbiddenTools())
        self.assertTrue('blasted' in toolset.listForbiddenTools())

        # Import required
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML
        importToolset(context)

        self.assertEqual(len(toolset.listRequiredTools()), 2)
        self.assertTrue('mandatory' in toolset.listRequiredTools())
        info = toolset.getRequiredToolInfo('mandatory')
        self.assertEqual(info['class'],
                         'Products.GenericSetup.tests.test_tool.DummyTool')
        self.assertTrue('obligatory' in toolset.listRequiredTools())
        info = toolset.getRequiredToolInfo('obligatory')
        self.assertEqual(info['class'],
                         'Products.GenericSetup.tests.test_tool.DummyTool')

    def test_tool_ids(self):
        # The tool import mechanism used to rely on the fact that all tools
        # have unique IDs set at the class level and that you can call their
        # constructor with no arguments. However, there might be tools
        # that need IDs set.
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()
        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML

        importToolset(context)

        for tool_id in ('mandatory', 'obligatory'):
            tool = getattr(site, tool_id)
            self.assertEqual(tool.getId(), tool_id)

    def test_tool_id_required(self):
        # Tests that tool creation will still work when an id is required
        # by the tool constructor.
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()
        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _WITH_ID_TOOLSET_XML

        importToolset(context)

        for tool_id in ('mandatory', 'requires_id', 'immutable_id'):
            tool = getattr(site, tool_id)
            self.assertEqual(tool.getId(), tool_id)

    def test_forbidden_tools(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        TOOL_IDS = ('doomed', 'blasted', 'saved')

        site = self._initSite()

        for tool_id in TOOL_IDS:
            pseudo = Folder()
            pseudo._setId(tool_id)
            site._setObject(tool_id, pseudo)

        self.assertEqual(len(site.objectIds()), len(TOOL_IDS) + 1)

        for tool_id in TOOL_IDS:
            self.assertTrue(tool_id in site.objectIds())

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _FORBIDDEN_TOOLSET_XML

        importToolset(context)

        self.assertEqual(len(site.objectIds()), 2)
        self.assertTrue('setup_tool' in site.objectIds())
        self.assertTrue('saved' in site.objectIds())

    def test_required_tools_missing(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()
        self.assertEqual(len(site.objectIds()), 1)

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML

        importToolset(context)

        self.assertEqual(len(site.objectIds()), 3)
        self.assertTrue(isinstance(
            aq_base(site._getOb('mandatory')), DummyTool))
        self.assertTrue(isinstance(
            aq_base(site._getOb('obligatory')), DummyTool))

    def test_required_tools_no_replacement(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()

        mandatory = DummyTool()
        mandatory._setId('mandatory')
        site._setObject('mandatory', mandatory)

        obligatory = DummyTool()
        obligatory._setId('obligatory')
        site._setObject('obligatory', obligatory)

        self.assertEqual(len(site.objectIds()), 3)

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML

        importToolset(context)

        self.assertEqual(len(site.objectIds()), 3)
        self.assertTrue(aq_base(site._getOb('mandatory')) is mandatory)
        self.assertTrue(aq_base(site._getOb('obligatory')) is obligatory)

    def test_required_tools_with_replacement(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()

        mandatory = AnotherDummyTool()
        mandatory._setId('mandatory')
        site._setObject('mandatory', mandatory)

        obligatory = SubclassedDummyTool()
        obligatory._setId('obligatory')
        site._setObject('obligatory', obligatory)

        self.assertEqual(len(site.objectIds()), 3)

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML

        importToolset(context)

        self.assertEqual(len(site.objectIds()), 3)

        self.assertFalse(aq_base(site._getOb('mandatory')) is mandatory)
        self.assertTrue(isinstance(
            aq_base(site._getOb('mandatory')), DummyTool))

        self.assertFalse(aq_base(site._getOb('obligatory')) is obligatory)
        self.assertTrue(isinstance(
            aq_base(site._getOb('obligatory')), DummyTool))

    def test_required_tools_missing_acquired_nofail(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()
        parent_site = Folder()

        mandatory = AnotherDummyTool()
        mandatory._setId('mandatory')
        parent_site._setObject('mandatory', mandatory)

        obligatory = AnotherDummyTool()
        obligatory._setId('obligatory')
        parent_site._setObject('obligatory', obligatory)

        site = site.__of__(parent_site)

        # acquiring subobjects of a different class during import
        # should not prevent new objects from being created if they
        # don't exist in the site

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _REQUIRED_TOOLSET_XML

        importToolset(context)

        self.assertFalse(aq_base(site._getOb('mandatory')) is mandatory)
        self.assertTrue(isinstance(
            aq_base(site._getOb('mandatory')), DummyTool))

        self.assertFalse(aq_base(site._getOb('obligatory')) is obligatory)
        self.assertTrue(isinstance(
            aq_base(site._getOb('obligatory')), DummyTool))

    def test_required_tools_missing_class_with_replacement(self):
        from ..tool import TOOLSET_XML
        from ..tool import importToolset

        site = self._initSite()

        obligatory = AnotherDummyTool()
        obligatory._setId('obligatory')
        site._setObject('obligatory', obligatory)

        self.assertEqual(len(site.objectIds()), 2)

        context = DummyImportContext(site, tool=site.setup_tool)
        context._files[TOOLSET_XML] = _BAD_CLASS_TOOLSET_XML

        importToolset(context)

        self.assertEqual(len(site.objectIds()), 2)


class DummyTool(Folder):

    pass


class AnotherDummyTool(Folder):

    pass


class SubclassedDummyTool(DummyTool):

    pass


class DummyToolRequiresId(Folder):

    def __init__(self, id):
        Folder.__init__(self)
        self._setId(id)


class DummyToolImmutableId(Folder):

    id = 'immutable_id'

    def _setId(self, id):
        if id != self.getId():
            raise ValueError()


def pre_handler(tool):
    try:
        tool.pre_handler_called += 1
    except AttributeError:
        tool.pre_handler_called = 1


def post_handler(tool):
    try:
        tool.post_handler_called += 1
    except AttributeError:
        tool.post_handler_called = 1


_FORBIDDEN_TOOLSET_XML = """\
<?xml version="1.0"?>
<tool-setup>
 <forbidden tool_id="doomed" />
 <forbidden tool_id="damned" />
 <forbidden tool_id="blasted" />
</tool-setup>
"""

_REQUIRED_TOOLSET_XML = """\
<?xml version="1.0"?>
<tool-setup>
 <required
    tool_id="mandatory"
    class="Products.GenericSetup.tests.test_tool.DummyTool" />
 <required
    tool_id="obligatory"
    class="Products.GenericSetup.tests.test_tool.DummyTool" />
</tool-setup>
"""

_WITH_ID_TOOLSET_XML = """\
<?xml version="1.0"?>
<tool-setup>
  <required
    tool_id="mandatory"
    class="Products.GenericSetup.tests.test_tool.DummyTool" />
  <required
    tool_id="requires_id"
    class="Products.GenericSetup.tests.test_tool.DummyToolRequiresId" />
  <required
    tool_id="immutable_id"
    class="Products.GenericSetup.tests.test_tool.DummyToolImmutableId" />
</tool-setup>
"""

_BAD_CLASS_TOOLSET_XML = """\
<?xml version="1.0"?>
<tool-setup>
 <required
    tool_id="obligatory"
    class="foobar" />
</tool-setup>
"""


def test_suite():
    return unittest.TestSuite((
        unittest.makeSuite(SetupToolTests),
        unittest.makeSuite(Test_exportToolset),
        unittest.makeSuite(Test_importToolset),
    ))
