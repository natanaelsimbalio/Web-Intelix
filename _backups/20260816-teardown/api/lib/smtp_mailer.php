<?php
// Cliente SMTP mínimo (STARTTLS + AUTH LOGIN) vía sockets, sin dependencias
// externas — mismo criterio que mercadopago.php: no hay build step en este
// hosting, así que evitamos Composer/PHPMailer y hablamos el protocolo
// directo. Alcanza para mandar mails simples de texto/HTML.

class SmtpException extends RuntimeException {}

function smtp_enviar_mail(string $toEmail, string $toName, string $subject, string $bodyHtml): void {
    $host = SMTP_HOST;
    $port = SMTP_PORT;

    $socket = @stream_socket_client(
        "tcp://$host:$port",
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );
    if (!$socket) {
        throw new SmtpException("No se pudo conectar a $host:$port ($errstr)");
    }

    $leer = function () use ($socket): string {
        $resp = '';
        while (($linea = fgets($socket, 515)) !== false) {
            $resp .= $linea;
            if (isset($linea[3]) && $linea[3] === ' ') break;
        }
        return $resp;
    };

    $escribir = function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    $esperar = function (array $codigosOk, string $paso) use ($leer): string {
        $resp = $leer();
        $codigo = (int)substr($resp, 0, 3);
        if (!in_array($codigo, $codigosOk, true)) {
            throw new SmtpException("SMTP falló en $paso: $resp");
        }
        return $resp;
    };

    $esperar([220], 'conexión');

    $escribir('EHLO ' . parse_url(SITE_URL, PHP_URL_HOST));
    $esperar([250], 'EHLO');

    if (SMTP_SECURE === 'tls') {
        $escribir('STARTTLS');
        $esperar([220], 'STARTTLS');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new SmtpException('No se pudo iniciar TLS');
        }
        $escribir('EHLO ' . parse_url(SITE_URL, PHP_URL_HOST));
        $esperar([250], 'EHLO post-TLS');
    }

    $escribir('AUTH LOGIN');
    $esperar([334], 'AUTH LOGIN');
    $escribir(base64_encode(SMTP_USER));
    $esperar([334], 'usuario');
    $escribir(base64_encode(SMTP_PASS));
    $esperar([235], 'contraseña');

    $escribir('MAIL FROM:<' . SMTP_FROM . '>');
    $esperar([250], 'MAIL FROM');
    $escribir('RCPT TO:<' . $toEmail . '>');
    $esperar([250, 251], 'RCPT TO');

    $escribir('DATA');
    $esperar([354], 'DATA');

    $fecha = date('r');
    $boundary = 'intelix-' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHeader = '=?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?= <' . SMTP_FROM . '>';
    $toHeader = ($toName !== '' ? '=?UTF-8?B?' . base64_encode($toName) . '?= ' : '') . '<' . $toEmail . '>';

    $mensaje = "Date: $fecha\r\n"
        . "From: $fromHeader\r\n"
        . "To: $toHeader\r\n"
        . "Subject: $encodedSubject\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "\r\n"
        . $bodyHtml . "\r\n";

    // Byte-stuffing: una línea que empieza con "." sola indica fin de DATA.
    $mensaje = preg_replace('/\n\./', "\n..", $mensaje);

    $escribir($mensaje . "\r\n.");
    $esperar([250], 'envío del mensaje');

    $escribir('QUIT');
    fclose($socket);
}
