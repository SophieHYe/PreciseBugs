<?php

class Users_View extends View
{
	public function __construct($page)
	{
		parent::__construct($page);
	}

	public function ShowList($columns, $data)
	{
		$title = 'Użytkownicy serwisu';
		$image = 'img/32x32/users_group.png';

		$attribs = array(
			array('width' => '5%',  'align' => 'center', 'visible' => '1'),
			array('width' => '10%', 'align' => 'left',   'visible' => '1', 'image' => '1'),
			array('width' => '10%',  'align' => 'left',   'visible' => '0'),
			array('width' => '10%', 'align' => 'left',   'visible' => '1'),
			array('width' => '10%', 'align' => 'left',   'visible' => '1'),
			array('width' => '25%', 'align' => 'left', 'visible' => '1'),
			array('width' => '5%',  'align' => 'left', 'visible' => '1'),
			array('width' => '10%', 'align' => 'center', 'visible' => '1'),
			array('width' => '5%', 'align' => 'center', 'visible' => '1'),
			array('width' => '5%', 'align' => 'center', 'visible' => '0'),
			array('width' => '5%', 'align' => 'center', 'visible' => '0'),
			array('width' => '5%',  'align' => 'center', 'visible' => '0'),
			array('width' => '15%', 'align' => 'center', 'visible' => '1'),
		);
		
		$actions = array(
			array('action' => 'view',    'icon' => 'info.png',   'title' => 'Podgląd'),
			array('action' => 'edit',    'icon' => 'edit.png',   'title' => 'Edytuj'),
			array('action' => 'setpass', 'icon' => 'access.png', 'title' => 'Hasło'),
			array('action' => 'delete',  'icon' => 'trash.png',  'title' => 'Usuń'),
		);
	
		$group_names = array('Guest', 'Adm', 'Opr', 'Usr');

		foreach ($data as $k => $v)
		{
			foreach ($v as $key => $value)
			{
				if ($key == 'id')
				{
					if ($value == $_SESSION['user_id'])
					{
						$data[$k]['user_login'] = '<b style="color: blue;">' . $data[$k]['user_login'] . '</b>';
					}
				}
				if ($key == 'status')
				{
					$data[$k][$key] = $group_names[$value];
				}
			}
		}

		include GENER_DIR . 'list.php';

		$list_object = new ListBuilder();

		$list_object->init($title, $image, $columns, $data, $this->get_list_params(), $attribs, $actions);

		$result = $list_object->build_list();

		return $result;
	}

	public function ShowForm($data, $user)
	{
		if ($data) // edycja
		{
			foreach ($data as $key => $value) 
			{
				if ($key == 'id') $main_id = $value;
				if ($key == 'user_login') $main_user_login = $value;
				if ($key == 'user_password') $main_user_password = $value;
				if ($key == 'user_name') $main_user_name = $value;
				if ($key == 'user_surname') $main_user_surname = $value;
				if ($key == 'email') $main_email = $value;
				if ($key == 'status') $main_status = $value;
				if ($key == 'registered') $main_registered = $value;
				if ($key == 'logged_in') $main_logged_in = $value;
				if ($key == 'modified') $main_modified = $value;
				if ($key == 'logged_out') $main_logged_out = $value;
				if ($key == 'active') $main_active = $value;
			}
		}
		else // nowa pozycja
		{
			$main_id = NULL;
			$main_user_login = NULL;
			$main_user_password = NULL;
			$main_user_name = NULL;
			$main_user_surname = NULL;
			$main_email = NULL;
			$main_registered = NULL;
			$main_logged_in = NULL;
			$main_modified = NULL;
			$main_logged_out = NULL;
			$main_status = USER;
			$main_active = 1;
		}

		$sel = range(0, 4);
		$sel[$main_status] = 'selected';

		$chk = array(NULL, NULL);
		$chk[$main_active] = 'checked';

		include GENER_DIR . 'form.php';

		$form_object = new FormBuilder();

		$form_title = $data ? 'Edycja użytkownika' : 'Nowy użytkownik';
		$form_image = 'img/32x32/list_edit.png';
		$form_width = '50%';
		
		$form_object->init($form_title, $form_image, $form_width);

		$action = $data ? 'edit&id=' . $main_id : 'add';

		$form_action = 'index.php?route=' . MODULE_NAME . '&action=' . $action;

		$form_object->set_action($form_action);

		$form_inputs = array(
			array(
				'caption' => 'Imię', 
				'data' => array(
					'type' => 'text', 'id' => 'user_name', 'name' => 'user_name', 'value' => $main_user_name, 'required' => 'required',
					),
				),
			array(
				'caption' => 'Nazwisko', 
				'data' => array(
					'type' => 'text', 'id' => 'user_surname', 'name' => 'user_surname', 'value' => $main_user_surname, 'required' => 'required',
					),
				),
			array(
				'caption' => 'Login', 
				'data' => array(
					'type' => 'text', 'id' => 'user_login', 'name' => 'user_login', 'value' => $main_user_login, 'required' => 'required',
					),
				),
			array(
				'caption' => 'E-mail', 
				'data' => array(
					'type' => 'email', 'id' => 'email', 'name' => 'email', 'value' => $main_email, 'required' => 'required',
					),
				),
			array(
				'caption' => 'Hasło', 
				'data' => array(
					'type' => 'password', 'id' => 'user_password', 'name' => 'user_password', 'value' => $main_user_password, 'required' => 'required',
					),
				),
			array(
				'caption' => 'Grupa użytkowników', 
				'data' => array(
					'type' => 'select', 'id' => 'status', 'name' => 'status', 
					'option' => array(
						array(
							'value' => '1', 'caption' => 'Administratorzy', $sel[1] => $sel[1],
							),
						array(
							'value' => '2', 'caption' => 'Operatorzy', $sel[2] => $sel[2],
							),
						array(
							'value' => '3', 'caption' => 'Zarejestrowani', $sel[3] => $sel[3],
							),
						), 
					),
				),
			array(
				'caption' => NULL, 
				'data' => array(
					'type' => 'radio', 'name' => 'active', 
					'items' => array(
						array(
							'id' => 'active_yes', 'label' => 'Aktywny', $chk[1] => $chk[1], 'value' => 1,
							),
						array(
							'id' => 'active_no', 'label' => 'Zablokowany', $chk[0] => $chk[0], 'value' => 0,
							),
						),
					),
				),
			);

		if ($data) unset($form_inputs[4]); // wyklucza z listy inputów pole hasło

		if ($user->get_value('user_status') != ADMIN) unset($form_inputs[5]); // wyklucza z listy inputów pole profil

		$form_object->set_inputs($form_inputs);
		
		$form_hiddens = $user->get_value('user_status') == ADMIN ? array() : array(
				array(
					'type' => 'hidden', 'id' => 'status', 'name' => 'status', 'value' => $main_status,
					),
				);
			
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

		$view_title = 'Szczegóły użytkownika';
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
				if (in_array($key, array('user_password', 'status', 'active'))) continue;
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

	public function ShowPassword($id)
	{
		include GENER_DIR . 'form.php';

		$form_object = new FormBuilder();

		$form_title = 'Nowe hasło';
		$form_image = 'img/32x32/server_key.png';
		$form_width = '300px';
		
		$form_object->init($form_title, $form_image, $form_width);

		$form_action = 'index.php?route=' . MODULE_NAME . '&action=setpass&id=' . $id;

		$form_object->set_action($form_action);

		$form_inputs = array(
			array(
				'caption' => 'Hasło', 
				'data' => array(
					'type' => 'password', 'id' => 'user_password', 'name' => 'user_password', 'value' => NULL, 'required' => 'required',
					),
				),
			array(
				'caption' => 'Powtórz', 
				'data' => array(
					'type' => 'password', 'id' => 'user_password_repeat', 'name' => 'user_password_repeat', 'value' => NULL, 'required' => 'required',
					),
				),
			);

		$form_object->set_inputs($form_inputs);
		
		$form_hiddens = array();
			
		$form_object->set_hiddens($form_hiddens);

		$form_buttons = array(
			array(
				'type' => 'submit', 'id' => 'save_button', 'name' => 'save_button', 'value' => 'Zapisz',
				),
			array(
				'type' => 'cancel', 'id' => 'cancel_button', 'name' => 'cancel_button', 'value' => 'Anuluj',
				),
			);
		
		$form_object->set_buttons($form_buttons);

		$result = $form_object->build_form();

		return $result;
	}
}

?>