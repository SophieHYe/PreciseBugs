import os
import re
import time
from html import escape
from urllib.parse import quote_plus as urlquote
import sqlite3 as sql
from hashlib import sha256
import asyncio
import mimetypes
from aiohttp import web, ClientSession
from kenny2automate.utils import DummyCtx
from kenny2automate.i18n import LANG, i18n

DISCORD_API = 'https://discordapp.com/api/v6'
LANG = {i: i18n(i, 'qqq') for i in LANG}
ONE_YEAR = 31557600
GLOBAL_GAMES = [
    'Go Fish', 'Connect 4',
    'Fight', 'Boggle', 'Uno',
    'Blackjack', 'Set', 'Chess',
    '007', 'Big Two'
]

class Handler:
    dtx = DummyCtx(author=DummyCtx(name='(server)'))

    def __init__(
            self, bot, db, logger, prefix,
            client_id, client_secret, web_root,
            document_root=os.path.abspath(os.path.dirname(__file__))
    ):
        self.bot = bot
        self.db = db
        self.logger = logger
        self.prefix = prefix
        self.sessions = {}
        self.client_id = client_id
        self.client_secret = client_secret
        self.web_root = web_root
        self.root = document_root
        self.app = web.Application()
        self.app.add_routes([
            web.get('/', self.index),
            web.get('/login', self.login),
            web.get('/settings', self.settings),
            web.post('/settings', self.save_settings),
            web.get('/servers', self.servers),
            web.get(r'/servers/{server:\d+}', self.server),
            web.post(r'/servers/{server:\d+}', self.save_server),
            web.get(r'/{name:.+(?<!\.html|..\.py)$}', self.file),
            web.get(r'/{name:.*}', self.notfound)
        ])

    def fil(self, path):
        return os.path.join(self.root, path)

    async def run(self):
        for k in self.db.execute('SELECT session_id FROM server_sessions').fetchall():
            self.sessions[k[0]] = ClientSession()
        self.runner = web.AppRunner(self.app)
        await self.runner.setup()
        site = web.TCPSite(self.runner, '0.0.0.0', 8080)
        await site.start()

    def run_sync(self):
        async def wakeup():
            while 1:
                try:
                    await asyncio.sleep(1)
                except:
                    return
        asyncio.get_event_loop().run_until_complete(self.run())
        asyncio.get_event_loop().run_until_complete(wakeup())
        asyncio.get_event_loop().run_until_complete(self.stop())

    async def stop(self):
        await self.runner.cleanup()
        for s in self.sessions.values():
            await s.close()

    async def checksesh(self, request, resp=None):
        sesh = request.cookies.get('session', sha256((request.remote + str(time.time())).encode('ascii')).hexdigest())
        sess = self.getsesh(sesh)
        if (
                not sess
                or time.time() - sess['last_use'] > ONE_YEAR
        ):
            sess = {
                'logged_in': None,
                'last_use': time.time(),
                'state': str(time.time())
            }
            if resp is not None:
                resp.set_cookie('session', sesh, max_age=ONE_YEAR)
                resp.set_cookie('state', sess['state'], max_age=ONE_YEAR)
            self.setsesh(sesh, sess)
            return sesh
        if sess['logged_in'] is not None:
            if time.time() > sess['logged_in'] + sess['expires_in']:
                data = {
                    'client_id': self.client_id,
                    'client_secret': self.client_secret,
                    'grant_type': 'refresh_token',
                    'refresh_token': sess['refresh_token'],
                    'redirect_uri': self.web_root + '/login',
                    'scope': 'identify guilds'
                }
                async with self.sessions[sesh].post(
                    DISCORD_API + '/oauth2/token',
                    data=data,
                    headers={'Content-Type':'application/x-www-form-urlencoded'}
                ) as r:
                    body = await r.json()
                    body['logged_in'] = time.time()
                    sess.update(body)
                await self.sessions[sesh].close()
                self.sessions[sesh] = ClientSession(headers={
                    'Authorization': '{} {}'.format(
                        sess['token_type'], sess['access_token']
                    )
                })
        sess['last_use'] = time.time()
        self.setsesh(sesh, sess)
        return None

    def getsesh(self, request):
        if not isinstance(request, str):
            request = request.cookies.get('session', None)
            if request is None:
                return {}
        return (self.db.execute(
            'SELECT session FROM server_sessions WHERE session_id=?',
            (request,)
        ).fetchone() or ([{}],))[0][0]

    def setsesh(self, request, sesh):
        if not isinstance(request, str):
            request = request.cookies.get('session', None)
            if request is None:
                return
        if not self.getsesh(request):
            self.db.execute(
                'INSERT INTO server_sessions VALUES (?, ?)',
                (request, [sesh])
            )
            self.sessions[request] = ClientSession()
        else:
            self.db.execute(
                'UPDATE server_sessions SET session=? WHERE session_id=?',
                ([sesh], request)
            )

    def checkuser(self, user_id):
        res = self.db.execute(
            'SELECT user_id FROM users WHERE user_id=?',
            (user_id,)
        ).fetchone()
        if res is None:
            self.db.execute(
                'INSERT INTO users (user_id) VALUES (?)',
                (user_id,)
            )

    def logged_in(self, request):
        return self.getsesh(request).get('logged_in', None) is not None

    def notfound(self, *_):
        raise web.HTTPNotFound(
            text=self.letext('404.html'),
            content_type='text/html'
        )

    async def elg(self, request):
        if (await self.checksesh(request)) is not None:
            if request.method != 'GET':
                raise web.HTTPSeeOther(str(request.path))
            self.notfound()
        if not self.logged_in(request):
            self.notfound()

    def lang(self, request):
        if not self.logged_in(request):
            available = set(LANG.keys())
            preferred = (j[0] for j in sorted((
                (i.group(1), float(i.group(2) or '1'))
                for i in re.finditer(
                    r'(?<![a-z])([a-z][a-z](?:-[a-z]+|[a-z])?)(?:\s*;\s*q=('
                    r'[01](?:\.[0-9])?))?(?:,\s*|$)',
                    request.headers.get('Accept-Language') or '',
                    re.I
                )
            ), key=lambda i: i[1], reverse=True))
            for i in preferred:
                if i in available:
                    return i
            return 'en'
        sesh = self.getsesh(request)
        self.checkuser(sesh['client']['id'])
        res = self.db.execute(
            'SELECT lang FROM users WHERE user_id=?',
            (sesh['client']['id'],)
        ).fetchone()
        if res is None or res['lang'] is None:
            return 'en'
        return res['lang']

    def letext(self, filename, title='kenny2automate'):
        with open(self.fil('template.html')) as f1, open(self.fil(filename)) as f2:
            return f1.read().format(title, f2.read())

    async def index(self, request):
        resp = web.Response(content_type='text/html')
        sesh = await self.checksesh(request, resp)
        lan = self.lang(request)
        if not self.logged_in(request):
            resp.text = self.letext(
                'notloggedin.html',
                i18n(lan, 'server/notloggedin;h1')
            ).format(
                self.getsesh(sesh or request)['state'],
                self.client_id,
                urlquote(self.web_root + '/login'),
                h1=i18n(lan, 'server/notloggedin;h1'),
                p=i18n(lan, 'server/notloggedin;p'),
                login=i18n(lan, 'server/notloggedin;login')
            )
            return resp
        h1 = i18n(
            lan, 'server/index;h1',
            self.getsesh(request)['client']['username']
        )
        resp.text = self.letext(
            'index.html',
            h1
        ).format(
            h1=h1,
            settings=i18n(lan, 'server/index;settings'),
            servers=i18n(lan, 'server/index;servers')
        )
        return resp

    async def login(self, request):
        #if (
        #        (await self.checksesh(request)) is not None
        #        or 'code' not in request.query
        #        or self.getsesh(request)['state'] != request.query.get('state', '')
        #):
        #    self.notfound()
        sesh = await self.checksesh(request)
        if sesh is not None:
            self.notfound()
        if 'code' not in request.query:
            self.notfound()
        if self.getsesh(request)['state'] != request.query.get('state', ''):
            self.notfound()
        sesh = request.cookies['session']
        sess = self.getsesh(request)
        data = {
            'client_id': self.client_id,
            'client_secret': self.client_secret,
            'grant_type': 'authorization_code',
            'code': request.query['code'],
            'redirect_uri': self.web_root + '/login',
            'scope': 'identify guilds'
        }
        async with self.sessions[sesh].post(
                DISCORD_API + '/oauth2/token',
                data=data
        ) as resp:
            body = await resp.json()
            body['logged_in'] = time.time()
            sess.update(body)
        await self.sessions[sesh].close()
        self.sessions[sesh] = ClientSession(headers={
            'Authorization': '{} {}'.format(
                sess['token_type'], sess['access_token']
            )
        })
        async with self.sessions[sesh].get(DISCORD_API + '/users/@me') as resp:
            sess['client'] = await resp.json()
            sess['client']['id'] = int(sess['client']['id'])
        async with self.sessions[sesh].get(DISCORD_API + '/users/@me/guilds') as resp:
            sess['servers'] = await resp.json()
        self.setsesh(request, sess)
        resp = web.HTTPSeeOther('/')
        resp.set_cookie('code', request.query['code'])
        raise resp

    async def settings(self, request):
        await self.elg(request)
        user_id = self.getsesh(request)['client']['id']
        self.checkuser(user_id)
        data = self.db.execute(
            'SELECT prefix, lang, games_ping FROM users WHERE user_id=?',
            (user_id,)
        ).fetchone()
        if data is not None:
            prefix, lang, games = data
        else:
            prefix, lang, games = data, data, data
        if prefix is None:
            prefix = self.prefix
        games = (games or '').split('|')
        options = ''.join('<option value="{}"{}>{}</option>'.format(
            i, ' selected' if i == lang else '', j
        ) for i, j in LANG.items())
        options = '<option value=""{}>{}</option>'.format(
            ' selected' if lang is None else '',
            i18n(lang or 'en', 'server/lang-auto')
        ) + options
        ping_th = ''.join(
            '<th>{}</th>'.format(i)
            for i in GLOBAL_GAMES
        )
        ping_th = '<tr><th colspan="{}">{}</th></tr>\n<tr>'.format(
            len(GLOBAL_GAMES),
            i18n(lang or 'en', 'server/ping-message')
        ) + ping_th + '</tr>'
        ping_options = '\n'.join(
            """        <td><label class="switch">
        <input name="ping" type="checkbox" value="{}" {}/>
        <span class="slider"></span>
    </label></td>""".format(g, 'checked ' if g in games else '')
            for g in GLOBAL_GAMES
        )
        return web.Response(
            text=self.letext(
                'settings.html',
                i18n(lang or 'en', 'server/settings;h1')
            ).format(
                escape(prefix),
                options,
                ping_th,
                ping_options,
                h1=i18n(lang or 'en', 'server/settings;h1'),
                prefix=i18n(lang or 'en', 'server/settings;prefix'),
                lang=i18n(lang or 'en', 'server/settings;lang'),
                save=i18n(lang or 'en', 'server/server;save'),
                back=i18n(lang or 'en', 'server/server;back'),
            ),
            content_type='text/html'
        )

    async def save_settings(self, request):
        await self.elg(request)
        data = await request.post()
        for k in ('prefix', 'lang', 'ping'):
            if k not in data:
                self.notfound()
        user_id = self.getsesh(request)['client']['id']
        self.checkuser(user_id)
        with self.db.connection:
            self.db.execute(
                'UPDATE users SET prefix=?, lang=?, games_ping=? WHERE user_id=?',
                (
                    data['prefix'] if data['prefix'].strip() else None,
                    data['lang'].strip() or None,
                    '|'.join(data.getall('ping')),
                    user_id
                )
            )
        raise web.HTTPSeeOther(str(request.path))

    async def servers(self, request):
        await self.elg(request)
        sess = self.getsesh(request)
        lan = self.lang(request)
        guilds = tuple(filter(
            lambda i: (
                i and i.get_member(
                    int(sess['client']['id'])
                ).guild_permissions.administrator
            ), (
                self.bot.get_guild(int(i['id']))
                for i in sess['servers']
            )
        ))
        options = '<span class="spacer"></span>'.join("""
<a class="guild" href="{}/{}" title="{}">
    <img src="{}" />
</a>
""".strip().format(
            str(request.path), i.id, escape(i.name), i.icon_url_as(format='png', size=64)
        ) for i in guilds)
        return web.Response(
            text=self.letext(
                'servers.html',
                i18n(lan, 'server/servers;h1')
            ).format(
                options,
                h1=i18n(lan, 'server/servers;h1'),
                div=i18n(lan, 'server/servers;div'),
                back=i18n(lan, 'server/server;back'),
            ),
            content_type='text/html'
        )

    async def server(self, request):
        await self.elg(request)
        guild = self.bot.get_guild(int(request.match_info.get('server', '0')))
        if guild is None:
            self.notfound()
        if not guild.get_member(
            int(self.getsesh(request)['client']['id'])
        ).guild_permissions.administrator:
            self.notfound()
        lan = self.lang(request)
        options = """
        <tr>
            <th rowspan="2">{}</th>
            <th rowspan="2">{}</th>
            <th colspan="{}">{}</th>
        </tr><tr>
            {}
        </tr>""".format(
            i18n(lan, 'server/server;channel'),
            i18n(lan, 'server/server;language'),
            len(GLOBAL_GAMES),
            i18n(lan, 'server/ping-message'),
            '\n'.join('<td>{}</td>'.format(i) for i in GLOBAL_GAMES),
        )
        non = i18n(lan, 'server/lang-none')
        for i in guild.text_channels:
            lang = self.db.execute(
                'SELECT lang, games_ping FROM channels WHERE channel_id=?',
                (i.id,)
            ).fetchone()
            if lang is None:
                self.db.execute(
                    'INSERT INTO channels (channel_id) VALUES (?)',
                    (i.id,)
                )
                lang, games = lang, []
            else:
                lang, games = lang
                games = (games or '').split('|')
            lang_options = '\n'.join('<option value="lang={}"{}>{}</option>'.format(
                a, ' selected' if a == lang else '', b
            ) for a, b in LANG.items())
            lang_options = '<option value="lang="{}>{}</option>\n'.format(
                ' selected' if lang is None else '', non
            ) + lang_options
            ping_options = '\n'.join(
                """        <td><label class="switch">
            <input name="channel-{0}" type="checkbox" value="ping={1}" {2}/>
            <span class="slider"></span>
        </label></td>""".format(i.id, g, 'checked ' if g in games else '')
                for g in GLOBAL_GAMES
            )
            options += """
    <tr>
        <td class="channel"><div># {0}</div></td>
        <td><select name="channel-{1}">
            {2}
        </select></td>
        {3}
    </div></td></tr>""".format(
                i.name, i.id, lang_options, ping_options
            )
        res = self.db.execute(
            'SELECT guild_disabled_commands, guild_disabled_cogs, words_censor \
FROM guilds WHERE guild_id=?',
            (guild.id,)
        ).fetchone()
        if res is None:
            cmds, cogs, censor = [], [], ''
            self.db.execute(
                'INSERT INTO guilds (guild_id) VALUES (?)',
                (guild.id,)
            )
        else:
            cmds, cogs, censor = res
            cmds = (cmds or '').split(',')
            cogs = (cogs or '').split(',')
            censor = censor or ''
        dcmds = ''
        def recurse_commands(thing):
            nonlocal dcmds
            if hasattr(thing, 'commands'):
                for cmd in thing.commands:
                    hide = False
                    parent = cmd.parent
                    while parent:
                        if parent.qualified_name in cmds:
                            hide = True
                            break
                        parent = parent.parent
                    dcmds += """
            <option
                data-parent="{parent}"
                data-cog="{cog}"
                value="{option}"
                style="{display}"
            >
                {prefix}{option}
            </option>""".format(
                        option=cmd.qualified_name,
                        parent=cmd.parent.qualified_name if cmd.parent else '',
                        cog=cmd.cog_name or 'None',
                        display=(
                            'display: none'
                            if (
                                hide or cmd.cog_name in cogs
                            )
                            else ''
                        ),
                        prefix=self.bot.command_prefix(self.bot, None)
                    )
                    recurse_commands(cmd)
        recurse_commands(self.bot)
        dcogs = """
    <option value="None">
        {}
    </option>""".format(non)
        for cog in self.bot.cogs.keys():
            dcogs += """
    <option value={option}>
        {option}
    </option>""".format(option=cog)
        h1 = i18n(lan, 'server/server;h1', escape(guild.name))
        return web.Response(
            text=self.letext(
                'server.html',
                h1
            ).format(
                channels=options,
                cmds=dcmds,
                cogs=dcogs,
                dcmds=cmds,
                dcogs=cogs,
                jcmds=','.join(cmds),
                jcogs=','.join(cogs),
                words_censor=i18n(
                    lan, 'words/server-censor-title',
                    '<code>{}{}</code>'.format(
                        self.bot.command_prefix(self.bot, None),
                        'words'
                    ),
                ),
                censor=censor,
                cmd=i18n(lan, 'server/server;command'),
                cog=i18n(lan, 'server/server;cog'),
                disabled=i18n(lan, 'server/server;disabled'),
                h1=h1,
                save=i18n(lan, 'server/server;save'),
                back=i18n(lan, 'server/server;back'),
            ),
            content_type='text/html'
        )

    async def save_server(self, request):
        await self.elg(request)
        guild = self.bot.get_guild(int(request.match_info.get('server', '0')))
        if guild is None:
            self.notfound()
        if not guild.get_member(
            int(self.getsesh(request)['client']['id'])
        ).guild_permissions.administrator:
            self.notfound()
        data = await request.post()
        params = []
        otherparams = {}
        for k in data.keys():
            if not k.startswith('channel-'):
                otherparams[k] = ','.join(data.getall(k))
                continue
            param = {'channel_id': int(k[len('channel-'):])}
            for v in data.getall(k):
                v = v.partition('=')
                if v[0] == 'ping':
                    if 'ping' not in param:
                        param['ping'] = set()
                    param['ping'].add(v[-1])
                else:
                    param[v[0]] = v[-1] or None
            param['ping'] = '|'.join(param.get('ping', ())) or None
            params.append(param)
        otherparams['guild_id'] = guild.id
        try:
            with self.db.connection:
                self.db.executemany(
                    'UPDATE channels SET lang=:lang, games_ping=:ping \
WHERE channel_id=:channel_id',
                    params
                )
                self.db.execute(
                    'UPDATE guilds SET guild_disabled_commands=:disable_cmd, \
guild_disabled_cogs=:disable_cog, words_censor=:words_censor WHERE guild_id=:guild_id',
                    otherparams
                )
        except sql.ProgrammingError as exc:
            raise web.HTTPBadRequest(reason=str(exc))
        raise web.HTTPSeeOther(request.path)

    async def file(self, request):
        path = request.match_info.get('name', '.html') or '.html'
        fullpath = self.fil(path)
        if os.path.isfile(fullpath):
            with open(fullpath, 'rb') as f:
                #self.logger.info('Request serving: {}'.format(path), extra={'ctx': self.dtx})
                return web.Response(
                    status=200,
                    body=f.read(),
                    content_type=mimetypes.guess_type(fullpath)[0]
                )
        else:
            #self.logger.error('Request not served, 404: {}'.format(path), extra={'ctx': self.dtx})
            self.notfound()

#Handler(None, None, None, 512581527343726592, 't5jgg5udqQrdiJe_bKHrn0VrEDMztpZ7').run_sync()
