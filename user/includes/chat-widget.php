<?php
/**
 * user/includes/chat-widget.php : bulle de chat IA flottante (BERTIN MOVIE)
 * Inclus à la fin de user/includes/menu-user.php, donc présente sur toutes les pages utilisateur.
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (empty($_SESSION['user_id'])) {
    return; // pas de chat pour un visiteur non connecté
}
if (empty($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(16));
}
$bm_chat_csrf = $_SESSION['chat_csrf'];
?>
<style>
    /* =========================================================
       CHAT IA : styles (préfixe bmc-), suit le thème sombre/clair du site
       ========================================================= */
    .bmc-fab {
        position: fixed;
        right: 20px;
        bottom: 20px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        z-index: 9500;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #fff;
        background: var(--primary-gradient, linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%));
        box-shadow: 0 8px 24px rgba(59, 130, 246, .45);
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .bmc-fab:hover {
        transform: scale(1.08);
        box-shadow: 0 12px 32px rgba(59, 130, 246, .6);
    }

    .bmc-fab:focus-visible,
    .bmc-btn:focus-visible,
    .bmc-send:focus-visible,
    .bmc-chip:focus-visible {
        outline: 2px solid var(--primary-light, #3b82f6);
        outline-offset: 2px;
    }

    .bmc-panel {
        position: fixed;
        right: 20px;
        top: 84px;
        width: 380px;
        max-width: calc(100vw - 24px);
        height: 560px;
        max-height: calc(100vh - 100px);
        z-index: 9500;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border-radius: 18px;
        background: var(--bg-card, #1e293b);
        color: var(--text-primary, #fff);
        border: 1px solid var(--border-color, #334155);
        box-shadow: var(--shadow-hover, 0 12px 48px rgba(0, 0, 0, .6));
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .bmc-panel.bmc-open {
        display: flex;
        animation: bmcIn .25s ease;
    }

    @keyframes bmcIn {
        from {
            opacity: 0;
            transform: translateY(12px) scale(.98);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .bmc-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 14px 14px 16px;
        color: #fff;
        background: var(--primary-gradient, linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%));
    }

    .bmc-head-ico {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .bmc-head-txt {
        flex: 1;
        min-width: 0;
    }

    .bmc-title {
        font-weight: 700;
        font-size: 15px;
        line-height: 1.2;
    }

    .bmc-sub {
        font-size: 12px;
        opacity: .85;
    }

    .bmc-btn {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        cursor: pointer;
        font-size: 14px;
        transition: background .2s ease;
    }

    .bmc-btn:hover {
        background: rgba(255, 255, 255, .3);
    }

    .bmc-msgs {
        flex: 1;
        overflow-y: auto;
        padding: 16px 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: var(--bg-body, #0f172a);
        scroll-behavior: smooth;
    }

    .bmc-m {
        max-width: 88%;
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.5;
        word-wrap: break-word;
        overflow-wrap: anywhere;
    }

    .bmc-m.bmc-bot {
        align-self: flex-start;
        background: var(--bg-card, #1e293b);
        border: 1px solid var(--border-color, #334155);
        border-bottom-left-radius: 4px;
    }

    .bmc-m.bmc-me {
        align-self: flex-end;
        color: #fff;
        background: var(--primary-gradient, linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%));
        border-bottom-right-radius: 4px;
        white-space: pre-wrap;
    }

    .bmc-m.bmc-err {
        border-color: var(--danger, #ef4444);
        color: var(--danger, #ef4444);
    }

    .bmc-m p {
        margin: 0 0 8px;
    }

    .bmc-m p:last-child,
    .bmc-m ul:last-child,
    .bmc-m ol:last-child {
        margin-bottom: 0;
    }

    .bmc-m ul,
    .bmc-m ol {
        margin: 0 0 8px;
        padding-left: 20px;
    }

    .bmc-m li {
        margin: 2px 0;
    }

    .bmc-m a {
        color: var(--primary-light, #3b82f6);
        font-weight: 600;
        text-decoration: underline;
    }

    .bmc-dots {
        display: inline-flex;
        gap: 4px;
        padding: 4px 0;
    }

    .bmc-dots i {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--text-secondary, #94a3b8);
        animation: bmcDot 1.2s infinite ease-in-out;
    }

    .bmc-dots i:nth-child(2) {
        animation-delay: .15s;
    }

    .bmc-dots i:nth-child(3) {
        animation-delay: .3s;
    }

    @keyframes bmcDot {

        0%,
        80%,
        100% {
            opacity: .25;
            transform: translateY(0);
        }

        40% {
            opacity: 1;
            transform: translateY(-3px);
        }
    }

    .bmc-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 4px;
    }

    .bmc-chip {
        border: 1px solid var(--border-color, #334155);
        background: var(--bg-card, #1e293b);
        color: var(--text-primary, #fff);
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 13px;
        cursor: pointer;
        transition: border-color .2s ease, color .2s ease;
    }

    .bmc-chip:hover {
        border-color: var(--primary-light, #3b82f6);
        color: var(--primary-light, #3b82f6);
    }

    .bmc-form {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
        border-top: 1px solid var(--border-color, #334155);
        background: var(--bg-card, #1e293b);
    }

    .bmc-input {
        flex: 1;
        resize: none;
        max-height: 96px;
        min-height: 40px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid var(--border-color, #334155);
        background: var(--bg-input, #1e293b);
        color: var(--text-primary, #fff);
        font: inherit;
        font-size: 14px;
        line-height: 1.4;
        outline: none;
    }

    .bmc-input:focus {
        border-color: var(--primary-light, #3b82f6);
    }

    .bmc-input::placeholder {
        color: var(--text-muted, #64748b);
    }

    .bmc-send {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        color: #fff;
        font-size: 15px;
        background: var(--primary-gradient, linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%));
        transition: opacity .2s ease, transform .2s ease;
    }

    .bmc-send:hover:not(:disabled) {
        transform: scale(1.06);
    }

    .bmc-send:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    /* La liste des messages doit pouvoir rétrécir (sinon la zone de saisie est poussée hors de l'écran) */
    .bmc-msgs {
        min-height: 0;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    /* Page figée derrière le chat plein écran (téléphone) */
    html.bmc-lock,
    html.bmc-lock body {
        overflow: hidden !important;
    }

    /* =========================================================
       VERSION TÉLÉPHONE (écran <= 520 px)
       - plein écran, hauteur = partie réellement visible (dvh + visualViewport en JS)
       - la zone de saisie reste au-dessus du clavier
       - texte de saisie à 16 px : sinon Safari (iPhone) zoome tout seul sur le champ
       - boutons assez grands pour le doigt (44 px)
       ========================================================= */
    @media (max-width: 520px) {
        .bmc-panel {
            right: 0;
            top: 0;
            left: 0;
            width: 100%;
            max-width: 100%;
            height: 100vh;
            height: 100dvh;
            max-height: 100vh;
            max-height: 100dvh;
            border-radius: 0;
            border: none;
        }

        .bmc-head {
            padding-top: calc(12px + env(safe-area-inset-top, 0px));
            padding-bottom: 12px;
            flex-shrink: 0;
        }

        .bmc-btn {
            width: 40px;
            height: 40px;
            font-size: 16px;
        }

        .bmc-form {
            flex-shrink: 0;
            padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
            gap: 8px;
        }

        .bmc-input {
            font-size: 16px;
            min-height: 44px;
            padding: 11px 12px;
        }

        .bmc-send {
            width: 44px;
            height: 44px;
            font-size: 16px;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .bmc-panel.bmc-open,
        .bmc-dots i {
            animation: none;
        }

        .bmc-msgs {
            scroll-behavior: auto;
        }
    }
</style>

<section class="bmc-panel" id="bmcPanel" role="dialog" aria-labelledby="bmcTitle">
    <header class="bmc-head">
        <div class="bmc-head-ico"><i class="fas fa-robot"></i></div>
        <div class="bmc-head-txt">
            <div class="bmc-title" id="bmcTitle">Assistant BERTIN MOVIE</div>
            <div class="bmc-sub" id="bmcSub"></div>
        </div>
        <button type="button" class="bmc-btn" id="bmcClear" title=""><i class="fas fa-trash-can"></i></button>
        <button type="button" class="bmc-btn" id="bmcClose" title=""><i class="fas fa-xmark"></i></button>
    </header>

    <div class="bmc-msgs" id="bmcMsgs" aria-live="polite"></div>

    <form class="bmc-form" id="bmcForm" autocomplete="off">
        <textarea class="bmc-input" id="bmcInput" rows="1" maxlength="500"></textarea>
        <button type="submit" class="bmc-send" id="bmcSend"><i class="fas fa-paper-plane"></i></button>
    </form>
</section>

<script>
    (function () {
        'use strict';

        var API = '/film_web_dy/user/chat_api.php';
        var LINK_BASE = '/film_web_dy/user/';
        var CSRF = <?= json_encode($bm_chat_csrf) ?>;
        var STORE = 'bmChat';
        var MAX_STORED = 30;
        var HISTORY_SENT = 8;

        var T = {
            fr: {
                title: 'Assistant BERTIN MOVIE',
                sub: 'Posez vos questions sur les films',
                placeholder: 'Écrivez votre message…',
                send: 'Envoyer',
                clear: 'Effacer la conversation',
                close: 'Fermer',
                open: 'Ouvrir l’assistant',
                hello: 'Bonjour ! Je peux vous aider à trouver un film, retrouver vos favoris ou votre historique, ou vous expliquer comment fonctionne le site.',
                chips: ['Quels films d’action avez-vous ?', 'Que me conseillez-vous ce soir ?', 'Montre mes favoris', 'Comment ajouter un favori ?'],
                empty: 'Je n’ai pas pu générer de réponse. Réessayez.',
                offline: 'Impossible de joindre le serveur. Vérifiez votre connexion.',
                generic: 'Une erreur est survenue. Réessayez dans un instant.'
            },
            en: {
                title: 'BERTIN MOVIE Assistant',
                sub: 'Ask me anything about the movies',
                placeholder: 'Type your message…',
                send: 'Send',
                clear: 'Clear conversation',
                close: 'Close',
                open: 'Open the assistant',
                hello: 'Hello! I can help you find a movie, look up your favorites or history, or explain how the site works.',
                chips: ['Which action movies do you have?', 'What do you recommend tonight?', 'Show my favorites', 'How do I add a favorite?'],
                empty: 'I could not generate an answer. Please try again.',
                offline: 'Cannot reach the server. Check your connection.',
                generic: 'Something went wrong. Please try again shortly.'
            }
        };

        var fab = document.getElementById('chatNavBtn'); // bouton dans la barre du haut (menu-user.php)
        var panel = document.getElementById('bmcPanel');
        var box = document.getElementById('bmcMsgs');
        var form = document.getElementById('bmcForm');
        var input = document.getElementById('bmcInput');
        var sendBtn = document.getElementById('bmcSend');
        var clearBtn = document.getElementById('bmcClear');
        var closeBtn = document.getElementById('bmcClose');

        var msgs = [];
        var busy = false;

        try { msgs = JSON.parse(sessionStorage.getItem(STORE) || '[]'); } catch (e) { msgs = []; }
        if (!Array.isArray(msgs)) { msgs = []; }

        function lang() {
            var l = 'fr';
            try { l = localStorage.getItem('lang') || 'fr'; } catch (e) { }
            return l === 'en' ? 'en' : 'fr';
        }
        function tr(k) { return T[lang()][k]; }

        function save() {
            msgs = msgs.slice(-MAX_STORED);
            try { sessionStorage.setItem(STORE, JSON.stringify(msgs)); } catch (e) { }
        }

        /* ---------- Rendu Markdown léger et sûr ---------- */
        var LINK_OK = /^(watch\.php\?id_video=\d+|video\.php|favoris\.php|historique\.php|profil\.php|index\.php|modifier_profil\.php|modifier_motdepasse\.php)$/;

        function esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function inline(s) {
            s = esc(s);
            s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, label, href) {
                href = href.replace(/&amp;/g, '&');
                if (!LINK_OK.test(href)) { return label; }
                return '<a href="' + esc(LINK_BASE + href) + '">' + label + '</a>';
            });
            s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            return s;
        }

        function render(text) {
            var lines = String(text).replace(/\r/g, '').split('\n');
            var html = '', list = null, para = [];

            function flushPara() {
                if (para.length) { html += '<p>' + para.join('<br>') + '</p>'; para = []; }
            }
            function flushList() {
                if (list) { html += '</' + list + '>'; list = null; }
            }

            lines.forEach(function (raw) {
                var line = raw.trim();
                var b = line.match(/^[-*•]\s+(.*)$/);
                var n = line.match(/^\d+[.)]\s+(.*)$/);
                var h = line.match(/^#{1,4}\s+(.*)$/);
                if (b || n) {
                    flushPara();
                    var kind = b ? 'ul' : 'ol';
                    if (list !== kind) { flushList(); html += '<' + kind + '>'; list = kind; }
                    html += '<li>' + inline((b || n)[1]) + '</li>';
                } else if (line === '') {
                    flushPara(); flushList();
                } else {
                    flushList();
                    para.push(h ? '<strong>' + inline(h[1]) + '</strong>' : inline(line));
                }
            });
            flushPara(); flushList();
            return html;
        }

        /* ---------- Affichage ---------- */
        function scrollDown() { box.scrollTop = box.scrollHeight; }

        /* ---------- Téléphone : garder la saisie visible quand le clavier s'ouvre ---------- */
        var vv = window.visualViewport;
        function isMobile() { return window.innerWidth <= 520; }

        function resetViewport() {
            panel.style.height = '';
            panel.style.maxHeight = '';
            panel.style.top = '';
        }

        // Cale le panneau sur la zone réellement visible (écran moins le clavier)
        function fitViewport() {
            if (!panel.classList.contains('bmc-open')) { return; }
            if (!isMobile() || !vv) { resetViewport(); return; }
            panel.style.height = vv.height + 'px';
            panel.style.maxHeight = vv.height + 'px';
            panel.style.top = vv.offsetTop + 'px';
            scrollDown();
        }

        if (vv) {
            vv.addEventListener('resize', fitViewport);
            vv.addEventListener('scroll', fitViewport);
        }
        window.addEventListener('orientationchange', function () { setTimeout(fitViewport, 300); });
        window.addEventListener('resize', function () { if (!isMobile()) { resetViewport(); document.documentElement.classList.remove('bmc-lock'); } });
        // Le clavier met un instant à s'ouvrir : on recalcule deux fois
        input.addEventListener('focus', function () {
            if (isMobile()) { setTimeout(fitViewport, 120); setTimeout(fitViewport, 450); }
        });

        function addBubble(role, content, isErr) {
            var d = document.createElement('div');
            d.className = 'bmc-m ' + (role === 'user' ? 'bmc-me' : 'bmc-bot') + (isErr ? ' bmc-err' : '');
            if (role === 'user') { d.textContent = content; } else { d.innerHTML = render(content); }
            box.appendChild(d);
            scrollDown();
            return d;
        }

        function typingBubble() {
            var d = document.createElement('div');
            d.className = 'bmc-m bmc-bot';
            d.innerHTML = '<span class="bmc-dots"><i></i><i></i><i></i></span>';
            box.appendChild(d);
            scrollDown();
            return d;
        }

        function showChips() {
            var wrap = document.createElement('div');
            wrap.className = 'bmc-chips';
            wrap.id = 'bmcChips';
            tr('chips').forEach(function (q) {
                var c = document.createElement('button');
                c.type = 'button';
                c.className = 'bmc-chip';
                c.textContent = q;
                c.addEventListener('click', function () { send(q); });
                wrap.appendChild(c);
            });
            box.appendChild(wrap);
        }

        function paint() {
            box.innerHTML = '';
            if (!msgs.length) {
                addBubble('assistant', tr('hello'));
                showChips();
            } else {
                msgs.forEach(function (m) { addBubble(m.role, m.content); });
            }
            scrollDown();
        }

        function applyLabels() {
            document.getElementById('bmcTitle').textContent = tr('title');
            document.getElementById('bmcSub').textContent = tr('sub');
            input.placeholder = tr('placeholder');
            sendBtn.setAttribute('aria-label', tr('send'));
            sendBtn.title = tr('send');
            clearBtn.title = tr('clear');
            clearBtn.setAttribute('aria-label', tr('clear'));
            closeBtn.title = tr('close');
            closeBtn.setAttribute('aria-label', tr('close'));
            if (fab) { fab.setAttribute('aria-label', tr('open')); fab.title = tr('open'); }
            if (!msgs.length && !busy) { paint(); }
        }

        /* ---------- Ouverture / fermeture ---------- */
        function openPanel() {
            panel.classList.add('bmc-open');
            if (fab) { fab.classList.add('active'); fab.setAttribute('aria-expanded', 'true'); }
            applyLabels();
            if (msgs.length && !box.childNodes.length) { paint(); }
            if (isMobile()) { document.documentElement.classList.add('bmc-lock'); }
            fitViewport();
            // Sur téléphone on n'ouvre pas le clavier automatiquement : on voit d'abord toute la conversation
            setTimeout(function () { if (!isMobile()) { input.focus(); } scrollDown(); }, 60);
        }

        function closePanel() {
            panel.classList.remove('bmc-open');
            document.documentElement.classList.remove('bmc-lock');
            resetViewport();
            if (document.activeElement === input) { input.blur(); }
            if (fab) { fab.classList.remove('active'); fab.setAttribute('aria-expanded', 'false'); fab.focus(); }
        }

        // Appelée par le bouton de la navbar : onclick="bmChatToggle()"
        window.bmChatToggle = function () {
            if (panel.classList.contains('bmc-open')) { closePanel(); } else { openPanel(); }
        };
        closeBtn.addEventListener('click', closePanel);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('bmc-open')) { closePanel(); }
        });

        clearBtn.addEventListener('click', function () {
            if (busy) { return; }
            msgs = [];
            save();
            paint();
        });

        /* ---------- Envoi ---------- */
        function autoGrow() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
        }
        input.addEventListener('input', autoGrow);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : send(); }
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); send(); });

        function setBusy(v) {
            busy = v;
            sendBtn.disabled = v;
        }

        function errorText(data) {
            if (data && data.message) { return data.message + (data.debug ? ' [' + data.debug + ']' : ''); }
            return tr('generic');
        }

        function post(body, signal) {
            return fetch(API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(body),
                signal: signal
            });
        }

        function send(textOverride) {
            if (busy) { return; }
            var text = (typeof textOverride === 'string' ? textOverride : input.value).trim();
            if (!text) { return; }

            var chips = document.getElementById('bmcChips');
            if (chips) { chips.remove(); }

            if (!msgs.length) { box.innerHTML = ''; }
            msgs.push({ role: 'user', content: text });
            save();
            addBubble('user', text);
            input.value = '';
            autoGrow();

            var history = msgs.slice(-(HISTORY_SENT + 1), -1);
            var bubble = typingBubble();
            var answer = '';
            var gotToken = false;
            var ctrl = new AbortController();
            var timer = setTimeout(function () { ctrl.abort(); }, 80000);
            setBusy(true);

            function finishOk() {
                clearTimeout(timer);
                if (!answer.trim()) {
                    bubble.className = 'bmc-m bmc-bot bmc-err';
                    bubble.textContent = tr('empty');
                } else {
                    msgs.push({ role: 'assistant', content: answer });
                    save();
                }
                setBusy(false);
                input.focus();
            }

            function finishErr(message) {
                clearTimeout(timer);
                bubble.className = 'bmc-m bmc-bot bmc-err';
                bubble.textContent = message;
                // On retire la question restée sans réponse pour garder un historique cohérent
                if (msgs.length && msgs[msgs.length - 1].role === 'user') { msgs.pop(); save(); }
                setBusy(false);
                input.focus();
            }

            // Repli : réponse d'un coup si l'affichage progressif n'est pas possible sur ce serveur
            function fallbackNoStream() {
                post({ message: text, history: history, lang: lang(), stream: false }, ctrl.signal)
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok || res.j.error) { finishErr(errorText(res.j)); return; }
                        answer = res.j.reply || '';
                        bubble.innerHTML = render(answer);
                        scrollDown();
                        finishOk();
                    })
                    .catch(function () { finishErr(tr('offline')); });
            }

            post({ message: text, history: history, lang: lang(), stream: true }, ctrl.signal)
                .then(function (resp) {
                    var ct = resp.headers.get('Content-Type') || '';

                    // Réponse JSON : erreur, ou réponse complète
                    if (ct.indexOf('text/event-stream') === -1 || !resp.body || !resp.body.getReader) {
                        return resp.json().then(function (j) {
                            if (!resp.ok || j.error) { finishErr(errorText(j)); return; }
                            answer = j.reply || '';
                            bubble.innerHTML = render(answer);
                            scrollDown();
                            finishOk();
                        });
                    }

                    // Flux SSE : affichage mot par mot
                    var reader = resp.body.getReader();
                    var decoder = new TextDecoder('utf-8');
                    var buf = '';
                    var failed = null;

                    function handle(line) {
                        if (line.indexOf('data:') !== 0) { return; }
                        var ev;
                        try { ev = JSON.parse(line.slice(5).trim()); } catch (e) { return; }
                        if (ev.error) { failed = ev; return; }
                        if (ev.t) {
                            gotToken = true;
                            answer += ev.t;
                            bubble.innerHTML = render(answer);
                            scrollDown();
                        }
                    }

                    function pump() {
                        return reader.read().then(function (r) {
                            if (r.done) {
                                if (buf.trim()) { handle(buf.trim()); }
                                if (failed && !gotToken) { finishErr(errorText(failed)); return; }
                                finishOk();
                                return;
                            }
                            buf += decoder.decode(r.value, { stream: true });
                            var parts = buf.split('\n');
                            buf = parts.pop();
                            parts.forEach(function (p) { handle(p.trim()); });
                            return pump();
                        });
                    }

                    return pump();
                })
                .catch(function (err) {
                    if (gotToken) {
                        // Flux coupé en cours de route : on garde ce qui est déjà affiché
                        finishOk();
                    } else if (err && err.name === 'AbortError') {
                        finishErr(tr('offline'));
                    } else {
                        fallbackNoStream();
                    }
                });
        }

        /* ---------- Suivre le changement de langue du site (bouton FR/EN) ---------- */
        var langLabel = document.getElementById('langLabel');
        if (langLabel && window.MutationObserver) {
            new MutationObserver(applyLabels).observe(langLabel, { childList: true, characterData: true, subtree: true });
        }

        applyLabels();
        if (msgs.length) { paint(); }
    })();
</script>