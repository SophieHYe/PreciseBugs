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
from opendiamond.dataretriever.test_utils import *


BASEURL = 'cocktailtest'
STYLE = False
LOCAL_OBJ_URI = True  # if true, return local file path, otherwise http.
INDEXDIR = DATAROOT = None
ITEMS_PER_ITERATION = int(1e4)

"""
    Example cocktail url:
        /cocktail/base/FFFFFFFFFFFFFFFF/distrbuted/1of2/ \
            mixers/FFFFFFFFFFFFFFFC/keywords/d_42_1.0/classes/gull,cardinal
        /cocktail/base/"0"/mixers/FFFFFFFFFFFFFFFC/keywords/d_42_1.0
        /cocktail/base/FFFFFFFFFFFFFFFF/keywords/d_42_1.0
"""


def init(config):
    global INDEXDIR, DATAROOT  # pylint: disable=global-statement
    INDEXDIR = '/srv/diamond/STREAM'
    DATAROOT = config.dataroot


scope_blueprint = Blueprint('test_store', __name__)

_log = logging.getLogger(__name__)

@scope_blueprint.route('/base/<baseidx>/mixers/<mixeridx>/keywords/<params>')
@scope_blueprint.route('/base/<baseidx>/mixers/<mixeridx>/keywords/<params>/' +
                        'classes/<classes>')
@scope_blueprint.route('/base/<baseidx>/mixers/<mixeridx>/keywords/<params>/' +
                        'classes/<classes>/start/<int:start>/limit/<int:limit>')
@scope_blueprint.route('/base/<baseidx>/distrbuted/<int:index>of<int:total>/' +
                        'mixers/<mixeridx>/keywords/<params>')
@scope_blueprint.route('/base/<baseidx>/distrbuted/<int:index>of<int:total>/' +
                        'mixers/<mixeridx>/keywords/<params>/classes/<classes>')
def get_mixer_classes(baseidx, mixeridx=None, index=1, total=1, params=None, 
                      classes=None, start=0, limit=-1):

    mixer_list = get_mixer_list(mixeridx, classes)
    start_idx = int((index-1)*(1.0/total)*len(mixer_list))
    end_idx = int(index*(1.0/total)*len(mixer_list))
    mixer_list = mixer_list[start_idx:end_idx]
    _log.info("Mixer Size {}".format(len(mixer_list)))
    sys.stdout.flush()

    return get_scope(baseidx, params, mixer_list, start, limit)

@scope_blueprint.route('/base/<baseidx>/keywords/<params>')
@scope_blueprint.route('/base/<baseidx>/keywords/<params>/start/<int:start>/limit/<int:limit>')
@scope_blueprint.route('/base/<baseidx>/start/<int:start>/limit/<int:limit>')
def get_scope(baseidx, params=None, mixer_list=None, start=0, limit=-1):
    _log.info("Enter Scope baseIdx {}".format(baseidx))
    sys.stdout.flush()
    base_list = []
    seed = None
    percentage = 0.
    if params:
        seed, percentage = decode_params(params)
    s_seed = seed
    if s_seed == None:
        s_seed = random.randrange(10000)
    if baseidx != "0":
        # format of baseidx: stream_inat 
        # format of base file: stream_{int: seed}_{float(.2f): baserate} 
        base_index = _get_index_absolute_path(baseidx)
        data_type, pos_file = base_index.split('_')
        # index[-1] = str("{:.2f}".format(index[-1])) # to ensure there is exactly two decial places
        # if seed != s_seed:
        #     index[1] = str(s_seed)
        #     base_index = '_'.join(index)
        base_list = [data_type, str(s_seed), pos_file, "{:.2f}".format(percentage)]
        base_index = '_'.join(base_list)
        print(base_index)
        if not os.path.exists(base_index):
            split_data(INDEXDIR, percentage, s_seed)
        #base_index = base_index.replace(str(seed),str(s_seed))

        with open(base_index, 'r') as f:
            base_list = list(f.readlines())
        if start > 0:
            base_list = base_list[start:]
        if limit > 0:
            end_ = len(base_list)
            if limit > end_:
                limit = end_
            base_list = base_list[:limit]
        total_entries = len(base_list)


    make_cocktail = bool(mixer_list and base_list)
        
    if base_list:
        total_entries = len(base_list)  #base_entries
    else:
        total_entries = len(mixer_list)
        base_list = mixer_list.copy()
        del mixer_list

    random.seed(seed)
    #random.Random(seed).shuffle(base_list)

    total_sample = 0 
    if make_cocktail:
        random.Random(seed).shuffle(mixer_list)
        total_sample = int(percentage*total_entries)
        total_entries = total_entries + total_sample

    # Streaming response:
    # http://flask.pocoo.org/docs/0.12/patterns/streaming/
    def generate():

        yield '<?xml version="1.0" encoding="UTF-8" ?>\n'
        if STYLE:
            yield '<?xml-stylesheet type="text/xsl" href="/scopelist.xsl" ?>\n'

        yield '<objectlist count="{:d}">\n'.format(total_entries)

        mix_per_iteration = 0
        iteration_count = 0
        if make_cocktail:
            mix_per_iteration = int(percentage * ITEMS_PER_ITERATION)
            pool = cycle(mixer_list)

        mix_indices = []

        def generate_mix_indices():
            random.seed(seed)
            return list(map(lambda x: x + ITEMS_PER_ITERATION*iteration_count,
                    sorted(random.sample(list(range(ITEMS_PER_ITERATION)), mix_per_iteration))))

        for count in range(total_entries):
            if not count % ITEMS_PER_ITERATION and make_cocktail:
                mix_indices = generate_mix_indices()
                iteration_count += 1

            if count in mix_indices:
                obj_path = next(pool).strip()
            else:
                obj_path = base_list.pop(0).strip()
            yield _get_object_element(object_path=obj_path) + '\n'

        yield '</objectlist>\n'

    headers = Headers([('Content-Type', 'text/xml')])

    return Response(stream_with_context(generate()),
                    status="200 OK",
                    headers=headers)

def decode_params(params):
    keywords = params.split('_')
    m_type = keywords[0]
    seed = int(keywords[1])
    percentage = 0.1
    if m_type == 'r':
        seed = None
    if len(keywords) > 2:
        percentage = float(keywords[2]) #float(keywords[2])/100.
    return seed, round(percentage, 4)

def get_mixer_list(idx, classes=None):
    """
    Return list of file paths present in given classes of mixer set
    If class list is None or incorrect then return list of entire mixer set.

    Args:
        classes (str): Comma seperated classes of interest
        idx     (str): Index of mixer collection

    Returns:
    """
    mixer_index = _get_index_absolute_path('GIDIDX' + idx.upper())

    image_types = ('*.jpg', '*.JPG', '*.jpeg', '.png')

    classes_list = []
    if classes:
        classes.replace('%2C', ',')
        classes.replace('%20', ' ')
        classes_list = classes.split(',')

    def get_class_path():
        with open(mixer_index, 'r') as f:
            dataset_path = f.readline()
        dataset_path = '/'.join(dataset_path.split('/')[:-2])
        class_paths = []
        for c in classes_list:
            class_paths.append(_get_obj_absolute_path(dataset_path+'/'+c.strip()))
        return class_paths

    mixer_list = []
    class_paths = get_class_path()
    _log.info("Class paths : {}".format(class_paths))

    if class_paths:
        for path in class_paths:
            _log.info("Path Exists ? {}".format(os.path.exists(path)))
            sys.stdout.flush()
            file_list = []
            for ext in image_types:
                file_list.extend(glob.glob(os.path.join(path, ext)))
            mixer_list.extend(sorted(file_list))
        mixer_list = [_get_obj_path(l.strip()) for l in mixer_list]
    else:
        with open(mixer_index, 'r') as f:
            mixer_list = list(f.readlines())

    return mixer_list

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
        attrs['_gt_label'] = path.split('/')[-2]
    except IOError:
        pass

    return jsonify(attrs)


def _get_object_element(object_path):
    path = _get_obj_absolute_path(object_path)
    class_text = '/'.join(path.split('/')[:-2])+'/classes.txt'
    if os.path.isfile(class_text):
        return '<object id={} src={} meta={} />' \
            .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                    quoteattr(_get_object_src_uri(object_path)),
                    quoteattr(url_for('.get_object_meta', object_path=object_path)))
    return '<object id={} src={} />' \
            .format(quoteattr(url_for('.get_object_id', object_path=object_path)),
                    quoteattr(_get_object_src_uri(object_path)))


def _get_object_src_uri(object_path):
    if LOCAL_OBJ_URI:
        return 'file://' + _get_obj_absolute_path(object_path)

    return url_for('.get_object_src_http', obj_path=object_path)

def _get_obj_path(obj_path):
    return obj_path.replace(DATAROOT+'/', '')

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
