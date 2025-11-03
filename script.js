(function() {
  const root = document.documentElement;
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const savedTheme = localStorage.getItem('theme');
  const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
  root.setAttribute('data-theme', initialTheme);

  const $ = (sel, parent=document) => parent.querySelector(sel);
  const $$ = (sel, parent=document) => Array.from(parent.querySelectorAll(sel));

  const navToggle = $('.nav-toggle');
  const navMenu = $('.nav-menu');
  const themeToggle = $('.theme-toggle');
  const yearEl = $('#year');

  if (yearEl) yearEl.textContent = new Date().getFullYear();

  navToggle?.addEventListener('click', () => {
    const isOpen = navMenu.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
  });

  $$('.nav-link', navMenu).forEach(link => link.addEventListener('click', () => {
    navMenu.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
  }));

  themeToggle?.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    themeToggle.textContent = next === 'dark' ? '🌙' : '☀️';
  });
  themeToggle.textContent = initialTheme === 'dark' ? '🌙' : '☀️';

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]');
    if (!a) return;
    const href = a.getAttribute('href');
    const target = href && href.length > 1 ? $(href) : null;
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.replaceState(null, '', href);
    }
  });

  const sections = $$('.section');
  const navLinks = $$('.nav-link');
  const onScroll = () => {
    const top = window.scrollY + 120;
    let current = 'home';
    sections.forEach(sec => {
      if (sec.offsetTop <= top) current = sec.id;
    });
    navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === `#${current}`));
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('in');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  $$('.reveal').forEach(el => observer.observe(el));

  const projects = [
    {
      title: 'Dashboard Analytics',
      desc: 'Visualisasi metrik bisnis real-time dengan filter interaktif.',
      img: 'https://images.unsplash.com/photo-1551281044-8e8a1fa8aa74?q=80&w=1200&auto=format&fit=crop',
      tags: ['React', 'TypeScript', 'Charts']
    },
    {
      title: 'E-Commerce UI',
      desc: 'Antarmuka storefront cepat dengan optimasi LCP/CLS.',
      img: 'https://images.unsplash.com/photo-1519336555923-59661f41bb54?q=80&w=1200&auto=format&fit=crop',
      tags: ['Next.js', 'Tailwind', 'SSR']
    },
    {
      title: 'Design System',
      desc: 'Komponen dapat digunakan ulang, tema gelap/terang, dan dokumentasi.',
      img: 'https://images.unsplash.com/photo-1587620962725-abab7fe55159?q=80&w=1200&auto=format&fit=crop',
      tags: ['Storybook', 'A11y', 'Tokens']
    }
  ];
  const projectList = $('#project-list');
  if (projectList) {
    projectList.innerHTML = projects.map(p => `
      <article class="project reveal">
        <img src="${p.img}" alt="${p.title}" loading="lazy" referrerpolicy="no-referrer">
        <div class="px">
          <h3>${p.title}</h3>
          <p>${p.desc}</p>
          <div class="tags">${p.tags.map(t => `<span class="tag">${t}</span>`).join('')}</div>
        </div>
      </article>
    `).join('');
    $$('.project').forEach(el => observer.observe(el));
  }

  const form = $('#contact-form');
  const rules = {
    name: v => v.trim().length >= 2 || 'Nama minimal 2 karakter',
    email: v => /.+@.+\..+/.test(v) || 'Email tidak valid',
    message: v => v.trim().length >= 10 || 'Pesan minimal 10 karakter'
  };

  const setError = (name, msg) => {
    const el = $(`[data-for="${name}"]`);
    if (el) el.textContent = msg || '';
  };

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    let ok = true;
    for (const [k, validate] of Object.entries(rules)) {
      const res = validate(data[k]);
      if (res !== true) { ok = false; setError(k, res); } else { setError(k); }
    }
    if (!ok) return;
    form.reset();
    $$('.error', form).forEach(el => el.textContent = '');
    const success = $('.form-success', form);
    success.hidden = false;
    setTimeout(() => success.hidden = true, 3000);
  });
})();



