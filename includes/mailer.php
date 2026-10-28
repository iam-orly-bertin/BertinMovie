<?php
// =========================================================
// includes/mailer.php - Notification par email (critères 33 et 37)
// Envoie un email à tous les utilisateurs quand l'admin ajoute un film.
// Utilise PHPMailer + Gmail SMTP, comme sendOTP() (identifiants dans config.php).
// =========================================================

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Charge PHPMailer (dossier vendor/) seulement au moment d'envoyer un email.
 * Si vendor/ n'est pas sur le serveur, on renvoie false au lieu de provoquer une erreur 500.
 */
function bm_phpmailer_dispo()
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    $ok = class_exists('PHPMailer\\PHPMailer\\PHPMailer')
        && defined('SMTP_USER') && defined('SMTP_PASS');
    if (!$ok) {
        error_log("mailer.php : PHPMailer (vendor/) ou SMTP_USER/SMTP_PASS (config.php) introuvable");
    }
    return $ok;
}

// Nombre maximum de destinataires par ajout (évite une page trop lente / limite Gmail)
if (!defined('NOTIF_MAX_DESTINATAIRES')) {
    define('NOTIF_MAX_DESTINATAIRES', 50);
}

/**
 * Envoie "Nouveau film disponible" à tous les utilisateurs (role = 'user').
 * Ne lève jamais d'exception : un échec d'envoi ne doit pas bloquer l'ajout du film.
 *
 * @return array ['envoyes' => int, 'echecs' => int]
 */
function envoyerNotificationFilm(PDO $pdo, $film_id, $nom_film, $titre = '')
{
    $resultat = ['envoyes' => 0, 'echecs' => 0];

    if (!bm_phpmailer_dispo()) {
        return $resultat;
    }

    // 1. Destinataires (requête préparée, admins exclus)
    try {
        $stmt = $pdo->prepare(
            "SELECT email, prenom FROM utilisateur
             WHERE role = 'user' AND email IS NOT NULL AND email <> ''
             LIMIT " . (int) NOTIF_MAX_DESTINATAIRES
        );
        $stmt->execute();
        $destinataires = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Notification film - lecture utilisateurs : " . $e->getMessage());
        return $resultat;
    }

    if (!$destinataires) {
        return $resultat;
    }

    $lien = defined('BASE_URL') ? BASE_URL : '';
    $nom_h = htmlspecialchars($nom_film, ENT_QUOTES, 'UTF-8');
    $titre_h = htmlspecialchars($titre !== '' ? $titre : $nom_film, ENT_QUOTES, 'UTF-8');
    $lien_h = htmlspecialchars($lien, ENT_QUOTES, 'UTF-8');

    // 2. Une seule connexion SMTP réutilisée pour tous les envois
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->SMTPKeepAlive = true;
        $mail->Timeout = 15;
        $mail->setFrom(SMTP_USER, 'BERTIN MOVIE');
        $mail->addReplyTo(SMTP_USER, 'BERTIN MOVIE');
        $mail->isHTML(true);
        $mail->Subject = '🎬 Nouveau film sur BERTIN MOVIE : ' . $nom_film;
    } catch (Throwable $e) {
        error_log("Notification film - configuration SMTP : " . $e->getMessage());
        return $resultat;
    }

    // 3. Un email personnalisé par utilisateur
    foreach ($destinataires as $d) {
        try {
            $prenom = htmlspecialchars($d['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
            $bonjour = $prenom !== '' ? "Bonjour $prenom," : "Bonjour,";

            $mail->clearAddresses();
            $mail->addAddress($d['email']);

            $mail->Body = '
            <div style="font-family:Arial,Helvetica,sans-serif;background:#f0f4ff;padding:24px;">
              <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
                <h2 style="margin:0 0 4px;color:#0f172a;">BERTIN <span style="color:#3b82f6;">MOVIE</span></h2>
                <p style="color:#64748b;font-size:13px;margin:0 0 20px;">Nouveau film disponible</p>
                <p style="color:#334155;font-size:15px;">' . $bonjour . '</p>
                <p style="color:#334155;font-size:15px;">Un nouveau film vient d\'être ajouté sur la plateforme :</p>
                <div style="background:#eff6ff;border-radius:12px;padding:16px;margin:16px 0;text-align:center;">
                  <div style="font-size:20px;font-weight:bold;color:#1d4ed8;">' . $nom_h . '</div>
                  <div style="font-size:13px;color:#64748b;margin-top:4px;">' . $titre_h . '</div>
                </div>
                <p style="text-align:center;margin:24px 0;">
                  <a href="' . $lien_h . '" style="background:#3b82f6;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:10px;font-weight:bold;">Voir le film</a>
                </p>
                <p style="color:#94a3b8;font-size:12px;">Vous recevez cet email car vous avez un compte BERTIN MOVIE.</p>
              </div>
            </div>';
            $mail->AltBody = "BERTIN MOVIE\n\n" . strip_tags($bonjour) . "\n\nUn nouveau film vient d'être ajouté : $nom_film\n\nConnectez-vous : $lien";

            $mail->send();
            $resultat['envoyes']++;
        } catch (Throwable $e) {
            $resultat['echecs']++;
            error_log("Notification film - échec pour " . ($d['email'] ?? '?') . " : " . $mail->ErrorInfo);
        }
    }

    $mail->smtpClose();
    return $resultat;
}


/**
 * Envoie le code OTP (6 chiffres) par email.
 * Utilisé par creation_compte.php ($contexte = 'inscription') et mot_de_passe_oublie.php ($contexte = 'reset').
 * En cas d'échec : écrit l'erreur dans error_log et dans $_SESSION['mail_error'], puis renvoie false.
 *
 * @return bool true si l'email est parti
 */
function sendOTP($email, $nom, $otp, $contexte = 'inscription')
{
    if (!bm_phpmailer_dispo()) {
        $_SESSION['mail_error'] = "PHPMailer introuvable (dossier vendor/) ou SMTP_USER/SMTP_PASS absents de config.php";
        return false;
    }
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(SMTP_USER, 'BERTIN MOVIE');
        $mail->addReplyTo(SMTP_USER, 'BERTIN MOVIE');
        $mail->addAddress($email, $nom);

        $phrase = ($contexte === 'reset')
            ? 'Vous avez demandé la réinitialisation de votre mot de passe.'
            : 'Merci de créer un compte sur BERTIN MOVIE !';

        $mail->isHTML(true);
        $mail->Subject = '🔐 Code OTP - BERTIN MOVIE';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; background: #f1f5f9; color: #0f172a; padding: 20px; margin: 0;">
            <div style="max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <h2 style="text-align: center; color: #3b82f6; margin-bottom: 20px;">🎬 BERTIN MOVIE</h2>
                <p style="font-size: 16px;">Bonjour <strong>' . htmlspecialchars($nom) . '</strong>,</p>
                <p style="color: #475569;">' . $phrase . '</p>
                <p style="color: #475569;">Voici votre code de vérification :</p>
                <div style="font-size: 36px; font-weight: 700; color: #3b82f6; text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px; margin: 20px 0; letter-spacing: 10px; font-family: monospace;">
                    ' . htmlspecialchars((string) $otp) . '
                </div>
                <p style="color: #94a3b8; font-size: 13px;">Ce code expire dans 10 minutes.</p>
                <p style="color: #94a3b8; font-size: 13px;">Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>
                <div style="text-align: center; color: #94a3b8; font-size: 12px; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    &copy; 2026 BERTIN MOVIE - Tous droits réservés
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "BERTIN MOVIE - Code OTP\n\nBonjour $nom,\n\nVotre code de vérification est : $otp\n\nCe code expire dans 10 minutes.\n\n© 2026 BERTIN MOVIE";

        $mail->send();
        return true;

    } catch (Throwable $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}