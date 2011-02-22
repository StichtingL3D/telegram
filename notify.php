<?php
/*------------------------------------------------------------------------------
	AW telegram notifyer. Send emails when you receive a telegram.
	L3D foundation. Lode Claassen <lode@l3d.nl>. February 2011.
------------------------------------------------------------------------------*/

/*--- start up ---*/
define('NL', "\n");
define('PATH', "/home/tools/crons/telegram/");
$last_id = ini('last_id');
$aw = aw_connect();
$log_mail = '';

/*--- debug settings ---*/
define('DEBUG', false);
define('DEBUG_ADDRESS', 'telegram@l3d.nl');
if (DEBUG) {
	error_reporting(-1);
	ini_set('display_errors', 1);
	define('NLdbg', "<br>\n");
	function _print_r($a) { echo '<pre>'; print_r($a); echo '</pre>'; }
	$log_mail .= log_message('DEBUG is on; last_id is not updated, mails go to '.DEBUG_ADDRESS);
}
else {
	error_reporting(0);
	ini_set('display_errors', 0);
}

/*--- get waiting telegrams ---*/
$sql_last_id = aw_secure($aw, $last_id);
$sql_query = "SELECT * FROM `awu_telegram` WHERE `ID` > '$sql_last_id' ORDER BY `ID` ASC;";
$telegrams = aw_query($aw, $sql_query, 'array');

$new_last_id = 0;
foreach ($telegrams as $gram) {
	/*--- get sender and receiver info ---*/
	$sql_sender = aw_secure($aw, $gram['From']);
	$sql_query = "SELECT `Name`, `Email` FROM `awu_citizen` WHERE `ID` = '$sql_sender';";
	$sender = aw_query($aw, $sql_query, 'row');
	
	$sql_receiver = aw_secure($aw, $gram['Citizen']);
	$sql_query = "SELECT `Name`, `Email` FROM `awu_citizen` WHERE `ID` = '$sql_receiver';";
	$receiver = aw_query($aw, $sql_query, 'row');
	
	/*--- check prerequisites ---*/
	$sql_receiver_email = aw_secure($aw, $receiver['Email']);
	$sql_query = "SELECT `ID` FROM `awu_citizen` WHERE `Email` = '$sql_receiver_email' LIMIT 2;";
	$possible_receivers = aw_query($aw, $sql_query);
	$multiple_receivers = ($possible_receivers->num_rows > 1) ? true : false;
	
	/*--- process telegram sending ---*/
	if ($multiple_receivers && strpos($receiver['Email'], ini('skip_multiple_from'))) {
		// skip multiple-receiver if from this domain
		$from = $gram['From'];
		$to = $gram['Citizen'];
		$name = $receiver['Name'];
		$email = $receiver['Email'];
		$log_message  = "SKIPPED multi-receiver from #$from to #$to ($name <$email>)";
		$log_mail .= log_message($log_message);
	}
	elseif ($multiple_receivers) {
		// hide telegram content with multiple receivers
		mail_notify($receiver);
	}
	else {
		// send full telegram
		mail_full($sender, $receiver, $gram['Message']);
	}
	
	$new_last_id = $gram['ID'];
}

/*--- wrap it up ---*/
if (!DEBUG) ini('last_id', $new_last_id);
$log_mail .= log_message('DONE');

if (strpos($log_mail, 'FAILED')) {
	@phpmailer_send($to=false, $subject='Telegram notifier: error!', $body=$log_mail);
}
elseif (DEBUG) {
	@phpmailer_send($to=false, $subject='Telegram notifier - debug log', $body=$log_mail);
}
elseif (date('Hi', time()) == '0000') {
	$full_log = file(PATH.'log');
	$custom_log = '';
	$date_last_day = date('D m/d/y', time()-3600);
	
	// get all interesting logs of the last day
	foreach ($full_log as $message) {
		$is_last_day = (strpos($message, $date_last_day) === 0) ? true : false;
		if ($is_last_day == false || strpos($message, 'DONE')) {
			continue;
		}
		$custom_log .= $message;
	}
	// add last heartbeat
	$custom_log .= end($full_log);
	
	@phpmailer_send($to=false, $subject='Telegram notifier - daily log', $body=$custom_log);
}

/*------------------------------------------------------------------------------
	Functions for usage above.
------------------------------------------------------------------------------*/

/*--- AW DB: connect to the database ---*/
function aw_connect() {
	$host = ini('awdb_host');
	$user = ini('awdb_user');
	$pass = ini('awdb_pass');
	$name = ini('awdb_name');
	
	$connection = new mysqli($host, $user, base64_decode($pass), $name);
	return $connection;
}

/*--- AW DB: escape values used in queries ---*/
function aw_secure($connection, $value) {
	return $connection->real_escape_string($value);
}

/*--- AW DB: query ---*/
// $result_type = field | row | field
function aw_query($connection, $sql, $result_type=false) {
	$result = $connection->query($sql);
	
	if ($result_type != false && stripos($sql, 'SELECT ') === 0) {
		if ($result_type == 'array') {
			$array = array();
			while ($row = $result->fetch_assoc()) {
				$array[] = $row;
			}
			$result = $array;
		}
		elseif ($result_type == 'row') {
			$result = $result->fetch_assoc();
		}
		elseif ($result_type == 'field') {
			$result = $result->fetch_array(MYSQLI_NUM);
			$result = $result[0];
		}
	}
	
	return $result;
}

/*--- Mail: send full telegram notify ---*/
function mail_full($from, $to, $telegram) {
	if (DEBUG) $to['Email'] = DEBUG_ADDRESS;
	global $log_mail;
	
	// prepare
	$subject = 'Telegram van '.$from['Name'].'!';
	$body_template = file_get_contents(PATH.'template_full.txt');
	$search = array( '{TO_NAME}', '{FROM_NAME}', '{TELEGRAM}' );
	$replace = array( $to['Name'], $from['Name'], $telegram );
	$body = str_replace($search, $replace, $body_template);
	
	// send
	$result = phpmailer_send($to, $subject, $body);
	if ($result !== true) {
		$log_message = 'FAILED SENDING full telegram email to '.$to['Email'].'.'
			.' phpmailer returned: "'.$result.'"';
		$log_mail .= log_message($log_message);
	}
	else {
		$log_message = 'OK sent full telegram email to '.$to['Email'].'.';
		$log_mail .= log_message($log_message);
	}
}

/*--- Mail: send notify of telegram existence ---*/
function mail_notify($to) {
	if (DEBUG) $to['Email'] = DEBUG_ADDRESS;
	global $log_mail;
	
	// prepare
	$subject = 'Je hebt een telegram in L3Daw';
	$body_template = file_get_contents(PATH.'template_notify.txt');
	$body = str_replace('{TO_NAME}', $to['Name'], $body_template);
	
	// send
	$result = phpmailer_send($to, $subject, $body);
	if ($result !== true) {
		$log_message = 'FAILED SENDING notify-only telegram email to '.$to['Email'].'.'
			.' phpmailer returned: "'.$result.'"';
		$log_mail .= log_message($log_message);
	}
	else {
		$log_message = 'OK sent notify-only telegram email to '.$to['Email'].'.';
		$log_mail .= log_message($log_message);
	}
}

/*--- send mails using phpmailer ---*/
function phpmailer_send($to, $subject, $content) {
	include_once(PATH.'phpmailer_v5.1/class.phpmailer.php');
	$phpmailer = new PHPMailer(true);
	
	if ($to == false) {
		$to = array(
			'Name' => ini('mail_name'),
			'Email' => ini('mail_user'),
		);
	}
	
	try {
		// settings
		$phpmailer->IsSMTP();
		if (DEBUG) $phpmailer->SMTPDebug = 2;
		$phpmailer->SMTPAuth = ini('mail_auth');
		$phpmailer->SMTPSecure = ini('mail_secu');
		$phpmailer->Host = ini('mail_host');
		$phpmailer->Port = ini('mail_port');
		$phpmailer->Username = ini('mail_user');
		$phpmailer->Password = base64_decode(ini('mail_pass'));
		
		// prepare
		$phpmailer->SetFrom(ini('mail_user'), ini('mail_name'));
		$phpmailer->AddAddress($to['Email'], $to['Name']);
		$phpmailer->Subject = $subject;
		$phpmailer->Body = $content;
		
		// send away
		$phpmailer->Send();
		return true;
	}
	catch (phpmailerException $e) {
		return $phpmailer->ErrorInfo;
	}
}

/*--- log for maintenance ---*/
function log_message($message) {
	$message = date('D m/d/y H:i:s', time()).'   '.trim($message).NL;
	@file_put_contents(PATH.'log', $message, FILE_APPEND);
	
	return $message;
}

/*--- read and write settings ---*/
function ini($key, $value=false) {
	$settings = parse_ini_file(PATH.'settings.ini');
	global $log_mail;
	
	if ($value == false) {
		return $settings[$key];
	}
	else {
		$new_settings = '';
		foreach ($settings as $loop_key => $loop_value) {
			if ($key == $loop_key) {
				$loop_value = $value;
			}
			$new_settings .= $loop_key.' = "'.$loop_value.'"'.NL;
		}
		$new_settings = trim($new_settings);
		
		$result = file_put_contents(PATH.'settings.ini', $new_settings);
		if ($result == false) {
			$log_message = 'FAILED SETTING new '.$key.' to "'.$value.'".';
			$log_mail .= log_message($log_message);
		}
	}
}

?>