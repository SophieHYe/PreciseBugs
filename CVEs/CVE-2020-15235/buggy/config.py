import time
from pydoc import locate

from django.conf import settings

DEFAULT_CONFIG = {
    'config_version': 4,
    'flag_prefix': 'ractf',
    'graph_members': 10,
    'register_end_time': -1,
    'end_time': time.time() + 7 * 24 * 60 * 60,
    'start_time': time.time(),
    'register_start_time': time.time(),
    'team_size': -1,
    'email_regex': '',
    'email_domain': '',
    'login_provider': 'basic_auth',
    'registration_provider': 'basic_auth',
    'token_provider': 'basic_auth',
    'enable_bot_users': True,
    'enable_ctftime': True,
    'enable_flag_submission': True,
    'enable_flag_submission_after_competition': True,
    'enable_force_admin_2fa': False,
    'enable_track_incorrect_submissions': True,
    'enable_login': True,
    'enable_prelogin': True,
    'enable_maintenance_mode': False,
    'enable_registration': True,
    'enable_scoreboard': True,
    'enable_scoring': True,
    'enable_solve_broadcast': True,
    'enable_teams': True,
    'enable_team_join': True,
    'enable_view_challenges_after_competion': True,
    'enable_team_leave': False,
    'invite_required': False,
    'hide_scoreboard_at': -1,
    'setup_wizard_complete': False,
    'sensitive_fields': ['sensitive_fields', 'enable_force_admin_2fa']
}

backend = locate(settings.CONFIG['BACKEND'])()
backend.load(defaults=DEFAULT_CONFIG)


def get(key):
    return backend.get(key)


def set(key, value):
    backend.set(key, value)


def get_all():
    return backend.get_all()


def get_all_non_sensitive():
    sensitive = backend.get('sensitive_fields')
    config = backend.get_all()
    for field in sensitive:
        del config[field]
    return config


def set_bulk(values: dict):
    for key, value in values.items():
        set(key, value)


def add_plugin_config(name, config):
    DEFAULT_CONFIG[name] = config
