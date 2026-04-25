/* ===== CONFIGURACIÓN ===== */
const WA_NUMBER = '5493734408167';
const WA_BASE   = `https://wa.me/${WA_NUMBER}`;
const CAT_LABELS = {
  todos:      'Todos',
  comedores:  'Juegos de Comedor',
  sillas:     'Sillas',
  mesas:      'Mesas',
  living:     'Living',
  dormitorio: 'Dormitorio'
};

/* ===== ESTADO ===== */
let productos    = [];
let activeFilter = 'todos';

/* ===== HELPERS ===== */
function waLink(nombre) {
  const msg = encodeURIComponent(`Hola, me interesa el producto: ${nombre}. ¿Podrían darme más información?`);
  return `${WA_BASE}?text=${msg}`;
}

function formatCat(cat) {
  return CAT_LABELS[cat] || cat;
}

function handleImgError(img) {
  img.style.display = 'none';
  img.parentElement.style.background = 'linear-gradient(135deg,#e8ddd0,#d4c5b0)';
}

function parsePrecio(str) {
  return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0;
}

function formatPeso(n) {
  return '$' + n.toLocaleString('es-AR');
}

function cuotas6(precioStr) {
  const val = parsePrecio(precioStr);
  if (!val) return '';
  return formatPeso(Math.ceil(val / 6));
}

const LOGOS_12 = `<img class="pay-img pay-img--wide" src="assets/img/logos/tuya-nbch.jpg" alt="TUYA"><img class="pay-img" src="assets/img/logos/nbch.jpg" alt="NBCH"><img class="pay-img" src="assets/img/logos/bcorrientes.png" alt="Banco Corrientes">`;
const LOGOS_6  = `<img class="pay-img" src="assets/img/logos/visa.svg" alt="Visa"><img class="pay-img" src="assets/img/logos/mastercard.svg" alt="Mastercard"><img class="pay-img" src="assets/img/logos/naranja.png" alt="Naranja X">`;

/* ===== SVG ICONS ===== */
const SVG_WA_MD = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.004 2.003C6.479 2.003 2 6.48 2 12.004c0 1.777.463 3.449 1.27 4.906L2 22l5.233-1.244A9.944 9.944 0 0012.004 22c5.523 0 10.001-4.478 10.001-10.001S17.527 2.003 12.004 2.003zm0 18.185a8.17 8.17 0 01-4.148-1.133l-.298-.177-3.1.737.78-2.965-.193-.302A8.17 8.17 0 013.82 12c0-4.512 3.67-8.182 8.183-8.182 4.512 0 8.182 3.67 8.182 8.182 0 4.512-3.67 8.188-8.181 8.188z"/></svg>`;

const SVG_WA_LG = `<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.004 2.003C6.479 2.003 2 6.48 2 12.004c0 1.777.463 3.449 1.27 4.906L2 22l5.233-1.244A9.944 9.944 0 0012.004 22c5.523 0 10.001-4.478 10.001-10.001S17.527 2.003 12.004 2.003zm0 18.185a8.17 8.17 0 01-4.148-1.133l-.298-.177-3.1.737.78-2.965-.193-.302A8.17 8.17 0 013.82 12c0-4.512 3.67-8.182 8.183-8.182 4.512 0 8.182 3.67 8.182 8.182 0 4.512-3.67 8.188-8.181 8.188z"/></svg>`;

/* ===== RENDER GRID ===== */
function renderGrid(list) {
  const grid = document.getElementById('product-grid');
  const noResults = document.getElementById('no-results');
  if (!grid) return;

  if (list.length === 0) {
    grid.innerHTML = '';
    noResults.classList.add('visible');
    return;
  }
  noResults.classList.remove('visible');

  grid.innerHTML = list.map(p => {
    const img = p.imagenes && p.imagenes.length ? p.imagenes[0] : '';
    const imgEl = img
      ? `<img src="${img}" alt="${p.nombre}" loading="lazy" onerror="handleImgError(this)">`
      : `<div class="img-placeholder"></div>`;

    return `
    <article class="product-card" data-id="${p.id}" data-cat="${p.categoria}" onclick="openProductModal(${p.id})">
      <div class="product-card__img-wrap">
        ${imgEl}
        <div class="product-card__img-overlay"><span>Ver detalle</span></div>
      </div>
      <div class="product-card__body">
        <div class="product-card__cat">${formatCat(p.categoria)}</div>
        <h3 class="product-card__name">${p.nombre}</h3>
        <p class="product-card__desc">${p.descripcion}</p>
        <div class="product-card__prices">
          <span class="price-main">${p.precio}</span>
          <span class="price-cuotas">${LOGOS_12} 12 cuotas sin interés de <strong>${p.precio_cuotas}</strong></span>
          <span class="price-cuotas">${LOGOS_6} 6 cuotas sin interés de <strong>${cuotas6(p.precio)}</strong></span>
          <span class="price-promo"><strong>${p.precio_promo}</strong> efectivo / transferencia</span>
        </div>
        <div class="product-card__footer">
          <a class="btn-wa" href="${waLink(p.nombre)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">
            ${SVG_WA_MD}
            Consultar
          </a>
        </div>
      </div>
    </article>`;
  }).join('');
}

/* ===== FILTROS ===== */
function applyFilter(cat) {
  activeFilter = cat;
  document.querySelectorAll('.filter-cat-card').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.cat === cat);
  });
  const list = cat === 'todos' ? productos : productos.filter(p => p.categoria === cat);
  renderGrid(list);
  document.getElementById('catalog-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ===== CATEGORY ICONS ===== */
const CAT_ICONS = {
  todos:      `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="13" height="13" rx="2"/><rect x="23" y="4" width="13" height="13" rx="2"/><rect x="4" y="23" width="13" height="13" rx="2"/><rect x="23" y="23" width="13" height="13" rx="2"/></svg>`,
  comedores:  `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="16" width="26" height="8" rx="2"/><line x1="12" y1="24" x2="12" y2="33"/><line x1="28" y1="24" x2="28" y2="33"/><rect x="1" y="10" width="7" height="9" rx="1.5"/><line x1="4.5" y1="19" x2="4.5" y2="25"/><rect x="32" y="10" width="7" height="9" rx="1.5"/><line x1="35.5" y1="19" x2="35.5" y2="25"/></svg>`,
  sillas:     `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="11" y="4" width="18" height="14" rx="2"/><rect x="9" y="18" width="22" height="8" rx="2"/><line x1="13" y1="26" x2="11" y2="36"/><line x1="27" y1="26" x2="29" y2="36"/></svg>`,
  mesas:      `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="34" height="7" rx="2"/><line x1="11" y1="20" x2="11" y2="34"/><line x1="29" y1="20" x2="29" y2="34"/></svg>`,
  living:     `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="17" width="30" height="13" rx="3"/><rect x="3" y="11" width="7" height="19" rx="3"/><rect x="30" y="11" width="7" height="19" rx="3"/><line x1="8" y1="30" x2="7" y2="36"/><line x1="32" y1="30" x2="33" y2="36"/></svg>`,
  dormitorio: `<svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="20" width="32" height="12" rx="2"/><path d="M4 22V14a2 2 0 0 1 2-2h28a2 2 0 0 1 2 2v8"/><rect x="8" y="14" width="9" height="8" rx="1.5"/><rect x="23" y="14" width="9" height="8" rx="1.5"/><line x1="8" y1="32" x2="8" y2="36"/><line x1="32" y1="32" x2="32" y2="36"/></svg>`
};

/* ===== FILTROS — ICON CARDS ===== */
function buildFilters(data) {
  const counts = { todos: data.length };
  data.forEach(p => { counts[p.categoria] = (counts[p.categoria] || 0) + 1; });

  const wrap = document.getElementById('filters');
  if (!wrap) return;
  wrap.innerHTML = Object.entries(CAT_LABELS).map(([key, label]) => {
    const count = counts[key] || 0;
    if (key !== 'todos' && count === 0) return '';
    return `<button class="filter-cat-card${key === 'todos' ? ' active' : ''}" data-cat="${key}" onclick="applyFilter('${key}')">
      <div class="cat-icon">${CAT_ICONS[key] || ''}</div>
      <span class="cat-name">${label}</span>
    </button>`;
  }).join('');
}

/* ===== PRODUCT MODAL ===== */
function openProductModal(id) {
  if (!productos.length) return;
  const p = productos.find(x => x.id === id);
  if (!p) return;

  setEl('modal-cat',         formatCat(p.categoria));
  setEl('modal-name',        p.nombre);
  setEl('modal-price',       p.precio);
  setElHTML('modal-price-cuotas', `${LOGOS_12} 12 cuotas sin interés de <strong>${p.precio_cuotas}</strong>`);
  const old6 = document.getElementById('modal-price-cuotas6');
  if (old6) old6.remove();
  document.getElementById('modal-price-cuotas').insertAdjacentHTML('afterend',
    `<div class="modal-price-cuotas" id="modal-price-cuotas6">${LOGOS_6} 6 cuotas sin interés de <strong>${cuotas6(p.precio)}</strong></div>`);
  setEl('modal-price-promo',  `${p.precio_promo} efectivo / transferencia`);
  setEl('modal-desc',        p.descripcion);

  const waBtn = document.getElementById('modal-wa-btn');
  if (waBtn) waBtn.href = waLink(p.nombre);

  const imgs = p.imagenes || [];
  const mainImg = document.getElementById('modal-main-img');
  if (mainImg) { mainImg.src = imgs[0] || ''; mainImg.alt = p.nombre; }

  const thumbsWrap = document.getElementById('modal-thumbs');
  if (thumbsWrap) {
    if (imgs.length > 1) {
      thumbsWrap.innerHTML = imgs.map((src, i) =>
        `<div class="modal-thumb${i === 0 ? ' active' : ''}" onclick="switchModalImg(this,'${src}')">
          <img src="${src}" alt="${p.nombre}">
        </div>`).join('');
      thumbsWrap.style.display = 'flex';
    } else {
      thumbsWrap.innerHTML = '';
      thumbsWrap.style.display = 'none';
    }
  }

  const related = productos.filter(x => x.categoria === p.categoria && x.id !== p.id).slice(0, 4);
  const relSection = document.getElementById('modal-related');
  const relGrid    = document.getElementById('modal-related-grid');
  if (relGrid) {
    if (related.length) {
      if (relSection) relSection.style.display = '';
      relGrid.innerHTML = related.map(r => {
        const rImg   = r.imagenes && r.imagenes.length ? r.imagenes[0] : '';
        const rImgEl = rImg
          ? `<img src="${rImg}" alt="${r.nombre}" loading="lazy" onerror="handleImgError(this)">`
          : `<div class="img-placeholder"></div>`;
        return `
        <article class="product-card" onclick="openProductModal(${r.id})">
          <div class="product-card__img-wrap">
            ${rImgEl}
            <div class="product-card__img-overlay"><span>Ver detalle</span></div>
          </div>
          <div class="product-card__body">
            <div class="product-card__cat">${formatCat(r.categoria)}</div>
            <h3 class="product-card__name">${r.nombre}</h3>
            <p class="product-card__desc">${r.descripcion}</p>
            <div class="product-card__prices">
              <span class="price-main">${r.precio}</span>
              <span class="price-cuotas">12 cuotas de <strong>${r.precio_cuotas}</strong></span>
              <span class="price-promo"><strong>${r.precio_promo}</strong> efectivo</span>
            </div>
            <div class="product-card__footer">
              <a class="btn-wa" href="${waLink(r.nombre)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">
                ${SVG_WA_MD} Consultar
              </a>
            </div>
          </div>
        </article>`;
      }).join('');
    } else {
      if (relSection) relSection.style.display = 'none';
    }
  }

  const modal = document.getElementById('product-modal');
  if (modal) {
    modal.classList.add('open');
    modal.scrollTop = 0;
    document.body.style.overflow = 'hidden';
  }
  document.addEventListener('keydown', onModalKey);
}

function closeProductModal() {
  document.getElementById('product-modal')?.classList.remove('open');
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onModalKey);
}

function onModalKey(e) {
  if (e.key === 'Escape') closeProductModal();
}

function switchModalImg(el, src) {
  document.querySelectorAll('.modal-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  const mainImg = document.getElementById('modal-main-img');
  if (mainImg) mainImg.src = src;
}

/* ===== LIGHTBOX ===== */
let lightboxImgs = [];
let lightboxIdx  = 0;

function openLightbox(imgs, idx = 0) {
  lightboxImgs = imgs;
  lightboxIdx  = idx;
  const lb = document.getElementById('lightbox');
  if (!lb) return;
  lb.classList.add('open');
  showLightboxImg();
  document.addEventListener('keydown', onLbKey);
}

function showLightboxImg() {
  const img = document.getElementById('lb-img');
  const cap = document.getElementById('lb-caption');
  if (img) img.src = lightboxImgs[lightboxIdx];
  if (cap) cap.textContent = `${lightboxIdx + 1} / ${lightboxImgs.length}`;
}

function closeLightbox() {
  document.getElementById('lightbox')?.classList.remove('open');
  document.removeEventListener('keydown', onLbKey);
}

function lbNext() {
  lightboxIdx = (lightboxIdx + 1) % lightboxImgs.length;
  showLightboxImg();
}

function lbPrev() {
  lightboxIdx = (lightboxIdx - 1 + lightboxImgs.length) % lightboxImgs.length;
  showLightboxImg();
}

function onLbKey(e) {
  if (e.key === 'Escape')     closeLightbox();
  if (e.key === 'ArrowRight') lbNext();
  if (e.key === 'ArrowLeft')  lbPrev();
}

/* ===== DETAIL PAGE (producto.html) ===== */
function initDetailPage() {
  const params = new URLSearchParams(window.location.search);
  const id     = parseInt(params.get('id'), 10);
  if (!id || !productos.length) return;

  const p = productos.find(x => x.id === id);
  if (!p) { document.title = 'Producto no encontrado – Atriums Muebles'; return; }

  document.title = `${p.nombre} – Atriums Muebles`;

  setEl('detail-cat',   formatCat(p.categoria));
  setEl('detail-name',  p.nombre);
  setEl('detail-price', p.precio);
  setEl('detail-desc',  p.descripcion);

  const waBtn = document.getElementById('detail-wa-btn');
  if (waBtn) waBtn.href = waLink(p.nombre);

  const bc = document.getElementById('breadcrumb-product');
  if (bc) bc.textContent = p.nombre;

  const main      = document.getElementById('gallery-main-img');
  const thumbsWrap = document.getElementById('gallery-thumbs');
  const imgs = p.imagenes || [];

  if (imgs.length && main) {
    main.src = imgs[0];
    main.alt = p.nombre;
    main.onclick = () => openLightbox(imgs, 0);
  }

  if (thumbsWrap && imgs.length > 1) {
    thumbsWrap.innerHTML = imgs.map((src, i) => `
      <div class="detail-gallery__thumb${i === 0 ? ' active' : ''}" onclick="switchThumb(${i}, '${src}', this)">
        <img src="${src}" alt="${p.nombre} – foto ${i+1}">
      </div>`).join('');
  }

  const related = productos
    .filter(x => x.categoria === p.categoria && x.id !== p.id)
    .slice(0, 4);
  const relGrid = document.getElementById('related-grid');
  if (relGrid && related.length) {
    relGrid.innerHTML = related.map(r => {
      const img   = r.imagenes && r.imagenes.length ? r.imagenes[0] : '';
      const imgEl = img ? `<img src="${img}" alt="${r.nombre}" loading="lazy">` : `<div class="img-placeholder"></div>`;
      return `
      <article class="product-card">
        <div class="product-card__img-wrap" onclick="window.location='producto.html?id=${r.id}'">
          ${imgEl}
          <div class="product-card__img-overlay"><span>Ver detalle</span></div>
        </div>
        <div class="product-card__body">
          <div class="product-card__cat">${formatCat(r.categoria)}</div>
          <h3 class="product-card__name"><a href="producto.html?id=${r.id}">${r.nombre}</a></h3>
          <p class="product-card__desc">${r.descripcion}</p>
          <div class="product-card__footer">
            <span class="product-card__price">${r.precio}</span>
            <a class="btn-wa" href="${waLink(r.nombre)}" target="_blank" rel="noopener">
              ${SVG_WA_MD} Consultar
            </a>
          </div>
        </div>
      </article>`;
    }).join('');
  } else if (relGrid) {
    relGrid.closest('.related-section')?.remove();
  }
}

/* ===== UTILS ===== */
function setEl(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}
function setElHTML(id, html) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = html;
}

function switchThumb(idx, src, el) {
  document.querySelectorAll('.detail-gallery__thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  const main = document.getElementById('gallery-main-img');
  if (main) { main.src = src; lightboxIdx = idx; }
}

/* ===== HERO CAROUSEL ===== */
let heroIdx   = 0;
let heroTimer = null;
const HERO_COUNT = 3;

function heroGoTo(idx) {
  document.querySelectorAll('.hero-slide').forEach((s, i) => s.classList.toggle('active', i === idx));
  document.querySelectorAll('.hero-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
  heroIdx = idx;
  clearInterval(heroTimer);
  heroTimer = setInterval(() => heroSlide(1), 5500);
}

function heroSlide(dir) {
  heroGoTo((heroIdx + dir + HERO_COUNT) % HERO_COUNT);
}

/* ===== MOBILE MENU ===== */
function toggleMenu() {
  const nav = document.getElementById('mobile-nav');
  const tog = document.getElementById('menu-toggle');
  if (!nav) return;
  const open = nav.classList.toggle('open');
  if (tog) tog.setAttribute('aria-expanded', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

/* ===== CONFIG (hero + nosotros) ===== */
async function loadConfig() {
  try {
    const res = await fetch('data/config.json');
    if (!res.ok) return;
    const cfg = await res.json();

    // Hero slides
    const slides = cfg?.hero?.slides;
    if (slides && slides.length) {
      document.querySelectorAll('.hero-slide').forEach((el, i) => {
        const s = slides[i];
        if (!s) return;
        // Imagen de fondo
        if (s.imagen) {
          el.style.backgroundImage =
            `linear-gradient(rgba(0,0,0,0.52),rgba(0,0,0,0.52)),url('${s.imagen}')`;
          el.style.backgroundSize     = 'cover';
          el.style.backgroundPosition = 'center';
        }
        // Badge
        const badge = el.querySelector('.hero-badge');
        if (badge && s.badge) badge.textContent = s.badge;
        // Título
        const h1 = el.querySelector('h1');
        if (h1 && s.titulo) {
          const parts = s.titulo.split('\n');
          h1.innerHTML = parts.join('<br>');
        }
        // Subtítulo
        const p = el.querySelector('p');
        if (p && s.subtitulo) p.textContent = s.subtitulo;
      });
    }

    // Nosotros
    const nos = cfg?.nosotros;
    if (nos) {
      const h2 = document.querySelector('#nosotros h2');
      if (h2 && nos.titulo) h2.textContent = nos.titulo;
      const parrafos = document.querySelectorAll('.nosotros-text p');
      if (nos.texto1 && parrafos[0]) parrafos[0].textContent = nos.texto1;
      if (nos.texto2 && parrafos[1]) parrafos[1].textContent = nos.texto2;
    }
  } catch (e) {
    // config.json no existe o tiene error → el HTML estático queda como está
  }
}

/* ===== INIT ===== */
async function init() {
  try {
    const res = await fetch('data/productos.json');
    const todos  = await res.json();

    // Filtrar productos inactivos (activo: false los oculta)
    productos = todos.filter(p => p.activo !== false);
    productos.sort((a, b) => a.orden - b.orden);

    const isDetail = !!document.getElementById('detail-name');
    if (!isDetail) {
      buildFilters(productos);
      renderGrid(productos);
    } else {
      initDetailPage();
    }
  } catch (e) {
    console.error('Error cargando productos:', e);
  }
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('DOMContentLoaded', loadConfig);

/* ===== INIT HERO CAROUSEL + EVENTS ===== */
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('heroCarousel')) {
    heroTimer = setInterval(() => heroSlide(1), 5500);
  }
  document.getElementById('lightbox')?.addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
  });
  document.getElementById('product-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeProductModal();
  });

  // Drag-to-scroll for modal carousel
  const carousel = document.getElementById('modal-related-grid');
  if (carousel) {
    let isDown = false, startX, scrollLeft;
    carousel.addEventListener('mousedown', e => {
      isDown = true;
      startX = e.pageX - carousel.offsetLeft;
      scrollLeft = carousel.scrollLeft;
    });
    carousel.addEventListener('mouseleave', () => { isDown = false; });
    carousel.addEventListener('mouseup', () => { isDown = false; });
    carousel.addEventListener('mousemove', e => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - carousel.offsetLeft;
      carousel.scrollLeft = scrollLeft - (x - startX);
    });
  }
});
