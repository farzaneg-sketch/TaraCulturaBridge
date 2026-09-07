<?php
// Sends an event registration confirmation email via SMTP2GO.
// Credentials live in config.php on the server only (not committed to git).
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://tarabridge.ca');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$to = trim($input['to'] ?? '');
$parentName = trim($input['parentName'] ?? '');
$kidName = trim($input['kidName'] ?? '');
$eventTitle = trim($input['eventTitle'] ?? '');
$eventDate = trim($input['eventDate'] ?? '');
$eventTime = trim($input['eventTime'] ?? '');
$eventLoc = trim($input['eventLoc'] ?? '');
$lang = ($input['lang'] ?? 'en') === 'fa' ? 'fa' : 'en';

if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL) || $eventTitle === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields']);
    exit;
}

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($lang === 'fa') {
    $subject = "تأیید ثبت‌نام: {$eventTitle}";
    $greeting = $parentName ? "سلام " . h($parentName) . "،" : "سلام،";
    $rows = "";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>رویداد:</td><td style='padding:4px 8px;'>" . h($eventTitle) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>تاریخ:</td><td style='padding:4px 8px;'>" . h($eventDate) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>ساعت:</td><td style='padding:4px 8px;'>" . h($eventTime) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>مکان:</td><td style='padding:4px 8px;'>" . h($eventLoc) . "</td></tr>";
    if ($kidName !== '') {
        $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>کودک:</td><td style='padding:4px 8px;'>" . h($kidName) . "</td></tr>";
    }
    $bodyHtml = "<div dir='rtl' style='font-family:Tahoma,Arial,sans-serif;line-height:1.8;color:#222;'>"
        . "<p>{$greeting}</p>"
        . "<p>ثبت‌نام شما برای رویداد زیر با موفقیت انجام شد:</p>"
        . "<table style='border-collapse:collapse;margin:12px 0;'>{$rows}</table>"
        . "<p>مشتاقانه منتظر دیدار شما هستیم!</p>"
        . "<p style='color:#777;font-size:13px;margin-top:24px;'>انجمن پل فرهنگی تارا</p>"
        . "</div>";
} else {
    $subject = "Registration Confirmed: {$eventTitle}";
    $greeting = $parentName ? "Hi " . h($parentName) . "," : "Hi,";
    $rows = "";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>Event:</td><td style='padding:4px 8px;'>" . h($eventTitle) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>Date:</td><td style='padding:4px 8px;'>" . h($eventDate) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>Time:</td><td style='padding:4px 8px;'>" . h($eventTime) . "</td></tr>";
    $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>Location:</td><td style='padding:4px 8px;'>" . h($eventLoc) . "</td></tr>";
    if ($kidName !== '') {
        $rows .= "<tr><td style='padding:4px 8px;font-weight:bold;'>Child:</td><td style='padding:4px 8px;'>" . h($kidName) . "</td></tr>";
    }
    $bodyHtml = "<div style='font-family:Arial,sans-serif;line-height:1.6;color:#222;'>"
        . "<p>{$greeting}</p>"
        . "<p>You're registered for the following event:</p>"
        . "<table style='border-collapse:collapse;margin:12px 0;'>{$rows}</table>"
        . "<p>We look forward to seeing you there!</p>"
        . "<p style='color:#777;font-size:13px;margin-top:24px;'>Tara Cultural Bridge Society</p>"
        . "</div>";
}

function smtpSendMail($host, $port, $user, $pass, $fromEmail, $fromName, $to, $subject, $htmlBody) {
    $errno = 0;
    $errstr = '';
    $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
    if (!$sock) {
        return [false, "Connect failed: $errstr ($errno)"];
    }
    stream_set_timeout($sock, 15);

    $read = function () use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = function ($cmd) use ($sock) {
        fwrite($sock, $cmd . "\r\n");
    };

    $resp = $read();
    if (strpos($resp, '220') !== 0) {
        fclose($sock);
        return [false, "Bad greeting: $resp"];
    }

    $write("EHLO tarabridge.ca");
    $read();

    $write("STARTTLS");
    $resp = $read();
    if (strpos($resp, '220') !== 0) {
        fclose($sock);
        return [false, "STARTTLS failed: $resp"];
    }

    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($sock);
        return [false, "TLS handshake failed"];
    }

    $write("EHLO tarabridge.ca");
    $read();

    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $resp = $read();
    if (strpos($resp, '235') !== 0) {
        fclose($sock);
        return [false, "Auth failed: $resp"];
    }

    $write("MAIL FROM:<{$fromEmail}>");
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($sock);
        return [false, "MAIL FROM failed: $resp"];
    }

    $write("RCPT TO:<{$to}>");
    $resp = $read();
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        fclose($sock);
        return [false, "RCPT TO failed: $resp"];
    }

    $write("DATA");
    $resp = $read();
    if (strpos($resp, '354') !== 0) {
        fclose($sock);
        return [false, "DATA failed: $resp"];
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [];
    $headers[] = "From: {$encodedFromName} <{$fromEmail}>";
    $headers[] = "To: <{$to}>";
    $headers[] = "Subject: {$encodedSubject}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: base64";

    $encodedBody = chunk_split(base64_encode($htmlBody));
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.";
    $write($message);
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($sock);
        return [false, "Message send failed: $resp"];
    }

    $write("QUIT");
    fclose($sock);
    return [true, "OK"];
}

list($ok, $info) = smtpSendMail(
    SMTP_HOST,
    SMTP_PORT,
    SMTP_USER,
    SMTP_PASS,
    FROM_EMAIL,
    FROM_NAME,
    $to,
    $subject,
    $bodyHtml
);

if ($ok) {
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => $info]);
}
