from flask import g, abort, render_template, send_file
from flask_login import current_user, login_required
from app import login_manager, app
from app.helper.utils import sqlalchemy_info, DataModel
from app.module.user.model import UserModel, UserTokenModel
from app.module.user import UserSession
from werkzeug.routing import BaseConverter, ValidationError
from werkzeug.utils import safe_join
from bson.objectid import ObjectId
from bson.errors import InvalidId
from os import path

#
# Handler route
#


@app.route('/file/private/<path:filename>')
@login_required
def private_static(filename):
    # Get path
    filepath = safe_join(app.config.get("PRIVATE_DIR"), filename)
    if path.isfile(filepath):
        return send_file(filepath)
    # End
    return abort(404)


#
# Middleware
#


@login_manager.user_loader
def user_loader(token):
    # Get token
    token_data = UserTokenModel.query.available().filter_by(token=token).first()
    if not token_data:
        return None

    # Get user
    user_data = UserModel.query.available().filter_by(id_=token_data.user_id).first()
    if not user_data:
        return None

    # User config
    if user_data.config:
        g.user_config = DataModel(data=user_data.config)
    else:
        g.user_config = DataModel()

    # Return session mixin
    return UserSession(token=token, user=user_data)


@app.context_processor
def context_processor():
    data = None
    user = None

    # Add user data to jinja
    if current_user.is_authenticated:
        user = current_user.user

    return {
        "data": data,
        "user": user
    }


@app.before_request
def before_request():
    pass


@app.after_request
def after_request(response):
    # SQLAlchemy info
    sqlalchemy_info(response)

    # Response
    return response

#
# URL Filter
#


class ObjectIDConverter(BaseConverter):
    def to_python(self, value):
        try:
            return ObjectId(str(value))
        except (InvalidId, ValueError, TypeError):
            raise ValidationError()

    def to_url(self, value):
        return str(value)


# Register
app.url_map.converters['ObjectID'] = ObjectIDConverter

#
# HTTP error handler
#


@app.errorhandler(400)
def bad_request(error):
    page_data = {
        "title": "Kesalahan 400",
        "message": "Bad Request",
        "error": True,
    }
    return render_template("error.html", data=page_data), 400


@app.errorhandler(401)
def unauthorized(error):
    page_data = {
        "title": "Kesalahan 401",
        "message": "Tidak Sah",
        "error": True,
    }
    return render_template("error.html", data=page_data), 401


@app.errorhandler(403)
def access_forbidden(error):
    page_data = {
        "title": "Kesalahan 403",
        "message": "Akses ditolak<br />Anda tidak memiliki hak akses ke halaman ini",
        "error": True,
    }
    return render_template("error.html", data=page_data), 403


@app.errorhandler(404)
def not_found(error):
    page_data = {
        "title": "Kesalahan 404",
        "message": "Halaman tidak ditemukan<br />Data yang anda akses sudah dihapus atau tidak tersedia",
        "error": True,
    }
    return render_template("error.html", data=page_data), 404


@app.errorhandler(413)
def payload_too_large(error):
    page_data = {
        "title": "Kesalahan 413",
        "message": "Payload Too Large",
        "error": True,
    }
    return render_template("error.html", data=page_data), 413


@app.errorhandler(500)
def internal_error(error):
    web_name = app.config["WEB_INFO"]["name"]
    page_data = {
        "title": "Kesalahan 500",
        "message": f"Terjadi kesalahan pada server internal<br />Jika pesan ini muncul terus-menerus silahkan hubungi pihak {web_name}",
        "error": True,
    }
    return render_template("error.html", data=page_data), 500


@app.errorhandler(501)
def not_implemented(error):
    page_data = {
        "title": "Kesalahan 501",
        "message": "Not Implemented",
        "error": True,
    }
    return render_template("error.html", data=page_data), 501
