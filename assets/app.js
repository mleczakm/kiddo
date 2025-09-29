import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

function $(sel){return document.querySelector(sel);} 
function createNode(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }

async function refreshUnreadBadge(){
  try{
    const res = await fetch('/u/notifications/unread-count', {credentials:'same-origin'});
    if(!res.ok) return;
    const data = await res.json();
    const b = $('#notif-badge');
    if(!b) return;
    const c = data.count||0;
    if(c>0){ b.textContent = c>9? '9+': String(c); b.classList.remove('hidden'); }
    else { b.classList.add('hidden'); b.textContent=''; }
  }catch(e){ /* ignore */ }
}

async function loadTray(){
  const tray = $('#notif-tray');
  const list = $('#notif-list');
  if(!tray || !list) return;
  list.innerHTML = '<div class="text-sm text-muted-foreground p-2">Loading…</div>';
  const res = await fetch('/u/notifications/tray', {credentials:'same-origin'});
  if(res.ok){ list.innerHTML = await res.text(); attachItemHandlers(); }
}

function toggleTray(){
  const tray = $('#notif-tray');
  if(!tray) return;
  const isHidden = tray.classList.contains('hidden');
  document.querySelectorAll('#notif-tray').forEach(el=>el.classList.add('hidden'));
  if(isHidden){ tray.classList.remove('hidden'); loadTray(); }
}

function attachItemHandlers(){
  document.querySelectorAll('#notif-list .notif-item').forEach(a=>{
    a.addEventListener('click', async (e)=>{
      const id = a.dataset.id; if(!id) return;
      try{ await fetch(`/u/notifications/${id}/read`, {method:'POST', credentials:'same-origin'}); refreshUnreadBadge(); }
      catch(_){ }
      // let the navigation happen
    });
  });
  document.querySelectorAll('#notif-list .notif-delete').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const id = btn.dataset.id; if(!id) return;
      try{ await fetch(`/u/notifications/${id}/delete`, {method:'POST', credentials:'same-origin'}); refreshUnreadBadge(); }
      catch(_){ }
      const row = btn.closest('[data-id]'); if(row) row.remove();
    });
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  const btn = $('#notif-btn');
  if(btn){
    btn.addEventListener('click', (e)=>{ e.stopPropagation(); toggleTray(); });
    document.addEventListener('click', (e)=>{
      const tray = $('#notif-tray');
      if(tray && !tray.contains(e.target) && e.target!==btn){ tray.classList.add('hidden'); }
    });
    refreshUnreadBadge();
    setInterval(refreshUnreadBadge, 30000);
  }
});
