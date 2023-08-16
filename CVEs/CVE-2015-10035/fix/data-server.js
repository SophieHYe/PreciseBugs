var http = require('http');
var url = require('url');
var mysql = require('mysql');

/**
 * Database connection
 */
var connection = mysql.createConnection({
	host     : 'localhost',
	database : 'tests',
	user     : 'root',
	password : 'root',
});

/**
 * Gets the 'test' objects from the database
 * @param response Response to write the test to
 * @param callback Function to map sub objects to the list of tests
 */
function getTestObjects(response, table ,callback){
	var tests = [];
	var query = connection.query("SELECT * FROM "+table+" ORDER BY id DESC", function(err, rows, fields) {
		if (err) {
			console.log(err);
		} else {
			for(var i = 0; i < rows.length; i++){
				rows[i].notes = [];
				tests[i] = rows[i];
			}
		}
	});

	query.on('end',function(){
		if(callback){
			callback(response,tests, table);
		}
		else {
			response.write(JSON.stringify(tests));
			response.end();
		}
	});
}

function getStats(response, filter, table){
	var stats = [];
	var query;
	if(filter === "all"){
		query = connection.query("SELECT (SELECT COUNT(*) FROM "+table+" WHERE status = 'success') AS success, "+ 
				"(SELECT COUNT(*) FROM "+table+" WHERE status = 'danger') as failure, "+
				"(SELECT COUNT(*) FROM "+table+" WHERE status != 'success' AND status != 'danger') as other", function(err, rows, fields) {
			if (err) {
				console.log(err);
			} else {
				for(var i = 0; i < rows.length; i++){
					stats[i] = rows[i];
				}
			}
		});

		query.on('end',function(){
			response.write(JSON.stringify(stats));
			response.end();
		});	
	}
}

/**
 * Gets the notes and adds them to the 'test' objects.
 * There is probably one query that could be ran to pull this info
 * into and object, to simplify code but only way I was skilled enough to  was 
 * first select 'tests' then manually add 'notes' to them with another
 * select statement. 
 * @param response Response to write the tests to
 * @param tests Array of tests from the DB
 */
function getAddNotesToTests(response, tests, table){
	var notes = [];
	var query = connection.query('SELECT * FROM notes_'+table.substring(6), function(err, rows, fields) {
		if (err) {
			console.log(err);
		} else {
			for(var i = 0; i < rows.length; i++){
				notes[i] = rows[i];
			}
		}
	});

	query.on('end',function(){
		for(var i = 0; i < tests.length; i++){
			var test = tests[i];
			for(var j = 0; j < notes.length; j++){
				var note = notes[j];
				if(note.testId === test.id){
					test.notes.push(note);
				} 
			}
		}
		response.write(JSON.stringify(tests));
		response.end();
	});
}

/**
 * Gets the tables in the database to fill the projects drop down
 * @param response Response to return
 */
function getProjectTables(response){
	var projects = [];
	var query = connection.query("SELECT TABLE_NAME as project FROM information_schema.tables WHERE TABLE_NAME LIKE 'test_%'", function(err, rows, fields) {
		if (err) {
			console.log(err);
		} else {
			for(var i = 0; i < rows.length; i++){
				projects[i] = rows[i];
			}
		}
	});

	query.on('end',function(){
		for(var j = 0; j < projects.length; j++){
			var project ={};
			project.db = projects[j].project;
			project.name = projects[j].project.substring(6);
			projects[j] = project;
		}
		response.write(JSON.stringify(projects));
		response.end();
	});
}

/**
 * Adds a note to a test
 * @param response The response to send back
 * @param note Note object to add
 */
function addNote(request, response, note){
	var parts = url.parse(request.url,true);
	var queryStr = "INSERT INTO notes_"+(parts.query.table).substring(6)+" (testId,who,note) VALUES ("+connection.escape(note.testId)+"," + connection.escape(note.who) + ","+connection.escape(note.note)+")";
	var query = connection.query(queryStr,function(err, rows, fields) {
		if (err) {
			response.statusCode = 400;
			console.log(err);
		} 
	});

	query.on('end',function(){
		response.end();
	});
}

/**
 * Handles requests to add a 'test' to the DB
 * @param request The http request
 * @param response The http response
 * @param body The data from the post request
 */
function addTest(request, response, body){
	var table = body.table;
	var test = body.result;
	var start = new Date(Number(test.start));
	var end = new Date(Number(test.end));

	//Format to YYYY-MM-DD HH:MM:SS
	start = start.getFullYear()+"-"+(start.getMonth()+1)+"-"+start.getDate()+" "+start.getHours()+":"+start.getMinutes()+":"+start.getSeconds();
	end = end.getFullYear()+"-"+(end.getMonth()+1)+"-"+end.getDate()+" "+end.getHours()+":"+end.getMinutes()+":"+end.getSeconds();

	var queryStr = "INSERT INTO "+table+" (name,param,error,start,end,status,extra,runInfo) VALUES ("+connection.escape(test.name)+"','" + connection.escape(test.param) + ","+connection.escape(test.error)+ "','"+start+ "','"+end+ "',"+connection.escape(test.status)+ ","+connection.escape(test.extra)+ ","+connection.escape(test.runInfo)+")";
	var query = connection.query(queryStr, function(err, rows, fields) {
		if (err) {
			response.statusCode = 400;
			console.log(err);
		} 
	});

	query.on('end',function(){
		response.end();
	});
}

/**
 * Function handles JSON POST requests, will read the data and once done
 * dispatch the request and response the the action function
 * @param request The http request
 * @param response The http response
 * @param action The function to be ran once the body is read
 */
function handlePost(request, response, action){
	response.statusCode = 200;
	response.setHeader('Access-Control-Allow-Origin', 'http://localhost:8000');
	response.setHeader('Access-Control-Allow-Methods', 'POST');
	response.setHeader('Access-Control-Allow-Headers', 'Content-Type');
	var body = '';	
	request.on('data', function (data) {
		body += data;
	});		
	request.on('error', function(e) {
		console.log('Bad post');
		response.statusCode = 400;
		response.end();
	});		
	request.on('end', function () {
		//TODO Mysterious blank post is sent (below work around)
		if(body.length){
			action(request, response, JSON.parse(body));
		} else{
			response.end();
		}
	});
}

/**
 * Server, so far just handles request to /getTestData
 */
var server = http.createServer(function (request,response){
	var parts = url.parse(request.url,true);
	var path = parts.path;
	response.setHeader('Access-Control-Allow-Origin', 'http://localhost:8000');
	response.setHeader('Access-Control-Allow-Methods', 'GET');
	if(path.indexOf("/getTestData?table=") === 0){
		response.statusCode = 200;
		getTestObjects(response, parts.query.table, getAddNotesToTests);
	} else if(path.indexOf("/getStats?filter") === 0){
		response.statusCode = 200;
		getStats(response,parts.query.filter,parts.query.table);
	} else if(parts.pathname === "/addNote"){
		handlePost(request, response, addNote);
	} else if(path === "/addTest") {
		handlePost(request, response, addTest);
	} else if(path === "/getProjects"){
		response.statusCode = 200;
		getProjectTables(response);
	} else {
		response.statusCode = 404;
		response.write("Invalid")
		response.end();
	}
});

server.listen(4968);
console.log("Server Started on port 4968");