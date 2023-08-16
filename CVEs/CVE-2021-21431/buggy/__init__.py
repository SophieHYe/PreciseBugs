"""channelmgnt.py - Sopel Channel Management Plugin."""

import re
import time

from MirahezeBots_jsonparser import jsonparser as jp

from sopel import formatting
from sopel.config.types import StaticSection, ValidatedAttribute
from sopel.module import (
    OP, commands, event, example, priority, require_admin, require_chanmsg,
)
from sopel.tools import Identifier
from sopel.tools import SopelMemory

"""
Modified from adminchannel.py - Sopel Channel Admin Module
Copyright 2010-2011, Michael Yanovich, Alek Rollyson, and Elsie Powell
Copyright © 2012, Elad Alfassa <elad@fedoraproject.org>
Licensed under the Eiffel Forum License 2.
https://sopel.chat
"""


class ChannelmgntSection(StaticSection):
    """Configuration class for channelmgnt."""

    datafile = ValidatedAttribute('datafile', str)
    support_channel = ValidatedAttribute('support_channel', str)
    forwardchan = ValidatedAttribute('forwardchan', str)


def setup(bot):
    """Set up config and bot memory for the plugin."""
    bot.config.define_section('channelmgnt', ChannelmgntSection)
    bot.memory['channelmgnt'] = SopelMemory()
    bot.memory['channelmgnt']['jdcache'] = jp.createdict(bot.settings.channelmgnt.datafile)


def configure(config):
    """Define sopel config wizzard questions."""
    config.define_section('channelmgnt', ChannelmgntSection, validate=False)
    config.channelmgnt.configure_setting('datafile', 'Where is the datafile for channelmgnt?')
    config.channelmgnt.configure_setting('support_channel', 'What channel should users ask for help in?')
    config.channelmgnt.configure_setting('forwardchan', 'What channel should users be forwarded to, for fix your connection bans?')


def default_mask(trigger):
    """Build default topic mask."""
    welcome = formatting.color('Welcome to:', formatting.colors.PURPLE)
    chan = formatting.color(trigger.sender, formatting.colors.TEAL)
    topic_ = formatting.bold('Topic:')
    topic_ = formatting.color('| ' + topic_, formatting.colors.PURPLE)
    arg = formatting.color('{}', formatting.colors.GREEN)
    return f'{welcome} {chan} {topic_} {arg}'


def chanopget(channeldata, chanopsjson):
    """Get chanop data for the given channel."""
    chanops = []
    if 'default' in chanopsjson.keys():
        defaultops = channelparse(channel='default', cachedjson=chanopsjson)
        if 'chanops' in defaultops[0].keys():
            chanops = chanops + defaultops[0]['chanops']
    if 'inherits-from' in channeldata.keys():
        for x in channeldata['inherits-from']:
            y = channelparse(channel=x, cachedjson=chanopsjson)
            chanops = chanops + y[0]['chanops']
    if 'chanops' in channeldata.keys():
        chanops = chanops + (channeldata['chanops'])
    if chanops == []:
        return False
    return chanops


def logchanget(channeldata, chanopsjson):
    """Get logging channel for the given channel."""
    log_channel = []
    if 'default' in chanopsjson.keys():
        defaultchan = channelparse(channel='default', cachedjson=chanopsjson)
        if 'log_channel' in defaultchan[0].keys():
            log_channel = (defaultchan[0]['log_channel'])
    if 'log_channel' in channeldata.keys():
        log_channel = (channeldata['log_channel'])
    if log_channel == []:
        return False
    return log_channel


def channelparse(channel, cachedjson):
    """Get json data for a specific channel."""
    if channel in cachedjson.keys():
        channeldata = cachedjson[channel]
        return channeldata, cachedjson
    return False


def get_chanops(channel, cachedjson):
    """Get chanop data for the provided channel."""
    channeldata = channelparse(channel=channel, cachedjson=cachedjson)
    if not channeldata:
        defaultops = channelparse(channel='default', cachedjson=cachedjson)
        if 'chanops' in defaultops[0].keys():
            return defaultops[0]['chanops']
        return False
    return chanopget(channeldata[0], channeldata[1])


def get_log_channel(channel, cachedjson):
    """Get logging channel for the given channel."""
    channeldata = channelparse(channel='default', cachedjson=cachedjson)
    if not channeldata:
        defaultchan = channelparse(channel=channel, cachedjson=cachedjson)
        if 'log_channel' in defaultchan[0].keys():
            return defaultchan[0]['log_channel']
        return False
    return logchanget(channeldata[0], channeldata[1])


def deopbot(chan, bot):
    """Deop the bot in the given channel."""
    bot.write(['MODE', chan, '-o', bot.nick])


def makemodechange(bot, trigger, mode, isusermode=False, isbqmode=False, selfsafe=False):
    """Change the channel mode."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Attempting to OP...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
        if (isusermode and not trigger.group(2) and selfsafe
           or isusermode and not trigger.group(2) and trigger.account in chanops):
            bot.write(['MODE', trigger.sender, mode, trigger.nick])
            if dodeop:
                deopbot(trigger.sender, bot)
        elif isusermode and trigger.account in chanops:
            bot.write(['MODE', trigger.sender, mode, trigger.group(2)])
            if dodeop:
                deopbot(trigger.sender, bot)
        elif isbqmode and trigger.account in chanops:
            bot.write(['MODE', trigger.sender, mode, parse_host_mask(trigger.group().split())])
            if dodeop:
                deopbot(trigger.sender, bot)
        elif trigger.account in chanops:
            bot.write(['MODE', trigger.sender, mode])
            if dodeop:
                deopbot(trigger.sender, bot)
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')

    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


@require_chanmsg
@commands('chanmode')
@example('.chanmode +mz')
def chanmode(bot, trigger):
    """Command to change channel mode."""
    makemodechange(bot, trigger, trigger.group(2), isusermode=False)


@require_chanmsg
@commands('op')
@example('.op Zppix')
def op(bot, trigger):
    """Command to op users in a room. If no nick is given, Sopel will op the nick who sent the command."""
    makemodechange(bot, trigger, '+o', isusermode=True)


@require_chanmsg
@commands('deop')
@example('.deop Zppix')
def deop(bot, trigger):
    """Command to deop users in a room. If no nick is given, Sopel will deop the nick who sent the command."""
    makemodechange(bot, trigger, '-o', isusermode=True, selfsafe=True)


@require_chanmsg
@commands('voice')
@example('.voice Zppix')
def voice(bot, trigger):
    """Command to voice users in a room. If no nick is given, Sopel will voice the nick who sent the command."""
    makemodechange(bot, trigger, '+v', isusermode=True)


@require_chanmsg
@commands('devoice')
@example('.devoice Zppix')
def devoice(bot, trigger):
    """Command to devoice users in a room. If no nick is given, the nick who sent the command will be devoiced."""
    makemodechange(bot, trigger, '-v', isusermode=True, selfsafe=True)


@require_chanmsg
@commands('kick')
@priority('high')
@example('.kick Zppix')
def kick(bot, trigger):
    """Kick a user from the channel."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Please wait...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
        text = trigger.group().split()
        argc = len(text)
        if argc < 2:
            return
        nick = Identifier(text[1])
        reason = ' '.join(text[2:])
        if nick != bot.config.core.nick and trigger.account in chanops:
            bot.write(['KICK', trigger.sender, nick, ':' + reason])
            if dodeop:
                deopbot(trigger.sender, bot)
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')
    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


def parse_host_mask(text):
    """Identify hostmask."""
    argc = len(text)
    if argc >= 2:
        opt = Identifier(text[1])
        mask = opt
        if not opt.is_nick() and argc < 3:
            return None
        if not opt.is_nick():
            mask = text[2]
        if re.match('^[^.@!/]+$', mask) is not None:
            return f'{mask}!*@*'
        if re.match('^[^@!]+$', mask) is not None:
            return f'*!*@{mask}'

        m = re.match('^([^!@]+)@$', mask)
        if m is not None:
            return f'*!{m.group(1)}@*'

        m = re.match('^([^!@]+)@([^@!]+)$', mask)
        if m is not None:
            return f'*!{m.group(1)}@{m.group(2)}'

        m = re.match('^([^!@]+)!(^[!@]+)@?$', mask)
        if m is not None:
            return f'{m.group(1)}!{m.group(2)}@*'

        return ''
    return None


@require_chanmsg
@commands('ban')
@priority('high')
@example('.ban Zppix')
def ban(bot, trigger):
    """Ban a user from the channel. The bot must be a channel operator for this command to work."""
    makemodechange(bot, trigger, '+b', isbqmode=True)


@require_chanmsg
@commands('unban')
@example('.unban Zppix')
def unban(bot, trigger):
    """Unban a user from the channel. The bot must be a channel operator for this command to work."""
    makemodechange(bot, trigger, '-b', isbqmode=True)


@require_chanmsg
@commands('quiet')
@example('.quiet Zppix')
def quiet(bot, trigger):
    """Quiet a user. The bot must be a channel operator for this command to work."""
    makemodechange(bot, trigger, '+q', isbqmode=True)


@require_chanmsg
@commands('unquiet')
@example('.unquiet Zppix')
def unquiet(bot, trigger):
    """Unquiet a user. The bot must be a channel operator for this command to work."""
    makemodechange(bot, trigger, '-q', isbqmode=True)


@require_chanmsg
@commands('kickban', 'kb')
@example('.kickban user1 user!*@* get out of here')
@priority('high')
def kickban(bot, trigger):
    """Kick and ban a user from the channel. The bot must be a channel operator for this command to work."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Please wait...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
        text = trigger.group().split()
        argc = len(text)
        if argc < 3:
            bot.reply('Syntax is: .kickban <nick> <reason>')
            if dodeop:
                deopbot(trigger.sender, bot)
            return
        nick = Identifier(text[1])
        mask = text[2] if any(s in text[2] for s in '!@*') else ''
        reasonidx = 3 if mask != '' else 2
        reason = ' '.join(text[reasonidx:])
        mask = parse_host_mask(trigger.group().split())
        if mask == '':
            mask = nick + '!*@*'
        if trigger.account in chanops:
            bot.write(['MODE', trigger.sender, '+b', mask])
            bot.write(['KICK', trigger.sender, nick, ':' + reason])
            if dodeop:
                deopbot(trigger.sender, bot)
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')
    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


def get_mask(bot, channel, default):
    """Get mask for given channel."""
    return (bot.db.get_channel_value(channel, 'topic_mask') or default).replace('%s', '{}')


@require_chanmsg
@commands('topic')
@example('.topic Your Great New Topic')
def topic(bot, trigger):
    """Change the channel topic. The bot must be a channel operator for this command to work."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Please wait...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
        if not trigger.group(2):
            return None
        channel = trigger.sender.lower()

        mask = get_mask(bot, channel, default_mask(trigger))
        narg = len(re.findall('{}', mask))

        top = trigger.group(2)
        args = []
        args = top.split('~', narg)

        if len(args) != narg:
            message = f'Not enough arguments. You gave {args}, it requires {narg}.'
            if dodeop:
                deopbot(trigger.sender, bot)
            return bot.say(message)
        topictext = mask.format(*args)
        if trigger.account in chanops:
            bot.write(('TOPIC', channel + ' :' + topictext))
            if dodeop:
                deopbot(trigger.sender, bot)
        else:
            return bot.reply('Access Denied. If in error, please contact the channel founder.')
    return bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


@require_chanmsg
@commands('tmask')
@example('.tmask Welcome to My Channel | Info: {}')
def set_mask(bot, trigger):
    """Set the topic mask to use for the current channel."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    if chanops:
        if trigger.account in chanops:
            bot.db.set_channel_value(trigger.sender, 'topic_mask', trigger.group(2))
            bot.say(f'Gotcha, {trigger.account}')
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')
    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


@require_chanmsg
@commands('showmask')
@example('showmask')
def show_mask(bot, trigger):
    """Show the topic mask for the current channel."""
    mask = bot.db.get_channel_value(trigger.sender, 'topic_mask')
    mask = mask or default_mask(trigger)
    bot.say(mask)


@require_chanmsg
@commands('invite')
def invite_user(bot, trigger):
    """Command to invite users to a room."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    channel = trigger.sender
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Please wait...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
            nick = trigger.group(2)
        if not nick:
            bot.say(f'{trigger.account}: No user specified.', trigger.sender)
        elif trigger.account in chanops:
            bot.write(['INVITE', channel, nick])
            if dodeop:
                deopbot(trigger.sender, bot)
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')
    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


@require_chanmsg
@commands('fyc', 'fixconnection')
@example('.fyc nick')
@priority('high')
def fyckb(bot, trigger):
    """Ban a user from the channel, forwards user to specified channel until unbanned. The bot must be a channel operator for this command to work."""
    chanops = get_chanops(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    dodeop = False
    if chanops:
        if bot.channels[trigger.sender].privileges[bot.nick] < OP and trigger.account in chanops:
            bot.say('Please wait...')
            bot.say('op ' + trigger.sender, 'ChanServ')
            time.sleep(1)
            dodeop = True
            text = trigger.group().split()
            nick = Identifier(text[1])
            mask = parse_host_mask(text)
            if mask == '':
                mask = nick + '!*@*'
            bot.write(['MODE', trigger.sender, '+b', f'{mask}${bot.settings.channelmgnt.forwardchan}'])
        else:
            bot.reply('Access Denied. If in error, please contact the channel founder.')
        if dodeop:
            deopbot(trigger.sender, bot)
    else:
        bot.reply(f'No ChanOps Found. Please ask for assistance in {bot.settings.channelmgnt.support_channel}')


@event('KICK')
def log_kick(bot, trigger):
    """Log blocks to a certain channel if specified in json."""
    logging_channel = get_log_channel(str(trigger.sender), bot.memory['channelmgnt']['jdcache'])
    greentext = f'kicked from {trigger.args[0]} by {trigger.nick} ({trigger.args[2]})'
    if logging_channel:
        bot.say(f'{formatting.bold(trigger.args[1])} was {formatting.color(text=greentext, fg="GREEN")}', logging_channel)


@require_admin(message='Only admins may purge cache.')
@commands('resetchanopcache')
def reset_chanop_cache(bot, trigger):  # noqa: U100
    """Reset the cache of the channel management data file."""
    bot.reply('Refreshing Cache...')
    bot.memory['channelmgnt']['jdcache'] = jp.createdict(bot.settings.channelmgnt.datafile)
    bot.reply('Cache refreshed')


@require_admin(message='Only admins may check cache')
@commands('checkchanopcache')
def check_chanop_cache(bot, trigger):  # noqa: U100
    """Validate the cache matches the copy on disk."""
    result = jp.validatecache(bot.settings.channelmgnt.datafile, bot.memory['channelmgnt']['jdcache'])
    if result:
        return bot.reply('Cache is correct.')
    return bot.reply('Cache does not match on-disk copy')
