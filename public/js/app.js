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
  var bindConfirm = function (f) {
    if (f.dataset.confirmBound) return;
    f.dataset.confirmBound = '1';
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  };
  $$('form[data-confirm]').forEach(bindConfirm);

  // Submit the stand/menu search shortly after typing stops; Enter still works.
  $$('form[data-live-search]').forEach(function (form) {
    var input = $('input[name="cari"]', form), timer;
    if (!input) return;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      }, 350);
    });
    form.addEventListener('submit', function () { clearTimeout(timer); });
  });

  var roleFilter = 'all';
  // Search within admin data tables without an extra request.
  $$('[data-table-search]').forEach(function (input) {
    var table = document.getElementById(input.dataset.tableSearch);
    if (!table) return;
    input.addEventListener('input', function () {
      var q = input.value.trim().toLocaleLowerCase();
      $$('tr', table).slice(1).forEach(function (row) {
        row.hidden = (q !== '' && !row.textContent.toLocaleLowerCase().includes(q)) ||
          (row.dataset.userRole && roleFilter !== 'all' && row.dataset.userRole !== roleFilter);
      });
    });
  });
  $$('[data-role-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
      roleFilter = button.dataset.roleFilter;
      $$('[data-role-filter]').forEach(function (item) { item.classList.toggle('on', item === button); });
      $$('[data-user-role]').forEach(function (row) {
        var input = $('[data-table-search="users-table"]');
        var q = input ? input.value.trim().toLocaleLowerCase() : '';
        row.hidden = (roleFilter !== 'all' && row.dataset.userRole !== roleFilter) ||
          (q !== '' && !row.textContent.toLocaleLowerCase().includes(q));
      });
    });
  });

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
      if (n && o && n.innerHTML !== o.innerHTML) {
        o.innerHTML = n.innerHTML;
        $$('form[data-confirm]', o).forEach(bindConfirm);
      }
    }).catch(function () {});
  }, ms);
})();
