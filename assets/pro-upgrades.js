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