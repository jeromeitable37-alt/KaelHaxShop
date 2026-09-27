/* KAELHAX password visibility controls */
(function(){
  function initPasswordToggles(){
    document.querySelectorAll('[data-password-toggle]').forEach(function(button){
      if(button.dataset.passwordToggleReady==='1') return;
      button.dataset.passwordToggleReady='1';
      button.addEventListener('click',function(){
        const targetId=button.getAttribute('data-password-toggle');
        const input=targetId ? document.getElementById(targetId) : null;
        if(!input) return;
        const visible=input.type==='text';
        input.type=visible?'password':'text';
        button.textContent=visible?'Show':'Hide';
        button.setAttribute('aria-label',visible?'Show password':'Hide password');
      });
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initPasswordToggles);
  else initPasswordToggles();
})();
