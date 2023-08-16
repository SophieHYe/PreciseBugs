#!/bin/env python
from flask import Flask
from flask import flash,request,send_file,send_from_directory,redirect,render_template, abort

from werkzeug.utils import secure_filename
from ipaddress import ip_address, ip_network
import urllib.request

import os
import random

import hooker

hooker.EVENTS.append("pre_process",
  help="Before any processing of the URL alias starts. Useful for UA filters, blacklists, etc")
hooker.EVENTS.append("pre_file",
  help="Before the alias resolves to a file")
hooker.EVENTS.append("pre_response",
  help="Before the created request is sent")

import wormnest.db_handler as db_handler
import wormnest.utils as utils

'''
To run the App:
python3 app.py
'''
app = Flask(__name__)

CONFIG = utils.parse_config()

app.config['UPLOAD_FOLDER'] = CONFIG['SRV_DIR']
print(CONFIG['IP_WHITELIST'])
# sys.exit(10)

def get_random_alias(length=None):
  assert CONFIG['ALIAS_DIGITS_MIN'] <= CONFIG['ALIAS_DIGITS_MAX']
  if length == None:
    length = random.randint(CONFIG['ALIAS_DIGITS_MIN'], CONFIG['ALIAS_DIGITS_MAX'])
  return utils.randomword(length)


def redirect_away():
  return redirect(CONFIG['REDIRECT_URL'], code=302)

def abort_404():
  return abort(404)

behaviours = {
  'abort' : abort_404,
  'redir' : redirect_away,
}

default_miss = behaviours.get(CONFIG['MISS'],'abort')
on_expired = behaviours.get(CONFIG['EXPIRE'],'abort')
blacklisted = behaviours.get(CONFIG['BLACKLISTED'],'abort')


@app.after_request
def add_header(response):
  response.headers['Cache-Control'] = 'no-store'
  del response.headers['Expires']
  response.headers['Server'] = CONFIG['SERVER_HEADER']
  response.headers['X-Content-Type-Options'] = "nosniff"
  del response.headers['Date']

  return response


@app.route('/%s/' % CONFIG['MANAGE_URL_DIR'])
def show_manage():
  return render_template(
    "manage_help.html",
    manage_url = request.base_url
    )

@app.route('/%s/load_defaults' % CONFIG['MANAGE_URL_DIR'])
def load_defaults():
  add_url_template = "http://127.0.0.1:{port}/{man}/add?path={path}&alias={alias}&unchecked=True"
  try:
    if CONFIG['DEFAULT_PATHS_FILE']:
      print("[+] Importing defaults from '{}'".format(CONFIG['DEFAULT_PATHS_FILE']))
      import json
      with open(CONFIG['DEFAULT_PATHS_FILE']) as url_defaults:
        defaults_url_dict = json.load(url_defaults)
        for path, url_params in defaults_url_dict.items():
          print(path, url_params)
          alias = url_params['alias']
          filename = url_params.get('filename',None)
          print (filename)
          if filename:
            add_url_template += '&filename={filename}'
          urllib.request.urlopen(add_url_template.format(
              port=CONFIG['PORT'],
              man=CONFIG['MANAGE_URL_DIR'],
              path=path,
              alias=alias,
              filename=filename,
              )
            )
    return "<pre>{}</pre>".format(
      json.dumps(defaults_url_dict, indent=2)
      )
  except Exception as e:
    return render_template("custom_error.html",error_msg=str(e))

@app.route(
  '/%s/list/' % CONFIG['MANAGE_URL_DIR'],
  defaults={'req_path': ''}
  )

@app.route('/%s/list/<path:req_path>' % CONFIG['MANAGE_URL_DIR'])
def dir_listing(req_path):
  '''
  Found here:
https://stackoverflow.com/questions/23718236/python-flask-browsing-through-directory-with-files
  '''
  # Joining the base and the requested path
  abs_path = os.path.join(CONFIG['SRV_DIR'], req_path)

  # Return 404 if path doesn't exist
  if not os.path.exists(abs_path):
    return abort(404)

  # Check if path is a file and serve
  if os.path.isfile(abs_path):
    return send_file(abs_path)

  # Show directory contents
  files = os.listdir(abs_path)
  full_paths = []
  for f in files:
    full_paths.append(
      (f, os.path.join(request.base_url, f))
    )
  # print (full_paths)
  add_url_link = "%s%s/add" % (request.url_root, CONFIG['MANAGE_URL_DIR'])
  return render_template('file.html',
    files=full_paths,
    add_url=add_url_link
    )


@app.route('/%s/add' % CONFIG['MANAGE_URL_DIR'])
def add_url():

  path = request.args.get("path")
  expires = request.args.get("clicks", -1)
  alias = request.args.get("alias", get_random_alias())
  attach_name = request.args.get("filename")
  mimetype = request.args.get("mime", None)
  unchecked_path = request.args.get("unchecked", False)
  if not request.args:
    return render_template(
      'add_help.html', 
    )
  try:
    original_filename = path.split('/')[-1]
    original_extension = original_filename.split('.')[-1]
  except Exception as e:
    return render_template(
      'custom_error.html', 
      error_msg="The 'path' variable does not validate"
      )

  if original_filename == original_extension:
    # If they are the same, there is no extension
    original_extension = ''
  else:
    original_extension = '.' + original_extension

  if not attach_name:

    if not CONFIG['DEFAULT_FILENAME']:
      # The filename is the path's filename
      attach_name = original_filename
    else:
      attach_name = CONFIG['DEFAULT_FILENAME']
      if CONFIG['USE_ORIGINAL_EXTENSION']:
        attach_name += original_extension

  path = os.path.join(CONFIG['SRV_DIR'], path)
  if not os.path.isfile(path) and not unchecked_path:
    return render_template(
      'custom_error.html', 
      error_msg="The path '{}' is NOT a file".format(path)
      )

  try:
    if expires is not None: 
      int(expires)
  except:
    return render_template(
      'custom_error.html', 
      error_msg="Parameter 'clicks' must be positive Integer"
      )
  try:
    db_handler.add_url(
      path, alias, expires,
      attachment = attach_name,
      mimetype = mimetype
      )
  except Exception as e:
    print (e)
    err =  "Error adding alias '{}'' for path '{}'".format(alias, path)
    return render_template(
      'custom_error.html', 
      error_msg=err
      )
  full_link = request.url_root + alias
  return render_template(
      'added_alias.html', 
      alias=alias,
      path=path,
      clicks=expires,
      link=full_link
      )

@app.route('/%s/del' % CONFIG['MANAGE_URL_DIR'], methods=["GET", "POST"])
def del_url():
  alias = request.args.get("alias", None) 
  if alias is None:
    alias = request.form.get("alias", None)
    if alias is None:
      return render_template(
        'del_help.html'
        )
  try:
    deleted = db_handler.del_url(alias)
  except KeyError:
    deleted = False
  return "Deleted '/%s'" % alias if deleted else "NOT deleted"

@app.route('/%s/config' % CONFIG['MANAGE_URL_DIR'])
def show_config(path=None):
    return render_template('show_config.html', entries=CONFIG)
    
@app.route('/%s/show' % CONFIG['MANAGE_URL_DIR'])
def show_all(path=None):
  entries = db_handler.get_all(path)
  return render_template(
        'show.html',  # Fix show.html to contain mimetypes
        entries = entries
        )

@app.route(
  '/%s/upload' % CONFIG['MANAGE_URL_DIR'],
  methods=['POST', 'GET']
  )
def file_upload():
  if request.method == 'POST':
    # check if the post request has the file part
    if 'file' not in request.files:
      return render_template(
          'upload_page.html',
          manage_url = CONFIG['MANAGE_URL_DIR'],
          message = "No file submitted"
        )
    file = request.files['file']
    # if user does not select file, browser also
    # submit a empty part without filename
    if file.filename == '':
      return render_template(
            'upload_page.html',
            manage_url = CONFIG['MANAGE_URL_DIR'],
            message = "No filename submitted"
          )
    if file:
      filename = request.form.get('filename', file.filename)
      filename = secure_filename(filename)
      try:
        file.save(
          os.path.join(
            app.config['UPLOAD_FOLDER'],
            filename
            )
          )
      except IsADirectoryError:
        return render_template(
              'upload_page.html',
              manage_url = CONFIG['MANAGE_URL_DIR'],
              message = "Filename exists"
            )

      if request.form.get("create_alias",
        default = False,
        type = bool):
        alias_name = request.form.get("alias", default=None)
        deliver_filename = request.form.get("deliver_filename", default=None)
        return redirect(
          "/{manage_url}/add?path={filepath}{alias}{filename}".format(
            manage_url = CONFIG['MANAGE_URL_DIR'],
            filepath = filename,
                                                alias="" if not alias_name else "&alias=%s" % alias_name,
                  filename="" if not deliver_filename else "&filename=%s" % deliver_filename,
                                            )
          )
      return render_template(
            'upload_page.html',
            manage_url = CONFIG['MANAGE_URL_DIR'],
            message = "File '{}' uploaded successfully!".format(filename)
          )
  return render_template(
        'upload_page.html',
        manage_url = CONFIG['MANAGE_URL_DIR'],
      )

#  Default behaviour - Serve all non "/manage" paths
@app.route('/<path:url_alias>', methods=['POST', 'GET'])
@app.route('/', defaults={'url_alias': ''}, methods=['POST', 'GET'])
def resolve_url(url_alias):
  ret_response = None
  # check if whitelisted/blacklisted ip
  remote_host = ip_address(request.remote_addr)
  if utils.is_listed(CONFIG['IP_BLACKLIST'], remote_host):
    ret_response = blacklisted()
    return hook_n_respond(request, ret_response)

  if not utils.is_listed(CONFIG['IP_WHITELIST'], remote_host):
    ret_response = blacklisted()
    return hook_n_respond(request, ret_response)

  if utils.is_geolocation_listed(CONFIG['GEOLOCATION_BLACKLIST'], remote_host):
    ret_response = blacklisted()
    return hook_n_respond(request, ret_response)

  # Run "pre_process" hook checks
  hook_ret = hooker.EVENTS["pre_process"](
    request=request,
    url_alias=url_alias,
  )
  #  In case the hook changed the original request
  url_alias = request.path[1:]  # Remove the '/'
  print("[*] %s" % url_alias)
  try:
    behaviour = hook_ret.popitem()[1]
    # Get the behavior from the list and generate its response:
    if behaviour is not None:
      ret_response = behaviours.get(behaviour, abort_404)()
      return hook_n_respond(request, ret_response)
  except KeyError:
    pass

  # Check if URL Alias exists
  try:
    alias_db_obj = db_handler.get_path(url_alias)
  except KeyError:
    # Non-existent
    ret_response = default_miss()
    return hook_n_respond(request, ret_response)
  except utils.LinkExpired:
    # Existent and expired
    ret_response = on_expired()
    return hook_n_respond(request, ret_response)

  path = alias_db_obj.path
  # Run the hooks for iconic filenames
  hook_ret = hooker.EVENTS["pre_file"](
    filename=path,
    request=request,
    )
  try:
    iconic_fd = hook_ret.popitem()[1]
  except KeyError:
    iconic_fd = None

  if iconic_fd:
    print(
      "[+] Filename '{}' HOOKED! A Custom file is served!".format(
        alias_db_obj.path
        )
      )
  # If it succeds the returned fd will be served 
    ret_fd = iconic_fd
    ret_response = send_file(
        filename_or_fp = ret_fd,
        as_attachment = True,
        attachment_filename = alias_db_obj.attachment,
        mimetype = alias_db_obj.mimetype,
      )
    return hook_n_respond(request, ret_response)

  # Else the file file system is checked for real files
  print(path, os.path.isfile(path))
  if not os.path.isfile(path):    
    # If doensn't exist, 'miss' behaviour is triggered
    ret_response = default_miss()
    return hook_n_respond(request, ret_response)

  ret_fd = open(path,'rb')
  # hook_ret = hooker.EVENTS["post_file"](
  #   filename=path,
  #   request=request,
  #   fd=ret_fd
  #   )

  ret_response = send_file(
      filename_or_fp = ret_fd,
      as_attachment = True,
      attachment_filename = alias_db_obj.attachment,
      mimetype = alias_db_obj.mimetype,
    )

  return hook_n_respond(request, ret_response)


def hook_n_respond(request, response):
  hook_ret = hooker.EVENTS["pre_response"](
    request=request,
    response=response
    )
  try:
    ret_response_final = hook_ret.popitem()[1]
  except KeyError:
    ret_response_final = response
  return ret_response_final


def main(*args, **kwargs):

  utils.log_spawn(CONFIG['LOG_SPAWN_FILE'], CONFIG['MANAGE_URL_DIR'], CONFIG['PORT'])
  app.run(
    host=CONFIG['IP'],
    port=CONFIG['PORT'],
    debug=os.getenv("DEBUG", False)
  )

if __name__=="__main__":
  main()
