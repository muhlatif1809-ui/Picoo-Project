<?php
// ui.php : kerangka halaman (layout), navigasi, dan potongan HTML yang dipakai berulang.

const LAY = [
    'dine_in' => 'Dine in',
    'take_away' => 'Take Away'
];

const STATUS_LABEL = [
    'menunggu_bayar' => 'Menunggu bayar',
    'baru' => 'Pesanan baru',
    'diproses' => 'Diproses',
    'siap' => 'Siap diambil',
    'selesai' => 'Selesai',
    'batal' => 'Dibatalkan'
];

/**
 * Membungkus isi halaman dengan HTML, CSS, dan JS.
 * $o: css, wrap, live (ms untuk auto-refresh).
 */
function layout(string $title, string $body, array $o = []): void
{
    $css = $o['css'] ?? 'app';
    $wrap = $o['wrap'] ?? 'app';
    $fl = take_flash();

    // Menentukan alamat dasar aplikasi saat dijalankan di XAMPP atau hosting.
    $scriptPath = str_replace(
        '\\',
        '/',
        $_SERVER['SCRIPT_NAME'] ?? ''
    );

    $base = str_contains($scriptPath, '/PICO/') ? '/PICO' : '';

    // Pastikan header dikirim sebelum output HTML.
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    echo '<!DOCTYPE html><html lang="id"><head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · PICO</title>'
        . '<link rel="stylesheet" href="' . $base . '/css/' . e($css) . '.css?v=' . aset_versi('css/' . $css . '.css') . '">'
        . '</head>'
        . '<body' . (!empty($o['live']) ? ' data-live="' . (int) $o['live'] . '"' : '') . '>'
        . '<div class="' . e($wrap) . '">' . $body . '</div>';

    if ($fl !== '') {
        echo '<div class="toast">' . e($fl) . '</div>';
    }

    echo pico_ui_effects()
        . '<script src="' . $base . '/js/app.js?v=' . aset_versi('js/app.js') . '"></script>'
        . '</body></html>';
}

/**
 * CSS & JS Efek UI Global (Button Animation, Card Hover, Skeleton Shimmer, & Responsif)
 * Berlaku otomatis untuk Pembeli, Penjual, dan Admin.
 */
function pico_ui_effects(): string
{
    return <<<'HTML'
<style id="pico-ui-effects">
  :root {
    --pico-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --pico-accent-glow: rgba(242, 140, 40, 0.28);
    --pico-card-shadow: 0 12px 26px -6px rgba(15, 23, 42, 0.11), 0 4px 10px -2px rgba(15, 23, 42, 0.05);
  }

  /* ===================================================================
     1. BUTTON ANIMATION (Pembeli, Penjual, Admin)
     =================================================================== */
  .btn,
  .plus,
  .qty button,
  .stepper-in button,
  .pf-cam-btn,
  .sp-cam-btn,
  .role-btn,
  .st-proof-thumb,
  .st-proof-fallback,
  .pf-edit-btn,
  .pf-nav-row,
  .pf-back-btn,
  .sp-edit-btn,
  .sp-set-row,
  .sp-logout-btn,
  .hx-back,
  .hx-add,
  .back,
  .seller-filters a,
  .chips a,
  .adm-user-tabs a,
  .adm-user-tabs button,
  .ic-btn,
  .ktm-act button,
  .adm-logout,
  .adm-burger,
  .bukti-upload-btn,
  #btn-zoom-qr,
  .sw,
  .sp-switch {
    overflow: hidden;
    transition:
      transform 0.22s var(--pico-ease),
      box-shadow 0.22s var(--pico-ease),
      background-color 0.2s ease,
      border-color 0.2s ease,
      color 0.2s ease,
      filter 0.2s ease !important;
    will-change: transform, box-shadow;
    -webkit-tap-highlight-color: transparent;
  }

  @media (hover: hover) {
    .btn:not(:disabled):hover,
    .hx-add:hover,
    .sp-logout-btn:hover,
    .bukti-upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px -4px var(--pico-accent-glow), 0 3px 8px -2px rgba(15, 23, 42, 0.1);
      filter: brightness(1.03);
    }

    .btn.sec:not(:disabled):hover,
    .ic-btn:hover,
    .seller-filters a:hover,
    .chips a:hover,
    .adm-user-tabs a:hover,
    .adm-user-tabs button:hover,
    .pf-edit-btn:hover,
    .sp-edit-btn:hover,
    .hx-back:hover,
    .back:hover,
    .pf-back-btn:hover,
    #btn-zoom-qr:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 14px -3px rgba(15, 23, 42, 0.1);
    }

    .plus:hover {
      transform: scale(1.1) rotate(90deg);
      box-shadow: 0 6px 14px -2px var(--pico-accent-glow);
    }

    .qty button:hover,
    .stepper-in button:hover,
    .pf-cam-btn:hover,
    .sp-cam-btn:hover,
    .st-proof-thumb:hover,
    .st-proof-fallback:hover {
      transform: scale(1.06);
    }

    .pf-nav-row:hover,
    .sp-set-row:hover {
      background-color: rgba(242, 140, 40, 0.05);
      padding-left: 18px;
    }

    .pf-nav-row:hover .pf-nav-chev,
    .sp-set-row:hover .sp-chev,
    .seller-stat:hover .st-go svg,
    .seller-section-title a:hover svg {
      transform: translateX(3px);
    }
  }

  .btn:not(:disabled):active,
  .hx-add:active,
  .sp-logout-btn:active,
  .bukti-upload-btn:active,
  .ic-btn:active,
  .seller-filters a:active,
  .chips a:active,
  .adm-user-tabs a:active,
  .adm-user-tabs button:active,
  .pf-edit-btn:active,
  .sp-edit-btn:active,
  .hx-back:active,
  .back:active,
  .pf-back-btn:active,
  .ktm-act button:active {
    transform: translateY(0) scale(0.95) !important;
    transition-duration: 0.08s !important;
  }

  .plus:active,
  .qty button:active,
  .stepper-in button:active,
  .pf-cam-btn:active,
  .sp-cam-btn:active,
  .st-proof-thumb:active,
  .st-proof-fallback:active {
    transform: scale(0.92) !important;
  }

  .pf-nav-chev,
  .sp-chev,
  .st-go svg,
  .seller-section-title a svg {
    display: inline-block;
    transition: transform 0.22s var(--pico-ease);
  }

  /* Efek Ripple saat tombol diklik */
  .pico-ripple {
    position: absolute;
    border-radius: 50%;
    transform: scale(0);
    pointer-events: none;
    background: rgba(255, 255, 255, 0.45);
    animation: picoRippleAnim 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  .btn.sec .pico-ripple,
  .ic-btn .pico-ripple,
  .pf-nav-row .pico-ripple,
  .sp-set-row .pico-ripple,
  .seller-filters a:not(.on) .pico-ripple,
  .chips a:not(.on) .pico-ripple {
    background: rgba(242, 140, 40, 0.18);
  }

  @keyframes picoRippleAnim {
    to {
      transform: scale(3.2);
      opacity: 0;
    }
  }

  /* ===================================================================
     2. CARD HOVER (Pembeli, Penjual, Admin)
     =================================================================== */
  .stand,
  .mres,
  .pc,
  .item,
  .order-item,
  .box,
  .opt,
  .pilih a,
  .role-btn,
  .st-card,
  .stand-switch,
  .pf-card,
  .pf-order-card,
  .seller-stat,
  .ocard,
  .so,
  .seller-order,
  .mrow,
  .pd-card,
  .chart-card,
  .sum,
  .em-card,
  .sp-card,
  .sp-metric,
  .sp-rep-box,
  .sp-status-bar,
  .kpi,
  .card,
  .panel,
  .tw,
  .adm-add,
  .adm-auth-card {
    transition:
      transform 0.26s var(--pico-ease),
      box-shadow 0.26s var(--pico-ease),
      border-color 0.26s ease,
      background-color 0.22s ease !important;
    will-change: transform, box-shadow;
  }

  .stand .thumb img,
  .mres .thumb img,
  .pc .im img,
  .item .thumb img,
  .seller-menu-photo img {
    transition: transform 0.35s var(--pico-ease), opacity 0.3s ease !important;
  }

  @media (hover: hover) {
    .stand:not(.off):hover,
    .mres:not(.off):hover,
    .pc:not(.out):hover,
    .item:hover,
    .order-item:hover,
    .pilih a:hover,
    .role-btn:hover,
    .st-pickup-card:hover,
    .stand-switch:hover,
    .opt:hover,
    .pf-order-card:hover,
    .seller-stat:hover,
    .ocard:hover,
    .so:hover,
    .seller-order:hover,
    .mrow:hover,
    .sp-metric:hover,
    .sp-rep-box:hover,
    .sp-status-bar:hover,
    .kpi:hover,
    .card:hover,
    .sum:hover {
      transform: translateY(-4px);
      box-shadow: var(--pico-card-shadow);
      border-color: rgba(242, 140, 40, 0.38);
    }

    .pf-card:hover,
    .sp-card:hover,
    .st-card:hover,
    .pd-card:hover,
    .chart-card:hover,
    .em-card:hover,
    .panel:hover,
    .tw:hover,
    .adm-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px -8px rgba(15, 23, 42, 0.09);
    }

    .stand:not(.off):hover .thumb img,
    .mres:not(.off):hover .thumb img,
    .pc:not(.out):hover .im img,
    .item:hover .thumb img,
    .mrow:hover .seller-menu-photo img {
      transform: scale(1.06);
    }

    .mcards tr:not(:first-child) {
      transition: background-color 0.18s ease, transform 0.18s var(--pico-ease);
    }
    .mcards tr:not(:first-child):hover {
      background-color: rgba(242, 140, 40, 0.045);
    }
  }

  a.stand:not(.off):active,
  a.mres:not(.off):active,
  a.order-item:active,
  .pilih a:active,
  .role-btn:active,
  a.pf-order-card:active,
  a.seller-stat:active,
  a.so:active,
  a.sp-status-bar:active {
    transform: scale(0.985) !important;
    transition-duration: 0.1s !important;
  }

  /* ===================================================================
     3. SKELETON SHIMMER (Pembeli, Penjual, Admin)
     =================================================================== */
  @keyframes picoShimmer {
    0% {
      background-position: 200% 0;
    }
    100% {
      background-position: -200% 0;
    }
  }

  .pico-skel,
  .skeleton {
    position: relative;
    overflow: hidden;
    background: linear-gradient(
      90deg,
      #e2e8f0 25%,
      #f8fafc 50%,
      #e2e8f0 75%
    ) !important;
    background-size: 200% 100% !important;
    animation: picoShimmer 1.35s infinite linear !important;
  }

  .pico-skel > img {
    opacity: 0;
  }

  .pico-img-loaded {
    opacity: 1 !important;
  }

  .pico-card-shimmer {
    position: relative !important;
    overflow: hidden !important;
  }

  .pico-card-shimmer::after {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 5;
    pointer-events: none;
    background: linear-gradient(
      90deg,
      rgba(255, 255, 255, 0) 0%,
      rgba(255, 255, 255, 0.72) 45%,
      rgba(248, 250, 252, 0.9) 50%,
      rgba(255, 255, 255, 0.72) 55%,
      rgba(255, 255, 255, 0) 100%
    );
    background-size: 220% 100%;
    animation: picoShimmer 1.1s infinite linear;
    border-radius: inherit;
  }

  body.pico-booting .pico-card-shimmer > * {
    opacity: 0.32;
    filter: blur(1px);
    transition: opacity 0.28s ease, filter 0.28s ease;
  }

  /* ===================================================================
     4. POSISI TOMBOL PLUS DI KANAN & RESPONSIF
     =================================================================== */
  .pc {
    position: relative;
    display: flex;
    flex-direction: column;
  }

  .pc .plus {
    position: absolute;
    right: 10px;
    bottom: 10px;
  }

  .pc .pr {
    padding-right: 38px;
  }

  .app,
  .adm {
    overflow-x: clip;
  }

  dialog {
    max-width: min(92vw, 420px);
  }

  @media (max-width: 380px) {
    .grid2 {
      gap: 10px;
    }
    .pc {
      padding: 8px 8px 12px;
    }
    .pc b {
      font-size: 0.88rem;
    }
    .pc .pr {
      font-size: 0.84rem;
      padding-right: 34px;
    }
    .pc .plus {
      width: 30px;
      height: 30px;
      right: 8px;
      bottom: 8px;
      font-size: 1.1rem;
    }
  }

  @media (min-width: 768px) {
    .app {
      max-width: 480px;
      margin: 0 auto;
      box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    }
    .app .nav,
    .app .foot {
      max-width: 480px;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .pico-skel,
    .skeleton,
    .pico-card-shimmer::after {
      animation: none !important;
    }
  }
</style>
<script id="pico-ui-effects-js">
(function () {
  var CARD_SEL = [
    '.stand', '.mres', '.pc', '.item', '.order-item', '.pilih a', '.role-btn', '.st-card', '.stand-switch',
    '.pf-card', '.pf-order-card', '.seller-stat', '.ocard', '.so',
    '.seller-order', '.mrow', '.pd-card', '.chart-card', '.sum',
    '.em-card', '.sp-card', '.sp-metric', '.sp-rep-box', '.sp-status-bar',
    '.kpi', '.card', '.panel', '.tw', '.adm-auth-card'
  ].join(',');

  var BTN_SEL = [
    '.btn', '.plus', '.qty button', '.stepper-in button', '.pilih a', '.role-btn', '.sp-cam-btn', '.st-proof-thumb', '.st-proof-fallback',
    '.pf-cam-btn', '.pf-edit-btn', '.pf-nav-row', '.pf-back-btn',
    '.sp-edit-btn', '.sp-set-row', '.sp-logout-btn', '.hx-back',
    '.hx-add', '.back', '.seller-filters a', '.chips a',
    '.adm-user-tabs a', '.adm-user-tabs button', '.ic-btn',
    '.ktm-act button', '.adm-logout', '.bukti-upload-btn', '#btn-zoom-qr'
  ].join(',');

  function initImageSkeletons(root) {
    var imgs = (root || document).querySelectorAll(
      '.thumb img, .pc .im img, .seller-menu-photo img, .em-photo-img img, .pf-avatar img, .sp-avatar img, .st-proof-thumb img, #img-qr-stand, .bukti-th'
    );
    imgs.forEach(function (img) {
      var wrap = img.parentElement;
      if (!wrap) return;
      if (img.complete && img.naturalWidth > 0) {
        wrap.classList.remove('pico-skel');
        img.classList.add('pico-img-loaded');
        return;
      }
      wrap.classList.add('pico-skel');
      function done() {
        wrap.classList.remove('pico-skel');
        img.classList.add('pico-img-loaded');
      }
      img.addEventListener('load', done, { once: true });
      img.addEventListener('error', done, { once: true });
    });
  }

  function triggerCardShimmer(duration) {
    var cards = document.querySelectorAll(CARD_SEL);
    if (!cards.length) return;
    document.body.classList.add('pico-booting');
    cards.forEach(function (c) {
      c.classList.add('pico-card-shimmer');
    });
    setTimeout(function () {
      document.body.classList.remove('pico-booting');
      cards.forEach(function (c, idx) {
        setTimeout(function () {
          c.classList.remove('pico-card-shimmer');
        }, Math.min(idx * 25, 180));
      });
    }, duration || 340);
  }

  document.addEventListener('pointerdown', function (e) {
    var btn = e.target.closest(BTN_SEL);
    if (!btn || btn.disabled) return;
    if (window.getComputedStyle(btn).position === 'static') {
      btn.style.position = 'relative';
    }
    var rect = btn.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height, 28);
    var ripple = document.createElement('span');
    ripple.className = 'pico-ripple';
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
    btn.appendChild(ripple);
    setTimeout(function () {
      if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
    }, 600);
  }, { passive: true });

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (
      e.defaultPrevented ||
      e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ||
      a.target === '_blank' ||
      a.hasAttribute('download') ||
      href === '' ||
      href.charAt(0) === '#' ||
      href.indexOf('javascript:') === 0
    ) {
      return;
    }
    triggerCardShimmer(1200);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initImageSkeletons(document);
      triggerCardShimmer(320);
    });
  } else {
    initImageSkeletons(document);
    triggerCardShimmer(320);
  }

  if (typeof MutationObserver !== 'undefined') {
    var obs = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (node) {
          if (node && node.nodeType === 1) {
            initImageSkeletons(node);
          }
        });
      });
    });
    obs.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
</script>
HTML;
}


/**
 * Membuat ikon SVG.
 */
function ico(string $k): string
{
    static $IC = [
    'home' => 'M3 11l9-8 9 8v10H3zM9 21v-6h6v6',
    'cart' => 'M3 4h2l2.5 11h10l2-8H6.5M9 20h.01M17 20h.01',
    'book' => 'M5 3h14v18H5zM5 17h14',
    'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
    'list' => 'M4 6h16M4 12h16M4 18h16',
    'fork' => 'M7 3v8M4 3v5a3 3 0 006 0V3M7 11v10M17 3c-2 2-3 4-3 7h3v11',
    'clock' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2'
    ];

    $path = $IC[$k] ?? $IC['home'];

    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="' . $path . '"/></svg>';
}

/**
 * Ikon garis (SVG) untuk tombol, kartu, dan judul.
 * ic('search', 18) -> ikon kaca pembesar ukuran 18px.
 */
function ic(string $k, int $size = 20): string
{
    static $P = [
    'back' => 'M15 18l-6-6 6-6',
    'next' => 'M9 18l6-6-6-6',
    'arrow' => 'M5 12h14M13 6l6 6-6 6',
    'search' => 'M10.5 4a6.5 6.5 0 110 13 6.5 6.5 0 010-13zM15.5 15.5l4.5 4.5',
    'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 100 6 3 3 0 000-6z',
    'trash' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
    'menu' => 'M4 7h16M4 12h16M4 17h16',
    'download' => 'M12 4v11M7 11l5 5 5-5M5 20h14',
    'tag' => 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01',
    'fork' => 'M7 3v8M4 3v5a3 3 0 006 0V3M7 11v10M17 3c-2 2-3 4-3 7h3v11',
    'minus' => 'M5 12h14',
    'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
    'image' => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M9 9.5h.01',
    'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
    'cart' => 'M3 4h2l2.5 11h10l2-8H6.5M9 20h.01M17 20h.01',
    'store' => 'M3 9l1.5-5h15L21 9M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8',
    'receipt' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
    'clock' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
    'plus' => 'M12 5v14M5 12h14',
    'wallet' => 'M3 7h18v12H3zM3 7l2-3h14l2 3M16 13h2',
    'bowl' => 'M3 11h18a9 9 0 01-18 0zM8 7c0-1.5 1-2 1-3.5M13 7c0-1.5 1-2 1-3.5',
    'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
    'flame' => 'M12 2c1 4 5 5 5 10a5 5 0 01-10 0c0-2 1-3 2-4 0 2 1 2 2 2 0-3-1-5 1-8z',
    'bell' => 'M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0',
    'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
    'users' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21c0-4 3-6 7-6s7 2 7 6M17 3.5a4 4 0 010 7.5M22 21c0-3-2-5-4-5.8',
    'orders' => 'M9 4h6l1 2h3v15H5V6h3zM9 12h6M9 16h4',
    'logout' => 'M9 4H5v16h4M16 8l4 4-4 4M20 12H9',
    'chev' => 'M6 9l6 6 6-6',
    'check' => 'M5 12l5 5L20 7',
    'edit' => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z',
    'x' => 'M6 6l12 12M18 6L6 18',
    ];
    $path = $P[$k] ?? $P['store'];
    // kaca pembesar dibuat sedikit lebih tebal supaya tetap tegas di ukuran kecil
    $sw = $k === 'search' ? '2.3' : '1.9';

    return '<svg class="ic" viewBox="0 0 24 24" width="' . $size . '" height="' . $size
        . '" fill="none" stroke="currentColor" stroke-width="' . $sw . '" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="'
        . $path . '"/></svg>';
}

/**
 * Warna khas tiap kios (dipakai untuk kotak nomor kios).
 * Nomor kios yang sama selalu dapat warna yang sama.
 */
function kios_warna($kios): string
{
    static $C = ['#f28c28', '#2f9e8f', '#6c63d9', '#d9487a', '#3b82c4', '#7a9a2c'];

    return $C[abs((int) $kios) % count($C)];
}

/**
 * Kotak nomor kios: label kecil "Kios" + angka besar.
 */
function kios_tile($kios, string $extra = ''): string
{
    return '<div class="kios-tile ' . $extra . '" style="--k:' . kios_warna($kios) . '"><small>Kios</small><b>'
        . e($kios) . '</b></div>';
}

/**
 * Header halaman gaya baru (judul + keterangan + ikon/jumlah di kanan).
 * $o: back (alamat tombol kembali), icon (nama ikon), count (angka di pojok ikon).
 * $title dan $sub boleh berisi HTML yang sudah di-escape oleh pemanggil.
 */
function hdx(string $title, string $sub = '', array $o = []): string
{
    $h = '<header class="hx">';

    if (!empty($o['back'])) {
        $h .= '<a class="hx-back" href="' . $o['back'] . '">' . ic('back', 18) . '<span>Kembali</span></a>';
    }

    $h .= '<div class="hx-row"><div class="hx-main"><h1>' . $title . '</h1>'
        . ($sub !== '' ? '<p class="hx-sub">' . $sub . '</p>' : '')
        . '</div>';

    if (!empty($o['icon'])) {
        $h .= '<div class="hx-ic">' . ic($o['icon'], 24);
        if (isset($o['count']) && (int) $o['count'] > 0) {
            $h .= '<span class="hx-count">' . (int) $o['count'] . '</span>';
        }
        $h .= '</div>';
    }

    return $h . '</div>' . ($o['extra'] ?? '') . '</header>';
}

/**
 * Saklar on/off (dipakai untuk buka/tutup stand).
 * Berupa tombol di dalam form: pemanggil yang menyiapkan form-nya.
 */
function saklar(bool $on, string $label): string
{
    return '<button type="submit" class="sw ' . ($on ? 'on' : '') . '" role="switch" aria-checked="'
        . ($on ? 'true' : 'false') . '" aria-label="' . e($label) . '"><i></i></button>';
}

/**
 * Keadaan kosong: ikon, judul, keterangan, dan (opsional) tombol.
 */
function empty_state(string $icon, string $title, string $text, string $href = '', string $btn = ''): string
{
    return '<div class="es"><div class="es-ic">' . ic($icon, 30) . '</div><h2>' . e($title) . '</h2><p>' . e($text) . '</p>'
        . ($href !== '' ? '<a class="btn es-btn" href="' . $href . '">' . e($btn) . '</a>' : '')
        . '</div>';
}

/**
 * Penanda versi untuk file CSS/JS supaya browser memuat ulang setelah deploy.
 * Memakai waktu ubah file; jika tidak terbaca di hosting, pakai angka cadangan
 * (naikkan angkanya setiap kali CSS/JS berubah).
 */
function aset_versi(string $file): string
{
    $t = @filemtime(dirname(__DIR__, 2) . '/public/' . $file);

    return $t ? (string) $t : 'r3';
}

/**
 * Membuat navigasi berdasarkan daftar menu.
 */
function nav(array $items, string $on): string
{
    $h = '<nav class="nav">';

    foreach ($items as [$href, $icon, $label]) {
        $h .= '<a href="' . $href . '" class="' . ($on === $href ? 'on' : '') . '">'
            . ico($icon) . $label . '</a>';
    }

    return $h . '</nav>';
}

/**
 * Navigasi pembeli.
 */
function nav_pel(string $on): string
{
    return nav([
        ['/home', 'home', 'Home'],
        ['/keranjang', 'cart', 'Keranjang'],
        ['/riwayat', 'book', 'Riwayat'],
        ['/profil', 'user', 'Profil']
    ], $on);
}

/**
 * Navigasi penjual.
 */
function nav_pen(string $on): string
{
    return nav([
        ['/penjual', 'home', 'Beranda'],
        ['/penjual/pesanan', 'book', 'Pesanan'],
        ['/penjual/menu', 'fork', 'Menu'],
        ['/penjual/riwayat', 'clock', 'Riwayat'],
        ['/penjual/profil', 'user', 'Profil']
    ], $on);
}

/**
 * Header biru.
 * $sub boleh berisi HTML yang sudah di-escape oleh pemanggil.
 */
function hd(string $t, string $sub = '', string $back = ''): string
{
    return '<header class="hd">'
        . ($back ? '<a class="back" href="' . $back . '">' . ic('back', 18) . 'Kembali</a>' : '')
        . '<h1>' . $t . '</h1>'
        . ($sub ? '<p>' . $sub . '</p>' : '')
        . '</header>';
}

/**
 * Menampilkan gambar atau placeholder.
 */
function img(?string $u): string
{
    return $u
        ? '<img src="' . e($u) . '" alt="" loading="lazy">'
        : '<span class="ph">🍽️</span>';
}

/**
 * Menampilkan label status pesanan.
 */
function tag(string $status): string
{
    return '<span class="tag s-' . e($status) . '">'
        . e(STATUS_LABEL[$status] ?? $status)
        . '</span>';
}

/**
 * Menampilkan pesan saat data kosong.
 */
function empty_box(string $t): string
{
    return '<div class="empty">' . e($t) . '</div>';
}