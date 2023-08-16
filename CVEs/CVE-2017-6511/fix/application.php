<?php

/*
 * Klasa odpowiedzialna za całość aplikacji - wszystkie jej aspekty (front-end i back-end)
 */

class Application
{
	protected $db;
	protected $settings;
	protected $user;
	protected $acl;
	protected $page;
	protected $menu;

	protected $controller_object;
	protected $model_object;
	protected $view_object;

	public function __construct()
	{
		include ABSTR_DIR . 'controller.php';
		include ABSTR_DIR . 'model.php';
		include ABSTR_DIR . 'view.php';

		// filtrowanie według frazy:
		if (isset($_POST['ListSearchButton']))
		{
			$_SESSION['list_filter'] = htmlspecialchars(substr(trim($_POST['ListSearchText']), 0, 32));
		}

		// usuwanie filtrowania:
		if (isset($_POST['ListSearchClose']))
		{
			$_SESSION['list_filter'] = NULL;
		}
	}

	public function start()
	{
		if (file_exists(INSTALL_SCRIPT))
		{
			$_SESSION['install_mode'] = TRUE;
		}

		if (isset($_GET['route']) && isset($_SESSION['last_route']))
		{
			if ($_GET['route'] == $_SESSION['last_route']) $_SESSION['keep_paginator'] = TRUE;
			else unset($_SESSION['keep_paginator']);
		}

		include LIB_DIR . 'database.php';
		
		$this->db = new Database();
		$this->db->init(DB_HOST, DB_NAME, DB_USER, DB_PASS);
		$this->db->connect();

		// tworzy obiekty strony:

		$this->set_settings($this);
		$this->set_page($this);
		$this->set_user($this);
		$this->set_menu($this);
	}

	public function stop()
	{
		include LIB_DIR . 'visitors.php';

		$visitor = new Visitors($this);
		$visitor->Store();

		$_SESSION['last_route'] = isset($_GET['route']) ? $_GET['route'] : NULL;
	}

	public function get_dbc()
	{
		return $this->db->get_connection();
	}

	public function set_controller($controller, $action)
	{
		include CLASS_DIR . 'module.php';

		$module = new Module($controller);

		define ('MODULE_NAME', $module->get_name());

		$class_file = APP_DIR . 'controller/' . MODULE_NAME . '.php';
		$class_name = ucfirst(MODULE_NAME) . '_Controller';
		$class_method = ucfirst(strip_tags($action)) . '_Action';

		// tworzy obiekt kontrolera:

		if (file_exists($class_file))
		{
			include $class_file;

			if (class_exists($class_name))
			{
				$this->controller_object = new $class_name($this);

				$this->set_acl($this);
			}
			else
			{
				die ('Class: <h3>'.$class_name.'</h3> not found.');
			}
		}
		else
		{
			die ('File: <h3>'.$class_file.'</h3> not found.');
		}

		// tworzy obiekt modelu:

		$this->set_model_object(MODULE_NAME);

		// tworzy obiekt widoku:

		$this->set_view_object(MODULE_NAME);

		// wywołuje akcję (metodę) kontrolera:

		if (method_exists($class_name, $class_method))
		{
			$this->controller_object->{$class_method}();
		}
		else
		{
			die ('Method: <h3>'.$class_method.'</h3> in class: <h3>'.$class_name.'</h3> not found.');
		}
	}

	public function get_controller()
	{
		return $this->controller_object;
	}

	public function set_model_object($module_name)
	{
		$class_file = APP_DIR . 'model' . '/' . $module_name . '.php';

		if (file_exists($class_file))
		{
			include $class_file;

			$class_name = ucfirst($module_name) . '_Model';

			if (class_exists($class_name))
			{
				$this->model_object = new $class_name($this->get_dbc());
			}
			else
			{
				die ('Class: <h3>'.$class_name.'</h3> not found.');
			}
		}
		else
		{
			die ('File: <h3>'.$class_file.'</h3> not found.');
		}
	}

	public function get_model_object()
	{
		return $this->model_object;
	}

	public function set_view_object($module_name)
	{
		$class_file = APP_DIR . 'view' . '/' . $module_name . '.php';

		if (file_exists($class_file))
		{
			include $class_file;

			$class_name = ucfirst($module_name) . '_View';

			if (class_exists($class_name))
			{
				$this->view_object = new $class_name($this->get_page());
			}
			else
			{
				die ('Class: <h3>'.$class_name.'</h3> not found.');
			}
		}
		else
		{
			die ('File: <h3>'.$class_file.'</h3> not found.');
		}
	}

	public function get_view_object()
	{
		return $this->view_object;
	}

	public function set_page($obj)
	{
		include CLASS_DIR . 'page.php';

		$this->page = new Page($obj);
	}

	public function get_page()
	{
		return $this->page;
	}

	public function set_settings($obj)
	{
		include LIB_DIR . 'settings.php';

		$this->settings = new Settings($obj);
	}

	public function get_settings()
	{
		return $this->settings;
	}

	public function set_user($obj)
	{
		include CLASS_DIR . 'status.php';

		$this->user = new Status($obj);
	}

	public function get_user()
	{
		return $this->user;
	}

	public function set_acl($obj)
	{
		include CLASS_DIR . 'acl.php';

		$this->acl = new AccessControlList($obj);
	}

	public function get_acl()
	{
		return $this->acl;
	}

	public function set_menu($obj)
	{
		include CLASS_DIR . 'menu.php';

		$this->menu = new Menu($obj);
	}

	public function get_menu()
	{
		return $this->menu;
	}
}

?>
