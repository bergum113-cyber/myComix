/*!
 * mc-video.js — ★ (2026-10-31) 동영상 플레이어(FileStation 동영상 플레이어 기능을 myComix 에 맞춤)
 *
 * FileStation 동영상 플레이어(js/fs-video-skin.js — 겹침형 조작 줄)를 붙이고, 그 플레이어가 기대하는 페이지 쪽 기능을 채움
 *   - 가운데 재생·±5초 버튼, 이동 표시(‹ −5 / +5 › 누적), 재생 방식 배지, 동영상 정보 창(ⓘ · I 키)
 *   - 단축키(FileStation 과 같음): Space/K 재생·일시정지, ←/→ 5초, J/L 10초, ↑/↓ 음량 5%, M 음소거, F 전체화면, I 정보, C 자막, < > 속도
 *     (입력칸 밖이면 어디서나 — 마우스로 누른 플레이어 버튼·음량 막대에 포커스가 남아 있어도 단축키가 먹게 그 포커스는 뺌)
 *   - 재생 속도·반복·A-B 구간 반복·이전/다음 영상·자동 다음 재생(다음 영상 자동 재생 — 막히면 소리를 끈 채 재생 + '소리 켜기')
 *   - 이어보기(보던 위치 기억 → 다시 열면 그 위치부터 + '처음부터' 알림)
 *   - 자막: 같은 폴더(압축 파일 속이면 같은 압축 폴더)의 srt·smi·vtt·ass — 켜기/끄기·크기·위치·싱크·파일 고르기(FileStation 파서·저장 칸 그대로)
 *   - 실시간 변환 재생(FFmpeg 설정 시): 브라우저가 못 여는 형식은 자동으로, 여는 형식은 ⚙ '재생 방식'에서. 화질·음성 트랙 고르기,
 *     아직 변환 안 된 곳으로 옮기면 그 위치부터 다시 변환. 일반 재생이 형식 오류로 실패하면 변환 재생으로 넘어감
 *   - 재생바 장면 미리보기(FFmpeg 설정 시, 일반 파일)
 * fs-video-skin.js 가 없으면 종전처럼 브라우저 기본 조작 줄 + 아래 버튼 줄.
 * 저장은 localStorage(실패해도 기본값으로 동작)
 */
(function () {
    'use strict';
    if (window.MCVideo) return;

    function lsGet(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    function lsDel(k) { try { localStorage.removeItem(k); } catch (e) {} }
    function fmtT(s) {
        s = Math.max(0, Math.floor(s || 0));
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (x < 10 ? '0' : '') + x;
    }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    var RATES = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    var IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // ── 자막 파서(FileStation share.php 와 같음 — 태그는 지우고 글자는 모두 이스케이프해서 <br> 만 남김)
    function subStripTags(s) { return String(s).replace(/<\/?[a-z][a-z0-9.]*\b[^>]*>/gi, '').replace(/<\/?\d[^>]*>/g, ''); }
    function subDecodeEnt(s) {
        if (s.indexOf('&') === -1) return s;
        try {
            var b = new DOMParser().parseFromString('<!doctype html><body>' + s.replace(/</g, '&lt;').replace(/\n/g, ''), 'text/html').body;
            return b ? b.textContent.replace(//g, '\n') : s;
        } catch (e) { return s; }
    }
    function subEsc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
    function parseSrt(text) {
        var cues = [], blocks = String(text).replace(/\r/g, '').split(/\n\n+/);
        blocks.forEach(function (block) {
            var lines = block.trim().split('\n');
            for (var i = 0; i < lines.length; i++) {
                var m = lines[i].match(/(\d{1,2}):(\d{2}):(\d{2})[.,](\d{3})\s*-->\s*(\d{1,2}):(\d{2}):(\d{2})[.,](\d{3})/);
                if (!m) continue;
                var start = +m[1] * 3600 + +m[2] * 60 + +m[3] + +m[4] / 1000, end = +m[5] * 3600 + +m[6] * 60 + +m[7] + +m[8] / 1000;
                var txt = subDecodeEnt(subStripTags(lines.slice(i + 1).join('\n'))).trim();
                if (txt) cues.push({ start: start, end: end, text: subEsc(txt).replace(/\n/g, '<br>') });
                break;
            }
        });
        return cues;
    }
    function parseSmi(text) {
        var cues = [], syncs = [], m;
        text = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var re = /<sync\s+start\s*=\s*"?(\d+)"?[^>]*>\s*(?:<p[^>]*>)?([\s\S]*?)(?=<sync|<\/body|$)/gi;
        while ((m = re.exec(text)) !== null) {
            var txt = subDecodeEnt(subStripTags(m[2].replace(/<br\s*\/?>/gi, '\n')).replace(/&nbsp;/gi, ' ')).replace(/ /g, ' ').replace(/\n[ \t]+\n/g, '\n\n').replace(/\n{2,}/g, '\n').trim();
            syncs.push({ ms: parseInt(m[1], 10), text: txt ? subEsc(txt).replace(/\n/g, '<br>') : '' });
        }
        syncs.sort(function (a, b) { return a.ms - b.ms; });
        for (var i = 0; i < syncs.length; i++) {
            if (!syncs[i].text) continue;
            var endMs = (i + 1 < syncs.length) ? syncs[i + 1].ms : syncs[i].ms + 5000;
            cues.push({ start: syncs[i].ms / 1000, end: endMs / 1000, text: syncs[i].text });
        }
        return cues;
    }
    function assTime(s) { var m = s.match(/(\d+):(\d{2}):(\d{2})[.](\d{2})/); return m ? +m[1] * 3600 + +m[2] * 60 + +m[3] + +m[4] / 100 : 0; }
    function parseAss(text) {
        var cues = [];
        String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').forEach(function (line) {
            if (line.indexOf('Dialogue:') !== 0) return;
            var parts = line.substring(9).split(',');
            if (parts.length < 10) return;
            var txt = subDecodeEnt(subStripTags(parts.slice(9).join(',').replace(/\{[^}]*\}/g, ''))).trim();
            txt = subEsc(txt).replace(/\\N/gi, '<br>');
            if (txt) cues.push({ start: assTime(parts[1].trim()), end: assTime(parts[2].trim()), text: txt });
        });
        return cues;
    }
    function parseSubtitle(text, ext) {
        if (ext === 'smi' || ext === 'sami') return parseSmi(text);
        if (ext === 'ass' || ext === 'ssa') return parseAss(text);
        return parseSrt(text);
    }

    function css() {
        if (document.getElementById('mc-video-css')) return;
        var s = document.createElement('style');
        s.id = 'mc-video-css';
        s.textContent =
            '.mcv-bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:8px}' +
            '.mcv-bar .mcv-sp{flex:1 1 auto}' +
            '.mcv-btn{height:34px;min-width:34px;padding:0 10px;border:1px solid #d5d9de;border-radius:17px;background:#fff;color:#3c4043;font-size:13px;display:inline-flex;align-items:center;justify-content:center;gap:4px;cursor:pointer;white-space:nowrap;line-height:1}' +
            '.mcv-btn:hover{background:#f1f3f4}.mcv-btn.on{background:#1a73e8;border-color:#1a73e8;color:#fff}.mcv-btn.half{border-color:#e8a200;color:#8a5300;background:#fff8e1}' +
            '.mcv-btn:focus{outline:none}.mcv-btn:focus-visible{outline:2px solid #80bdff;outline-offset:1px}' +
            '.mcv-sel{height:34px;border:1px solid #d5d9de;border-radius:17px;background:#fff;color:#3c4043;font-size:13px;padding:0 8px}' +
            '.mcv-ab-lbl{font-size:12px;color:#8a5300;font-variant-numeric:tabular-nums}' +
            '.mcv-resume{display:none;margin-top:8px;padding:8px 12px;border-radius:10px;background:#fff8e1;color:#5f4500;font-size:14px;align-items:center;gap:10px;flex-wrap:wrap}' +
            '.mcv-resume.show{display:flex}' +
            '.mcv-list{margin-top:10px;border:1px solid #e3e6ea;border-radius:10px;overflow:hidden;background:#fff}' +
            '.mcv-list-head{display:flex;align-items:center;gap:8px;padding:8px 12px;font-weight:600;font-size:14px;background:#f8f9fa;border-bottom:1px solid #e3e6ea}' +
            '.mcv-list-body{max-height:min(46vh,420px);overflow:auto}' +
            '.mcv-item{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:14px;color:inherit;text-decoration:none!important;border-bottom:1px solid #f1f3f4;cursor:pointer;word-break:break-all}' +
            '.mcv-item:last-child{border-bottom:0}.mcv-item:hover{background:#f1f3f4}' +
            '.mcv-item.cur{background:#e8f0fe;color:#0b57d0;font-weight:600}.mcv-item.no{color:#9aa0a6}' +
            '.mcv-item .mcv-no{flex:0 0 auto;min-width:22px;color:#9aa0a6;font-size:12px;font-variant-numeric:tabular-nums}' +
            '.mcv-toast{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,.65);color:#fff;padding:8px 14px;border-radius:18px;font-size:15px;pointer-events:none;opacity:0;transition:opacity .2s;z-index:5}' +
            '.mcv-toast.show{opacity:1}' +
            '.dark-mode .mcv-btn,[data-theme="dark"] .mcv-btn,.dark-mode .mcv-sel,[data-theme="dark"] .mcv-sel{background:#2a2a2a;border-color:#444;color:#e0e0e0}' +
            '.dark-mode .mcv-btn:hover,[data-theme="dark"] .mcv-btn:hover{background:#333}' +
            '.dark-mode .mcv-btn.on,[data-theme="dark"] .mcv-btn.on{background:#8ab4f8;border-color:#8ab4f8;color:#111}' +
            '.dark-mode .mcv-list,[data-theme="dark"] .mcv-list{background:#1e1e1e;border-color:#333}' +
            '.dark-mode .mcv-list-head,[data-theme="dark"] .mcv-list-head{background:#2a2a2a;border-color:#333}' +
            '.dark-mode .mcv-item,[data-theme="dark"] .mcv-item{border-color:#2a2a2a}.dark-mode .mcv-item:hover,[data-theme="dark"] .mcv-item:hover{background:#2a2a2a}' +
            '.dark-mode .mcv-item.cur,[data-theme="dark"] .mcv-item.cur{background:#1f3352;color:#8ab4f8}' +
            '.dark-mode .mcv-resume,[data-theme="dark"] .mcv-resume{background:#3a3020;color:#fdd663}' +
            '@media (max-width:576px){.mcv-btn{height:32px;padding:0 8px;font-size:12px}.mcv-btn .mcv-lbl{display:none}.mcv-sel{height:32px;font-size:12px}}' +
            // FileStation 플레이어가 붙은 동안 — 겹치는 아래 버튼 숨김 + 가운데 재생·±5초 버튼 기본 규칙(FileStation 공유 화면과 같음)
            '.mcv-dup{display:none!important}' +
            '.mcv-wrap .video-play-overlay{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:72px;height:72px;border-radius:50%;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:12;transition:opacity .25s,transform .15s;border:2px solid rgba(255,255,255,.6);opacity:0;pointer-events:none;padding:0;color:#fff;margin:0}' +
            '.mcv-wrap .video-play-overlay:focus{outline:none}.mcv-wrap .video-play-overlay:focus-visible{outline:2px solid #fff;outline-offset:2px}' +
            '.mcv-wrap .video-play-overlay:hover{transform:translate(-50%,-50%) scale(1.08)}.mcv-wrap .video-play-overlay:active{transform:translate(-50%,-50%) scale(.95)}' +
            '.mcv-wrap .video-play-overlay.video-seek-btn{width:52px;height:52px;border-width:1px}' +
            '.mcv-wrap .video-play-overlay.video-seek-btn-back{left:calc(50% - clamp(96px,20%,150px))}.mcv-wrap .video-play-overlay.video-seek-btn-fwd{left:calc(50% + clamp(96px,20%,150px))}' +
            '.mcv-wrap:not(.playing) .video-play-overlay{opacity:1;pointer-events:auto}' +
            '.mcv-wrap.playing .video-play-overlay.mcv-cplay{pointer-events:auto}' +
            '@media (hover:hover) and (pointer:fine){.mcv-wrap.playing:hover .video-play-overlay{opacity:.6;pointer-events:auto}.mcv-wrap.playing:hover .video-play-overlay:hover{opacity:1}}' +
            '.mcv-wrap.playing.show-controls .video-play-overlay{opacity:.9;pointer-events:auto}' +
            '@media (pointer:coarse){.mcv-wrap.playing:not(.show-controls):hover .video-play-overlay{opacity:0!important}.mcv-wrap.playing:not(.show-controls) .video-play-overlay{pointer-events:none!important}}' +
            '.mcv-wrap.playing.fsvs-idle .video-play-overlay,.mcv-wrap.playing.fsvs-idle:hover .video-play-overlay{opacity:0!important}' +
            '.mcv-wrap.playing.fsvs-idle .video-play-overlay.video-seek-btn{pointer-events:none!important}' +
            '.mcv-wrap.playing.fsvs-idle{cursor:none}' +
            '.mcv-wrap.mcv-skin:fullscreen,.mcv-wrap.mcv-skin:-webkit-full-screen{border-radius:0;display:flex;align-items:center;justify-content:center}' +
            '.mcv-wrap.mcv-skin:fullscreen video,.mcv-wrap.mcv-skin:-webkit-full-screen video{max-height:100vh!important;height:100%;width:100%;object-fit:contain}' +
            // ★ 이동 표시(FileStation 공유 화면과 같음 — 스킨 CSS 가 화살표 모양으로 꾸밈)
            '.mcv-wrap .video-seek-overlay{position:absolute;top:50%;transform:translateY(-50%);min-width:48px;height:48px;padding:0 10px;box-sizing:border-box;border-radius:24px;background:rgba(0,0,0,.6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:bold;opacity:0;pointer-events:none;z-index:13;white-space:nowrap}' +
            '.mcv-wrap .video-seek-left{left:16px}.mcv-wrap .video-seek-right{right:16px}' +
            '.mcv-wrap .video-seek-overlay.show{animation:mcvSeekFlash 1.2s ease-out forwards}.mcv-wrap .video-seek-overlay.show.again{animation:mcvSeekAgain 1.2s ease-out forwards}' +
            '@keyframes mcvSeekFlash{0%{opacity:0;transform:translateY(-50%) scale(.8)}10%{opacity:1;transform:translateY(-50%) scale(1.1)}20%{opacity:1;transform:translateY(-50%) scale(1)}70%{opacity:1;transform:translateY(-50%) scale(1)}100%{opacity:0;transform:translateY(-50%) scale(1)}}' +
            '@keyframes mcvSeekAgain{0%{opacity:1;transform:translateY(-50%) scale(1.06)}8%{opacity:1;transform:translateY(-50%) scale(1)}70%{opacity:1;transform:translateY(-50%) scale(1)}100%{opacity:0;transform:translateY(-50%) scale(1)}}' +
            // ★ 재생 방식 배지(FileStation .stream-badge 와 같음) — 멈춰 있거나 조작부가 보일 때만
            '.mcv-wrap .mcv-badge{position:absolute;top:10px;left:10px;max-width:calc(100% - 20px);font-size:11px;font-weight:bold;padding:3px 8px;border-radius:3px;z-index:10;pointer-events:none;color:#fff;opacity:.9;transition:opacity .3s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
            '.mcv-wrap .mcv-badge.native{background:rgba(76,175,80,.85)}.mcv-wrap .mcv-badge.tc{background:rgba(255,152,0,.85)}.mcv-wrap .mcv-badge.err{background:rgba(220,53,69,.9);white-space:normal}' +
            '.mcv-wrap.playing .mcv-badge{opacity:0}' +
            '@media (hover:hover) and (pointer:fine){.mcv-wrap.playing:hover .mcv-badge{opacity:.9}}' +
            '.mcv-wrap.playing.show-controls .mcv-badge{opacity:.9}.mcv-wrap.playing.fsvs-idle .mcv-badge,.mcv-wrap.playing.fsvs-idle:hover .mcv-badge{opacity:0}' +
            // ★ 자막(FileStation .subtitle-overlay 와 같음 — 스킨이 조작 줄과 겹치지 않게 올려 줌)
            '.mcv-wrap .mcv-subbox{position:absolute;inset:0;pointer-events:none;z-index:10}' +
            '.mcv-wrap .subtitle-overlay{position:absolute;bottom:8%;left:5%;right:5%;text-align:center;color:#fff;font-size:1.1em;font-weight:500;text-shadow:-1px -1px 0 #000,1px -1px 0 #000,-1px 1px 0 #000,1px 1px 0 #000,0 0 6px rgba(0,0,0,.9);pointer-events:none;line-height:1.5;word-wrap:break-word;overflow-wrap:break-word}' +
            // ★ 변환 준비 중 표시
            '.mcv-wrap .mcv-spin{position:absolute;left:50%;top:50%;width:44px;height:44px;margin:-22px 0 0 -22px;border:3px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:mcvSpin .9s linear infinite;z-index:11;pointer-events:none;display:none}' +
            '.mcv-wrap.mcv-busy .mcv-spin{display:block}.mcv-wrap.mcv-busy .video-play-overlay{opacity:0!important;pointer-events:none!important}' +
            '@keyframes mcvSpin{to{transform:rotate(360deg)}}' +
            // ★ 동영상 정보 창
            '.mcv-info-ov{position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:16px}' +
            '.mcv-info{background:#fff;color:#212529;border-radius:12px;max-width:520px;width:100%;max-height:calc(100vh - 32px);overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.35)}' +
            '.mcv-info-h{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid #e9ecef;font-weight:700}' +
            '.mcv-info-h span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '.mcv-info-x{border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:inherit;padding:0 4px}' +
            '.mcv-info table{width:100%;border-collapse:collapse;font-size:14px}.mcv-info td{padding:7px 14px;border-bottom:1px solid #f1f3f5;vertical-align:top;word-break:break-all}' +
            '.mcv-info td:first-child{width:34%;color:#6c757d;white-space:nowrap;word-break:normal}' +
            '.dark-mode .mcv-info,[data-theme="dark"] .mcv-info{background:#1e1e1e;color:#e0e0e0}.dark-mode .mcv-info-h,[data-theme="dark"] .mcv-info-h{border-color:#333}' +
            '.dark-mode .mcv-info td,[data-theme="dark"] .mcv-info td{border-color:#2a2a2a}.dark-mode .mcv-info td:first-child,[data-theme="dark"] .mcv-info td:first-child{color:#a8adb3}';
        (document.head || document.documentElement).appendChild(s);
    }

    /**
     * @param {HTMLVideoElement} video
     * @param {Object} o  {
     *   key: 이어보기 열쇠, list:[{title,url,playable,current}], onPick(idx), onNext(), showList, manageEnded, t: {번역},
     *   autoplay: true 면 열자마자 재생(다음 영상 자동 재생으로 열린 경우), nextParam: 다음 영상 주소에 붙일 꼬리('&autoplay=1'),
     *   badge: {text, mode}, info: [[이름, 값], ...] (정보 창), subs: [{name, ext, content}] (자막),
     *   native: 일반 재생 주소(없으면 처음부터 변환 재생), tc: {url, dur, h, audio:[{label}], auto} (FFmpeg 설정 시), frameUrl: 장면 주소 앞부분
     * }
     */
    function init(video, o) {
        if (!video || video.__mcv) return video && video.__mcv;
        o = o || {};
        var T = o.t || {};
        css();
        var api = { video: video };
        video.__mcv = api;

        // ── 컨트롤 줄
        var anchor = video.closest('.video-container') || video;
        var bar = el('div', 'mcv-bar');
        var resume = el('div', 'mcv-resume');
        var host = o.mount || null;
        if (host) { host.appendChild(resume); host.appendChild(bar); }
        else { anchor.parentNode.insertBefore(resume, anchor.nextSibling); resume.parentNode.insertBefore(bar, resume.nextSibling); }

        function btn(icon, label, title) {
            var b = el('button', 'mcv-btn'); b.type = 'button';
            b.addEventListener('click', function () { try { b.blur(); } catch (e) {} }); // 누른 뒤 포커스가 남아 단축키를 가로막지 않게
            if (title) { b.title = title; b.setAttribute('aria-label', title); }
            b.appendChild(document.createTextNode(icon));
            if (label) { var l = el('span', 'mcv-lbl', label); b.appendChild(l); }
            return b;
        }
        var bBack = btn('⏪', T.back5 || '5s', T.back5_help || '-5s');
        var bFwd = btn('⏩', T.fwd5 || '5s', T.fwd5_help || '+5s');
        var sRate = el('select', 'mcv-sel'); sRate.setAttribute('aria-label', T.speed || 'Speed'); sRate.title = T.speed || 'Speed';
        RATES.forEach(function (r) { var op = el('option', null, r + 'x'); op.value = String(r); sRate.appendChild(op); });
        var bLoop = btn('🔁', T.loop || 'Loop', T.loop || 'Loop');
        var bAb = btn('↔', 'A-B', T.ab_set_a || 'A-B');
        var abLbl = el('span', 'mcv-ab-lbl');
        var bAuto = btn('⏭', T.autonext || 'Auto next', T.autonext || 'Auto next');
        var bList = null;
        bar.appendChild(bBack); bar.appendChild(bFwd); bar.appendChild(sRate); bar.appendChild(bLoop); bar.appendChild(bAb); bar.appendChild(abLbl);
        bar.appendChild(el('span', 'mcv-sp'));
        var list = Array.isArray(o.list) ? o.list : [];
        var hasNext = list.length > 1 || typeof o.onNext === 'function';
        if (hasNext) bar.appendChild(bAuto);

        var skinOn = false, wrap = video.closest('.video-container');

        // ── 토스트(키보드·버튼 피드백)
        var toast = el('div', 'mcv-toast'), toastT = 0;
        if (getComputedStyle(anchor).position === 'static') anchor.style.position = 'relative';
        anchor.appendChild(toast);
        function say(t, ms) {
            // FileStation 플레이어가 붙어 있으면 그 알림 자리(위쪽 가운데)에 — 가운데 재생 버튼을 가리지 않게
            if (skinOn && window.FSVideoSkin && typeof window.FSVideoSkin.showMessage === 'function') { window.FSVideoSkin.showMessage(wrap, t, { ms: ms || 700 }); return; }
            toast.textContent = t; toast.classList.add('show'); clearTimeout(toastT); toastT = setTimeout(function () { toast.classList.remove('show'); }, ms || 700);
        }

        // ── 재생 위치(실시간 변환 재생이면 브라우저 시각 0 = 실제 tcOff 초)
        var tc = (o.tc && o.tc.url) ? o.tc : null;
        var mode = (o.native ? 'native' : (tc ? 'tc' : 'native'));
        var tcOff = 0, tcAudio = 0, tcQ = 0, tcStarted = false;
        if (tc) {
            var _ad = 0; (tc.audio || []).forEach(function (a, i) { if (a && a.def && !_ad) _ad = i; }); tcAudio = _ad;
            video._knownDuration = +tc.dur || 0;
        }
        var tcPend = null, tcPendT = 0, tcPendPlay = false;   // 변환 위치 이동 — 키를 이어 누르면 모아서 한 번만 다시 변환
        function isTc() { return mode === 'tc'; }
        function absNow() { if (tcPend !== null) return tcPend; return (isTc() ? tcOff : 0) + (video.currentTime || 0); }
        function absDur() {
            if (isTc()) return (tc && +tc.dur) || 0;
            var d = video.duration; return (isFinite(d) && d > 0) ? d : 0;
        }
        function seekEnd() {   // 변환 스트림은 범위 요청이 없어 받아 둔 곳으로도 못 감 — seekable 만(대개 0 → 다시 변환)
            var m = 0;
            try { var r = video.seekable; for (var i = 0; i < r.length; i++) m = Math.max(m, r.end(i)); } catch (e) {}
            return m;
        }
        function tcSeekLater(T2, play) {
            tcPend = T2; tcPendPlay = tcPendPlay || play;
            clearTimeout(tcPendT);
            tcPendT = setTimeout(function () { var t = tcPend, pl = tcPendPlay; tcPend = null; tcPendPlay = false; if (t !== null) startTc(t, pl); }, 400);
        }
        function seekAbs(T2) {
            var d = absDur();
            if (d > 0) T2 = Math.min(T2, d - 0.05);
            T2 = Math.max(0, T2);
            if (!isTc()) { video.currentTime = T2; return; }
            var end = seekEnd();
            if (tcPend === null && T2 >= tcOff && T2 <= tcOff + Math.max(0, end - 0.5)) video.currentTime = T2 - tcOff;
            else tcSeekLater(T2, !video.paused);
        }

        // ── 이동
        function seek(d, quiet) {
            seekAbs(absNow() + d);
            if (!quiet && !skinOn) say((d > 0 ? '+' : '') + d + 's');
        }
        bBack.addEventListener('click', function () { seek(-5); });
        bFwd.addEventListener('click', function () { seek(5); });

        // ── 속도
        function setRate(r, quiet) {
            r = parseFloat(r); if (RATES.indexOf(r) < 0) r = 1;
            video.playbackRate = r; video.defaultPlaybackRate = r; sRate.value = String(r);
            lsSet('mc_v_rate', String(r));
            // FileStation 플레이어도 속도를 따로 기억(fs_video_prefs.r)하고 붙을 때 그 값을 씀 — 두 기억을 같게
            try { var fp = JSON.parse(localStorage.getItem('fs_video_prefs') || 'null'); if (!fp || typeof fp !== 'object') fp = {}; if (fp.r !== r) { fp.r = r; localStorage.setItem('fs_video_prefs', JSON.stringify(fp)); } } catch (e) {}
            if (!quiet) say((T.speed || 'Speed') + ' ' + r + 'x');
        }
        sRate.addEventListener('change', function () { setRate(sRate.value); try { sRate.blur(); } catch (e) {} });
        setRate(lsGet('mc_v_rate', '1'), true);
        video.addEventListener('loadedmetadata', function () { var r = parseFloat(sRate.value) || 1; if (video.playbackRate !== r) video.playbackRate = r; });

        // ── 반복
        function setLoop(on) { video.loop = !!on; bLoop.classList.toggle('on', !!on); bLoop.setAttribute('aria-pressed', on ? 'true' : 'false'); lsSet('mc_v_loop', on ? '1' : '0'); }
        bLoop.addEventListener('click', function () { setLoop(!video.loop); say(video.loop ? (T.loop_on || 'Loop on') : (T.loop_off || 'Loop off')); });
        setLoop(lsGet('mc_v_loop', '0') === '1');

        // ── A-B (영상마다 새로 — 다른 영상을 열면 해제 · 변환 재생 중엔 숨김: FileStation 과 같음)
        var A = null, B = null;
        function abFinite() { if (isTc()) return false; var d = video.duration; return isFinite(d) && d > 0; }
        function abUI() {
            bAb.classList.toggle('on', A !== null && B !== null);
            bAb.classList.toggle('half', A !== null && B === null);
            bAb.title = A === null ? (T.ab_set_a || 'Set A') : (B === null ? (T.ab_set_b || 'Set B') : (T.ab_clear || 'Clear A-B'));
            bAb.setAttribute('aria-label', bAb.title);
            abLbl.textContent = A === null ? '' : (fmtT(A) + ' ~ ' + (B === null ? '' : fmtT(B)));
            var show = abFinite();
            bAb.style.display = show ? '' : 'none'; abLbl.style.display = show ? '' : 'none';
        }
        function abClear() { A = null; B = null; abUI(); }
        function abClick(quiet) {
            var t = video.currentTime || 0;
            if (A === null) { A = t; }
            else if (B === null) { if (t <= A + 0.3) A = t; else B = t; } // 앞이나 0.3초 안을 누르면 A 를 다시 지정
            else { A = null; B = null; }
            abUI();
            if (!quiet) say(A === null ? (T.ab_off || 'A-B off') : (B === null ? 'A ' + fmtT(A) : fmtT(A) + ' ~ ' + fmtT(B)));
        }
        bAb.addEventListener('click', function () { abClick(false); });
        video.addEventListener('timeupdate', function () { if (A !== null && B !== null && video.currentTime >= B) video.currentTime = A; });
        video.addEventListener('loadedmetadata', abUI);
        video.addEventListener('durationchange', abUI);
        video.addEventListener('emptied', function () { if (!switching) abClear(); });   // 다른 영상으로 바뀜(변환 위치 이동·화질 변경은 같은 영상)
        abUI();

        // ── 이전·다음·자동 다음
        function curIdx() { for (var i = 0; i < list.length; i++) if (list[i].current) return i; return -1; }
        function playableFrom(start, step) { for (var j = start; j >= 0 && j < list.length; j += step) if (list[j].playable !== false || o.tcAll) return j; return -1; }
        function goIdx(i) {
            if (i < 0) return;
            if (typeof o.onPick === 'function') { o.onPick(i); return; }
            // ★ 다음 영상 페이지에서 바로 재생 · ★ (2026-10-31) replace — 영상을 넘길 때마다 방문 기록이 쌓여 뒤로 가기를 여러 번 해야 목록으로 가던 것(만화 뷰어의 이전·다음과 같게)
            if (list[i] && list[i].url) location.replace(list[i].url + (o.nextParam || ''));
        }
        function setAuto(on) { bAuto.classList.toggle('on', !!on); bAuto.setAttribute('aria-pressed', on ? 'true' : 'false'); lsSet('mc_v_autonext', on ? '1' : '0'); }
        var autoOn = lsGet('mc_v_autonext', '1') === '1';
        bAuto.addEventListener('click', function () { autoOn = !autoOn; setAuto(autoOn); say(autoOn ? (T.autonext_on || 'Auto next on') : (T.autonext_off || 'Auto next off')); });
        setAuto(autoOn);
        api.shouldAutoNext = function () { return hasNext && autoOn && !video.loop && !(A !== null && B !== null); };
        // ★ 변환 재생이 영상 끝 전에 끊김(ffmpeg 오류·서버 재시작 등) — 브라우저는 '끝남'으로 알리지만 다음 영상으로 넘기거나 이어보기를 지우면 안 됨
        function tcCutShort() { var d = (tc && +tc.dur) || 0; return isTc() && d > 0 && absNow() < d - 3; }
        video.addEventListener('ended', function () {
            if (tcCutShort()) { setBadgeErr(T.tc_error || 'Transcoding failed'); save(true); return; }
            if (o.manageEnded === false || !api.shouldAutoNext()) return; // 압축 파일 속 영상은 페이지가 직접 처리(manageEnded:false)
            if (typeof o.onNext === 'function') { o.onNext(); return; }
            goIdx(playableFrom(curIdx() + 1, 1));
        });

        // ── 재생 목록
        var listBox = null;
        function renderList() {
            if (!listBox) return;
            var body = listBox.querySelector('.mcv-list-body');
            body.innerHTML = '';
            list.forEach(function (it, i) {
                var a = el(it.url ? 'a' : 'div', 'mcv-item' + (it.current ? ' cur' : '') + (it.playable === false ? ' no' : ''));
                if (it.url) a.href = it.url;
                a.appendChild(el('span', 'mcv-no', String(i + 1)));
                a.appendChild(el('span', null, (it.playable === false ? '⚠ ' : '') + it.title));
                if (typeof o.onPick === 'function') a.addEventListener('click', function (ev) { ev.preventDefault(); o.onPick(i); });
                else if (it.url) a.addEventListener('click', function (ev) {   // ★ (2026-10-31) 목록에서 고른 영상도 방문 기록을 쌓지 않음(새 탭·Ctrl·가운데 클릭은 그대로)
                    if (ev.button !== 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) return;
                    // 페이지 공통 링크 처리(bootstrap.php 페이지 전환 — location.href 로 이동)까지 가지 않게 여기서 멈춤
                    ev.preventDefault(); ev.stopPropagation();
                    try { if (typeof window.myComixMarkNavigation === 'function') window.myComixMarkNavigation(); } catch (x) {}
                    try { document.documentElement.classList.add('leaving'); } catch (x) {}
                    location.replace(it.url);
                });
                body.appendChild(a);
            });
            var c = body.querySelector('.mcv-item.cur');
            if (c) body.scrollTop = Math.max(0, c.offsetTop - body.offsetTop - body.clientHeight / 2 + c.offsetHeight / 2);
        }
        if (list.length > 1 && o.showList !== false) {
            bList = btn('🎬', T.list || 'Folder videos', T.list_title || T.list || 'Folder videos');   // ★ (2026-10-31) ☰ 는 아래 '목록으로' 버튼과 같아 헷갈림 → 압축 동영상 목록과 같은 🎬
            bar.appendChild(bList);
            listBox = el('div', 'mcv-list');
            var head = el('div', 'mcv-list-head', (T.list_title || 'Videos') + ' (' + list.length + ')');
            listBox.appendChild(head);
            listBox.appendChild(el('div', 'mcv-list-body'));
            bar.parentNode.insertBefore(listBox, bar.nextSibling);
            var open = lsGet('mc_v_list', '1') === '1';
            var setList = function (on) { listBox.style.display = on ? '' : 'none'; bList.classList.toggle('on', on); bList.setAttribute('aria-pressed', on ? 'true' : 'false'); lsSet('mc_v_list', on ? '1' : '0'); if (on) renderList(); };
            bList.addEventListener('click', function () { open = !open; setList(open); });
            setList(open);
        }
        api.setCurrent = function (idx) { list.forEach(function (it, i) { it.current = i === idx; }); renderList(); };

        // ── 이어보기(실시간 변환 재생은 실제 시각으로 기억)
        var key = o.key ? 'mc_vres:' + o.key : '', lastSave = 0;
        var played = false;   // 이 페이지에서 실제로 재생했을 때만 기억·지우기(열기만 하고 나가면 지난 위치를 그대로 둠)
        video.addEventListener('playing', function () { played = true; });
        function save(force) {
            if (!key || !played) return;
            var now = Date.now();
            if (!force && now - lastSave < 5000) return;
            lastSave = now;
            var t = absNow(), d = absDur();
            if (!(d > 0)) return;                             // 길이를 모르면 기억하지 않음
            if (t < 10 || t > d - 10) { lsDel(key); return; } // 처음 10초·끝나기 10초 전은 기억 안 함(다 본 영상은 지움)
            lsSet(key, JSON.stringify({ t: Math.floor(t), d: Math.floor(d), at: Math.floor(now / 1000) }));
        }
        function readResume() { if (!key) return null; var r = null; try { r = JSON.parse(lsGet(key, 'null')); } catch (e) { r = null; } return (r && r.t > 10) ? r : null; }
        function checkResume() {   // 종전 방식(스킨이 없을 때) — 아래 '이어보기' 단추
            var r = readResume(); if (!r) return;
            resume.innerHTML = '';
            resume.appendChild(el('span', null, (T.resume_q || 'Continue from') + ' ' + fmtT(r.t)));
            var go = el('button', 'mcv-btn on', '▶ ' + (T.resume || 'Resume')); go.type = 'button';
            var no = el('button', 'mcv-btn', T.resume_no || 'From start'); no.type = 'button';
            go.addEventListener('click', function () { seekAbs(r.t); resume.classList.remove('show'); var p = video.play(); if (p && p.catch) p.catch(function () {}); });
            no.addEventListener('click', function () { lsDel(key); resume.classList.remove('show'); });
            resume.appendChild(go); resume.appendChild(no);
            resume.classList.add('show');
        }
        video.addEventListener('timeupdate', function () { save(false); });
        video.addEventListener('pause', function () { save(true); });
        video.addEventListener('ended', function () { if (tcCutShort()) return; if (key) lsDel(key); resume.classList.remove('show'); });
        video.addEventListener('playing', function () { if (absNow() > 3) resume.classList.remove('show'); });
        window.addEventListener('pagehide', function () { save(true); });

        // ── 전체화면·PIP
        function fsToggle() {
            try {
                var fe = document.fullscreenElement || document.webkitFullscreenElement;
                if (fe) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
                var tgt = (skinOn && wrap) ? wrap : video;
                if (tgt.requestFullscreen) tgt.requestFullscreen();
                else if (tgt.webkitRequestFullscreen) tgt.webkitRequestFullscreen();
                else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen(); // 아이폰 — 기기 기본 전체화면
            } catch (er) {}
        }

        // ── 재생 시작(자동 재생이 막히면 소리를 끈 채 재생 + '소리 켜기' 알림 — FileStation 이어 보기 알림과 같은 상자)
        function vpToast(text, btnText, onBtn, ms) {
            if (!wrap) { say(text, ms || 3000); return; }
            var old = wrap.querySelector('.fs-vp-toast'); if (old) old.remove();
            var t = el('div', 'fs-vp-toast'); var sp = el('span', null, text); t.appendChild(sp);
            if (btnText) {
                var b = el('button', null, btnText); b.type = 'button';
                b.addEventListener('click', function (e) { e.stopPropagation(); try { onBtn(); } catch (x) {} t.remove(); });
                t.appendChild(b);
            }
            t.addEventListener('click', function (e) { e.stopPropagation(); });
            wrap.appendChild(t);
            setTimeout(function () { try { t.remove(); } catch (x) {} }, ms || 6000);
        }
        function playNow(fromAuto) {
            var p;
            try { p = video.play(); } catch (e) { p = null; }
            if (p && p.catch) p.catch(function (err) {
                if (!fromAuto || !err || err.name !== 'NotAllowedError') return;
                // 브라우저가 소리 있는 자동 재생을 막음 → 소리를 끄고 재생
                video.muted = true;
                var p2 = video.play();
                if (p2 && p2.then) p2.then(function () {
                    vpToast(T.autoplay_muted || 'Playing muted', T.unmute || 'Unmute', function () { video.muted = false; try { if (window.FSVideoSkin) window.FSVideoSkin.showVolume(wrap); } catch (x) {} }, 8000);
                }).catch(function () {});
            });
        }

        // ── 실시간 변환 재생
        var switching = false;
        function tcUrl(ss) { return tc.url + '&ss=' + (Math.round(ss * 1000) / 1000) + '&a=' + tcAudio + '&q=' + tcQ; }
        function setBusy(on) { if (wrap) wrap.classList.toggle('mcv-busy', !!on); }
        function startTc(ss, play, lazy) {
            if (!tc) return;
            clearTimeout(tcPendT); tcPend = null; tcPendPlay = false;
            var wasMode = mode;
            mode = 'tc'; tcStarted = true;
            tcOff = Math.max(0, Math.min(+ss || 0, Math.max(0, ((+tc.dur) || 0) - 1)));
            video._qualitySeekOffset = tcOff;
            switching = true;
            setBusy(!(lazy && !play));
            try { video.removeAttribute('src'); while (video.firstChild && video.firstChild.tagName === 'SOURCE') video.removeChild(video.firstChild); } catch (e) {}
            video.preload = (lazy && !play) ? 'none' : 'auto';   // lazy: 재생을 누를 때 변환 시작(미리 ffmpeg 를 돌리지 않음)
            video.src = tcUrl(tcOff);
            if (!(lazy && !play)) video.load();   // src 를 바꾸면 다시 읽음 — 미룰 때는 load() 를 부르지 않음(크롬은 load() 때 preload=none 을 무시하기도)
            if (wasMode !== 'tc') { abClear(); updateBadge(); }
            if (play) playNow(true);
        }
        function startNative(ss, play) {
            if (!o.native) return;
            mode = 'native'; tcOff = 0; video._qualitySeekOffset = 0;
            switching = true;
            video.src = o.native;
            video.load();
            var go = function () { video.removeEventListener('loadedmetadata', go); try { if (ss > 0) video.currentTime = ss; } catch (e) {} if (play) playNow(true); };
            video.addEventListener('loadedmetadata', go);
            updateBadge();
        }
        video.addEventListener('loadstart', function () { switching = false; });
        var playReq = !!o.autoplay;   // 재생하려던 중인지(형식 오류로 변환 재생으로 넘길 때 이어서 재생할지)
        video.addEventListener('play', function () { playReq = true; });
        video.addEventListener('pause', function () { playReq = false; });   // 바꾼 주소를 읽기 시작(그 앞의 emptied 까지는 같은 영상)
        video.addEventListener('play', function () { if (isTc() && video.readyState < 3) setBusy(true); });   // 변환 준비 중 표시
        video.addEventListener('playing', function () { setBusy(false); });
        video.addEventListener('canplay', function () { setBusy(false); });
        video.addEventListener('error', function (ev) {
            // <source> 로 연 영상은 오류가 <source> 에 남(video.error 는 비어 있음 — 잡으려고 capture) → 마지막 <source> 실패면 형식 오류와 같게
            var srcFail = ev && ev.target && ev.target !== video && ev.target.tagName === 'SOURCE';
            if (ev && ev.target !== video && !srcFail) return;
            if (srcFail && ev.target.nextElementSibling && ev.target.nextElementSibling.tagName === 'SOURCE') return;
            setBusy(false);
            var err = video.error, code = err ? err.code : (srcFail ? 4 : 0);
            if (!isTc() && tc && (code === 3 || code === 4)) {
                // 일반 재생이 형식·코덱 문제로 실패 → 같은 위치부터 변환 재생(FileStation 과 같음)
                var at = absNow();
                say(T.tc_fallback || 'Switching to transcoding', 2500);
                startTc(at, playReq, true);
                return;
            }
            if (isTc()) setBadgeErr(T.tc_error || 'Transcoding failed');
        }, true);

        // ★ 크롬 등이 영상 코덱(HEVC 등)을 못 열면 오류 없이 소리만 나옴(화면 0x0) → 같은 위치부터 변환 재생(서버가 영상 트랙이 있다고 본 파일만)
        video.addEventListener('loadeddata', function () {
            if (isTc() || !tc || !(+tc.h > 0) || video.videoWidth > 0) return;
            say(T.tc_fallback || 'Switching to transcoding', 2500);
            startTc(absNow(), playReq, true);
        });

        // 화질·재생 방식·음성 선택 — 화면에 붙이지 않은 <select> 를 스킨 ⚙ 설정이 읽고 바꿈(FileStation 과 같은 연결)
        var selMode = null, selQ = null, selAudio = null;
        if (tc) {
            if (o.native) {
                selMode = el('select', 'playback-mode-select'); selMode.id = 'mcv-playback-mode';
                [['native', T.mode_native || 'Direct play'], ['tc', T.mode_tc || 'Transcoding']].forEach(function (x) { var op = el('option', null, x[1]); op.value = x[0]; selMode.appendChild(op); });
                selMode.addEventListener('change', function () {
                    var at = absNow(), pl = !video.paused;
                    if (selMode.value === 'tc' && !isTc()) startTc(at, pl);
                    else if (selMode.value === 'native' && isTc()) startNative(at, pl);
                });
            }
            selQ = el('select'); selQ.id = 'mcv-quality';
            var qh = +tc.h || 0;
            [[0, T.q_orig || 'Original'], [1080, '1080p'], [720, '720p'], [480, '480p']].forEach(function (x) {
                if (x[0] && qh && x[0] >= qh) return;   // 원본보다 큰 화질은 뺌
                var op = el('option', null, x[0] ? x[1] : (x[1] + (qh ? ' (' + qh + 'p)' : ''))); op.value = String(x[0]); selQ.appendChild(op);
            });
            selQ.addEventListener('change', function () { tcQ = parseInt(selQ.value, 10) || 0; if (isTc()) startTc(absNow(), !video.paused); });
            if ((tc.audio || []).length >= 2) {
                selAudio = el('select', 'audio-track-select'); selAudio.id = 'audio-track-picker';
                tc.audio.forEach(function (a, i) { var op = el('option', null, a.label || ('Track ' + (i + 1))); op.value = String(i); selAudio.appendChild(op); });
                selAudio.value = String(tcAudio);
                selAudio.addEventListener('change', function () {
                    var n = parseInt(selAudio.value, 10) || 0;
                    if (n === tcAudio && isTc()) return;
                    tcAudio = n;
                    // 일반 재생은 첫 음성만 나오므로 다른 음성을 고르면 변환 재생으로(FileStation 과 같음)
                    if (isTc() || n !== 0 || !o.native) startTc(absNow(), !video.paused);
                });
            }
        }
        function syncSelects() {
            if (selMode) selMode.value = isTc() ? 'tc' : 'native';
            if (selAudio) selAudio.value = String(isTc() ? tcAudio : 0);
        }

        // ── 배지
        var badge = null;
        function updateBadge() {
            if (!badge) return;
            badge.classList.remove('err', 'native', 'tc');
            if (isTc()) { badge.classList.add('tc'); badge.textContent = '⚡ ' + ((o.badge && o.badge.src) ? o.badge.src + ' → ' : '') + (T.badge_tc || 'Transcoding'); }
            else { badge.classList.add('native'); badge.textContent = (T.badge_native || 'Direct play') + ((o.badge && o.badge.src) ? ' · ' + o.badge.src : ''); }
            syncSelects();
        }
        function setBadgeErr(msg) { if (!badge) return; badge.classList.remove('native', 'tc'); badge.classList.add('err'); badge.textContent = '❌ ' + msg; }

        // ── 자막
        var subs = [], subIdx = 0, subCues = [], subOn = lsGet('mc_v_sub', '1') !== '0', subSync = 0, subBox = null, subOv = null;
        var subSize = (function () { var n = parseFloat(lsGet('subSize', '1.1')); return isFinite(n) ? Math.min(2.5, Math.max(0.6, n)) : 1.1; })();
        var subBottom = (function () { var n = parseFloat(lsGet('subBottom', '8')); return isFinite(n) ? Math.min(40, Math.max(0, n)) : 8; })();
        function subFit() {
            if (!subBox || !wrap) return;
            var w = wrap.clientWidth || 640;
            subBox.style.fontSize = Math.max(13, Math.min(44, w * 0.026)) + 'px';
        }
        function subApply() { if (subOv) { subOv.style.fontSize = subSize + 'em'; subOv.style.bottom = subBottom + '%'; } }
        function subRender() {
            if (!subOv) return;
            if (!subOn || !subCues.length || (!video.videoWidth && video.readyState < 2)) { if (subOv.innerHTML !== '') subOv.innerHTML = ''; return; }
            var t = absNow() + subSync, found = '';
            for (var i = 0; i < subCues.length; i++) { var c = subCues[i]; if (t >= c.start && t <= c.end) { found = c.text; break; } }
            if (subOv.innerHTML !== found) subOv.innerHTML = found;   // 파서가 이스케이프한 글자 + <br> 만
        }
        function subLoad(i, announce) {
            subIdx = i; subCues = [];
            var s = subs[i];
            if (s) { try { subCues = parseSubtitle(s.content || '', String(s.ext || '').toLowerCase()); } catch (e) { subCues = []; } }
            subRender();
            if (announce && s && skinOn && window.FSVideoSkin) window.FSVideoSkin.showMessage(wrap, (T.sub_file || 'Subtitles') + ': ' + s.name, { icon: 'cc', off: !subOn, ms: 1500 });
        }
        api.setSubs = function (arr) {
            subs = Array.isArray(arr) ? arr.filter(function (x) { return x && typeof x.content === 'string'; }) : [];
            subSync = 0;
            subLoad(0, subs.length > 0);
        };
        function subToggle() {
            if (!subs.length) return;
            subOn = !subOn; lsSet('mc_v_sub', subOn ? '1' : '0'); subRender();
            if (skinOn && window.FSVideoSkin) window.FSVideoSkin.showMessage(wrap, subOn ? (T.sub_on || 'Subtitles on') : (T.sub_off || 'Subtitles off'), { icon: 'cc', off: !subOn, ms: 1000 });
        }
        video.addEventListener('timeupdate', subRender);
        video.addEventListener('seeked', subRender);

        // ── 동영상 정보 창(ⓘ · I 키)
        var infoOv = null;
        function infoClose() { if (infoOv) { infoOv.remove(); infoOv = null; } }
        function infoShow() {
            if (infoOv) { infoClose(); return; }
            var rows = (o.info || []).slice();
            rows.push([T.info_mode || 'Playback', isTc() ? (T.badge_tc || 'Transcoding') + (tcQ ? ' · ' + tcQ + 'p' : '') + ((tc && (tc.audio || []).length > 1) ? ' · ' + ((tc.audio[tcAudio] || {}).label || '') : '') : (T.badge_native || 'Direct play')]);
            if (subs.length) rows.push([T.sub_file || 'Subtitles', subs.map(function (s, i) { return (i === subIdx ? '● ' : '') + s.name; }).join('\n')]);
            infoOv = el('div', 'mcv-info-ov');
            var box = el('div', 'mcv-info'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
            var h = el('div', 'mcv-info-h'); h.appendChild(el('span', null, T.info_title || 'Video info'));
            var x = el('button', 'mcv-info-x', '×'); x.type = 'button'; x.setAttribute('aria-label', T.close || 'Close');
            x.addEventListener('click', infoClose); h.appendChild(x); box.appendChild(h);
            var tb = el('table');
            rows.forEach(function (r) {
                if (!r || r[1] === '' || r[1] == null) return;
                var tr = el('tr'); tr.appendChild(el('td', null, String(r[0])));
                var td = el('td'); String(r[1]).split('\n').forEach(function (line, k) { if (k) td.appendChild(el('br')); td.appendChild(document.createTextNode(line)); });
                tr.appendChild(td); tb.appendChild(tr);
            });
            box.appendChild(tb); infoOv.appendChild(box);
            infoOv.addEventListener('click', function (e) { if (e.target === infoOv) infoClose(); });
            (document.fullscreenElement || document.webkitFullscreenElement || document.body).appendChild(infoOv);
            try { x.focus(); } catch (e) {}
        }
        api.showInfo = infoShow;

        // ── 이동 표시(‹ −5 / +5 › — 이어 누르면 −10, −15 … 누적, FileStation fsSeekFeedback 과 같음)
        var skL = null, skR = null;
        function seekFeedback(back) {
            if (!skL || !skR) return;
            var e2 = back ? skL : skR, other = back ? skR : skL;
            if (other._st) { other._st = null; other.classList.remove('show', 'again'); }
            var now = performance.now(), st = e2._st, cont = !!(st && now < st.end);
            var sum = (cont ? st.sum : 0) + 5;
            e2._st = { sum: sum, end: now + 1200 };
            e2.textContent = (back ? '−' : '+') + sum;
            e2.classList.remove('show', 'again');
            void e2.offsetWidth;
            e2.classList.add('show');
            if (cont) e2.classList.add('again');
        }

        // ── FileStation 동영상 플레이어(js/fs-video-skin.js) 붙이기
        var lastPtr = 'mouse', ptrVisible = false, ptrEl = null;
        if (wrap && window.FSVideoSkin && typeof window.FSVideoSkin.attach === 'function' && o.skin !== false) {
            try {
                wrap.classList.add('mcv-wrap');
                // 가운데 재생·±5초 버튼(FileStation 과 같은 모양)
                var cPlay = document.createElement('button');
                cPlay.type = 'button'; cPlay.className = 'video-play-overlay mcv-cplay';
                cPlay.title = T.center_play || 'Play / Pause'; cPlay.setAttribute('aria-label', cPlay.title);
                cPlay.innerHTML = '<svg class="mcv-i-play" viewBox="0 0 24 24" width="48" height="48" fill="white" aria-hidden="true"><path d="M8 6.82v10.36a1.5 1.5 0 0 0 2.3 1.27l8.14-5.18a1.5 1.5 0 0 0 0-2.54L10.3 5.55A1.5 1.5 0 0 0 8 6.82z"/></svg>'
                    + '<svg class="mcv-i-pause" viewBox="0 0 24 24" width="48" height="48" fill="white" aria-hidden="true" style="display:none"><rect x="6" y="5" width="4.5" height="14" rx="1.6"/><rect x="13.5" y="5" width="4.5" height="14" rx="1.6"/></svg>';
                var mkSeek = function (cls, d, label, path) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.className = 'video-play-overlay video-seek-btn ' + cls;
                    b.title = label; b.setAttribute('aria-label', label);
                    b.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="white" aria-hidden="true"><path d="' + path + '"/><text x="12" y="16.2" text-anchor="middle" font-size="8.5" font-weight="700" font-family="system-ui,-apple-system,sans-serif">5</text></svg>';
                    b.addEventListener('click', function (e) { e.stopPropagation(); seek(d, true); try { b.blur(); } catch (x) {} });
                    return b;
                };
                var cBack = mkSeek('video-seek-btn-back', -5, T.back5_help || '-5s', 'M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z');
                var cFwd = mkSeek('video-seek-btn-fwd', 5, T.fwd5_help || '+5s', 'M4 13c0 4.42 3.58 8 8 8s8-3.58 8-8h-2c0 3.31-2.69 6-6 6s-6-2.69-6-6 2.69-6 6-6v4l5-5-5-5v4c-4.42 0-8 3.58-8 8z');
                skL = el('div', 'video-seek-overlay video-seek-left'); skR = el('div', 'video-seek-overlay video-seek-right');
                badge = el('div', 'mcv-badge');
                var spin = el('div', 'mcv-spin');
                subBox = el('div', 'mcv-subbox'); subOv = el('div', 'subtitle-overlay'); subBox.appendChild(subOv);
                [subBox, badge, spin, skL, skR, cBack, cPlay, cFwd].forEach(function (n) { wrap.appendChild(n); });
                var togglePlay = function () { if (video.paused || video.ended) playNow(false); else video.pause(); };
                api.togglePlay = togglePlay;
                cPlay.addEventListener('click', function (e) {
                    e.stopPropagation();
                    // 손가락: 숨어 있던 가운데 버튼의 첫 탭은 보이기만(FileStation 과 같음)
                    if (lastPtr !== 'mouse' && wrap.classList.contains('playing') && !ptrVisible) { wrap.classList.add('show-controls'); return; }
                    togglePlay(); try { cPlay.blur(); } catch (x) {}
                });
                var syncC = function () {
                    var playing = !video.paused && !video.ended;
                    wrap.classList.toggle('playing', playing);
                    if (!playing) wrap.classList.remove('show-controls');
                    cPlay.querySelector('.mcv-i-play').style.display = playing ? 'none' : '';
                    cPlay.querySelector('.mcv-i-pause').style.display = playing ? '' : 'none';
                };
                ['play', 'playing', 'pause', 'ended', 'emptied', 'loadstart'].forEach(function (ev) { video.addEventListener(ev, syncC); });
                // 영상 빈 곳: 마우스는 누르면 재생/일시정지·두 번 누르면 전체화면, 손가락은 누르면 조작부 보이기/숨기기(FileStation 과 같음)
                wrap.addEventListener('pointerdown', function (e) {
                    lastPtr = e.pointerType || 'mouse';
                    ptrVisible = wrap.classList.contains('show-controls') && !wrap.classList.contains('fsvs-idle');
                }, { capture: true, passive: true });
                video.addEventListener('click', function (e) {
                    if (!skinOn) return;
                    if (lastPtr === 'mouse') { togglePlay(); return; }
                    if (!wrap.classList.contains('playing')) return;
                    if (ptrVisible) { wrap.classList.remove('show-controls'); try { wrap.dispatchEvent(new CustomEvent('fsvs-hidenow', { detail: { evt: e } })); } catch (x) {} }
                    else wrap.classList.add('show-controls');
                });
                video.addEventListener('dblclick', function () { if (skinOn && lastPtr === 'mouse') fsToggle(); });
                wrap.addEventListener('fsvs-idlechange', function (ev) {
                    if (!ev.detail) return;
                    if (ev.detail.idle) wrap.classList.remove('show-controls');   // 숨을 때만 따라감(보이기는 탭 처리가 정함)
                });
                var ctx = {
                    setSpeed: function (v) { setRate(v, true); },
                    toggleLoop: function () { setLoop(!video.loop); },
                    isLoop: function () { return !!video.loop; },
                    abVisible: function () { return abFinite(); },
                    clickAb: function () { abClick(true); },
                    abState: function () { return { a: A, b: B }; },
                    hasPip: function () { return !!(document.pictureInPictureEnabled && !video.disablePictureInPicture && video.requestPictureInPicture); },
                    togglePip: function () {
                        try {
                            if (document.pictureInPictureElement) { document.exitPictureInPicture(); return; }
                            var p = video.requestPictureInPicture(); if (p && p.catch) p.catch(function () {});
                        } catch (er) {}
                    },
                    toggleFs: fsToggle,
                    menuRoomAbove: function () { return 0; },   // 플레이어 틀이 넘침을 잘라내므로(.video-container overflow:hidden) 틀 안에서만
                    hasList: function () { return list.length > 1; },
                    canPrev: function () { return playableFrom(curIdx() - 1, -1) >= 0; },
                    canNext: function () { return playableFrom(curIdx() + 1, 1) >= 0; },
                    goPrev: function () { goIdx(playableFrom(curIdx() - 1, -1)); },
                    goNext: function () { goIdx(playableFrom(curIdx() + 1, 1)); },
                    // 자막
                    hasCc: function () { return subs.length > 0; },
                    toggleCc: subToggle,
                    ccOn: function () { return subOn; },
                    subSync: function () { return subSync; },
                    subAct: function (name) {
                        if (name === 'size-down') subSize = Math.max(0.6, Math.round((subSize - 0.1) * 10) / 10);
                        else if (name === 'size-up') subSize = Math.min(2.5, Math.round((subSize + 0.1) * 10) / 10);
                        else if (name === 'pos-up') subBottom = Math.min(40, subBottom + 2);
                        else if (name === 'pos-down') subBottom = Math.max(0, subBottom - 2);
                        else if (name === 'sync-down') subSync = Math.max(-30, Math.round((subSync - 0.5) * 10) / 10);
                        else if (name === 'sync-up') subSync = Math.min(30, Math.round((subSync + 0.5) * 10) / 10);
                        else if (name === 'sync-reset') subSync = 0;
                        lsSet('subSize', String(subSize)); lsSet('subBottom', String(subBottom));
                        subApply(); subRender();
                    },
                    subTracks: function () {
                        if (subs.length < 2) return null;
                        return { tracks: subs.map(function (s, i) { return { label: s.name, on: i === subIdx }; }), select: function (i) { subLoad(i, true); } };
                    },
                    // 실시간 변환 재생
                    isStreaming: function () { return isTc(); },
                    timeOffset: function () { return isTc() ? tcOff : 0; },
                    knownDuration: function () { return isTc() ? ((tc && +tc.dur) || 0) : 0; },
                    streamSeek: function (T2) { if (isTc()) tcSeekLater(Math.max(0, Math.min(T2, absDur() > 0 ? absDur() - 0.05 : T2)), !video.paused); },
                    selects: function () {
                        var r = [];
                        if (selMode) r.push(selMode);
                        if (isTc() && selQ && selQ.options.length > 1) r.push(selQ);
                        if (selAudio && !(IOS && !isTc() && video.audioTracks && video.audioTracks.length >= 2)) r.push(selAudio);
                        return r;
                    },
                    // 사파리(맥·아이폰)는 일반 재생에서도 음성 트랙을 바로 바꿀 수 있음
                    nativeAudio: function () {
                        var at = video.audioTracks;
                        if (isTc() || !at || at.length < 2) return null;
                        var tracks = [];
                        for (var i = 0; i < at.length; i++) tracks.push({ label: (tc && tc.audio && tc.audio[i] && tc.audio[i].label) || at[i].label || at[i].language || ('Track ' + (i + 1)), on: !!at[i].enabled });
                        return { tracks: tracks, select: function (n) { for (var k = 0; k < at.length; k++) at[k].enabled = (k === n); } };
                    }
                };
                if (o.info) ctx.showInfo = infoShow;   // 동영상 정보(ⓘ) — 정보가 있는 화면만
                if (o.frameUrl) ctx.frameUrl = function (sec) { return o.frameUrl + '&t=' + Math.max(0, Math.floor(sec || 0)); };
                if (window.FSVideoSkin.attach({ video: video, wrap: wrap, ctx: ctx })) {
                    skinOn = true;
                    wrap.classList.add('mcv-skin');
                    // 아래 버튼 줄에서 조작 줄과 겹치는 것(±5초·속도·반복·A-B)은 숨김 — 자동 다음·목록은 그대로
                    [bBack, bFwd, sRate, bLoop, bAb, abLbl].forEach(function (n) { n.classList.add('mcv-dup'); });
                    syncC(); updateBadge(); subApply(); subFit();
                    window.addEventListener('resize', subFit);
                    document.addEventListener('fullscreenchange', function () { setTimeout(subFit, 50); });
                    document.addEventListener('webkitfullscreenchange', function () { setTimeout(subFit, 50); });
                } else {
                    [subBox, badge, spin, skL, skR, cBack, cPlay, cFwd].forEach(function (n) { try { n.remove(); } catch (x) {} });
                    badge = null; subBox = null; subOv = null; skL = skR = null;
                    wrap.classList.remove('mcv-wrap');
                }
            } catch (er) { skinOn = false; }
        }
        api.skinOn = function () { return skinOn; };

        // ── 처음 재생 준비: 이어보기 · 변환 재생 · 자동 재생
        if (Array.isArray(o.subs) && o.subs.length) api.setSubs(o.subs);
        var res = readResume();
        if (skinOn) {
            if (mode === 'tc' && tc) {
                // 브라우저가 못 여는 형식 — 변환 재생(재생을 누르거나 자동 재생일 때 시작: 미리 변환하지 않게 preload=none)
                tcOff = res ? res.t : 0; video._qualitySeekOffset = tcOff;
                try { video.removeAttribute('src'); while (video.firstChild && video.firstChild.tagName === 'SOURCE') video.removeChild(video.firstChild); } catch (e) {}
                video.preload = 'none';
                video.src = tcUrl(tcOff);
                updateBadge();
                if (res) vpToast((T.resume_from || 'Resumed from') + ' ' + fmtT(res.t), T.resume_no || 'From start', function () { lsDel(key); startTc(0, true); }, 6000);
                if (o.autoplay) { setBusy(true); playNow(true); }
            } else {
                if (res) {
                    var go = function () {
                        video.removeEventListener('loadedmetadata', go);
                        var d = video.duration;
                        if (isFinite(d) && res.t < d - 10) { try { video.currentTime = res.t; } catch (e) {} vpToast((T.resume_from || 'Resumed from') + ' ' + fmtT(res.t), T.resume_no || 'From start', function () { lsDel(key); video.currentTime = 0; }, 6000); }
                    };
                    if (video.readyState >= 1) go(); else video.addEventListener('loadedmetadata', go);
                }
                if (o.autoplay) playNow(true);
            }
        } else {
            checkResume();
            if (o.autoplay) playNow(true);
        }
        var resGo = null;
        api.setKey = function (k) {   // 압축 파일 속 영상마다(그 영상의 이어보기)
            key = k ? 'mc_vres:' + k : ''; played = false; resume.classList.remove('show');
            var r = readResume();
            if (resGo) { video.removeEventListener('loadedmetadata', resGo); resGo = null; }   // ★ 앞 영상의 위치가 아직 안 쓰였으면 버림(빨리 다른 영상을 고르면 그 위치로 뛰던 것)
            if (!skinOn) { checkResume(); return; }
            if (!r) return;
            var go2 = resGo = function () {
                video.removeEventListener('loadedmetadata', go2); resGo = null;
                var d = video.duration;
                if (isFinite(d) && r.t < d - 10) { try { video.currentTime = r.t; } catch (e) {} vpToast((T.resume_from || 'Resumed from') + ' ' + fmtT(r.t), T.resume_no || 'From start', function () { lsDel(key); video.currentTime = 0; }, 6000); }
            };
            if (video.readyState >= 1 && video.currentSrc) go2(); else video.addEventListener('loadedmetadata', go2);
        };

        // ── 단축키(FileStation 과 같음) — 창 전체에서 먼저 받음(같은 키를 쓰는 페이지 스크롤 처리보다 앞서, 처리한 키는 거기까지 안 감)
        document.addEventListener('pointerdown', function (ev) { try { ptrEl = (ev.target && ev.target.closest) ? ev.target.closest('button,a,input,select,[tabindex]') : null; } catch (x) { ptrEl = null; } }, true);
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Tab') ptrEl = null; }, true);
        window.addEventListener('keydown', function (e) {
            if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || e.isComposing) return;
            if (!video.isConnected) return;
            if (infoOv) { if (e.key === 'Escape' || e.code === 'KeyI') { e.preventDefault(); e.stopImmediatePropagation(); infoClose(); } return; }
            var k = e.key, code = e.code;
            var handled = { ' ': 1, 'k': 1, 'K': 1, 'ArrowLeft': 1, 'ArrowRight': 1, 'ArrowUp': 1, 'ArrowDown': 1, 'j': 1, 'J': 1, 'l': 1, 'L': 1, 'm': 1, 'M': 1, 'f': 1, 'F': 1, '<': 1, '>': 1 };
            var isOurs = handled[k] || code === 'KeyI' || code === 'KeyC' || code === 'KeyM' || code === 'KeyF' || code === 'KeyK' || code === 'KeyJ' || code === 'KeyL' || code === 'Space';
            if (!isOurs) return;
            var ae = document.activeElement;
            // 마우스·손가락으로 누른 플레이어 버튼·음량 막대 등에 포커스가 남아 있으면 빼고 단축키로(FileStation 과 같음 — 종전엔 음량 막대를 누른 뒤 모든 키가 안 먹었음)
            if (ae && ae !== document.body && ae === ptrEl && wrap && wrap.contains(ae) && typeof ae.blur === 'function') { try { ae.blur(); } catch (x) {} ae = document.body; }
            var tg = (e.target === ae || !ae) ? (e.target && e.target.tagName) : (ae && ae.tagName);
            if (ae === document.body) tg = 'BODY';
            if (/^(INPUT|SELECT|TEXTAREA)$/.test(tg || '') || (ae && ae.isContentEditable)) return;
            if (tg === 'BUTTON' && (k === ' ' || k === 'Enter')) return; // 키보드로 고른 버튼 위 Space 는 그 버튼 누르기
            if (!skinOn && e.target === video && (k === ' ' || k === 'ArrowLeft' || k === 'ArrowRight')) return; // 기본 조작 줄이 처리
            var done = true;
            if (k === ' ' || code === 'Space' || code === 'KeyK') { if (video.paused || video.ended) playNow(false); else video.pause(); }
            else if (k === 'ArrowLeft' || k === 'ArrowRight') { var back = k === 'ArrowLeft'; seek(back ? -5 : 5, true); if (skinOn) seekFeedback(back); else say((back ? '-' : '+') + '5s'); }
            else if (code === 'KeyJ') { seek(-10); }
            else if (code === 'KeyL') { seek(10); }
            else if (k === 'ArrowUp' || k === 'ArrowDown') {
                if (!IOS) {
                    video.volume = Math.max(0, Math.min(1, Math.round(((+video.volume || 0) + (k === 'ArrowUp' ? 0.05 : -0.05)) * 100) / 100));
                    if (k === 'ArrowUp' && video.muted) video.muted = false;
                    if (skinOn && window.FSVideoSkin) window.FSVideoSkin.showVolume(wrap); else say(Math.round(video.volume * 100) + '%');
                }
            }
            else if (code === 'KeyM') { video.muted = !video.muted; if (skinOn && window.FSVideoSkin) window.FSVideoSkin.showVolume(wrap); else say(video.muted ? '🔇' : '🔊'); }
            else if (code === 'KeyF') { fsToggle(); }
            else if (code === 'KeyI') { if (skinOn && o.info) { if (!e.repeat) infoShow(); } else done = false; }
            else if (code === 'KeyC') { if (subs.length) subToggle(); else done = false; }
            else if (k === '>' || k === '<') { var i = RATES.indexOf(parseFloat(sRate.value)); i = Math.max(0, Math.min(RATES.length - 1, i + (k === '>' ? 1 : -1))); setRate(RATES[i]); }
            else done = false;
            if (done) { e.preventDefault(); e.stopImmediatePropagation(); }
        }, true);
        return api;
    }

    window.MCVideo = { init: init };
})();
