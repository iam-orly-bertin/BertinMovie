<?php
// includes/mail_config.php : réglages d'envoi des e-mails.
// Ce dossier est protégé par .htaccess : la clé n'est pas lisible depuis le navigateur.
// Laisse 'brevo_api_key' vide pour utiliser seulement mail() de PHP (souvent bloqué sur InfinityFree).
return [
    'brevo_api_key' => '',                 // colle ici ta clé API Brevo (commence par xkeysib-...)
    'from_email'    => '',                 // e-mail expéditeur VÉRIFIÉ dans Brevo (ex. ton adresse Gmail)
    'from_name'     => 'BERTIN MOVIE',
];
