<?php
$mbox = imap_open("{imap.googlemail.com:465/ssl}", "telegram", base64_decode('dEhlJjNQaGFjcjhmJmczZg=='));

echo "<h1>Mailboxes</h1>\n";
$folders = imap_listmailbox($mbox, "{smtp.googlemail.com:465/ssl}", "*");

if ($folders == false) {
    echo "Call failed<br />\n";
} else {
    foreach ($folders as $val) {
        echo $val . "<br />\n";
    }
}

echo "<h1>Headers in INBOX</h1>\n";
$headers = imap_headers($mbox);

if ($headers == false) {
    echo "Call failed<br />\n";
} else {
    foreach ($headers as $val) {
        echo $val . "<br />\n";
    }
}

imap_close($mbox);
?>
