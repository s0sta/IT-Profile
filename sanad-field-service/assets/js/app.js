/* Sanad — Field Service Management · frontend behaviours (no dependencies) */
(function () {
  'use strict';

  /* ---------------------------------------------------- theme toggle */
  var themeBtn = document.getElementById('theme-toggle');
  if (themeBtn) {
    function syncThemeIcon() {
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      themeBtn.innerHTML = dark
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>';
      themeBtn.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    }
    themeBtn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('sanad-theme', next); } catch (e) {}
      syncThemeIcon();
    });
    syncThemeIcon();
  }

  /* ---------------------------------------------------- mobile sidebar */
  var navToggle = document.getElementById('nav-toggle');
  var overlay = document.getElementById('sidebar-overlay');
  if (navToggle && overlay) {
    navToggle.addEventListener('click', function () {
      document.body.classList.add('sidebar-open');
      overlay.classList.add('show');
    });
    overlay.addEventListener('click', function () {
      document.body.classList.remove('sidebar-open');
      overlay.classList.remove('show');
    });
  }

  /* ---------------------------------------------------- user dropdown */
  var chip = document.getElementById('user-chip');
  var drop = document.getElementById('user-dropdown');
  if (chip && drop) {
    chip.addEventListener('click', function (ev) {
      ev.stopPropagation();
      drop.hidden = !drop.hidden;
    });
    document.addEventListener('click', function (ev) {
      if (!drop.hidden && !drop.contains(ev.target) && ev.target !== chip) {
        drop.hidden = true;
      }
    });
  }

  /* ---------------------------------------------------- auto-submit selects */
  document.querySelectorAll('select.auto-submit').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var form = sel.closest('form');
      if (form) form.submit();
    });
  });

  /* ---------------------------------------------------- confirm dialogs */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* ---------------------------------------------------- asset quick search */
  var assetInput = document.querySelector('input[data-asset-search]');
  var assetBox = document.getElementById('asset-suggestions');
  if (assetInput && assetBox) {
    var timer = null;
    assetInput.addEventListener('input', function () {
      clearTimeout(timer);
      var q = assetInput.value.trim();
      if (q.length < 2) { assetBox.hidden = true; assetBox.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch('index.php?p=api&a=asset_search&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var items = (data && data.items) || [];
            if (!items.length) { assetBox.hidden = true; assetBox.innerHTML = ''; return; }
            assetBox.innerHTML = items.map(function (it) {
              return '<a class="kb-suggest-item" href="' + escapeHtml(it.url) + '">' +
                '<span class="kb-suggest-title">' + escapeHtml(it.tag) + ' — ' + escapeHtml(it.name) + '</span>' +
                '<span class="kb-suggest-excerpt">' + escapeHtml(it.status || '') + '</span></a>';
            }).join('');
            assetBox.hidden = false;
          })
          .catch(function () { assetBox.hidden = true; });
      }, 300);
    });
    document.addEventListener('click', function (ev) {
      if (assetBox && !assetBox.hidden && !assetBox.contains(ev.target) && ev.target !== assetInput) {
        assetBox.hidden = true;
      }
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------------------------------------------- notification bell polling */
  var bell = document.querySelector('.bell .bell-badge');
  if (bell) {
    setInterval(function () {
      fetch('index.php?p=api&a=notif_count')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var n = (d && d.unread) || 0;
          if (n > 0) {
            bell.textContent = n > 99 ? '99+' : String(n);
            bell.style.display = 'flex';
          } else {
            bell.style.display = 'none';
          }
        })
        .catch(function () { /* keep current badge */ });
    }, 60000);
  }

  /* ---------------------------------------------------- flash auto-dismiss */
  document.querySelectorAll('.flash').forEach(function (f) {
    setTimeout(function () {
      f.style.transition = 'opacity .5s ease';
      f.style.opacity = '0';
      setTimeout(function () { f.remove(); }, 500);
    }, 6000);
  });

  /* ---------------------------------------------------- textarea auto-grow */
  document.querySelectorAll('textarea').forEach(function (ta) {
    function grow() {
      ta.style.height = 'auto';
      ta.style.height = Math.min(Math.max(ta.scrollHeight, 90), 420) + 'px';
    }
    ta.addEventListener('input', grow);
    grow();
  });
})();
