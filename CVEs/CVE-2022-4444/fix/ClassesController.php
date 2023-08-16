<?php

set_time_limit(0);
Yii::import('application.modules.timesheet.models.TimesheetCurricularMatrix', true);
Yii::import('application.modules.timesheet.models.TimesheetInstructor', true);
Yii::import('application.modules.timesheet.models.InstructorSchool', true);
Yii::import('application.modules.timesheet.models.Unavailability', true);

class ClassesController extends Controller
{

    /**
     * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
     * using two-column layout. See 'protected/views/layouts/column2.php'.
     */
    public $layout = 'fullmenu';

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
            'postOnly + delete', // we only allow deletion via POST request
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow', // allow authenticated user to perform 'create' and 'update' actions
                'actions' => array('index',
                    'frequency', 'saveFrequency',
                    'classContents', 'getClassContents', 'saveClassContents', 'saveContent',
                    'getdisciplines', 'getfrequency', 'getcontents'),
                'users' => array('@'),
            ),
            array('allow', // allow admin user to perform 'admin' and 'delete' actions
                'actions' => array('admin', 'delete'),
                'users' => array('admin'),
            ),
            array('deny', // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * Displays the Class Contents
     */
    public function actionClassContents()
    {
        if (Yii::app()->getAuthManager()->checkAccess('instructor', Yii::app()->user->loginInfos->id)) {
            $criteria = new CDbCriteria;
            $criteria->alias = "c";
            $criteria->join = ""
                . " join instructor_teaching_data on instructor_teaching_data.classroom_id_fk = c.id "
                . " join instructor_identification on instructor_teaching_data.instructor_fk = instructor_identification.id ";
            $criteria->condition = "c.school_year = :school_year and c.school_inep_fk = :school_inep_fk and instructor_identification.users_fk = :users_fk";
            $criteria->order = "name";
            $criteria->params = array(':school_year' => Yii::app()->user->year, ':school_inep_fk' => Yii::app()->user->school, ':users_fk' => Yii::app()->user->loginInfos->id);

            $classrooms = Classroom::model()->findAll($criteria);
        } else {
            $classrooms = Classroom::model()->findAll('school_year = :school_year and school_inep_fk = :school_inep_fk order by name', ['school_year' => Yii::app()->user->year, 'school_inep_fk' => Yii::app()->user->school]);
        }
        $this->render('classContents', array(
            'classrooms' => $classrooms
        ));
    }

    /**
     * Get all contents
     */
    public function actionGetContents()
    {
        $contents = ClassResources::model()->findAllByAttributes(['type' => ClassResources::CONTENT]);
        $return = [];
        foreach ($contents as $content) {
            $return[$content->id] = $content->name;
        }
        echo json_encode($return);
    }

    /**
     *   Save a new Content
     */
    public function actionSaveContent()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $name = strtoupper($_POST['name']);
            $description = isset($_POST['description']) ? strtoupper($_POST['description']) : "";
            $type = isset($_POST['type']) ? $_POST['type'] : 1;
            $exist = ClassResources::model()->exists('name = :n', ['n' => $name]);
            if (!$exist) {
                $newContent = new ClassResources();
                $newContent->name = $name;
                $newContent->description = $description;
                $newContent->type = $type;
                $newContent->save();

                $return = ['id' => $newContent->id, 'name' => $newContent->name];
                echo json_encode($return);
            }
        }
    }

    /**
     * Get all classes by classroom, discipline and month
     */
    public function actionGetClassContents()
    {
        if ($_POST["fundamentalMaior"] == "1") {
            $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month and discipline_fk = :discipline_fk and unavailable = 0 order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"], "discipline_fk" => $_POST["discipline"]]);
        } else {
            $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month and unavailable = 0 group by day order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"]]);
        }
        if (!empty($schedules)) {
            foreach ($schedules as $schedule) {
                $classContents[$schedule->day]["available"] = date("Y-m-d") >= Yii::app()->user->year . "-" . str_pad($schedule->month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($schedule->day, 2, "0", STR_PAD_LEFT);
                foreach ($schedule->classHasContents as $classHasContent) {
                    $classContents[$schedule->day]["contents"][$classHasContent->contentFk->id] = $classHasContent->contentFk->description;
                }
            }
            echo json_encode(["valid" => true, "classContents" => $classContents]);
        } else {
            echo json_encode(["valid" => false, "error" => "Não existe quadro de horário com dias letivos para o mês selecionado."]);
        }
    }

    /**
     * Save the contents for each class.
     */
    public function actionSaveClassContents()
    {
        if ($_POST["fundamentalMaior"] == "1") {
            $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month and discipline_fk = :discipline_fk group by day order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"], "discipline_fk" => $_POST["discipline"]]);
        } else {
            $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month group by day order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"]]);
        }
        foreach ($_POST["classContents"] as $classContent) {
            foreach ($schedules as $schedule) {
                if ($schedule->day == $classContent["day"]) {
                    ClassHasContent::model()->deleteAll("schedule_fk = :schedule_fk", ["schedule_fk" => $schedule->id]);
                    foreach ($classContent["contents"] as $content) {
                        $classHasContent = new ClassHasContent();
                        $classHasContent->schedule_fk = $schedule->id;
                        $classHasContent->content_fk = $content;
                        $classHasContent->save();
                    }
                    break;
                }
            }
        }
    }

/**
 * Open the Frequency View.
 */
public
function actionFrequency()
{
    if (Yii::app()->getAuthManager()->checkAccess('instructor', Yii::app()->user->loginInfos->id)) {
        $criteria = new CDbCriteria;
        $criteria->alias = "c";
        $criteria->join = ""
            . " join instructor_teaching_data on instructor_teaching_data.classroom_id_fk = c.id "
            . " join instructor_identification on instructor_teaching_data.instructor_fk = instructor_identification.id ";
        $criteria->condition = "c.school_year = :school_year and c.school_inep_fk = :school_inep_fk and instructor_identification.users_fk = :users_fk";
        $criteria->order = "name";
        $criteria->params = array(':school_year' => Yii::app()->user->year, ':school_inep_fk' => Yii::app()->user->school, ':users_fk' => Yii::app()->user->loginInfos->id);

        $classrooms = Classroom::model()->findAll($criteria);
    } else {
        $classrooms = Classroom::model()->findAll('school_year = :school_year and school_inep_fk = :school_inep_fk order by name', ['school_year' => Yii::app()->user->year, 'school_inep_fk' => Yii::app()->user->school]);
    }
    $this->render('frequency', array(
        'classrooms' => $classrooms
    ));
}

/**
 * Get all frequency by classroom, discipline and month
 */
public
function actionGetFrequency()
{
    if ($_POST["fundamentalMaior"] == "1") {
        $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month and discipline_fk = :discipline_fk and unavailable = 0 order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"], "discipline_fk" => $_POST["discipline"]]);
    } else {
        $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and month = :month and unavailable = 0 group by day order by day, schedule", ["classroom_fk" => $_POST["classroom"], "month" => $_POST["month"]]);
    }
    $criteria = new CDbCriteria();
    $criteria->with = array('studentFk');
    $criteria->together = true;
    $criteria->order = 'name';
    $enrollments = StudentEnrollment::model()->findAllByAttributes(array('classroom_fk' => $_POST["classroom"]), $criteria);
    if ($schedules != null) {
        if ($enrollments != null) {
            $students = [];
            $dayName = ["Domingo", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"];
            foreach ($enrollments as $enrollment) {
                $array["studentId"] = $enrollment->student_fk;
                $array["studentName"] = $enrollment->studentFk->name;
                $array["schedules"] = [];
                foreach ($schedules as $schedule) {
                    $classFault = ClassFaults::model()->exists("schedule_fk = :schedule_fk and student_fk = :student_fk", ["schedule_fk" => $schedule->id, "student_fk" => $enrollment->student_fk]);
                    $available = date("Y-m-d") >= Yii::app()->user->year . "-" . str_pad($schedule->month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($schedule->day, 2, "0", STR_PAD_LEFT);
                    array_push($array["schedules"], ["available" => $available, "day" => $schedule->day, "week_day" => $dayName[$schedule->week_day], "schedule" => $schedule->schedule, "fault" => $classFault]);
                }
                array_push($students, $array);
            }
            echo json_encode(["valid" => true, "students" => $students]);
        } else {
            echo json_encode(["valid" => false, "error" => "Matricule alunos nesta turma para trazer o quadro de frequência."]);
        }
    } else {
        echo json_encode(["valid" => false, "error" => "Não existe quadro de horário com dias letivos para o mês selecionado."]);
    }
}

/**
 * Save the frequency for each student and class.
 */
public
function actionSaveFrequency()
{
    if ($_POST["fundamentalMaior"] == "1") {
        $schedule = Schedule::model()->find("classroom_fk = :classroom_fk and day = :day and month = :month and schedule = :schedule", ["classroom_fk" => $_POST["classroomId"], "day" => $_POST["day"], "month" => $_POST["month"], "schedule" => $_POST["schedule"]]);
        $this->saveFrequency($schedule);
    } else {
        $schedules = Schedule::model()->findAll("classroom_fk = :classroom_fk and day = :day and month = :month", ["classroom_fk" => $_POST["classroomId"], "day" => $_POST["day"], "month" => $_POST["month"]]);
        foreach ($schedules as $schedule) {
            $this->saveFrequency($schedule);
        }
    }
}

private
function saveFrequency($schedule)
{
    if ($_POST["studentId"] != null) {
        if ($_POST["fault"] == "1") {
            $classFault = new ClassFaults();
            $classFault->student_fk = $_POST["studentId"];
            $classFault->schedule_fk = $schedule->id;
            $classFault->save();
        } else {
            ClassFaults::model()->deleteAll("schedule_fk = :schedule_fk and student_fk = :student_fk", ["schedule_fk" => $schedule->id, "student_fk" => $_POST["studentId"]]);
        }
    } else {
        if ($_POST["fault"] == "1") {
            $enrollments = StudentEnrollment::model()->findAll("classroom_fk = :classroom_fk", ["classroom_fk" => $_POST["classroomId"]]);
            foreach ($enrollments as $enrollment) {
                $classFault = ClassFaults::model()->find("schedule_fk = :schedule_fk and student_fk = :student_fk", ["schedule_fk" => $schedule->id, "student_fk" => $enrollment->student_fk]);
                if ($classFault == null) {
                    $classFault = new ClassFaults();
                    $classFault->student_fk = $enrollment->student_fk;
                    $classFault->schedule_fk = $schedule->id;
                    $classFault->save();
                }
            }
        } else {
            ClassFaults::model()->deleteAll("schedule_fk = :schedule_fk", ["schedule_fk" => $schedule->id]);
        }
    }
}

/**
 * Get all disciplines by classroom
 */
public
function actionGetDisciplines()
{
    $crid = $_POST['classroom'];
    $disciplinesLabels = ClassroomController::classroomDisciplineLabelArray();
    if (Yii::app()->getAuthManager()->checkAccess('instructor', Yii::app()->user->loginInfos->id)) {
        $disciplines = Yii::app()->db->createCommand(
            "select d.id from `edcenso_discipline` as `d`
                        JOIN `instructor_teaching_data` `t` ON 
                                (`t`.`discipline_1_fk` = `d`.`id` 
                                || `t`.`discipline_2_fk` = `d`.`id` 
                                || `t`.`discipline_3_fk` = `d`.`id`
                                || `t`.`discipline_4_fk` = `d`.`id`
                                || `t`.`discipline_5_fk` = `d`.`id`
                                || `t`.`discipline_6_fk` = `d`.`id`
                                || `t`.`discipline_7_fk` = `d`.`id`
                                || `t`.`discipline_8_fk` = `d`.`id`
                                || `t`.`discipline_9_fk` = `d`.`id`
                                || `t`.`discipline_10_fk` = `d`.`id`
                                || `t`.`discipline_11_fk` = `d`.`id`
                                || `t`.`discipline_12_fk` = `d`.`id`
                                || `t`.`discipline_13_fk` = `d`.`id`)
                        join `classroom` as `c` on (c.id = t.classroom_id_fk)
                        left join instructor_identification ii on t.instructor_fk = ii.id 
                        where ii.users_fk = " . Yii::app()->user->loginInfos->id . " and t.classroom_id_fk = " . $crid . " order by d.name")->queryAll();
        foreach ($disciplines as $discipline) {
            echo htmlspecialchars(CHtml::tag('option', array('value' => $discipline['id']), CHtml::encode($disciplinesLabels[$discipline['id']]), true));
        }
    } else {
        echo CHtml::tag('option', array('value' => ""), CHtml::encode('Selecione a disciplina'), true);
        $classr = Yii::app()->db->createCommand("select distinct discipline_fk from schedule join edcenso_discipline on edcenso_discipline.id = schedule.discipline_fk where classroom_fk = " . $crid . " order by edcenso_discipline.name")->queryAll();
        foreach ($classr as $i => $discipline) {
            if (isset($discipline['discipline_fk'])) {
                echo htmlspecialchars(CHtml::tag('option', array('value' => $discipline['discipline_fk']), CHtml::encode($disciplinesLabels[$discipline['discipline_fk']]), true));
            }
        }
    }
}
}
