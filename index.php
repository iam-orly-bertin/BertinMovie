<?php
// Page d'accueil publique : BERTIN MOVIE
session_start();
require_once __DIR__ . '/connexion.php';

/* ---------- LANGUAGE ---------- */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
$lang = $_SESSION['lang'] ?? 'fr';

$translations = [
    'fr' => [
        'nav_home' => 'Accueil',
        'nav_rank' => 'Les plus regardés',
        'nav_genres' => 'Genres',
        'nav_new' => 'Nouveautés',
        'nav_cast' => 'Acteurs',
        'login' => 'Connexion',
        'signup' => "S'inscrire",
        'live_badge' => 'Le plus regardé en ce moment',
        'free_badge' => 'Streaming gratuit',
        'hero_fallback_title' => 'Votre cinéma, sur tous vos écrans',
        'hero_fallback_desc' => "Des films à regarder quand vous voulez. Créez un compte gratuit et lancez votre premier film en quelques secondes.",
        'cta_watch' => 'Regarder maintenant',
        'cta_browse' => 'Parcourir les genres',
        'views' => 'vues',
        'scroll' => 'Défiler',
        'eyebrow_rank' => 'Classement',
        'h_rank' => 'Les plus regardés',
        'eyebrow_genres' => 'Genres',
        'h_genres' => 'Choisissez votre genre',
        'eyebrow_catalog' => 'Catalogue',
        'h_new' => 'Ajoutés récemment',
        'see_all' => 'Voir tout',
        'eyebrow_cast' => 'Distribution',
        'h_cast' => "Les têtes d'affiche",
        'tag_new' => 'Nouveau',
        'no_genre' => 'Sans genre',
        'film' => 'film',
        'films' => 'films',
        'genre' => 'genre',
        'genres' => 'genres',
        'ticket_title' => 'Votre place est réservée',
        'ticket_desc' => 'Créez votre compte gratuit en quelques secondes et lancez un film dès ce soir.',
        'create_account' => 'Créer mon compte',
        'have_account' => "J'ai déjà un compte",
        'to_watch' => 'à regarder',
        'to_explore' => 'à explorer',
        'footer_legal' => 'Mentions légales',
        'footer_contact' => 'Contact',
    ],
    'en' => [
        'nav_home' => 'Home',
        'nav_rank' => 'Most watched',
        'nav_genres' => 'Genres',
        'nav_new' => 'New',
        'nav_cast' => 'Cast',
        'login' => 'Sign in',
        'signup' => 'Sign up',
        'live_badge' => 'Most watched right now',
        'free_badge' => 'Free streaming',
        'hero_fallback_title' => 'Your cinema, on every screen',
        'hero_fallback_desc' => 'Watch movies whenever you want. Create a free account and start your first film in seconds.',
        'cta_watch' => 'Watch now',
        'cta_browse' => 'Browse genres',
        'views' => 'views',
        'scroll' => 'Scroll',
        'eyebrow_rank' => 'Ranking',
        'h_rank' => 'Most watched',
        'eyebrow_genres' => 'Genres',
        'h_genres' => 'Choose your genre',
        'eyebrow_catalog' => 'Catalog',
        'h_new' => 'Recently added',
        'see_all' => 'See all',
        'eyebrow_cast' => 'Cast',
        'h_cast' => 'Headliners',
        'tag_new' => 'New',
        'no_genre' => 'No genre',
        'film' => 'film',
        'films' => 'films',
        'genre' => 'genre',
        'genres' => 'genres',
        'ticket_title' => 'Your seat is reserved',
        'ticket_desc' => 'Create your free account in seconds and start a film tonight.',
        'create_account' => 'Create my account',
        'have_account' => 'I already have an account',
        'to_watch' => 'to watch',
        'to_explore' => 'to explore',
        'footer_legal' => 'Legal notice',
        'footer_contact' => 'Contact',
    ],
];
$t = $translations[$lang];

/* ---------- DATA ---------- */
$base = "SELECT f.*, v.id_video, v.thumbnail, v.views, c.nom_categorie
         FROM film f
         INNER JOIN video_film v ON f.id_film = v.id_film
         LEFT JOIN categorie c ON f.id_categorie = c.id_categorie
         WHERE f.is_deleted = 0 AND f.est_archive = 0";
$popular_films = $pdo->query("$base ORDER BY v.views DESC LIMIT 6")->fetchAll();
$hero_film = $popular_films[0] ?? null;
$new_films = $pdo->query("$base ORDER BY v.date_upload DESC LIMIT 5")->fetchAll();
$total_films = (int) $pdo->query("SELECT COUNT(DISTINCT f.id_film) FROM film f INNER JOIN video_film v ON f.id_film = v.id_film WHERE f.is_deleted = 0 AND f.est_archive = 0")->fetchColumn();

$categories = $pdo->query("
    SELECT c.*, COUNT(f.id_film) AS total_films
    FROM categorie c
    LEFT JOIN film f ON c.id_categorie = f.id_categorie AND f.is_deleted = 0 AND f.est_archive = 0
    GROUP BY c.id_categorie ORDER BY total_films DESC LIMIT 6")->fetchAll();

$acteurs = $pdo->query("
    SELECT a.*, COUNT(fa.id_film) AS total_films
    FROM acteur a
    LEFT JOIN film_acteur fa ON a.id_acteur = fa.id_acteur
    LEFT JOIN film f ON fa.id_film = f.id_film AND f.is_deleted = 0 AND f.est_archive = 0
    GROUP BY a.id_acteur ORDER BY total_films DESC LIMIT 8")->fetchAll();

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function img($f)
{
    return h($f['thumbnail'] ?? 'assets/default-thumbnail.jpg');
}
function duree($d)
{
    return is_numeric($d) ? $d . ' min' : $d;
}
$cat_icons = ['Action' => 'fa-bolt', 'Comédie' => 'fa-face-laugh', 'Comedy' => 'fa-face-laugh', 'Drame' => 'fa-masks-theater', 'Horreur' => 'fa-skull', 'Romance' => 'fa-heart', 'Sci-Fi' => 'fa-rocket', 'Animation' => 'fa-cube', 'Thriller' => 'fa-brain'];
$hues = [215, 262, 38, 330, 168, 205];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BERTIN MOVIE — <?= h($t['hero_fallback_title']) ?></title>
    <!-- Anti-flash : dark uniquement si l'utilisateur l'a choisi -->
    <script>(function () { try { if (localStorage.getItem('theme') === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); } } catch (e) { } })();</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* ================== THEME TOKENS ================== */
        :root {
            --bg: #ffffff;
            --bg-soft: #f5f8fd;
            --bg-band: #eef4ff;
            --bg-elev: #ffffff;
            --text: #0a1229;
            --text-soft: #45516b;
            --text-mut: #8a94ab;
            --line: #e4eaf3;
            --line-2: #d5deec;
            --blue: #2563eb;
            --blue-600: #1d4ed8;
            --blue-700: #1e40af;
            --blue-100: #dbeafe;
            --blue-50: #eff6ff;
            --blue-glow: rgba(37, 99, 235, .20);
            --shadow-1: 0 1px 2px rgba(10, 18, 41, .05);
            --shadow-2: 0 10px 30px -14px rgba(10, 18, 41, .18);
            --shadow-3: 0 26px 55px -22px rgba(10, 18, 41, .28);
            --shadow-blue: 0 18px 40px -16px rgba(37, 99, 235, .45);
            --radius: 18px;
        }

        html[data-theme="dark"] {
            --bg: #0a1122;
            --bg-soft: #0e1730;
            --bg-band: #101c3a;
            --bg-elev: #131d3d;
            --text: #eaf0fb;
            --text-soft: #b0bad1;
            --text-mut: #6d7a96;
            --line: #1c2846;
            --line-2: #263558;
            --blue: #5b9cff;
            --blue-600: #3b82f6;
            --blue-700: #2563eb;
            --blue-100: rgba(91, 156, 255, .18);
            --blue-50: rgba(91, 156, 255, .08);
            --blue-glow: rgba(91, 156, 255, .30);
            --shadow-1: 0 1px 2px rgba(0, 0, 0, .5);
            --shadow-2: 0 10px 30px -14px rgba(0, 0, 0, .6);
            --shadow-3: 0 26px 55px -22px rgba(0, 0, 0, .75);
            --shadow-blue: 0 18px 40px -16px rgba(59, 130, 246, .5);
        }

        /* ================== BASE ================== */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 96px
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            transition: background .35s ease, color .35s ease;
        }

        img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        a {
            color: inherit;
            text-decoration: none
        }

        button {
            font-family: inherit
        }

        ::selection {
            background: var(--blue);
            color: #fff
        }

        :focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 3px
        }

        .wrap {
            width: min(1240px, 90%);
            margin: 0 auto
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: clamp(1.9rem, 4.2vw, 3.2rem);
            line-height: 1.05;
            letter-spacing: -.025em;
            text-wrap: balance
        }

        /* ================== NAV ================== */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 60;
            padding: 16px 0;
            border-bottom: 1px solid transparent;
            transition: padding .3s, background .3s, backdrop-filter .3s, border-color .3s, box-shadow .3s;
        }

        .nav.scrolled {
            padding: 10px 0;
            background: color-mix(in srgb, var(--bg) 82%, transparent);
            backdrop-filter: blur(18px) saturate(160%);
            border-bottom-color: var(--line);
            box-shadow: var(--shadow-1);
        }

        .nav-in {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0
        }

        .logo-mark {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--blue), var(--blue-600));
            color: #fff;
            font-size: .85rem;
            flex: none;
            box-shadow: var(--shadow-blue);
        }

        .logo-txt {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            line-height: 1;
            letter-spacing: -.02em
        }

        .logo-txt em {
            font-style: normal;
            color: var(--blue)
        }

        .links {
            display: flex;
            align-items: center;
            gap: 2px;
            list-style: none
        }

        .links a {
            padding: 9px 14px;
            border-radius: 99px;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text-soft);
            transition: color .2s, background .2s;
        }

        .links a:hover {
            color: var(--text);
            background: var(--bg-soft)
        }

        .links a.on {
            color: var(--blue);
            background: var(--blue-50)
        }

        /* nav tools (lang / theme / buttons) */
        .nav-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0
        }

        .lang-switch {
            display: flex;
            padding: 3px;
            border-radius: 99px;
            gap: 2px;
            border: 1px solid var(--line);
            background: var(--bg-soft);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .05em;
        }

        .lang-switch a {
            padding: 5px 10px;
            border-radius: 99px;
            color: var(--text-mut);
            transition: color .2s, background .2s;
        }

        .lang-switch a:hover:not(.on) {
            color: var(--text)
        }

        .lang-switch a.on {
            color: #fff;
            background: var(--blue);
            box-shadow: var(--shadow-blue)
        }

        .theme-toggle {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: var(--bg-soft);
            color: var(--text);
            cursor: pointer;
            overflow: hidden;
            display: grid;
            place-items: center;
            transition: border-color .2s, background .2s;
        }

        .theme-toggle:hover {
            border-color: var(--blue);
            background: var(--blue-50);
            color: var(--blue)
        }

        .theme-toggle i {
            position: absolute;
            font-size: .95rem;
            transition: transform .55s cubic-bezier(.4, 0, .2, 1), opacity .35s
        }

        .theme-toggle .icon-moon {
            opacity: 1;
            transform: rotate(0) scale(1)
        }

        .theme-toggle .icon-sun {
            opacity: 0;
            transform: rotate(-90deg) scale(.4)
        }

        html[data-theme="dark"] .theme-toggle .icon-moon {
            opacity: 0;
            transform: rotate(90deg) scale(.4)
        }

        html[data-theme="dark"] .theme-toggle .icon-sun {
            opacity: 1;
            transform: rotate(0) scale(1)
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 22px;
            border-radius: 99px;
            font-weight: 700;
            font-size: .9rem;
            border: 1.5px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: transform .25s, background .25s, color .25s, border-color .25s, box-shadow .25s;
        }

        .btn i {
            font-size: .85em
        }

        .btn:hover {
            transform: translateY(-2px)
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--blue), var(--blue-600));
            color: #fff;
            box-shadow: var(--shadow-blue);
        }

        .btn-primary:hover {
            box-shadow: 0 22px 44px -14px rgba(37, 99, 235, .65)
        }

        .btn-outline {
            border-color: var(--line-2);
            color: var(--text);
            background: transparent
        }

        .btn-outline:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-50)
        }

        .btn-white {
            background: #fff;
            color: var(--blue-700);
            box-shadow: 0 14px 30px -14px rgba(0, 0, 0, .35)
        }

        .btn-ghost-light {
            border-color: rgba(255, 255, 255, .5);
            color: #fff;
            background: transparent
        }

        .btn-ghost-light:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, .12)
        }

        .ghost-link {
            padding: 9px 16px;
            border-radius: 99px;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text-soft);
            transition: background .2s, color .2s
        }

        .ghost-link:hover {
            background: var(--bg-soft);
            color: var(--text)
        }

        .burger {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--bg-soft);
            color: var(--text);
            font-size: 1.05rem;
            cursor: pointer;
        }

        /* ================== HERO ================== */
        .hero {
            position: relative;
            min-height: 100svh;
            display: flex;
            align-items: center;
            padding: 140px 0 100px;
            overflow: hidden;
            isolation: isolate;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            background:
                radial-gradient(60% 55% at 82% 22%, var(--blue-50) 0%, transparent 62%),
                radial-gradient(50% 60% at 12% 90%, var(--blue-50) 0%, transparent 60%),
                linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 70%);
        }

        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            opacity: .5;
            background-image: radial-gradient(circle at 1px 1px, var(--line-2) 1px, transparent 0);
            background-size: 26px 26px;
            -webkit-mask-image: radial-gradient(ellipse 60% 50% at 50% 40%, #000 30%, transparent 75%);
            mask-image: radial-gradient(ellipse 60% 50% at 50% 40%, #000 30%, transparent 75%);
        }

        .hero-in {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 60px;
            align-items: center
        }

        .kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 16px 7px 12px;
            border-radius: 99px;
            background: var(--blue-50);
            border: 1px solid var(--blue-100);
            color: var(--blue);
            font-weight: 700;
            font-size: .78rem;
            letter-spacing: .04em;
        }

        .kicker .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--blue);
            box-shadow: 0 0 0 4px var(--blue-glow);
            animation: pulse 2.2s ease-in-out infinite;
        }

        .hero h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: clamp(2.5rem, 5.6vw, 4.6rem);
            line-height: .98;
            letter-spacing: -.035em;
            margin: 22px 0 20px;
            max-width: 14ch;
            text-wrap: balance;
            overflow-wrap: anywhere;
        }

        .lead {
            color: var(--text-soft);
            font-size: 1.06rem;
            max-width: 48ch;
            margin-bottom: 28px
        }

        .facts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 36px
        }

        .facts span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            background: var(--bg-elev);
            border: 1px solid var(--line);
            font-weight: 600;
            font-size: .86rem;
            color: var(--text-soft);
            box-shadow: var(--shadow-1);
        }

        .facts i {
            color: var(--blue);
            font-size: .8rem
        }

        .cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px
        }

        .cta-row .btn {
            padding: 15px 28px;
            font-size: .98rem
        }

        .copy>* {
            animation: rise .85s cubic-bezier(.2, .8, .2, 1) both
        }

        .copy>:nth-child(2) {
            animation-delay: .08s
        }

        .copy>:nth-child(3) {
            animation-delay: .16s
        }

        .copy>:nth-child(4) {
            animation-delay: .24s
        }

        .copy>:nth-child(5) {
            animation-delay: .32s
        }

        /* poster stack */
        .stack {
            position: relative;
            aspect-ratio: 1/1.06;
            perspective: 1200px;
            transform-style: preserve-3d;
            transform: rotateY(calc(var(--mx, 0) * 9deg)) rotateX(calc(var(--my, 0) * -7deg));
            transition: transform .55s cubic-bezier(.2, .8, .2, 1);
        }

        .stack::before {
            content: '';
            position: absolute;
            inset: -12% -6% -4% -6%;
            z-index: -1;
            border-radius: 50%;
            background: radial-gradient(closest-side, var(--blue-glow), transparent 70%);
            filter: blur(30px);
        }

        .poster {
            position: absolute;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 40px 70px -30px rgba(10, 18, 41, .5), 0 0 0 1px var(--line);
            transition: transform .55s cubic-bezier(.2, .8, .2, 1), box-shadow .4s;
        }

        html[data-theme="dark"] .poster {
            box-shadow: 0 40px 70px -30px rgba(0, 0, 0, .9), 0 0 0 1px rgba(255, 255, 255, .08);
        }

        .poster:hover {
            transform: rotate(0) scale(1.05) !important;
            z-index: 6;
            box-shadow: var(--shadow-blue), 0 0 0 1px var(--blue);
        }

        .p1 {
            width: 46%;
            left: 0;
            top: 15%;
            transform: rotate(-8deg)
        }

        .p2 {
            width: 52%;
            left: 24%;
            top: 0;
            z-index: 3
        }

        .p3 {
            width: 46%;
            right: 0;
            top: 16%;
            transform: rotate(8deg)
        }

        .seal {
            position: absolute;
            top: -14px;
            right: -6px;
            width: 96px;
            height: 96px;
            z-index: 7;
            color: var(--blue);
            animation: spin 24s linear infinite;
            background: var(--bg);
            border-radius: 50%;
            box-shadow: var(--shadow-2);
        }

        .seal text {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 8.4px;
            letter-spacing: .15em;
            font-weight: 700;
            fill: currentColor;
            text-transform: uppercase;
        }

        .scroll-cue {
            position: absolute;
            left: 50%;
            bottom: 26px;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 9px;
            font-size: .66rem;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: var(--text-mut);
            font-weight: 700;
        }

        .scroll-cue span {
            width: 1px;
            height: 34px;
            background: linear-gradient(var(--blue), transparent);
            animation: cue 2s ease-in-out infinite;
        }

        /* ================== MARQUEE ================== */
        .ticker {
            overflow: hidden;
            padding: 20px 0;
            background: var(--bg-band);
            border-block: 1px solid var(--line);
        }

        .ticker .track {
            display: flex;
            width: max-content;
            animation: scroll 62s linear infinite
        }

        .ticker:hover .track {
            animation-play-state: paused
        }

        .ticker span {
            display: inline-flex;
            align-items: center;
            gap: 26px;
            padding-right: 26px;
            white-space: nowrap;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-soft);
            letter-spacing: -.01em;
        }

        .ticker span::after {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--blue);
            opacity: .6;
        }

        /* ================== SECTIONS ================== */
        .sec {
            padding-top: 104px
        }

        .sec-h {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-bottom: 38px;
            flex-wrap: wrap
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-size: .72rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 14px;
        }

        .eyebrow::before {
            content: '';
            width: 22px;
            height: 2px;
            background: var(--blue);
            border-radius: 2px
        }

        .arrows {
            display: flex;
            gap: 8px
        }

        .arrows button {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 1px solid var(--line-2);
            background: var(--bg);
            color: var(--text);
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: background .25s, border-color .25s, color .25s, transform .25s;
        }

        .arrows button:hover {
            background: var(--blue);
            border-color: var(--blue);
            color: #fff;
            transform: translateY(-2px)
        }

        /* ================== RAIL ================== */
        .rail {
            display: flex;
            gap: 22px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding: 10px 2px 26px;
            scrollbar-width: none;
        }

        .rail::-webkit-scrollbar {
            display: none
        }

        .t {
            position: relative;
            flex: 0 0 clamp(228px, 24vw, 286px);
            scroll-snap-align: start;
            padding-left: 32px
        }

        .t .rank {
            position: absolute;
            left: -8px;
            top: 0;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 6.2rem;
            line-height: 1;
            letter-spacing: -.06em;
            color: transparent;
            -webkit-text-stroke: 1.6px var(--blue-100);
            pointer-events: none;
            z-index: 0;
        }

        .t .pic {
            position: relative;
            aspect-ratio: 2/3;
            border-radius: 14px;
            overflow: hidden;
            background: var(--bg-soft);
            box-shadow: var(--shadow-2);
            transition: box-shadow .3s, transform .3s;
        }

        .t:hover .pic {
            box-shadow: var(--shadow-blue);
            transform: translateY(-4px)
        }

        .t .pic img {
            transition: transform .65s cubic-bezier(.2, .8, .2, 1)
        }

        .t:hover .pic img {
            transform: scale(1.06)
        }

        .play {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            opacity: 0;
            transition: opacity .3s;
            background: linear-gradient(0deg, rgba(10, 18, 41, .78), transparent 62%);
        }

        .play i {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fff;
            color: var(--blue);
            font-size: .95rem;
            padding-left: 3px;
            box-shadow: 0 10px 24px -8px rgba(0, 0, 0, .4);
        }

        .t:hover .play,
        .n:hover .play {
            opacity: 1
        }

        .t h3 {
            font-weight: 700;
            font-size: 1rem;
            margin-top: 14px;
            line-height: 1.35;
            position: relative
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 14px;
            color: var(--text-mut);
            font-size: .82rem;
            margin-top: 5px;
            font-weight: 500;
        }

        .meta i {
            margin-right: 5px;
            color: var(--blue);
            font-size: .74rem
        }

        /* ================== GENRES (soft blue band) ================== */
        .band {
            background: var(--bg-band);
            border-block: 1px solid var(--line);
            padding: 96px 0;
            margin-top: 104px;
        }

        .wall {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 56px
        }

        .g {
            position: relative;
            display: grid;
            grid-template-columns: 34px 24px 1fr auto 22px;
            align-items: center;
            gap: 16px;
            padding: 24px 10px;
            border-bottom: 1px solid var(--line-2);
            overflow: hidden;
            border-radius: 10px;
            transition: padding .4s cubic-bezier(.2, .8, .2, 1);
        }

        .wall .g:nth-last-child(-n+2) {
            border-bottom: 0
        }

        .g::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            background: linear-gradient(135deg, var(--blue), var(--blue-600));
            transform: scaleY(0);
            transform-origin: 50% 100%;
            transition: transform .45s cubic-bezier(.2, .8, .2, 1);
        }

        .g>* {
            position: relative;
            z-index: 1;
            transition: color .35s, transform .35s, opacity .35s
        }

        .g-idx {
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .1em;
            color: var(--text-mut);
            font-variant-numeric: tabular-nums
        }

        .g-ic {
            color: hsl(var(--h) 72% 48%);
            font-size: .95rem;
            text-align: center
        }

        .g-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: clamp(1.4rem, 2.4vw, 2rem);
            line-height: 1;
            letter-spacing: -.02em;
        }

        .g-count {
            font-size: .83rem;
            font-weight: 600;
            color: var(--text-mut);
            white-space: nowrap
        }

        .g-arrow {
            color: var(--text-mut);
            opacity: 0;
            transform: translateX(-8px)
        }

        .g:hover {
            padding-left: 22px;
            padding-right: 18px
        }

        .g:hover::before {
            transform: scaleY(1)
        }

        .g:hover .g-idx {
            color: rgba(255, 255, 255, .65)
        }

        .g:hover .g-ic {
            color: #fff
        }

        .g:hover .g-name {
            color: #fff;
            transform: translateX(4px)
        }

        .g:hover .g-count {
            color: rgba(255, 255, 255, .75)
        }

        .g:hover .g-arrow {
            opacity: 1;
            color: #fff;
            transform: translateX(0)
        }

        /* ================== BENTO ================== */
        .bento {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 200px;
            gap: 16px
        }

        .n {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            display: block;
            isolation: isolate;
            background: var(--bg-soft);
            box-shadow: var(--shadow-2);
            transition: transform .3s, box-shadow .3s;
        }

        .n:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-blue)
        }

        .n:first-child {
            grid-column: span 2;
            grid-row: span 2
        }

        .n img {
            position: absolute;
            inset: 0;
            transition: transform .7s cubic-bezier(.2, .8, .2, 1)
        }

        .n:hover img {
            transform: scale(1.06)
        }

        .n .cap {
            position: absolute;
            inset: auto 0 0;
            padding: 66px 20px 20px;
            background: linear-gradient(0deg, rgba(6, 12, 26, .94) 8%, rgba(6, 12, 26, .5) 55%, transparent);
            color: #fff;
        }

        .n h3 {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.32
        }

        .n:first-child h3 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: clamp(1.5rem, 2.4vw, 2.1rem);
            line-height: 1.02;
            letter-spacing: -.02em;
        }

        .n .meta {
            color: rgba(255, 255, 255, .75)
        }

        .n .meta i {
            color: #fff
        }

        .tag {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 3;
            background: var(--blue);
            color: #fff;
            font-weight: 700;
            font-size: .66rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            padding: 5px 11px;
            border-radius: 99px;
            box-shadow: var(--shadow-blue);
        }

        .n .play i {
            width: 46px;
            height: 46px;
            font-size: .85rem;
            background: var(--blue);
            color: #fff
        }

        /* ================== CAST ================== */
        .cast {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(156px, 1fr));
            gap: 16px
        }

        .a {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            padding: 26px 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--bg-elev);
            transition: border-color .3s, transform .3s, box-shadow .3s;
        }

        .a:hover {
            border-color: var(--blue);
            transform: translateY(-5px);
            box-shadow: var(--shadow-blue)
        }

        .ava {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-family: 'Outfit', sans-serif;
            font-size: 1.95rem;
            font-weight: 800;
            line-height: 1;
            color: #fff;
            background: linear-gradient(140deg, hsl(var(--h) 85% 62%), hsl(calc(var(--h) + 45) 85% 50%));
            box-shadow: 0 14px 26px -12px hsl(var(--h) 85% 45% / .75);
            transition: transform .4s;
        }

        .a:hover .ava {
            transform: scale(1.08) rotate(-4deg)
        }

        .a b {
            font-weight: 700;
            font-size: .94rem;
            text-align: center;
            line-height: 1.32
        }

        .a small {
            color: var(--text-mut);
            font-size: .79rem;
            font-weight: 600
        }

        /* ================== TICKET ================== */
        #cta {
            padding-top: 104px
        }

        .ticket {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 280px;
            border-radius: 24px;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 45%, #3b82f6 100%);
            color: #fff;
            box-shadow: 0 40px 80px -40px rgba(30, 64, 175, .65);
            overflow: hidden;
        }

        .ticket::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: .35;
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, .35) 1px, transparent 0);
            background-size: 22px 22px;
            -webkit-mask-image: radial-gradient(ellipse at 80% 20%, #000 20%, transparent 70%);
            mask-image: radial-gradient(ellipse at 80% 20%, #000 20%, transparent 70%);
        }

        .ticket .main {
            padding: 56px 52px;
            position: relative;
            z-index: 1
        }

        .ticket h2 {
            color: #fff;
            margin-bottom: 14px;
            font-size: clamp(1.7rem, 3.4vw, 2.6rem)
        }

        .ticket p {
            max-width: 44ch;
            color: rgba(255, 255, 255, .82);
            font-size: 1.02rem;
            margin-bottom: 30px
        }

        .stub {
            position: relative;
            z-index: 1;
            border-left: 2px dashed rgba(255, 255, 255, .35);
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 26px;
            text-align: center;
            background: rgba(0, 0, 0, .08);
        }

        .stub b {
            display: block;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            line-height: 1;
            letter-spacing: -.03em;
            color: #fff;
        }

        .stub span {
            display: block;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .15em;
            color: rgba(255, 255, 255, .75);
            font-weight: 700;
            margin-top: 8px;
        }

        .barcode {
            height: 40px;
            opacity: .9;
            border-radius: 3px;
            background: repeating-linear-gradient(90deg, #fff 0 2px, transparent 2px 4px, #fff 4px 5px, transparent 5px 9px, #fff 9px 12px, transparent 12px 14px, #fff 14px 15px, transparent 15px 19px);
        }

        .notch {
            position: absolute;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg);
            left: calc(100% - 280px);
            transform: translateX(-50%);
            z-index: 2;
            transition: background .35s;
        }

        .notch.top {
            top: -16px
        }

        .notch.bottom {
            bottom: -16px
        }

        /* ================== FOOTER ================== */
        footer {
            margin-top: 104px;
            border-top: 1px solid var(--line);
            padding: 40px 0;
            color: var(--text-mut);
            font-size: .88rem;
            background: var(--bg-soft);
        }

        footer .wrap {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px
        }

        footer nav {
            display: flex;
            gap: 26px;
            flex-wrap: wrap
        }

        footer nav a {
            transition: color .2s;
            font-weight: 600
        }

        footer nav a:hover {
            color: var(--blue)
        }

        /* ================== ANIM ================== */
        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(28px)
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        @keyframes pulse {
            50% {
                opacity: .3;
                transform: scale(.85)
            }
        }

        @keyframes cue {

            0%,
            100% {
                opacity: .3;
                transform: scaleY(.6)
            }

            50% {
                opacity: 1;
                transform: scaleY(1)
            }
        }

        @keyframes scroll {
            to {
                transform: translateX(-50%)
            }
        }

        /* ================== RESPONSIVE ================== */
        @media(max-width:1040px) {
            .links {
                position: absolute;
                top: 100%;
                left: 5%;
                right: 5%;
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                background: var(--bg);
                border: 1px solid var(--line);
                border-radius: 18px;
                padding: 10px;
                display: none;
                box-shadow: var(--shadow-3);
            }

            .links.open {
                display: flex
            }

            .burger {
                display: grid;
                place-items: center
            }

            .ghost-link {
                display: none
            }

            .hero-in {
                grid-template-columns: 1fr;
                gap: 44px
            }

            .stack {
                width: min(420px, 100%);
                margin: 0 auto;
                aspect-ratio: 1/.95
            }

            .wall {
                grid-template-columns: 1fr;
                column-gap: 0
            }

            .wall .g {
                border-bottom: 1px solid var(--line-2) !important
            }

            .wall .g:last-child {
                border-bottom: 0 !important
            }

            .bento {
                grid-template-columns: 1fr 1fr;
                grid-auto-rows: 190px
            }

            .n:first-child {
                grid-column: span 2;
                grid-row: span 1
            }
        }

        @media(max-width:720px) {
            .sec {
                padding-top: 72px
            }

            .band {
                padding: 66px 0;
                margin-top: 72px
            }

            #cta {
                padding-top: 72px
            }

            .hero {
                padding: 118px 0 80px
            }

            .sec-h {
                margin-bottom: 26px
            }

            .t .rank {
                font-size: 5rem
            }

            .ticket {
                grid-template-columns: 1fr
            }

            .ticket .main {
                padding: 36px 26px
            }

            .stub {
                border-left: 0;
                border-top: 2px dashed rgba(255, 255, 255, .35);
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: space-around;
                gap: 22px 32px;
                padding: 30px 22px;
            }

            .stub>div {
                flex: 1 1 auto
            }

            .stub b {
                font-size: 2.3rem
            }

            .barcode {
                flex-basis: 100%
            }

            .notch {
                display: none
            }

            .bento {
                grid-template-columns: 1fr;
                grid-auto-rows: 220px
            }

            .n:first-child {
                grid-column: span 1;
                grid-row: span 1
            }

            .scroll-cue {
                display: none
            }
        }

        @media(max-width:560px) {
            .nav-tools {
                gap: 6px
            }

            .lang-switch {
                font-size: .7rem
            }

            .lang-switch a {
                padding: 4px 8px
            }

            .theme-toggle {
                width: 36px;
                height: 36px
            }

            .btn-primary span {
                display: none
            }

            .btn-primary {
                padding: 12px 14px
            }

            .cast {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr))
            }

            .a {
                padding: 20px 12px
            }
        }

        @media(prefers-reduced-motion:reduce) {

            *,
            *::before {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important
            }
        }
    </style>
</head>

<body>

    <nav class="nav" id="nav">
        <div class="wrap nav-in">
            <a href="#accueil" class="logo">
                <span class="logo-mark"><i class="fas fa-film"></i></span>
                <span class="logo-txt">Bertin<em>Movie</em></span>
            </a>
            <ul class="links" id="links">
                <li><a href="#accueil" class="on"><?= h($t['nav_home']) ?></a></li>
                <li><a href="#classement"><?= h($t['nav_rank']) ?></a></li>
                <li><a href="#categories"><?= h($t['nav_genres']) ?></a></li>
                <li><a href="#nouveautes"><?= h($t['nav_new']) ?></a></li>
                <li><a href="#acteurs"><?= h($t['nav_cast']) ?></a></li>
            </ul>
            <div class="nav-tools">
                <!-- LANGUAGE SWITCH -->
                <div class="lang-switch" role="group" aria-label="Language">
                    <a href="?lang=fr" class="<?= $lang === 'fr' ? 'on' : '' ?>" aria-label="Français">FR</a>
                    <a href="?lang=en" class="<?= $lang === 'en' ? 'on' : '' ?>" aria-label="English">EN</a>
                </div>
                <!-- THEME TOGGLE -->
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Basculer thème clair / sombre"
                    aria-pressed="false">
                    <i class="fas fa-moon icon-moon"></i>
                    <i class="fas fa-sun icon-sun"></i>
                </button>
                <a href="login.php" class="ghost-link"><?= h($t['login']) ?></a>
                <a href="creation_compte.php" class="btn btn-primary"><span><?= h($t['signup']) ?></span><i
                        class="fas fa-arrow-right"></i></a>
                <button class="burger" id="burger" aria-label="Menu" aria-expanded="false"><i
                        class="fas fa-bars"></i></button>
            </div>
        </div>
    </nav>

    <header class="hero" id="accueil">
        <div class="wrap hero-in">
            <div class="copy">
                <p class="kicker">
                    <span class="dot"></span>
                    <?= $hero_film ? h($t['live_badge']) : h($t['free_badge']) ?>
                </p>
                <h1><?= $hero_film ? h($hero_film['nom_film']) : h($t['hero_fallback_title']) ?></h1>
                <p class="lead">
                    <?= !empty($hero_film['titre']) ? h(mb_strimwidth($hero_film['titre'], 0, 180, '…')) : h($t['hero_fallback_desc']) ?>
                </p>
                <?php if ($hero_film): ?>
                    <div class="facts">
                        <?php if (!empty($hero_film['nom_categorie'])): ?><span><i
                                    class="fas fa-tag"></i><?= h($hero_film['nom_categorie']) ?></span><?php endif; ?>
                        <?php if (!empty($hero_film['date_sortie'])): ?><span><i
                                    class="fas fa-calendar"></i><?= date('Y', strtotime($hero_film['date_sortie'])) ?></span><?php endif; ?>
                        <?php if (!empty($hero_film['views'])): ?><span><i
                                    class="fas fa-eye"></i><?= number_format($hero_film['views'], 0, ',', ' ') ?>
                                <?= h($t['views']) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="cta-row">
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-play"></i>
                        <?= h($t['cta_watch']) ?></a>
                    <a href="#categories" class="btn btn-outline"><?= h($t['cta_browse']) ?></a>
                </div>
            </div>

            <?php if (count($popular_films) >= 3): ?>
                <div class="stack" id="fan">
                    <?php foreach (array_slice($popular_films, 0, 3) as $i => $f): ?>
                        <a href="login.php" class="poster p<?= $i + 1 ?>" aria-label="<?= h($f['nom_film']) ?>"><img
                                src="<?= img($f) ?>" alt="<?= h($f['nom_film']) ?>"></a>
                    <?php endforeach; ?>
                    <svg class="seal" viewBox="0 0 100 100" aria-hidden="true">
                        <defs>
                            <path id="sealPath" d="M50,50 m-37,0 a37,37 0 1,1 74,0 a37,37 0 1,1 -74,0" />
                        </defs>
                        <text>
                            <textPath href="#sealPath" startOffset="0">Now showing · Bertin Movie · Now showing ·
                            </textPath>
                        </text>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        <a class="scroll-cue" href="#classement"><?= h($t['scroll']) ?><span></span></a>
    </header>

    <?php if ($popular_films): ?>
        <div class="ticker" aria-hidden="true">
            <div class="track">
                <?php for ($k = 0; $k < 2; $k++)
                    foreach (array_merge($popular_films, $new_films) as $f): ?><span><?= h($f['nom_film']) ?></span><?php endforeach; ?>
            </div>
        </div>

        <section class="sec" id="classement">
            <div class="wrap">
                <div class="sec-h">
                    <div>
                        <p class="eyebrow"><?= h($t['eyebrow_rank']) ?></p>
                        <h2><?= h($t['h_rank']) ?></h2>
                    </div>
                    <div class="arrows">
                        <button type="button" data-dir="-1" aria-label="Précédent"><i
                                class="fas fa-arrow-left"></i></button>
                        <button type="button" data-dir="1" aria-label="Suivant"><i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                <div class="rail" id="rail">
                    <?php foreach ($popular_films as $i => $f): ?>
                        <a href="login.php" class="t">
                            <span class="rank"><?= $i + 1 ?></span>
                            <div class="pic"><img src="<?= img($f) ?>" alt="<?= h($f['nom_film']) ?>" loading="lazy">
                                <div class="play"><i class="fas fa-play"></i></div>
                            </div>
                            <h3><?= h($f['nom_film']) ?></h3>
                            <div class="meta"><span><i
                                        class="fas fa-eye"></i><?= number_format($f['views'] ?? 0, 0, ',', ' ') ?></span><span><i
                                        class="fas fa-tag"></i><?= h($f['nom_categorie'] ?? $t['no_genre']) ?></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($categories): ?>
        <section class="band" id="categories">
            <div class="wrap">
                <div class="sec-h">
                    <div>
                        <p class="eyebrow"><?= h($t['eyebrow_genres']) ?></p>
                        <h2><?= h($t['h_genres']) ?></h2>
                    </div>
                </div>
                <div class="wall">
                    <?php foreach ($categories as $i => $c): ?>
                        <a href="login.php" class="g" style="--h:<?= $hues[$i % 6] ?>">
                            <span class="g-idx"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                            <i class="fas <?= $cat_icons[$c['nom_categorie']] ?? 'fa-clapperboard' ?> g-ic"></i>
                            <span class="g-name"><?= h($c['nom_categorie']) ?></span>
                            <span class="g-count"><?= (int) $c['total_films'] ?>
                                <?= $c['total_films'] > 1 ? h($t['films']) : h($t['film']) ?></span>
                            <i class="fas fa-arrow-right g-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($new_films): ?>
        <section class="sec" id="nouveautes">
            <div class="wrap">
                <div class="sec-h">
                    <div>
                        <p class="eyebrow"><?= h($t['eyebrow_catalog']) ?></p>
                        <h2><?= h($t['h_new']) ?></h2>
                    </div>
                    <a href="#cta" class="btn btn-outline"><?= h($t['see_all']) ?> <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="bento">
                    <?php foreach ($new_films as $f): ?>
                        <a href="login.php" class="n">
                            <img src="<?= img($f) ?>" alt="<?= h($f['nom_film']) ?>" loading="lazy">
                            <span class="tag"><?= h($t['tag_new']) ?></span>
                            <div class="play"><i class="fas fa-play"></i></div>
                            <div class="cap">
                                <h3><?= h($f['nom_film']) ?></h3>
                                <div class="meta"><span><i
                                            class="fas fa-tag"></i><?= h($f['nom_categorie'] ?? $t['no_genre']) ?></span><?php if (!empty($f['duree'])): ?><span><i
                                                class="fas fa-clock"></i><?= h(duree($f['duree'])) ?></span><?php endif; ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($acteurs): ?>
        <section class="sec" id="acteurs">
            <div class="wrap">
                <div class="sec-h">
                    <div>
                        <p class="eyebrow"><?= h($t['eyebrow_cast']) ?></p>
                        <h2><?= h($t['h_cast']) ?></h2>
                    </div>
                </div>
                <div class="cast">
                    <?php foreach ($acteurs as $a):
                        $nom = trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')); ?>
                        <a href="login.php" class="a">
                            <div class="ava" style="--h:<?= crc32($nom) % 360 ?>">
                                <?= h(mb_strtoupper(mb_substr($a['prenom'] ?: $a['nom'], 0, 1))) ?></div>
                            <b><?= h($nom) ?></b><small><?= (int) $a['total_films'] ?>
                                <?= $a['total_films'] > 1 ? h($t['films']) : h($t['film']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section id="cta" class="sec">
        <div class="wrap">
            <div class="ticket">
                <div class="main">
                    <h2><?= h($t['ticket_title']) ?></h2>
                    <p><?= h($t['ticket_desc']) ?></p>
                    <div class="cta-row">
                        <a href="creation_compte.php" class="btn btn-white"><i class="fas fa-ticket"></i>
                            <?= h($t['create_account']) ?></a>
                        <a href="login.php" class="btn btn-ghost-light"><?= h($t['have_account']) ?></a>
                    </div>
                </div>
                <div class="stub">
                    <div><b><?= $total_films ?></b><span><?= $total_films > 1 ? h($t['films']) : h($t['film']) ?>
                            <?= h($t['to_watch']) ?></span></div>
                    <div><b><?= count($categories) ?></b><span><?= count($categories) > 1 ? h($t['genres']) : h($t['genre']) ?>
                            <?= h($t['to_explore']) ?></span></div>
                    <div class="barcode" aria-hidden="true"></div>
                </div>
                <span class="notch top"></span><span class="notch bottom"></span>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap">
            <a href="#accueil" class="logo">
                <span class="logo-mark"><i class="fas fa-film"></i></span>
                <span class="logo-txt">Bertin<em>Movie</em></span>
            </a>
            <nav>
                <a href="login.php"><?= h($t['login']) ?></a>
                <a href="creation_compte.php"><?= h($t['signup']) ?></a>
                <a href="#"><?= h($t['footer_legal']) ?></a>
                <a href="#"><?= h($t['footer_contact']) ?></a>
            </nav>
            <span>© <?= date('Y') ?> BERTIN MOVIE</span>
        </div>
    </footer>

    <script>
        /* ---------- THEME ---------- */
        const root = document.documentElement;
        const themeToggle = document.getElementById('themeToggle');
        function syncThemeBtn() {
            const isDark = root.getAttribute('data-theme') === 'dark';
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        }
        themeToggle.addEventListener('click', () => {
            const isDark = root.getAttribute('data-theme') === 'dark';
            if (isDark) {
                root.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
            } else {
                root.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            }
            syncThemeBtn();
        });
        syncThemeBtn();

        /* ---------- NAV ---------- */
        const nav = document.getElementById('nav');
        const links = document.getElementById('links');
        const burger = document.getElementById('burger');
        const onScroll = () => nav.classList.toggle('scrolled', scrollY > 40);
        onScroll();
        addEventListener('scroll', onScroll, { passive: true });

        burger.addEventListener('click', () => burger.setAttribute('aria-expanded', links.classList.toggle('open')));
        links.addEventListener('click', e => { if (e.target.closest('a')) links.classList.remove('open'); });
        addEventListener('keydown', e => { if (e.key === 'Escape') links.classList.remove('open'); });

        /* active link on scroll */
        const io = new IntersectionObserver(entries => entries.forEach(en => {
            if (en.isIntersecting) links.querySelectorAll('a').forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + en.target.id));
        }), { rootMargin: '-45% 0px -50% 0px' });
        document.querySelectorAll('header[id],section[id]').forEach(s => io.observe(s));

        /* ranking arrows */
        const rail = document.getElementById('rail');
        if (rail) document.querySelectorAll('.arrows button').forEach(b => b.addEventListener('click', () => {
            rail.scrollBy({ left: b.dataset.dir * (rail.clientWidth * .8), behavior: 'smooth' });
        }));

        /* poster parallax */
        const fan = document.getElementById('fan');
        if (fan && matchMedia('(hover:hover) and (prefers-reduced-motion:no-preference)').matches) {
            const hero = document.getElementById('accueil');
            hero.addEventListener('pointermove', e => {
                fan.style.setProperty('--mx', (e.clientX / innerWidth - .5) * 2);
                fan.style.setProperty('--my', (e.clientY / innerHeight - .5) * 2);
            });
            hero.addEventListener('pointerleave', () => {
                fan.style.setProperty('--mx', 0);
                fan.style.setProperty('--my', 0);
            });
        }
    </script>
</body>

</html>