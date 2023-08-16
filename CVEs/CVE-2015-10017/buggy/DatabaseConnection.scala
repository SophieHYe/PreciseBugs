package prolod.common.config

import java.io.{File, FileNotFoundException}
import java.sql._
import java.{lang, util}

import com.ibm.db2.jcc.am.{SqlDataException, SqlException, SqlIntegrityConstraintViolationException, SqlSyntaxErrorException}
import com.typesafe.slick.driver.db2.DB2Driver.api._
import play.api.libs.json._
import prolod.common.models.PatternFormats.patternDBFormat
import prolod.common.models.{Dataset, Group, Pattern, PatternFromDB, _}
import slick.jdbc.{StaticQuery => Q}
import slick.profile.SqlStreamingAction

import scala.Function._
import scala.collection.JavaConverters._
import scala.collection.mutable
import scala.concurrent.Await
import scala.concurrent.duration.Duration
import scala.io.Source

/*
case class Schemata(id : String, schema_name : String, entities : Int, tuples : Int)

class Schematas(tag: Tag)
extends Table[Schemata](tag, "PROLOD_MAIN.SCHEMATA") {

def id = column[String]("id", O.PrimaryKey)
def schema_name = column[String]("schema_name", O.NotNull)
def entities = column[Int]("entities", O.NotNull)
def tuples = column[Int]("tuples", O.NotNull)

def * = (id, schema_name, entities, tuples) <> (Schemata.tupled, Schemata.unapply)
}
*/

class DatabaseConnection(config : Configuration) {
	var driver = com.typesafe.slick.driver.db2.DB2Driver.api

	val url = "jdbc:db2://"+config.dbDb2Host+":"+config.dbDb2Port+"/"+config.dbDb2Database
	val username = config.dbDb2Username
	val password = config.dbDb2Password
	// Class.forName("com.typesafe.slick.driver.db2.DB2Driver")
	Class.forName("com.ibm.db2.jcc.DB2Driver")
	// DriverManager.getConnection(url, username, password)

	val db:Database = Database.forURL(url, username, password, driver="com.ibm.db2.jcc.DB2Driver")
	var connection:Connection = DriverManager.getConnection(url, username, password)

	/**
	 * execute sql and convert async result to sync - http://slick.typesafe.com/doc/3.0.0/sql.html
	 */
	def execute[T](implicit sql: SqlStreamingAction[Vector[T], T, Effect]): Vector[T] = {
		val q = db.run(sql)
		Await.result(q, Duration.Inf)
		val value = q.value.get
		value.get
	}

	def dropTables(name : String): Unit = {
		try {
			val createStatement = connection.createStatement()
			createStatement.execute("DROP TABLE "+name+".patterns")
			createStatement.close()
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			val createStatement = connection.createStatement()
			createStatement.execute("DROP TABLE "+name+".coloredpatterns")
			createStatement.close()
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			val createStatement = connection.createStatement()
			createStatement.execute("DROP TABLE "+name+".graphstatistics")
			createStatement.close()
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			var createStatement = connection.createStatement()
			var createResultSet = createStatement.execute("DROP TABLE "+name+".CLUSTERS")
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}

		val sqlDir = new File("prolod-preprocessing/sql/")
		for (file <- sqlDir.listFiles) {
			try {
				val queryString = Source.fromFile(file.getPath).mkString
				val query = String.format(queryString, name)
				try {
					getTableNameFromStatement(queryString) match {
						case tableName => {
							try {
								var dropStatement = connection.createStatement()
								var tableNamenormalized = tableName
								if (tableName.equals("")) tableNamenormalized = file.getName.replace(".sql", "")
								dropStatement.execute("DROP TABLE " + name + "." + tableNamenormalized)
								dropStatement.close()
							} catch {
								case e : SqlSyntaxErrorException => println(e.getMessage)
							}
						}
					}
				} catch {
					case e : SqlSyntaxErrorException => println(e.getMessage +  System.lineSeparator() + query)
				}
			} catch {
				case e : SqlSyntaxErrorException => println(e.getMessage)
				case e : FileNotFoundException =>  println(e.getMessage)
			}
		}
	}

	def createTables(name : String): Unit = {
		try {
			val createStatement = connection.createStatement()
			val createResultSet = createStatement.execute("CREATE SCHEMA " + name)
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			val createStatement = connection.createStatement()
			createStatement.execute("CREATE TABLE "+name+".patterns (id INT, name VARCHAR(200), pattern CLOB, occurences INT, diameter FLOAT, nodedegreedistribution CLOB)")
			createStatement.close()
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			val createStatement = connection.createStatement()
			createStatement.execute("CREATE TABLE "+name+".coloredpatterns (id INT, pattern CLOB)")
			createStatement.close()
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			val createStatement = connection.createStatement()
			val createResultSet = createStatement.execute("CREATE TABLE "+name+".graphstatistics (nodedegreedistribution CLOB, averagelinks FLOAT, edges INT, connectedcomponents INT, stronglyconnectedcomponents INT, gcnodes INT, gcedges INT, highestIndegrees CLOB, highestOutdegrees CLOB)")
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		try {
			var createStatement = connection.createStatement()
			var createResultSet = createStatement.execute("CREATE TABLE "+name+".CLUSTERS "+
				"(                                                                   "+
				"       ID INT NOT NULL GENERATED ALWAYS AS IDENTITY(START WITH 1 INCREMENT BY 1),    "+
				"USERNAME VARCHAR(50) DEFAULT 'default' NOT NULL,       "+
				//"SESSION_ID INT NOT NULL,                    "+
				//"SESSION_LOCAL_ID INT,                  "+
				"LABEL VARCHAR(255),                      "+
				//"CHILD_SESSION INT,                         "+
				//"AVG_ERROR FLOAT(53),                         "+
				"CLUSTER_SIZE INT,                              "+
				//"PARTITIONNAME VARCHAR(255) DEFAULT 'MAINTABLE', "+
				"PRIMARY KEY (ID, USERNAME)                                "+
				")")
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}

		val sqlDir = new File("prolod-preprocessing/sql/")
		for (file <- sqlDir.listFiles) {
			try {
				val queryString = Source.fromFile(file.getPath).mkString
				val query = String.format(queryString, name)
				try {
					val statement = connection.prepareStatement(query)
					statement.execute
					statement.close()
				} catch {
					case e : SqlSyntaxErrorException => println(e.getMessage +  System.lineSeparator() + query)
				}
			} catch {
				case e : SqlSyntaxErrorException => println(e.getMessage)
				case e : FileNotFoundException =>  println(e.getMessage)
			}
		}
	}

	def createIndices(name: String) = {
		val sqlDir = new File("prolod-preprocessing/sql/indices/")
		for (file <- sqlDir.listFiles) {
			try {
				val queryString = Source.fromFile(file.getPath).mkString
				val r = "/\\*[\\s\\S]*?\\*/|--[^\\r\\n]*|;"
				val queries = queryString.split(r)
				for (queryString <- queries) {
					val query = String.format(queryString, name)
					try {
						val statement = connection.prepareStatement(query)
						statement.execute
						statement.close()
					} catch {
						case e : SqlSyntaxErrorException => println(e.getMessage +  System.lineSeparator() + query)
					}
				}
			} catch {
				case e : SqlSyntaxErrorException => println(e.getMessage)
				case e : FileNotFoundException =>  println(e.getMessage)
			}
		}

	}

	def getTableNameFromStatement(s: String) : String = {
		val pattern = """%s.(.*)""".r

		s match {
			case pattern(group) => {
				println(group)
				group
			}
			case _ => {
				println("")
				""
			}
		}
		/*
		var result = pattern.findFirstIn(s).gro//for (m <- pattern findFirstIn s) yield m
		println(s + result)
		result                */
	}

	def getClusters(s: String, ontologyNamespace : String): Seq[Group] = {
		val table = s
		val sql = sql"SELECT label, cluster_size FROM #${table}.CLUSTERS WHERE username = 'ontology' ORDER BY label".as[(String, Int)]
		var id : Int = -1
		try {
			val result = execute(sql)
			result map tupled((label, cluster_size) => {
				id += 1
				new Group(id, removeOntologyNamespace(label, ontologyNamespace), cluster_size)
			})
		} catch {
			case e : SqlSyntaxErrorException => println("This dataset has no clusters: " + s)
			Nil
		}
	}


	private def removeOntologyNamespace(name: String, ontologyNamespace: String): String = {
		var result = name
		if (ontologyNamespace != null) {
			if (ontologyNamespace.startsWith("\"group\":\"")) {
				result = result.replace(ontologyNamespace, "\"group\":\"")
			} else {
				result = result.replace(ontologyNamespace, "")
			}
		}
		result
	}

	def getDatasetEntities(name : String) : Int = {
		val sql = sql"SELECT entities FROM PROLOD_MAIN.SCHEMATA WHERE id = ${name}".as[Int]
		execute(sql).headOption.getOrElse(-1)
	}

	def getDatasets(): Seq[Dataset] = {
		var datasets: List[Dataset] = Nil

		val sql = sql"SELECT id, schema_name, entities, ontology_namespace FROM PROLOD_MAIN.SCHEMATA ORDER BY LOWER(schema_name)".as[(String, String, Int, String)]

		val result = execute(sql) map tupled((id, schema, entities, ontology_namespace) => {
			new Dataset(id, schema, entities, getClusters(id, ontology_namespace))
		})
		result.filter(_.size > 0)
	}


	def getStatistics(dataset: String) : mutable.Map[String, String] = {
		val statistics = mutable.Map[String, String]()
		val sql = sql"SELECT nodedegreedistribution, averagelinks, edges, connectedcomponents, stronglyconnectedcomponents FROM #$dataset.graphstatistics".as[(String, Float, Int, Int, Int)]
		try {
			val result = execute(sql) map tupled((nodedegreedistribution, averagelinks, edges, connectedcomponents, stronglyconnectedcomponents) => {
				statistics += ("nodedegreedistribution" -> nodedegreedistribution)
				statistics += ("averagelinks" -> averagelinks.toString)
				statistics += ("edges" -> edges.toString)
				statistics += ("connectedcomponents" -> connectedcomponents.toString)
				statistics += ("stronglyconnectedcomponents" -> stronglyconnectedcomponents.toString)
			})
			val sql2 = sql"SELECT gcnodes, highestIndegrees, highestOutdegrees FROM #$dataset.graphstatistics".as[(Int, String, String)]
			val result2 = execute(sql2) map tupled((gcnodes, highestIndegrees, highestOutdegrees) => {
				statistics += ("gcnodes" -> gcnodes.toString)
				statistics += ("highestIndegrees" -> highestIndegrees)
				statistics += ("highestOutdegrees" -> highestOutdegrees)
			})
			val sql3 = sql"SELECT gcedges FROM #$dataset.graphstatistics".as[Int]
			val result3 = execute(sql3) map ((gcedges) => {
				statistics += ("gcedges" -> gcedges.toString)
			})
		} catch {
			case e: SqlSyntaxErrorException => {
				val sql2 = sql"SELECT gcnodes FROM #$dataset.graphstatistics".as[(Int)]
				val result2 = execute(sql2) map ((gcnodes) => {
					statistics += ("gcnodes" -> gcnodes.toString)
				})
				println(e.getMessage + System.lineSeparator() + sql.toString)
			}
		}
		statistics
	}

	def getColoredPatterns(dataset: String, id: Int): List[Pattern] = {
		var patterns : List[Pattern] = Nil
		val sql = sql"SELECT ontology_namespace FROM PROLOD_MAIN.SCHEMATA WHERE ID = ${dataset}".as[String]
		val namespaces: Vector[String] = execute(sql) map (ontology_namespace => {
				"\"group\":\"" + ontology_namespace.replace("/", "\\/")
		})
		try {
			val statement = connection.createStatement()
			val resultSet = statement.executeQuery("SELECT pattern FROM "+ dataset+".COLOREDPATTERNS WHERE id = "+id)
			while ( resultSet.next() ) {
				var pattern = resultSet.getString("pattern")
				namespaces foreach (ontology_namespace => {
					 pattern = removeOntologyNamespace(pattern, ontology_namespace)
				})
				// val occurences = resultSet.getInt("occurences")

				val patternJsonT = Json.parse(pattern).validate[PatternFromDB]
				val patternsV = List(patternJsonT).filter(p => p.isSuccess).map(p => p.get)
				val errors = List(patternJsonT).filter(p => p.isError)
				if (errors.nonEmpty) {
					println("Could not validate " + errors)
				}
				val patternJson = Json.parse(pattern).validate[PatternFromDB].get
				patterns :::= List(new Pattern(id, "", -1, patternJson.nodes, patternJson.links)) // new Pattern(id, "", occurences, Nil, Nil)
			}
		} catch {
			case e : SqlSyntaxErrorException => println("This dataset has no patterns: " + dataset)
		}
		patterns
	}

	def getPatterns(s: String): List[Pattern] = {
		var patterns : List[Pattern] = Nil
		try {
			val statement = connection.createStatement()
			val resultSet = statement.executeQuery("SELECT id, name, pattern, occurences FROM "+ s+".PATTERNS ORDER BY occurences ASC")
			while ( resultSet.next() ) {
				val id = resultSet.getInt("id")
				val pattern = resultSet.getString("pattern")
				val occurences = resultSet.getInt("occurences")
				val name = resultSet.getString("name")
				//val diameter = resultSet.getDouble("diameter")
				val patternJson = Json.parse(pattern).validate[PatternFromDB].get
				patterns :::= List(new Pattern(id, name, occurences, patternJson.nodes, patternJson.links)) // new Pattern(id, "", occurences, Nil, Nil)
			}
		} catch {
			case e : SqlSyntaxErrorException => println("This dataset has no patterns: " + s)
		}
		patterns
	}

	def getPatternDiameter(dataset: String, patternId: Int) : Int = {
		var diameter : Int = 0
		try {
			val sql = sql"SELECT diameter FROM #${dataset}.patterns WHERE id = #${patternId}".as[(Int)]
			val result = execute(sql)
			result map ((diameterSql) => {
				diameter = diameterSql
			})
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}
		diameter
	}

	def getEntityDetails(dataset: String, subjectId: Int): Entity = {
		var triples : List[Triple] = Nil
		var label : String = ""
		var subjectUri = ""
		var predicateUri = ""
		var objectUri = ""
		try {
			val statement = connection.createStatement()
			val resultSet = statement.executeQuery("SELECT tuple_id, predicate_id FROM "+ dataset+".maintable WHERE subject_id = "+subjectId)
			while ( resultSet.next() ) {
				val objectId = resultSet.getString("tuple_id")
				val predicateId = resultSet.getString("predicate_id")

				val statement1 = connection.createStatement()
				val resultSet1 = statement1.executeQuery("SELECT subject FROM "+ dataset+".subjecttable WHERE id = "+subjectId)
				while ( resultSet1.next() ) {
					subjectUri = resultSet1.getString("subject")
				}
				statement1.close()

				val statement2 = connection.createStatement()
				val resultSet2 = statement2.executeQuery("SELECT predicate FROM "+ dataset+".predicatetable WHERE id = "+predicateId)
				while ( resultSet2.next() ) {
					predicateUri = resultSet2.getString("predicate")
				}
				statement2.close()

				val statement3 = connection.createStatement()
				val resultSet3 = statement3.executeQuery("SELECT object FROM "+ dataset+".objecttable WHERE tuple_id = "+objectId)
				while ( resultSet3.next() ) {
					objectUri = resultSet3.getString("object")
					if (predicateUri.equals("http://www.w3.org/2000/01/rdf-schema#label")) {
						label = objectUri
					}
				}
				statement3.close()

				triples :::= List(new Triple(subjectUri, predicateUri, objectUri))
			}
			statement.close
		} catch {
			case e : SqlSyntaxErrorException => println(e.getMessage)
		}

		val entityDetails = new Entity(subjectId, subjectUri, label, triples)
		entityDetails
	}

	def insert: DBIO[Unit] = DBIO.seq(
		// sqlu"INSERT INTO PROLOD_MAIN.SCHEMATA ('ID', 'SCHEMA_NAME', 'TUPLES', 'ENTITIES') VALUES ('caterpillar','caterpillar',20,3)"
	)

	def replaceDatasetName(query: String, name: String) = {
		query.replace("##dataset##", name)
	}

	def insertDataset(name : String, tuples: Int, entities: Int, ontologyNamespace : String, namespace : String) {
		try {
			val statement = connection.createStatement()
			val resultSet = statement.execute("INSERT INTO PROLOD_MAIN.SCHEMATA (ID, SCHEMA_NAME, TUPLES, ENTITIES, ONTOLOGY_NAMESPACE, NAMESPACE) VALUES ('"+name+"','"+name+"',"+tuples+","+entities+",'"+ontologyNamespace+"','"+namespace+"')")
		} catch {
			case e : SqlIntegrityConstraintViolationException => println("Dataset already exists")
		}
	}

	def insertPatterns(name: String, patterns: util.HashMap[Integer, util.HashMap[String, Integer]], coloredPatterns: util.HashMap[Integer, util.List[String]], diameter: util.HashMap[Integer, lang.Double]) {
		val coloredPatternsMap = coloredPatterns.asScala.toMap
		val diameterMap = diameter.asScala.toMap
		val patternsMap = patterns.asScala.toMap
		patternsMap.foreach {
			case (id, patternHashMap) => {
				val patternHashMapScala = patternHashMap.asScala.toMap
				patternHashMapScala.foreach {
					case (pattern, occurences) => {
						val patternDiameter = diameterMap.get(id).get
						try {
							val statement = connection.createStatement()
							val patternJson : PatternFromDB = Json.parse(pattern).validate[PatternFromDB].get
							val patternName = patternJson.name.getOrElse("")
							val resultSet = statement.execute("INSERT INTO " + name + ".PATTERNS (ID, NAME, PATTERN, OCCURENCES, DIAMETER) VALUES (" + id + ", '" + patternName + "',  '" + pattern + "'," + occurences + "," + patternDiameter + ")")
						} catch {
							case e: SqlIntegrityConstraintViolationException => println("Pattern already exists")
							case e: SqlException => println(e.getMessage)
							case e: SqlSyntaxErrorException => println(e.getMessage + System.lineSeparator() + "INSERT INTO " + name + ".PATTERNS (ID, PATTERN, OCCURENCES, DIAMETER) VALUES (" + id + ", '" + pattern + "'," + occurences + ", " + patternDiameter + ")")
						}
						val cPattern = coloredPatternsMap.get(id).get.asScala.toList
						cPattern.foreach { case (coloredpattern) =>
							try {
								val statement = connection.createStatement()
								val resultSet = statement.execute("INSERT INTO " + name + ".coloredpatterns (ID, PATTERN) VALUES (" + id + ", '" + coloredpattern + "')")
							} catch {
								case e: SqlException => {
									println(e.getMessage)
									println(coloredpattern)
								}
								case e: SqlSyntaxErrorException => println(e.getMessage + System.lineSeparator() + "INSERT INTO " + name + ".coloredpatterns (ID, PATTERN) VALUES (" + id + ", '" + coloredpattern + "')")
							}
						}
					}
				}
			}
		}
	}

	class RsIterator(rs: ResultSet) extends Iterator[ResultSet] {
		def hasNext: Boolean = rs.next()
		def next(): ResultSet = rs
	}

	def performInsert(table: String, names: Seq[Any], values: Seq[Any]): Option[Int] = {
		val query = String.format("insert into %s (%s) values (%s)",
			table,
			commaize(names.map(n => n.toString).toList),
			commaize(values.map(v => "'" + v.toString.replace("'", "") + "'").toList)
		)
		try {
			val statement = connection.prepareStatement(query)
			statement.execute
			val key = statement.getGeneratedKeys
			statement.close()
			if(key.next) {
				key.getInt(1)
			}
		} catch {
			case e : SqlIntegrityConstraintViolationException => println(e.getMessage +  System.lineSeparator() + query)
			case e : SqlSyntaxErrorException => println(e.getMessage +  System.lineSeparator() + query)
			case e : SqlDataException =>  println(e.getMessage +  System.lineSeparator() + query)
		}
		None
	}

	private def commaize(list: List[_ <: Any]): String = list match {
		case List()  => ""
		case List(x) => x.toString
		case _       => list.head + ", " + commaize(list.tail)
	}

	def insertSubject(name: String, s: String): Int = {
		performInsert(name + ".subjecttable", List("subject"), List(s)) match {
			case Some(i) => i
			case None => getSubjectId(name, s)
		}
	}

	def getSubjectId(dataset: String, s: String): Int = {
		var result : Int = -1
		val statement = connection.createStatement()
		try {
			val resultSet = statement.executeQuery("SELECT id FROM " + dataset + ".subjecttable WHERE subject='" + s + "'")
			resultSet.next()
			result = resultSet.getInt("id")
		} catch {
			case e : SqlException => println(e.getMessage)
			case e: SqlSyntaxErrorException  => println(e.getMessage)
		}
		statement.close()
		result
	}

	def insertObject(name: String, s: String): Int = {
		performInsert(name + ".objecttable", List("object"), List(s)) match {
			case Some(i) => i
			case None => {
				try {
					getObjectId(name, s)
				} catch {
					case e: SqlSyntaxErrorException => {
						println(e.getMessage + System.lineSeparator() + s)
						-1
					}
				}
			}
		}
	}

	def getObjectId(name: String, s: String): Int = {
		var result : Int = -1
		val statement = connection.createStatement()
		val resultSet = statement.executeQuery("SELECT tuple_id FROM " + name + ".objecttable WHERE object='" + s.replace("'", "") + "'")
		resultSet.next()
		result = resultSet.getInt("tuple_id")
		statement.close()
		result
	}

	def getOntologyNamespace(s: String): String = {
		var namespace :String = null
		try {
			val sql = sql"""SELECT ONTOLOGY_NAMESPACE FROM PROLOD_MAIN.SCHEMATA WHERE SCHEMA_NAME = '#${s}'""".as[String]
			val result = execute(sql)
			result map ((ns) => {
				namespace = ns
			})
		} catch {
			case e: SqlException => println(e.getMessage + System.lineSeparator())
			case e: SqlSyntaxErrorException   => println(e.getMessage + System.lineSeparator())
		}
		namespace
	}

	def getNamespace(s: String): String = {
		var namespace :String = null
		try {
			val sql = sql"""SELECT NAMESPACE FROM PROLOD_MAIN.SCHEMATA WHERE SCHEMA_NAME = '#${s}'""".as[String]
			val result = execute(sql)
			result map ((ns) => {
				namespace = ns
			})
		} catch {
			case e: SqlException => println(e.getMessage + System.lineSeparator())
			case e: SqlSyntaxErrorException   => println(e.getMessage + System.lineSeparator())
		}
		namespace
	}

	def insertPredicate(name: String, s: String): Int = {
		performInsert(name + ".predicatetable", List("predicate"), List(s)) match {
			case Some(i) => i
			case None => getPredicateId(name, s)
		}
	}

	def getPredicateId(name: String, s: String): Int = {
		var result : Int = -1
		val statement = connection.createStatement()
		val resultSet = statement.executeQuery("SELECT id FROM " + name + ".predicatetable WHERE predicate='" + s + "'")
		resultSet.next()
		result = resultSet.getInt("id")
		statement.close()
		result
	}

	def insertTriples(name: String, s: Int, p: Int, o: Int): Unit = {
		performInsert(name + ".maintable", List("subject_id", "predicate_id", "tuple_id"), List(s, p, o))
		/*
		try {
			val statement = connection.createStatement()
			val resultSet = statement.execute("INSERT INTO " + name + ".subjecttable (subject) VALUES ('" + s + "')")
			println(resultSet)
		} catch {
			case e: SqlIntegrityConstraintViolationException => println(e.getMessage)
			case e: SqlException => println(e.getMessage)
		}
		*/
	}

	def insertStatistics(name: String, nodes: String, links: Double, edges: Int, gcEdges : Int, gcNodes : Int, connectedcomponents : Int, stronglyconnectedcomponents : Int, highestIndegrees: String, highestOutdegrees: String) = {
		try {
			val statement = connection.createStatement()
			val resultSet = statement.execute("INSERT INTO " + name + ".graphstatistics (nodedegreedistribution, averagelinks, edges, gcnodes, gcedges, connectedcomponents, stronglyconnectedcomponents, highestIndegrees, highestOutdegrees) VALUES ('" + nodes + "'," + links + ", " + edges + ", " + gcNodes + ", " + gcEdges + ", " + connectedcomponents + ", " + stronglyconnectedcomponents + ",'" + highestIndegrees + "','" + highestOutdegrees + "')")
		} catch {
			case e: SqlIntegrityConstraintViolationException => println(e.getMessage)
			case e: SqlException => println(e.getMessage)
		}
	}

	def insertClasses(name: String, clusters: util.List[String]) = {
		val clusterUris = clusters.asScala.toList
		clusterUris.foreach {
			case (cluster) =>
				try {
					val statement = connection.createStatement()
					val resultSet = statement.execute("INSERT INTO " + name + ".clusters (label, cluster_size, username) VALUES ('" + cluster + "', 0 , 'ontology')")
				} catch {
					case e: SqlIntegrityConstraintViolationException => println(e.getMessage + System.lineSeparator() + "INSERT INTO " + name + ".clusters (label, cluster_size, username) VALUES ('" + cluster + "', 0 , 'ontology')")
					case e: SqlException => println(e.getMessage + System.lineSeparator() + "INSERT INTO " + name + ".clusters (label, cluster_size, username) VALUES ('" + cluster + "', 0 , 'ontology')")
					case e: SqlSyntaxErrorException   => println(e.getMessage + System.lineSeparator() + "INSERT INTO " + name + ".clusters (label, cluster_size, username) VALUES ('" + cluster + "', 0 , 'ontology')")
				}
		}

	}

	def updateClusterSizes(dataset : String, ontologyNamespace : String) = {
		for (cluster : Group <- getClusters(dataset, ontologyNamespace)) {
			try {
				val sql = sql"""SELECT COUNT(*) FROM #${dataset}.MAINTABLE as m, #${dataset}.predicatetable as p, #${dataset}.objecttable as o WHERE m.predicate_id = p.id  AND o.tuple_id = m.tuple_id  AND p.predicate = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' AND o.object = '#${ontologyNamespace}#${cluster.name}'""".as[(Int)]
				val result = execute(sql)
				var clusterSize = 0
				result map ((cluster_size) => {
					clusterSize = cluster_size
				})

				val statement = connection.createStatement()
				val resultSet = statement.execute("UPDATE " + dataset + ".clusters SET cluster_size = " + clusterSize + " WHERE label = '" + ontologyNamespace + cluster.name + "'")
			} catch {
				case e: SqlIntegrityConstraintViolationException => println(e.getMessage)
				case e: SqlException => println(e.getMessage + System.lineSeparator())
				case e: SqlSyntaxErrorException   => println(e.getMessage + System.lineSeparator())
			}
		}
	}
}
