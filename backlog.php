<?php
define('NL', "\n");
define('NLdbg', "<br>\n");
define('PATH', "/home/tools/crons/telegram/");

$full_log = file(PATH.'log');
$custom_log = '';
$date_today = date('D m/d/y', time());
echo '<h1>Telegram log from today ('.$date_today.')</h1>';
foreach ($full_log as $message) {
	$is_today = (strpos($message, $date_today) === 0) ? true : false;
	if ($is_today == false || strpos($message, 'DONE')) {
		continue;
	}
	$custom_log .= $message;
}

if (empty($custom_log)) {
	echo '<p><em>no activity for today</em></p>';
}
else {
	echo '<pre>';
	echo $custom_log;
	echo '</pre>';
}

echo '<p style="border-top: 1px solid #AAA; color: #AAA;">Stichting L3D '.date('Y', time()).'</p>';
?>