(() => {
  const input = document.getElementById('featureSearchInput');
  const resultsPanel = document.getElementById('featureSearchResults');
  if (!input || !resultsPanel) return;

  const featureMap = new Map();
  document.querySelectorAll('.tree-menu a[data-feature-id]').forEach((link) => {
    const id = link.dataset.featureId;
    if (!id) return;

    featureMap.set(id, {
      href: link.getAttribute('href'),
      title: link.textContent.trim()
    });
  });

  const items = Array.from(document.querySelectorAll('[id^="f-"]'))
    .map((el) => {
      const id = el.id;
      let title = '';
      let summary = '';

      if (el.tagName.toLowerCase() === 'tr') {
        const cols = el.querySelectorAll('td');
        title = (cols[0]?.textContent || '').trim();
        summary = (cols[1]?.textContent || '').trim();
      } else {
        title = (el.querySelector('h3')?.textContent || '').trim();
        summary = (el.querySelector('p')?.textContent || '').trim();
      }

      const moduleTitle = el.closest('section')?.querySelector('h2')?.textContent?.trim() || '';
      const feature = featureMap.get(id);
      return {
        id,
        title,
        summary,
        moduleTitle,
        href: feature?.href || '',
        navTitle: feature?.title || moduleTitle
      };
    })
    .filter((item) => item.title && item.href);

  const normalize = (value) => (value || '').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');

  const hide = () => {
    resultsPanel.classList.remove('show');
    resultsPanel.innerHTML = '';
  };

  const render = (query) => {
    const normalized = normalize(query);

    if (normalized.length < 2) {
      hide();
      return;
    }

    const matched = items.filter((item) => {
      const fullText = normalize([item.title, item.summary, item.moduleTitle, item.navTitle].join(' '));
      return fullText.includes(normalized);
    });

    if (matched.length === 0) {
      resultsPanel.innerHTML = '<div class="search-empty">Không có chức năng khớp với nội dung tìm kiếm.</div>';
      resultsPanel.classList.add('show');
      return;
    }

    const max = Math.min(matched.length, 14);
    const rows = matched.slice(0, max).map((item) => {
      const safeTitle = item.title.replace(/</g, '&lt;').replace(/>/g, '&gt;');
      const safePath = item.navTitle.replace(/</g, '&lt;').replace(/>/g, '&gt;');
      return `<a class="search-result" href="${item.href}">
        <span class="title">${safeTitle}</span>
        <span class="path">${safePath}</span>
      </a>`;
    }).join('');

    resultsPanel.innerHTML = rows;
    resultsPanel.classList.add('show');
  };

  input.addEventListener('input', (e) => render(e.target.value));

  document.addEventListener('click', (event) => {
    const wrapper = event.target.closest('.search-shell');
    if (!wrapper) {
      hide();
    }
  });
})();
