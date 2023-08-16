# -*- coding: utf-8 -*-
"""Installer for the collective.task package."""

from setuptools import find_packages
from setuptools import setup


long_description = (
    open('README.rst').read()
    + '\n' +
    'Contributors\n'
    '============\n'
    + '\n' +
    open('CONTRIBUTORS.rst').read()
    + '\n' +
    open('CHANGES.rst').read()
    + '\n')


setup(
    name='collective.task',
    version='3.0.9.dev0',
    description="Tasks management for Plone.",
    long_description=long_description,
    # Get more from http://pypi.python.org/pypi?%3Aaction=list_classifiers
    classifiers=[
        "Environment :: Web Environment",
        "Framework :: Plone",
        "Framework :: Plone :: 4.3",
        "Programming Language :: Python",
        "Programming Language :: Python :: 2.7",
    ],
    keywords='Plone Python',
    author='Cédric Messiant',
    author_email='cedricmessiant@ecreall.com',
    url='http://pypi.python.org/pypi/collective.task',
    license='GPL',
    packages=find_packages('src', exclude=['ez_setup']),
    namespace_packages=['collective'],
    package_dir={'': 'src'},
    include_package_data=True,
    zip_safe=False,
    install_requires=[
        'dexterity.localrolesfield',
        'plone.api',
        'plone.app.lockingbehavior',
        'plone.directives.form',
        'plone.formwidget.masterselect',
        'plone.principalsource',
        'future',
        'imio.helpers',
        'imio.migrator',
        'setuptools',
        'z3c.table>=2.2',
    ],
    extras_require={
        'test': [
            'collective.eeafaceted.batchactions',
            'imio.prettylink',
            'plone.app.testing',
            'plone.app.contenttypes',
            'plone.app.robotframework[debug]',
        ],
    },
    entry_points="""
    [z3c.autoinclude.plugin]
    target = plone
    """,
)
