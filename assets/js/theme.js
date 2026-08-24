(function(){
  var THEMES = ['dark','medium','light'];
  var ICONS  = {dark:'dark_mode', medium:'brightness_medium', light:'light_mode'};

  function applyTheme(t){
    if (t === 'dark') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', t);
    var icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = ICONS[t];
    localStorage.setItem('ct-theme', t);
  }

  function cycleTheme(){
    var cur = localStorage.getItem('ct-theme') || 'dark';
    var next = THEMES[(THEMES.indexOf(cur) + 1) % THEMES.length];
    applyTheme(next);
  }

  applyTheme(localStorage.getItem('ct-theme') || 'dark');

  var btn = document.getElementById('btn-theme-toggle');
  if (btn) btn.addEventListener('click', cycleTheme);
})();
