<?php

require 'includes/header.php';

// If user is not an administrator.
if (!allowed('manage_reports')) {
    $_SESSION['notice'] = MESSAGE_PAGE_ACCESS_DENIED;
    header('Location: '.DOMAIN);
    exit('');
}

if ((int) $_GET['handle']) {
    $link->update('reports', array('handled' => 1), 'id='.$link->escape((int) $_GET['handle']));
    $_SESSION['notice'] = 'Report marked as handled.';
    log_mod('handle_report', (int) $_GET['handle']);
    header('Location: '.DOMAIN.'reports');
    die();
}

if ((int) $_GET['topic'] && !$_GET['reply']) {
    $link->update('reports', array('handled' => 1), 'reply=0 AND topic='.$link->escape((int) $_GET['topic']));
    $_SESSION['notice'] = 'Reports marked as handled.';
    log_mod('handle_reports', (int) $_GET['topic']);
    header('Location: '.DOMAIN.'reports');
    die();
}

if ((int) $_GET['topic'] && (int) $_GET['reply']) {
    $link->update('reports', array('handled' => 1), 'reply='.(int) $_GET['reply'].' AND topic='.(int) $_GET['topic']);
    $_SESSION['notice'] = 'Reports marked as handled.';
    log_mod('handle_reports', (int) $_GET['topic'].'#reply_'.(int) $_GET['reply']);
    header('Location: '.DOMAIN.'reports');
    die();
}

$page_title = 'Reports ('.$NUM_REPORTS.')';
$additional_head = '';

if ($NUM_REPORTS > 0) {
    $sql = 'SELECT reports.id, reports.topic, reports.reply, reports.reason, topics.headline, count(reports.id) as times_reported FROM reports, topics WHERE topics.id = reports.topic AND reports.handled = 0 GROUP BY reports.topic, reports.reply ORDER BY reports.id DESC';
    $send = $link->db_exec($sql);
    if ($link->num_rows() > 0) {
        $continue = true;
    }
}

if ($continue) {
    echo '<table>
		<thead>
			<tr>
				<th class="minimal">Link</th>
				<th>First reason</th>
				<th class="minimal">Times reported</th>
				<th class="minimal">Details</th>
				<th class="minimal">Handle</th>
			</tr>
		</thead>
		<tbody>';

    $selecter = true;
    while ($get = mysql_fetch_array($send)) {
        if ($selecter) {
            $class = '';
        } else {
            $class = 'odd';
        }
        $selecter = !$selecter;

        $topiclink = "<a href='".DOMAIN.'topic/'.$get['topic'];
        if ($get['reply']) {
            $topiclink .= '#reply_'.$get['reply'];
        }
        $topiclink .= "'>".$get['headline'].'</a>';

        $report_info = $get['topic'];
        if ($get['reply']) {
            $report_info .= '/'.$get['reply'];
        }

        // Get data.
        echo "<tr class=\"$class\">
		<td class=\"minimal\">".$topiclink.'</td>
		<td>'.htmlspecialchars($get['reason']).'</td>
		<td class="minimal">'.$get['times_reported']."</td>
		<td class=\"minimal\"><a href='".DOMAIN.'show_report/'.$report_info."'>Details</a></td>
		<td class=\"minimal\"><a href='".DOMAIN.'reports/handle/'.$report_info."'>Handle</a></td>
		</tr>";
    }
    echo '</tbody> </table>';
} else {
    echo 'No unhandled reports.';
}

echo '<h2>Past reports</h2>';
    $sql = 'SELECT reports.id, reports.topic, reports.reply, reports.reason, topics.headline, count(reports.id) as times_reported FROM reports, topics WHERE topics.id = reports.topic AND reports.handled = 1 GROUP BY reports.topic, reports.reply ORDER BY reports.id DESC LIMIT 15';
    $send = $link->db_exec($sql);
    if ($link->num_rows() > 0) {
        $continue = true;
    } else {
        $continue = false;
    }

if ($continue) {
    echo '<table>
		<thead>
			<tr>
				<th class="minimal">Link</th>
				<th>First reason</th>
				<th class="minimal">Times reported</th>
				<th class="minimal">Details</th>
			</tr>
		</thead>
		<tbody>';

    $selecter = true;
    while ($get = mysql_fetch_array($send)) {
        if ($selecter) {
            $class = '';
        } else {
            $class = 'odd';
        }
        $selecter = !$selecter;

        $topiclink = "<a href='".DOMAIN.'topic/'.$get['topic'];
        if ($get['reply']) {
            $topiclink .= '#reply_'.$get['reply'];
        }
        $topiclink .= "'>".$get['headline'].'</a>';

        $report_info = $get['topic'];
        if ($get['reply']) {
            $report_info .= '/'.$get['reply'];
        }

        // Get data.
        echo "<tr class=\"$class\">
		<td class=\"minimal\">".$topiclink.'</td>
		<td>'.htmlspecialchars($get['reason']).'</td>
		<td class="minimal">'.$get['times_reported']."</td>
		<td class=\"minimal\"><a href='".DOMAIN.'show_report/'.$report_info."'>Details</a></td>
		</tr>";
    }
    echo '</tbody> </table>';
} else {
    echo 'No past reports.';
}

require 'includes/footer.php';
