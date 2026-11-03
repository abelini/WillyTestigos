<?php

declare(strict_types=1);

namespace Willy\Service;

use PHPMailer\PHPMailer\PHPMailer;
use SplFileInfo;
use Throwable;
use Willy\Exception\DeliveryException;
use Willy\Support\MailConfig;

use function explode;
use function trim;

final class MailSender
{
    public function send(MailConfig $config, string $pdfPath, string $subject, string $body): void
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config->host;
            $mail->Port = $config->port;
            $mail->SMTPAuth = true;
            $mail->Username = $config->user;
            $mail->Password = $config->pass;
            $mail->CharSet = 'UTF-8';

            if ($config->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($config->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($config->from, $config->fromName);

            foreach (explode(',', $config->to) as $recipient) {
                $address = trim($recipient);

                if ($address === '') {
                    continue;
                }

                $mail->addAddress($address);
            }

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->addAttachment($pdfPath, new SplFileInfo($pdfPath)->getBasename(), 'base64', 'application/pdf');
            $mail->send();
        } catch (Throwable $exception) {
            throw new DeliveryException('No se pudo enviar el correo: ' . $exception->getMessage());
        }
    }
}
