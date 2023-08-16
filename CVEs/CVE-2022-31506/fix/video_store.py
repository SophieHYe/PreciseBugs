#
#  The OpenDiamond Platform for Interactive Search
#
#  Copyright (c) 2018 Carnegie Mellon University
#  All rights reserved.
#
#  This software is distributed under the terms of the Eclipse Public
#  License, Version 1.0 which can be found in the file named LICENSE.
#  ANY USE, REPRODUCTION OR DISTRIBUTION OF THIS SOFTWARE CONSTITUTES
#  RECIPIENT'S ACCEPTANCE OF THIS AGREEMENT
#
from builtins import range, str

import datetime
import json
import os
import subprocess
import sys
from math import ceil

from flask import Blueprint, Response, request, stream_with_context, url_for
from opendiamond.dataretriever.util import DiamondTextAttr
from werkzeug.datastructures import Headers
from werkzeug.security import safe_join

# IMPORTANT: requires ffmpeg >= 3.3. Lower versions produce incorrect clipping.

BASEURL = 'video'
STYLE = False
INDEXDIR = DATAROOT = None


def init(config):
    global INDEXDIR, DATAROOT  # pylint: disable=global-statement
    INDEXDIR = config.indexdir
    DATAROOT = config.dataroot


scope_blueprint = Blueprint('video_store', __name__)

@scope_blueprint.route('/scope/<gididx>')
@scope_blueprint.route('/scope/stride/<int:stride>/span/<int:span>/<gididx>')
def get_scope(gididx, stride=5, span=5):
    index = 'GIDIDX' + gididx.upper()

    def generate():
        yield '<?xml version="1.0" encoding="UTF-8" ?>\n'
        if STYLE:
            yield '<?xml-stylesheet type="text/xsl" href="/scopelist.xsl" ?>\n'

        yield '<objectlist>\n'

        with open(_get_index_absolute_path(index), 'rt') as f:
            for line in f:
                video = line.strip()
                video_path = str(_get_obj_absolute_path(video))
                try:
                    video_meta = _ffprobe(video_path)
                    length_sec = float(video_meta['format']['duration'])
                    num_clips = int(ceil(length_sec / stride))
                    yield '<count adjust="{}"/>\n'.format(num_clips)
                    for clip in range(num_clips):
                        yield _get_object_element(start=clip * stride, span=span, video=video) + '\n'
                except Exception as e:
                    print("Error parsing {}. {}. Skip.".format(video, str(e)), file=sys.stderr)
                    pass

        yield '</objectlist>\n'

    headers = Headers([('Content-Type', 'text/xml')])
    return Response(stream_with_context(generate()),
                    status="200 OK",
                    headers=headers)


@scope_blueprint.route('/id/start/<int:start>/span/<int:span>/<path:video>')
def get_object_id(start, span, video):
    headers = Headers([('Content-Type', 'text/xml')])
    return Response(_get_object_element(start, span, video),
                    "200 OK",
                    headers=headers)


@scope_blueprint.route('/obj/start/<int:start>/span/<int:span>/<path:video>')
def get_object(start, span, video):
    # Reference:
    # https://github.com/mikeboers/PyAV/blob/master/tests/test_seek.py
    video_path = str(_get_obj_absolute_path(video))
    proc = _create_ffmpeg_segment_proc(video_path,
                                       start_sec=start,
                                       duration_sec=span)

    def generate():
        while True:
            data = proc.stdout.read(4096)
            if not data:
                break
            yield data

    headers = Headers([('Content-Type', 'video/mp4')])
    response = Response(stream_with_context(generate()),
                        status="200 OK",
                        headers=headers)
    # Cache control
    stat = os.stat(video_path)
    last_modified = stat.st_mtime
    size = stat.st_size
    etag = "{}_{}_{}_{}".format(last_modified, size, start, span)
    response.last_modified = last_modified
    response.set_etag(etag=etag)
    response.cache_control.public = True
    response.cache_control.max_age = \
        datetime.timedelta(days=365).total_seconds()
    response.make_conditional(request)

    return response


def _get_object_element(start, span, video):
    return '<object id="{}" src="{}" />'.format(
        url_for('.get_object_id', start=start, span=span, video=video),
        url_for('.get_object', start=start, span=span, video=video))


def _get_obj_absolute_path(obj_path):
    return safe_join(DATAROOT, obj_path)


def _get_index_absolute_path(index):
    return safe_join(INDEXDIR, index)


def _ffprobe(video_path):
    cmd_l = ['ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_format', video_path]

    proc = subprocess.Popen(cmd_l, stdout=subprocess.PIPE, bufsize=-1)
    data = json.load(proc.stdout)
    
    return data


def _create_ffmpeg_segment_proc(video_path, start_sec, duration_sec):
    """
    Use ffmpeg to extract a .mp4 segment of the video. Outfile is written to stdout.
    Note: requires ffmpeg >= 3.3. Lower versions produce wrong results.
    Reference: http://trac.ffmpeg.org/wiki/Seeking
    https://stackoverflow.com/questions/34123272/ffmpeg-transmux-mpegts-to-mp4-gives-error-muxer-does-not-support-non-seekable
    :param video_path:
    :param start_sec:
    :param duration_sec:
    :return: the subprocess
    """
    cmd_l = ['ffmpeg', '-v', 'quiet',
             '-ss', str(start_sec),
             '-t', str(duration_sec),
             '-i', str(video_path),
             '-movflags', 'frag_keyframe+empty_moov',
             '-c', 'copy',
             '-f', 'mp4',
             'pipe:1']

    proc = subprocess.Popen(cmd_l, stdout=subprocess.PIPE, bufsize=-1)
    return proc
