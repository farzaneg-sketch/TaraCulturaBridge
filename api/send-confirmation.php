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

function sendViaSmtp2goApi($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody) {
    $payload = [
        'sender' => "{$fromName} <{$fromEmail}>",
        'to' => ["<{$to}>"],
        'subject' => $subject,
        'html_body' => $htmlBody,
        'text_body' => trim(strip_tags($htmlBody)),
    ];

    $ch = curl_init('https://api.smtp2go.com/v3/email/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Smtp2go-Api-Key: ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $responseBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [false, "Request failed: $curlErr"];
    }

    $decoded = json_decode($responseBody, true);
    $failed = $decoded['data']['failed'] ?? null;
    $emailId = $decoded['data']['email_id'] ?? null;

    if ($httpCode === 200 && $failed === 0 && $emailId) {
        return [true, $emailId];
    }

    return [false, "HTTP $httpCode: $responseBody"];
}

list($ok, $info) = sendViaSmtp2goApi(
    SMTP2GO_API_KEY,
    FROM_EMAIL,
    FROM_NAME,
    $to,
    $subject,
    $bodyHtml
);

if ($ok) {
    echo json_encode(['status' => 'ok', 'email_id' => $info]);
} else {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => $info]);
}
