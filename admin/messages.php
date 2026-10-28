<?php
require_once __DIR__ . '/../includes/session_boot.php';
require_once __DIR__ . '/../connexion.php';
require_once __DIR__ . '/../includes/update_session.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/support_lib.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit();
}
if (isset($_SESSION['user_id'])) {
    updateUserSession($pdo, $_SESSION['user_id']);
}
support_ensure_table($pdo);
$csrf = support_csrf();
$open_user = (int) ($_GET['user'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Messages - BERTIN MOVIE</title>
    <link rel="stylesheet" href="/film_web_dy/style.css?v=3">
    <link rel="stylesheet" href="/film_web_dy/admin/hx-style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* =========================================================
           MESSAGES (admin) - préfixe ms-, couleurs du design hx
        ========================================================= */
        .ms-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--hx-muted);
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
            box-shadow: var(--hx-sh);
        }

        .ms-pill i {
            color: var(--hx-blue);
        }

        .ms-pill.has {
            color: #dc2626;
            border-color: rgba(239, 68, 68, .35);
            background: rgba(239, 68, 68, .08);
        }

        .ms-pill.has i {
            color: #ef4444;
        }

        .ms-box {
            display: grid;
            grid-template-columns: 350px minmax(0, 1fr);
            height: clamp(480px, calc(100vh - 300px), 780px);
            height: clamp(480px, calc(100dvh - 300px), 780px);
            border-radius: 20px;
            overflow: hidden;
            background: var(--hx-bg);
            color: var(--hx-text);
            border: 1px solid var(--hx-line);
            box-shadow: var(--hx-sh-lg);
        }

        /* ----- colonne gauche ----- */
        .ms-side {
            display: flex;
            flex-direction: column;
            min-height: 0;
            border-right: 1px solid var(--hx-line);
            background: var(--hx-bg);
        }

        .ms-sh {
            padding: 16px 16px 10px;
        }

        .ms-search {
            position: relative;
        }

        .ms-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--hx-muted-2);
            font-size: 14px;
            pointer-events: none;
        }

        .ms-search input {
            width: 100%;
            box-sizing: border-box;
            height: 44px;
            padding: 0 40px 0 40px;
            border-radius: 12px;
            border: 2px solid var(--hx-line);
            background: var(--hx-bg-2);
            color: var(--hx-text);
            font: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .ms-search input:focus {
            border-color: var(--hx-blue);
            background: var(--hx-bg);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .1);
        }

        .ms-search button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            color: var(--hx-muted);
            background: transparent;
        }

        .ms-search button:hover {
            background: var(--hx-line);
        }

        .ms-tabs {
            display: flex;
            gap: 6px;
            margin-top: 12px;
            padding: 4px;
            border-radius: 12px;
            background: var(--hx-bg-2);
            border: 1px solid var(--hx-line);
        }

        .ms-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--hx-muted);
            background: transparent;
            transition: all .2s;
        }

        .ms-tab.on {
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 4px 12px rgba(59, 130, 246, .3);
        }

        .ms-tab .n {
            min-width: 18px;
            padding: 0 5px;
            border-radius: 9px;
            font-size: 11px;
            line-height: 18px;
            background: #ef4444;
            color: #fff;
        }

        .ms-conv {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding-bottom: 8px;
        }

        .ms-lbl {
            padding: 12px 18px 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--hx-muted-2);
        }

        .ms-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 2px 8px;
            padding: 11px 10px;
            border-radius: 14px;
            cursor: pointer;
            transition: background .15s;
        }

        .ms-item:hover {
            background: var(--hx-bg-2);
        }

        .ms-item.on {
            background: rgba(59, 130, 246, .12);
        }

        .ms-item.on::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 14px;
            bottom: 14px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--hx-blue);
        }

        .ms-av {
            flex: none;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .02em;
            color: #fff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .18);
        }

        .ms-info {
            min-width: 0;
            flex: 1;
        }

        .ms-name {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            font-size: 14.5px;
            font-weight: 600;
            color: var(--hx-text);
        }

        .ms-name span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ms-name small {
            flex: none;
            font-size: 11.5px;
            font-weight: 500;
            color: var(--hx-muted-2);
        }

        .ms-last {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 3px;
            font-size: 13px;
            color: var(--hx-muted);
        }

        .ms-last span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ms-item.unread .ms-name span,
        .ms-item.unread .ms-last span {
            color: var(--hx-text);
            font-weight: 700;
        }

        .ms-item.unread .ms-name small {
            color: var(--hx-blue);
            font-weight: 700;
        }

        .ms-un {
            flex: none;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            line-height: 22px;
            text-align: center;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 3px 8px rgba(59, 130, 246, .4);
        }

        .ms-none {
            padding: 40px 24px;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
            color: var(--hx-muted);
        }

        .ms-none i {
            display: block;
            margin-bottom: 12px;
            font-size: 34px;
            color: var(--hx-muted-2);
            opacity: .6;
        }

        .ms-sk {
            display: flex;
            gap: 12px;
            align-items: center;
            margin: 4px 18px;
            padding: 10px 0;
        }

        .ms-sk i {
            width: 46px;
            height: 46px;
            border-radius: 50%;
        }

        .ms-sk b {
            flex: 1;
            height: 34px;
            border-radius: 8px;
        }

        .ms-sk i,
        .ms-sk b {
            background: linear-gradient(90deg, var(--hx-bg-2) 25%, var(--hx-line) 50%, var(--hx-bg-2) 75%);
            background-size: 200% 100%;
            animation: msSh 1.3s infinite;
        }

        @keyframes msSh {
            to {
                background-position: -200% 0;
            }
        }

        /* ----- colonne droite ----- */
        .ms-main {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background: var(--hx-bg-2);
        }

        .ms-head {
            flex: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--hx-bg);
            border-bottom: 1px solid var(--hx-line);
        }

        .ms-head .ms-av {
            width: 42px;
            height: 42px;
            font-size: 14px;
        }

        .ms-ht {
            min-width: 0;
        }

        .ms-ht b {
            display: block;
            font-size: 15.5px;
            font-weight: 700;
            color: var(--hx-text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ms-ht small {
            display: block;
            margin-top: 1px;
            font-size: 12.5px;
            color: var(--hx-muted);
        }

        .ms-prof {
            margin-left: auto;
            flex: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: var(--hx-blue);
            background: rgba(59, 130, 246, .1);
            transition: background .2s;
        }

        .ms-prof:hover {
            background: rgba(59, 130, 246, .2);
        }

        .ms-back {
            display: none;
            flex: none;
            width: 40px;
            height: 40px;
            margin-left: -6px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 17px;
            color: var(--hx-text);
            background: transparent;
        }

        .ms-back:hover {
            background: var(--hx-bg-2);
        }

        .ms-hide {
            display: none !important;
        }

        .ms-msgs {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .ms-msgs::-webkit-scrollbar,
        .ms-conv::-webkit-scrollbar {
            width: 6px;
        }

        .ms-msgs::-webkit-scrollbar-thumb,
        .ms-conv::-webkit-scrollbar-thumb {
            background: var(--hx-line);
            border-radius: 3px;
        }

        .ms-welcome {
            margin: auto;
            max-width: 320px;
            text-align: center;
            color: var(--hx-muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .ms-welcome .ic {
            width: 84px;
            height: 84px;
            margin: 0 auto 16px;
            border-radius: 26px;
            display: grid;
            place-items: center;
            font-size: 34px;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 14px 30px rgba(59, 130, 246, .35);
        }

        .ms-welcome b {
            display: block;
            margin-bottom: 4px;
            font-size: 18px;
            color: var(--hx-text);
        }

        .ms-day {
            align-self: center;
            margin: 14px 0 8px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: capitalize;
            color: var(--hx-muted);
            background: var(--hx-bg);
            border: 1px solid var(--hx-line);
        }

        .ms-row {
            display: flex;
            margin-top: 8px;
            animation: msIn .2s ease;
        }

        .ms-row.cont {
            margin-top: 0;
        }

        @keyframes msIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .ms-row.me {
            justify-content: flex-end;
        }

        .ms-bub {
            max-width: min(70%, 520px);
            padding: 9px 14px 6px;
            border-radius: 18px;
            font-size: 14.5px;
            line-height: 1.45;
            word-break: break-word;
            overflow-wrap: anywhere;
            background: var(--hx-bg);
            color: var(--hx-text);
            border: 1px solid var(--hx-line);
            box-shadow: var(--hx-sh);
        }

        .ms-row.them .ms-bub {
            border-bottom-left-radius: 6px;
        }

        .ms-row.them.cont .ms-bub {
            border-top-left-radius: 6px;
        }

        .ms-row.me .ms-bub {
            color: #fff;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-bottom-right-radius: 6px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, .28);
        }

        .ms-row.me.cont .ms-bub {
            border-top-right-radius: 6px;
        }

        .ms-txt {
            white-space: pre-wrap;
        }

        .ms-txt a {
            color: inherit;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .ms-row.them .ms-txt a {
            color: var(--hx-blue);
        }

        .ms-meta {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
            font-size: 10.5px;
            opacity: .72;
        }

        .ms-tick {
            font-size: 11px;
            letter-spacing: -2px;
        }

        .ms-tick.rd {
            color: #bae6fd;
            opacity: 1;
        }

        .ms-img {
            display: block;
            max-width: 100%;
            max-height: 280px;
            margin-bottom: 5px;
            border-radius: 12px;
            cursor: zoom-in;
            background: rgba(127, 127, 127, .15);
        }

        .ms-file {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 210px;
            margin-bottom: 5px;
            padding: 8px 10px;
            border-radius: 12px;
            color: inherit;
            text-decoration: none;
            background: rgba(127, 127, 127, .12);
            transition: background .2s;
        }

        .ms-file:hover {
            background: rgba(127, 127, 127, .22);
        }

        .ms-row.me .ms-file {
            background: rgba(255, 255, 255, .17);
        }

        .ms-fi {
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

        .ms-fi.t-pdf {
            background: rgba(239, 68, 68, .15);
            color: #ef4444;
        }

        .ms-fi.t-excel {
            background: rgba(34, 197, 94, .15);
            color: #16a34a;
        }

        .ms-fi.t-ppt {
            background: rgba(249, 115, 22, .15);
            color: #f97316;
        }

        .ms-fi.t-zip {
            background: rgba(245, 158, 11, .15);
            color: #f59e0b;
        }

        .ms-fi.t-media {
            background: rgba(139, 92, 246, .15);
            color: #8b5cf6;
        }

        .ms-row.me .ms-fi {
            background: rgba(255, 255, 255, .22);
            color: #fff;
        }

        .ms-fn {
            min-width: 0;
            font-size: 13.5px;
            font-weight: 600;
        }

        .ms-fn span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 240px;
        }

        .ms-fn small {
            display: block;
            margin-top: 1px;
            font-size: 11.5px;
            font-weight: 400;
            opacity: .75;
        }

        .ms-down {
            position: absolute;
            left: 50%;
            bottom: 96px;
            transform: translateX(-50%);
            z-index: 3;
            display: none;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font: inherit;
            font-size: 12.5px;
            font-weight: 600;
            color: #fff;
            background: #2563eb;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .45);
        }

        .ms-foot {
            flex: none;
            padding: 12px 16px 14px;
            background: var(--hx-bg);
            border-top: 1px solid var(--hx-line);
        }

        .ms-err {
            display: none;
            margin-bottom: 8px;
            padding: 9px 13px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #dc2626;
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .ms-chip {
            display: none;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 14px;
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .25);
        }

        .ms-chip .ms-fi {
            width: 42px;
            height: 42px;
            overflow: hidden;
        }

        .ms-chip .ms-fi img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ms-chip .ms-fn {
            flex: 1;
            color: var(--hx-text);
        }

        .ms-chip button {
            flex: none;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            color: var(--hx-muted);
            background: transparent;
        }

        .ms-chip button:hover {
            background: var(--hx-line);
        }

        .ms-form {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            padding: 5px;
            border-radius: 28px;
            border: 2px solid var(--hx-line);
            background: var(--hx-bg-2);
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .ms-form:focus-within {
            border-color: var(--hx-blue);
            background: var(--hx-bg);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .1);
        }

        .ms-form textarea {
            flex: 1;
            min-width: 0;
            resize: none;
            min-height: 40px;
            max-height: 130px;
            padding: 10px 8px;
            border: none;
            outline: none;
            background: transparent;
            color: var(--hx-text);
            font: inherit;
            font-size: 14.5px;
            line-height: 1.4;
        }

        .ms-form textarea::placeholder {
            color: var(--hx-muted-2);
        }

        .ms-b {
            flex: none;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: grid;
            place-items: center;
            transition: transform .15s, opacity .2s, background .2s;
        }

        .ms-b.att {
            color: var(--hx-muted);
            background: transparent;
        }

        .ms-b.att:hover {
            color: var(--hx-blue);
            background: rgba(59, 130, 246, .12);
        }

        .ms-b.snd {
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 6px 16px rgba(59, 130, 246, .4);
        }

        .ms-b.snd:hover {
            transform: scale(1.06);
        }

        .ms-b.snd:disabled,
        .ms-b.snd.off {
            opacity: .45;
            cursor: default;
            transform: none;
            box-shadow: none;
        }

        .ms-hint {
            margin-top: 7px;
            padding-left: 14px;
            font-size: 11.5px;
            color: var(--hx-muted-2);
        }

        .ms-drop {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            color: var(--hx-blue);
            background: rgba(59, 130, 246, .1);
            border: 3px dashed var(--hx-blue);
            pointer-events: none;
            backdrop-filter: blur(2px);
        }

        .ms-drop i {
            font-size: 40px;
        }

        .ms-main.drag .ms-drop {
            display: flex;
        }

        .ms-lb {
            position: fixed;
            inset: 0;
            z-index: 10100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 6, 23, .9);
        }

        .ms-lb.open {
            display: flex;
        }

        .ms-lb img {
            max-width: 94vw;
            max-height: 84vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
        }

        .ms-lb-bar {
            position: absolute;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 10px;
        }

        .ms-lb-bar a,
        .ms-lb-bar button {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 18px;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            background: rgba(255, 255, 255, .16);
        }

        /* ----- tablette ----- */
        @media (max-width: 1024px) {
            .ms-box {
                grid-template-columns: 300px minmax(0, 1fr);
            }
        }

        /* ----- téléphone : liste pleine largeur, conversation en plein écran ----- */
        @media (max-width: 768px) {
            .hx-stats {
                display: none;
            }

            .hx-head {
                margin-bottom: 14px;
            }

            .hx-title h1 {
                font-size: 22px;
            }

            .ms-box {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 420px;
                border-radius: 16px;
            }

            .ms-side {
                border-right: none;
                min-height: calc(100vh - 290px);
                min-height: calc(100dvh - 290px);
            }

            .ms-conv {
                max-height: none;
            }

            .ms-box .ms-main {
                display: none;
            }

            .ms-box.thread .ms-side {
                display: none;
            }

            .ms-box.thread .ms-main {
                display: flex;
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                z-index: 10001;
                height: 100vh;
                height: 100dvh;
                animation: msUp .28s cubic-bezier(.2, .8, .2, 1);
            }

            @keyframes msUp {
                from {
                    transform: translateX(30px);
                    opacity: 0;
                }

                to {
                    transform: none;
                    opacity: 1;
                }
            }

            .ms-box.thread {
                overflow: visible;
            }

            .ms-back {
                display: grid;
                place-items: center;
            }

            .ms-head {
                padding-top: calc(10px + env(safe-area-inset-top, 0px));
            }

            .ms-prof span {
                display: none;
            }

            .ms-prof {
                width: 40px;
                height: 40px;
                padding: 0;
                justify-content: center;
                border-radius: 50%;
            }

            .ms-msgs {
                padding: 14px 12px;
            }

            .ms-bub {
                max-width: 86%;
            }

            .ms-foot {
                padding: 10px 10px calc(12px + env(safe-area-inset-bottom, 0px));
            }

            .ms-form textarea {
                font-size: 16px;
            }

            .ms-hint {
                display: none;
            }

            .ms-down {
                bottom: calc(86px + env(safe-area-inset-bottom, 0px));
            }

            .ms-drop {
                display: none !important;
            }

            html.ms-lock,
            html.ms-lock body {
                overflow: hidden !important;
            }
        }

        @media (max-width: 480px) {
            .ms-pill {
                padding: 7px 12px;
                font-size: 12.5px;
            }

            .ms-sh {
                padding: 14px 12px 8px;
            }

            .ms-item {
                margin: 2px 4px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .ms-row,
            .ms-box.thread .ms-main {
                animation: none !important;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/menu.php'; ?>

    <div class="content hx-wrap">

        <!-- ===== EN-TÊTE ===== -->
        <div class="hx-head">
            <div class="hx-title">
                <div class="hx-title-ico"><i class="fas fa-comments"></i></div>
                <div>
                    <h1>Messages</h1>
                    <p>Discutez en direct avec vos utilisateurs</p>
                </div>
            </div>
            <div class="ms-pill" id="msPill"><i class="fas fa-circle-check"></i> <span id="msPillT">Tout est lu</span>
            </div>
        </div>

        <!-- ===== SYNTHÈSE ===== -->
        <div class="hx-stats">
            <div class="hx-stat hx-stat--red">
                <div class="hx-stat-ico"><i class="fas fa-envelope"></i></div>
                <div><strong id="stUnread">0</strong><span>Messages non lus</span></div>
            </div>
            <div class="hx-stat hx-stat--orange">
                <div class="hx-stat-ico"><i class="fas fa-reply"></i></div>
                <div><strong id="stReply">0</strong><span>À répondre</span></div>
            </div>
            <div class="hx-stat hx-stat--blue">
                <div class="hx-stat-ico"><i class="fas fa-comments"></i></div>
                <div><strong id="stConv">0</strong><span>Conversations</span></div>
            </div>
        </div>

        <!-- ===== MESSAGERIE ===== -->
        <div class="ms-box" id="msBox">
            <aside class="ms-side">
                <div class="ms-sh">
                    <div class="ms-search">
                        <i class="fas fa-search"></i>
                        <input id="msSearch" type="text" placeholder="Rechercher un utilisateur…" autocomplete="off"
                            enterkeyhint="search">
                        <button type="button" id="msClear" aria-label="Effacer"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="ms-tabs">
                        <button type="button" class="ms-tab on" id="tabAll">Toutes</button>
                        <button type="button" class="ms-tab" id="tabUn">Non lues <span class="n ms-hide"
                                id="tabUnN">0</span></button>
                    </div>
                </div>
                <div class="ms-conv" id="msConv">
                    <div class="ms-sk"><i></i><b></b></div>
                    <div class="ms-sk"><i></i><b></b></div>
                    <div class="ms-sk"><i></i><b></b></div>
                </div>
            </aside>

            <section class="ms-main" id="msMain">
                <div class="ms-head ms-hide" id="msHead">
                    <button class="ms-back" id="msBack" type="button" aria-label="Retour"><i
                            class="fas fa-arrow-left"></i></button>
                    <div class="ms-av" id="msHeadAv"></div>
                    <div class="ms-ht"><b id="msTitle"></b><small id="msSub"></small></div>
                    <a class="ms-prof" id="msProfile" href="#"><i class="fas fa-user"></i><span>Profil</span></a>
                </div>
                <div class="ms-msgs" id="msMsgs">
                    <div class="ms-welcome">
                        <div class="ic"><i class="fas fa-comments"></i></div><b>Vos messages</b>Sélectionnez une
                        conversation à gauche, ou recherchez un utilisateur pour lui écrire en premier.
                    </div>
                </div>
                <button class="ms-down" id="msDown" type="button"><i class="fas fa-arrow-down"></i><span
                        id="msDownT"></span></button>
                <div class="ms-foot ms-hide" id="msFoot">
                    <div class="ms-err" id="msErr" role="alert"></div>
                    <div class="ms-chip" id="msChip">
                        <div class="ms-fi" id="msChipIco"></div>
                        <div class="ms-fn"><span id="msChipName"></span><small id="msChipSize"></small></div>
                        <button type="button" id="msChipX" aria-label="Retirer"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="ms-form">
                        <input type="file" id="msFile" hidden
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp3,.mp4">
                        <button class="ms-b att" id="msAttach" type="button" aria-label="Joindre un fichier"><i
                                class="fas fa-paperclip"></i></button>
                        <textarea id="msText" rows="1" maxlength="2000" placeholder="Écrire une réponse…"></textarea>
                        <button class="ms-b snd off" id="msSend" type="button" aria-label="Envoyer"><i
                                class="fas fa-paper-plane"></i></button>
                    </div>
                    <div class="ms-hint">Entrée pour envoyer · Maj+Entrée pour aller à la ligne · glissez un fichier ou
                        collez une capture</div>
                </div>
                <div class="ms-drop"><i class="fas fa-cloud-arrow-up"></i><span>Déposez le fichier ici</span></div>
            </section>
        </div>
    </div>

    <div class="ms-lb" id="msLb">
        <div class="ms-lb-bar"><a id="msLbDl" href="#" aria-label="Télécharger"><i
                    class="fas fa-download"></i></a><button type="button" id="msLbX" aria-label="Fermer"><i
                    class="fas fa-xmark"></i></button></div>
        <img id="msLbImg" alt="">
    </div>

    <script>
        (function () {
            var API = 'support_api.php', CSRF = <?= json_encode($csrf) ?>, MAX = 8 * 1024 * 1024, START = <?= (int) $open_user ?>;
            var $ = function (id) { return document.getElementById(id); };
            var box = $('msBox'), main = $('msMain'), conv = $('msConv'), msgs = $('msMsgs'), txt = $('msText'), send = $('msSend'), fileIn = $('msFile'),
                chip = $('msChip'), err = $('msErr'), search = $('msSearch'), down = $('msDown'), root = document.documentElement;
            var OK_EXT = /\.(jpe?g|png|gif|webp|pdf|txt|zip|docx?|xlsx?|pptx?|mp3|mp4)$/i;
            var coarse = window.matchMedia && matchMedia('(pointer: coarse)').matches;
            var PAL = ['#3b82f6,#2563eb', '#8b5cf6,#6d28d9', '#06b6d4,#0891b2', '#10b981,#059669', '#f59e0b,#d97706', '#ec4899,#db2777', '#6366f1,#4f46e5', '#ef4444,#dc2626'];

            var cur = 0, curName = '', lastId = 0, peerRead = 0, seen = {}, lastDay = '', lastFrom = '', lastTs = 0, unseen = 0,
                busyT = false, busyC = false, fails = 0, tT = null, tC = null, sTimer = null, convs = [], onlyUnread = false, totalUnread = 0,
                baseTitle = document.title, chipUrl = null, stickNext = false;

            function showErr(m) { err.textContent = m || ''; err.style.display = m ? 'block' : 'none'; }
            function toDate(s) { return new Date(s.replace(' ', 'T') + 'Z'); }
            function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' Mo' : Math.max(1, Math.round(n / 1024)) + ' Ko'; }
            function ini(n) { var p = (n || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p[1] ? p[1][0] : (p[0][1] || ''))).toUpperCase(); }
            function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
            function avatar(id, name, cls) { var a = el('div', cls || 'ms-av', ini(name)), c = PAL[id % PAL.length].split(','); a.style.background = 'linear-gradient(135deg,' + c[0] + ',' + c[1] + ')'; return a; }
            function when(s) {
                var d = toDate(s), now = new Date(), y = new Date(); y.setDate(now.getDate() - 1);
                if (d.toDateString() === now.toDateString()) { return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
                if (d.toDateString() === y.toDateString()) { return 'Hier'; }
                return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
            }
            function near() { return msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 100; }
            function get(q) { return fetch(API + '?' + q, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { if (r.status === 401) { throw 'session'; } if (!r.ok) { throw 'http'; } return r.json(); }); }
            function kind(name) {
                var x = (name.split('.').pop() || '').toLowerCase();
                if (x === 'pdf') { return ['fa-file-pdf', 't-pdf']; }
                if (x === 'doc' || x === 'docx') { return ['fa-file-word', '']; }
                if (x === 'xls' || x === 'xlsx') { return ['fa-file-excel', 't-excel']; }
                if (x === 'ppt' || x === 'pptx') { return ['fa-file-powerpoint', 't-ppt']; }
                if (x === 'zip') { return ['fa-file-zipper', 't-zip']; }
                if (x === 'mp3') { return ['fa-file-audio', 't-media']; }
                if (x === 'mp4') { return ['fa-file-video', 't-media']; }
                if (x === 'txt') { return ['fa-file-lines', '']; }
                return ['fa-file', ''];
            }
            function linkify(t, b) {
                var re = /(https?:\/\/[^\s<]*[^\s<.,;:!?)\]"'])/g, last = 0, m;
                while ((m = re.exec(t))) {
                    if (m.index > last) { b.appendChild(document.createTextNode(t.slice(last, m.index))); }
                    var a = el('a', '', m[0]); a.href = m[0]; a.target = '_blank'; a.rel = 'noopener noreferrer'; b.appendChild(a);
                    last = m.index + m[0].length;
                }
                if (last < t.length) { b.appendChild(document.createTextNode(t.slice(last))); }
            }

            /* ---------- synthèse ---------- */
            function setTotal(n) {
                totalUnread = n;
                var pill = $('msPill'); pill.classList.toggle('has', n > 0);
                pill.querySelector('i').className = 'fas ' + (n > 0 ? 'fa-bell' : 'fa-circle-check');
                $('msPillT').textContent = n > 0 ? n + ' message' + (n > 1 ? 's' : '') + ' non lu' + (n > 1 ? 's' : '') : 'Tout est lu';
                $('stUnread').textContent = n;
                var tn = $('tabUnN'); tn.textContent = n > 99 ? '99+' : n; tn.classList.toggle('ms-hide', !n);
                document.title = n ? '(' + n + ') ' + baseTitle : baseTitle;
            }

            /* ---------- liste ---------- */
            function item(c, sub, isNew) {
                var it = el('div', 'ms-item' + (c.id === cur ? ' on' : '') + (c.unread ? ' unread' : '')); it.dataset.id = c.id; it.dataset.name = c.name;
                it.appendChild(avatar(c.id, c.name));
                var inf = el('div', 'ms-info'), nm = el('div', 'ms-name'); nm.appendChild(el('span', '', c.name));
                if (c.t) { nm.appendChild(el('small', '', when(c.t))); }
                inf.appendChild(nm);
                var l = el('div', 'ms-last'); l.appendChild(el('span', '', sub !== undefined ? sub : ((c.mine ? 'Vous : ' : '') + c.last)));
                if (c.unread) { l.appendChild(el('span', 'ms-un', c.unread > 99 ? '99+' : c.unread)); }
                inf.appendChild(l); it.appendChild(inf);
                it.addEventListener('click', function () { openConv(c.id, c.name); });
                return it;
            }
            function drawList(others) {
                var q = search.value.trim(), shown = onlyUnread ? convs.filter(function (c) { return c.unread > 0; }) : convs;
                conv.textContent = '';
                if (shown.length) {
                    if (q && others && others.length) { conv.appendChild(el('div', 'ms-lbl', 'Conversations')); }
                    shown.forEach(function (c) { conv.appendChild(item(c)); });
                } else if (!(others && others.length)) {
                    var n = el('div', 'ms-none'); n.innerHTML = '<i class="fas ' + (onlyUnread ? 'fa-circle-check' : (q ? 'fa-user-slash' : 'fa-inbox')) + '"></i>';
                    n.appendChild(document.createTextNode(onlyUnread ? 'Aucun message non lu.' : (q ? 'Aucun utilisateur trouvé.' : "Aucune conversation pour l'instant. Recherchez un utilisateur ci-dessus pour lui écrire en premier.")));
                    conv.appendChild(n);
                }
                if (!onlyUnread && others && others.length) {
                    conv.appendChild(el('div', 'ms-lbl', 'Autres utilisateurs'));
                    others.forEach(function (x) { conv.appendChild(item({ id: x.id, name: x.name }, '@' + x.user + ' · nouvelle conversation')); });
                }
            }
            function loadList() {
                if (busyC) { return; } busyC = true;
                var q = search.value.trim();
                get('action=conversations&q=' + encodeURIComponent(q)).then(function (j) {
                    convs = j.conversations; setTotal(j.unread || 0);
                    if (!q) { $('stConv').textContent = convs.length; $('stReply').textContent = convs.filter(function (c) { return !c.mine; }).length; }
                    if (q.length >= 2) {
                        return get('action=users&q=' + encodeURIComponent(q)).then(function (u) {
                            var have = {}; convs.forEach(function (c) { have[c.id] = 1; });
                            drawList(u.users.filter(function (x) { return !have[x.id]; }));
                        });
                    }
                    drawList();
                }).catch(function () { if (!conv.querySelector('.ms-item')) { conv.textContent = ''; var n = el('div', 'ms-none'); n.innerHTML = '<i class="fas fa-wifi"></i>'; n.appendChild(document.createTextNode('Connexion impossible. Nouvelle tentative…')); conv.appendChild(n); } })
                    .then(function () { busyC = false; });
            }
            function schedList() { clearTimeout(tC); tC = setTimeout(function () { if (!document.hidden) { loadList(); } schedList(); }, 6000); }
            search.addEventListener('input', function () { $('msClear').style.display = search.value ? 'block' : 'none'; clearTimeout(sTimer); sTimer = setTimeout(loadList, 300); });
            $('msClear').addEventListener('click', function () { search.value = ''; this.style.display = 'none'; loadList(); search.focus(); });
            $('tabAll').addEventListener('click', function () { onlyUnread = false; $('tabAll').classList.add('on'); $('tabUn').classList.remove('on'); drawList(); loadList(); });
            $('tabUn').addEventListener('click', function () { onlyUnread = true; $('tabUn').classList.add('on'); $('tabAll').classList.remove('on'); drawList(); });

            /* ---------- messages ---------- */
            function render(m) {
                if (seen[m.id]) { return false; } seen[m.id] = 1;
                var w = msgs.querySelector('.ms-welcome, .ms-nomsg'); if (w) { w.remove(); }
                var d = toDate(m.t), dk = d.toDateString(), mine = m.from === 'admin';
                if (dk !== lastDay) { lastDay = dk; lastFrom = ''; msgs.appendChild(el('div', 'ms-day', d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))); }
                var cont = lastFrom === m.from && (d - lastTs) < 300000; lastFrom = m.from; lastTs = +d;
                var row = el('div', 'ms-row ' + (mine ? 'me' : 'them') + (cont ? ' cont' : '')); row.dataset.id = m.id;
                var b = el('div', 'ms-bub');
                if (m.file) {
                    var url = 'support_file.php?id=' + m.id;
                    if (m.file.image) {
                        var im = el('img', 'ms-img'); im.src = url; im.alt = m.file.name; im.loading = 'lazy';
                        im.addEventListener('click', function () { lightbox(url); });
                        im.addEventListener('load', function () { if (stickNext) { msgs.scrollTop = msgs.scrollHeight; } });
                        b.appendChild(im);
                    } else {
                        var k = kind(m.file.name), f = el('a', 'ms-file'); f.href = url + '&dl=1';
                        var ic = el('div', 'ms-fi ' + k[1]); ic.innerHTML = '<i class="fas ' + k[0] + '"></i>'; f.appendChild(ic);
                        var fn = el('div', 'ms-fn'); fn.appendChild(el('span', '', m.file.name)); fn.appendChild(el('small', '', size(m.file.size))); f.appendChild(fn); b.appendChild(f);
                    }
                }
                if (m.text) { var p = el('div', 'ms-txt'); linkify(m.text, p); b.appendChild(p); }
                var meta = el('div', 'ms-meta'); meta.appendChild(el('span', '', d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })));
                if (mine) { meta.appendChild(el('span', 'ms-tick' + (m.id <= peerRead ? ' rd' : ''), m.id <= peerRead ? '✓✓' : '✓')); }
                b.appendChild(meta); row.appendChild(b); msgs.appendChild(row);
                if (m.id > lastId) { lastId = m.id; }
                return !mine;
            }
            function ticks() { msgs.querySelectorAll('.ms-row.me').forEach(function (r) { var t = r.querySelector('.ms-tick'); if (t) { var rd = +r.dataset.id <= peerRead; t.textContent = rd ? '✓✓' : '✓'; t.classList.toggle('rd', rd); } }); }
            function toBottom() { msgs.scrollTop = msgs.scrollHeight; unseen = 0; down.style.display = 'none'; }
            msgs.addEventListener('scroll', function () { if (near()) { unseen = 0; down.style.display = 'none'; } });
            down.addEventListener('click', toBottom);

            function openConv(id, name) {
                cur = id; curName = name || ''; lastId = 0; peerRead = 0; seen = {}; lastDay = ''; lastFrom = ''; lastTs = 0; unseen = 0; fails = 0; busyT = false;
                msgs.textContent = ''; down.style.display = 'none'; clearFile(); txt.value = ''; grow();
                var sk = el('div', 'ms-welcome'); sk.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="font-size:26px;color:var(--hx-blue)"></i>'; msgs.appendChild(sk);
                box.classList.add('thread'); root.classList.add('ms-lock'); $('msHead').classList.remove('ms-hide'); $('msFoot').classList.remove('ms-hide'); showErr('');
                if (curName) { setHead({ id: id, name: curName, user: '' }); }
                try { history.replaceState(null, '', '?user=' + id); } catch (e) { }
                document.querySelectorAll('.ms-item').forEach(function (n) { n.classList.toggle('on', +n.dataset.id === id); });
                clearTimeout(tT); pollThread(true); if (!coarse) { setTimeout(function () { txt.focus(); }, 120); }
            }
            function setHead(u) {
                var h = $('msHeadAv'), a = avatar(u.id, u.name); h.className = a.className; h.style.background = a.style.background; h.textContent = a.textContent;
                $('msTitle').textContent = u.name; $('msSub').textContent = u.user ? '@' + u.user + ' · Utilisateur' : 'Utilisateur';
                $('msProfile').href = 'utilisateur_detail.php?id=' + u.id;
            }
            function pollThread(first) {
                if (!cur || busyT) { return; } busyT = true;
                var id = cur;
                get('action=poll&user=' + id + '&after=' + lastId).then(function (j) {
                    if (id !== cur) { return; }
                    fails = 0; showErr(''); setHead(j.user);
                    var stick = near() || first, incoming = 0; stickNext = stick;
                    j.messages.forEach(function (m) { if (render(m)) { incoming++; } });
                    peerRead = j.peer_read || 0; ticks();
                    if (first && !j.messages.length) { var w = msgs.querySelector('.ms-welcome'); if (w) { w.remove(); } var n = el('div', 'ms-welcome ms-nomsg'); n.innerHTML = '<div class="ic"><i class="fas fa-comment-dots"></i></div><b></b>Aucun message pour l\'instant. Écrivez le premier !'; n.querySelector('b').textContent = j.user.name; msgs.appendChild(n); }
                    if (stick) { toBottom(); }
                    else if (incoming) { unseen += incoming; $('msDownT').textContent = unseen > 1 ? ' ' + unseen + ' nouveaux messages' : ' Nouveau message'; down.style.display = 'flex'; }
                    if (first || incoming) { loadList(); }
                }).catch(function (e) { fails++; if (e === 'session') { showErr('Session expirée : rechargez la page.'); fails = 8; } else if (fails > 2) { showErr('Connexion perdue, nouvelle tentative…'); } })
                    .then(function () { busyT = false; schedThread(); });
            }
            function schedThread() { clearTimeout(tT); if (!cur || document.hidden) { return; } tT = setTimeout(pollThread, Math.min(60000, 3000 * (1 + fails))); }
            document.addEventListener('visibilitychange', function () { if (!document.hidden) { clearTimeout(tT); if (cur) { pollThread(); } loadList(); } });
            function back() { box.classList.remove('thread'); root.classList.remove('ms-lock'); cur = 0; clearTimeout(tT); $('msHead').classList.add('ms-hide'); $('msFoot').classList.add('ms-hide'); try { history.replaceState(null, '', 'messages.php'); } catch (e) { } document.querySelectorAll('.ms-item.on').forEach(function (n) { n.classList.remove('on'); }); loadList(); }
            $('msBack').addEventListener('click', back);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if ($('msLb').classList.contains('open')) { closeLb(); } } });

            /* ---------- visionneuse ---------- */
            function lightbox(url) { $('msLbImg').src = url; $('msLbDl').href = url + '&dl=1'; $('msLb').classList.add('open'); }
            function closeLb() { $('msLb').classList.remove('open'); $('msLbImg').removeAttribute('src'); }
            $('msLbX').addEventListener('click', closeLb);
            $('msLb').addEventListener('click', function (e) { if (e.target === this) { closeLb(); } });

            /* ---------- saisie / fichier ---------- */
            function upd() { send.classList.toggle('off', !txt.value.trim() && !fileIn.files.length); }
            function grow() { txt.style.height = 'auto'; txt.style.height = Math.min(txt.scrollHeight, 130) + 'px'; upd(); }
            txt.addEventListener('input', grow);
            txt.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !coarse) { e.preventDefault(); submit(); } });
            $('msAttach').addEventListener('click', function () { fileIn.click(); });
            function clearFile() { fileIn.value = ''; chip.style.display = 'none'; if (chipUrl) { URL.revokeObjectURL(chipUrl); chipUrl = null; } upd(); }
            function setFile(f) {
                if (!f || !cur) { return; }
                if (!OK_EXT.test(f.name)) { showErr('Type de fichier non autorisé.'); fileIn.value = ''; return; }
                if (f.size > MAX) { showErr('Fichier trop volumineux (8 Mo maximum).'); fileIn.value = ''; return; }
                showErr('');
                try { var dt = new DataTransfer(); dt.items.add(f); fileIn.files = dt.files; } catch (e) { }
                var ico = $('msChipIco'), k = kind(f.name); ico.className = 'ms-fi ' + k[1]; ico.textContent = '';
                if (/^image\//.test(f.type)) { chipUrl = URL.createObjectURL(f); var im = document.createElement('img'); im.src = chipUrl; ico.appendChild(im); }
                else { ico.innerHTML = '<i class="fas ' + k[0] + '"></i>'; }
                $('msChipName').textContent = f.name; $('msChipSize').textContent = size(f.size); chip.style.display = 'flex'; upd();
            }
            fileIn.addEventListener('change', function () { setFile(fileIn.files[0]); });
            $('msChipX').addEventListener('click', clearFile);
            txt.addEventListener('paste', function (e) {
                var items = (e.clipboardData || {}).items || [];
                for (var i = 0; i < items.length; i++) { if (items[i].kind === 'file') { var f = items[i].getAsFile(); if (f) { if (!f.name || f.name === 'image.png') { try { f = new File([f], 'capture-' + Date.now() + '.png', { type: f.type }); } catch (x) { } } setFile(f); e.preventDefault(); return; } } }
            });
            var dragN = 0;
            main.addEventListener('dragenter', function (e) { if (!cur) { return; } e.preventDefault(); dragN++; main.classList.add('drag'); });
            main.addEventListener('dragover', function (e) { e.preventDefault(); });
            main.addEventListener('dragleave', function () { if (--dragN <= 0) { dragN = 0; main.classList.remove('drag'); } });
            main.addEventListener('drop', function (e) { e.preventDefault(); dragN = 0; main.classList.remove('drag'); if (e.dataTransfer && e.dataTransfer.files[0]) { setFile(e.dataTransfer.files[0]); } });

            /* ---------- envoi ---------- */
            function submit() {
                var text = txt.value.trim(), f = fileIn.files[0];
                if (!cur || (!text && !f) || send.disabled) { return; }
                var fd = new FormData(); fd.append('action', 'send'); fd.append('user', cur); fd.append('contenu', text); if (f) { fd.append('fichier', f); }
                send.disabled = true; showErr('');
                fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (x) {
                        if (!x.ok) { showErr(x.j.error || 'Erreur'); return; }
                        txt.value = ''; clearFile(); grow(); stickNext = true; render(x.j.message); toBottom(); loadList();
                    })
                    .catch(function () { showErr('Connexion perdue.'); })
                    .then(function () { send.disabled = false; if (!coarse) { txt.focus(); } });
            }
            send.addEventListener('click', submit);

            loadList(); schedList();
            if (START > 0) { openConv(START); }
        })();
    </script>
</body>

</html>