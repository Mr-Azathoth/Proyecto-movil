(function () {
  'use strict';

  async function compressImg(file, maxPx, quality) {
    return new Promise(function (resolve) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.width, h = img.height;
        if (w > maxPx || h > maxPx) {
          if (w >= h) { h = Math.round(h * maxPx / w); w = maxPx; }
          else        { w = Math.round(w * maxPx / h); h = maxPx; }
        }
        var c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        c.toBlob(resolve, 'image/jpeg', quality);
      };
      img.src = url;
    });
  }

  function insertNodeAtCursor(el, node) {
    el.focus();
    var sel = window.getSelection();
    if (sel.rangeCount && el.contains(sel.anchorNode)) {
      var r = sel.getRangeAt(0);
      r.deleteContents();
      r.insertNode(node);
      r.setStartAfter(node);
      r.collapse(true);
      sel.removeAllRanges();
      sel.addRange(r);
    } else {
      el.appendChild(node);
    }
  }

  window.setupImagePaste = function (el, uploadFn) {
    el.addEventListener('paste', async function (e) {
      var items = Array.from(e.clipboardData ? e.clipboardData.items : []);
      var imgItem = items.find(function (i) { return i.type.startsWith('image/'); });
      if (!imgItem) return;
      e.preventDefault();
      var file = imgItem.getAsFile();
      if (!file) return;

      var ph = document.createElement('span');
      ph.className = 'ce-uploading';
      ph.textContent = 'Subiendo imagen…';
      insertNodeAtCursor(el, ph);

      try {
        var blob = await compressImg(file, 1200, 0.82);
        var fd = new FormData();
        fd.append('imagen', blob, 'paste.jpg');
        var r = await uploadFn(fd);
        var j = await r.json();
        if (!j.ok) throw new Error(j.msg || 'Error al subir');
        var img = document.createElement('img');
        img.src = j.data.url;
        ph.replaceWith(img);
      } catch (err) {
        ph.textContent = '⚠ ' + err.message;
      }
    });
  };
}());
