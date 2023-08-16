# -*- coding: utf-8 -*-

# Imports ###########################################################

import os
from setuptools import setup


# Functions #########################################################

def package_data(pkg, root_list):
    """Generic function to find package_data for `pkg` under `root`."""
    data = []
    for root in root_list:
        for dirname, _, files in os.walk(os.path.join(pkg, root)):
            for fname in files:
                data.append(os.path.relpath(os.path.join(dirname, fname), pkg))

    return {pkg: data}


# Main ##############################################################

setup(
    name='xblock-drag-and-drop-v2',
    version='2.7.0',
    description='XBlock - Drag-and-Drop v2',
    packages=['drag_and_drop_v2'],
    install_requires=[
        'XBlock',
        'xblock-utils',
        'bleach',
    ],
    entry_points={
        'xblock.v1': 'drag-and-drop-v2 = drag_and_drop_v2:DragAndDropBlock',
    },
    package_data=package_data("drag_and_drop_v2", ["static", "templates", "public", "translations", "locale"]),
)
