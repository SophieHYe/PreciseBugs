# -*- coding: utf-8 -*-
from pysqlite2 import dbapi2 as sqlite3
import os.path

class mod_fun(object):
    def __init__(self,parent,config):
        self.config = config
        self.parent = parent
        parent.register_callback(u'!snack', self.snack, 'Several small fuckups')
        for key in config['items'].keys():
            words = config['items'][key]
            parent.register_callback('+%s'%key,self.factory('+',key, words),config['help']+" (%s verweist auf %s)" % (key, config['items'][key]))
            parent.register_callback('-%s'%key,self.factory('-',key, words),config['help']+" (%s verweist auf %s)" % (key, config['items'][key]))
            parent.register_callback('?%s'%key,self.factory('?',key, words),config['help']+" (%s verweist auf %s)" % (key, config['items'][key]))
        #parent.register_callback('+keks',self.keks,u'Verschenke einen Keks!')
        #parent.register_callback('-keks',self.klauen,u'Klaue jemanden einen Keks!')
        #parent.register_callback('?keks',self.readkeks,u'Sieh nach wieviele Kekse jemand hat.')
        
        dbpath = os.path.join(os.path.dirname(__file__), config['dbpath']).replace('\\','/')
        self.connectDB(dbpath)
        
    def factory(self, cmd, name, words):
        if (cmd=='+'):
            func = self.geben
        elif (cmd=='-'):
            func = self.klauen
        else:
            func = self.lesen
        def rss_func(params):
            func(params, name, words)
        return rss_func

    def connectDB(self, dbpath):
        self.parent.print_notice("SQLite-Database: "+dbpath)
        self.DBconn = sqlite3.connect(dbpath)
        self.DBcursor = self.DBconn.cursor()

    def snack(self, params):
        self.parent.privmsg(u':)', params.channel)

    def geben(self, params, name, words):
        if (self.check_name(params.args[0], params.channel)):
            return
        if len(params.args) == 1:
            if params.target == params.args[0]:
                keks = self.aendern(params.target, 0, name)
                self.parent.privmsg(u'Sei nicht so selbstsüchtig! Du hast bereits %i %s.' % (keks, words['plural']), params.channel)
                return
            keks = self.aendern(params.args[0], 1, name)
            self.parent.privmsg(u'%s hat jetzt %i %s.' % (params.args[0], keks, words['plural']), params.channel)
        else:
            self.parent.privmsg(u'hast du nicht was vergessen? (Du hast %i %s)' % (self.aendern(params.target, 0, name), words['plural']), params.channel)
    
    def klauen(self, params, name, words):
        if (self.check_name(params.args[0], params.channel)):
            return
        if len(params.args) == 1:
            if params.target == params.args[0]:
                self.parent.privmsg(u'Du klaust dir selber 1 %s. Das war eine drastische Änderung. NICHT!' % words['singular'], params.channel)
                return
            keks = self.aendern(params.args[0], -1, name)
            if (keks!=-999):
                keks2 = self.aendern(params.target, 1, name)
                if (keks == 0):
                    self.parent.privmsg(u'Na toll, du hast %s allerletztes %s geklaut. Du hast jetzt %i %s.' % (params.args[0], words['singular'], keks2, words['plural']), params.channel)
                else:
                    self.parent.privmsg(u'Du klaust %s einen %s (hat jetzt %i). Du hast jetzt %i %s' % (params.args[0], words['singular'], keks, keks2, words['plural']), params.channel)
            else:
                self.parent.privmsg(u'Geht nicht!', params.channel)
        else:
            self.parent.privmsg(u'hast du nicht was vergessen?', params.channel)
    
    def aendern(self, name, amount, item):
        keks = self.DBcursor.execute(u"SELECT nickname, count from kekse WHERE nickname like '%s' AND item =='%s' LIMIT 1" % (name, item)).fetchall()
        if(len(keks)>0 and len(keks[0]) > 1):
            keks = keks[0][1]
            if amount == 0:
                return keks
            keks2 = keks+amount
            if (keks2 >= 1):
                self.DBcursor.execute(u"UPDATE kekse set count=? WHERE item==? AND nickname like ?", (keks2, item, name))
                self.DBconn.commit()
                return keks2
            else:
                self.DBcursor.execute(u"UPDATE kekse set count=? WHERE nickname like ? AND item==?", (0, name, item))
                self.DBconn.commit()
                if keks>0:
                    return 0
                return -999
        else:
            if (amount >= 0):
                self.DBcursor.execute(u"INSERT INTO kekse (nickname, count, item) VALUES (?, ?, ?)", (name, amount, item))
                self.DBconn.commit()
                return amount
            else:
                return -999;
    
    def lesen(self, params, name, words):
        if len(params.args) == 1:
            keks = 0
            anrede = u"Du hast"
            if params.args[0]==params.target:
                keks = self.aendern(params.target, 0, name)
            else:
                keks = self.aendern(params.args[0],0, name)
                anrede=u"%s hat" % params.args[0]
            self.parent.privmsg(u"%s %i %s." % (anrede, keks, words['plural']), params.channel)
        elif len(params.args) == 0:
            keks = self.DBcursor.execute(u"SELECT `nickname`, `count` from kekse WHERE item=='%s' AND `count`>=1 ORDER BY `count` DESC LIMIT 10" % (name)).fetchall()
            if len(keks) <= 0:
                self.parent.privmsg(u"Es gibt noch keine %s?" % words['plural'], params.channel)
                return
            kekst = u"[Top-%s-Sammler] " % words['singular']
            first = True
            for keksi in keks:
                if len(keksi) > 1:
                    if first:
                        kekst += u"%s hat %i" % keksi
                        first = False
                    else:
                        kekst += u", %s hat %i" %keksi
            self.parent.privmsg(kekst, params.channel)
        else:
            self.parent.privmsg(u"Ich hab keine Ahnung was du mir damit sagen willst...", params.channel)
            return

    def check_name(self, target, channel):
        valid_names = self.parent.channels[channel]
        invalid = target not in valid_names
        if (invalid):
            self.parent.privmsg(u'%s ist gar nicht hier...' % target, channel)
        return invalid