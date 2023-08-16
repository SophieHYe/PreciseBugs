/**
 * New node file
 */
debug=true;

function Adult(databaseObject)
{
	this.databaseObject=databaseObject;
	this.scouts=[];
}

function hashPassword(password)
{
	var hash=0;

	for (i=0;i<password.length;i++)
	{
		hash+=password[i].charCodeAt()*37;
	}
	return hash;
}

function selectAdult(username, connection)
{
	var strQuery = "SELECT * FROM adult WHERE username= '" +connection.escape(username)+"'";

	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				temp= rows[0];
				temp.password="";
				if(debug)
				{console.log("SelectAdult \n"+rows[0]+"\n");}
				
				return temp;
			}
			});
}

function validateAdult(username, password, connection)
{
	var strQuery = "SELECT * FROM adult WHERE username= '" +connection.escape(username)+"'" +"AND password= '"
	+ hashPassword(password)+"'";

	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				temp= rows[0];
				temp.password="";
				
				if(debug)
				{console.log("validateAdult \n"+rows[0]+"\n");}
				
				return temp;
			}
			});
}

function insertAdult(firstName, lastName, username, password, packNumber, 
		leaderType, rankType, phoneNumber, connection)
{
	var temp= selectAdult(username, connection);

	if(temp.databaseObject.adult_id<1)
	{
		return temp;
	}
	var strQuery = "INSERT INTO adult VALUES('"+firstName+"', '"+lastName+"', '"+
	username + "', '" +hashPassword(password) + "', '" + packNumber+"', '"+
	leaderType +"', '"+ rankType+"', '"+phoneNumber+ "', 'NULL')";
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				temp= new Adult(row[0]);
				if(debug)
				{console.log("insertAdult \n"+rows[0]+"\n");}
				
				return addScoutsToParent(temp);
			}
			});
}

function updateAdult(firstName, lastName, username, password, packNumber, 
		leaderType, rankType, phoneNumber,adultID, connection)
{
	var temp= selectAdult(username, connection);

	if(temp.databaseObject.adult_id<1)
	{
		temp= new Adult(firstName, lastName, username, packNumber, 
				leaderType, rankType, phoneNumber,-1);
		return temp;
	}
	var strQuery = "UPDATE adult SET first_name="+firstName+", last_name="+lastName+", username="+
	username + ", password=" +hashPassword(password) + ", pack_number=" + packNumber+", leader_type="+
	leaderType +", rank_type="+ rankType+", phone_number="+phoneNumber+ "WHERE adult_id="+id;
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				temp= new Adult(row[0]);
				if(debug)
				{console.log("UpdateAdult \n"+rows[0]+"\n");}
				
				return addScoutsToParent(temp);
			}
			});
}

function insertAchievement(name, description, categoryID, numElectives, connection)
{
	var strQuery = "INSERT INTO achievement  VALUES('"+name+"', '" +  description + "', '" + categoryID
	+"', '"+ numElectives+ "', 'NULL')";
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertAchievement \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function insertCategory(name, description, rankID, numAchievments, connection)
{
	var strQuery = "INSERT INTO category VALUES('"+name+"', '" +  description + "', '" 
	+ rankID+"', '"+numAchievments+ "', 'NULL')";
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertCategory \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function insertRank(name, description, connection)
{
	var strQuery = "INSERT INTO rank  VALUES('"+name+"', '" +  description + "', 'NULL')";

	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertRank \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function insertRecord(recordRankType, dateDone,requirementID, 
		scoutID, connection)
{
	var strQuery = "INSERT INTO record  VALUES('"+recordRankType+"', '" +  
	dateDone + "', '" +requirementID+"', '"+scoutID+"', 'NULL')";

	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertRecord \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function insertRequirement(name, description, achievementID, reqElec, connection)
{
	var strQuery = "INSERT INTO requirement VALUES('"+name+"', '" +  description + 
	"', '" + achievementID+"', '"+reqElec+ "', 'NULL')";
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertRequirement \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function insertScout(firstName, lastName, birthDate, 
		packNumber, rankType, parentID, leaderID, connection)
{
	var strQuery = "INSERT INTO scout VALUES('"+firstName+"', '" +lastName+"', '"+
	birthDate + "', '" + packNumber + "', '"+ rankType+"', '"+parentID+", "+leaderID+"', 'NULL')";
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("insertScout \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function updateScout(firstName, lastName, birthDate,packNumber, 
		 rankType, parentID, leaderID, scoutID, connection)
{
	var strQuery = "UPDATE scout SET first_name="+firstName+", last_name="+lastName+", birth_date="+
	 + ", pack_number=" + + ", rank_type=" + packNumber+", parent_id="+
	leaderType +", leader_id="+ rankType+" WHERE scout_id="+id;
	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{
				if(debug)
				{console.log("updateScout \n"+rows[0]+"\n");}
				return rows[0];
			}
			});
}

function addScoutsToParent(adult, connection)
{
	var strQuery = "SELECT * FROM scout WHERE parent_id= '" +adult.rowID+"'";

	connection.query( strQuery, function(err, rows)
			{if(err) {
				throw err;
			}else{

				if(debug)
				{console.log("addScoutsToParent \n");}
				for(i=0;i<rows.length ;i++)
				{
					if(debug)
					{console.log("Scout "+i+"\n"+rows[i]+"\n");}
					adult.scouts.add(rows[i]);
				}
				return adult;
			}
			});
}