from __future__ import absolute_import
from flask import url_for, redirect, abort, request, Blueprint, current_app
from flask_login import login_required, current_user
import six
from six.moves import filter, map
from sqlalchemy.orm.exc import NoResultFound
from itertools import chain

from .. import ships, systems, db
from ..models import Request, ActionType
from ..auth import PermissionType
from ..auth.models import Division, User, Group, Pilot, Entity
from .requests import PermissionRequestListing, PersonalRequests
from ..util import jsonify, classproperty


api = Blueprint('api', __name__)


filters = Blueprint('filters', __name__)


@api.route('/entities/')
@login_required
def list_entities():
    """Return a JSON object with a list of all of the specified entity type.

    Example output::
        {
          entities: [
            {name: 'Bar', id: 1, source: 'Auth Source', type: 'User'},
            {name: 'Foo', id: 0, source: 'Another Auth Source', type: 'Group'},
            {name: 'Baz', id: 20, source: 'Auth Source', type: 'Group'}
          ]
        }

    This method is only accesible to administrators.

    :param str entity_type: Either ``'user'`` or ``'group'``.
    """
    if not current_user.admin and not \
            current_user.has_permission(PermissionType.admin):
        abort(403)
    user_query = db.session.query(User.id, User.name, User.authmethod)
    group_query = db.session.query(Group.id, Group.name, Group.authmethod)
    users = map(lambda e: {
            u'id': e.id,
            u'name': e.name,
            u'type': u'User',
            u'source': e.authmethod}, user_query)
    groups = map(lambda e: {
            u'id': e.id,
            u'name': e.name,
            u'type': u'Group',
            u'source': e.authmethod}, group_query)
    return jsonify(entities=chain(users, groups))


@api.route('/user/<int:user_id>/')
@login_required
def user_detail(user_id):
    if not current_user.admin and not \
            current_user.has_permission(PermissionType.admin):
        abort(403)
    user = User.query.get_or_404(user_id)
    # Set up divisions
    submit = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.submit,
                user.permissions))
    review = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.review,
                user.permissions))
    pay = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.pay,
                user.permissions))
    resp = {
        u'name': user.name,
        u'groups': list(user.groups),
        u'divisions': {
            u'submit': list(set(submit)),
            u'review': list(set(review)),
            u'pay': list(set(pay)),
        },
        u'admin': user.admin,
        u'requests': user.requests,
    }
    return jsonify(**resp)


@api.route('/group/<int:group_id>/')
@login_required
def group_detail(group_id):
    if not current_user.admin and not \
            current_user.has_permission(PermissionType.admin):
        abort(403)
    group = Group.query.get_or_404(group_id)
    submit = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.submit,
                group.permissions))
    review = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.review,
                group.permissions))
    pay = map(lambda p: p.division,
            filter(lambda p: p.permission == PermissionType.pay,
                group.permissions))
    resp = {
        u'name': group.name,
        u'users': list(group.users),
        u'divisions': {
            u'submit': list(set(submit)),
            u'review': list(set(review)),
            u'pay': list(set(pay)),
        },
    }
    return jsonify(**resp)


@api.route('/division/')
@login_required
def list_divisions():
    """List all divisions.
    """
    if not current_user.admin:
        abort(403)
    divisions = db.session.query(Division.id, Division.name)
    return jsonify(divisions=divisions)


@api.route('/division/<int:division_id>/')
@login_required
def division_detail(division_id):
    """Get the details of a division.

    :param int division_id: The ID of the division
    """
    division = Division.query.get_or_404(division_id)
    if not current_user.admin and not \
            current_user.has_permission(PermissionType.admin, division):
        abort(403)
    permissions = {}
    for perm in PermissionType.all:
        key = perm.name + '_href'
        permissions[key] = url_for('.division_permissions',
                division_id=division_id,
                permission=perm.name)
    return jsonify(
            name=division.name,
            requests=division.requests,
            permissions=permissions)


@api.route('/division/<int:division_id>/<permission>/')
@login_required
def division_permissions(division_id, permission):
    division = Division.query.get_or_404(division_id)
    if not current_user.admin and not \
            current_user.has_permission(PermissionType.admin, division):
        abort(403)
    permission = PermissionType.from_string(permission)
    # Can't use normal Entity JSON encoder as it doesn't include the
    # authentication source or their type (explicitly. Ain't nobody got time
    # for parsing the entity type out of the href).
    entities = []
    for entity in map(lambda p: p.entity, division.permissions[permission]):
        entity_info = {
            u'name': entity.name,
            u'id': entity.id,
            u'source': str(entity.authmethod),
        }
        if hasattr(entity, u'users'):
            entity_info[u'type'] = u'Group'
            entity_info[u'length'] = len(entity.users)
        else:
            entity_info[u'type'] = u'User'
        entities.append(entity_info)
    return jsonify(
        entities=entities,
        name=permission.name,
        description=permission.description)


@api.route('/ships/')
@login_required
def ship_list():
    """Get an array of objects corresponding to every ship type.

    The objects have two keys, ``id`` is the integer typeID, and ``name`` is
    the name of the ship. This method is only accessible for logged in users to
    try to keep possible misuse to a minimum.
    """
    ship_objs = list(map(lambda s: {u'name': s[1], u'id': s[0]},
            ships.ships.items()))
    return jsonify(ships=ship_objs)


class FiltersRequestListing(object):
    @classproperty
    def _load_options(self):
        """Returns a sequence of
        :py:class:`~sqlalchemy.orm.strategy_options.Load` objects specifying
        which attributes to load.
        """
        return (
                db.Load(Request).load_only(
                    'id',
                    'pilot_id',
                    'corporation',
                    'alliance',
                    'ship_type',
                    'status',
                    'base_payout',
                    'kill_timestamp',
                    'timestamp',
                    'division_id',
                    'submitter_id',
                    'system',
                ),
                db.Load(Division).joinedload('name'),
                db.Load(Pilot).joinedload('name'),
                db.Load(User).joinedload('id')
        )


    def dispatch_request(self, filters='', **kwargs):
        def request_dict(request):
            payout = request.payout
            return {
                u'id': request.id,
                u'href': url_for('requests.get_request_details',
                    request_id=request.id),
                u'pilot': request.pilot.name,
                u'corporation': request.corporation,
                u'alliance': request.alliance,
                u'ship': request.ship_type,
                u'status': request.status.name,
                u'payout': payout.currency(),
                u'kill_timestamp': request.kill_timestamp,
                u'submit_timestamp': request.timestamp,
                u'division': request.division.name,
                u'submitter_id': request.submitter.id,
                u'system': request.system,
                u'constellation': request.constellation,
                u'region': request.region,
            }

        return jsonify(requests=map(request_dict, self.requests({})))


class APIRequestListing(FiltersRequestListing, PermissionRequestListing): pass


class APIPersonalRequests(FiltersRequestListing, PersonalRequests): pass


@filters.record
def register_request_lists(state):
    # Create the views
    all_requests = APIRequestListing.as_view('filter_requests_all',
            PermissionType.all, ActionType.statuses)
    user_requests = APIPersonalRequests.as_view('filter_requests_own')
    pending_requests = APIRequestListing.as_view('filter_requests_pending',
            (PermissionType.review,), ActionType.pending)
    pay_requests = APIRequestListing.as_view('filter_requests_pay',
            (PermissionType.pay,), (ActionType.approved,))
    completed_requests = APIRequestListing.as_view('filter_requests_completed',
            PermissionType.elevated, ActionType.finalized)
    # Attach the views to paths
    for prefix in state.app.request_prefixes:
        state.add_url_rule(prefix + '/', view_func=all_requests)
        state.add_url_rule(prefix + '/<path:filters>/',
                view_func=all_requests)
        state.add_url_rule(prefix + '/personal/', view_func=user_requests)
        state.add_url_rule(prefix + '/personal/<path:filters>/',
                view_func=user_requests)
        state.add_url_rule(prefix + '/pending/', view_func=pending_requests)
        state.add_url_rule(prefix + '/pending/<path:filters>/',
                view_func=pending_requests)
        state.add_url_rule(prefix + '/pay/', view_func=pay_requests)
        state.add_url_rule(prefix + '/pay/<path:filters>/',
                view_func=pay_requests)
        state.add_url_rule(prefix + '/completed/',
                view_func=completed_requests)
        state.add_url_rule(prefix + '/completed/<path:filters>/',
                view_func=completed_requests)


def _first(o):
    return o[0]


@filters.route('/ship/')
@login_required
def filter_ships():
    ships = db.session.query(Request.ship_type).distinct()
    return jsonify(key=u'ship', ship=map(_first, ships))


@filters.route('/system/')
@login_required
def filter_systems():
    systems = db.session.query(Request.system).distinct()
    return jsonify(key=u'system', system=map(_first, systems))


@filters.route('/constellation/')
@login_required
def filter_constellations():
    constellations = db.session.query(Request.constellation).distinct()
    return jsonify(key=u'constellation',
            constellation=map(_first, constellations))


@filters.route('/region/')
@login_required
def filter_regions():
    regions = db.session.query(Request.region).distinct()
    return jsonify(key=u'region', region=map(_first, regions))


@filters.route('/details/<path:query>')
@login_required
def query_details(query):
    requests = db.session.query(Request.id)\
            .filter(Request.details.match(query))
    return jsonify(ids=map(_first, requests))


@filters.route('/pilot/')
@login_required
def filter_pilots():
    pilots = db.session.query(Pilot.name)
    return jsonify(key=u'pilot', pilot=map(_first, pilots))


@filters.route('/corporation/')
@login_required
def filter_corps():
    corps = db.session.query(Request.corporation).distinct()
    return jsonify(key=u'corporation', corporation=map(_first, corps))


@filters.route('/alliance/')
@login_required
def filter_alliances():
    alliances = db.session.query(Request.alliance)\
            .filter(Request.alliance != None)\
            .distinct()
    return jsonify(key=u'alliance', alliance=map(_first, alliances))


@filters.route('/division/')
@login_required
def filter_divisions():
    div_names = db.session.query(Division.name)
    return jsonify(key=u'division', division=map(_first, div_names))
