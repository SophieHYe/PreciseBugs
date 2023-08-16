import {EventEmitter} from 'events';
import * as fl from 'fluture';
import test from 'oletus';
import {Readable} from 'stream';
import {equivalence, equality as eq} from 'fluture/test/assertions.js';
import {withTestServer} from './server.js';
import {lookup} from 'dns';

import * as fn from '../index.js';

const assertResolves = a => b => equivalence (a) (fl.resolve (b));
const assertRejects = a => b => equivalence (a) (fl.reject (b));

const noop = () => {};

test ('once', () => {
  const ee1 = new EventEmitter ();

  const cancel = fl.value (noop) (fn.once ('test') (ee1));
  eq (ee1.listenerCount ('test')) (1);
  eq (ee1.listenerCount ('error')) (1);

  cancel ();
  eq (ee1.listenerCount ('test')) (0);
  eq (ee1.listenerCount ('error')) (0);

  const ee2 = new EventEmitter ();
  const ee3 = new EventEmitter ();

  setTimeout (() => {
    ee2.emit ('test', 42);
    ee3.emit ('error', 42);
  }, 10);

  return Promise.all ([
    assertResolves (fn.once ('test') (ee2)) (42),
    assertRejects (fn.once ('test') (ee3)) (42),
  ]);
});

test ('encode', () => Promise.all ([
  assertRejects (fn.encode ('lalalala') (Buffer.from ('hello')))
                (new Error ('Unknown encoding: lalalala')),
  assertResolves (fn.encode ('utf8') (Buffer.from ('hello'))) ('hello'),
  assertResolves (fn.encode ('hex') (Buffer.from ('hello'))) ('68656c6c6f'),
]));

test ('streamOf', () => {
  const eventualStream = fn.streamOf (Buffer.from ('hello'));
  return new Promise ((res, rej) => {
    fl.fork (e => rej (new Error ('The future rejected: ' + String (e))))
            (stream => {
              stream.on ('end', res);
              stream.on ('data', data => {
                try {
                  eq (data) (Buffer.from ('hello'));
                } catch (e) {
                  rej (e);
                }
              });
            })
            (eventualStream);
    setTimeout (rej, 20, new Error ('No data on the stream'));
  });
});

test ('emptyStream', () => new Promise ((res, rej) => {
  fl.fork (e => rej (new Error ('The Future rejected: ' + String (e))))
          (stream => {
            stream.on ('data', data => {
              rej (new Error ('The stream emitted data: ' + String (data)));
            });
            stream.on ('end', res);
          })
          (fn.emptyStream);
}));

test ('buffer', () => {
  const s1 = new Readable ({read: noop});
  const s2 = new Readable ({read: noop});

  const b1 = Buffer.from ('hello');
  const b2 = Buffer.from ('world');

  s1.push (b1);

  setTimeout (() => {
    s1.push (b2);
    s1.push (null);
    s2.emit ('error', 42);
  }, 10);

  return Promise.all ([
    assertResolves (fn.buffer (s1)) ([b1, b2]),
    assertRejects (fn.buffer (s2)) (42),
  ]);
});

test ('bufferString', () => Promise.all ([
  assertResolves (fl.chain (fn.bufferString ('utf8')) (fn.streamOf (Buffer.from ('hello'))))
                 ('hello'),
  assertResolves (fl.chain (fn.bufferString ('hex')) (fn.streamOf (Buffer.from ('hello'))))
                 ('68656c6c6f'),
]));

test ('instant', () => (
  assertResolves (fn.instant ('noodles')) ('noodles')
));

test ('immediate', () => {
  fl.value (eq ('should not run')) (fn.immediate ('did run')) ();
  return assertResolves (fn.immediate ('results')) ('results');
});

const getRequest = fn.Request ({}) ('https://example.com') (fn.emptyStream);

const postRequest = fn.Request ({method: 'POST'}) ('https://example.com') (fn.streamOf (Buffer.from ('test')));

const responseHeaders = {
  'connection': 'close',
  'content-type': 'text/plain',
  'date': 'now',
  'transfer-encoding': 'chunked',
};

const mockResponse = ({code = 200, message = 'OK', headers = responseHeaders, request = getRequest, body = Buffer.from ('hello')}) => fl.map (stream => {
  stream.headers = headers;
  stream.statusCode = code;
  stream.statusMessage = message;
  return fn.Response (request) (stream);
}) (fn.streamOf (body));

const getResponse = code => location => mockResponse ({
  code: code,
  headers: Object.assign ({}, responseHeaders, {location}),
  request: getRequest,
});

const postResponse = code => location => mockResponse ({
  code: code,
  headers: Object.assign ({}, responseHeaders, {location}),
  request: postRequest,
});

const sendMockRequest = eventualBody => withTestServer (({url}) => (
  fn.sendRequest (fn.Request ({headers: {
    'Connection': 'close',
    'Transfer-Encoding': 'chunked',
  }}) (`${url}/echo`) (eventualBody))
));

test ('Request', () => {
  const options = {};
  const url = 'https://example.com';
  const body = fn.emptyStream;
  const request = fn.Request (options) (`${url}/echo`) (body);
  eq (fn.Request.options (request)) (options);
  eq (fn.Request.url (request)) (`${url}/echo`);
  eq (fn.Request.body (request)) (body);
});

test ('Response', () => {
  const message = fn.emptyStream;
  const response = fn.Response (getRequest) (message);
  eq (fn.Response.request (response)) (getRequest);
  eq (fn.Response.message (response)) (message);
});

test ('cleanRequestOptions', () => {
  const req = o => fn.Request (o) ('https://example.com') (fn.emptyStream);
  eq (fn.cleanRequestOptions (req ({}))) ({
    agent: undefined,
    createConnection: undefined,
    defaultPort: undefined,
    family: undefined,
    headers: {},
    insecureHTTPParser: false,
    localAddress: undefined,
    lookup: lookup,
    maxHeaderSize: 16384,
    method: 'GET',
    setHost: true,
    socketPath: undefined,
    timeout: undefined,
  });
  eq (fn.cleanRequestOptions (req ({agent: {defaultPort: 42}}))) ({
    agent: {defaultPort: 42},
    createConnection: undefined,
    defaultPort: 42,
    family: undefined,
    headers: {},
    insecureHTTPParser: false,
    localAddress: undefined,
    lookup: lookup,
    maxHeaderSize: 16384,
    method: 'GET',
    setHost: true,
    socketPath: undefined,
    timeout: undefined,
  });
});

test ('bufferResponse', () => Promise.all ([
  assertResolves (fl.chain (fn.bufferResponse ('utf8')) (sendMockRequest (fn.emptyStream)))
                 ('GET/'),
  assertResolves (fl.chain (fn.bufferResponse ('utf8')) (sendMockRequest (fn.streamOf (Buffer.from ('hello')))))
                 ('GET/hello'),
]));

const thenBuffer = fl.bichain (res => fl.swap (fn.bufferResponse ('utf8') (res)))
                              (fn.bufferResponse ('utf8'));

test ('sendRequest', () => Promise.all ([
  assertRejects (fn.sendRequest (fn.Request ({}) ('https://localhost') (fn.emptyStream)))
                (Object.assign (new Error ('connect ECONNREFUSED 127.0.0.1:443'), {
                  address: '127.0.0.1',
                  code: 'ECONNREFUSED',
                  errno: -111,
                  port: 443,
                  syscall: 'connect',
                })),
  assertRejects (fn.sendRequest (fn.Request ({}) ('ftp://localhost') (fn.emptyStream)))
                (new Error ("Unsupported protocol 'ftp:'")),
  assertResolves (thenBuffer (sendMockRequest (fn.emptyStream)))
                 ('GET/'),
  assertResolves (thenBuffer (sendMockRequest (fn.streamOf (Buffer.from ('hello')))))
                 ('GET/hello'),
]));

test ('request cancellation', () => new Promise ((res, rej) => {
  const cancel = fl.fork (rej)
                         (rej)
                         (fn.sendRequest (fn.Request ({}) ('https://localhost') (fn.emptyStream)));
  cancel ();
  setTimeout (res, 1000);
}));

test ('retrieve', () => Promise.all ([
  assertResolves (withTestServer (({url}) => thenBuffer (fn.retrieve (`${url}/echo`) ({}))))
                 ('GET/'),
]));

test ('send', () => Promise.all ([
  assertResolves (withTestServer (({url}) => thenBuffer (fn.send ('text/plain') ('POST') (`${url}/echo`) ({}) (Buffer.from ('hello')))))
                 ('POST/hello'),
]));

test ('sendJson', () => Promise.all ([
  assertResolves (withTestServer (({url}) => thenBuffer (fn.sendJson ('POST') (`${url}/echo`) ({}) ({message: 'hello'}))))
                 ('POST/{"message":"hello"}'),
]));

test ('sendForm', () => Promise.all ([
  assertResolves (withTestServer (({url}) => thenBuffer (fn.sendForm ('POST') (`${url}/echo`) ({}) ({message: 'hello'}))))
                 ('POST/message=hello'),
]));

test ('redirectAnyRequest', () => Promise.all ([
  assertResolves (fl.map (fn.redirectAnyRequest) (mockResponse ({})))
                 (getRequest),
  assertResolves (fl.map (fn.redirectAnyRequest) (getResponse (301) ('ftp://xxx')))
                 (fn.Request ({headers: {}}) ('ftp://xxx/') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectAnyRequest) (getResponse (301) ('/echo')))
                 (fn.Request ({}) ('https://example.com/echo') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectAnyRequest) (postResponse (301) ('/echo')))
                 (fn.Request ({method: 'POST'}) ('https://example.com/echo') (fn.streamOf (Buffer.from ('test')))),
  assertResolves (fl.map (fn.redirectAnyRequest)
                         (mockResponse ({code: 301,
                                         headers: {location: 'https://example.com/path'},
                                         request: fn.Request ({headers: {cookie: 'yum'}}) ('https://example.com') (fn.emptyStream)})))
                 (fn.Request ({headers: {cookie: 'yum'}}) ('https://example.com/path') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectAnyRequest)
                         (mockResponse ({code: 301,
                                         headers: {location: 'https://sub.example.com/'},
                                         request: fn.Request ({headers: {cookie: 'yum'}}) ('https://example.com') (fn.emptyStream)})))
                 (fn.Request ({headers: {cookie: 'yum'}}) ('https://sub.example.com/') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectAnyRequest)
                         (mockResponse ({code: 301,
                                         headers: {location: 'https://bigsub.example.com/'},
                                         request: fn.Request ({headers: {cookie: 'yum'}}) ('https://example.com') (fn.emptyStream)})))
                 (fn.Request ({headers: {cookie: 'yum'}}) ('https://bigsub.example.com/') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectAnyRequest)
                         (mockResponse ({code: 301,
                                         headers: {location: 'https://elsewhere.com/'},
                                         request: fn.Request ({headers: {cookie: 'yum'}}) ('https://example.com') (fn.emptyStream)})))
                 (fn.Request ({headers: {}}) ('https://elsewhere.com/') (fn.emptyStream)),
]));

test ('redirectIfGetMethod', () => Promise.all ([
  assertResolves (fl.map (fn.redirectIfGetMethod) (mockResponse ({})))
                 (getRequest),
  assertResolves (fl.map (fn.redirectIfGetMethod) (getResponse (301) ('ftp://xxx')))
                 (fn.Request ({headers: {}}) ('ftp://xxx/') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectIfGetMethod) (getResponse (301) ('/echo')))
                 (fn.Request ({}) ('https://example.com/echo') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectIfGetMethod) (postResponse (301) ('/echo')))
                 (postRequest),
]));

test ('redirectUsingGetMethod', () => Promise.all ([
  assertResolves (fl.map (fn.redirectUsingGetMethod) (mockResponse ({})))
                 (fn.Request ({method: 'GET'}) ('https://example.com') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectUsingGetMethod) (getResponse (301) ('ftp://xxx')))
                 (fn.Request ({method: 'GET', headers: {}}) ('ftp://xxx/') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectUsingGetMethod) (getResponse (200) ('/echo')))
                 (fn.Request ({method: 'GET'}) ('https://example.com/echo') (fn.emptyStream)),
  assertResolves (fl.map (fn.redirectUsingGetMethod) (postResponse (200) ('/echo')))
                 (fn.Request ({method: 'GET'}) ('https://example.com/echo') (fn.emptyStream)),
]));

test ('retryWithoutCondition', () => Promise.all ([
  assertResolves (fl.map (fn.retryWithoutCondition) (mockResponse ({})))
                 (fn.Request ({headers: {}}) ('https://example.com') (fn.emptyStream)),
  assertResolves (fl.map (fn.retryWithoutCondition) (getResponse (301) ('ftp://xxx')))
                 (fn.Request ({headers: {}}) ('https://example.com') (fn.emptyStream)),
  assertResolves (fl.map (fn.retryWithoutCondition) (getResponse (200) ('/echo')))
                 (fn.Request ({headers: {}}) ('https://example.com') (fn.emptyStream)),
  assertResolves (fl.map (fn.retryWithoutCondition) (postResponse (200) ('/echo')))
                 (fn.Request ({method: 'POST'}) ('https://example.com') (fn.streamOf (Buffer.from ('test')))),
  assertResolves (fl.map (fn.retryWithoutCondition) (mockResponse ({request: fn.Request ({headers: {'If-None-Match': 'abc123'}}) ('https://example.com') (fn.emptyStream), body: Buffer.from ('test')})))
                 (fn.Request ({headers: {}}) ('https://example.com') (fn.emptyStream)),
  assertResolves (fl.map (fn.retryWithoutCondition) (mockResponse ({request: fn.Request ({method: 'POST', headers: {'If-None-Match': 'abc123'}}) ('https://example.com') (fn.streamOf (Buffer.from ('hello'))), body: Buffer.from ('test')})))
                 (fn.Request ({method: 'POST', headers: {'If-None-Match': 'abc123'}}) ('https://example.com') (fn.streamOf (Buffer.from ('hello')))),
]));

test ('followRedirectsWith', () => Promise.all ([
  assertResolves (thenBuffer (withTestServer (({url}) => fl.chain (fn.followRedirectsWith (_ => fn.Request ({}) (`${url}/echo`) (fn.emptyStream)) (1)) (mockResponse ({})))))
                 ('GET/'),
  assertResolves (thenBuffer (withTestServer (({url}) => fl.chain (fn.followRedirectsWith (_ => fn.Request ({}) (`${url}/echo`) (fn.emptyStream)) (0)) (mockResponse ({})))))
                 ('hello'),
]));

test ('followRedirects', () => Promise.all ([
  assertResolves (fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (mockResponse ({}))))
                 ('hello'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (301) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (302) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (303) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (304) (`${url}/echo`)))))
                 ('hello'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (305) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (getResponse (307) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (301) (`${url}/echo`)))))
                 ('hello'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (302) (`${url}/echo`)))))
                 ('hello'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (303) (`${url}/echo`)))))
                 ('GET/'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (304) (`${url}/echo`)))))
                 ('hello'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (305) (`${url}/echo`)))))
                 ('POST/test'),
  assertResolves (withTestServer (({url}) => fl.chain (fn.bufferResponse ('utf8')) (fl.chain (fn.followRedirects (1)) (postResponse (307) (`${url}/echo`)))))
                 ('hello'),
]));

test ('autoBufferResponse', () => Promise.all ([
  assertRejects (fl.chain (fn.autoBufferResponse) (mockResponse ({headers: {'content-type': 'text/plain; charset=lalalala'}})))
                (new Error ('Failed to buffer response: Unknown encoding: lalalala')),
  assertResolves (fl.chain (fn.autoBufferResponse) (sendMockRequest (fn.emptyStream)))
                 ('GET/'),
  assertResolves (fl.chain (fn.autoBufferResponse) (sendMockRequest (fn.streamOf (Buffer.from ('hello')))))
                 ('GET/hello'),
  assertResolves (fl.chain (fn.autoBufferResponse) (mockResponse ({})))
                 ('hello'),
  assertResolves (fl.chain (fn.autoBufferResponse) (mockResponse ({headers: {}})))
                 ('hello'),
  assertResolves (fl.chain (fn.autoBufferResponse) (mockResponse ({headers: {'content-type': 'text/plain; charset=hex'}})))
                 ('68656c6c6f'),
]));

const respond200 = mockResponse ({});

test ('matchStatus', () => Promise.all ([
  assertResolves (fl.map (fn.matchStatus (() => 'else') ({200: () => '200', 201: () => '201'})) (mockResponse ({code: 400}))) ('else'),
  assertResolves (fl.map (fn.matchStatus (() => 'else') ({200: () => '200', 201: () => '201'})) (mockResponse ({code: 200}))) ('200'),
  assertResolves (fl.map (fn.matchStatus (() => 'else') ({200: () => '200', 201: () => '201'})) (mockResponse ({code: 201}))) ('201'),
]));

test ('acceptStatus', () => Promise.all ([
  assertResolves (fl.map (thenBuffer) (fl.map (fn.acceptStatus (200)) (respond200)))
                 (fl.resolve ('hello')),
  assertResolves (fl.map (thenBuffer) (fl.map (fn.acceptStatus (201)) (respond200)))
                 (fl.reject ('hello')),
]));

test ('responseToError', () => (
  assertRejects (fl.chain (fn.responseToError) (mockResponse ({
    code: 500,
    message: 'Internal Server Error',
    headers: responseHeaders,
    body: Buffer.from ('Dear user,\n\nEverything broke down.\nWe are sorry.'),
  }))) (new Error (
    'Unexpected Internal Server Error (500) response. Response body:\n' +
    '\n' +
    '  Dear user,\n' +
    '  \n' +
    '  Everything broke down.\n' +
    '  We are sorry.'
  ))
));

test ('HTTP Integration', () => {
  const notFound = res => (
    fl.chain (message => fl.reject (new Error (message))) (fn.autoBufferResponse (res))
  );

  const showResponse = res => (
    fl.map (body => `${(fn.Response.message (res)).statusCode}: ${body}`)
           (fn.autoBufferResponse (res))
  );

  const responseHandler = res => (
    fn.followRedirects (20) (res)
    .pipe (fl.chain (fn.matchStatus (fn.responseToError) ({200: showResponse, 404: notFound})))
  );

  const runTest = f => withTestServer (({url}) => fl.chain (responseHandler) (f (url)));

  return Promise.all ([
    assertResolves (runTest (url => fn.retrieve (`${url}/redirect`) ({})))
                   ('200: GET/'),
    assertRejects (runTest (url => fn.sendJson ('POST') (`${url}/redirect`) ({}) ('hello')))
                  (new Error ('Unexpected Moved Permanently (301) response. Response body:\n\n  ')),
    assertResolves (runTest (url => fn.sendJson ('POST') (`${url}/redirect-post`) ({}) ('hello')))
                   ('200: POST/"hello"'),
    assertRejects (runTest (url => fn.retrieve (`${url}/self-redirect`) ({})))
                  (new Error ('Unexpected Moved Permanently (301) response. Response body:\n\n  ')),
    assertRejects (runTest (url => fn.retrieve (`${url}/redirect-loop-a`) ({})))
                  (new Error ('Unexpected Moved Permanently (301) response. Response body:\n\n  ')),
  ]);
});
