// ================= CUSTODIA — shared behaviors =================

document.addEventListener('DOMContentLoaded', () => {
  initNavToggle();
  initFavButtons();
  initCountdowns();
  initTabs();
  initProfileTabs();
  initSettingsNav();
  initSwitches();
  initThemeOpts();
  initGallery();
  initTypeToggle();
  initDropzone();
  initChat();
  initPasswordToggles();
  initSearchFilterChips();
  initThreadListMobile();
});

/* ---------- toast ---------- */
function showToast(msg){
  let t = document.querySelector('.toast');
  if(!t){
    t = document.createElement('div');
    t.className = 'toast glass';
    t.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg><span class="msg"></span>`;
    document.body.appendChild(t);
  }
  t.querySelector('.msg').textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(()=> t.classList.remove('show'), 2600);
}
window.showToast = showToast;

/* ---------- mobile nav ---------- */
function initNavToggle(){
  const btn = document.querySelector('.nav-toggle');
  const links = document.querySelector('.nav-links');
  if(!btn || !links) return;
  btn.addEventListener('click', ()=>{
    const open = links.style.display === 'flex';
    links.style.display = open ? 'none' : 'flex';
    if(!open){
      links.style.cssText = 'display:flex;position:absolute;top:72px;left:0;right:0;flex-direction:column;background:rgba(8,8,13,0.97);backdrop-filter:blur(20px);padding:12px 20px 20px;border-bottom:1px solid var(--border);';
    }
  });
}

/* ---------- favorite hearts ---------- */
function initFavButtons(){
  document.querySelectorAll('.fav-btn').forEach(btn=>{
    btn.addEventListener('click', e=>{
      e.stopPropagation();
      btn.classList.toggle('active');
      showToast(btn.classList.contains('active') ? 'Добавлено в избранное' : 'Убрано из избранного');
    });
  });
}

/* ---------- countdown timers ---------- */
function initCountdowns(){
  document.querySelectorAll('[data-countdown]').forEach(el=>{
    const endsIn = parseInt(el.getAttribute('data-countdown'), 10); // seconds from now
    const end = Date.now() + endsIn * 1000;
    const render = ()=>{
      let diff = Math.max(0, end - Date.now());
      const d = Math.floor(diff/86400000); diff -= d*86400000;
      const h = Math.floor(diff/3600000); diff -= h*3600000;
      const m = Math.floor(diff/60000); diff -= m*60000;
      const s = Math.floor(diff/1000);
      if(el.dataset.style === 'blocks'){
        const set = (sel,val)=>{ const n = el.querySelector(sel); if(n) n.textContent = String(val).padStart(2,'0'); };
        set('.u-d', d); set('.u-h', h); set('.u-m', m); set('.u-s', s);
      } else {
        el.querySelector('.cd-text').textContent = d > 0 ? `${d}д ${h}ч` : `${h}ч ${m}м ${s}с`;
      }
    };
    render();
    setInterval(render, 1000);
  });
}

/* ---------- product page tabs ---------- */
function initTabs(){
  document.querySelectorAll('.tabs').forEach(tabGroup=>{
    const buttons = tabGroup.querySelectorAll('.tab-btn');
    buttons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        buttons.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const target = btn.getAttribute('data-tab');
        document.querySelectorAll('.tab-panel').forEach(p=>{
          p.classList.toggle('active', p.getAttribute('data-panel') === target);
        });
      });
    });
  });
}

/* ---------- gallery ---------- */
function initGallery(){
  const main = document.querySelector('.gallery-main');
  const thumbs = document.querySelectorAll('.gallery-thumbs button');
  if(!main || !thumbs.length) return;
  thumbs.forEach(t=>{
    t.addEventListener('click', ()=>{
      thumbs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      main.innerHTML = t.querySelector('svg').outerHTML;
    });
  });
}

/* ---------- profile tabs ---------- */
function initProfileTabs(){
  const tabs = document.querySelectorAll('.ptab');
  if(!tabs.length) return;
  tabs.forEach(t=>{
    t.addEventListener('click', ()=>{
      tabs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      const target = t.getAttribute('data-ptab');
      document.querySelectorAll('.profile-panel').forEach(p=>{
        p.classList.toggle('active', p.getAttribute('data-panel') === target);
      });
    });
  });
}

/* ---------- settings nav ---------- */
function initSettingsNav(){
  const items = document.querySelectorAll('.settings-nav a[data-target]');
  if(!items.length) return;
  items.forEach(a=>{
    a.addEventListener('click', e=>{
      e.preventDefault();
      items.forEach(x=>x.classList.remove('active'));
      a.classList.add('active');
      document.querySelectorAll('.settings-panel').forEach(p=>{
        p.style.display = (p.id === a.dataset.target) ? 'block' : 'none';
      });
    });
  });
}

/* ---------- switches ---------- */
function initSwitches(){
  document.querySelectorAll('.switch input').forEach(sw=>{
    sw.addEventListener('change', ()=>{
      showToast(sw.checked ? 'Включено' : 'Выключено');
    });
  });
}

/* ---------- theme options ---------- */
function initThemeOpts(){
  document.querySelectorAll('.theme-opt').forEach(opt=>{
    opt.addEventListener('click', ()=>{
      document.querySelectorAll('.theme-opt').forEach(o=>o.classList.remove('active'));
      opt.classList.add('active');
    });
  });
}

/* ---------- listing type toggle ---------- */
function initTypeToggle(){
  const options = document.querySelectorAll('.type-option');
  const auctionFields = document.querySelector('.auction-fields');
  if(!options.length) return;
  options.forEach(opt=>{
    opt.addEventListener('click', ()=>{
      options.forEach(o=>o.classList.remove('active'));
      opt.classList.add('active');
      if(auctionFields){
        auctionFields.classList.toggle('show', opt.dataset.type === 'auction');
      }
    });
  });
}

/* ---------- dropzone ---------- */
function initDropzone(){
  const zone = document.querySelector('.dropzone');
  const grid = document.querySelector('.preview-grid');
  if(!zone) return;
  const input = zone.querySelector('input[type=file]');
  const addThumb = ()=>{
    const div = document.createElement('div');
    div.className = 'preview-thumb';
    const hues = [250,45,190,320];
    const hue = hues[Math.floor(Math.random()*hues.length)];
    div.innerHTML = `<svg viewBox="0 0 100 100"><defs><linearGradient id="g${Date.now()+Math.random()}" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="hsl(${hue},70%,55%)"/><stop offset="1" stop-color="hsl(${hue+40},60%,35%)"/>
      </linearGradient></defs><rect width="100" height="100" fill="url(#g${Date.now()})"/></svg>
      <button aria-label="Удалить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6L6 18"/></svg></button>`;
    div.querySelector('button').addEventListener('click', e=>{ e.stopPropagation(); div.remove(); });
    grid.appendChild(div);
  };
  zone.addEventListener('click', ()=> addThumb());
  ['dragenter','dragover'].forEach(ev=> zone.addEventListener(ev, e=>{ e.preventDefault(); zone.classList.add('drag'); }));
  ['dragleave','drop'].forEach(ev=> zone.addEventListener(ev, e=>{ e.preventDefault(); zone.classList.remove('drag'); }));
  zone.addEventListener('drop', e=>{ const n = (e.dataTransfer.files||[]).length || 1; for(let i=0;i<n;i++) addThumb(); });
}

/* ---------- chat ---------- */
function initChat(){
  const form = document.querySelector('.chat-input-row');
  const body = document.querySelector('.chat-body');
  if(!form || !body) return;
  const input = form.querySelector('input');
  const send = ()=>{
    const val = input.value.trim();
    if(!val) return;
    const row = document.createElement('div');
    row.className = 'msg-row mine';
    row.innerHTML = `<div><div class="bubble">${escapeHtml(val)}</div><span class="msg-time">только что</span></div>`;
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
    input.value = '';
    setTimeout(()=>{
      const reply = document.createElement('div');
      reply.className = 'msg-row';
      reply.innerHTML = `<img class="msg-avatar" src="${avatarSvg('#8b7fe8')}"><div><div class="bubble">Принято — отвечу в течение часа.</div><span class="msg-time">только что</span></div>`;
      body.appendChild(reply);
      body.scrollTop = body.scrollHeight;
    }, 1200);
  };
  form.querySelector('.send-btn').addEventListener('click', send);
  input.addEventListener('keydown', e=>{ if(e.key === 'Enter') send(); });

  document.querySelectorAll('.thread-item').forEach(item=>{
    item.addEventListener('click', ()=>{
      document.querySelectorAll('.thread-item').forEach(i=>i.classList.remove('active'));
      item.classList.add('active');
      const name = item.querySelector('b').textContent;
      const header = document.querySelector('.chat-header b');
      if(header) header.textContent = name;
      const list = document.querySelector('.thread-list');
      if(list && window.innerWidth <= 860) list.classList.remove('show');
    });
  });
}
function escapeHtml(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function avatarSvg(color){
  const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 60 60'><rect width='60' height='60' fill='${color}'/><circle cx='30' cy='24' r='11' fill='rgba(255,255,255,0.85)'/><ellipse cx='30' cy='54' rx='19' ry='14' fill='rgba(255,255,255,0.85)'/></svg>`;
  return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
}

function initThreadListMobile(){
  const toggle = document.querySelector('[data-thread-toggle]');
  const list = document.querySelector('.thread-list');
  if(!toggle || !list) return;
  toggle.addEventListener('click', ()=> list.classList.add('show'));
}

/* ---------- password visibility ---------- */
function initPasswordToggles(){
  document.querySelectorAll('.toggle-pass').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const input = btn.parentElement.querySelector('input');
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });
}

/* ---------- filter chips ---------- */
function initSearchFilterChips(){
  document.querySelectorAll('.filter-bar').forEach(bar=>{
    bar.querySelectorAll('.chip').forEach(chip=>{
      chip.addEventListener('click', ()=>{
        bar.querySelectorAll('.chip').forEach(c=>c.classList.remove('active'));
        chip.classList.add('active');
      });
    });
  });
}

/* ---------- generic form submit intercept (no backend) ---------- */
document.addEventListener('submit', e=>{
  const form = e.target;
  if(form.matches('form')){
    e.preventDefault();
    const action = form.getAttribute('data-success') || 'Готово';
    showToast(action);
    if(form.dataset.redirect){
      setTimeout(()=> window.location.href = form.dataset.redirect, 700);
    }
  }
});
