/*!
 * mc-icons.js — ★ (2026-10-30) 화면의 이모지 아이콘을 같은 굵기의 선 아이콘(SVG)으로 바꿔 그림
 *  - 아이콘 모양: Lucide (ISC License, https://lucide.dev)
 *  - 바꾸는 것: 버튼·메뉴·배지·제목·설정 줄 등의 이모지 중 아래 표에 있는 것
 *    (앞뒤가 띄어쓰기·글 처음/끝인 경우만 — 낱말 사이에 붙은 이모지는 그대로)
 *  - 안 바꾸는 것: 파일·작품 이름, 본문(TXT·README·공지), 입력칸·선택칸, 알림창(alert) 글자, 표에 없는 이모지(테마 이름 등)
 *  - 색: 상태(완료 초록·오류/삭제 빨강·주의/별 노랑·안내 파랑)만 색, 나머지는 글자색 그대로.
 *        색 버튼·배지 안에서는 늘 글자색을 따라감
 *  - 소스 글자는 그대로 두고 화면에서만 바꿈(나중에 생기는 메뉴·창도 MutationObserver 로 처리)
 *  - 끄기: 요소에 class="mc-noico" 를 붙이면 그 안은 안 바꿈
 */
(function () {
    'use strict';
    if (window.__mcIcons) return;
    window.__mcIcons = true;

    var P = {
        'house': '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'plus': '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'languages': '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>', // ★ (2026-11-06 08:10) 한영 검색어 연결(🔤)
        'globe': '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'log-out': '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
        'chart': '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'trending': '<path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/>',
        'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'file': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        'monitor': '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
        'laptop': '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/>',
        'window': '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M10 4v4"/><path d="M2 8h20"/><path d="M6 4v4"/>',
        'settings': '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'palette': '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'tag': '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'library': '<path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/>',
        'folder': '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'folder-open': '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>',
        'layout-list': '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><path d="M14 4h7"/><path d="M14 9h7"/><path d="M14 15h7"/><path d="M14 20h7"/>',
        'database': '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
        'search': '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'file-search': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h4"/><path d="M14 2v5h5"/><path d="M14 2l5 5v3"/><circle cx="16.5" cy="16.5" r="3"/><path d="m21 21-2.4-2.4"/>',
        'megaphone': '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user': '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'trash': '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'scroll': '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
        'lock': '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'shield': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'mail': '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'clock': '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'alarm': '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M5 3 2 6"/><path d="m22 6-3-3"/><path d="M6.38 18.7 4 21"/><path d="M17.64 18.67 20 21"/>',
        'calendar': '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'clipboard': '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
        'book-open': '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
        'wrench': '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'moon': '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sun': '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'image': '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'camera': '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
        'film': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h18"/><path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 16.5h4"/>',
        'package': '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'alert': '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'info': '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'help': '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        'circle-check': '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'check': '<path d="M20 6 9 17l-5-5"/>',
        'square-check': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="m9 12 2 2 4-4"/>',
        'square': '<rect width="18" height="18" x="3" y="3" rx="2"/>',
        'circle-x': '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'x': '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'ban': '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
        'refresh': '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'repeat': '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
        'save': '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
        'zap': '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'bulb': '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
        'sparkles': '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>',
        'download': '<path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>',
        'upload': '<path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
        'arrow-up': '<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>',
        'arrow-down': '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'arrow-left': '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrows-lr': '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'chevron-left': '<path d="m15 18-6-6 6-6"/>',
        'chevron-right': '<path d="m9 18 6-6-6-6"/>',
        'chevrons-down': '<path d="m7 6 5 5 5-5"/><path d="m7 13 5 5 5-5"/>',
        'star': '<path fill="currentColor" d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
        'star-o': '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
        'microscope': '<path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/>',
        'memory': '<path d="M6 19v-3"/><path d="M10 19v-3"/><path d="M14 19v-3"/><path d="M18 19v-3"/><path d="M8 11V9"/><path d="M16 11V9"/><path d="M12 11V9"/><path d="M2 15h20"/><path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v1.1a2 2 0 0 0 0 3.837V17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5.1a2 2 0 0 0 0-3.837Z"/>',
        'network': '<rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/>',
        'code': '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'puzzle': '<path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z"/>',
        'rewind': '<path d="M12 6a2 2 0 0 0-3.414-1.414l-6 6a2 2 0 0 0 0 2.828l6 6A2 2 0 0 0 12 18z"/><path d="M22 6a2 2 0 0 0-3.414-1.414l-6 6a2 2 0 0 0 0 2.828l6 6A2 2 0 0 0 22 18z"/>',
        'skip-fwd': '<path d="M21 4v16"/><path d="M6.029 4.285A2 2 0 0 0 3 6v12a2 2 0 0 0 3.029 1.715l9.997-5.998a2 2 0 0 0 .003-3.432z"/>',
        'volume': '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/>',
        'mute': '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="m22 9-6 6"/><path d="m16 9 6 6"/>',
        'link': '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'key': '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
        'eye': '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'pencil': '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'bookmark': '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
        'play': '<path fill="currentColor" d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/>',
        'skip-back': '<path d="M17.971 4.285A2 2 0 0 1 21 6v12a2 2 0 0 1-3.029 1.715l-9.997-5.998a2 2 0 0 1-.003-3.432z"/><path d="M3 20V4"/>',
        'stop': '<rect width="14" height="14" x="5" y="5" rx="2"/>',
        'pause': '<rect x="14" y="4" width="4" height="16" rx="1"/><rect x="6" y="4" width="4" height="16" rx="1"/>',
        'fast-forward': '<path d="M12 6a2 2 0 0 1 3.414-1.414l6 6a2 2 0 0 1 0 2.828l-6 6A2 2 0 0 1 12 18z"/><path d="M2 6a2 2 0 0 1 3.414-1.414l6 6a2 2 0 0 1 0 2.828l-6 6A2 2 0 0 1 2 18z"/>',
        'printer': '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        'menu': '<path d="M4 12h16"/><path d="M4 18h16"/><path d="M4 6h16"/>',
        'list': '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        'target': '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'ruler': '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/>',
        'pin': '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'bell': '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'inbox': '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'
    };

    // 이모지 → [아이콘, 색]  색: g 초록 · r 빨강 · a 노랑 · b 파랑 · '' 글자색
    var M = {
        '🏠': ['house', ''], '🏡': ['house', ''],
        '➕': ['plus', 'g'], '🆕': ['sparkles', ''], '✨': ['sparkles', ''], '🎉': ['sparkles', ''],
        '🔤': ['languages', ''], // ★ (2026-11-06 08:10)
        '🌐': ['globe', ''], '🌍': ['globe', ''], '🌏': ['globe', ''],
        '🚪': ['log-out', 'r'],
        '📊': ['chart', ''], '📈': ['trending', ''],
        '📝': ['file-text', ''], '📄': ['file', ''], '📃': ['file', ''],
        '🖥': ['monitor', ''], '💻': ['laptop', ''], '🪟': ['window', ''],
        '⚙': ['settings', ''], '🔧': ['wrench', ''], '🛠': ['wrench', ''], '🧰': ['wrench', ''],
        '🎨': ['palette', ''], '🏷': ['tag', ''],
        '📚': ['library', ''],
        '📁': ['folder', ''], '📂': ['folder-open', ''], '🗂': ['layout-list', ''],
        '🗄': ['database', ''],
        '🔍': ['search', ''], '🔎': ['file-search', ''],
        '📢': ['megaphone', ''], '📣': ['megaphone', ''],
        '👥': ['users', ''], '👤': ['user', ''],
        '🗑': ['trash', 'r'],
        '📜': ['scroll', ''],
        '🔐': ['lock', ''], '🔒': ['lock', ''],
        '🛡': ['shield', ''],
        '📧': ['mail', ''], '✉': ['mail', ''], '📨': ['mail', ''],
        '🕐': ['clock', ''], '⏱': ['clock', ''], '⏰': ['alarm', ''],
        '📅': ['calendar', ''], '📆': ['calendar', ''],
        '📋': ['clipboard', ''],
        '📖': ['book-open', ''],
        '🌙': ['moon', ''], '☀': ['sun', ''],
        '🖼': ['image', ''], '📷': ['camera', ''],
        '🎬': ['film', ''],
        '📦': ['package', ''],
        '⚠': ['alert', 'a'], '🚨': ['alert', 'r'],
        'ℹ': ['info', 'b'], '❓': ['help', 'b'],
        '✅': ['circle-check', 'g'], '✓': ['check', 'g'], '✔': ['check', 'g'], '☑': ['square-check', ''], '☐': ['square', ''], '⬜': ['square', ''],
        '❌': ['circle-x', 'r'], '✗': ['x', 'r'], '✖': ['x', 'r'],
        '🚫': ['ban', 'r'], '⛔': ['ban', 'r'],
        '🔄': ['refresh', ''], '🔃': ['refresh', ''], '♻': ['refresh', ''], '🔁': ['repeat', ''],
        '💾': ['save', ''],
        '⚡': ['zap', ''], '💡': ['bulb', 'a'],
        '📥': ['download', ''], '📤': ['upload', ''],
        '⬆': ['arrow-up', ''], '⬇': ['arrow-down', ''],
        '⭐': ['star', 'a'], '🌟': ['star', 'a'], '☆': ['star-o', ''],
        '🔗': ['link', ''], '🔑': ['key', ''], '👁': ['eye', ''],
        '✏': ['pencil', ''], '🖊': ['pencil', ''],
        '🔖': ['bookmark', ''], '📑': ['list', ''],
        '⏪': ['rewind', ''], '⏭': ['skip-fwd', ''], '🔊': ['volume', ''], '🔇': ['mute', ''], '⏮': ['skip-back', ''], '⏹': ['stop', ''], '⏸': ['pause', ''], '⏩': ['fast-forward', ''], '⏬': ['chevrons-down', ''],
        '🖨': ['printer', ''], '☰': ['menu', ''],
        '🎯': ['target', ''], '📏': ['ruler', ''], '📐': ['ruler', ''],
        '🔬': ['microscope', ''], '🧠': ['memory', ''], '📡': ['network', ''], '🐘': ['code', ''], '🧩': ['puzzle', ''],
        '📌': ['pin', ''], '📍': ['pin', ''], '🔔': ['bell', ''], '📭': ['inbox', ''],
        // 화살표·삼각형: 버튼·링크 안에서만 (문장 속 "A → B" 같은 글자는 그대로)
        '←': ['arrow-left', '', 1], '→': ['arrow-right', '', 1], '↔': ['arrows-lr', '', 1],
        '◀': ['chevron-left', '', 1], '▶': ['play', '', 1]
    };

    // 아이콘으로 안 바꾸는 곳: 입력·코드·본문·파일/작품 이름·공지
    var SKIP = 'script,style,noscript,textarea,select,option,input,code,pre,svg,[contenteditable],.mc-noico,' +
        '#txtContent,#txtContentWrapper,.notice-popup-body,.notice-popup-text,' +
        '#bookmarkTable td > button,#bookmarkTable tr:not(#bookmarkCountRow) small,.mc-title,.mc-continue-name,.card.text-black > .card-body,' +
        '.sc-name,.fav-title,.vol-title,.vol-name,#srName,.markdown-body,.readme-body,' +
        '#docx-container,#pptx-container,.xlsx-viewer,#hwp-viewer,' + // ★ (2026-11-02 09:04) 문서 뷰어 본문(문서 속 이모지를 아이콘으로 바꾸지 않게)
        '.bm-title,.bm-sub,.fav-sub,.fs-noico,' +
        '.mcv-name,.mcv-ttl,' +
        '.mc-fname,.mc-tab,.mc-parent,' +
        '.sr-desc,.chip,.sc-t,.lib-sel,.bm-ch,.bm-q,.lsd-t'; /* ★ (2026-11-04 00:29) 작품 설명·작가/태그 칩·거르기 칩·전자책 책갈피 장 이름/고른 글·범위 목록(본문 이모지) */ /* ★ (2026-11-02 21:18) 폴더 이름·콘텐츠 폴더 탭·위 경로(폴더 이름 속 이모지) */ /* ★ (2026-11-02 17:48) 뷰어 막대 제목·전체 제목 창(파일 이름 속 이모지를 아이콘으로 바꾸지 않게) */ /* ★ (2026-11-02 11:36) 즐겨찾기 목록 경로도 */ // ★ (2026-11-02 09:57) 북마크 화면 파일 이름·영상 트랙 이름 (영상 목록은 '⚠' 표시가 아이콘이어야 해서 뺌)
    var INTERACTIVE = 'button,a,label,.btn,[role="button"],.nav-zone-icon,.touch-area-icon,.page-arrow';

    var keys = Object.keys(M).sort(function (a, b) { return b.length - a.length; });
    var alt = keys.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\uFE0F?'; }).join('|');
    var TOKEN = new RegExp('(^|\\s)((?:' + alt + ')+)(?=\\s|$)', 'g');
    var ONE = new RegExp(alt, 'g');
    var QUICK = new RegExp(alt);

    function svg(name, color, emo) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        s.setAttribute('class', 'mc-ico mc-ei mc-ico-' + name + (color ? ' mc-c-' + color : ''));
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('fill', 'none');
        s.setAttribute('stroke', 'currentColor');
        s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round');
        s.setAttribute('stroke-linejoin', 'round');
        s.setAttribute('aria-hidden', 'true');
        s.setAttribute('focusable', 'false');
        s.setAttribute('data-emo', emo);
        s.innerHTML = P[name];
        return s;
    }

    function convertText(node) {
        var v = node.nodeValue;
        if (!v || !QUICK.test(v)) return;
        var pe = node.parentElement;
        if (!pe) return;
        var forced = !!pe.closest('.emoji-icon');
        if (!forced && pe.closest(SKIP)) return;
        var inter = forced || !!pe.closest(INTERACTIVE);
        TOKEN.lastIndex = 0;
        var m, last = 0, frag = null;
        while ((m = TOKEN.exec(v)) !== null) {
            var run = m[2], start = m.index + m[1].length, parts = run.match(ONE) || [];
            var ok = parts.length && parts.every(function (e) { var k = M[e.replace(/️/g, '')]; return k && (!k[2] || inter); });
            if (!ok) continue;
            if (!frag) frag = document.createDocumentFragment();
            if (start > last) frag.appendChild(document.createTextNode(v.slice(last, start)));
            parts.forEach(function (e) {
                var base = e.replace(/️/g, ''), k = M[base];
                var name = k[0];
                if (base === '▶') {   // 글자 없이 ▶ 만 있는 건 다음 화살표, 글자와 같이 있으면 재생
                    name = v.trim() === run ? 'chevron-right' : 'play';
                }
                frag.appendChild(svg(name, k[1], base));
            });
            last = start + run.length;
        }
        if (!frag) return;
        if (last < v.length) frag.appendChild(document.createTextNode(v.slice(last)));
        node.parentNode.replaceChild(frag, node);
    }

    function walk(root) {
        if (!root) return;
        if (root.nodeType === 3) { convertText(root); return; }
        if (root.nodeType !== 1 && root.nodeType !== 11) return;
        if (root.nodeType === 1 && root.matches && root.matches('script,style,svg,textarea,select,input,code,pre')) return;
        var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), list = [], n;
        while ((n = w.nextNode())) { if (QUICK.test(n.nodeValue)) list.push(n); }
        for (var i = 0; i < list.length; i++) convertText(list[i]);
    }

    function css() {
        if (document.getElementById('mc-icons-css')) return;
        var st = document.createElement('style');
        st.id = 'mc-icons-css';
        st.textContent =
            'svg.mc-ei{width:1.15em;height:1.15em;vertical-align:-.2em;flex:0 0 auto;display:inline-block;overflow:visible}' +
            '.mc-ico,.mc-ico *{pointer-events:none}' +
            '.emoji-icon svg.mc-ei{width:1em;height:1em;vertical-align:-.15em}' +
            'svg.mc-c-g{color:#1e8e3e}svg.mc-c-r{color:#d93025}svg.mc-c-a{color:#e8a200}svg.mc-c-b{color:#1a73e8}' +
            'html.dark-mode svg.mc-c-g,[data-theme="dark"] svg.mc-c-g{color:#81c995}' +
            'html.dark-mode svg.mc-c-r,[data-theme="dark"] svg.mc-c-r{color:#f28b82}' +
            'html.dark-mode svg.mc-c-a,[data-theme="dark"] svg.mc-c-a{color:#fdd663}' +
            'html.dark-mode svg.mc-c-b,[data-theme="dark"] svg.mc-c-b{color:#8ab4f8}' +
            // 색 버튼·배지·색 배경 안에서는 글자색 그대로
            '.btn svg.mc-ei,.badge svg.mc-ei,[class*="bg-"] svg.mc-ei,.quick-link svg.mc-ei,.nav-link.active svg.mc-ei{color:inherit}' +
            // 즐겨찾기 별(.fav-btn)·별 표시는 노랑 유지
            '.fav-btn svg.mc-ei,.fav-count svg.mc-ei{color:#f5b400}' +
            // ★ 목록 카드의 별은 링크 안이라 공통 링크 규칙(color … !important)에 색이 덮임 → 색(color) 대신 채우기(fill)로 노랑 고정
            //   (즐겨찾기 아님 = .fav-btn 의 grayscale 로 회색, 즐겨찾기 = 노랑)
            '.fav-btn svg.mc-ico-star path{fill:#f5b400 !important;stroke:#f5b400 !important}' +
            // 뷰어 즐겨찾기 버튼: 켜짐 ⭐ = 노란 별 + 진한 테두리(노란 버튼 위에서도 보이게), 꺼짐 ☆ = 테두리 별(버튼 글자색)
            '#favBtn svg.mc-ico-star path{fill:#ff9800 !important;stroke:#7a4100 !important;stroke-width:1.4px}' +
            '#favBtn.btn-outline-warning:not(:hover) svg.mc-ico-star-o{color:#ffc107 !important}' +
            '.btn-outline-secondary svg.mc-c-a,.btn-light svg.mc-c-a{color:#e8a200}' +
            '.quick-link svg.mc-ei{width:1.6em;height:1.6em;margin-bottom:2px}' +
            // darkmode.css 의 '.dark-mode .btn svg{fill:… !important}'(채운 아이콘용)가 선 아이콘을 덮어 채우지 않게
            '.dark-mode .btn svg.mc-ei,[data-theme="dark"] .btn svg.mc-ei,.dark-mode .btn svg.mc-ico,[data-theme="dark"] .btn svg.mc-ico{fill:none !important}';
        (document.head || document.documentElement).appendChild(st);
    }

    var mo = null;
    function observe(target) {
        if (!window.MutationObserver || mo || !target) return;
        mo = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var mu = muts[i];
                if (mu.type === 'characterData') { convertText(mu.target); continue; }
                for (var j = 0; j < mu.addedNodes.length; j++) walk(mu.addedNodes[j]);
            }
        });
        mo.observe(target, { childList: true, subtree: true, characterData: true });
    }
    function run() {
        css();
        walk(document.body);
        observe(document.body);
    }

    // ★ (2026-11-05 23:15) <head> 에서 불러온 경우(목록 화면) — 본문이 들어오는 대로(화면에 그리기 전에) 바꿈
    //   예전엔 페이지 맨 끝에서 불러와 본문을 다 받은 뒤 한꺼번에 바꿨고, 그 사이 먼저 그려진 예전 이모지(📁·📚 등)가 잠깐 보였음(펜닐 제보)
    //   DOMContentLoaded 때 한 번 더 훑어 빠진 것이 없게 / 페이지 끝에서 불러오는 화면은 예전과 똑같이 동작
    if (document.body) run();
    else {
        css();
        observe(document.documentElement);
        document.addEventListener('DOMContentLoaded', function () { walk(document.body); });
    }
})();
