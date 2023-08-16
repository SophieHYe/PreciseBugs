/* global describe, it, expect */

var chai = require('chai');
var fprm = require('..');
var AuthorizationError = require('../lib/errors/authorizationerror');

describe('oauth2orize-fprm', function() {
  
  it('should export function', function() {
    expect(fprm).to.be.an('function');
  });
  
  describe('responding to a request', function() {
    var response, err;

    before(function(done) {
      chai.connect.use(function(req, res) {
          var params = {
            state: req.oauth2.req.state,
            id_token: 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjEifQ.eyJzdWIiOiJqb2huIiw\
iYXVkIjoiZmZzMiIsImp0aSI6ImhwQUI3RDBNbEo0c2YzVFR2cllxUkIiLC\
Jpc3MiOiJodHRwczpcL1wvbG9jYWxob3N0OjkwMzEiLCJpYXQiOjEzNjM5M\
DMxMTMsImV4cCI6MTM2MzkwMzcxMywibm9uY2UiOiIyVDFBZ2FlUlRHVE1B\
SnllRE1OOUlKYmdpVUciLCJhY3IiOiJ1cm46b2FzaXM6bmFtZXM6dGM6U0F\
NTDoyLjA6YWM6Y2xhc3NlczpQYXNzd29yZCIsImF1dGhfdGltZSI6MTM2Mz\
kwMDg5NH0.c9emvFayy-YJnO0kxUNQqeAoYu7sjlyulRSNrru1ySZs2qwqq\
wwq-Qk7LFd3iGYeUWrfjZkmyXeKKs_OtZ2tI2QQqJpcfrpAuiNuEHII-_fk\
IufbGNT_rfHUcY3tGGKxcvZO9uvgKgX9Vs1v04UaCOUfxRjSVlumE6fWGcq\
XVEKhtPadj1elk3r4zkoNt9vjUQt9NGdm1OvaZ2ONprCErBbXf1eJb4NW_h\
nrQ5IKXuNsQ1g9ccT5DMtZSwgDFwsHMDWMPFGax5Lw6ogjwJ4AQDrhzNCFc\
0uVAwBBb772-86HpAkGWAKOK-wTC6ErRTcESRdNRe0iKb47XRXaoz5acA'
          };
        
          fprm(req.oauth2, res, params);
        })
        .req(function(req) {
          req.oauth2 = {};
          req.oauth2.redirectURI = 'https://client.example.org/callback';
          req.oauth2.req = { state: 'DcP7csa3hMlvybERqcieLHrRzKBra' };
        })
        .end(function(res) {
          response = res;
          done();
        })
        .dispatch();
    });
    
    it('should respond with headers', function() {
      expect(response.getHeader('Content-Type')).to.equal('text/html;charset=UTF-8');
      expect(response.getHeader('Cache-Control')).to.equal('no-cache, no-store');
      expect(response.getHeader('Pragma')).to.equal('no-cache');
    });
    
    it('should respond with body', function() {
      expect(response.body).to.equal('<html>\
<head><title>Submit This Form</title></head>\
<body onload=\"javascript:document.forms[0].submit()\">\
<form method=\"post\" action=\"https://client.example.org/callback\">\
<input type=\"hidden\" name=\"state\" value=\"DcP7csa3hMlvybERqcieLHrRzKBra\"/>\
<input type=\"hidden\" name=\"id_token\" value=\"eyJhbGciOiJSUzI1NiIsImtpZCI6IjEifQ.eyJzdWIiOiJqb2huIiwiYXVkIjoiZmZzMiIsImp0aSI6ImhwQUI3RDBNbEo0c2YzVFR2cllxUkIiLCJpc3MiOiJodHRwczpcL1wvbG9jYWxob3N0OjkwMzEiLCJpYXQiOjEzNjM5MDMxMTMsImV4cCI6MTM2MzkwMzcxMywibm9uY2UiOiIyVDFBZ2FlUlRHVE1BSnllRE1OOUlKYmdpVUciLCJhY3IiOiJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YWM6Y2xhc3NlczpQYXNzd29yZCIsImF1dGhfdGltZSI6MTM2MzkwMDg5NH0.c9emvFayy-YJnO0kxUNQqeAoYu7sjlyulRSNrru1ySZs2qwqqwwq-Qk7LFd3iGYeUWrfjZkmyXeKKs_OtZ2tI2QQqJpcfrpAuiNuEHII-_fkIufbGNT_rfHUcY3tGGKxcvZO9uvgKgX9Vs1v04UaCOUfxRjSVlumE6fWGcqXVEKhtPadj1elk3r4zkoNt9vjUQt9NGdm1OvaZ2ONprCErBbXf1eJb4NW_hnrQ5IKXuNsQ1g9ccT5DMtZSwgDFwsHMDWMPFGax5Lw6ogjwJ4AQDrhzNCFc0uVAwBBb772-86HpAkGWAKOK-wTC6ErRTcESRdNRe0iKb47XRXaoz5acA\"/>\
</form>\
</body>\
</html>');
    });
  });

  describe('responding to a malformed request', function() {
    var response, err;

    before(function(done) {
      chai.connect.use(function(req, res) {
          var params = {
            client_id: '"></a>bxD15c32DJhz9XagFx5gniWLH02IzAKK',
            scope: '"></a>openid email user_metadata',
            response_mode: '"></a>form_post',
            state: req.oauth2.req.state,
            id_token: '"></a>eyJhbGciOiJSUzI1NiIsImtpZCI6IjEifQ.eyJzdWIiOiJqb2huIiw',
            expires_in: 86400
          };

          fprm(req.oauth2, res, params);
        })
        .req(function(req) {
          req.oauth2 = {};
          req.oauth2.redirectURI = 'https://client.example.org/callback?id="><a>';
          req.oauth2.req = { state: '"></a>DcP7csa3hMlvybERqcieLHrRzKBra' };
        })
        .end(function(res) {
          response = res;
          done();
        })
        .dispatch();
    });

    it('should sanitize html characters within input fields', function() {
      expect(response.body).to.equal('<html><head><title>Submit This Form</title>\
</head><body onload="javascript:document.forms[0].submit()">\
<form method="post" action="https://client.example.org/callback?id=&quot;&gt;&lt;a&gt;">\
<input type="hidden" name="client_id" value="&quot;&gt;&lt;/a&gt;bxD15c32DJhz9XagFx5gniWLH02IzAKK"/>\
<input type="hidden" name="scope" value="&quot;&gt;&lt;/a&gt;openid email user_metadata"/>\
<input type="hidden" name="response_mode" value="&quot;&gt;&lt;/a&gt;form_post"/>\
<input type="hidden" name="state" value="&quot;&gt;&lt;/a&gt;DcP7csa3hMlvybERqcieLHrRzKBra"/>\
<input type="hidden" name="id_token" value="&quot;&gt;&lt;/a&gt;eyJhbGciOiJSUzI1NiIsImtpZCI6IjEifQ.eyJzdWIiOiJqb2huIiw"/>\
<input type="hidden" name="expires_in" value="86400"/>\
</form></body></html>');
    });
  });

  describe('validation', function() {
    it('should not throw if no redirect URI', function() {
      expect(function() {
        fprm.validate({ redirectURI: 'https://client.example.org/callback' });
      }).to.not.throw();
    });
    
    it('should throw if no redirect URI', function() {
      expect(function() {
        fprm.validate({});
      }).to.throw(AuthorizationError, 'Unable to issue redirect for OAuth 2.0 transaction');
    });
  });
  
});
