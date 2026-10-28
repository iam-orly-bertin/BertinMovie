<?php
/**
 * user/includes/support-widget.php : bulle « Contacter l'admin » (chat support, préfixe bms-)
 * À inclure à la fin de menu-user.php, juste après chat-widget.php.
 * Couleurs : variables du site (--bg-card, --bg-body, --border-color...) donc thème sombre/clair automatique.
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') === 'admin') {
    return;
}
require_once __DIR__ . '/../../includes/support_lib.php';
$bms_csrf = support_csrf();
?>
<style>
    /* =========================================================
       CHAT SUPPORT (utilisateur) - préfixe bms-
    ========================================================= */
    .bms-fab {
        position: fixed;
        right: 20px;
        bottom: 90px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        z-index: 9400;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 23px;
        color: #fff;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 10px 28px rgba(59, 130, 246, .5);
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .bms-fab:hover {
        transform: translateY(-3px) scale(1.06);
        box-shadow: 0 14px 34px rgba(59, 130, 246, .6);
    }

    .bms-fab:active {
        transform: scale(.95);
    }

    .bms-fab:focus-visible,
    .bms-btn:focus-visible,
    .bms-x:focus-visible {
        outline: 3px solid rgba(96, 165, 250, .7);
        outline-offset: 2px;
    }

    .bms-badge {
        box-sizing: border-box;
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 11px;
        background: #ef4444;
        color: #fff;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
        display: none;
        border: 2px solid var(--bg-body, #0f172a);
        animation: bmsPulse 2s infinite;
    }

    @keyframes bmsPulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, .55);
        }

        50% {
            box-shadow: 0 0 0 7px rgba(239, 68, 68, 0);
        }
    }

    .bms-panel {
        position: fixed;
        right: 20px;
        bottom: 20px;
        width: 384px;
        max-width: calc(100vw - 20px);
        height: 600px;
        max-height: calc(100vh - 40px);
        max-height: calc(100dvh - 40px);
        z-index: 9600;
        display: none;
        flex-direction: column;
        border-radius: 22px;
        overflow: hidden;
        background: var(--bg-card, #1e293b);
        color: var(--text-primary, #fff);
        border: 1px solid var(--border-color, #334155);
        box-shadow: 0 24px 64px rgba(2, 6, 23, .5);
        font-family: inherit;
    }

    .bms-panel.bms-open {
        display: flex;
        animation: bmsIn .28s cubic-bezier(.2, .8, .2, 1);
    }

    @keyframes bmsIn {
        from {
            opacity: 0;
            transform: translateY(18px) scale(.97);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .bms-head {
        position: relative;
        flex: none;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 16px 15px;
        color: #fff;
        background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 62%, #60a5fa 100%);
        overflow: hidden;
    }

    .bms-head::after {
        content: '';
        position: absolute;
        right: -40px;
        top: -60px;
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .1);
        pointer-events: none;
    }

    .bms-av {
        position: relative;
        flex: none;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 19px;
        background: rgba(255, 255, 255, .2);
    }

    .bms-dot {
        position: absolute;
        right: 0;
        bottom: 1px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #22c55e;
        border: 2px solid #2563eb;
    }

    .bms-ht {
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .bms-ht b {
        display: block;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .bms-ht small {
        display: block;
        font-size: 12.5px;
        opacity: .9;
        margin-top: 1px;
    }

    .bms-x {
        position: relative;
        z-index: 1;
        margin-left: auto;
        flex: none;
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        color: #fff;
        background: rgba(255, 255, 255, .18);
        transition: background .2s;
    }

    .bms-x:hover {
        background: rgba(255, 255, 255, .3);
    }

    .bms-list {
        position: relative;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding: 16px 14px;
        display: flex;
        flex-direction: column;
        gap: 3px;
        background: var(--bg-body, #0f172a);
    }

    .bms-list::-webkit-scrollbar {
        width: 6px;
    }

    .bms-list::-webkit-scrollbar-thumb {
        background: var(--border-color, #334155);
        border-radius: 3px;
    }

    .bms-load {
        margin: auto;
        font-size: 26px;
        color: #3b82f6;
    }

    .bms-empty {
        margin: auto;
        max-width: 260px;
        text-align: center;
        font-size: 14px;
        line-height: 1.55;
        color: var(--text-secondary, #94a3b8);
        padding: 20px 10px;
    }

    .bms-empty .bms-ei {
        width: 72px;
        height: 72px;
        margin: 0 auto 14px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 30px;
        color: #3b82f6;
        background: rgba(59, 130, 246, .12);
    }

    .bms-empty b {
        display: block;
        color: var(--text-primary, #fff);
        font-size: 17px;
        margin-bottom: 4px;
    }

    .bms-day {
        align-self: center;
        margin: 12px 0 8px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-secondary, #94a3b8);
        background: var(--bg-card, #1e293b);
        border: 1px solid var(--border-color, #334155);
    }

    .bms-row {
        display: flex;
        margin-top: 8px;
        animation: bmsMsg .22s ease;
    }

    .bms-row.bms-cont {
        margin-top: 0;
    }

    @keyframes bmsMsg {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .bms-row.bms-me {
        justify-content: flex-end;
    }

    .bms-bubble {
        max-width: 82%;
        padding: 9px 13px 6px;
        border-radius: 18px;
        font-size: 14.5px;
        line-height: 1.45;
        word-break: break-word;
        overflow-wrap: anywhere;
        background: var(--bg-card, #1e293b);
        color: var(--text-primary, #fff);
        border: 1px solid var(--border-color, #334155);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
    }

    .bms-them .bms-bubble {
        border-bottom-left-radius: 6px;
    }

    .bms-me .bms-bubble {
        border: none;
        color: #fff;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border-bottom-right-radius: 6px;
        box-shadow: 0 4px 14px rgba(37, 99, 235, .28);
    }

    .bms-cont.bms-them .bms-bubble {
        border-top-left-radius: 6px;
    }

    .bms-cont.bms-me .bms-bubble {
        border-top-right-radius: 6px;
    }

    .bms-text {
        white-space: pre-wrap;
    }

    .bms-text a {
        color: inherit;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .bms-them .bms-text a {
        color: #3b82f6;
    }

    .bms-meta {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
        font-size: 10.5px;
        opacity: .72;
    }

    .bms-tick {
        font-size: 11px;
        letter-spacing: -2px;
    }

    .bms-tick.bms-rd {
        color: #bae6fd;
        opacity: 1;
    }

    .bms-img {
        display: block;
        max-width: 100%;
        max-height: 240px;
        border-radius: 12px;
        margin: 0 0 5px;
        cursor: zoom-in;
        background: rgba(127, 127, 127, .15);
    }

    .bms-file {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 190px;
        margin: 0 0 5px;
        padding: 8px 10px;
        border-radius: 12px;
        color: inherit;
        text-decoration: none;
        background: rgba(127, 127, 127, .14);
        transition: background .2s;
    }

    .bms-file:hover {
        background: rgba(127, 127, 127, .24);
    }

    .bms-me .bms-file {
        background: rgba(255, 255, 255, .17);
    }

    .bms-fi {
        flex: none;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        font-size: 19px;
        background: rgba(59, 130, 246, .15);
        color: #3b82f6;
    }

    .bms-fi.t-pdf {
        background: rgba(239, 68, 68, .15);
        color: #ef4444;
    }

    .bms-fi.t-word {
        background: rgba(59, 130, 246, .15);
        color: #3b82f6;
    }

    .bms-fi.t-excel {
        background: rgba(34, 197, 94, .15);
        color: #22c55e;
    }

    .bms-fi.t-ppt {
        background: rgba(249, 115, 22, .15);
        color: #f97316;
    }

    .bms-fi.t-zip {
        background: rgba(245, 158, 11, .15);
        color: #f59e0b;
    }

    .bms-fi.t-media {
        background: rgba(139, 92, 246, .15);
        color: #8b5cf6;
    }

    .bms-me .bms-fi {
        background: rgba(255, 255, 255, .22);
        color: #fff;
    }

    .bms-fn {
        min-width: 0;
        font-size: 13.5px;
        font-weight: 600;
    }

    .bms-fn span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 170px;
    }

    .bms-fn small {
        display: block;
        font-weight: 400;
        font-size: 11.5px;
        opacity: .75;
        margin-top: 1px;
    }

    .bms-down {
        position: absolute;
        left: 50%;
        bottom: 96px;
        transform: translateX(-50%);
        z-index: 3;
        display: none;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 600;
        color: #fff;
        background: #2563eb;
        box-shadow: 0 6px 18px rgba(37, 99, 235, .45);
    }

    .bms-foot {
        flex: none;
        padding: 10px 12px 12px;
        background: var(--bg-card, #1e293b);
        border-top: 1px solid var(--border-color, #334155);
    }

    .bms-err {
        display: none;
        margin-bottom: 8px;
        padding: 8px 12px;
        border-radius: 10px;
        font-size: 12.5px;
        font-weight: 500;
        color: #ef4444;
        background: rgba(239, 68, 68, .12);
        border: 1px solid rgba(239, 68, 68, .3);
    }

    .bms-chip {
        display: none;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
        padding: 7px 8px 7px 8px;
        border-radius: 14px;
        background: rgba(59, 130, 246, .1);
        border: 1px solid rgba(59, 130, 246, .25);
    }

    .bms-chip .bms-fi {
        width: 38px;
        height: 38px;
        overflow: hidden;
    }

    .bms-chip .bms-fi img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .bms-chip .bms-fn {
        flex: 1;
    }

    .bms-chip .bms-fn span {
        max-width: 200px;
    }

    .bms-chip button {
        flex: none;
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        color: var(--text-secondary, #94a3b8);
        background: transparent;
    }

    .bms-chip button:hover {
        background: rgba(127, 127, 127, .2);
    }

    .bms-box {
        display: flex;
        align-items: flex-end;
        gap: 4px;
        padding: 5px;
        border-radius: 26px;
        border: 1px solid var(--border-color, #334155);
        background: var(--bg-input, var(--bg-body, #0f172a));
        transition: border-color .2s, box-shadow .2s;
    }

    .bms-box:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
    }

    .bms-box textarea {
        flex: 1;
        min-width: 0;
        resize: none;
        max-height: 120px;
        min-height: 38px;
        padding: 9px 6px;
        border: none;
        outline: none;
        background: transparent;
        color: var(--text-primary, #fff);
        font-family: inherit;
        font-size: 14.5px;
        line-height: 1.4;
    }

    .bms-box textarea::placeholder {
        color: var(--text-muted, #64748b);
    }

    .bms-btn {
        flex: none;
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        display: grid;
        place-items: center;
        transition: transform .15s, opacity .2s, background .2s;
    }

    .bms-attach {
        color: var(--text-secondary, #94a3b8);
        background: transparent;
    }

    .bms-attach:hover {
        background: rgba(59, 130, 246, .12);
        color: #3b82f6;
    }

    .bms-send {
        color: #fff;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        box-shadow: 0 4px 12px rgba(37, 99, 235, .4);
    }

    .bms-send:hover {
        transform: scale(1.06);
    }

    .bms-send:disabled,
    .bms-send.bms-off {
        opacity: .45;
        cursor: default;
        transform: none;
        box-shadow: none;
    }

    .bms-drop {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-weight: 600;
        color: #3b82f6;
        background: rgba(15, 23, 42, .92);
        background: color-mix(in srgb, var(--bg-card, #1e293b) 92%, transparent);
        border: 3px dashed #3b82f6;
        border-radius: 22px;
        pointer-events: none;
    }

    .bms-drop i {
        font-size: 38px;
    }

    .bms-panel.bms-drag .bms-drop {
        display: flex;
    }

    .bms-lb {
        position: fixed;
        inset: 0;
        z-index: 10100;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(2, 6, 23, .9);
    }

    .bms-lb.bms-open {
        display: flex;
    }

    .bms-lb img {
        max-width: 94vw;
        max-height: 84vh;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
    }

    .bms-lb-bar {
        position: absolute;
        top: 14px;
        right: 14px;
        display: flex;
        gap: 10px;
    }

    .bms-lb-bar a,
    .bms-lb-bar button {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 17px;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        background: rgba(255, 255, 255, .16);
    }

    @media (max-width: 768px) {
        .bms-fab {
            right: 14px;
            width: 54px;
            height: 54px;
            bottom: calc(64px + 18px + env(safe-area-inset-bottom, 0px));
        }

        .bms-panel {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100%;
            max-width: 100%;
            height: 100vh;
            height: 100dvh;
            max-height: none;
            border-radius: 0;
            border: none;
            z-index: 10001;
        }

        .bms-panel.bms-open {
            animation: bmsUp .3s cubic-bezier(.2, .8, .2, 1);
        }

        @keyframes bmsUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }

            to {
                transform: none;
                opacity: 1;
            }
        }

        .bms-head {
            padding-top: calc(14px + env(safe-area-inset-top, 0px));
        }

        .bms-foot {
            padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
        }

        .bms-box textarea {
            font-size: 16px;
        }

        /* évite le zoom automatique sur iPhone */
        .bms-bubble {
            max-width: 88%;
        }

        .bms-down {
            bottom: calc(100px + env(safe-area-inset-bottom, 0px));
        }

        .bms-drop {
            border-radius: 0;
        }

        html.bms-lock,
        html.bms-lock body {
            overflow: hidden !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .bms-panel.bms-open,
        .bms-row,
        .bms-badge {
            animation: none;
        }
    }
</style>

<button class="bms-fab" id="bmsFab" type="button" aria-label="Contacter l'admin" title="Contacter l'admin">
    <i class="fas fa-headset"></i><span class="bms-badge" id="bmsBadge">0</span>
</button>

<div class="bms-panel" id="bmsPanel" role="dialog" aria-label="Chat avec l'admin">
    <div class="bms-head">
        <div class="bms-av"><i class="fas fa-headset"></i><span class="bms-dot"></span></div>
        <div class="bms-ht"><b id="bmsTitle"></b><small id="bmsSub"></small></div>
        <button class="bms-x" id="bmsClose" type="button" aria-label="Fermer"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="bms-list" id="bmsList">
        <div class="bms-load"><i class="fas fa-circle-notch fa-spin"></i></div>
    </div>
    <button class="bms-down" id="bmsDown" type="button"><i class="fas fa-arrow-down"></i><span
            id="bmsDownT"></span></button>
    <div class="bms-foot">
        <div class="bms-err" id="bmsErr" role="alert"></div>
        <div class="bms-chip" id="bmsChip">
            <div class="bms-fi" id="bmsChipIco"></div>
            <div class="bms-fn"><span id="bmsChipName"></span><small id="bmsChipSize"></small></div>
            <button type="button" id="bmsChipX" aria-label="Retirer"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="bms-box">
            <input type="file" id="bmsFile" hidden
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp3,.mp4">
            <button class="bms-btn bms-attach" id="bmsAttach" type="button" aria-label="Joindre un fichier"><i
                    class="fas fa-paperclip"></i></button>
            <textarea id="bmsText" rows="1" maxlength="2000"></textarea>
            <button class="bms-btn bms-send bms-off" id="bmsSend" type="button" aria-label="Envoyer"><i
                    class="fas fa-paper-plane"></i></button>
        </div>
    </div>
    <div class="bms-drop"><i class="fas fa-cloud-arrow-up"></i><span id="bmsDropT"></span></div>
</div>

<div class="bms-lb" id="bmsLb">
    <div class="bms-lb-bar"><a id="bmsLbDl" href="#" aria-label="Télécharger"><i class="fas fa-download"></i></a><button
            type="button" id="bmsLbX" aria-label="Fermer"><i class="fas fa-xmark"></i></button></div>
    <img id="bmsLbImg" alt="">
</div>

<script>
    (function () {
        var API = 'support_api.php', CSRF = <?= json_encode($bms_csrf) ?>, MAX = 8 * 1024 * 1024;
        var lang = 'fr'; try { lang = localStorage.getItem('lang') === 'en' ? 'en' : 'fr'; } catch (e) { }
        var T = {
            fr: { title: 'Support BERTIN MOVIE', sub: "Réponse dans cette fenêtre", ph: 'Écrire un message…', hi: 'Bonjour 👋', empty: "Posez votre question ou envoyez un fichier : l'administrateur vous répondra ici.", today: "Aujourd'hui", big: 'Fichier trop volumineux (8 Mo maximum).', ext: 'Type de fichier non autorisé.', net: 'Connexion perdue, nouvelle tentative…', sess: 'Session expirée, rechargez la page.', drop: 'Déposez le fichier ici', newm: 'Nouveau message', newms: ' nouveaux messages' },
            en: { title: 'BERTIN MOVIE Support', sub: 'Reply in this window', ph: 'Write a message…', hi: 'Hello 👋', empty: 'Ask your question or send a file: the administrator will reply here.', today: 'Today', big: 'File too large (8 MB max).', ext: 'File type not allowed.', net: 'Connection lost, retrying…', sess: 'Session expired, reload the page.', drop: 'Drop the file here', newm: 'New message', newms: ' new messages' }
        }[lang];
        var $ = function (id) { return document.getElementById(id); };
        var fab = $('bmsFab'), badge = $('bmsBadge'), panel = $('bmsPanel'), list = $('bmsList'), txt = $('bmsText'), send = $('bmsSend'),
            fileIn = $('bmsFile'), chip = $('bmsChip'), err = $('bmsErr'), down = $('bmsDown'), root = document.documentElement;
        $('bmsTitle').textContent = T.title; $('bmsSub').textContent = T.sub; txt.placeholder = T.ph; $('bmsDropT').textContent = T.drop;
        var OK_EXT = /\.(jpe?g|png|gif|webp|pdf|txt|zip|docx?|xlsx?|pptx?|mp3|mp4)$/i;
        var coarse = window.matchMedia && matchMedia('(pointer: coarse)').matches;

        var lastId = 0, peerRead = 0, isOpen = false, busy = false, pending = false, timer = null, fails = 0, seen = {}, loaded = false,
            lastDay = '', lastFrom = '', lastTs = 0, unseen = 0, chipUrl = null;

        function showErr(m) { err.textContent = m || ''; err.style.display = m ? 'block' : 'none'; }
        function setBadge(n) { badge.textContent = n > 99 ? '99+' : n; badge.style.display = n > 0 && !isOpen ? 'block' : 'none'; }
        function toDate(s) { return new Date(s.replace(' ', 'T') + 'Z'); }
        function dayLabel(d) { return d.toDateString() === new Date().toDateString() ? T.today : d.toLocaleDateString(lang === 'en' ? 'en-GB' : 'fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }); }
        function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' Mo' : Math.max(1, Math.round(n / 1024)) + ' Ko'; }
        function near() { return list.scrollHeight - list.scrollTop - list.clientHeight < 90; }
        function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
        function kind(name) {
            var x = (name.split('.').pop() || '').toLowerCase();
            if (x === 'pdf') { return ['fa-file-pdf', 't-pdf']; }
            if (x === 'doc' || x === 'docx') { return ['fa-file-word', 't-word']; }
            if (x === 'xls' || x === 'xlsx') { return ['fa-file-excel', 't-excel']; }
            if (x === 'ppt' || x === 'pptx') { return ['fa-file-powerpoint', 't-ppt']; }
            if (x === 'zip') { return ['fa-file-zipper', 't-zip']; }
            if (x === 'mp3') { return ['fa-file-audio', 't-media']; }
            if (x === 'mp4') { return ['fa-file-video', 't-media']; }
            if (x === 'txt') { return ['fa-file-lines', '']; }
            return ['fa-file', ''];
        }
        function linkify(t, box) {
            var re = /(https?:\/\/[^\s<]*[^\s<.,;:!?)\]"'])/g, last = 0, m;
            while ((m = re.exec(t))) {
                if (m.index > last) { box.appendChild(document.createTextNode(t.slice(last, m.index))); }
                var a = el('a', '', m[0]); a.href = m[0]; a.target = '_blank'; a.rel = 'noopener noreferrer'; box.appendChild(a);
                last = m.index + m[0].length;
            }
            if (last < t.length) { box.appendChild(document.createTextNode(t.slice(last))); }
        }

        /* ---------- affichage d'un message ---------- */
        function render(m) {
            if (seen[m.id]) { return false; }
            seen[m.id] = 1;
            var ld = list.querySelector('.bms-load, .bms-empty'); if (ld) { ld.remove(); }
            var d = toDate(m.t), dk = d.toDateString(), mine = m.from === 'user';
            if (dk !== lastDay) { lastDay = dk; lastFrom = ''; list.appendChild(el('div', 'bms-day', dayLabel(d))); }
            var cont = lastFrom === m.from && (d - lastTs) < 300000;
            lastFrom = m.from; lastTs = +d;
            var row = el('div', 'bms-row ' + (mine ? 'bms-me' : 'bms-them') + (cont ? ' bms-cont' : '')); row.dataset.id = m.id;
            var b = el('div', 'bms-bubble');
            if (m.file) {
                var url = 'support_file.php?id=' + m.id;
                if (m.file.image) {
                    var im = el('img', 'bms-img'); im.src = url; im.alt = m.file.name; im.loading = 'lazy';
                    im.addEventListener('click', function () { lightbox(url); });
                    im.addEventListener('load', function () { if (stickNext) { list.scrollTop = list.scrollHeight; } });
                    b.appendChild(im);
                } else {
                    var k = kind(m.file.name), f = el('a', 'bms-file'); f.href = url + '&dl=1';
                    var ic = el('div', 'bms-fi ' + k[1]); ic.innerHTML = '<i class="fas ' + k[0] + '"></i>'; f.appendChild(ic);
                    var fn = el('div', 'bms-fn'); fn.appendChild(el('span', '', m.file.name)); fn.appendChild(el('small', '', size(m.file.size))); f.appendChild(fn);
                    b.appendChild(f);
                }
            }
            if (m.text) { var p = el('div', 'bms-text'); linkify(m.text, p); b.appendChild(p); }
            var meta = el('div', 'bms-meta'); meta.appendChild(el('span', '', d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })));
            if (mine) { meta.appendChild(el('span', 'bms-tick' + (m.id <= peerRead ? ' bms-rd' : ''), m.id <= peerRead ? '✓✓' : '✓')); }
            b.appendChild(meta); row.appendChild(b); list.appendChild(row);
            if (m.id > lastId) { lastId = m.id; }
            return !mine;
        }
        var stickNext = false;
        function ticks() { list.querySelectorAll('.bms-me').forEach(function (r) { var t = r.querySelector('.bms-tick'); if (t) { var rd = +r.dataset.id <= peerRead; t.textContent = rd ? '✓✓' : '✓'; t.classList.toggle('bms-rd', rd); } }); }
        function emptyState() {
            if (list.querySelector('.bms-row')) { return; }
            var ld = list.querySelector('.bms-load'); if (ld) { ld.remove(); }
            var e = el('div', 'bms-empty'); e.innerHTML = '<div class="bms-ei"><i class="fas fa-comments"></i></div><b></b><span></span>';
            e.querySelector('b').textContent = T.hi; e.querySelector('span').textContent = T.empty; list.appendChild(e);
        }
        function toBottom() { list.scrollTop = list.scrollHeight; unseen = 0; down.style.display = 'none'; }
        list.addEventListener('scroll', function () { if (near()) { unseen = 0; down.style.display = 'none'; } });
        down.addEventListener('click', toBottom);

        /* ---------- polling ---------- */
        function tick() {
            if (busy) { pending = true; return; } busy = true;
            var url = isOpen ? API + '?action=poll&open=1&after=' + lastId : API + '?action=count';
            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { if (r.status === 401) { throw 'session'; } if (!r.ok) { throw 'http'; } return r.json(); })
                .then(function (j) {
                    fails = 0; showErr('');
                    if (isOpen) {
                        var stick = near() || !loaded, incoming = 0;
                        stickNext = stick;
                        (j.messages || []).forEach(function (m) { if (render(m)) { incoming++; } });
                        peerRead = j.peer_read || 0; ticks();
                        if (!loaded) { loaded = true; emptyState(); }
                        if (stick) { toBottom(); }
                        else if (incoming) { unseen += incoming; $('bmsDownT').textContent = unseen > 1 ? ' ' + unseen + T.newms : ' ' + T.newm; down.style.display = 'flex'; }
                        setBadge(0);
                    } else { setBadge(j.unread || 0); }
                })
                .catch(function (e) { if (e === 'session') { showErr(T.sess); fails = 8; } else { fails++; if (isOpen && fails > 2) { showErr(T.net); } } })
                .then(function () { busy = false; if (pending) { pending = false; tick(); return; } schedule(); });
        }
        function schedule() {
            clearTimeout(timer); if (document.hidden) { return; }
            var d = isOpen ? 3000 : 25000; if (fails) { d = Math.min(60000, d * (1 + fails)); }
            timer = setTimeout(tick, d);
        }
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { clearTimeout(timer); tick(); } });

        function open() {
            isOpen = true; panel.classList.add('bms-open'); fab.style.display = 'none'; root.classList.add('bms-lock'); setBadge(0);
            clearTimeout(timer); tick(); if (!coarse) { setTimeout(function () { txt.focus(); }, 200); }
        }
        function close() { isOpen = false; panel.classList.remove('bms-open'); fab.style.display = 'flex'; root.classList.remove('bms-lock'); schedule(); }
        fab.addEventListener('click', open); $('bmsClose').addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if ($('bmsLb').classList.contains('bms-open')) { closeLb(); } else if (isOpen) { close(); } } });
        window.bmsOpenSupport = open;   // utilisable depuis un lien : onclick="bmsOpenSupport()"

        /* ---------- visionneuse d'image ---------- */
        function lightbox(url) { $('bmsLbImg').src = url; $('bmsLbDl').href = url + '&dl=1'; $('bmsLb').classList.add('bms-open'); }
        function closeLb() { $('bmsLb').classList.remove('bms-open'); $('bmsLbImg').removeAttribute('src'); }
        $('bmsLbX').addEventListener('click', closeLb);
        $('bmsLb').addEventListener('click', function (e) { if (e.target === this) { closeLb(); } });

        /* ---------- saisie et pièce jointe ---------- */
        function upd() { send.classList.toggle('bms-off', !txt.value.trim() && !fileIn.files.length); }
        function grow() { txt.style.height = 'auto'; txt.style.height = Math.min(txt.scrollHeight, 120) + 'px'; upd(); }
        txt.addEventListener('input', grow);
        txt.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !coarse) { e.preventDefault(); submit(); } });
        $('bmsAttach').addEventListener('click', function () { fileIn.click(); });
        function clearFile() { fileIn.value = ''; chip.style.display = 'none'; if (chipUrl) { URL.revokeObjectURL(chipUrl); chipUrl = null; } upd(); }
        function setFile(f) {
            if (!f) { return; }
            if (!OK_EXT.test(f.name)) { showErr(T.ext); fileIn.value = ''; return; }
            if (f.size > MAX) { showErr(T.big); fileIn.value = ''; return; }
            showErr('');
            try { var dt = new DataTransfer(); dt.items.add(f); fileIn.files = dt.files; } catch (e) { }
            var ico = $('bmsChipIco'), k = kind(f.name); ico.className = 'bms-fi ' + k[1]; ico.textContent = '';
            if (/^image\//.test(f.type)) { chipUrl = URL.createObjectURL(f); var im = document.createElement('img'); im.src = chipUrl; ico.appendChild(im); }
            else { ico.innerHTML = '<i class="fas ' + k[0] + '"></i>'; }
            $('bmsChipName').textContent = f.name; $('bmsChipSize').textContent = size(f.size); chip.style.display = 'flex'; upd();
        }
        fileIn.addEventListener('change', function () { setFile(fileIn.files[0]); });
        $('bmsChipX').addEventListener('click', clearFile);
        txt.addEventListener('paste', function (e) {   // coller une image (capture d'écran)
            var items = (e.clipboardData || {}).items || [];
            for (var i = 0; i < items.length; i++) { if (items[i].kind === 'file') { var f = items[i].getAsFile(); if (f) { if (!f.name || f.name === 'image.png') { try { f = new File([f], 'capture-' + Date.now() + '.png', { type: f.type }); } catch (x) { } } setFile(f); e.preventDefault(); return; } } }
        });
        var dragN = 0;
        panel.addEventListener('dragenter', function (e) { e.preventDefault(); dragN++; panel.classList.add('bms-drag'); });
        panel.addEventListener('dragover', function (e) { e.preventDefault(); });
        panel.addEventListener('dragleave', function () { if (--dragN <= 0) { dragN = 0; panel.classList.remove('bms-drag'); } });
        panel.addEventListener('drop', function (e) { e.preventDefault(); dragN = 0; panel.classList.remove('bms-drag'); if (e.dataTransfer && e.dataTransfer.files[0]) { setFile(e.dataTransfer.files[0]); } });

        /* ---------- envoi ---------- */
        function submit() {
            var text = txt.value.trim(), f = fileIn.files[0];
            if ((!text && !f) || send.disabled) { return; }
            var fd = new FormData(); fd.append('action', 'send'); fd.append('contenu', text); if (f) { fd.append('fichier', f); }
            send.disabled = true; showErr('');
            fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (x) {
                    if (!x.ok) { showErr(x.j.error || 'Erreur'); return; }
                    txt.value = ''; clearFile(); grow(); stickNext = true;
                    render(x.j.message); toBottom();
                })
                .catch(function () { showErr(T.net); })
                .then(function () { send.disabled = false; if (!coarse) { txt.focus(); } });
        }
        send.addEventListener('click', submit);

        tick();   // premier contrôle des messages non lus (pastille)
    })();
</script>