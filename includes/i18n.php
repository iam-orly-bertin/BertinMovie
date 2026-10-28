<?php
// =============================================
// TRADUCTIONS FR / EN  (pages d'accès : connexion, inscription, mot de passe)
// La langue choisie est mémorisée dans le cookie "bm_lang".
// Pour ajouter un texte : ajoute la même clé dans 'fr' ET dans 'en', puis écris t('ma_cle').
// =============================================

function bm_lang()
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $c = $_COOKIE['bm_lang'] ?? '';
    if ($c === 'fr' || $c === 'en') {
        $lang = $c;
    } else {
        // Première visite : langue du navigateur (anglais si "en", sinon français)
        $al = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'fr', 0, 2));
        $lang = ($al === 'en') ? 'en' : 'fr';
    }
    return $lang;
}

function t($key, ...$args)
{
    global $BM_T;
    $s = $BM_T[bm_lang()][$key] ?? $BM_T['fr'][$key] ?? $key;
    return $args ? vsprintf($s, $args) : $s;
}

$BM_T = [];

// ---------------------------------------------------------------- FRANÇAIS
$BM_T['fr'] = [
    // commun
    'label_email' => 'Email',
    'label_password' => 'Mot de passe',
    'link_back_login' => 'Retour à la connexion',
    'err_pw_short' => 'Le mot de passe doit contenir au moins 6 caractères.',
    'err_wait' => '⏳ Veuillez patienter %d seconde(s) avant de demander un nouveau code.',
    'err_mail_send' => "❌ Erreur lors de l'envoi de l'email.",
    'ui_lang' => 'Langue',
    'ui_theme' => 'Thème clair / sombre',

    // e-mails OTP
    'mail_subject' => '🔐 Code OTP - BERTIN MOVIE',
    'mail_hello' => 'Bonjour',
    'mail_signup_intro' => 'Merci de créer un compte sur BERTIN MOVIE !',
    'mail_reset_intro' => 'Vous avez demandé la réinitialisation de votre mot de passe.',
    'mail_code_label' => 'Voici votre code de vérification :',
    'mail_expire' => 'Ce code expire dans 10 minutes.',
    'mail_ignore' => "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.",
    'mail_rights' => 'Tous droits réservés',
    'mail_alt' => "BERTIN MOVIE - Code OTP\n\nBonjour %1\$s,\n\nVotre code de vérification est : %2\$s\n\nCe code expire dans 10 minutes.\n\n© 2026 BERTIN MOVIE",

    // connexion
    'login_title' => 'BERTIN MOVIE - Connexion',
    'login_subtitle' => 'Plateforme de streaming premium',
    'login_feat1' => 'Films en illimité',
    'login_feat2' => 'Regardez où vous voulez',
    'login_feat3' => 'Créez vos favoris',
    'login_h1' => 'Connexion',
    'login_desc' => 'Connectez-vous à votre espace personnel',
    'login_ph_email' => 'votre@email.com',
    'login_ph_password' => 'Entrez votre mot de passe',
    'login_btn' => 'Se connecter',
    'login_register' => 'Créer un compte',
    'login_forgot' => 'Mot de passe oublié ?',
    'err_timeout' => "Votre session a expiré après une période d'inactivité. Veuillez vous reconnecter.",
    'minute_sg' => 'minute',
    'minute_pl' => 'minutes',
    'attempt_sg' => 'tentative',
    'attempt_pl' => 'tentatives',
    'err_locked' => 'Compte temporairement verrouillé après trop de tentatives échouées. Réessayez dans %s, ou utilisez « Mot de passe oublié ».',
    'err_locked_now' => 'Trop de tentatives échouées : compte verrouillé pendant %d minutes.',
    'err_bad_login' => 'Email ou mot de passe incorrect.',
    'err_bad_login_left' => 'Email ou mot de passe incorrect. Il vous reste %s avant le verrouillage.',

    // inscription
    'reg_title' => 'BERTIN MOVIE - Inscription',
    'reg_subtitle' => 'Créez votre compte en quelques secondes',
    'reg_username' => "Nom d'utilisateur",
    'reg_ph_username' => 'Ex: olyberthin',
    'reg_ph_email' => 'exemple@email.com',
    'reg_firstname' => 'Prénom',
    'reg_ph_firstname' => 'Votre prénom',
    'reg_lastname' => 'Nom',
    'reg_ph_lastname' => 'Votre nom',
    'reg_sex' => 'Sexe',
    'reg_select' => '-- Sélectionner --',
    'reg_male' => 'Masculin',
    'reg_female' => 'Féminin',
    'reg_birth' => 'Date de naissance',
    'reg_nationality' => 'Nationalité',
    'reg_select_country' => '-- Sélectionner un pays --',
    'reg_ph_password' => 'Min. 6 caractères',
    'reg_confirm' => 'Confirmer',
    'reg_ph_confirm' => 'Répétez le mot de passe',
    'reg_btn' => 'Créer mon compte',
    'reg_otp_sent' => '✅ Un code OTP a été envoyé à votre email : %s',
    'reg_otp_info' => '📩 Un code OTP a été envoyé à <strong>%s</strong>',
    'reg_btn_verify' => 'Vérifier mon compte',
    'reg_resend' => 'Renvoyer un nouveau code',
    'reg_signin' => 'Se connecter',
    'reg_done' => '✅ Compte créé avec succès !',
    'err_all_required' => 'Tous les champs sont requis.',
    'err_pw_mismatch' => 'Les mots de passe ne correspondent pas.',
    'err_email_invalid' => 'Adresse email invalide.',
    'err_sex_invalid' => 'Veuillez sélectionner un sexe valide.',
    'err_date_invalid' => 'Date de naissance invalide.',
    'err_country_invalid' => 'Veuillez sélectionner un pays valide.',
    'err_taken' => "Cet email ou nom d'utilisateur est déjà utilisé.",
    'err_mail_detail_reg' => "Détail : erreur de connexion au serveur d'email, réessayez plus tard.",
    'err_otp_empty' => 'Veuillez entrer le code OTP.',
    'err_session_expired' => "Session expirée. Veuillez recommencer l'inscription.",
    'err_code_expired_reg' => "⌛ Votre code a expiré. Veuillez recommencer l'inscription.",
    'err_too_many_reg' => "🔒 Trop d'essais incorrects. Veuillez recommencer l'inscription.",
    'err_otp_wrong_reg' => '❌ Code OTP incorrect ou expiré. Il vous reste %d essai(s).',
    'js_otp_alert' => 'Veuillez entrer les 6 chiffres du code OTP.',

    // mot de passe oublié
    'forgot_title' => 'BERTIN MOVIE - Mot de passe oublié',
    'forgot_subtitle' => 'Réinitialiser votre mot de passe',
    'forgot_ph_email' => 'Entrez votre email',
    'forgot_btn' => 'Envoyer le code',
    'err_enter_email' => 'Veuillez entrer votre email.',
    'err_email_unknown' => "❌ Cet email n'existe pas dans notre système.",
    'err_mail_detail' => 'Détail : erreur de connexion',
    'err_expired_get' => '⌛ Votre code a expiré. Demandez-en un nouveau.',
    'err_tries_get' => "🔒 Trop d'essais incorrects. Demandez un nouveau code.",

    // vérification du code
    'verif_title' => 'BERTIN MOVIE - Vérification OTP',
    'verif_subtitle' => 'Vérification du code OTP',
    'verif_info' => '📩 Un code OTP a été envoyé à <strong>%s</strong>',
    'verif_btn' => 'Vérifier mon code',
    'verif_resend' => 'Renvoyer un nouveau code',
    'verif_home' => "Retour à l'accueil",
    'err_otp_wrong' => '❌ Code OTP incorrect. Il vous reste %d essai(s).',

    // nouveau mot de passe
    'newpw_title' => 'BERTIN MOVIE - Nouveau mot de passe',
    'newpw_subtitle' => 'Nouveau mot de passe',
    'newpw_label' => 'Nouveau mot de passe',
    'newpw_ph' => 'Minimum 6 caractères',
    'newpw_confirm' => 'Confirmer',
    'newpw_ph_confirm' => 'Répétez le mot de passe',
    'newpw_btn' => 'Modifier mon mot de passe',
    'err_pw_diff' => 'Les mots de passe sont différents.',
];

// ---------------------------------------------------------------- ENGLISH
$BM_T['en'] = [
    'label_email' => 'Email',
    'label_password' => 'Password',
    'link_back_login' => 'Back to sign in',
    'err_pw_short' => 'Password must be at least 6 characters long.',
    'err_wait' => '⏳ Please wait %d second(s) before requesting a new code.',
    'err_mail_send' => '❌ Failed to send the email.',
    'ui_lang' => 'Language',
    'ui_theme' => 'Light / dark theme',

    'mail_subject' => '🔐 OTP code - BERTIN MOVIE',
    'mail_hello' => 'Hello',
    'mail_signup_intro' => 'Thank you for creating an account on BERTIN MOVIE!',
    'mail_reset_intro' => 'You requested a password reset.',
    'mail_code_label' => 'Here is your verification code:',
    'mail_expire' => 'This code expires in 10 minutes.',
    'mail_ignore' => "If you didn't make this request, please ignore this email.",
    'mail_rights' => 'All rights reserved',
    'mail_alt' => "BERTIN MOVIE - OTP code\n\nHello %1\$s,\n\nYour verification code is: %2\$s\n\nThis code expires in 10 minutes.\n\n© 2026 BERTIN MOVIE",

    'login_title' => 'BERTIN MOVIE - Sign in',
    'login_subtitle' => 'Premium streaming platform',
    'login_feat1' => 'Unlimited movies',
    'login_feat2' => 'Watch anywhere you want',
    'login_feat3' => 'Build your favorites',
    'login_h1' => 'Sign in',
    'login_desc' => 'Sign in to your personal space',
    'login_ph_email' => 'your@email.com',
    'login_ph_password' => 'Enter your password',
    'login_btn' => 'Sign in',
    'login_register' => 'Create an account',
    'login_forgot' => 'Forgot your password?',
    'err_timeout' => 'Your session expired after a period of inactivity. Please sign in again.',
    'minute_sg' => 'minute',
    'minute_pl' => 'minutes',
    'attempt_sg' => 'attempt',
    'attempt_pl' => 'attempts',
    'err_locked' => 'Account temporarily locked after too many failed attempts. Try again in %s, or use "Forgot your password?".',
    'err_locked_now' => 'Too many failed attempts: account locked for %d minutes.',
    'err_bad_login' => 'Incorrect email or password.',
    'err_bad_login_left' => 'Incorrect email or password. You have %s left before the account is locked.',

    'reg_title' => 'BERTIN MOVIE - Sign up',
    'reg_subtitle' => 'Create your account in a few seconds',
    'reg_username' => 'Username',
    'reg_ph_username' => 'e.g. olyberthin',
    'reg_ph_email' => 'example@email.com',
    'reg_firstname' => 'First name',
    'reg_ph_firstname' => 'Your first name',
    'reg_lastname' => 'Last name',
    'reg_ph_lastname' => 'Your last name',
    'reg_sex' => 'Gender',
    'reg_select' => '-- Select --',
    'reg_male' => 'Male',
    'reg_female' => 'Female',
    'reg_birth' => 'Date of birth',
    'reg_nationality' => 'Nationality',
    'reg_select_country' => '-- Select a country --',
    'reg_ph_password' => 'Min. 6 characters',
    'reg_confirm' => 'Confirm',
    'reg_ph_confirm' => 'Repeat the password',
    'reg_btn' => 'Create my account',
    'reg_otp_sent' => '✅ An OTP code was sent to your email: %s',
    'reg_otp_info' => '📩 An OTP code was sent to <strong>%s</strong>',
    'reg_btn_verify' => 'Verify my account',
    'reg_resend' => 'Send a new code',
    'reg_signin' => 'Sign in',
    'reg_done' => '✅ Account created successfully!',
    'err_all_required' => 'All fields are required.',
    'err_pw_mismatch' => 'Passwords do not match.',
    'err_email_invalid' => 'Invalid email address.',
    'err_sex_invalid' => 'Please select a valid gender.',
    'err_date_invalid' => 'Invalid date of birth.',
    'err_country_invalid' => 'Please select a valid country.',
    'err_taken' => 'This email or username is already in use.',
    'err_mail_detail_reg' => 'Details: could not connect to the email server, please try again later.',
    'err_otp_empty' => 'Please enter the OTP code.',
    'err_session_expired' => 'Session expired. Please start the sign-up again.',
    'err_code_expired_reg' => '⌛ Your code has expired. Please start the sign-up again.',
    'err_too_many_reg' => '🔒 Too many incorrect attempts. Please start the sign-up again.',
    'err_otp_wrong_reg' => '❌ Incorrect or expired OTP code. You have %d attempt(s) left.',
    'js_otp_alert' => 'Please enter the 6 digits of the OTP code.',

    'forgot_title' => 'BERTIN MOVIE - Forgot password',
    'forgot_subtitle' => 'Reset your password',
    'forgot_ph_email' => 'Enter your email',
    'forgot_btn' => 'Send the code',
    'err_enter_email' => 'Please enter your email.',
    'err_email_unknown' => '❌ This email does not exist in our system.',
    'err_mail_detail' => 'Details: connection error',
    'err_expired_get' => '⌛ Your code has expired. Request a new one.',
    'err_tries_get' => '🔒 Too many incorrect attempts. Request a new code.',

    'verif_title' => 'BERTIN MOVIE - OTP verification',
    'verif_subtitle' => 'OTP code verification',
    'verif_info' => '📩 An OTP code was sent to <strong>%s</strong>',
    'verif_btn' => 'Verify my code',
    'verif_resend' => 'Send a new code',
    'verif_home' => 'Back to home',
    'err_otp_wrong' => '❌ Incorrect OTP code. You have %d attempt(s) left.',

    'newpw_title' => 'BERTIN MOVIE - New password',
    'newpw_subtitle' => 'New password',
    'newpw_label' => 'New password',
    'newpw_ph' => 'Minimum 6 characters',
    'newpw_confirm' => 'Confirm',
    'newpw_ph_confirm' => 'Repeat the password',
    'newpw_btn' => 'Change my password',
    'err_pw_diff' => 'The passwords do not match.',
];