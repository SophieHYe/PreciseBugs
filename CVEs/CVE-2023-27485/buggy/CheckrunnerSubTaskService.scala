package de.thm.ii.fbs.services.persistence

import de.thm.ii.fbs.model.{CheckrunnerSubTask, CheckrunnerSubTaskResult, SubTaskResult}
import de.thm.ii.fbs.util.DB
import org.springframework.beans.factory.annotation.Autowired
import org.springframework.jdbc.core.JdbcTemplate
import org.springframework.stereotype.Component

import java.math.BigInteger
import java.sql.{ResultSet, SQLException}

/**
  * CheckrunnerSubTaskService handles persistance for Checkrunner
  */
@Component
class CheckrunnerSubTaskService {
  @Autowired
  private implicit val jdbc: JdbcTemplate = null

  /**
    * Get all CheckrunnerSubTasks for a configuration
    * @param configurationId the configuration id for which to get the checkrunners
    * @return List of subtasks
    */
  def getAll(configurationId: Int): List[CheckrunnerSubTask] = DB.query(
    "SELECT configuration_id, sub_task_id, points, name FROM checkrunner_sub_task WHERE configuration_id = ?",
    (res, _) => parseSubTaskResult(res),
    configurationId
  )

  /**
    * Get subtask by name
    * @param configurationId the configuration id for which to get the subtasks
    * @param name the name of the subtasks
    * @return Option of subtasks
    */
  def get(configurationId: Int, name: String): Option[CheckrunnerSubTask] = DB.query(
    "SELECT configuration_id, sub_task_id, name, points FROM checkrunner_sub_task WHERE configuration_id = ? AND name = ?",
    (res, _) => parseSubTaskResult(res),
    configurationId, name
  ).headOption

  /**
    * Create a new subtask
    * @param configurationId the configuration id for which to get the subtasks
    * @param name the name of the course
    * @param points the max points
    * @return The new Checkrunner
    */
  def create(configurationId: Int, name: String, points: Int): CheckrunnerSubTask = DB.insert(
    "INSERT INTO checkrunner_sub_task (configuration_id, name, points) VALUES (?, ?, ?)",
    configurationId, name, points
  ).map(gk => gk(0).asInstanceOf[BigInteger].intValue())
    .flatMap(_ => get(configurationId, name)) match {
    case Some(course) => course
    case None => throw new SQLException("Course could not be created")
  }

  /**
    * Get all results for a subission
    * @param configurationId The configuration id for which to get the results
    * @param submissionId The id of the submission to get the subtasks results
    * @return List of subtasks
    */
  def listResults(configurationId: Int, submissionId: Int): List[CheckrunnerSubTaskResult] = DB.query(
    "SELECT configuration_id, sub_task_id, submission_id, points FROM checkrunner_sub_task_result WHERE configuration_id = ? AND submission_id = ?",
    (res, _) => parseSubTaskResultResult(res),
    configurationId, submissionId
  )

  /**
    * Get subtask by name
    * @param configurationId the configuration id for which to get the result
    * @param subTaskId the id of the sub task to get result
    * @param submissionId the submission id of the sub task to get result
    * @return List of subTaskResults
    */
  def getResult(configurationId: Int, subTaskId: Int, submissionId: Int): Option[CheckrunnerSubTaskResult] = DB.query(
    "SELECT configuration_id, sub_task_id, submission_id, points FROM checkrunner_sub_task_result WHERE " +
      "configuration_id = ? AND sub_task_id = ? AND submission_id = ?",
    (res, _) => parseSubTaskResultResult(res),
    configurationId, subTaskId, submissionId
  ).headOption

  /**
    * Create a new submission result
    * @param configurationId the configuration id
    * @param subTaskId the id of the subTask
    * @param submissionId the id of the submission
    * @param points the max points
    * @return The new Checkrunner
    */
  def createResult(configurationId: Int, subTaskId: Int, submissionId: Int, points: Int): Unit = DB.insert(
    "INSERT INTO checkrunner_sub_task_result (configuration_id, sub_task_id, submission_id, points) VALUES (?, ?, ?, ?)",
    configurationId, subTaskId, submissionId, points
  )

  /**
    * Gets or creates a subtask
    * @param configurationId the configuration id for which to get the checkrunners
    * @param name the name of the course
    * @param maxPoints the max points
    * @return The new Checkrunner
    */
  def getOrCrate(configurationId: Int, name: String, maxPoints: Int): CheckrunnerSubTask = get(configurationId, name)
    .getOrElse(create(configurationId, name, maxPoints))

  /**
    * Get a list of all subtask results with task information
    * @param configurationId The configuration id for which to get the results
    * @param submissionId The id of the submission to get the subtasks results
    * @return List of subtasks results with tasks
    */
  def listResultsWithTasks(configurationId: Int, submissionId: Int): List[SubTaskResult] = DB.query(
    "SELECT st.name, st.points AS max_points, str.points FROM checkrunner_sub_task_result str JOIN " +
      "checkrunner_sub_task st ON str.sub_task_id = st.sub_task_id " +
      "WHERE str.configuration_id = ? AND str.submission_id = ?",
    (res, _) => parseSubTaskResultWithSubTask(res),
    configurationId, submissionId
  )

  /**
    * Parse SQL Query sub task results
    * @param res SQL Query result
    * @return CheckrunnerSubTask
    */
  private def parseSubTaskResult(res: ResultSet) = CheckrunnerSubTask(
    configurationId = res.getInt("configuration_id"),
    subTaskId = res.getInt("sub_task_id"),
    name = res.getString("name"),
    points = res.getInt("points")
  )

  /**
    * Parse SQL Query sub task results
    * @param res SQL Query result
    * @return CheckrunnerSubTask
    */
  private def parseSubTaskResultResult(res: ResultSet) = CheckrunnerSubTaskResult(
    configurationId = res.getInt("configuration_id"),
    subTaskId = res.getInt("sub_task_id"),
    submissionId = res.getInt("submission_id"),
    points = res.getInt("points")
  )

  /**
    * Parse SQL Query sub task result + sub task result
    * @param res SQL Query result
    * @return SubTaskResult
    */
  private def parseSubTaskResultWithSubTask(res: ResultSet) = SubTaskResult(
    name = res.getString("name"),
    points = res.getInt("points"),
    maxPoints = res.getInt("max_points")
  )
}
