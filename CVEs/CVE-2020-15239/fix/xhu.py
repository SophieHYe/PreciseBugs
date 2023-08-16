########################################################################
# File name: xhu.py
# This file is part of: xmpp-http-upload
#
# LICENSE
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# General Public License for more details.
#
# You should have received a copy of the GNU General Public
# License along with this program.  If not, see
# <http://www.gnu.org/licenses/>.
#
########################################################################
import contextlib
import errno
import fnmatch
import json
import hashlib
import hmac
import pathlib
import typing

import flask
import werkzeug.exceptions

app = flask.Flask("xmpp-http-upload")
app.config.from_envvar("XMPP_HTTP_UPLOAD_CONFIG")
application = app

if app.config['ENABLE_CORS']:
    from flask_cors import CORS
    CORS(app)


def get_paths(root: str, sub_path: str) \
        -> typing.Tuple[pathlib.Path, pathlib.Path]:
    base_path = flask.safe_join(root, sub_path)
    data_file = pathlib.Path(base_path + ".data")
    metadata_file = pathlib.Path(base_path + ".meta")

    return data_file, metadata_file


def load_metadata(metadata_file):
    with metadata_file.open("r") as f:
        return json.load(f)


def get_info(path: str) -> typing.Tuple[
        pathlib.Path,
        dict]:
    data_file, metadata_file = get_paths(app.config["DATA_ROOT"], path)

    return data_file, load_metadata(metadata_file)


@contextlib.contextmanager
def write_file(at: pathlib.Path):
    with at.open("xb") as f:
        try:
            yield f
        except:  # NOQA
            at.unlink()
            raise


@app.route("/")
def index():
    return flask.Response(
        "Welcome to XMPP HTTP Upload. State your business.",
        mimetype="text/plain",
    )


def stream_file(src, dest, nbytes):
    while nbytes > 0:
        data = src.read(min(nbytes, 4096))
        if not data:
            break
        dest.write(data)
        nbytes -= len(data)

    if nbytes > 0:
        raise EOFError


@app.route("/<path:path>", methods=["PUT"])
def put_file(path):
    try:
        data_file, metadata_file = get_paths(app.config["DATA_ROOT"], path)
    except werkzeug.exceptions.NotFound:
        return flask.Response(
            "Not Found",
            404,
            mimetype="text/plain",
        )

    verification_key = flask.request.args.get("v", "")
    length = int(flask.request.headers.get("Content-Length", 0))
    hmac_input = "{} {}".format(path, length).encode("utf-8")
    key = app.config["SECRET_KEY"]
    mac = hmac.new(key, hmac_input, hashlib.sha256)
    digest = mac.hexdigest()

    if not hmac.compare_digest(digest, verification_key):
        return flask.Response(
            "Invalid verification key",
            403,
            mimetype="text/plain",
        )

    content_type = flask.request.headers.get(
        "Content-Type",
        "application/octet-stream",
    )

    data_file.parent.mkdir(parents=True, exist_ok=True, mode=0o770)

    try:
        with write_file(data_file) as fout:
            stream_file(flask.request.stream, fout, length)

            with metadata_file.open("x") as f:
                json.dump(
                    {
                        "headers": {"Content-Type": content_type},
                    },
                    f,
                )
    except EOFError:
        return flask.Response(
            "Bad Request",
            400,
            mimetype="text/plain",
        )
    except OSError as exc:
        if exc.errno == errno.EEXIST:
            return flask.Response(
                "Conflict",
                409,
                mimetype="text/plain",
            )
        raise

    return flask.Response(
        "Created",
        201,
        mimetype="text/plain",
    )


def generate_headers(response_headers, metadata_headers):
    for key, value in metadata_headers.items():
        response_headers[key] = value

    content_type = metadata_headers["Content-Type"]
    for mimetype_glob in app.config.get("NON_ATTACHMENT_MIME_TYPES", []):
        if fnmatch.fnmatch(content_type, mimetype_glob):
            break
    else:
        response_headers["Content-Disposition"] = "attachment"

    response_headers["X-Content-Type-Options"] = "nosniff"
    response_headers["X-Frame-Options"] = "DENY"
    response_headers["Content-Security-Policy"] = "default-src 'none'; frame-ancestors 'none'; sandbox"


@app.route("/<path:path>", methods=["HEAD"])
def head_file(path):
    try:
        data_file, metadata = get_info(path)

        stat = data_file.stat()
    except (OSError, werkzeug.exceptions.NotFound):
        return flask.Response(
            "Not Found",
            404,
            mimetype="text/plain",
        )

    response = flask.Response()
    response.headers["Content-Length"] = str(stat.st_size)
    generate_headers(
        response.headers,
        metadata["headers"],
    )
    return response


@app.route("/<path:path>", methods=["GET"])
def get_file(path):
    try:
        data_file, metadata = get_info(path)
    except (OSError, werkzeug.exceptions.NotFound):
        return flask.Response(
            "Not Found",
            404,
            mimetype="text/plain",
        )

    response = flask.make_response(flask.send_file(
        str(data_file),
    ))
    generate_headers(
        response.headers,
        metadata["headers"],
    )
    return response
