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

from builtins import next
from builtins import range
import os
import datetime
from xml.sax.saxutils import quoteattr
import sys

import logging
import random
import glob
from itertools import cycle
from flask import Blueprint, url_for, Response, stream_with_context, send_file, \
    jsonify
from werkzeug.datastructures import Headers
from werkzeug.utils import safe_join
from opendiamond.dataretriever.util import read_file_list, write_data


BASEURL = 'augment'
STYLE = False
LOCAL_OBJ_URI = True  # if true, return local file path, otherwise http.
INDEXDIR = DATAROOT = None
ITEMS_PER_ITERATION = int(1e4)
KEYWORD = 'yellowthroat'

"""
    Example url:
        /augment/root/<ROOT_DIR>/distributed/<id>of<N>/ \
            keywords/<d/r ([d]eterminant/[r]andom)>_<random_seed>_<base_rate>

        /augment/root/STREAM/distributed/1of2/keywords/d_42_1.0
"""


def init(config):
    global INDEXDIR, DATAROOT  # pylint: disable=global-statement
    INDEXDIR = 'STREAM'
    DATAROOT = config.dataroot


scope_blueprint = Blueprint('augment_store', __name__)

_log = logging.getLogger(__name__)

@scope_blueprint.route('/root/<rootdir>/distributed/<int:index>of<int:total>' +
                        '/keywords/<params>')
@scope_blueprint.route('/root/<rootdir>/keywords/<params>')
@scope_blueprint.route('/root/<rootdir>/distributed/<int:index>of<int:total>' +
                        '/keywords/<params>/start/<int:start>/limit/<int:limit>')
@scope_blueprint.route('/root/<rootdir>/keywords/<params>' +
                        '/start/<int:start>/limit/<int:limit>')
def get_scope(rootdir, index=0, total=1, params=None, start=0, limit=sys.maxsize):
    global KEYWORD
    if rootdir == "0":
        rootdir = INDEXDIR

    rootdir = _get_obj_absolute_path(rootdir)
    seed = None
    percentage = 0.
    seed, percentage = decode_params(params)

    # Assuming the same positive list is present in all the servers
    # Always create a new index file
    base_list, KEYWORD = create_index(rootdir, percentage, seed, index, total)
    total_entries = len(base_list)

    start = start if start > 0 else 0
    end = min(total_entries, start + limit) if limit > 0 else total_entries
    base_list = base_list[start:end]
    total_entries = end - start

    def generate():
        yield '<?xml version="1.0" encoding="UTF-8" ?>\n'
        if STYLE:
            yield '<?xml-stylesheet type="text/xsl" href="/scopelist.xsl" ?>\n'

        yield '<objectlist count="{:d}">\n'.format(total_entries)

        for path in base_list:
            path = path.strip()
            yield _get_object_element(object_path=path) + '\n'

        yield '</objectlist>\n'

    headers = Headers([('Content-Type', 'text/xml')])

    return Response(stream_with_context(generate()),
                    status="200 OK",
                    headers=headers)

def decode_params(params):
    """
    Decodes the params which are '_' seperated
    <[d]eterminant/[r]andom>_<random_seed>_<baserate>
    """
    keywords = params.split('_')
    mix_type = keywords[0]
    seed = None
    if len(keywords) > 1:
        seed = int(keywords[1])
    if mix_type == 'r' or seed is None:
        seed = random.randrange(10000)
    percentage = 0.1 # default base_rate = 0.1%
    if len(keywords) > 2:
        percentage = float(keywords[2])
    return seed, round(percentage, 4)

@scope_blueprint.route('/id/<path:object_path>')
def get_object_id(object_path):
    headers = Headers([('Content-Type', 'text/xml')])
    return Response(_get_object_element(object_path=object_path),
                    "200 OK",
                    headers=headers)

def _get_object_element(object_path):
    path = _get_obj_absolute_path(object_path)
    meta = {'_gt_label': KEYWORD}
    if KEYWORD in path:
        return '<object id={} src={} meta={} />' \
                .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                        quoteattr(_get_object_src_uri(object_path)),
                        quoteattr(url_for('.get_object_meta', present=True)))

    return '<object id={} src={} />' \
            .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                    quoteattr(_get_object_src_uri(object_path)))


@scope_blueprint.route('/meta/<path:present>')
def get_object_meta(present=False):
    attrs = dict()
    if present:
        attrs['_gt_label'] = KEYWORD

    return jsonify(attrs)

def _get_object_src_uri(object_path):
    if LOCAL_OBJ_URI:
        return 'file://' + _get_obj_absolute_path(object_path)

    return url_for('.get_object_src_http', obj_path=object_path)

def _get_obj_absolute_path(obj_path):
    return safe_join(DATAROOT, obj_path)

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

def create_index(base_dir, base_rate=0.05, seed=42, rank=0, total_servers=1):
    """
    Creates Index List File:
    Assuming name of files NEGATIVE (e.g:subset YFCC), POSITIVE
    """

    filepath_split = ['STREAM', "{:.2f}".format(base_rate), str(rank), str(total_servers), str(seed)]
    filepath = '_'.join(filepath_split)
    filepath = os.path.join(base_dir, filepath)
    positive_path = os.path.join(base_dir, 'POSITIVE')
    negative_path = os.path.join(base_dir, 'NEGATIVE')
    positive_firstline = open(positive_path).readline().rstrip()
    keyword = positive_firstline.split('/')[-2] # Assuming all positives are in the same parent dir

    _log.info("Dir {} BR: {} Seed:{} FP{}".format(base_dir, base_rate, seed, filepath))
    sys.stdout.flush()

    if not os.path.exists(filepath):
        positive_data = read_file_list(positive_path) # same across servers
        negative_data = read_file_list(negative_path) # different across servers
        random.Random(seed).shuffle(positive_data)
        random.Random(seed).shuffle(negative_data)
        len_positive = len(positive_data)
        start_idx = int(rank * (1.0 / total_servers) * len_positive)
        end_idx = int((rank+1) * (1.0 / total_servers) * len_positive)
        positive_data = positive_data[start_idx:end_idx]
        len_positive = len(positive_data)
        negative_sample = int(len_positive * (100./base_rate -1))
        negative_data = negative_data[:negative_sample]
        return write_data(filepath, [negative_data, positive_data], seed), keyword

    return read_file_list(filepath), keyword
