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
  const details = document.getElementById('notif-details');
  if(details){
    details.addEventListener('toggle', ()=>{ if(details.open){ loadTray(); } });
    refreshUnreadBadge();
    setInterval(refreshUnreadBadge, 30000);
  }

  // Impersonation UX inside tray
  document.addEventListener('click', (e)=>{
    const toggle = document.getElementById('impersonate-btn');
    if(toggle && e.target === toggle){
      e.preventDefault(); e.stopPropagation();
      const box = document.getElementById('impersonate-box');
      const input = document.getElementById('impersonate-input');
      if(box && input){ box.classList.remove('hidden'); toggle.classList.add('hidden'); input.focus(); }
    }
  });

  async function fetchSuggestions(q){
    const res = await fetch(`/u/impersonation/suggest?q=${encodeURIComponent(q)}`, {credentials:'same-origin'});
    if(!res.ok) return [];
    return await res.json();
  }

  function renderSuggestions(list){
    const cont = document.getElementById('impersonate-suggestions');
    if(!cont) return;
    cont.innerHTML='';
    if(!list || list.length===0){ cont.classList.add('hidden'); return; }
    list.forEach(item=>{
      const el = createNode(`<button type="button" class="w-full text-left px-3 py-2 hover:bg-muted/50" data-email="${item.email}"><div class="font-medium text-workshop-brown">${item.name||item.email}</div><div class="text-xs text-muted-foreground">${item.email}</div></button>`);
      el.addEventListener('click', ()=>{
        const url = new URL(window.location.href);
        url.searchParams.set('_switch_user', item.email);
        window.location.assign(url.toString());
      });
      cont.appendChild(el);
    });
    cont.classList.remove('hidden');
  }

  const input = document.getElementById('impersonate-input');
  if(input){
    let lastQ=''; let timer=null;
    input.addEventListener('input', ()=>{
      const q=input.value.trim();
      if(q.length<2){ renderSuggestions([]); return; }
      lastQ=q;
      clearTimeout(timer);
      timer=setTimeout(async ()=>{
        if(lastQ!==q) return;
        try{ const data = await fetchSuggestions(q); renderSuggestions(data); }catch(_){ }
      }, 200);
    });
    input.addEventListener('keydown', (e)=>{
      if(e.key==='Escape'){
        const box=document.getElementById('impersonate-box');
        const toggle=document.getElementById('impersonate-btn');
        const cont=document.getElementById('impersonate-suggestions');
        if(box&&toggle){ box.classList.add('hidden'); toggle.classList.remove('hidden'); }
        if(cont){ cont.classList.add('hidden'); }
      }
    });
  }
});
