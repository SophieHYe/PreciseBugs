from __future__ import print_function

import argparse
import json
from oauthlib.oauth2 import LegacyApplicationClient
import logging
import logging.handlers
from requests_oauthlib import OAuth2Session
import os
import requests
import six
import sys
import traceback

from six.moves.urllib.parse import quote as urlquote
from six.moves.urllib.parse import urlparse


# ------------------------------------------------------------------------------

logger = None
prog_name = os.path.basename(sys.argv[0])
AUTH_ROLES = ['root-admin', 'realm-admin', 'anonymous']

LOG_FILE_ROTATION_COUNT = 3

TOKEN_URL_TEMPLATE = (
    '{server}/auth/realms/{realm}/protocol/openid-connect/token')
GET_SERVER_INFO_TEMPLATE = (
    '{server}/auth/admin/serverinfo/')
GET_REALMS_URL_TEMPLATE = (
    '{server}/auth/admin/realms')
CREATE_REALM_URL_TEMPLATE = (
    '{server}/auth/admin/realms')
DELETE_REALM_URL_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}')
GET_REALM_METADATA_TEMPLATE = (
    '{server}/auth/realms/{realm}/protocol/saml/descriptor')

CLIENT_REPRESENTATION_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients/{id}')
GET_CLIENTS_URL_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients')
CLIENT_DESCRIPTOR_URL_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/client-description-converter')
CREATE_CLIENT_URL_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients')

GET_INITIAL_ACCESS_TOKEN_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients-initial-access')
SAML2_CLIENT_REGISTRATION_TEMPLATE = (
  '{server}/auth/realms/{realm}/clients-registrations/saml2-entity-descriptor')

GET_CLIENT_PROTOCOL_MAPPERS_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients/{id}/protocol-mappers/models')
GET_CLIENT_PROTOCOL_MAPPERS_BY_PROTOCOL_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients/{id}/protocol-mappers/protocol/{protocol}')

POST_CLIENT_PROTOCOL_MAPPER_TEMPLATE = (
    '{server}/auth/admin/realms/{realm}/clients/{id}/protocol-mappers/models')


ADMIN_CLIENT_ID = 'admin-cli'

# ------------------------------------------------------------------------------


class RESTError(Exception):
    def __init__(self, status_code, status_reason,
                 response_json, response_text, cmd):
        self.status_code = status_code
        self.status_reason = status_reason
        self.error_description = None
        self.error = None
        self.response_json = response_json
        self.response_text = response_text
        self.cmd = cmd

        self.message = '{status_reason}({status_code}): '.format(
            status_reason=self.status_reason,
            status_code=self.status_code)

        if response_json:
            self.error_description = response_json.get('error_description')
            if self.error_description is None:
                self.error_description = response_json.get('errorMessage')
            self.error = response_json.get('error')
            self.message += '"{error_description}" [{error}]'.format(
                error_description=self.error_description,
                error=self.error)
        else:
            self.message += '"{response_text}"'.format(
                response_text=self.response_text)

        self.args = (self.message,)

    def __str__(self):
        return self.message

# ------------------------------------------------------------------------------


def configure_logging(options):
    global logger  # pylint: disable=W0603

    log_dir = os.path.dirname(options.log_file)
    if os.path.exists(log_dir):
        if not os.path.isdir(log_dir):
            raise ValueError('logging directory "{log_dir}" exists but is not '
                             'directory'.format(log_dir=log_dir))
    else:
        os.makedirs(log_dir)

    log_level = logging.ERROR
    if options.verbose:
        log_level = logging.INFO
    if options.debug:
        log_level = logging.DEBUG

        # These two lines enable debugging at httplib level
        # (requests->urllib3->http.client) You will see the REQUEST,
        # including HEADERS and DATA, and RESPONSE with HEADERS but
        # without DATA.  The only thing missing will be the
        # response.body which is not logged.
        try:
            import http.client as http_client  # Python 3
        except ImportError:
            import httplib as http_client      # Python 2

        http_client.HTTPConnection.debuglevel = 1

        # Turn on cookielib debugging
        if False:
            try:
                import http.cookiejar as cookiejar
            except ImportError:
                import cookielib as cookiejar  # Python 2
            cookiejar.debug = True

    logger = logging.getLogger(prog_name)

    try:
        file_handler = logging.handlers.RotatingFileHandler(
            options.log_file, backupCount=LOG_FILE_ROTATION_COUNT)
    except IOError as e:
        print('Unable to open log file %s (%s)' % (options.log_file, e),
              file=sys.stderr)

    else:
        formatter = logging.Formatter(
            '%(asctime)s %(name)s %(levelname)s: %(message)s')
        file_handler.setFormatter(formatter)
        file_handler.setLevel(logging.DEBUG)
        logger.addHandler(file_handler)

    console_handler = logging.StreamHandler(sys.stdout)
    formatter = logging.Formatter('%(message)s')
    console_handler.setFormatter(formatter)
    console_handler.setLevel(log_level)
    logger.addHandler(console_handler)

    # Set the log level on the logger to the lowest level
    # possible. This allows the message to be emitted from the logger
    # to it's handlers where the level will be filtered on a per
    # handler basis.
    logger.setLevel(1)

# ------------------------------------------------------------------------------


def json_pretty(text):
    return json.dumps(json.loads(text),
                      indent=4, sort_keys=True)


def py_json_pretty(py_json):
    return json_pretty(json.dumps(py_json))


def server_name_from_url(url):
    return urlparse(url).netloc


def get_realm_names_from_realms(realms):
    return [x['realm'] for x in realms]


def get_client_client_ids_from_clients(clients):
    return [x['clientId'] for x in clients]


def find_client_by_name(clients, client_id):
    for client in clients:
        if client.get('clientId') == client_id:
            return client
    raise KeyError('{item} not found'.format(item=client_id))


# ------------------------------------------------------------------------------

class KeycloakREST(object):

    def __init__(self, server, auth_role=None, session=None):
        self.server = server
        self.auth_role = auth_role
        self.session = session

    def get_initial_access_token(self, realm_name):
        cmd_name = "get initial access token for realm '{realm}'".format(
            realm=realm_name)
        url = GET_INITIAL_ACCESS_TOKEN_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)

        params = {"expiration": 60,  # seconds
                  "count": 1}

        response = self.session.post(url, json=params)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json    # ClientInitialAccessPresentation

    def get_server_info(self):
        cmd_name = "get server info"
        url = GET_SERVER_INFO_TEMPLATE.format(server=self.server)

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.get(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json

    def get_realms(self):
        cmd_name = "get realms"
        url = GET_REALMS_URL_TEMPLATE.format(server=self.server)

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.get(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json

    def create_realm(self, realm_name):
        cmd_name = "create realm '{realm}'".format(realm=realm_name)
        url = CREATE_REALM_URL_TEMPLATE.format(server=self.server)

        logger.debug("%s on server %s", cmd_name, self.server)

        params = {"enabled": True,
                  "id": realm_name,
                  "realm": realm_name,
                  }

        response = self.session.post(url, json=params)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.created:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)

    def delete_realm(self, realm_name):
        cmd_name = "delete realm '{realm}'".format(realm=realm_name)
        url = DELETE_REALM_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.delete(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.no_content:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)

    def get_realm_metadata(self, realm_name):
        cmd_name = "get metadata for realm '{realm}'".format(realm=realm_name)
        url = GET_REALM_METADATA_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.get(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.ok:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)
        return response.text

    def get_clients(self, realm_name):
        cmd_name = "get clients in realm '{realm}'".format(realm=realm_name)
        url = GET_CLIENTS_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.get(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json


    def get_client_by_id(self, realm_name, id):
        cmd_name = "get client id {id} in realm '{realm}'".format(
            id=id, realm=realm_name)
        url = GET_CLIENTS_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        params = {'clientID': id}

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.get(url, params=params)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json


    def get_client_by_name(self, realm_name, client_name):
        clients = self.get_clients(realm_name)
        client = find_client_by_name(clients, client_name)
        id = client.get('id')
        logger.debug("client name '%s' mapped to id '%s'",
                     client_name, id)
        logger.debug("client %s\n%s", client_name, py_json_pretty(client))
        return client

    def get_client_id_by_name(self, realm_name, client_name):
        client = self.get_client_by_name(realm_name, client_name)
        id = client.get('id')
        return id

    def get_client_descriptor(self, realm_name, metadata):
        cmd_name = "get client descriptor realm '{realm}'".format(
            realm=realm_name)
        url = CLIENT_DESCRIPTOR_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)

        headers = {'Content-Type': 'application/xml;charset=utf-8'}

        response = self.session.post(url, headers=headers, data=metadata)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.ok):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json

    def create_client_from_descriptor(self, realm_name, descriptor):
        cmd_name = "create client from descriptor "
        "'{client_id}'in realm '{realm}'".format(
            client_id=descriptor['clientId'], realm=realm_name)
        url = CREATE_CLIENT_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)

        response = self.session.post(url, json=descriptor)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.created:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)

    def create_client(self, realm_name, metadata):
        logger.debug("create client in realm %s on server %s",
                     realm_name, self.server)
        descriptor = self.get_client_descriptor(realm_name, metadata)
        self.create_client_from_descriptor(realm_name, descriptor)
        return descriptor

    def register_client(self, initial_access_token, realm_name, metadata):
        cmd_name = "register_client realm '{realm}'".format(
            realm=realm_name)
        url = SAML2_CLIENT_REGISTRATION_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name))

        logger.debug("%s on server %s", cmd_name, self.server)

        headers = {'Content-Type': 'application/xml;charset=utf-8'}

        if initial_access_token:
            headers['Authorization'] = 'Bearer {token}'.format(
                token=initial_access_token)

        response = self.session.post(url, headers=headers, data=metadata)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if (not response_json or
            response.status_code != requests.codes.created):
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, json_pretty(response.text))

        return response_json    # ClientRepresentation

    def delete_client_by_name(self, realm_name, client_name):
        id = self.get_client_id_by_name(realm_name, client_name)
        self.delete_client_by_id(realm_name, id)


    def delete_client_by_id(self, realm_name, id):
        cmd_name = "delete client id '{id}'in realm '{realm}'".format(
            id=id, realm=realm_name)
        url = CLIENT_REPRESENTATION_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name),
            id=urlquote(id))

        logger.debug("%s on server %s", cmd_name, self.server)
        response = self.session.delete(url)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.no_content:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)

    def update_client(self, realm_name, client):
        id = client['id']
        cmd_name = "update client {id} in realm '{realm}'".format(
            id=client['clientId'], realm=realm_name)
        url = CLIENT_REPRESENTATION_TEMPLATE.format(
            server=self.server, realm=urlquote(realm_name),
            id=urlquote(id))

        logger.debug("%s on server %s", cmd_name, self.server)

        response = self.session.put(url, json=client)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.no_content:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)


    def update_client_attributes(self, realm_name, client, update_attrs):
        client_id = client['clientId']
        logger.debug("update client attrs: client_id=%s "
        "current attrs=%s update=%s" % (client_id, client['attributes'],
                                update_attrs))
        client['attributes'].update(update_attrs)
        logger.debug("update client attrs: client_id=%s "
        "new attrs=%s" % (client_id, client['attributes']))
        self.update_client(realm_name, client);


    def update_client_by_name_attributes(self, realm_name, client_name,
                                         update_attrs):
        client = self.get_client_by_name(realm_name, client_name)
        self.update_client_attributes(realm_name, client, update_attrs)

    def new_saml_group_protocol_mapper(self, mapper_name, attribute_name,
                                       friendly_name=None,
                                       single_attribute=True):
        mapper = {
            'protocol': 'saml',
            'name': mapper_name,
            'protocolMapper': 'saml-group-membership-mapper',
            'config': {
                'attribute.name': attribute_name,
                'attribute.nameformat': 'Basic',
                'single': single_attribute,
                'full.path': False,
            },
        }

        if friendly_name:
            mapper['config']['friendly.name'] = friendly_name

        return mapper

    def create_client_protocol_mapper(self, realm_name, client, mapper):
        id = client['id']
        cmd_name = ("create protocol-mapper '{mapper_name}' for client {id} "
                    "in realm '{realm}'".format(
                        mapper_name=mapper['name'],id=client['clientId'], realm=realm_name))
        url = POST_CLIENT_PROTOCOL_MAPPER_TEMPLATE.format(
            server=self.server,
            realm=urlquote(realm_name),
            id=urlquote(id))

        logger.debug("%s on server %s", cmd_name, self.server)

        response = self.session.post(url, json=mapper)
        logger.debug("%s response code: %s %s",
                     cmd_name, response.status_code, response.reason)

        try:
            response_json = response.json()
        except ValueError as e:
            response_json = None

        if response.status_code != requests.codes.created:
            logger.error("%s error: status=%s (%s) text=%s",
                         cmd_name, response.status_code, response.reason,
                         response.text)
            raise RESTError(response.status_code, response.reason,
                            response_json, response.text, cmd_name)

        logger.debug("%s response = %s", cmd_name, response.text)


    def create_client_by_name_protocol_mapper(self, realm_name, client_name,
                                              mapper):
        client = self.get_client_by_name(realm_name, client_name)
        self.create_client_protocol_mapper(realm_name, client, mapper)



    def add_client_by_name_redirect_uris(self, realm_name, client_name, uris):
        client = self.get_client_by_name(realm_name, client_name)

        uris = set(uris)
        redirect_uris = set(client['redirectUris'])
        redirect_uris |= uris
        client['redirectUris'] = list(redirect_uris)
        self.update_client(realm_name, client);

    def remove_client_by_name_redirect_uris(self, realm_name, client_name, uris):
        client = self.get_client_by_name(realm_name, client_name)

        uris = set(uris)
        redirect_uris = set(client['redirectUris'])
        redirect_uris -= uris
        client['redirectUris'] = list(redirect_uris)

        self.update_client(realm_name, client);


# ------------------------------------------------------------------------------


class KeycloakAdminConnection(KeycloakREST):

    def __init__(self, server, auth_role, realm, client_id,
                 username, password, tls_verify):
        super(KeycloakAdminConnection, self).__init__(server, auth_role)

        self.realm = realm
        self.client_id = client_id
        self.username = username
        self.password = password

        self.session = self._create_session(tls_verify)

    def _create_session(self, tls_verify):
        token_url = TOKEN_URL_TEMPLATE.format(
            server=self.server, realm=urlquote(self.realm))
        refresh_url = token_url

        client = LegacyApplicationClient(client_id=self.client_id)
        session = OAuth2Session(client=client,
                                auto_refresh_url=refresh_url,
                                auto_refresh_kwargs={
                                    'client_id': self.client_id})

        session.verify = tls_verify
        token = session.fetch_token(token_url=token_url,
                                    username=self.username,
                                    password=self.password,
                                    client_id=self.client_id,
                                    verify=session.verify)

        return session


class KeycloakAnonymousConnection(KeycloakREST):

    def __init__(self, server, tls_verify):
        super(KeycloakAnonymousConnection, self).__init__(server, 'anonymous')
        self.session = self._create_session(tls_verify)


    def _create_session(self, tls_verify):
        session = requests.Session()
        session.verify = tls_verify

        return session

# ------------------------------------------------------------------------------


def do_server_info(options, conn):
    server_info = conn.get_server_info()
    print(json_pretty(server_info))


def do_list_realms(options, conn):
    realms = conn.get_realms()
    realm_names = get_realm_names_from_realms(realms)
    print('\n'.join(sorted(realm_names)))


def do_create_realm(options, conn):
    conn.create_realm(options.realm_name)


def do_delete_realm(options, conn):
    conn.delete_realm(options.realm_name)


def do_get_realm_metadata(options, conn):
    metadata = conn.get_realm_metadata(options.realm_name)
    print(metadata)


def do_list_clients(options, conn):
    clients = conn.get_clients(options.realm_name)
    client_ids = get_client_client_ids_from_clients(clients)
    print('\n'.join(sorted(client_ids)))


def do_create_client(options, conn):
    metadata = options.metadata.read()
    descriptor = conn.create_client(options.realm_name, metadata)


def do_register_client(options, conn):
    metadata = options.metadata.read()
    client_representation = conn.register_client(
        options.initial_access_token,
        options.realm_name, metadata)


def do_delete_client(options, conn):
    conn.delete_client_by_name(options.realm_name, options.client_name)

def do_client_test(options, conn):
    'experimental test code used during development'

    uri = 'https://openstack.jdennis.oslab.test:5000/v3/mellon/fooResponse'

    conn.remove_client_by_name_redirect_uri(options.realm_name,
                                            options.client_name,
                                            uri)

# ------------------------------------------------------------------------------

verbose_help = '''

The structure of the command line arguments is "noun verb" where noun
is one of Keycloak's data items (e.g. realm, client, etc.) and the
verb is an action to perform on the item. Each of the nouns and verbs
may have their own set of arguments which must follow the noun or
verb.

For example to delete the client XYZ in the realm ABC:

echo password | {prog_name} -s http://example.com:8080 -P - client delete -r ABC -c XYZ

where 'client' is the noun, 'delete' is the verb and -r ABC -c XYZ are
arguments to the delete action.

If the command completes successfully the exit status is 0. The exit
status is 1 if an authenticated connection with the server cannont be
successfully established. The exit status is 2 if the REST operation
fails.

The server should be a scheme://hostname:port URL.
'''


class TlsVerifyAction(argparse.Action):
    def __init__(self, option_strings, dest, nargs=None, **kwargs):
        if nargs is not None:
            raise ValueError("nargs not allowed")
        super(TlsVerifyAction, self).__init__(option_strings, dest, **kwargs)

    def __call__(self, parser, namespace, values, option_string=None):
        if values.lower() in ['true', 'yes', 'on']:
            verify = True
        elif values.lower() in ['false', 'no', 'off']:
            verify = False
        else:
            verify = values
            
        setattr(namespace, self.dest, verify)

def main():
    global logger
    result = 0

    parser = argparse.ArgumentParser(description='Keycloak REST client',
                    prog=prog_name,
                    epilog=verbose_help.format(prog_name=prog_name),
                    formatter_class=argparse.RawDescriptionHelpFormatter)

    parser.add_argument('-v', '--verbose', action='store_true',
                        help='be chatty')

    parser.add_argument('-d', '--debug', action='store_true',
                        help='turn on debug info')

    parser.add_argument('--show-traceback', action='store_true',
                        help='exceptions print traceback in addition to '
                             'error message')

    parser.add_argument('--log-file',
                        default='{prog_name}.log'.format(
                            prog_name=prog_name),
                        help='log file pathname')

    parser.add_argument('--permit-insecure-transport',  action='store_true',
                        help='Normally secure transport such as TLS '
                        'is required, defeat this check')

    parser.add_argument('--tls-verify', action=TlsVerifyAction,
                        default=True,
                        help='TLS certificate verification for requests to'
                        ' the server. May be one of case insenstive '
                        '[true, yes, on] to enable,'
                        '[false, no, off] to disable.'
                        'Or the pathname to a OpenSSL CA bundle to use.'
                        ' Default is True.')

    group = parser.add_argument_group('Server')

    group.add_argument('-s', '--server',
                       required=True,
                       help='DNS name or IP address of Keycloak server')

    group.add_argument('-a', '--auth-role',
                       choices=AUTH_ROLES,
                       default='root-admin',
                       help='authenticating as what type of user (default: root-admin)')

    group.add_argument('-u', '--admin-username',
                       default='admin',
                       help='admin user name (default: admin)')

    group.add_argument('-P', '--admin-password-file',
                       type=argparse.FileType('rb'),
                       help=('file containing admin password '
                             '(or use a hyphen "-" to read the password '
                             'from stdin)'))

    group.add_argument('--admin-realm',
                       default='master',
                       help='realm admin belongs to')

    cmd_parsers = parser.add_subparsers(help='available commands')

    # --- realm commands ---
    realm_parser = cmd_parsers.add_parser('realm',
                                          help='realm operations')

    sub_parser = realm_parser.add_subparsers(help='realm commands')

    cmd_parser = sub_parser.add_parser('server_info',
                                       help='dump server info')
    cmd_parser.set_defaults(func=do_server_info)

    cmd_parser = sub_parser.add_parser('list',
                                       help='list realm names')
    cmd_parser.set_defaults(func=do_list_realms)

    cmd_parser = sub_parser.add_parser('create',
                                       help='create new realm')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.set_defaults(func=do_create_realm)

    cmd_parser = sub_parser.add_parser('delete',
                                       help='delete existing realm')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.set_defaults(func=do_delete_realm)

    cmd_parser = sub_parser.add_parser('metadata',
                                       help='retrieve realm metadata')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.set_defaults(func=do_get_realm_metadata)

    # --- client commands ---
    client_parser = cmd_parsers.add_parser('client',
                                           help='client operations')

    sub_parser = client_parser.add_subparsers(help='client commands')

    cmd_parser = sub_parser.add_parser('list',
                                       help='list client names')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')

    cmd_parser.set_defaults(func=do_list_clients)

    cmd_parser = sub_parser.add_parser('create',
                                       help='create new client')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.add_argument('-m', '--metadata', type=argparse.FileType('rb'),
                            required=True,
                            help='SP metadata file or stdin')
    cmd_parser.set_defaults(func=do_create_client)

    cmd_parser = sub_parser.add_parser('register',
                                       help='register new client')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.add_argument('-m', '--metadata', type=argparse.FileType('rb'),
                            required=True,
                            help='SP metadata file or stdin')
    cmd_parser.add_argument('--initial-access-token', required=True,
                            help='realm initial access token for '
                            'client registeration')
    cmd_parser.set_defaults(func=do_register_client)

    cmd_parser = sub_parser.add_parser('delete',
                                       help='delete existing client')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.add_argument('-c', '--client-name', required=True,
                            help='client name')
    cmd_parser.set_defaults(func=do_delete_client)

    cmd_parser = sub_parser.add_parser('test',
                                       help='experimental test used during '
                                       'development')
    cmd_parser.add_argument('-r', '--realm-name', required=True,
                            help='realm name')
    cmd_parser.add_argument('-c', '--client-name', required=True,
                            help='client name')
    cmd_parser.set_defaults(func=do_client_test)

    # Process command line arguments
    options = parser.parse_args()
    configure_logging(options)

    if options.permit_insecure_transport:
        os.environ['OAUTHLIB_INSECURE_TRANSPORT'] = '1'

    # Get admin password
    options.admin_password = None

    # 1. Try password file
    if options.admin_password_file is not None:
        options.admin_password = options.keycloak_admin_password_file.readline().strip()
        options.keycloak_admin_password_file.close()

    # 2. Try KEYCLOAK_ADMIN_PASSWORD environment variable
    if options.admin_password is None:
        if (('KEYCLOAK_ADMIN_PASSWORD' in os.environ) and
            (os.environ['KEYCLOAK_ADMIN_PASSWORD'])):
            options.admin_password = os.environ['KEYCLOAK_ADMIN_PASSWORD']

    try:
        anonymous_conn = KeycloakAnonymousConnection(options.server,
                                                     options.tls_verify)

        admin_conn = KeycloakAdminConnection(options.server,
                                             options.auth_role,
                                             options.admin_realm,
                                             ADMIN_CLIENT_ID,
                                             options.admin_username,
                                             options.admin_password,
                                             options.tls_verify)
    except Exception as e:
        if options.show_traceback:
            traceback.print_exc()
        print(six.text_type(e), file=sys.stderr)
        result = 1
        return result

    try:
        if options.func == do_register_client:
            conn = admin_conn
        else:
            conn = admin_conn
        result = options.func(options, conn)
    except Exception as e:
        if options.show_traceback:
            traceback.print_exc()
        print(six.text_type(e), file=sys.stderr)
        result = 2
        return result

    return result

# ------------------------------------------------------------------------------

if __name__ == '__main__':
    sys.exit(main())
else:
    logger = logging.getLogger('keycloak-cli')
