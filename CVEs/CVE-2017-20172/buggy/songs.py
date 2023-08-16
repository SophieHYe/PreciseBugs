from flask import jsonify, request, make_response, g
from sqlalchemy.exc import IntegrityError
from . import api
from .. import db, auth
from ..models import Song
from .errors import bad_request, route_not_found

@api.route('/songs/<name>')
def song(name):
    return jsonify(name=name)

@api.route('/songs/<int:id>')
def get_song(id):
    song = Song.query.filter_by(id=id).first()
    if not song:
        return route_not_found(song)
    return make_response(jsonify(song.to_json()), 200)

@api.route('/songs/', methods=['POST'])
@auth.login_required
def new_song():
    # check if json
    if request.headers['content_type'] == 'application/json':
        payload = request.get_json()

        # validate payload
        if not request.json or \
        not 'title' in payload or \
        not 'artist' in payload or \
        not 'url' in payload:
            message = 'the payload aint right'
            return bad_request(message)

        # validate that song doesn't already exist
        # TODO: this needs to be way more sophisticated
        if Song.query.filter_by(url=payload['url']).first():
            message = 'this song already exists'
            return bad_request(message)

        # add song
        try:
            song = Song(title=payload['title'], \
                        artist=payload['artist'], \
                        url=payload['url'], \
                        user=g.current_user)
            db.session.add(song)
            db.session.commit()
            return make_response(jsonify(song.to_json()), 200)
        except IntegrityError:
            message = 'this song already exists'
            return bad_request(message)
        except AssertionError as ex:
            return bad_request(ex.args[0])
        except Exception as ex:
            template = "An exception of type {0} occured. Arguments:\n{1!r}"
            message = template.format(type(ex).__name__, ex.args)
            return bad_request(message)

    else:
        message = 'that aint json'
        return bad_request(message)

@api.route('/songs/<int:id>/related')
def get_song_relations(id):
    top = request.args.get('top')
    song = Song.query.filter_by(id=id).first()
    if not song:
        return route_not_found(song)
    return make_response(jsonify(song.get_related_songs_json(top)), 200)




