/* includes/idle_timeout.js : déconnexion automatique après inactivité (côté navigateur)
 * - avertit la personne quelques secondes avant la coupure
 * - envoie un « keepalive » au serveur tant qu'elle est active (souris, clavier, tactile, vidéo en lecture, envoi long)
 * - les requêtes fetch automatiques sont marquées « arrière-plan » : elles ne prolongent PAS la session
 * Les durées viennent de auth_check.php (IDLE_TIMEOUT_MIN / IDLE_WARNING_SEC).
 */
(function () {
    'use strict';
    var me = document.currentScript;
    if (!me || window.__bmIdle) { return; }
    window.__bmIdle = true;

    var TIMEOUT = (parseInt(me.getAttribute('data-timeout'), 10) || 900) * 1000;
    var WARN = (parseInt(me.getAttribute('data-warn'), 10) || 60) * 1000;
    var PING_EVERY = Math.max(5000, Math.min(60000, TIMEOUT / 4));
    var LONG_REQUEST = 8000;           // une requête qui dure > 8 s (envoi de film...) compte comme activité

    var en = /^en/i.test(document.documentElement.lang || '');
    var T = en
        ? { title: 'Still there?', msg: 'For your security you will be signed out in', unit: 's due to inactivity.', btn: 'Stay signed in' }
        : { title: 'Toujours là ?', msg: 'Pour votre sécurité, vous allez être déconnecté dans', unit: 's pour cause d\'inactivité.', btn: 'Rester connecté' };

    var origFetch = window.fetch ? window.fetch.bind(window) : null;
    var pending = {}, seq = 0;
    var lastActivity = Date.now(), lastPing = Date.now(), warnBox = null;

    function sameOrigin(u) {
        try { return new URL(u, location.href).origin === location.origin; } catch (e) { return false; }
    }

    /* --- fetch : marque les appels automatiques comme « arrière-plan » (même origine seulement) --- */
    if (origFetch) {
        window.fetch = function (input, init) {
            var url = (typeof input === 'string') ? input : ((input && input.url) || '');
            if (sameOrigin(url)) {
                init = init || {};
                var h = new Headers(init.headers || (input && input.headers) || undefined);
                h.set('X-BM-Background', '1');
                init.headers = h;
            }
            var id = ++seq; pending[id] = Date.now();
            var p = origFetch(input, init);
            var done = function () { delete pending[id]; };
            p.then(done, done);
            return p;
        };
    }
    /* --- XMLHttpRequest : on compte seulement les envois longs --- */
    if (window.XMLHttpRequest) {
        var xsend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function () {
            var id = ++seq, x = this;
            pending[id] = Date.now();
            var dec = function () { x.removeEventListener('loadend', dec); delete pending[id]; };
            this.addEventListener('loadend', dec);
            try { return xsend.apply(this, arguments); } catch (e) { dec(); throw e; }
        };
    }

    function busy() {
        var now = Date.now(), k;
        for (k in pending) { if (now - pending[k] > LONG_REQUEST) { return true; } }
        var v = document.getElementsByTagName('video');
        for (var i = 0; i < v.length; i++) { if (!v[i].paused && !v[i].ended) { return true; } }
        return false;
    }

    function expire() { location.replace(location.pathname + location.search); }  // le serveur redirige vers login.php?timeout=1

    function ping() {
        lastPing = Date.now();
        if (!origFetch) { return; }
        origFetch(location.pathname, { method: 'GET', headers: { 'X-BM-Keepalive': '1' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { if (r.status === 401) { expire(); } })
            .catch(function () { });
    }

    function hideWarn() {
        if (warnBox && warnBox.parentNode) { warnBox.parentNode.removeChild(warnBox); }
        warnBox = null;
    }

    function showWarn(sec) {
        if (!warnBox) {
            warnBox = document.createElement('div');
            warnBox.setAttribute('role', 'alertdialog');
            warnBox.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.6);font-family:Inter,"Segoe UI",Arial,sans-serif;';
            warnBox.innerHTML =
                '<div style="background:#fff;color:#0f172a;max-width:380px;width:90%;padding:26px;border-radius:18px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.35)">' +
                '<div style="font-size:34px">⏳</div>' +
                '<h3 style="margin:8px 0 6px;font-size:18px">' + T.title + '</h3>' +
                '<p style="font-size:14px;color:#475569;line-height:1.5;margin:0 0 16px">' + T.msg + ' <b id="bmIdleSec"></b> ' + T.unit + '</p>' +
                '<button type="button" id="bmIdleStay" style="border:0;border-radius:12px;padding:11px 22px;font-size:15px;font-weight:700;color:#fff;background:#2563eb;cursor:pointer">' + T.btn + '</button>' +
                '</div>';
            document.body.appendChild(warnBox);
            document.getElementById('bmIdleStay').addEventListener('click', function (e) { e.stopPropagation(); touch(true); });
        }
        var s = document.getElementById('bmIdleSec');
        if (s) { s.textContent = sec; }
    }

    function touch(force) {
        var now = Date.now();
        if (!force && !warnBox && now - lastActivity < 1000) { return; }
        lastActivity = now;
        if (warnBox) { hideWarn(); ping(); return; }
        if (now - lastPing >= PING_EVERY) { ping(); }
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'wheel', 'touchstart', 'click'].forEach(function (ev) {
        document.addEventListener(ev, function () { touch(false); }, { passive: true, capture: true });
    });

    setInterval(function () {
        var now = Date.now();
        if (busy()) {                       // vidéo en lecture ou envoi long : la personne est là
            lastActivity = now;
            if (warnBox) { hideWarn(); }
            if (now - lastPing >= PING_EVERY) { ping(); }
            return;
        }
        var idle = now - lastActivity;
        if (idle >= TIMEOUT + 2000) { expire(); return; }
        if (idle >= TIMEOUT - WARN) { showWarn(Math.max(0, Math.ceil((TIMEOUT - idle) / 1000))); }
    }, 1000);
})();