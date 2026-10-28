<?php
include("connexion.php");

// Réinitialiser le mot de passe admin
$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

$update = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE nom_utilisateur = 'admin'");
$update->execute([$hash]);

echo "✅ Mot de passe admin réinitialisé avec succès !<br>";
echo "Nom d'utilisateur : <strong>admin</strong><br>";
echo "Mot de passe : <strong>admin123</strong><br>";
echo "<br><a href='index.php'>Aller à la page de connexion</a>";
?>