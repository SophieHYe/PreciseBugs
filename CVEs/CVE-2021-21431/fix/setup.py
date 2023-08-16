"""Set up the package for the plugin."""

from setuptools import find_packages, setup

with open('README.md') as readme_file:
    readme = readme_file.read()
with open('requirements.txt') as requirements_file:
    requirements = list(requirements_file.readlines())


setup(
    name='sopel_plugins.channelmgnt',
    version='2.0.1',
    description='Channelmgnt plugin for Sopel',
    long_description=readme,
    long_description_content_type='text/markdown',  # This is important!
    author='MirahezeBot Contributors',
    author_email='staff@mirahezebots.org',
    url='https://github.com/MirahezeBots/sopel-channelmgnt',
    packages=find_packages('.'),
    include_package_data=True,
    install_requires=requirements,
    license='Eiffel Forum License, version 2',
)
