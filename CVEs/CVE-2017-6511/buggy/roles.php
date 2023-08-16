<?php

class Roles_View extends View
{
	public function __construct($page)
	{
		parent::__construct($page);
	}

	public function ShowList($columns, $data)
	{
		$title = 'Role użytkowników';
		$image = 'img/32x32/lock_go.png';

		$attribs = array(
			array('width' => '5%',  'align' => 'center', 'visible' => '1'),
			array('width' => '10%', 'align' => 'center', 'visible' => '1', 'image' => '1'),
			array('width' => '15%', 'align' => 'center', 'visible' => '1'),
			array('width' => '10%', 'align' => 'center', 'visible' => '1'),
			array('width' => '50%', 'align' => 'center', 'visible' => '1'),
			array('width' => '10%', 'align' => 'center', 'visible' => '1'),
		);
		
		$actions = array(
			array('action' => 'view',   'icon' => 'info.png',  'title' => 'Podgląd'),
			array('action' => 'edit',   'icon' => 'edit.png',  'title' => 'Edytuj'),
			array('action' => 'delete', 'icon' => 'trash.png', 'title' => 'Usuń'),
		);
	
		foreach ($data as $k => $v)
		{
			foreach ($v as $key => $value)
			{
				if ($key == 'user_login')
				{
					if ($value == $_SESSION['user_login'])
					{
						$data[$k]['user_login'] = '<b style="color: blue;">' . $data[$k]['user_login'] . '</b>';
					}
				}
			}
		}

		include GENER_DIR . 'list.php';

		$list_object = new ListBuilder();

		$list_object->init($title, $image, $columns, $data, $this->get_list_params(), $attribs, $actions);

		$result = $list_object->build_list();

		return $result;
	}

	public function ShowForm($id, $data)
	{
		$users = array();
		$functions = array();

		foreach ($data as $k => $v) 
		{
			if ($k == 'users')
			{
				foreach ($v as $i => $j)
				{
					foreach ($j as $key => $value)
					{
						if ($key == 'id') $user_id = $value;
						if ($key == 'user_login') $user_login = $value;
						if ($key == 'user_name') $user_name = $value;
						if ($key == 'user_surname') $user_surname = $value;
					}
					if ($id) // edycja roli
					{
						if ($user_id == $id)
						{
							$users[] = array(
								'value' => $user_id, 'caption' => $user_name . ' ' . $user_surname . ' (' . $user_login . ')',
								);
						}
					}
					else // nowa rola
					{
						$users[] = array(
							'value' => $user_id, 'caption' => $user_name . ' ' . $user_surname . ' (' . $user_login . ')',
							);
					}
				}
			}
			if ($k == 'functions')
			{
				foreach ($v as $i => $j)
				{
					foreach ($j as $key => $value)
					{
						if ($key == 'function_id') $function_id = $value;
						if ($key == 'function') $function = $value;
						if ($key == 'meaning') $meaning = $value;
						if ($key == 'module') $module = $value;
						if ($key == 'access') $access = $value;
					}
					$chk = $access ? 'checked' : NULL;
					$functions[] = array(
						'caption' => NULL, 
						'data' => array(
							'type' => 'checkbox', 'id' => 'function_'.$function_id, 'name' => 'function_'.$function_id, 'label' => $meaning . ' (' . $module . ')', $chk => $chk, 'value' => NULL,
							),
						);
				}
			}
		}

		include GENER_DIR . 'form.php';

		$form_object = new FormBuilder();

		$form_title = $data ? 'Edycja roli użytkownika' : 'Nowa rola użytkownika';
		$form_image = 'img/32x32/list_edit.png';
		$form_width = '50%';
		
		$form_object->init($form_title, $form_image, $form_width);

		$action = $id ? 'edit&id=' . $id : 'add';

		$form_action = 'index.php?route=' . MODULE_NAME . '&action=' . $action;

		$form_object->set_action($form_action);

		$form_inputs = array(
			array(
				'caption' => 'Użytkownik', 
				'data' => array(
					'type' => 'select', 'id' => 'user_id', 'name' => 'user_id', 
					'option' => $users, 
					),
				),
			array(
				'caption' => 'Funkcje', 
				'data' => array(
					'type' => 'label', 'value' => NULL,
					),
				),
			);
		
		foreach ($functions as $function)
		{
			$form_inputs[] = $function;
		}

		$form_object->set_inputs($form_inputs);
		
		$form_hiddens = array();
			
		$form_object->set_hiddens($form_hiddens);

		$form_buttons = array(
			array(
				'type' => 'save', 'id' => 'save_button', 'name' => 'save_button', 'value' => 'Zapisz',
				),
			array(
				'type' => 'close', 'id' => 'update_button', 'name' => 'update_button', 'value' => 'Zamknij',
				),
			array(
				'type' => 'cancel', 'id' => 'cancel_button', 'name' => 'cancel_button', 'value' => 'Anuluj',
				),
			);
		
		$form_object->set_buttons($form_buttons);

		$result = $form_object->build_form();

		return $result;
	}

	public function ShowDetails($data)
	{
		include GENER_DIR . 'view.php';

		$view_object = new ViewBuilder();

		$view_title = 'Szczegóły roli użytkownika';
		$view_image = 'img/32x32/list_information.png';
		$view_width = '50%';
		
		$view_object->init($view_title, $view_image, $view_width);

		$view_action = 'index.php?route=' . MODULE_NAME;

		$view_object->set_action($view_action);

		$view_inputs = array();

		if (is_array($data))
		{
			foreach ($data as $key => $value) 
			{
				$view_inputs[] = array('caption' => $key, 'value' => $value);
			}
		}

		$view_object->set_inputs($view_inputs);
		
		$view_buttons = array(
			array(
				'type' => 'cancel', 'id' => 'cancel_button', 'name' => 'cancel_button', 'value' => 'Zamknij',
				),
			);
		
		$view_object->set_buttons($view_buttons);

		$result = $view_object->build_view();

		return $result;
	}
}

?>
