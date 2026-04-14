<?php
// =============================================================
// PHPMailer-Factory – Wiederverwendbare SMTP-Konfiguration
// =============================================================
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->load();

$mailer = new PHPMailer(true);
$mailer->isSMTP();
$mailer->Host       = $_ENV['SMTP_HOST'];
$mailer->Port       = (int)$_ENV['SMTP_PORT'];
$mailer->SMTPAuth   = true;
$mailer->Username   = $_ENV['SMTP_USER'];
$mailer->Password   = $_ENV['SMTP_PASS'];
$mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mailer->CharSet    = 'UTF-8';

$mailer->setFrom('kontakt@fpv-spots-germany.de', 'FPV Spots Germany');
