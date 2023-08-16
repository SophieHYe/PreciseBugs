# -*- encoding: utf-8 -*-
import os
import logging
import sqlite3
import md5

from pyramid.config import Configurator
from pyramid.events import NewRequest
from pyramid.events import subscriber
from pyramid.events import ApplicationCreated
from pyramid.httpexceptions import HTTPFound
from pyramid.session import UnencryptedCookieSessionFactoryConfig
from pyramid.view import view_config

from wsgiref.simple_server import make_server

logging.basicConfig()
log = logging.getLogger(__file__)

here = os.path.dirname(os.path.abspath(__file__))

# Start page where we can enter our credit card number
@view_config(route_name='cc_number', renderer='cc_number.mako')
def cc_number_view(request):
    clear_session(request)
    if request.method == 'POST' and request.POST.get('cc_number'):
        cc_number = request.POST.get('cc_number')
        card = get_card(request, cc_number)
        if not card:
            request.session.flash('Your credit card %s wasn\'t found! Please try again.' % cc_number)
            return HTTPFound(location=request.route_url('error'))
        if card['status'] == 'blocked':
            request.session.flash('Your credit card %s is blocked! Please try another card.' % cc_number)
            return HTTPFound(location=request.route_url('error'))
        request.session['card'] = card
        return HTTPFound(location=request.route_url('pin'))
    return {}

# Page where we can enter our pin code, should be accessible only if cc was valid
@view_config(route_name='pin', renderer='pin.mako')
def pin_view(request):
    if not is_card_at_session(request):
        return HTTPFound(location=request.route_url('cc_number'))

    card = request.session['card']
    if request.method == 'POST' and request.POST.get('pin'):
        pin = md5.md5(request.POST.get('pin')).hexdigest()
        if pin != card['pin']:
            card['failed_attempts'] += 1
            update_failed_attempts(request, card['failed_attempts'])
            if card['failed_attempts'] == 4:
                m = 'You reached max number of tries. Your card %s was blocked.' % card['cc_dashed']
                block_card(request)
                clear_session(request)
            else:
                m = 'Your pin code isn\'t valid! Please try again. Left number of tries: %s' % (4 - card['failed_attempts'])
            request.session.flash(m)
            return HTTPFound(location=request.route_url('error'))

        update_failed_attempts(request, 0)
        request.session['card']['valid_pin'] = True
        return HTTPFound(location=request.route_url('operations'))
    return {}

@view_config(route_name='operations', renderer='operations.mako')
def operations_view(request):
    if not is_card_pin_at_session(request):
        return HTTPFound(location=request.route_url('cc_number'))

    return {}

@view_config(route_name='balance', renderer='balance.mako')
def balance_view(request):
    if not is_card_pin_at_session(request):
        return HTTPFound(location=request.route_url('cc_number'))
    save_balance_check(request)

    return {}

@view_config(route_name='withdraw', renderer='withdraw.mako')
def withdraw_view(request):
    if not is_card_pin_at_session(request):
        return HTTPFound(location=request.route_url('cc_number'))

    card = request.session['card']
    if request.method == 'POST' and request.POST.get('withdraw'):
        withdraw = int(request.POST.get('withdraw'))
        if withdraw == 0 or withdraw > card['balance']:
            request.session.flash('There are not enough funds available on the card. Available: %s' % card['balance'])
            return HTTPFound(location=request.route_url('error'))
        save_withdraw(request, withdraw)
        return HTTPFound(location=request.route_url('report'))
    return {}

@view_config(route_name='report', renderer='report.mako')
def report_view(request):
    if not is_card_pin_at_session(request):
        return HTTPFound(location=request.route_url('cc_number'))

    return {}

@view_config(route_name='report_all', renderer='report_all.mako')
def report_all_view(request):
    rs = request.db.execute(
        "select cc_number, failed_attempts, status, balance from cards"
    )
    cards = [dict(cc_number=row[0], failed_attempts=row[1], status=row[2], balance=row[3]) for row in rs.fetchall()]
    rs = request.db.execute(
        "select cc_number, datetime, operation_code, total from cards c inner join operations o on c.id = o.card_id"
    )
    operations = [dict(cc_number=row[0], datetime=row[1], operation_code=row[2], total=row[3]) for row in rs.fetchall()]
    return {'cards': cards, 'operations': operations}

@view_config(route_name='error', renderer='error.mako')
def error_view(request):
    return {}

@view_config(context='pyramid.exceptions.NotFound', renderer='notfound.mako')
def notfound_view(request):
    request.response.status = '404 Not Found'
    return {}

# subscribers
@subscriber(NewRequest)
def new_request_subscriber(event):
    request = event.request
    settings = request.registry.settings
    request.db = sqlite3.connect(settings['db'])
    request.add_finished_callback(close_db_connection)

def close_db_connection(request):
    request.db.close()

@subscriber(ApplicationCreated)
def application_created_subscriber(event):
    with open(os.path.join(here, 'schema.sql')) as f:
        stmt = f.read()
        settings = event.app.registry.settings
        db = sqlite3.connect(settings['db'])
        db.executescript(stmt)
        db.commit()

#custom
def clear_session(request):
    request.session.pop('card', None)

def is_card_at_session(request):
    return 'card' in request.session

def is_card_pin_at_session(request):
    return 'card' in request.session and request.session['card']['valid_pin'] is True

def get_card(request, cc_number):
    q = "select * from cards where cc_number = '%s'" % cc_number.replace('-', '')
    row = request.db.execute(q).fetchone()
    if row is not None:
        return dict(
            id = row[0],
            cc_number = row[1],
            pin = row[2],
            failed_attempts = row[3],
            status = row[4],
            balance = row[5],
            cc_dashed = cc_number,
            valid_pin = False
        )

def update_failed_attempts(request, failed_attempts):
    card = request.session['card']
    request.db.execute("update cards set failed_attempts = ? where id = ?", (failed_attempts, card['id']))
    request.db.commit()

def block_card(request):
    card = request.session['card']
    request.db.execute("update cards set status = 'blocked' where id = %s" % card['id'])
    request.db.commit()

def save_balance_check(request):
    card = request.session['card']
    request.db.execute(
        "insert into operations (card_id, operation_code, total) values (?, 'balance', ?)", (card['id'], card['balance'])
    )
    request.db.commit()

def save_withdraw(request, withdraw):
    card = request.session['card']
    balance = card['balance'] - withdraw
    request.db.execute(
        "update cards set balance = ? where id = ?", (balance, card['id'])
    )
    request.db.execute(
        "insert into operations (card_id, operation_code, total) values (?, 'withdraw', ?)", (card['id'], withdraw)
    )
    request.db.commit()

    request.session['card']['balance'] = balance
    request.session['card']['withdraw'] = withdraw

if __name__ == '__main__':
    # configuration settings
    settings = {}
    settings['reload_all'] = True
    settings['debug_all'] = True
    settings['mako.directories'] = os.path.join(here, 'templates')
    settings['db'] = os.path.join(here, 'machine.db')
    # session factory
    session_factory = UnencryptedCookieSessionFactoryConfig('itsaseekreet')
    # configuration setup
    config = Configurator(settings=settings, session_factory=session_factory)
    # add mako templating
    config.include('pyramid_mako')
    # routes setup
    config.add_route('cc_number', '/')
    config.add_route('pin', '/pin')
    config.add_route('operations', '/operations')
    config.add_route('balance', '/balance')
    config.add_route('withdraw', '/withdraw')
    config.add_route('report', '/report')
    config.add_route('report_all', '/report-all')
    config.add_route('error', '/error')
    # static view setup
    config.add_static_view('static', os.path.join(here, 'static'))
    # scan for @view_config and @subscriber decorators
    config.scan()
    # serve app
    app = config.make_wsgi_app()
    server = make_server('0.0.0.0', 8080, app)
    server.serve_forever()