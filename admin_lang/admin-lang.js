/* ==========================================================================
   BERTIN MOVIE - Moteur de traduction de l'administration
   --------------------------------------------------------------------------
   - Lit les textes dans translations.js (window.ADMIN_TRANSLATIONS).
   - La langue est stockée dans localStorage['lang'] ('fr' ou 'en'),
     la même clé que le bouton de langue du menu et que corbeille.php.
   - Dès que le bouton de langue change cette valeur, TOUTE la page est
     traduite (titres, tableaux, boutons, placeholders, info-bulles, messages,
     alert/confirm) et le contenu ajouté plus tard par JavaScript aussi.
   - Retour en français : les textes d'origine sont restaurés tels quels.
   ========================================================================== */
(function () {
  'use strict';
  if (window.__adminLangLoaded) { return; }
  window.__adminLangLoaded = true;

  var KEY = 'lang';
  var MAP = Object.create(null);
  var PATTERNS = [];
  var DATE_RE = null;
  var DATE_WORDS = Object.create(null);

  /* ---------- outils ---------- */
  function norm(s) {
    return String(s).replace(/[\u00a0\u202f]/g, ' ').replace(/[\u2019\u2018]/g, "'").replace(/\u2026/g, '...')
      .replace(/\s+/g, ' ').trim().toLowerCase();
  }
  function build() {
    var T = window.ADMIN_TRANSLATIONS || {}, g, k;
    for (g in T) { for (k in T[g]) { MAP[norm(k)] = T[g][k]; } }
    (window.ADMIN_PATTERNS || []).forEach(function (p) {
      try { PATTERNS.push([new RegExp(p[0], 'i'), p[1]]); } catch (e) { }
    });
    var W = window.ADMIN_DATE_WORDS || {}, words = [];
    for (k in W) { DATE_WORDS[k.toLowerCase()] = W[k]; words.push(k); }
    if (words.length) { DATE_RE = new RegExp('\\b(' + words.join('|') + ')\\b', 'gi'); }
  }
  function matchCase(src, out) {
    var letters = src.replace(/[^A-Za-zÀ-ÿ]/g, '');
    if (letters.length > 1 && src === src.toUpperCase()) { return out.toUpperCase(); }
    return out;
  }

  /* Traduit un texte français ; renvoie null si rien à traduire */
  function translate(text) {
    if (typeof text !== 'string' || !text) { return null; }
    var m = text.match(/^(\s*)([\s\S]*?)(\s*)$/), lead = m[1], core = m[2], trail = m[3];
    if (!core) { return null; }
    var r = lookup(core);
    return r === null ? null : lead + r + trail;
  }
  function lookup(core) {
    var k = norm(core), v = MAP[k];
    if (v !== undefined) { return matchCase(core, v); }
    var i, p;
    for (i = 0; i < PATTERNS.length; i++) {
      p = PATTERNS[i];
      if (p[0].test(core)) { return core.replace(p[0], p[1]); }
    }
    /* emojis / symboles au début, ":" ou "..." à la fin */
    var m = core.match(/^([^A-Za-zÀ-ÿ0-9"«(]+)([\s\S]+)$/);
    if (m) { var a = lookup(m[2]); if (a !== null) { return m[1] + a; } }
    m = core.match(/^([\s\S]+?)(\s*:|\s*\.{3}|\s*…|\s*\*)$/);
    if (m) {
      var b = lookup(m[1]);
      if (b !== null) { return b + m[2].replace(/^\s+/, ''); }
    }
    if (DATE_RE) {
      DATE_RE.lastIndex = 0;
      if (DATE_RE.test(core)) {
        DATE_RE.lastIndex = 0;
        return core.replace(DATE_RE, function (w) {
          var e = DATE_WORDS[w.toLowerCase()];
          return e;
        });
      }
    }
    return null;
  }

  /* ---------- langue ---------- */
  function getLang() {
    try { return localStorage.getItem(KEY) === 'en' ? 'en' : 'fr'; } catch (e) { return 'fr'; }
  }

  /* ---------- parcours du DOM ---------- */
  var SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, CODE: 1, PRE: 1, NOSCRIPT: 1, INPUT: 1, SELECT_: 1 };
  var ATTRS = ['placeholder', 'title', 'alt', 'aria-label'];
  var ATTR_SEL = '[placeholder],[title],[alt],[aria-label],input[type=submit],input[type=button],input[type=reset]';

  function skipAnc(el) {
    return !!(el && el.closest && el.closest('[data-no-i18n],.notranslate,[translate="no"],[data-lang-fr]'));
  }
  function skipEl(el) {
    if (!el || el.nodeType !== 1) { return false; }
    if (SKIP[el.tagName]) { return true; }
    return !!(el.closest && el.closest('[data-no-i18n],.notranslate,[translate="no"],[data-lang-fr]'));
  }

  var textState = new WeakMap();   // Text node -> {fr, en}
  var attrState = new WeakMap();   // Element   -> { attr: {fr, en} }

  function doText(node, lang) {
    var st = textState.get(node), cur = node.nodeValue;
    if (lang === 'en') {
      if (st && cur === st.en) { return; }          // déjà traduit
      var fr = cur, en = translate(fr);
      if (en !== null && en !== fr) { textState.set(node, { fr: fr, en: en }); node.nodeValue = en; }
      else if (st) { textState.delete(node); }
    } else if (st) {
      if (cur === st.en) { node.nodeValue = st.fr; }
      textState.delete(node);
    }
  }
  function doAttrs(el, lang) {
    var st = attrState.get(el) || {}, any = false, i, a, cur, s, en;
    var list = ATTRS.slice();
    if (el.tagName === 'INPUT' && /^(submit|button|reset)$/i.test(el.type || '')) { list.push('value'); }
    for (i = 0; i < list.length; i++) {
      a = list[i];
      if (!el.hasAttribute(a)) { continue; }
      cur = el.getAttribute(a); s = st[a];
      if (lang === 'en') {
        if (s && cur === s.en) { any = true; continue; }
        en = translate(cur);
        if (en !== null && en !== cur) { st[a] = { fr: cur, en: en }; el.setAttribute(a, en); any = true; }
        else if (s) { delete st[a]; }
      } else if (s) {
        if (cur === s.en) { el.setAttribute(a, s.fr); }
        delete st[a];
      }
    }
    if (any) { attrState.set(el, st); } else { attrState.delete(el); }
  }
  function walk(root, lang) {
    if (!root) { return; }
    if (root.nodeType === 3) {
      if (!skipEl(root.parentNode)) { doText(root, lang); }
      return;
    }
    if (root.nodeType !== 1) { return; }
    if (skipAnc(root)) { return; }
    if (SKIP[root.tagName]) { if (root.matches(ATTR_SEL)) { doAttrs(root, lang); } return; }
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), n, list = [];
    while ((n = w.nextNode())) { list.push(n); }
    list.forEach(function (t) { if (!skipEl(t.parentNode)) { doText(t, lang); } });
    var els = root.querySelectorAll(ATTR_SEL);
    for (var i = 0; i < els.length; i++) { if (!skipAnc(els[i])) { doAttrs(els[i], lang); } }
    if (root.matches && root.matches(ATTR_SEL) && !skipAnc(root)) { doAttrs(root, lang); }
  }

  var titleState = null;
  function doTitle(lang) {
    var cur = document.title;
    if (lang === 'en') {
      if (titleState && cur === titleState.en) { return; }
      var en = translate(cur);
      if (en !== null && en !== cur) { titleState = { fr: cur, en: en }; document.title = en; } else { titleState = null; }
    } else if (titleState) {
      if (cur === titleState.en) { document.title = titleState.fr; }
      titleState = null;
    }
  }

  var applied = null;
  function apply(force) {
    var lang = getLang();
    if (!force && lang === applied) { return; }
    applied = lang;
    pause();
    try {
      document.documentElement.setAttribute('lang', lang);
      doTitle(lang);
      walk(document.body, lang);
    } finally { resume(); }
    try { document.dispatchEvent(new CustomEvent('adminlangchange', { detail: { lang: lang } })); } catch (e) { }
  }

  /* ---------- contenu ajouté / modifié plus tard (JS, AJAX, toasts…) ---------- */
  var obs = null, pending = new Set(), timer = null;
  function onMut(list) {
    if (getLang() !== 'en') { return; }
    list.forEach(function (m) {
      if (m.type === 'childList') {
        m.addedNodes.forEach(function (n) { if (n.nodeType === 1 || n.nodeType === 3) { pending.add(n); } });
      } else if (m.type === 'characterData') {
        pending.add(m.target);
      } else if (m.type === 'attributes') {
        pending.add(m.target);
      }
    });
    if (pending.size && !timer) { timer = setTimeout(flush, 30); }
  }
  function flush() {
    timer = null;
    var lang = getLang(), items = Array.from(pending);
    pending.clear();
    if (lang !== 'en') { return; }
    pause();
    try {
      items.forEach(function (n) {
        if (!n.isConnected) { return; }
        if (n.nodeType === 1 && n.tagName === 'TITLE') { doTitle(lang); return; }
        if (n.nodeType === 3) { if (!skipEl(n.parentNode)) { doText(n, lang); } return; }
        walk(n, lang);
      });
    } finally { resume(); }
  }
  function pause() { if (obs) { obs.disconnect(); } }
  function resume() {
    if (!obs) { return; }
    obs.observe(document.documentElement, {
      childList: true, subtree: true, characterData: true,
      attributes: true, attributeFilter: ATTRS.concat(['value'])
    });
  }

  /* ---------- crochets : le bouton de langue ---------- */
  function hookStorage() {
    try {
      var orig = Storage.prototype.setItem;
      Storage.prototype.setItem = function (k, v) {
        var r = orig.apply(this, arguments);
        if (k === KEY && this === window.localStorage) { setTimeout(function () { apply(); }, 0); }
        return r;
      };
    } catch (e) { }
    window.addEventListener('storage', function (e) { if (e.key === KEY) { apply(); } });
  }
  function hookFunctions() {
    ['toggleLanguage', 'applyLanguage', 'changeLanguage', 'setLanguage', 'switchLanguage', 'toggleLang', 'changeLang', 'setLang'].forEach(function (name) {
      var f = window[name];
      if (typeof f !== 'function' || f.__adminLangWrapped) { return; }
      var w = function () {
        var r = f.apply(this, arguments);
        setTimeout(function () { apply(); }, 0);
        return r;
      };
      w.__adminLangWrapped = true;
      window[name] = w;
    });
  }
  function hookClicks() {
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest && e.target.closest('#langBtn,#langBtnSidebar,[data-lang-toggle],.lang-btn,.lang-switch,[onclick*="anguage("],[onclick*="Lang("]');
      if (t) { setTimeout(function () { apply(); }, 80); }
    }, true);
  }
  function hookDialogs() {
    ['alert', 'confirm', 'prompt'].forEach(function (n) {
      var f = window[n];
      if (typeof f !== 'function') { return; }
      window[n] = function (msg) {
        var a = Array.prototype.slice.call(arguments);
        if (getLang() === 'en' && typeof msg === 'string') { var t = translate(msg); if (t !== null) { a[0] = t; } }
        return f.apply(window, a);
      };
    });
  }

  /* ---------- API publique ---------- */
  window.adminLang = {
    get: getLang,
    set: function (l) { try { localStorage.setItem(KEY, l === 'en' ? 'en' : 'fr'); } catch (e) { } apply(true); },
    toggle: function () { this.set(getLang() === 'en' ? 'fr' : 'en'); },
    t: function (fr) { if (getLang() !== 'en') { return fr; } var r = translate(fr); return r === null ? fr : r; },
    apply: function () { apply(true); }
  };
  window.adminT = window.adminLang.t;   // utilisable dans les scripts des pages : adminT('Texte FR')

  /* ---------- démarrage ---------- */
  function init() {
    build();
    hookStorage();
    hookDialogs();
    hookClicks();
    hookFunctions();
    obs = new MutationObserver(onMut);
    resume();
    apply(true);
    window.addEventListener('load', function () { hookFunctions(); apply(true); });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
