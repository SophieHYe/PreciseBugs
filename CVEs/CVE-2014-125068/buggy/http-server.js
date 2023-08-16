var http = require('http');
var fs = require('fs');

fs.exists = fs.exists || require('path').exists;

var SERVER_PORT = 8080;

var FILE_TYPE_GZIP = 1;

var FILE_EXT_GZIP = '.gz';
var FILE_EXT_CSS = '.css';
var FILE_EXT_JS = '.js';

// GZIP text/plain required to get Chrome XHR to decompress file.
var MIME_TYPE_GZIP = 'text/plain';
var MIME_TYPE_JS = 'text/javascript';
var MIME_TYPE_CSS = 'text/css';

function log(code, string) {
  //console.log('[' + code + '] ' + string);
}

var server = http.createServer(function(request, response) {
  var filePath = request.url;

  // Remove query strings from uri
  if (filePath.indexOf('?') > -1) {
    filePath = filePath.substr(0, filePath.indexOf('?'));
  }

  filePath = '.' + filePath;

  fs.exists(filePath, function(exists) {
    if (!exists) {
      log(404, filePath)
      response.writeHead(404);
      response.end();

      return;
    }

    var mimeType = '';
    var fileType = -1;

    if (filePath.substring(filePath.length - FILE_EXT_GZIP.length) == FILE_EXT_GZIP) {
      fileType = FILE_TYPE_GZIP;
      mimeType = MIME_TYPE_GZIP;
    } else if (filePath.substring(filePath.length - FILE_EXT_JS.length) == FILE_EXT_JS) {
      mimeType = MIME_TYPE_JS;
    } else if (filePath.substring(filePath.length - FILE_EXT_CSS.length) == FILE_EXT_CSS) {
      mimeType = MIME_TYPE_CSS;
    }

    var acceptEncoding = request.headers['accept-encoding'];
    if (!acceptEncoding) {
      acceptEncoding = '';
    }

    fs.readFile(filePath, function(error, content) {
      if (error) {
        log(500, filePath);
        response.writeHead(500);
        response.end();
      } else {
        log(200, filePath);
        var raw = fs.createReadStream(filePath);

        if (fileType == FILE_TYPE_GZIP && acceptEncoding.match(/\bgzip\b/)) {
          response.writeHead(200, { 'Content-Type': 'text/plain', 'Content-Encoding': 'gzip' });
        } else if (mimeType) {
          response.writeHead(200, { 'Content-Type': mimeType });
        } else {
          response.writeHead(200, {});
        }

        raw.pipe(response);
      }
    });
  });
});

server.on('error', function (e) {
  if (e.code == 'EADDRINUSE') {
    console.log('Port ' + SERVER_PORT + ' already in use.');
  }
});

server.listen(SERVER_PORT);
console.log('Server listening on port ' + SERVER_PORT);
