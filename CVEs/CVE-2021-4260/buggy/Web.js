'use strict';

const extend = Object.assign;
const express = require('express');
const domain = require('domain');
const mongoose = require('mongoose');
const Schema = mongoose.Schema;
const flash = require('connect-flash');
const path = require('path');
const fs = require('fs');
const csrf = require('csurf');
const routeUtils = require('./utils/routeUtils');
const stringUtils = require('./utils/stringUtils.js');
const callsites = require('callsites');
const webExtender = require('./loaders/webExtender.js');
const {customAlphabet} = require('nanoid/non-secure');
const nanoidInsecure = customAlphabet('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 10)

/**
Oils web app
*/
class Web {

  constructor(customConf) {

    let web = this;
    web.lib = web.lib || {};

    let webId = nanoidInsecure();
    web.id = webId;

    Object.defineProperty(web.lib, 'mongoose', {
      get: function() {
        let stack = new Error().stack;
        console.warn("Use web.require('mongoose') instead of calling web.lib..", stack);
        return require('mongoose');
      }
    });

    if (!global._web) {
      global._web = {};
    }

    let conf = {};

    if (!conf.baseDir) {
      let tmpBaseDir = __filename.substr(0, __filename.indexOf('node_modules') - 1);
      if (!stringUtils.isEmpty(tmpBaseDir)) {
        conf.baseDir = tmpBaseDir;
      }
    }

    if (global._web[conf.baseDir]) {
      throw new Error("Web has been redefined " + conf.baseDir + " vs " + JSON.stringify(callerId.getData()));
    }

    global._web[conf.baseDir] = web;

    if (!global.hasOwnProperty('web')) {
      Object.defineProperty(global, 'web', {
        get: function() {
          if (Object.keys(global._web).length === 1) {
            return web;
          }

          for (let i in global._web) {
            if (stringUtils.startsWith(callsites()[1].getFileName(), i)) {
              //console.warn('Found new web! ' + i);
              return global._web[i];
            }
          }

          throw new Error("Web cache not found " + JSON.stringify(callerId.getData()));
          //return web;
        }
      })
    }
 
    //load custom config file
    this.conf = require('./conf/conf-default.js')();
    this.conf = extend(this.conf, conf);
    
    if (this.conf.customConfigFile) {
      let customConf = requireNvm(path.join(this.conf.baseDir, this.conf.customConfigFile));
      if (customConf) {
        this.conf = extend(this.conf, customConf);
      }
    }

    if (this.conf.pluginsConfPath) {
      console.log("Reading plugins conf", this.conf.pluginsConfPath);
      this.conf.plugins = this.conf.plugins || {};
      this.conf.plugins = extend(this.conf.plugins, web.includeNvm(this.conf.pluginsConfPath));
    }

    //zconf: third config path for environmental / more private properties
    if (this.conf.zconf === true) {
      let customConf = requireNvm(path.join(this.conf.baseDir, 'conf', 'zconf.js'));
      if (customConf) {
        console.info('Found zconf... extending.');
        this.conf = extend(this.conf, customConf);
      }
    } else if (this.conf.zconf) {
      let zconf = requireNvm(this.conf.zconf);
      if (zconf) {
        this.conf = extend(this.conf, zconf);
        console.info('Found zconf.. extending.');
      } else {
        console.warn(web.conf.zconf, 'not found. Ignoring.');
      }
    }

    if (customConf) {
      this.conf = extend(this.conf, customConf);
    }

    this.logger = require('./utils/logger.js')(this);

    console.isDebug = this.conf.isDebug;
    if (console.isDebug) {
      console.debug('Oils config: ' + JSON.stringify(this.conf, null, 2));
    }

    this.app = express();
    this.events = {};
    this.modelCache = new Object();
    this.plugins = [];

    this.overrideResponse();

    if (this.conf.extendWeb && this.conf.extendWeb.enabled) {
      webExtender.load(this, this.conf.extendWeb.path, this.conf.extendWeb.context);
    }
  }

  //a way to use oils library so no need to re-install
  //e.g. const moment = web.require('moment');
  require(str) {
    return require(str);
  }

  overrideResponse() {
    const web = this;
    // override default res.render
    const render = express.response.render;

    web.on('beforeRender', web.initBeforeRender)

    express.response.render = function(view, options = {}, callback) {
      const req = this.req;
      const res = this;

      web.callEvent('beforeRender', [view, options, callback, req, res])
      render.apply(this, [view, options, callback]);
    };

    // res.renderFile is deprecated, same as res.render
    // retained for backward compat
    express.response.renderFile = express.response.render;
  }

  initBeforeRender(view, options, callback, req, res) {

    if (req.flash) {
      // it seems when 500 error is called, flash is not available
      // after it res.render was moved
      options['_errors'] = req.flash('error');
      options['_warns'] = req.flash('warn');
      options['_infos'] = req.flash('info');
    }
    options['_conf'] = web.conf.viewConf;
    options['_ext'] = req.ext;

    if (web.conf.enableCsrfToken && req.csrfToken) {
      options['_csrf'] = req.csrfToken();
    }

  }

  // requireNvm - cannot define this as a utility because it will never work
  // because it will require from this directory.
  // use includeNvm instead

  // EVENTS -----------
  on(eventStr, callback) {
    if (!this.events[eventStr]) {
      this.events[eventStr] = [];
    }

    this.events[eventStr].push(callback);
  }

  // use call if you want async
  callEvent(eventStr, argsArray){
    let myEvents = this.events[eventStr];
    if (myEvents) {
      for (let myEvent of myEvents) {
        myEvent.apply(this, argsArray);
      }
    }

  }

  async call(eventStr, argsArray){
    let myEvents = this.events[eventStr];
    if (myEvents) {
      for (let myEvent of myEvents) {
        try {
          await myEvent.apply(this, argsArray);
        } catch (ex) {
          console.error("Error loading one event", eventStr, ex);
        }
      }
    }

  }
  // EVENTS end -------

  include(file) {
    return require(this.includeFullPath(file));
  }

  includeNvm(file) {
    return requireNvm(this.includeFullPath(file));
  }

  includeFullPath(file) {
    let baseDir = this.conf.baseDir || process.cwd();
    return path.join(this.conf.baseDir, file);
  }

  //MODELS ------------

  includeModelObj(modelJs) {
    let web = this;
    let modelName = modelJs.name;
    
    if (!modelJs.schema) {
      throw new Error(modelName + '.schema not found.');
    }

    let collectionName = undefined;
    if (modelJs.parentModel) {
      
      let parentModel = web.includeModel(modelJs.parentModel);
      let parentModelJs = parentModel.getModelDictionary();
      collectionName = parentModel.collection.name;

      modelJs.schema = extend(parentModelJs.schema, modelJs.schema);
      
      let origSchema = modelJs.initSchema;
      modelJs.initSchema = [];
      if (origSchema) {
        modelJs.initSchema.push(origSchema);
      }

      if (parentModelJs.initSchema) {
        modelJs.initSchema.push(parentModelJs.initSchema);
      }

      modelJs.options = extend(parentModel.options || {}, modelJs.options);

      if (console.isDebug) {
        console.debug('Model %s has a parent %s', modelName, modelJs.parentModel);
      }

    }

    let conn;

    let getModelConnection = function(modelJs) {
      if (modelJs.connection) {
        return modelJs.connection
      }
      
      if (modelJs.parentModel) {
        return getModelConnection(web.include(modelJs.parentModel));
      }
    }

    let modelConn = getModelConnection(modelJs);
    if (modelConn) {
      conn = this.connections[modelConn];
      if (web.conf.isDebug) {
        console.debug("Found model conn: ", modelJs.name, modelConn);
      }
    } else if (web.connections.mainDb) {
      conn = web.connections.mainDb; 
    } else {
    for (let i in this.connections) {
        //get the first connection
        conn = this.connections[i];
        break;
      }
    }

    if (!conn) {
      console.warn('No defined DB. Check your configuration.');
      return;
    }
    
    let schema = new Schema(modelJs.schema, modelJs.options);

    //TODO: executing schemas of children are not a good idea
    if (modelJs.initSchema) {
      if (modelJs.initSchema instanceof Array) {
        for (let i in modelJs.initSchema) {
          let mySchema = modelJs.initSchema[i];
          
          mySchema(schema);
        }
      } else {
        //console.debug('[%s] Executing normal initSchema %s', modelJs.name ,modelJs.initSchema);
        //fixed bug where schemas are execd twice
        if (!modelJs.initSchema.execd) {
          modelJs.initSchema(schema);
        }
      }
      
    }

    let model = conn.model(modelName, schema, collectionName);
    if (console.isDebug) {
      console.debug("Loaded model for the first time: " + modelName)
    }

    web.modelCache[modelName] = model;
    model.getModelDictionary = function() {
      return modelJs;
    }        
    return model;
  }

  includeModel(workingPath) {

    let web = this;
    let modelJs = null;

    //removed try catch bec v6+ of node already include stack info
    modelJs = web.include(workingPath);

    if (!modelJs.name) {
      if (!workingPath) {
        throw new Error('Model name must be defined.');
      }
      modelJs.name = path.basename(workingPath, '.js');
    }

    if (web.modelCache[modelJs.name]) {
      if (console.isDebug) {
        console.debug("Loading model %s from cache", modelJs.name)
      }
      return web.modelCache[modelJs.name];
    }
   
    return web.includeModelObj(modelJs);
  }

  models(modelName) {

    if (!this.modelCache[modelName]) {
      let workingPath = this.conf.modelsDir + '/' + modelName;
      this.modelCache[modelName] = this.includeModel(workingPath);
    }
    return this.modelCache[modelName];

  }

  //END MODELS --------


  addPlugin(plugin) {
    this.plugins[plugin.id] = plugin;
  }

  async loadPlugins(cb) {
    let self = this;
    let pluginFunctions = [];

    // delete the concept of next in the far future, loading of functions are now async
    let nextObsolete = function(){};

    for (let pluginId in self.plugins) {
      let plugin = self.plugins[pluginId];
      try {
        await plugin.load(plugin.conf, self, nextObsolete);
      } catch (ex) {
        console.error("Error loading plugin", pluginId, ex);
      }
    }

    if (cb) {
      cb();
    }
  }

  //for deprection, use addRoutes whenever possible instead
  applyRoutes(routes) {
    let stack = new Error().stack;
    console.warn("Consider using web.addRoutes instead of applyRoutes.", stack);
    this._applyRoutes(routes);
  }

  _applyRoutes(routes) {
    for (let routeKey in routes) {
      let customRoute = routes[routeKey];
      if (console.isDebug) {
        console.debug('[conf.route] ' + routeKey);
      }
      routeUtils.applyRoute(web, routeKey, customRoute);
    }
  }

  addRoutes(routes) {
    this.conf.routes = this.conf.routes || {};
    for (let key in routes) {
      if (this.conf.routes[key]) {
        console.warn("Check conflicting routes:", key, confRoutes);
      }

      this.conf.routes[key] = routes[key];
    }
  }

  async initServer(cb) {
    let app = this.app;
    let web = this;
    let self = this;
    let bodyParser = require('body-parser');
    let methodOverride = require('method-override');
    let cookieParser = require('cookie-parser');
    let cookieSession = require('cookie-session');

    let httpsConfigEnabled = (web.conf.https && web.conf.https.enabled) || web.conf.httpsOpts.httpsEnabled;

    if (httpsConfigEnabled) {
      let defaultHttpsConf = require('./conf/conf-https-default.js')(web);

      let defaultLetsEncryptConf = defaultHttpsConf.letsEncrypt || {};

      let confLetsEncrypt = web.conf.https && web.conf.https.letsEncrypt;

      web.conf.https = extend(defaultHttpsConf, web.conf.https || {});

      web.conf.https.letsEncrypt = extend(
        defaultLetsEncryptConf,
        confLetsEncrypt || {},
        web.conf.httpsOpts.letsEncrypt
      );
    }

  
    let templatesPath = path.join(self.conf.baseDir, self.conf.viewsDir);

    if (self.conf.templateLoader) {
      self.templateEngine = self.conf.templateLoader(web, templatesPath);
    } else {
      self.templateEngine = require('./engines/nunjucks')(web, templatesPath);
    }
    

    var rawBodySaver = function (req, res, buf, encoding) {
      if (web.conf.saveRawBody && buf && buf.length) {
        req.rawBody = buf.toString(encoding || 'utf8');
      }
    }

    if (web.conf.trustProxy) {
      console.log("Trusting proxy", web.conf.trustProxy);
      app.set('trust proxy', web.conf.trustProxy);
    }

    app.use(bodyParser.json({limit: web.conf.parserLimit, verify: rawBodySaver, parameterLimit: web.conf.parserParameterLimit}));
    app.use(bodyParser.urlencoded({
      extended: true,
      limit: web.conf.parserLimit,
      verify: rawBodySaver,
      parameterLimit: web.conf.parserParameterLimit
    }));

    if (web.conf.saveRawBody) {
      console.warn("conf.saveRawBody uses a raw parser that conflicts with multer. Better maybe to save raw data in controller level instead.")
      // raw interferes with multer (upload files) 
      // https://github.com/expressjs/multer/issues/523
      // use sparingly or better move to controller level
      app.use(bodyParser.raw({ verify: rawBodySaver, type: '*/*', limit: web.conf.parserLimit, parameterLimit: web.conf.parserParameterLimit}));
    }

    
    app.use(methodOverride());
    let cookieKey = web.conf.secretPassphrase;
    if (cookieKey === "change-this-it-is-2019!") {
      throw new Error("Security error. Change conf.secretPassphrase.");
    }
    app.use(cookieParser(cookieKey));
  
    app.use(cookieSession({keys: [cookieKey], 
      cookie: {maxAge: self.conf.cookieMaxAge},
      maxAge: self.conf.cookieMaxAge, //documentation is confusing that's why need to dup
    }));

    if (self.conf.enableCsrfToken) {
      let excludePaths = self.conf.enableCsrfToken.excludes;
      if (excludePaths && excludePaths.length) {
        app.use(function(req, res, next) {
          let hasCalledNext = false;
          for (let path of excludePaths) {
            if ( (path instanceof RegExp && path.test(req.path))
              || (req.path === path)) {

              next();
              hasCalledNext = true;
              break;
            }
          }

          if (!hasCalledNext) {
            csrf()(req, res, next);
          }
        })
      } else {
        app.use(csrf());
      }
      
    }

    await self.call('afterWebMiddleware');
   
    app.use(require('./middleware/custom-response.js')());
    app.use(flash());

    if (web.conf.validateNoSqlInject) {
      console.log("Adding validation for nosql injection");
      
      app.use(function(req, res, next) {
        //prevent NOSQL injection for mongoose
        let valid = false;
        try {
          web.validateNoSqlInject(req.query);
          web.validateNoSqlInject(req.body);
          web.validateNoSqlInject(req.params);
          valid = true;
        } catch (ex) {
          console.error('Error in validation nosql', ex);
          var ip = req.header('x-forwarded-for') || (req.connection && req.connection.remoteAddress);
          console.error("[ALERT] Possible NOSQL injection", req.url, req.query, req.body, req.params, ip, req.user);
          res.status(400).send("Invalid Request");
        }

        if (valid) {
          next();
        }

      });
    }

    await require('./loaders/connections.js')(self);

    require('./loaders/plugins.js')(self);
    
    await self.loadPlugins();

    //use this for adding events prior to adding routes
    await self.call('loadPlugins');

    let confRoutes = self.conf.routes || {};

    confRoutes = extend(self.includeNvm(self.conf.routesFile) || {}, confRoutes);

    self.conf.routes = confRoutes;
    self._applyRoutes(self.conf.routes);
    await require('./loaders/controllers')(self);

    app.use(self.conf.publicContext, express.static(path.join(self.conf.baseDir, self.conf.publicDir)));
    
    await self.call('initServer');

    
    app.use(function(err, req, res, next){
      res.status(500);
      if (web.conf.handle500) {
        web.conf.handle500(err, req, res, next);
      } else {
        res.send("This is embarrassing.");
      }
      console.error("General error", err);
    });


    if (cb) {
      await cb();
    }
    
  }

  validateNoSqlInject(query) {
    if (query) {
      for (let key in query) {
        if (key && key[0] === '$') {
          console.error("Invalid key found", key);
          throw new Error("Invalid request [999]");
        }

        if (query[key] && typeof query[key] == 'object') {
          this.validateNoSqlInject(query[key]);
        }
      }
    }

    return query;
  }

  getLetsEncryptLex() {
    let self = this;

    if (!self.lex) {

      if (!self.conf.https.letsEncrypt.email) {
        throw new Error("conf.https.letsEncrypt.email must not be nil.");
      }

      //validations
      let letsEncrServer = self.conf.https.letsEncrypt.testing ? self.conf.https.letsEncrypt.stagingServer : self.conf.https.letsEncrypt.prodServer;

      self.conf.https.letsEncrypt.server = self.conf.https.letsEncrypt.server || letsEncrServer;

      if (self.stringUtils.isEmpty(self.conf.https.letsEncrypt.server)) {
        throw new Error("Cannot find encrypt server.");
      }

      if (!self.conf.https.letsEncrypt.approveDomains) {
        throw new Error("conf.https.letsEncrypt.approveDomains must not be nil. See wildcard.js example from greenlock-express");
      }

      if (console.isDebug) {
        console.debug('Server:', letsEncrServer, 'with https conf:', self.conf.https);
      }


      self.lex = require('greenlock-express').create(self.conf.https.letsEncrypt);
    }

    return self.lex
  }

  /**
   * Start the server (starts up the sample application).
   * @param {Web~startCallback} cb - called after server starts.
   */
  start(cb) {
    let serverDomain = domain.create();
    serverDomain.on('error', function(err) {
      console.error('Server domain caught an exception: ' + err);
      if (err) {
        console.error(err.stack);
      }
    });

    let web = this;
    serverDomain.run(function() {

      // Initialize the express server and routes.
      // we don't await because of unknown behavior with domains
      web.initServer(function() {
        startServer(web, cb);
      });


    });
  }

};




//collection of general utilties
Web.prototype.utils = require('./utils/oilsUtils.js');

//collection of common file utilities
Web.prototype.fileUtils = require('./utils/fileUtils.js');

//collection of common date utilities
Web.prototype.dateUtils = require('./utils/dateUtils.js');

//collection of common string utilities
Web.prototype.stringUtils = stringUtils;

Web.prototype.objectUtils = require('./utils/objectUtils.js');

Web.prototype.sleep = sleep;

//web.Plugin.extend..
Web.prototype.Plugin = require('./Plugin.js');

module.exports = Web;


function _applyRoutes(routes) {
  for (let routeKey in routes) {
    let customRoute = routes[routeKey];
    if (console.isDebug) {
      console.debug('[conf.route] ' + routeKey);
    }
    routeUtils.applyRoute(web, routeKey, customRoute);
  }
}

function requireNvm(libStr) {
  try {
    return require(libStr);
  } catch (er) {
    if (er.code === 'MODULE_NOT_FOUND') {
      if (console.isDebug) {
        console.debug('Ignoring file not found through requireNvm', libStr);
      }
      return null;
    } else {
      throw er;
    }
  }

  console.error("[requireNvm] Unexpected end");
}

async function sleep(ms) {
  return new Promise(function(resolve, reject) {
    return setTimeout(resolve, ms);
  })
}


function startListening(httpServer, opts = {}, cb) {
  const {port, ipAddress, addtlLog = ""} = opts;
  httpServer.listen(port, ipAddress, function(err, result) {
     if (err) {
        console.error("Error starting node server", err);
      } else {
        console.log('%s: Node server started on %s:%d %s...',
                  Date(Date.now()), ipAddress, port, addtlLog);
      }
      
      if (cb) {
        cb(err, httpServer, opts);
      }
  });
}

function defaultRedirectToHttpsMiddleware(req, res) {
  let nonStandardPort = '';
  let portToUse = (web.conf.httpsOpts.port || web.conf.https.port);
  if (portToUse !== 443) {
    nonStandardPort = ':' + portToUse;
  }

  let hostStr;
  if (req.headers.host) {
    hostStr = req.headers.host.split(':')[0]
  } else {
    hostStr = req.hostname;
  }
  res.writeHead(302, {'Location': 'https://' + hostStr + nonStandardPort + req.url});
  res.end();
}


function startServer(web, cb) {

  const http = require('http');

  let alwaysSecure = null;

  let httpsConfigEnabled = (web.conf.https && web.conf.https.enabled) || web.conf.httpsOpts.httpsEnabled;

  let middlewareToUse = function(anotherMiddleware) {
    return anotherMiddleware;
  }
  
  if (httpsConfigEnabled) {
    if (web.conf.https.letsEncrypt) {
      
      let https = web.conf.https.getHttpsServer();
      let lex = web.getLetsEncryptLex();
      let httpsPort = web.conf.https.port || 443;

      middlewareToUse = lex.middleware;
      
      let httpServer = https.createServer(lex.httpsOptions, middlewareToUse(web.app));
      startListening(httpServer, {port: httpsPort, ipAddress: web.conf.ipAddress, addtLog: "(HTTPS)"}, cb);

    } else {
      let https = web.conf.https.getHttpsServer();
      let privateKey = fs.readFileSync(web.conf.https.privateKey, 'utf8');
      let certificate = fs.readFileSync(web.conf.https.certificate, 'utf8');
      let credentials = {key: privateKey, cert: certificate};

      let httpsServer = https.createServer(credentials, web.app);
      startListening(httpServer, {port: web.conf.port, ipAddress: web.conf.ipAddress, addtlLog: "(HTTPS)"}, cb);
    }

    alwaysSecure = web.conf.https.alwaysSecure;
  }
  
  if ((alwaysSecure && alwaysSecure.enabled) || web.conf.httpsOpts.alwaysSecure) {
    let redirectMiddleware = alwaysSecure.redirectHandler 
      || defaultRedirectToHttpsMiddleware;

    let httpRedirecter = http.createServer(middlewareToUse(redirectMiddleware));
    startListening(httpRedirecter, {port: web.conf.port, ipAddress: web.conf.ipAddress}, cb);
  } else {
    let httpServer = http.createServer(middlewareToUse(web.app));
    startListening(httpServer, {port: web.conf.port, ipAddress: web.conf.ipAddress}, cb);
  }
}
  

