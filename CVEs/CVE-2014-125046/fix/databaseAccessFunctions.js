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

function insertAdult(firstName, lastName, username, password, packNumber, 
		leaderType, rankType, phoneNumber, connection)
{
	var temp= selectAdult(username, connection);

	if(temp.databaseObject.adult_id<1)
	{
		return temp;
	}
	var strQuery = "INSERT INTO adult VALUES('"+
	connection.escape(firstName)               +"', '"+
	connection.escape(lastName)                +"', '"+
	connection.escape(username)                +"', '"+
	connection.escape(hashPassword(password))  +"', '"+ 
	connection.escape(packNumber)              +"', '"+	
	connection.escape(leaderType)              +"', '"+ 
	connection.escape(rankType)                +"', '"+
	connection.escape(phoneNumber)             +"', 'NULL')";

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
	var strQuery = "SELECT * FROM adult WHERE username= '" +
	connection.escape(username)+"'" +"AND password= '"     + 
	connection.escape(hashPassword(password))+"'";

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
	var strQuery = "UPDATE adult SET "+
	"first_name="    +connection.escape(firstName)             +
	", last_name="   +connection.escape(lastName)              +
	", username="    +connection.escape(username)              + 
	", password="    +connection.escape(hashPassword(password))+ 
	", pack_number=" +connection.escape(packNumber)            +
	", leader_type=" +connection.escape(leaderType)            +
	", rank_type="   +connection.escape(rankType)              +
	", phone_number="+connection.escape(phoneNumber)           + 
	"WHERE adult_id="+connection.escape(id);
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
	var strQuery = "INSERT INTO achievement  VALUES('"+
	connection.escape(name)                           +"', '"+ 
	connection.escape(description)                    +"', '"+ 
	connection.escape(categoryID)                     +"', '"+ 
	connection.escape(numElectives)                   +"', 'NULL')";
	
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
	var strQuery = "INSERT INTO category VALUES('"+
	connection.escape(name)                       +"', '"+
	connection.escape(description)                +"', '"+ 
	connection.escape(rankID)                     +"', '"+
	connection.escape(numAchievments)             +"', 'NULL')";
	
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
	var strQuery = "INSERT INTO rank  VALUES('"+
	connection.escape(name)                    +"', '"+  
	connection.escape(description)             +"', 'NULL')";

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
	var strQuery = "INSERT INTO record  VALUES('"+
	connection.escape(recordRankType)            +"', '"+  
	connection.escape(dateDone)                  +"', '"+
	connection.escape(requirementID)             +"', '"+
	connection.escape(scoutID)                   +"', 'NULL')";

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
	var strQuery = "INSERT INTO requirement VALUES('"+
	connection.escape(name)                          +"', '" + 
	connection.escape(description)                   +"', '" + 
	connection.escape(achievementID)                 +"', '"+
	connection.escape(reqElec)                       +"', 'NULL')";
	
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
	var strQuery = "INSERT INTO scout VALUES('"+
	connection.escape(firstName)               +"', '"+
	connection.escape(lastName)                +"', '"+
	connection.escape(birthDate)               +"', '"+ 
	connection.escape(packNumber)              +"', '"+ 
	connection.escape(rankType)                +"', '"+
	connection.escape(parentID)                +"', '"+
	connection.escape(leaderID)                +"', 'NULL')";
	
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
	var strQuery = "UPDATE scout SET "+
	"first_name="     +connection.escape(firstName)  +
	", last_name="    +connection.escape(lastName)   +
	", birth_date="   +connection.escape(birthdate)  + 
	", pack_number="  +connection.escape(packNumber) + 
	", rank_type="    + connection.escape(rankType)  +
	", parent_id="    +connection.escape(parentID)   +
	", leader_id="    + connection.escape(leaderID)  +
	" WHERE scout_id="+connection.escape(id);
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
	var strQuery = "SELECT * FROM scout WHERE parent_id= '" +connection.escape(adult.rowID)+"'";

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