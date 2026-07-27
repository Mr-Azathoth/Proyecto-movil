(function () {
  'use strict';

  async function compressImg(file, maxPx, quality) {
    // Attempt 1: createImageBitmap (works with most clipboard formats including BMP)
    var bitmap = null;
    try {
      bitmap = await createImageBitmap(file);
    } catch (_) { /* fall through to Image element */ }

    if (bitmap) {
      var w = bitmap.width, h = bitmap.height;
      if (w > maxPx || h > maxPx) {
        if (w >= h) { h = Math.round(h * maxPx / w); w = maxPx; }
        else        { w = Math.round(w * maxPx / h); h = maxPx; }
      }
      var c = document.createElement('canvas');
      c.width = w; c.height = h;
      c.getContext('2d').drawImage(bitmap, 0, 0, w, h);
      bitmap.close();
      return new Promise(function (resolve, reject) {
        c.toBlob(function (blob) {
          if (!blob) { reject(new Error('No se pudo comprimir la imagen')); return; }
          resolve(blob);
        }, 'image/jpeg', quality);
      });
    }

    // Attempt 2: HTMLImageElement fallback
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      var timer = setTimeout(function () {
        URL.revokeObjectURL(url);
        reject(new Error('Tiempo de espera al cargar imagen agotado'));
      }, 15000);
      img.onload = function () {
        clearTimeout(timer);
        URL.revokeObjectURL(url);
        var w = img.width, h = img.height;
        if (w > maxPx || h > maxPx) {
          if (w >= h) { h = Math.round(h * maxPx / w); w = maxPx; }
          else        { w = Math.round(w * maxPx / h); h = maxPx; }
        }
        var c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        c.toBlob(function (blob) {
          if (!blob) { reject(new Error('No se pudo comprimir la imagen')); return; }
          resolve(blob);
        }, 'image/jpeg', quality);
      };
      img.onerror = function () {
        clearTimeout(timer);
        URL.revokeObjectURL(url);
        reject(new Error('No se pudo leer la imagen del portapapeles'));
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
