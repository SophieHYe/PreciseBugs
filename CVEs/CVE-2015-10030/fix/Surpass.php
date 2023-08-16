<?php namespace Sukohi\Surpass;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Exception;

class Surpass {

    const TABLE = 'image_files';
    const DIR_HIDDEN_NAME = 'surpass_hidden_dir';
    const ID_HIDDEN_NAME = 'surpass_ids';
    const KEY_HIDDEN_NAME = 'surpass_keys';
    const KEY_OVERWRITE_ID = 'surpass_overwrite_id';
    private $_path, $_dir, $_progress;
    private $_alert = 'You can upload up to %d files.';
    private $_button = 'Remove';
    private $_drop_zone_id, $_id_hidden_name = '';
    private $_max_files = 5;
    private $_filename_length = 10;
    private $_timeout = 0;
    private $_form_data, $_result, $_load, $_resize_params, $_css = array();
    private $_overwrite = false;
    private $_ids = array(

        'input' => 'image_upload',
        'preview' => 'preview_images'

    );
    private $_callbacks = array(
        'add' => '',
        'done' => ''
    );
    private $_preview_params = array(

        'maxHeight' => 120

    );

    public function path($path) {

        $this->_path = $path;
        return $this;

    }

    public function dir($dir) {

        $this->_dir = str_replace(["\0", '/', '.'], '', $dir);
        $this->_id_hidden_name = self::ID_HIDDEN_NAME .'_'. $dir;
        return $this;

    }

    public function id($mode, $id) {

        $this->_ids[$mode] = $id;
        return $this;

    }

    public function ids($ids) {

        $this->_ids = $ids;
        return $this;

    }

    public function renderId($mode) {

        return $this->_ids[$mode];

    }

    public function maxFiles($max_files) {

        $this->_max_files = $max_files;
        return $this;

    }

    public function alert($alert) {

        $this->_alert = $alert;
        return $this;

    }

    public function formData($form_data) {

        $this->_form_data = $form_data;
        return $this;

    }

    public function button($label) {

        $this->_button = $label;
        return $this;

    }

    public function preview($params) {

        $this->_preview_params = $params;
        return $this;

    }

    public function resize($size, $force_crop = true) {

        $this->_resize_params = ['size' => $size, 'force_crop' => $force_crop];
        return $this;

    }

    public function timeout($milliseconds) {

        $this->_timeout = intval($milliseconds);
        return $this;

    }

    public function overwrite($bool = false) {

        $this->_overwrite = $bool;
        return $this;

    }

    public function css($css) {

        $this->_css = $css;
        return $this;

    }

    public function progress($content) {

        $this->_progress = $content;
        return $this;

    }

    public function callback($callbacks) {

        $this->_callbacks = $callbacks;
        return $this;

    }

    public function dropZone($id) {

        $this->_drop_zone_id = $id;
        return $this;

    }

    public function renderCss($mode) {

        return (!empty($this->_css[$mode])) ? ' class="'. $this->_css[$mode] .'"' : '';

    }

    public function html($mode) {

        if($mode == 'preview') {

            return View::make('packages.sukohi.surpass.preview', array(
                'id' => $this->renderId('preview'),
                'css' => $this->renderCss('preview')
            ))->render();

        } else if($mode == 'js') {

            $load_data = [];

            if(!empty($this->_load)) {

                foreach ($this->_load as $id => $load) {

                    $load_data[] = array(

                        'id' => $id,
                        'url' => $load->url,
                        'filename' => $load->filename

                    );

                }

            }
            $this->_form_data[self::DIR_HIDDEN_NAME] = $this->_dir;
            $this->_form_data[self::KEY_HIDDEN_NAME] = json_encode($this->_ids);

            return View::make('packages.sukohi.surpass.js', array(

                'max_file' => $this->_max_files,
                'load_data' => $load_data,
                'form_data' => $this->_form_data,
                'alert' => sprintf($this->_alert, $this->_max_files),
                'button_label' => $this->_button,
                'preview_params' => $this->_preview_params,
                'resize_params' => $this->_resize_params,
                'progress' => $this->_progress,
                'callbacks' => $this->_callbacks,
                'drop_zone_id' => $this->_drop_zone_id,
                'id_hidden_name' => $this->_id_hidden_name,
                'dir' => strtolower($this->_dir),
                'dir_studly' => studly_case($this->_dir),
                'input_id' => $this->renderId('input'),
                'preview_id' => $this->renderId('preview'),
                'css_div' => Surpass::renderCss('div'),
                'css_loading' => Surpass::renderCss('loading'),
                'css_button' => Surpass::renderCss('button'),
                'overwrite' => $this->_overwrite,
                'timeout' => $this->_timeout

            ))->render();

        }

    }

    public function filenameLength($length) {

        $this->_filename_length = $length;
        return $this;

    }

    public function insert($file_path, $attributes = array()) {

        if(!File::exists($file_path)) {

            return -1;

        }

        DB::beginTransaction();

        try {

            $extension = File::extension($file_path);
            $filename = $this->filename($extension);
            $size = File::size($file_path);
            $save_dir = $this->filePath($this->_dir);

            if(!File::exists($save_dir)) {

                File::makeDirectory($save_dir);

            }

            $save_path = $save_dir .'/'. $filename;
            File::copy($file_path, $save_path);
            DB::commit();

        } catch (Exception $e) {

            DB::rollback();
            return -1;

        }

        return $this->saveData($filename, $extension, $size, $attributes);

    }

    public function save($attributes = array()) {

        $this->dir(Input::get(self::DIR_HIDDEN_NAME));
        $this->ids(json_decode(Input::get(self::KEY_HIDDEN_NAME), true));
        $result = false;
        $id = $width = $height = -1;
        $mime_type = '';
        $input_id = $this->_ids['input'];
        $extension = Input::file($input_id)->getClientOriginalExtension();
        $filename = $this->filename($extension);
        $file_size = Input::file($input_id)->getSize();
        $error_message = '';

        DB::beginTransaction();

        try {

            $save_path = $this->filePath($this->_dir);

            if(!file_exists($save_path)) {

                throw new Exception('The directory doesn\'t exist.');

            } else if(!is_writable($save_path)) {

                throw new Exception('The directory is not writable.');

            }

            Input::file($input_id)->move($save_path, $filename);
            $id = $this->saveData($filename, $extension, $file_size, $attributes);
            DB::commit();
            list($width, $height, $image_type) = getimagesize($save_path .'/'. $filename);
            $mime_type = image_type_to_mime_type ($image_type);
            $result = true;

        } catch (Exception $e) {

            $error_message = $e->getMessage();
            $filename = null;
            DB::rollback();

        }

        $this->_result = array(
            'result' => $result,
            'insertId' => $id,
            'path' => $this->_path,
            'dir' => $this->_dir,
            'filename' => $filename,
            'file_path' => $this->_path .'/'. $this->_dir .'/'. $filename,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'mime_type' => $mime_type,
            'saveMode' => ($this->isOverwrite()) ? 'overwrite' : 'insert'
        );

        if(!empty($error_message)) {

            $this->_result['error_message'] = $error_message;

        }

        return $result;

    }

    public function saveAttributes($id, $attributes) {

        return DB::table(self::TABLE)->where('id', $id)->update(array(
            'attributes' => json_encode($attributes)
        ));

    }

    public function remove() {

        $result = $this->removeById(intval(Input::get('remove_id')));
        $this->_result = array('result' => $result);
        return $result;

    }

    public function removeById($ids = '') {

        $result = false;

        if(empty($ids)) {

            return $result;

        }

        if(!is_array($ids)) {

            $ids = array($ids);

        }

        DB::beginTransaction();

        try {

            foreach ($ids as $id) {

                $db = DB::table(self::TABLE)->where('id', '=', $id);
                $image_file = $db->select('dir', 'filename')->first();
                $remove_path = $this->filePath($image_file->dir, $image_file->filename);
                File::delete($remove_path);
                $db->delete();

            }

            DB::commit();
            $result = true;

        } catch (Exception $e) {

            DB::rollback();

        }

        return $result;

    }

    public function result() {

        $result = $this->_result;
        $this->_result = array();
        return Response::json($result);

    }

    public function refresh() {

        DB::beginTransaction();

        try {

            $image_files = DB::table(self::TABLE)->select('id', 'dir', 'filename')->get();
            $exists_image_paths = array();

            foreach ($image_files as $key => $image_file) {

                $path = $this->filePath($image_file->dir, $image_file->filename);

                if(!file_exists($path)) {

                    DB::table(self::TABLE)
                        ->where('id', '=', $image_file->id)
                        ->delete();

                } else {

                    $exists_image_paths[] = public_path($this->_path .'/'. $image_file->dir .'/'. $image_file->filename);

                }

            }

            $files = File::allFiles($this->_path);

            foreach ($files as $file) {

                $remove_path = $file->getRealPath();

                if(!in_array($remove_path, $exists_image_paths)) {

                    File::delete($remove_path);

                }

            }

            DB::commit();
            return true;

        } catch (Exception $e) {

            DB::rollback();
            return false;

        }

    }

    public function load($ids=array(), $old_flag=true) {

        if(!is_array($ids)) {

            $ids = [$ids];

        }

        if($old_flag
            && Input::old($this->_id_hidden_name)
            && is_array(Input::old($this->_id_hidden_name))) {

            $ids = Input::old($this->_id_hidden_name);

        }

        if(!empty($ids)) {

            $this->_load = array();
            $image_files = DB::table(self::TABLE)
                ->select('id', 'dir', 'filename', 'extension', 'size', 'created_at', 'attributes')
                ->whereIn('id', $ids)
                ->get();

            foreach ($image_files as $image_file) {

                $this->addLoadObject(array(

                    'id' => $image_file->id,
                    'dir' => $image_file->dir,
                    'filename' => $image_file->filename,
                    'extension' => $image_file->extension,
                    'size' => $image_file->size,
                    'created_at' => $image_file->created_at,
                    'attributes' => json_decode($image_file->attributes, true)

                ));

            }

        }

        return $this;

    }

    public function loadSaved() {

        $keys = array_keys($this->_load);
        return $this->_load[end($keys)];

    }

    public function loadData() {

        return $this->_load;

    }

    public function imageFileId($dir) {

        $ids = Surpass::imageFileIds($dir);

        if(!empty($ids)) {

            return $ids[0];

        }

        return '';

    }

    public function imageFileIds($dir) {

        $this->dir($dir);
        $id_hidden_name = Input::get($this->_id_hidden_name);
        $ids = !empty($id_hidden_name) ? Input::get($this->_id_hidden_name) : [];
        sort($ids);
        return $ids;

    }

    private function filePath($dir, $filename='') {

        $path = $this->_path .'/'. $dir;

        if(!empty($filename)) {

            $path .= '/'. $filename;

        }

        return public_path($path);

    }

    private function fileUrl($dir, $filename) {

        return URL::to($this->_path .'/'. $dir .'/'. $filename);

    }

    private function addLoadObject($params) {

        $id = $params['id'];
        $dir = $params['dir'];
        $filename = $params['filename'];
        $attributes = $params['attributes'];

        $load = new \stdClass;
        $load->id = $id;
        $load->dir = $dir;
        $load->filename = $filename;
        $load->path = $this->filePath($dir, $filename);
        $load->url = $this->fileUrl($dir, $filename);
        $load->attributes = $attributes;
        $load->tag = '<img src="'. $load->url .'"'. $this->generateAttribute($attributes) .'>';
        $this->_load[$id] = $load;

    }

    private function generateAttribute($attributes) {

        $return = '';

        if(!empty($attributes)) {

            foreach ($attributes as $key => $value) {

                $return .= ' '. $key .'="'. $value .'"';

            }

        }

        return $return;

    }

    private function filename($extension) {

        return str_random($this->_filename_length) .'.'. $extension;

    }

    private function isOverwrite() {

        return (Input::has(self::KEY_OVERWRITE_ID) && Input::get(self::KEY_OVERWRITE_ID) > 0);

    }

    private function saveData($filename, $extension, $size, $attributes) {

        $save_params = array(

            'dir' => $this->_dir,
            'filename' => $filename,
            'extension' => $extension,
            'size' => $size,
            'created_at' => Carbon::now(),
            'attributes' => (!empty($attributes)) ? json_encode($attributes) : ''

        );

        if($this->isOverwrite()) {

            $id = Input::get(self::KEY_OVERWRITE_ID);
            DB::table(self::TABLE)->where('id', $id)->update($save_params);

        } else {

            $id = DB::table(self::TABLE)->insertGetId($save_params);

        }

        if($id > 0) {

            $save_params['id'] = $id;
            $save_params['attributes'] = $attributes;
            $this->addLoadObject($save_params);

        } else {

            $id = -1;
            throw new Exception('Save Failed.');

        }

        return $id;

    }

}