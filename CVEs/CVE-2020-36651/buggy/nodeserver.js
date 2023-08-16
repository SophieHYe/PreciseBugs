var http = require('http');
var fs = require('fs');
var header = require('./tool/httpHeader').contentType;
var route = require('./router').response;
var conf = require('./config');

function responseTemp(response, head, file) {
  response.writeHead(200, head);
  response.write(file);
  response.end();
};

function error(response, text) {
  response.writeHead(500, {'Content-Type': 'text/html;charset:utf-8'});
  response.write('<h2>Server Error</h2><p>Error in api or template config about this domain</p><p>' + (text || 'Can\'t find domain config!') + '</p>');
  response.end();
}

function start(config) {
  var host = conf.constant.host;
  if(config) conf.serv = config;
  function onRequest(request, response) {
    var frontUrl = '';
    if(request.url === '/favicon.ico') return;
    for(var key in conf.serv) {
      if(request.headers.host.indexOf(key) !== -1) {
        host = conf.serv[key];
      }
    }
    
    var nowTemp = host.frondend + (request.url.replace('/', '') || host.baseTemp);
    var httpHead = header(nowTemp);
    conf.app = conf.getApp(host.backend);
    if(!host) {
      error(response);
      return;
    }

    // 直接定向到模板
    var defaultTemp = function() {
      fs.readFile(host.frondend + host.baseTemp, function(err, file) {
        if(err) {
          error(response, err);
          return;
        }
        responseTemp(response, httpHead, file);
      });
    };

    var send = function(res) {
      if(res) {
        if(res === 'error') {
          error(response, 'Route config error!');
          return;
        }

        if(res.html) {
          // html格式
          response.writeHead(res.status, {'Content-Type': 'text/html;charset:utf-8'});
          response.write(res.html);
          response.end();
          return;
        } else if(res.status === 302) {
          // 重定向
          response.writeHead(res.status, {
            'Content-Type': 'text/html;charset:utf-8',
            'Location': res.url
          });
          response.end();
          return;
        } else if(res.data) {
          // json格式
          response.writeHead(res.status, {'Content-Type': 'application/json'});
          response.write(JSON.stringify(res));
          response.end();
          return;
        } else {
          error(response, 'Data type error!');
        }
      } else {
        fs.exists(nowTemp, function(exists) {
          if(!exists) {
            defaultTemp();
          } else {
            fs.readFile(nowTemp, function(err, file) {
              if (err) {
                defaultTemp();
              } else {
                responseTemp(response, httpHead, file);
              }
            });
          }
        });
      }
    };

    route(conf.app.url, request, send);
  }

  http.createServer(onRequest).listen(conf.constant.port);
  console.log('server running at ' + conf.constant.port);
}

exports.start = start;
