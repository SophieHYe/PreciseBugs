# coding=utf-8
import praw
import sqlite3
import time

con = sqlite3.connect('estados_municipios.db')
cursor = con.cursor()

def dbLookup(msg):
    if len(msg.split(',')) != 2:
        #procura na lista de paises
        query = 'SELECT id FROM paises WHERE nome == "%s"' % (msg)
        cursor.execute(query)
        if cursor.fetchone():
            return True
        else:
            return False
    else:
        cidade = msg.split(',')[0].strip()
        estado = msg.split(',')[1].strip()
        #check cidade pertence ao estado
        query = 'SELECT estados.id FROM municipios JOIN estados ON municipios.estados_id == estados.id WHERE municipios.nome == "%s" AND estados.uf == "%s";' % (cidade, estado)
        cursor.execute(query)
        if not cursor.fetchone():
            return False
            
    return True
    

def main():
    r = praw.Reddit(user_agent='flairbotbr')
    r.login('botbr', 'apassword')
    if r.is_logged_in():
        print 'logged in'
    else:
        print 'failed to log in'
        return
    while True:
        time.sleep(0.5)
        for msg in r.get_unread(limit=None):
            try:
                print 'AUTHOR: %s - SUBJECT: %s - BODY: %s' % (msg.author, msg.subject, msg.body)
            except UnicodeEncodeError:
                print 'AUTHOR: %s - unprintable chars' % (msg.author)
            sub = r.get_subreddit('brasil')
            if msg.subject == 'flair':
                if dbLookup(msg.body):
                    estado = 'world' if len(msg.body.split(',')) < 2 else msg.body.split(',')[1].strip()
                    sub.set_flair(msg.author,msg.body,estado)
                    r.send_message(msg.author,'flair','Flair configurado.')
                    print('flair ok')
                else:
                    r.send_message(msg.author,'flair','Configuração de flair falhou.')
                    print('flair fail')
            if msg.subject == 'remover flair':
                sub.set_flair(msg.author,'','')
                r.send_message(msg.author,'flair','Flair removido.')
                print('remove flair ok')
                
            msg.mark_as_read()

if __name__ == '__main__':
    main()
    