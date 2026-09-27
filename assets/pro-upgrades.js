/* KAELHAX Pro upgrade JS — defensive, no dependency on chat code */
(function(){
  function ready(){
    const search=document.querySelector('[data-pro-order-search]');
    const filter=document.querySelector('[data-pro-order-filter]');
    const rows=[...document.querySelectorAll('[data-pro-order-list] .pro-order-row')];
    if(search||filter){
      const apply=()=>{
        const q=(search?.value||'').trim().toLowerCase();
        const status=filter?.value||'all';
        rows.forEach(row=>{
          const hay=(row.getAttribute('data-search')||'').toLowerCase();
          const matchText=!q||hay.includes(q);
          const matchStatus=status==='all'||row.dataset.status===status;
          row.style.display=(matchText&&matchStatus)?'flex':'none';
        });
      };
      search?.addEventListener('input',apply);
      filter?.addEventListener('change',apply);
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',ready); else ready();
})();
(function(){
  function makeToast(){
    let el=document.getElementById('proNotifyToast');
    if(el) return el;
    el=document.createElement('div');
    el.id='proNotifyToast';
    el.className='pro-notify-toast';
    el.innerHTML='<div class="pro-notify-icon">🔔</div><div class="pro-notify-copy"><strong></strong><span></span><a href="index.php?page=my-orders">Open My Orders →</a></div><button type="button" aria-label="Close">×</button>';
    document.body.appendChild(el);
    el.querySelector('button').addEventListener('click',()=>el.classList.remove('show'));
    return el;
  }
  function showBuyerNotice(order){
    const toast=makeToast();
    const status=String(order.status||'pending').toUpperCase();
    toast.querySelector('strong').textContent='Order status updated';
    toast.querySelector('span').textContent=(order.product||'Your order')+' is now '+status+(order.amount?' • '+order.amount:'');
    toast.classList.remove('show'); void toast.offsetWidth; toast.classList.add('show');
    clearTimeout(window.__proNotifyTimer);
    window.__proNotifyTimer=setTimeout(()=>toast.classList.remove('show'),8000);
    try{ if('vibrate' in navigator) navigator.vibrate([70,40,90]); }catch(_){}
  }
  function initBuyerStatusWatcher(){
    if(typeof isAdminPortal!=='undefined' && isAdminPortal) return;
    const pageMatch=/(^|&)page=/.test(location.search) || location.search==='';
    if(!pageMatch) return;

    const storageKey='kaelhax_buyer_order_status_v1';
    let known={};
    try{const saved=JSON.parse(localStorage.getItem(storageKey)||'{}'); if(saved&&typeof saved==='object') known=saved;}catch(_){}

    let initialized=false;
    async function poll(){
      try{
        const url=new URL('index.php',window.location.href);
        url.searchParams.set('action','buyer_order_status_poll');
        const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}});
        if(response.status===401) return;
        if(!response.ok) return;
        const data=await response.json();
        if(!data||!data.ok) return;
        const orders=Array.isArray(data.orders)?data.orders:[];
        for(const order of orders){
          const id=String(order.id||''); if(!id) continue;
          const status=String(order.status||'pending').toLowerCase();
          const stamp=String(order.updated_at||order.created_at||'');
          const previous=known[id];
          known[id]={status,stamp};
          if(initialized && previous && previous.status!==status) showBuyerNotice(order);
        }
        localStorage.setItem(storageKey,JSON.stringify(known));
        initialized=true;
      }catch(_){}
    }

    poll();
    window.setInterval(poll,8000);
  }

  function initHistoryFilter(){
    const search=document.querySelector('[data-pro-history-search]');
    const filter=document.querySelector('[data-pro-history-filter]');
    const rows=[...document.querySelectorAll('[data-pro-history-row]')];
    if(!rows.length) return;
    const apply=()=>{
      const q=(search?.value||'').trim().toLowerCase();
      const status=filter?.value||'all';
      rows.forEach(row=>{
        const hay=(row.getAttribute('data-search')||'').toLowerCase();
        row.style.display=(!q||hay.includes(q))&&(status==='all'||row.dataset.status===status)?'':'none';
      });
    };
    search?.addEventListener('input',apply);
    filter?.addEventListener('change',apply);
  }

  function boot(){
    initHistoryFilter();
    initBuyerStatusWatcher();
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot); else boot();
})();
