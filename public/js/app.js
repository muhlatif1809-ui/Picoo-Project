// app.js : interaksi kecil di browser (dialog, jumlah, hitung mundur, auto-refresh).
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var rp = function (n) { return 'Rp' + Number(n).toLocaleString('id-ID'); };

  // toast hilang sendiri
  var t = $('.toast'); if (t) setTimeout(function () { t.remove(); }, 2600);

  // tampilkan/sembunyikan password
  var eye = $('#eye'); if (eye) eye.onclick = function () { var p = $('#pw'); p.type = p.type === 'password' ? 'text' : 'password'; };

  // konfirmasi sebelum kirim form: <form data-confirm="Yakin?">
  $$('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });

  // dialog menu
  $$('[data-open]').forEach(function (b) { b.onclick = function () { var d = document.getElementById(b.dataset.open); if (d) d.showModal(); }; });
  $$('dialog').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
  $$('form.add').forEach(function (f) {
    var n = 1, max = +f.dataset.max, h = +f.dataset.h, b = $('.n', f), inp = $('[name=jml]', f), btn = $('.go', f);
    var u = function () { b.textContent = n; inp.value = n; btn.textContent = 'Tambahkan ke keranjang  ' + rp(n * h); }; u();
    $('.mn', f).onclick = function () { n = Math.max(1, n - 1); u(); };
    $('.pl', f).onclick = function () { n = Math.min(max, n + 1); u(); };
    f.addEventListener('submit', function (e) {
      if (f.dataset.cs && f.dataset.cs !== '0' && f.dataset.cs !== f.dataset.st) {
        if (!confirm('Keranjang berisi menu dari stand lain. Kosongkan dulu dan mulai pesanan baru?')) return e.preventDefault();
        $('[name=ganti]', f).value = '1';
      }
    });
  });

  // hitung mundur pembayaran: <span data-exp="epoch detik">
  var tm = $('[data-exp]');
  if (tm) {
    var exp = +tm.dataset.exp * 1000, ok = $('#ok');
    var tick = function () {
      var s = Math.max(0, Math.floor((exp - Date.now()) / 1000));
      tm.textContent = s ? 'Bayar dalam ' + String((s / 60) | 0).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0') : 'Waktu pembayaran habis';
      if (!s && ok) ok.disabled = true;
    };
    tick(); setInterval(tick, 1000);
  }

  // auto-refresh isi <div id="live"> (status pesanan, daftar pesanan penjual)
  var ms = +document.body.dataset.live;
  if (ms) setInterval(function () {
    if (document.hidden) return;
    fetch(location.href, { credentials: 'same-origin', headers: { 'X-Live': '1' } }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (h) {
      if (!h) return;
      var n = new DOMParser().parseFromString(h, 'text/html').getElementById('live'), o = document.getElementById('live');
      if (n && o && n.innerHTML !== o.innerHTML) o.innerHTML = n.innerHTML;
    }).catch(function () {});
  }, ms);
})();
