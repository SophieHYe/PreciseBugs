
/**
 * Module dependencies.
 */

var express = require('express')
  , routes = require('./routes')
  , mobconf = require('./routes/mobileconfig')
  , http = require('http')
  , path = require('path');
  
var cookieParser = require('cookie-parser')

var app = express();

// all environments
app.set('port', process.env.PORT || 3001);
app.set('views', __dirname + '/views');
app.set('view engine', 'pug');

app.use(cookieParser(process.env.COOKIE_KEY || 'f76210bc2acc4f54af5754e15b0aab05'));
app.use(express.static(path.join(__dirname, 'public')));
app.use(express.raw({
  'type' : 'application/pkcs7-signature'
}))

app.get('/', routes.index);

app.post('/enroll', mobconf.enroll);
app.get('/enrollment', mobconf.enrollment);


http.createServer(app).listen(app.get('port'), function(){
  console.log('Express server listening on port ' + app.get('port'));
});
