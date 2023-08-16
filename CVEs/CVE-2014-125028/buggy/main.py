from flask import Flask, render_template, request, session, redirect
import os, sys
import requests
import jwt

CLIENT_ID = 'valtech.idp.testclient.local'
CLIENT_SECRET = os.environ.get('CLIENT_SECRET')

if CLIENT_SECRET is None:
  print 'CLIENT_SECRET missing. Start using "CLIENT_SECRET=very_secret_secret python main.py"'
  sys.exit(-1)

app = Flask(__name__, static_url_path='')

@app.route('/')
def index():
  signed_in = session.get('signed_in') != None
  header = 'Not signed in'
  text = 'Click the button below to sign in.'

  if signed_in:
    header = 'Welcome!'
    text = 'Signed in as %s.' % session['email']

  return render_template('index.html', header=header, text=text)

@app.route('/sign-in')
def sign_in():
  if session.get('signed_in') != None: return redirect('/')
  authorize_url = 'https://stage-id.valtech.com/oauth2/authorize?response_type=%s&client_id=%s&scope=%s' % ('code', CLIENT_ID, 'email openid')
  return redirect(authorize_url)

@app.route('/sign-in/callback')
def sign_in_callback():
  code = request.args.get('code')

  # as both scope openid and email was requested on authorize request above, the client
  # will receive both an access_token (according to OAuth 2) AND an id_token (according to OpenID Connect)
  tokens = exchange_code_for_tokens(code)

  # if the client only need authentication (and not authorization), the access token can be ignored
  # (but it is still possible to use it if client wants to, and is left here for documentation)
  #user_info = fetch_user_info(tokens['access_token'])

  # as this example app is only interested in who logged in, we will parse the id_token.
  # currently, IDP does not sign id_tokens, but as IDP uses https this is no problem
  # (but the id_token should not be passed around in plaintext where it can be modified by a man-in-the-middle)
  user_info = jwt.decode(tokens["id_token"], verify=False)

  session['signed_in'] = True
  session['email'] = user_info['email']
  
  return redirect('/')

@app.route('/sign-out')
def sign_out():
  session.clear()
  return redirect('https://stage-id.valtech.com/oidc/end-session?client_id=%s' % CLIENT_ID)

def exchange_code_for_tokens(code):
  data = {
    'grant_type': 'authorization_code',
    'code': code,
    'client_id': CLIENT_ID,
    'client_secret': CLIENT_SECRET
  }

  res = requests.post('https://stage-id.valtech.com/oauth2/token', data=data)
  return res.json()

def fetch_user_info(access_token):
  res = requests.get('https://stage-id.valtech.com/api/users/me', headers={ 'Authorization': 'Bearer %s' % access_token })
  return res.json()

if __name__ == '__main__':
  app.secret_key = 'someverysecretkey'
  app.run(host='0.0.0.0', debug=True)
