<?php
/**
 * chat_test.php : vérifie que TON serveur peut utiliser Groq.
 * À ouvrir une fois dans le navigateur : http://localhost/film_web_dy/chat_test.php
 * ⚠️ SUPPRIME ce fichier après le test.
 */
require_once __DIR__ . '/chat_config.php';

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
@set_time_limit(60);

function t_line($ok, $label, $detail = '')
{
    $icon = $ok === true ? '✅' : ($ok === false ? '❌' : 'ℹ️');
    echo htmlspecialchars("$icon $label") . ($detail !== '' ? ' — ' . htmlspecialchars($detail) : '') . "\n";
    @flush();
}

function t_curl($url, $key, $post = null, $onChunk = null)
{
    $verify = (bool) (defined('GROQ_SSL_VERIFY') ? GROQ_SSL_VERIFY : true);
    $headers = ['Authorization: Bearer ' . $key];
    $opts = [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
    ];
    $body = '';
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
        $opts[CURLOPT_POSTFIELDS] = json_encode($post);
    }
    $opts[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use (&$body, $onChunk) {
        $body .= $data;
        if ($onChunk) { $onChunk($data); }
        return strlen($data);
    };
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $r = ['status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'errno' => curl_errno($ch), 'error' => curl_error($ch), 'body' => $body];
    curl_close($ch);
    return $r;
}

function t_hint($r)
{
    if (in_array($r['errno'], [60, 77, 35, 51, 58, 59], true)) {
        return "Problème de certificat SSL. En local (XAMPP/WAMP) : télécharge https://curl.se/ca/cacert.pem, puis dans php.ini mets curl.cainfo=\"C:\\chemin\\cacert.pem\" et redémarre Apache. En dernier recours, EN LOCAL SEULEMENT, mets GROQ_SSL_VERIFY à false dans chat_config.php.";
    }
    if (in_array($r['errno'], [5, 6, 7, 28], true)) {
        return "Le serveur ne peut pas joindre api.groq.com : appels sortants bloqués (fréquent sur les hébergements gratuits), pare-feu ou pas d'Internet. Demande à l'hébergeur ou change d'hébergeur/proxy.";
    }
    if ($r['status'] === 401) { return "Clé refusée : vérifie GROQ_API_KEY dans chat_config.php (créée sur console.groq.com/keys)."; }
    if ($r['status'] === 403) { return "Accès refusé (clé, région ou IP du serveur bloquée par Groq/Cloudflare)."; }
    if ($r['status'] === 429) { return "Quota atteint : réessaie dans une minute."; }
    return '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Groq - BERTIN MOVIE</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; background: #0f172a; color: #e2e8f0; padding: 24px; }
        pre { background: #1e293b; padding: 18px; border-radius: 12px; line-height: 1.7; white-space: pre-wrap; }
        h1 { font-size: 20px; }
    </style>
</head>
<body>
<h1>Test de l'assistant IA (Groq)</h1>
<pre>
<?php
$key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
$model = defined('GROQ_MODEL') ? GROQ_MODEL : '';

t_line(version_compare(PHP_VERSION, '7.2', '>='), 'Version de PHP', PHP_VERSION . ' (7.2 minimum)');
t_line(function_exists('curl_init'), 'Extension cURL');
t_line(extension_loaded('mbstring'), 'Extension mbstring');
t_line(extension_loaded('openssl'), 'Extension openssl');

$keyOk = ($key !== '' && strpos($key, 'COLLE_TA_CLE') === false);
t_line($keyOk, 'Clé Groq renseignée', $keyOk ? ('commence par « ' . substr($key, 0, 4) . ' », ' . strlen($key) . ' caractères') : 'ouvre chat_config.php');
t_line(null, 'Modèle configuré', $model);

if (!function_exists('curl_init') || !$keyOk) {
    echo "\nCorrige les points ❌ ci-dessus puis recharge la page.\n";
    echo '</pre></body></html>';
    exit;
}

/* 1) Joindre Groq et lister les modèles */
echo "\n--- Connexion à Groq ---\n";
$r = t_curl('https://api.groq.com/openai/v1/models', $key);
if ($r['errno'] === 0 && $r['status'] === 200) {
    t_line(true, 'Groq répond et la clé est acceptée');
    $j = json_decode($r['body'], true);
    $ids = [];
    foreach (($j['data'] ?? []) as $m) { if (!empty($m['id'])) { $ids[] = $m['id']; } }
    sort($ids);
    $found = in_array($model, $ids, true);
    t_line($found, 'Le modèle « ' . $model . ' » est disponible avec ta clé', $found ? '' : 'choisis-en un dans la liste ci-dessous et mets-le dans GROQ_MODEL');
    echo "Modèles disponibles : " . htmlspecialchars(implode(', ', $ids)) . "\n";
} else {
    t_line(false, 'Impossible d\'utiliser Groq', 'HTTP ' . $r['status'] . ' / curl ' . $r['errno'] . ' ' . $r['error']);
    $h = t_hint($r);
    if ($h) { echo htmlspecialchars("👉 $h") . "\n"; }
    echo '</pre></body></html>';
    exit;
}

/* 2) Test d'un message avec affichage progressif */
echo "\n--- Test d'une réponse en streaming ---\n";
echo "Chaque ligne ci-dessous est un morceau de réponse avec son heure d'arrivée.\n";
@flush();

$payload = [
    'model'                 => $model,
    'messages'              => [['role' => 'user', 'content' => 'Compte de 1 à 12, séparés par des virgules, sans autre texte.']],
    'max_completion_tokens' => 200,
    'stream'                => true,
];
if (defined('GROQ_REASONING_EFFORT') && GROQ_REASONING_EFFORT !== '') {
    $payload['reasoning_effort'] = GROQ_REASONING_EFFORT;
}

$t0 = microtime(true);
$first = null;
$chunks = 0;
$text = '';
$buf = '';
$r = t_curl('https://api.groq.com/openai/v1/chat/completions', $key, $payload, function ($data) use (&$buf, &$chunks, &$first, &$text, $t0) {
    $buf .= $data;
    while (($p = strpos($buf, "\n")) !== false) {
        $line = trim(substr($buf, 0, $p));
        $buf = substr($buf, $p + 1);
        if (strpos($line, 'data:') !== 0) { continue; }
        $js = trim(substr($line, 5));
        if ($js === '[DONE]' || $js === '') { continue; }
        $o = json_decode($js, true);
        $piece = $o['choices'][0]['delta']['content'] ?? '';
        if ($piece !== '') {
            $chunks++;
            if ($first === null) { $first = microtime(true) - $t0; }
            $text .= $piece;
            printf("[%.2fs] %s\n", microtime(true) - $t0, htmlspecialchars($piece));
            @flush();
        }
    }
});

if ($r['errno'] !== 0 || $r['status'] !== 200) {
    t_line(false, 'La génération a échoué', 'HTTP ' . $r['status'] . ' / curl ' . $r['errno'] . ' ' . $r['error'] . ' ' . substr($r['body'], 0, 300));
    $h = t_hint($r);
    if ($h) { echo htmlspecialchars("👉 $h") . "\n"; }
} else {
    t_line($chunks > 0, 'Réponse reçue de Groq', $chunks . ' morceaux, premier morceau après ' . ($first === null ? '?' : round($first, 2) . ' s'));
    echo "\nSi les lignes « [x.xxs] ... » sont apparues progressivement pendant le chargement de cette page,\n";
    echo "ton hébergeur laisse passer l'affichage mot par mot. Si tout est apparu d'un coup à la fin,\n";
    echo "le chat marchera quand même : il affichera simplement la réponse d'un seul coup.\n";
}
?>

🧹 Pense à supprimer chat_test.php quand tu as fini.
</pre>
</body>
</html>
