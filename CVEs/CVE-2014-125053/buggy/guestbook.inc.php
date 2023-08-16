<?php
if (!defined('GUESTBOOK_PATH')) die('Hacking attempt!');

global $user;

include(GUESTBOOK_PATH . 'include/functions.inc.php');

$url_self = empty($page['start']) ? GUESTBOOK_URL : add_url_params(GUESTBOOK_URL, array('start' => $page['start']));

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+
if (isset($_GET['action']))
{
  switch ($_GET['action'])
  {
    case 'edit_comment':
    {
      include_once(GUESTBOOK_PATH.'include/functions_comment.inc.php');
      
      check_input_parameter('comment_to_edit', $_GET, false, PATTERN_ID);
      $author_id = get_comment_author_id_guestbook($_GET['comment_to_edit']);

      if (can_manage_comment('edit', $author_id))
      {
        if (!empty($_POST['content']))
        {
          check_pwg_token();
          $comment_action = update_user_comment_guestbook(
            array(
              'comment_id' => $_GET['comment_to_edit'],
              'content' => $_POST['content']
              ),
            $_POST['key']
            );

          $perform_redirect = false;
          switch ($comment_action)
          {
            case 'moderate':
              $_SESSION['page_infos'][] = l10n('An administrator must authorize your comment before it is visible.');
            case 'validate':
              $_SESSION['page_infos'][] = l10n('Your comment has been registered');
              $perform_redirect = true;
              break;
            case 'reject':
              $_SESSION['page_errors'][] = l10n('Your comment has NOT been registered because it did not pass the validation rules');
              $perform_redirect = true;
              break;
            default:
              trigger_error('Invalid comment action '.$comment_action, E_USER_WARNING);
          }

          if ($perform_redirect)
          {
            redirect($url_self);
          }
          unset($_POST['content']);
        }
        else
        {
          $edit_comment = $_GET['comment_to_edit'];
        }
      }
      break;
    }
    case 'delete_comment' :
    {
      check_pwg_token();

      include_once(GUESTBOOK_PATH.'include/functions_comment.inc.php');

      check_input_parameter('comment_to_delete', $_GET, false, PATTERN_ID);

      $author_id = get_comment_author_id_guestbook($_GET['comment_to_delete']);

      if (can_manage_comment('delete', $author_id))
      {
        delete_user_comment_guestbook($_GET['comment_to_delete']);
      }

      redirect($url_self);
    }
    case 'validate_comment' :
    {
      check_pwg_token();

      include_once(GUESTBOOK_PATH.'include/functions_comment.inc.php');

      check_input_parameter('comment_to_validate', $_GET, false, PATTERN_ID);

      $author_id = get_comment_author_id_guestbook($_GET['comment_to_validate']);

      if (can_manage_comment('validate', $author_id))
      {
        validate_user_comment_guestbook($_GET['comment_to_validate']);
      }

      redirect($url_self);
    }

  }
}

// +-----------------------------------------------------------------------+
// |                                add comment                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['content']) && (!is_a_guest() || $conf['guestbook']['guest_can_add']))
{
  $comm = array(
    'author' => trim(@$_POST['author']),
    'email' => trim(@$_POST['email']),
    'content' => trim($_POST['content']),
    'website' => trim($_POST['website']),
    'rate' => @$_POST['score'],
   );

  include_once(GUESTBOOK_PATH.'include/functions_comment.inc.php');

  $comment_action = insert_user_comment_guestbook($comm, @$_POST['key']);

  switch ($comment_action)
  {
    case 'moderate':
      $page['infos'][] = l10n('An administrator must authorize your comment before it is visible.');
    case 'validate':
      $page['infos'][] = l10n('Your comment has been registered');
      break;
    case 'reject':
      set_status_header(403);
      $template->assign('GB_OPEN', true);
      $page['errors'][] = l10n('Your comment has NOT been registered because it did not pass the validation rules');
      break;
    default:
      trigger_error('Invalid comment action '.$comment_action, E_USER_WARNING);
  }
}

// +-----------------------------------------------------------------------+
// |                                display comments                       |
// +-----------------------------------------------------------------------+
$where_clauses = array('1=1');
if (!is_admin())
{
  $where_clauses[] = 'validated = \'true\'';
}
if (isset($_GET['comment_id']))
{
  $where_clauses[] = 'com.id = '.pwg_db_real_escape_string($_GET['comment_id']);
}

// number of comments for this picture
$query = '
SELECT
    COUNT(*) AS nb_comments
  FROM '.GUESTBOOK_TABLE.' as com
  WHERE '.implode(' AND ', $where_clauses).'
;';
$row = pwg_db_fetch_assoc(pwg_query($query));

// navigation bar creation
$page['start'] = 0;
if (isset($_GET['start']))
{
  $page['start'] = $_GET['start'];
}

$navigation_bar = create_navigation_bar(
  GUESTBOOK_URL,
  $row['nb_comments'],
  $page['start'],
  $conf['guestbook']['nb_comment_page'],
  false
  );

$template->assign(array(
  'COMMENT_COUNT' => $row['nb_comments'],
  'navbar' => $navigation_bar,
  ));
  
if ($row['nb_comments'] > 0)
{
  $query = '
SELECT
    com.id,
    author,
    author_id,
    '.$conf['user_fields']['username'].' AS username,
    date,
    content,
    validated,
    website,
    rate,
    email
  FROM '.GUESTBOOK_TABLE.' AS com
  LEFT JOIN '.USERS_TABLE.' AS u
    ON u.'.$conf['user_fields']['id'].' = author_id
  WHERE '.implode(' AND ', $where_clauses).'
  ORDER BY date DESC
  LIMIT '.$conf['guestbook']['nb_comment_page'].' OFFSET '.$page['start'].'
;';
  $result = pwg_query( $query );

  while ($row = pwg_db_fetch_assoc($result))
  {
    if (!empty($row['author']))
    {
      $author = $row['author'];
      if ($author == 'guest')
      {
        $author = l10n('guest');
      }
    }
    else
    {
      $author = stripslashes($row['username']);
    }

    $tpl_comment = array(
      'ID' => $row['id'],
      'AUTHOR' => trigger_change('render_comment_author', $author),
      'DATE' => format_date($row['date'], true),
      'CONTENT' => trigger_change('render_comment_content', $row['content'], 'guestbook'),
      'WEBSITE' => $row['website'],
      );
      
    if ($conf['guestbook']['activate_rating'])
    {
      $tpl_comment['STARS'] = get_stars($row['rate'], get_root_url().GUESTBOOK_PATH .'template/jquery.raty/');
    }
      
    if (is_admin() and !empty($row['email']))
    {
      $tpl_comment['EMAIL'] = $row['email'];
    }

    if (can_manage_comment('delete', $row['author_id']))
    {
      $tpl_comment['U_DELETE'] = add_url_params(
        $url_self,
        array(
          'action'=>'delete_comment',
          'comment_to_delete'=>$row['id'],
          'pwg_token' => get_pwg_token(),
          )
        );
    }
    if (can_manage_comment('edit', $row['author_id']))
    {
      $tpl_comment['U_EDIT'] = add_url_params(
        $url_self,
        array(
          'action'=>'edit_comment',
          'comment_to_edit'=>$row['id'],
          )
        );
        if (isset($edit_comment) and ($row['id'] == $edit_comment))
        {
          $tpl_comment['IN_EDIT'] = true;
          $tpl_comment['KEY'] = get_ephemeral_key(2);
          $tpl_comment['CONTENT'] = $row['content'];
          $tpl_comment['PWG_TOKEN'] = get_pwg_token();
          $tpl_comment['U_CANCEL'] = $url_self;
        }
    }
    if (is_admin())
    {
      if ($row['validated'] != 'true')
      {
        $tpl_comment['U_VALIDATE'] = add_url_params(
          $url_self,
          array(
            'action' => 'validate_comment',
            'comment_to_validate' => $row['id'],
            'pwg_token' => get_pwg_token(),
            )
          );
      }
    }
    $template->append('comments', $tpl_comment);
  }
}

$show_add_comment_form = !is_a_guest() || $conf['guestbook']['guest_can_add'];
if (isset($edit_comment))
{
  $show_add_comment_form = false;
}

if ($show_add_comment_form)
{
  foreach (array('content','author','website','email') as $el)
  {
    ${$el} = '';
    if ('reject'===@$comment_action and !empty($comm[$el]))
    {
      ${$el} = htmlspecialchars( stripslashes($comm[$el]) );
    }
  }
  if (is_classic_user())
  {
    $author = $user['username'];
    $email = $user['email'];
  }
  if (empty($conf['comments_email_mandatory'])) // < 2.5 compatibility
  {
    $conf['comments_email_mandatory'] = false;
  }

  $template->assign('comment_add',
    array(
      'F_ACTION' => $url_self,
      'KEY' => get_ephemeral_key(3),
      'CONTENT' => $content,
      'IS_LOGGED' => is_classic_user(),
      'AUTHOR' => $author,
      'WEBSITE' => $website,
      'EMAIL' => $email,
      'ACTIVATE_RATING' => $conf['guestbook']['activate_rating'],
      'EMAIL_MANDATORY' => $conf['comments_email_mandatory'],
    ));
}

$template->assign(array(
  'GUESTBOOK_PATH' => GUESTBOOK_PATH,
  'ABS_GUESTBOOK_PATH' => realpath(GUESTBOOK_PATH) . '/',
  ));

$template->set_filename('guestbook', realpath(GUESTBOOK_PATH . 'template/guestbook.tpl'));
$template->assign_var_from_handle('CONTENT', 'guestbook');
