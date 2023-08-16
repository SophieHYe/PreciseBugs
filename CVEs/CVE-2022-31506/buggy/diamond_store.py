#
#  The OpenDiamond Platform for Interactive Search
#
#  Copyright (c) 2009-2018 Carnegie Mellon University
#  All rights reserved.
#
#  This software is distributed under the terms of the Eclipse Public
#  License, Version 1.0 which can be found in the file named LICENSE.
#  ANY USE, REPRODUCTION OR DISTRIBUTION OF THIS SOFTWARE CONSTITUTES
#  RECIPIENT'S ACCEPTANCE OF THIS AGREEMENT
#

import os
import datetime
from xml.sax.saxutils import quoteattr

from flask import Blueprint, url_for, Response, stream_with_context, send_file, \
    jsonify
from werkzeug.datastructures import Headers

from opendiamond.dataretriever.util import ATTR_SUFFIX

BASEURL = 'collection'
STYLE = False
LOCAL_OBJ_URI = True  # if true, return local file path, otherwise http.
INDEXDIR = DATAROOT = None


def init(config):
    global INDEXDIR, DATAROOT  # pylint: disable=global-statement
    INDEXDIR = config.indexdir
    DATAROOT = config.dataroot


scope_blueprint = Blueprint('diamond_store', __name__)


@scope_blueprint.route('/<gididx>')
@scope_blueprint.route('/<gididx>/limit/<int:limit>')
def get_scope(gididx, limit=None):
    index = 'GIDIDX' + gididx.upper()
    index = _get_index_absolute_path(index)

    # Streaming response:
    # http://flask.pocoo.org/docs/0.12/patterns/streaming/
    def generate():
        num_entries = 0
        with open(index, 'r') as f:
            for _ in f.readlines():
                num_entries += 1
                if limit is not None and num_entries >= limit:
                    break

        with open(index, 'r') as f:
            yield '<?xml version="1.0" encoding="UTF-8" ?>\n'
            if STYLE:
                yield '<?xml-stylesheet type="text/xsl" href="/scopelist.xsl" ?>\n'

            yield '<objectlist count="{:d}">\n'.format(num_entries)
            
            count = 0
            for path in f.readlines():
                path = path.strip()
                yield _get_object_element(object_path=path) + '\n'
                count += 1
                if limit is not None and count >= limit:
                    break

            yield '</objectlist>\n'

    headers = Headers([('Content-Type', 'text/xml')])

    return Response(stream_with_context(generate()),
                    status="200 OK",
                    headers=headers)


@scope_blueprint.route('/id/<path:object_path>')
def get_object_id(object_path):
    headers = Headers([('Content-Type', 'text/xml')])
    return Response(_get_object_element(object_path=object_path),
                    "200 OK",
                    headers=headers)


@scope_blueprint.route('/meta/<path:object_path>')
def get_object_meta(object_path):
    path = _get_obj_absolute_path(object_path)
    attrs = dict()

    try:
        with DiamondTextAttr(path, 'r') as attributes:
            for key, value in attributes:
                attrs[key] = value
    except IOError:
        pass

    return jsonify(attrs)


def _get_object_element(object_path):
    path = _get_obj_absolute_path(object_path)

    if os.path.isfile(path + ATTR_SUFFIX):
        return '<object id={} src={} meta={} />' \
            .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                    quoteattr(_get_object_src_uri(object_path)),
                    quoteattr(url_for('.get_object_meta', object_path=object_path)))
    else:
        return '<object id={} src={} />' \
            .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                    quoteattr(_get_object_src_uri(object_path)))


def _get_object_src_uri(object_path):
    if LOCAL_OBJ_URI:
        return 'file://' + _get_obj_absolute_path(object_path)
    else:
        return url_for('.get_object_src_http', obj_path=object_path)


def _get_obj_absolute_path(obj_path):
    return os.path.join(DATAROOT, obj_path)


def _get_index_absolute_path(index):
    return os.path.join(INDEXDIR, index)


@scope_blueprint.route('/obj/<path:obj_path>')
def get_object_src_http(obj_path):
    path = _get_obj_absolute_path(obj_path)

    headers = Headers()
    # With add_etags=True, conditional=True
    # Flask should be smart enough to do 304 Not Modified
    response = send_file(path,
                         cache_timeout=datetime.timedelta(
                             days=365).total_seconds(),
                         add_etags=True,
                         conditional=True)
    response.headers.extend(headers)
    return response
