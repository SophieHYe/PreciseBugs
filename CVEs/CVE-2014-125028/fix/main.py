from flask import Flask, render_template, request, session, redirect, make_response
import os, sys
import requests
import jwt
import uuid

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

  # state is used for CSRF protection. the client generates a value and stores it
  # for the user somewhere (in a cookie or in a session). it then passes the same value
  # in the state parameter in the authorize request. IDP will mirror the state value
  # to the redirect URI. the client should then make sure the state value it has stored
  # matches what it receives in the callback
  state = str(uuid.uuid4())

  authorize_url = 'https://stage-id.valtech.com/oauth2/authorize?response_type=%s&client_id=%s&scope=%s&state=%s' % ('code', CLIENT_ID, 'email openid', state)

  resp = make_response(redirect(authorize_url))
  resp.set_cookie('python-flask-csrf', state)
  return resp

@app.route('/sign-in/callback')
def sign_in_callback():
  code = request.args.get('code')
  state = request.args.get('state')

  if state != request.cookies.get('python-flask-csrf'):
    raise Exception("Possible CSRF detected (state does not match stored state)")

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

  resp = make_response(redirect('/'))
  resp.set_cookie('python-flask-csrf', '', expires=0)
  return resp

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
