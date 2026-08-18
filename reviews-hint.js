(function () {
    var SHOWN_KEY = 'rvHintDone';
    function init() {
          var rail = document.querySelector('.reviews-rail');
          if (!rail) { setTimeout(init, 500); return; }
          if (rail.scrollWidth - rail.clientWidth < 40) return;
          var host = rail.parentElement;
          if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
          var hint = document.createElement('div');
          hint.setAttribute('aria-hidden', 'true');
          hint.style.cssText = 'position:absolute;z-index:6;right:10px;top:34%;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.7);box-shadow:0 3px 12px rgba(20,40,60,0.22);display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:1;transition:opacity 0.3s ease;animation:rvNudge 1.6s ease-in-out infinite';
          hint.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="rgb(15,118,110)" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"></path></svg>';
          var css = document.createElement('style');
          css.textContent = '@keyframes rvNudge{0%,100%{transform:translateX(0)}50%{transform:translateX(5px)}}';
          document.head.appendChild(css);
          host.appendChild(hint);
          var done = false;
          function hide() {
                  if (done) return;
                  done = true;
                  hint.style.opacity = '0';
                  setTimeout(function () { if (hint.parentNode) hint.parentNode.removeChild(hint); }, 350);
                  rail.removeEventListener('scroll', hide);
                  rail.removeEventListener('touchstart', hide);
                  try { sessionStorage.setItem(SHOWN_KEY, '1'); } catch (e) {}
          }
          try { if (sessionStorage.getItem(SHOWN_KEY)) { hide(); return; } } catch (e) {}
          rail.addEventListener('scroll', hide, { passive: true });
          rail.addEventListener('touchstart', hide, { passive: true });
    }
    if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', init);
    } else {
          init();
    }
})();
