package de.thm.ii.fbs.controller

import de.thm.ii.fbs.controller.exception.{BadRequestException, ConflictException, ForbiddenException, ResourceNotFoundException}
import de.thm.ii.fbs.model.{CourseRole, GlobalRole, SubTaskResult, Submission}
import de.thm.ii.fbs.services.checker.CheckerServiceFactoryService
import de.thm.ii.fbs.services.persistence._
import de.thm.ii.fbs.services.security.AuthService
import org.springframework.beans.factory.annotation.Autowired
import org.springframework.web.bind.annotation._
import org.springframework.web.multipart.MultipartFile

import java.time.Instant
import javax.servlet.http.{HttpServletRequest, HttpServletResponse}

/**
  * Submission controller implement routes for submitting task and receive results
  */
@RestController
@CrossOrigin
@RequestMapping(path = Array("/api/v1/users"))
class SubmissionController {
  @Autowired
  private val authService: AuthService = null
  @Autowired
  private val storageService: StorageService = null
  @Autowired
  private val submissionService: SubmissionService = null
  @Autowired
  private val taskService: TaskService = null
  @Autowired
  private val checkerConfigurationService: CheckrunnerConfigurationService = null
  @Autowired
  private val checkerServiceFactoryService: CheckerServiceFactoryService = null
  @Autowired
  private val courseRegistrationService: CourseRegistrationService = null
  @Autowired
  private val checkrunnerSubTaskServer: CheckrunnerSubTaskService = null
  @Autowired
  private val courseRegistration: CourseRegistrationService = null

  /**
    * Get a list of all submissions for a task
    *
    * @param uid User id
    * @param cid Course id
    * @param tid Task id
    * @param req Http request
    * @param res Http response
    * @return Submission results
    */
  @GetMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/submissions"))
  @ResponseBody
  def getAll(@PathVariable("uid") uid: Int, @PathVariable("cid") cid: Int, @PathVariable("tid") tid: Int,
             req: HttpServletRequest, res: HttpServletResponse): List[Submission] = {
    val user = authService.authorize(req, res)
    val task = taskService.getOne(tid).get

    val adminPrivileged = (user.hasRole(GlobalRole.ADMIN, GlobalRole.MODERATOR)
      || List(CourseRole.DOCENT, CourseRole.TUTOR).contains(courseRegistrationService.getCoursePrivileges(user.id).getOrElse(cid, CourseRole.STUDENT)))
    val privileged = (user.id == uid && !task.isPrivate) || adminPrivileged

    if (privileged) {
      submissionService.getAll(uid, cid, tid, adminPrivileged || task.mediaType == "application/x-spreadsheet")
        .map(submission => submissionService.getOrHidden(submission, task.hideResult, adminPrivileged))
    } else {
      throw new ForbiddenException()
    }
  }

  /**
    * Get a list of all submissions for a task
    *
    * @param uid User id
    * @param cid Course id
    * @param tid Task id
    * @param sid Submission id
    * @param req Http request
    * @param res Http response
    * @return Submission results
    */
  @GetMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/submissions/{sid}/subresults"))
  @ResponseBody
  def getSubresults(@PathVariable("uid") uid: Int, @PathVariable("cid") cid: Int, @PathVariable("tid") tid: Int,
                    @PathVariable("sid") sid: Int, req: HttpServletRequest, res: HttpServletResponse): List[SubTaskResult] = {
    val user = authService.authorize(req, res)
    val task = taskService.getOne(tid).get

    val adminPrivileged = (user.hasRole(GlobalRole.ADMIN, GlobalRole.MODERATOR)
      || List(CourseRole.DOCENT, CourseRole.TUTOR).contains(courseRegistrationService.getCoursePrivileges(user.id).getOrElse(cid, CourseRole.STUDENT)))
    val privileged = (user.id == uid && !task.isPrivate) || adminPrivileged

    if (!privileged) {
      throw new ForbiddenException()
    }

    if (task.hideResult && !adminPrivileged) {
      List()
    } else {
      checkerConfigurationService.getAll(cid, tid).headOption match {
        case Some(cc) => checkrunnerSubTaskServer.listResultsWithTasks(uid, cc.id, sid)
        case None => throw new ResourceNotFoundException()
      }
    }
  }

  /**
    * Submit a file for a task
    *
    * @param uid  User id
    * @param cid  Course id
    * @param tid  Task id
    * @param file Mutipart file
    * @param req  Http request
    * @param res  Http response
    * @return Submission information
    */
  @PostMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/submissions"))
  @ResponseBody
  def submit(@PathVariable("uid") uid: Int, @PathVariable("cid") cid: Int, @PathVariable("tid") tid: Int,
             @RequestParam file: MultipartFile,
             req: HttpServletRequest, res: HttpServletResponse): Submission = {
    val user = authService.authorize(req, res)
    val someCourseRole = courseRegistration.getCourseRoleOfUser(cid, user.id)
    val noPrivateAccess = someCourseRole.contains(CourseRole.STUDENT) && user.globalRole != GlobalRole.ADMIN

    if (user.id == uid) {
      this.taskService.getOne(tid) match {
        case Some(task) =>
          // Not allow Students to Submit to Private Tasks
          if (noPrivateAccess && task.isPrivate) {
            throw new ForbiddenException()
          }

          val expectedMediaType = task.mediaType
          val currentMediaType = req.getContentType // Transform to media type (Content Type != Media Type)
          if (task.deadline.isDefined && Instant.now().isAfter(Instant.parse(task.deadline.get))) {
            throw new BadRequestException("Deadline Before Now")
          }
          if (true) { // TODO: Check media type compatibility
            val submission = submissionService.create(uid, tid)
            storageService.storeSolutionFileInBucket(submission.id, file)
            checkerConfigurationService.getAll(cid, tid).foreach(cc => {
              val checkerService = checkerServiceFactoryService(cc.checkerType)
              checkerService.notify(tid, submission.id, cc, user)
            })
            submission

          } else {
            throw new BadRequestException("Unsupported Media Type")
          }
        case _ => throw new ResourceNotFoundException()
      }
    } else {
      throw new ForbiddenException()
    }
  }

  /**
    * Restart the submission process
    *
    * @param uid User id
    * @param cid Course id
    * @param tid Task id
    * @param sid Task id
    * @param req Http request
    * @param res Http response
    * @return
    */
  @PutMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/submissions/{sid}"))
  def resubmit(@PathVariable("uid") uid: Int, @PathVariable("cid") cid: Int, @PathVariable("tid") tid: Int, @PathVariable("sid") sid: Int,
               req: HttpServletRequest, res: HttpServletResponse): Unit = {
    val user = authService.authorize(req, res)
    val task = taskService.getOne(tid).get
    val someCourseRole = courseRegistration.getCourseRoleOfUser(cid, user.id)
    val noPrivateAccess = someCourseRole.contains(CourseRole.STUDENT) && user.globalRole != GlobalRole.ADMIN

    val allowed = user.id == uid && !(noPrivateAccess && task.isPrivate)
    if (allowed) {
      submissionService.getOne(sid, uid) match {
        case Some(submission) =>
          if (!submission.isInBlockStorage) {
            throw new ConflictException("resubmit is not supported for this submission")
          }

          submissionService.clearResults(sid, uid)
          checkerConfigurationService.getAll(cid, tid).foreach(cc => {
            val checkerService = checkerServiceFactoryService(cc.checkerType)
            checkerService.notify(tid, sid, cc, user)
          })
        case None => throw new ResourceNotFoundException()
      }
    } else {
      throw new ForbiddenException()
    }
  }

  /**
    * Restart the submission process for all submissions of task
    *
    * @param cid Course id
    * @param tid Task id
    * @param req Http request
    * @param res Http response
    * @return
    */
  @PostMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/resubmitAll"))
  def resubmitAll(@PathVariable("uid") uid: Int, @PathVariable("cid") cid: Int, @PathVariable("tid") tid: Int,
                  req: HttpServletRequest, res: HttpServletResponse): Unit = {
    val user = authService.authorize(req, res)

    val adminPrivileged = (user.hasRole(GlobalRole.ADMIN, GlobalRole.MODERATOR)
      || List(CourseRole.DOCENT, CourseRole.TUTOR).contains(courseRegistrationService.getCoursePrivileges(user.id).getOrElse(cid, CourseRole.STUDENT)))

    if (adminPrivileged) {
      submissionService.getAllByTask(cid, tid).filter(s => s.isInBlockStorage).foreach(submission => {
        submissionService.clearResults(submission.id, submission.userID.get)
        checkerConfigurationService.getAll(cid, tid).foreach(cc => {
          val checkerService = checkerServiceFactoryService(cc.checkerType)
          checkerService.notify(tid, submission.id, cc, user)
        })
      })
    } else {
      throw new ForbiddenException()
    }
  }

  /**
    * Get the status of submission
    *
    * @param uid User id
    * @param cid Course id
    * @param tid Task id
    * @param sid Task id
    * @param req Http request
    * @param res Http response
    * @return A submission state
    */
  @GetMapping(value = Array("/{uid}/courses/{cid}/tasks/{tid}/submissions/{sid}"))
  @ResponseBody
  def getOne(@PathVariable uid: Int, @PathVariable cid: Int, @PathVariable tid: Int, @PathVariable sid: Int,
             req: HttpServletRequest, res: HttpServletResponse): Submission = {
    val user = authService.authorize(req, res)
    val task = taskService.getOne(tid).get

    val adminPrivileged = (user.hasRole(GlobalRole.ADMIN, GlobalRole.MODERATOR)
      || List(CourseRole.DOCENT, CourseRole.TUTOR).contains(courseRegistrationService.getCoursePrivileges(user.id).getOrElse(cid, CourseRole.STUDENT)))
    val privileged = (user.id == uid && !task.isPrivate) || adminPrivileged

    if (privileged) {
      submissionService.getOne(sid, uid, adminPrivileged) match {
        case Some(submission) =>
          submissionService.getOrHidden(submission, task.hideResult, adminPrivileged)
        case None => throw new ResourceNotFoundException()
      }
    } else {
      throw new ForbiddenException()
    }
  }
}
