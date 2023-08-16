#!/usr/bin/env node
'use strict';

process.title = 'pengu';

const { server: WebSocketServer } = require('websocket');
const http = require('http');
const path = require('path');
const express = require('express');
const pg = require('pg');
const poly = require('./poly');
const { Line, Point } = poly;
const pengu = require('./pengu');
const mapUtils = require('./mapUtils');
const { promisify } = require('es6-promisify');
const session = require('express-session');
const { signedCookie } = require('cookie-parser');
const morgan = require('morgan');
const serveStatic = require('serve-static');
const fs = require('fs');

function findById(arr, id) {
	for (let item of arr) {
		if (item.id === id) {
			return item;
		}
	}
	return null;
}

async function runApp() {
	let app = express();

	app.set('port', process.env.PORT ?? 8080);

	let sessionStore = new session.MemoryStore();
	let secret = Math.random().toString();
	app.use(session({store: sessionStore, resave: true, saveUninitialized: true, secret: secret, cookie: { maxAge: 10000 }, key: 'sid'}));

	let env = process.env.NODE_ENV ?? 'development';
	if (env === 'development') {
		app.use(morgan('combined'));
	}

	app.get('/', function(req, res) {
		if (!req.session.user) {
			res.redirect('/authenticate');
		} else {
			res.statusCode = 200;
			res.sendFile(path.join(__dirname, '../client/index.html'));
		}
	});

	app.use('/content', serveStatic(path.join(__dirname, '../content')));
	app.use('/', serveStatic(path.join(__dirname, '../client')));

	if (process.env.OPENID_PROVIDER) {
		require('./auth/openid')(app, process.env.OPENID_VERIFY, process.env.OPENID_REALM, process.env.OPENID_PROVIDER);
	} else {
		require('./auth/simple')(app);
	}

	let server = http.createServer(app).listen(app.get('port'));
	console.log('Started app on port %d', app.get('port'));

	let wsServer = new WebSocketServer({
		httpServer: server,
		autoAcceptConnections: false
	});

	function originIsAllowed() {
		return true;
	}

	let clients = [];
	let players = {};
	let rooms = JSON.parse(fs.readFileSync(path.join(__dirname, '../content/world/map.json'), 'utf8'), function (key, value) {
		let type;
		if (value && typeof value === 'object') {
			type = value._class;
			if (typeof type === 'string' && typeof poly[type] === 'function') {
				return new (poly[type])(value);
			}
		}
		return value;
	});
	let items = JSON.parse(fs.readFileSync(path.join(__dirname, '../content/items/items.json'), 'utf8'));

	let _dbUri = process.env.DATABASE_URL;
	let dbEnabled = _dbUri !== null;
	let pgpool = null;
	let registered = {};
	if (dbEnabled) {
		pgpool = new pg.Pool({connectionString: _dbUri});
		let result = await pgpool.query('select * from "penguin"');
		for (let row of result.rows) {
			registered[row.name] = row;
		}
	}

	wsServer.on('request', function(request) {
		if (!originIsAllowed(request.origin)) {
			request.reject();
			console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
			return;
		}

		let connection = request.accept(null, request.origin);
		clients.push(connection);
		console.log((new Date()) + ' Connection accepted.');
		connection.on('message', async function(message) {
			if (message.type === 'utf8') {
				try {
					let json = JSON.parse(message.utf8Data);
					console.log(json);
					if (json.type === 'init' && connection.name === undefined) {
						let sid = signedCookie(request.cookies.filter((x) => x.name == 'sid')[0].value, secret);
						try {
							let session = await promisify(sessionStore.get.bind(sessionStore))(sid);
							if (!session) {
								throw 'Missing session.';
							}

							if (Object.keys(players).includes(session.user)) {
								connection.drop(pengu.USERNAME_ALREADY_BEING_USED, 'Username already being used');
								return;
							}
							let name = connection.name = session.user;
							connection.sendUTF(JSON.stringify({type: 'sync', name: name, data: players}));
							if (!Object.keys(registered).includes(name)) {
								registered[name] = {clothing: [], closet: {}, registered: (new Date()).toUTCString(), group: json.group};
								if (dbEnabled) {
									try {
										await pgpool.query('insert into "penguin"("name", "closet", "clothing", "registered", "group") VALUES($1, $2, $3, $4, $5)', [name, JSON.stringify(registered[name].closet), JSON.stringify(registered[name].clothing), registered[name].registered, registered[name].group]);
									} catch (err) {
										console.log(err);
									}
								}
							}
							players[name] = registered[name];
							players[name].x = 550;
							players[name].y = 500;
							players[name].room = 'plaza';

							console.info('Initial handshake with ' + name);
							for (let client of clients) {
								client.sendUTF(JSON.stringify({type: 'enter', name: name, room: players[name].room, x: players[name].x, y: players[name].y, clothing: players[name].clothing}));
							}
							connection.sendUTF(JSON.stringify({type: 'syncCloset', closet: players[name].closet}));
						} catch (err) {
							connection.drop(pengu.SESSION_READ_ERROR, 'Problems reading session');
							throw err;
						}
					} else if (json.type === 'move') {
						let newRoom = null;
						let name = connection.name;
						let currentRoom = players[name].room;
						let target = mapUtils.getTarget(rooms[currentRoom], new Line(new Point(players[name].x, players[name].y), new Point(json.x, json.y)));
						if (rooms[currentRoom].zones[0].area.containsPoint(target)) {
							console.log('Moving ' + name + ' to ' + target);
							players[name].x = target.x;
							players[name].y = target.y;
							for (let zone of rooms[currentRoom].zones) {
								if (zone.type[0] === 'door' && zone.area.containsPoint(target)) {
									newRoom = zone.type[1];
									console.log(name + ' goes to ' + newRoom);
									players[name].room = newRoom;
									break;
								}
							}
							let msg = {type: 'move', name: name, x: players[name].x, y: players[name].y};
							if (newRoom) {
								msg.travel = newRoom;
								players[name].x = msg.newX = rooms[newRoom].spawn.x;
								players[name].y = msg.newY = rooms[newRoom].spawn.y;
							}
							for (let client of clients) {
								client.sendUTF(JSON.stringify(msg));
							}
						}
					} else if (json.type === 'message') {
						json.text = json.text.trim();
						if (json.text !== '') {
							let name = connection.name;
							if (json.text.startsWith('/')) {
								if (json.text.startsWith('/mute ') && ['admin', 'moderator'].includes(players[name].group)) {
									let bannedName = json.text.substr(6);
									players[bannedName].banned = true;
									if (dbEnabled) {
										try {
											await pgpool.query('update "penguin" set "banned"=true where "name"=$1', [bannedName]);
										} catch (err) {
											console.log(err);
										}
									}
								} else if (json.text.startsWith('/unmute ') && ['admin', 'moderator'].includes(players[name].group)) {
									let bannedName = json.text.substr(8);
									players[bannedName].banned = false;
									if (dbEnabled) {
										try {
											await pgpool.query('update "penguin" set "banned"=false where "name"=$1', [bannedName]);
										} catch (err) {
											console.log(err);
										}
									}
								}
							} else {
								if (!players[name].banned) {
									console.log(name + ' said ' + json.text);
									for (let client of clients) {
										client.sendUTF(JSON.stringify({type: 'say', name: name, text: json.text}));
									}
								} else {
									connection.sendUTF(JSON.stringify({type: 'say', name: name, text: json.text}));
								}
							}
						}
					} else if (json.type === 'addItem') {
						let name = connection.name;
						json.itemId = parseInt(json.itemId);
						if (!findById(items, json.itemId)?.available) {
							connection.sendUTF(JSON.stringify({type: 'error', message: 'Tato věc nejde v současnosti získat.'}));
							console.log(name + ' attempted to acquire ' + json.itemId);
						} else if (!Object.keys(players[name].closet).includes(`${json.itemId}`)) {
							players[name].closet[json.itemId] = {'date': new Date(), 'means': 'collect'};
							if (dbEnabled) {
								try {
									await pgpool.query('update "penguin" set "closet"=$2 where "name"=$1', [name, JSON.stringify(players[name].closet)]);
								} catch (err) {
									console.log(err);
								}
							}
							connection.sendUTF(JSON.stringify({type: 'syncCloset', closet: players[name].closet}));
							console.log(name + ' acquired ' + json.itemId);
						} else {
							connection.sendUTF(JSON.stringify({type: 'error', message: 'Tuto věc již máš.'}));
							console.log(name + ' attempted to reacquire ' + json.itemId);
						}
					} else if (json.type === 'dress') {
						let name = connection.name;
						json.itemId = parseInt(json.itemId);
						if (Object.keys(players[name].closet).includes(`${json.itemId}`)) {
							if (players[name].clothing.includes(json.itemId)) {
								players[name].clothing = players[name].clothing.filter(item => item !== json.itemId);
								console.log(name + ' undressed ' + json.itemId);
							} else {
								players[name].clothing.push(json.itemId);
								console.log(name + ' dressed ' + json.itemId);
							}
							if (dbEnabled) {
								try {
									await pgpool.query('update "penguin" set "clothing"=$2 where "name"=$1', [name, JSON.stringify(players[name].clothing)]);
								} catch (err) {
									console.log(err);
								}
							}
							for (let client of clients) {
								client.sendUTF(JSON.stringify({type: 'dress', name: name, clothing: players[name].clothing}));
							}
						}
					}
				} catch (ex) {
					console.error(ex);
				}
				// connection.sendUTF(message.utf8Data);
			}
		});
		connection.on('close', function(reasonCode, description) {
			// remove the connection from the pool
			clients = clients.filter((client) => client !== connection);
			registered[connection.name] = players[connection.name];
			delete players[connection.name];
			console.log((new Date()) + ' Peer ' + connection.remoteAddress + '(' + connection.name + ') disconnected.' + (description ? ' Reason: ' + description : ''));
			for (let client of clients) {
				client.sendUTF(JSON.stringify({type: 'exit', name: connection.name}));
			}
		});
	});

	function exitHandler() {
		console.log('Server is going down');
		for (let client of clients) {
			client.drop(pengu.SERVER_GOING_DOWN, 'Server is going down');
		}
		process.exit();
	}

	process.on('SIGINT', exitHandler);
}

runApp();
