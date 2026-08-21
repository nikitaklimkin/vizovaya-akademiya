(function () {
      var ICON = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="PATH"></path></svg>';
      var BTN = 'width:44px;height:44px;padding:0;border-radius:50%;border:1.5px solid rgba(15,118,110,0.3);background:#fff;color:rgb(15,118,110);display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(20,40,60,0.08);transition:opacity .2s ease;';
      function build() {
              var rail = document.querySelector('.reviews-rail');
              if (!rail) { setTimeout(build, 500); return; }
              if (document.getElementById('rvNav')) return;
              if (rail.scrollWidth - rail.clientWidth < 40) { setTimeout(build, 1200); return; }
              var bar = document.createElement('div');
              bar.id = 'rvNav';
                    bar.style.cssText = 'display:flex;justify-content:center;align-items:center;gap:16px;margin:4px 0 16px;';
              var prev = document.createElement('button');
              prev.type = 'button';
              prev.setAttribute('aria-label', 'Предыдущие отзывы');
              prev.style.cssText = BTN;
              prev.innerHTML = ICON.replace('PATH', 'M15 5l-7 7 7 7');
              var next = document.createElement('button');
              next.type = 'button';
              next.setAttribute('aria-label', 'Следующие отзывы');
              next.style.cssText = BTN;
              next.innerHTML = ICON.replace('PATH', 'M9 5l7 7-7 7');
              bar.appendChild(prev);
              bar.appendChild(next);
                    rail.parentNode.insertBefore(bar, rail);
              function step(dir) {
                        var d = Math.round(rail.clientWidth * 0.72);
                    var from = rail.scrollLeft;
                    var to = Math.max(0, Math.min(from + dir * d, rail.scrollWidth - rail.clientWidth));
                          var t0 = Date.now(); var snapWas = rail.style.scrollSnapType; rail.style.scrollSnapType = 'none';
                    function frame() {
                                    var p = Math.min(1, (Date.now() - t0) / 620);
                                    var e = 1 - Math.pow(1 - p, 3);
                            rail.scrollLeft = from + (to - from) * e;
                                    if (p < 1) { requestAnimationFrame(frame); } else { rail.style.scrollSnapType = snapWas; }
                    }
                    frame();
              }
              prev.addEventListener('click', function () { step(-1); });
              next.addEventListener('click', function () { step(1); });
              function sync() {
                        var max = rail.scrollWidth - rail.clientWidth - 4;
                        prev.style.opacity = rail.scrollLeft <= 4 ? '0.3' : '1';
                        next.style.opacity = rail.scrollLeft >= max ? '0.3' : '1';
              }
              rail.addEventListener('scroll', sync, { passive: true });
              window.addEventListener('resize', sync);
              sync();
      }
      if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', build);
      } else {
              build();
      }
})();
