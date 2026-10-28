<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../includes/auth_check.php';

echo "✅ Vous êtes connecté en tant que : " . $user_nom;
echo "<br>Role : " . $user_role;
echo "<br>ID : " . $user_id;
echo "<br><a href='../logout.php'>Se déconnecter</a>";