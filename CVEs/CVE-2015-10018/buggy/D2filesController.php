<?php


class D2filesController extends Controller
{
    #public $layout='//layouts/column2';

    public $defaultAction = "admin";
    public $scenario = "crud";
    public $scope = "crud";


    public function filters()
    {
        return array(
            'accessControl',
        );
    }

    public function accessRules() {
        return array(
            array('deny', // deny guests
                'users'=>array('?'),
            ),
            array(
                'allow', // allow main screen only to Administrator
                'actions' => array('create', 'admin', 'view', 'update', 'editableSaver', 'delete'),
                'roles' => array('Administrator'),
            ),
            array(
                'allow', // allow actions controled by related model to registred users
                'actions' => array('upload', 'deleteFile', 'downloadFile', 'editableSaver'),
                'users'=>array('*'),
            ),
            array('deny',
                'users'=>array('*'),
            ),
        );
    }
    
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        if ($this->module !== null) {
            $this->breadcrumbs[$this->module->Id] = array('/' . $this->module->Id);
        }
        return true;
    }

    public function actionView($id, $ajax = false)
    {
        $model = $this->loadModel($id);
        $this->render('view', array('model' => $model,));
    }

    public function actionCreate()
    {
        $model = new D2files;
        $model->scenario = $this->scenario;

        $this->performAjaxValidation($model, 'd2files-form');

        if (isset($_POST['D2files'])) {
            $model->attributes = $_POST['D2files'];

            try {
                if ($model->save()) {
                    if (isset($_GET['returnUrl'])) {
                        $this->redirect($_GET['returnUrl']);
                    } else {
                        $this->redirect(array('view', 'id' => $model->id));
                    }
                }
            } catch (Exception $e) {
                $model->addError('id', $e->getMessage());
            }
        } elseif (isset($_GET['D2files'])) {
            $model->attributes = $_GET['D2files'];
        }

        $this->render('create', array('model' => $model));
    }

    public function actionUpdate($id)
    {
        $model = $this->loadModel($id);
        $model->scenario = $this->scenario;

        $this->performAjaxValidation($model, 'd2files-form');

        if (isset($_POST['D2files'])) {
            $model->attributes = $_POST['D2files'];


            try {
                if ($model->save()) {
                    if (isset($_GET['returnUrl'])) {
                        $this->redirect($_GET['returnUrl']);
                    } else {
                        $this->redirect(array('view', 'id' => $model->id));
                    }
                }
            } catch (Exception $e) {
                $model->addError('id', $e->getMessage());
            }
        }
        
        $this->render('update', array('model' => $model));
    }
    
    public function actionUpload($model_name, $model_id) {
        
        // validate download action access
        //if (!Yii::app()->user->checkAccess($model_name . '.uploadD2File')) {
        D2files::extendedCheckAccess($model_name . '.uploadD2File');
        
        if (!$this->performReadValidation($model_name, $model_id)) {
            throw new CHttpException(403, Yii::t("D2filesModule.model","You are not authorized to perform this action."));
        }

        Yii::import("vendor.dbrisinajumi.d2files.compnents.*");
        $oUploadHandler = new UploadHandlerD2files(
                        array(
                            'model_name' => $model_name,
                            'model_id' => $model_id,
                        )
        );
        
        return true;

    }
    
    public function actionDeleteFile($id) {
        
        $m = D2files::model();
        $model = $m->findByPk($id);
        if ($model === null) {
            throw new CHttpException(404, Yii::t("D2filesModule.model","The requested record does not exist."));
        }
        
        // validate read access
        if (!$this->performReadValidation($model->model, $model->model_id)) {
            throw new CHttpException(403, Yii::t("D2filesModule.model","You are not authorized to perform this action."));
        }
        
        // validate delete action access
        //if (!Yii::app()->user->checkAccess($model->model . '.deleteD2File')) {
        D2files::extendedCheckAccess($model->model . '.deleteD2File');
        
        $model->deleted = 1;
        $model->save();
        
    }

    public function actionDownloadFile($id) {
        
        $criteria = new CDbCriteria;
        $criteria->compare('t.deleted', 0);
        $m = D2files::model();
        $model = $m->findByPk($id, $criteria);
        if ($model === null) {
            throw new CHttpException(404, Yii::t("D2filesModule.model","The requested record does not exist."));
        }

        // validate download action access
        //if (!Yii::app()->user->checkAccess($model->model . '.downloadD2File')) {
        D2files::extendedCheckAccess($model->model . '.downloadD2File');
        
        // validate read access
        if (!$this->performReadValidation($model->model, $model->model_id)) {
            throw new CHttpException(403, Yii::t("D2filesModule.model","You are not authorized to perform this action."));
        }

        Yii::import( "vendor.dbrisinajumi.d2files.compnents.*");
        $oUploadHandler = new UploadHandlerD2files(
                        array(
                            'model_name' => $model->model,
                            'model_id' => $id,
                            'download_via_php' => TRUE,
                            'file_name' => $model->file_name,
                        )
        );  
    }
    
    public function actionEditableSaver()
    {
        $id = Yii::app()->request->getPost('pk');
        if(empty($id)){
            throw new CHttpException(404, Yii::t("D2filesModule.model","The requested record does not exist."));
        }
        
        $m = D2files::model();
        $model = $m->findByPk($id);
        if ($model === null) {
            throw new CHttpException(404, Yii::t("D2filesModule.model","The requested record does not exist."));
        }
        
        // validate read access
        if (!$this->performReadValidation($model->model, $model->model_id)) {
            throw new CHttpException(403, Yii::t("D2filesModule.model","You are not authorized to perform this action."));
        }
        
        // validate upload (editable) action access
        D2files::extendedCheckAccess($model->model . '.uploadD2File');        
        
        $es = new EditableSaver('D2files'); // classname of model to be updated
        $es->update();
    }

    public function actionAjaxCreate($field, $value) 
    {
        $model = new D2files;
        $model->$field = $value;
        try {
            if ($model->save()) {
                return TRUE;
            }else{
                return var_export($model->getErrors());
            }            
        } catch (Exception $e) {
            throw new CHttpException(500, $e->getMessage());
        }
    }
    
    public function actionDelete($id)
    {
        if (Yii::app()->request->isPostRequest) {
            try {
                $this->loadModel($id)->delete();
            } catch (Exception $e) {
                throw new CHttpException(500, $e->getMessage());
            }

            if (!isset($_GET['ajax'])) {
                if (isset($_GET['returnUrl'])) {
                    $this->redirect($_GET['returnUrl']);
                } else {
                    $this->redirect(array('admin'));
                }
            }
        } else {
            throw new CHttpException(400, Yii::t('D2filesModule.crud_static', 'Invalid request. Please do not repeat this request again.'));
        }
    }

    public function actionAdmin()
    {
        $model = new D2files('search');
        $scopes = $model->scopes();
        if (isset($scopes[$this->scope])) {
            $model->{$this->scope}();
        }
        $model->unsetAttributes();

        if (isset($_GET['D2files'])) {
            $model->attributes = $_GET['D2files'];
        }

        $this->render('admin', array('model' => $model));
    }

    public function loadModel($id)
    {
        $m = D2files::model();
        // apply scope, if available
        $scopes = $m->scopes();
        if (isset($scopes[$this->scope])) {
            $m->{$this->scope}();
        }
        $model = $m->findByPk($id);
        if ($model === null) {
            throw new CHttpException(404, Yii::t('D2filesModule.crud_static', 'The requested page does not exist.'));
        }
        return $model;
    }

    protected function performAjaxValidation($model)
    {
        if (isset($_POST['ajax']) && $_POST['ajax'] === 'd2files-form') {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }
    
    protected function performReadValidation($model_name, $model_id)
    {
        list($module_name, $model_name) = explode('.', $model_name);
        $m = $model_name::model();
        $modelMain = $m->findByPk($model_id);
        if ($modelMain === null) {
            return false;
        }
        return true;
    }

}
