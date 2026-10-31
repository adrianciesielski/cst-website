<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function respond(int $status, bool $ok, string $message): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Formularz przyjmuje tylko wysłane wiadomości.');
}

// Honeypot: boty często uzupełniają ukryte pole. Zwróć pozorne powodzenie bez wysyłania.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Dziękujemy. Wiadomość została wysłana.');
}

$name = trim((string)($_POST['name'] ?? ''));
$reply = trim((string)($_POST['reply'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || strlen($name) > 360 || $reply === '' || strlen($reply) > 180 || $message === '' || strlen($message) > 15000) {
    respond(422, false, 'Uzupełnij wymagane pola i sprawdź długość wiadomości.');
}

$replyIsEmail = filter_var($reply, FILTER_VALIDATE_EMAIL) !== false;
$replyIsPhone = preg_match('/^[+0-9().\s-]{7,30}$/u', $reply) === 1;
if (!$replyIsEmail && !$replyIsPhone) {
    respond(422, false, 'Wpisz poprawny adres e-mail albo numer telefonu.');
}

$recipient = 'kontakt@c-st.pl';
$subject = '=?UTF-8?B?' . base64_encode('Nowe zapytanie ze strony C-ST') . '?=';
$body = "Wiadomość z formularza strony C-ST\n\n"
    . "Imię: {$name}\n"
    . "Telefon lub e-mail: {$reply}\n\n"
    . "Treść wiadomości:\n{$message}\n";

$headers = [
    'From: C-ST <kontakt@c-st.pl>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];
if ($replyIsEmail) {
    $headers[] = 'Reply-To: ' . $reply;
}

// home.pl wymaga nadawcy obsługiwanego przez ten sam hosting.
$sent = @mail($recipient, $subject, $body, implode("\r\n", $headers), '-f kontakt@c-st.pl');
if (!$sent) {
    error_log('C-ST contact form: PHP mail() returned false.');
    respond(500, false, 'Nie udało się wysłać wiadomości. Zadzwoń pod 667 666 844 lub napisz na kontakt@c-st.pl.');
}

respond(200, true, 'Dziękujemy. Wiadomość została wysłana.');
