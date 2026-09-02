#!/usr/bin/env node
'use strict';
// Genera favicons con el diseño combinado: C arc + circuit dots en tips + T centrada (todo cyan)
const zlib = require('zlib');
const fs   = require('fs');
const path = require('path');

// ── PNG ──────────────────────────────────────────────────────────
const CRC = (() => {
  const t = new Uint32Array(256);
  for (let i = 0; i < 256; i++) { let c = i; for (let j = 0; j < 8; j++) c = (c&1)?0xEDB88320^(c>>>1):c>>>1; t[i]=c; }
  return t;
})();
function crc32(b) { let c=0xFFFFFFFF; for (let i=0;i<b.length;i++) c=CRC[(c^b[i])&0xFF]^(c>>>8); return (c^0xFFFFFFFF)>>>0; }
function chunk(tp,data){ const lb=Buffer.alloc(4);lb.writeUInt32BE(data.length); const tb=Buffer.from(tp); const cb=Buffer.alloc(4);cb.writeUInt32BE(crc32(Buffer.concat([tb,data]))); return Buffer.concat([lb,tb,data,cb]); }
function png(w,h,rgba){
  const hdr=Buffer.alloc(13); hdr.writeUInt32BE(w,0); hdr.writeUInt32BE(h,4); hdr[8]=8; hdr[9]=6;
  const raw=Buffer.alloc(h*(1+w*4));
  for(let y=0;y<h;y++){ raw[y*(1+w*4)]=0; for(let x=0;x<w;x++){ const s=(y*w+x)*4,d=y*(1+w*4)+1+x*4; raw[d]=rgba[s];raw[d+1]=rgba[s+1];raw[d+2]=rgba[s+2];raw[d+3]=rgba[s+3]; } }
  return Buffer.concat([Buffer.from([137,80,78,71,13,10,26,10]),chunk('IHDR',hdr),chunk('IDAT',zlib.deflateSync(raw,{level:9})),chunk('IEND',Buffer.alloc(0))]);
}

// ── ICO ──────────────────────────────────────────────────────────
function ico(entries){ // [{size,pngBuf}]
  const n=entries.length, hdrSz=6+n*16;
  const hdr=Buffer.alloc(hdrSz); hdr.writeUInt16LE(0,0); hdr.writeUInt16LE(1,2); hdr.writeUInt16LE(n,4);
  let off=hdrSz, parts=[hdr];
  entries.forEach(({size,pngBuf},i)=>{
    const e=hdr.subarray(6+i*16,6+(i+1)*16);
    const s=size>=256?0:size; e[0]=s;e[1]=s;e[2]=0;e[3]=0;
    e.writeUInt16LE(1,4); e.writeUInt16LE(32,6);
    e.writeUInt32LE(pngBuf.length,8); e.writeUInt32LE(off,12);
    off+=pngBuf.length; parts.push(pngBuf);
  });
  return Buffer.concat(parts);
}

// ── Rasterizer ───────────────────────────────────────────────────
// Geometría (ViewBox 340×340):
//   C arc: centro (275,170) radio 92, desde (219,97) hasta (219,243) — arco GRANDE (lado izquierdo)
//   T crossbar: (118,135)→(196,135) sw=9
//   T stem:     (157,144)→(157,205) sw=9
//   Circuit top: dot(219,97,r6) → line→(249,67) → dot(r3.5) → line→(276,67)
//   Circuit bot: dot(219,243,r6) → line→(249,273) → dot(r3.5) → line→(276,273)
// Colores: bg #04080d, todo lo demás cyan #50d2ff

function render(size) {
  const VB = 340, sc = size / VB;
  const pix = new Uint8Array(size * size * 4);

  // Fondo oscuro
  for (let i = 0; i < pix.length; i += 4) { pix[i]=4;pix[i+1]=8;pix[i+2]=13;pix[i+3]=255; }

  // Rounded-rect alpha mask (rx=44 vb → scaled)
  const RX = 44 * sc;
  const HC = size / 2;
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const ex = Math.max(0, Math.abs(x+0.5-HC) - (HC-RX));
      const ey = Math.max(0, Math.abs(y+0.5-HC) - (HC-RX));
      const d  = Math.sqrt(ex*ex+ey*ey);
      if (d > RX) { const i=(y*size+x)*4; pix[i]=pix[i+1]=pix[i+2]=pix[i+3]=0; }
      else if (d > RX-1) {
        const i=(y*size+x)*4; const m=RX-d;
        pix[i+3]=Math.round(255*m);
      }
    }
  }

  // Cyan
  const CR=80, CG=210, CB=255;

  // Alpha composite over current pixel
  function bld(x, y, r, g, b, a) {
    if (a<=0||x<0||y<0||x>=size||y>=size) return;
    x=x|0; y=y|0;
    const i=(y*size+x)*4;
    const ba=pix[i+3]/255;
    if (ba<=0) { pix[i]=r;pix[i+1]=g;pix[i+2]=b;pix[i+3]=Math.min(255,Math.round(a*255)); return; }
    const oa=a+ba*(1-a);
    pix[i]  =Math.round((r*a+pix[i]  *ba*(1-a))/oa);
    pix[i+1]=Math.round((g*a+pix[i+1]*ba*(1-a))/oa);
    pix[i+2]=Math.round((b*a+pix[i+2]*ba*(1-a))/oa);
    pix[i+3]=Math.min(255,Math.round(oa*255));
  }

  // Filled circle con glow opcional
  function circle(cx, cy, r, gr, gb, gg, glowSW) {
    cx*=sc; cy*=sc; r*=sc; glowSW=(glowSW||0)*sc;
    const m=r+glowSW+1.5;
    for (let py=Math.floor(cy-m);py<=Math.ceil(cy+m);py++) {
      for (let px=Math.floor(cx-m);px<=Math.ceil(cx+m);px++) {
        const dist=Math.sqrt((px+0.5-cx)**2+(py+0.5-cy)**2);
        const cov=Math.max(0,Math.min(1,r-dist+0.5));
        if (cov>0) bld(px,py,gr,gb,gg,cov);
        else if (glowSW>0&&dist<r+glowSW) { const ga=0.5*((1-(dist-r)/glowSW)**2); bld(px,py,gr,gb,gg,ga*0.8); }
      }
    }
  }

  // Line segment con glow
  function line(x1,y1,x2,y2,sw,lr,lg,lb,glowSW) {
    x1*=sc;y1*=sc;x2*=sc;y2*=sc;sw*=sc;glowSW=(glowSW||0)*sc;
    const len=Math.sqrt((x2-x1)**2+(y2-y1)**2); if(len<0.001)return;
    const dx=(x2-x1)/len, dy=(y2-y1)/len;
    const nx=dy, ny=-dx; // perpendicular
    const m=sw/2+glowSW+1.5;
    const x0m=Math.min(x1,x2)-m, x1m=Math.max(x1,x2)+m;
    const y0m=Math.min(y1,y2)-m, y1m=Math.max(y1,y2)+m;
    for (let py=Math.floor(y0m);py<=Math.ceil(y1m);py++) {
      for (let px=Math.floor(x0m);px<=Math.ceil(x1m);px++) {
        const t=Math.max(0,Math.min(len,(px+0.5-x1)*dx+(py+0.5-y1)*dy));
        const perp=Math.abs((px+0.5-x1)*nx+(py+0.5-y1)*ny);
        const df=perp-sw/2;
        if (df<0.5) bld(px,py,lr,lg,lb,Math.min(1,-df+0.5));
        else if (glowSW>0&&df<glowSW) { const ga=0.45*((1-df/glowSW)**1.5); bld(px,py,lr,lg,lb,ga); }
      }
    }
  }

  // C arc
  // centro (275,170) radio 92; arco GRANDE = lado izquierdo = |angle| > P1a
  // P1 = (219,97): angle = atan2(97-170, 219-275) ≈ -2.2333 rad → |P1a| ≈ 2.2333
  const ARC_CX=275, ARC_CY=170, ARC_R=92, ARC_SW=10;
  const P1A=Math.abs(Math.atan2(97-170,219-275)); // ≈ 2.2333

  function arc(glow) {
    const cx=ARC_CX*sc, cy=ARC_CY*sc, R=ARC_R*sc;
    const sw=(glow?ARC_SW+glow:ARC_SW)*sc;
    const alpha=glow ? 0.3/(glow/6) : 1;
    const outer=R+sw/2+1.5, inner=R-sw/2-1.5;
    for (let py=Math.floor(cy-outer);py<=Math.ceil(cy+outer);py++) {
      for (let px=Math.floor(cx-outer);px<=Math.ceil(cx+outer);px++) {
        if(px<0||py<0||px>=size||py>=size) continue;
        // 2×2 supersample
        let cov=0;
        for(let sy=0;sy<2;sy++) for(let sx=0;sx<2;sx++) {
          const spx=px+(sx+0.25)/2, spy=py+(sy+0.25)/2;
          const ddx=spx-cx, ddy=spy-cy;
          const d=Math.sqrt(ddx*ddx+ddy*ddy);
          const ang=Math.atan2(ddy,ddx);
          if(Math.abs(ang)<=P1A) continue; // in gap
          const df=Math.abs(d-R)-sw/2;
          cov+=Math.max(0,Math.min(1,-df+0.5));
        }
        cov/=4;
        if(cov>0.001) bld(px,py,CR,CG,CB,glow?cov*alpha:cov);
      }
    }
  }

  // Dibujar: primero glow (capas más anchas), luego stroke principal
  arc(16); // glow capa exterior
  arc(8);  // glow intermedio
  arc(0);  // stroke principal

  // T (todo cyan, centrada dentro del arco)
  line(118,135,196,135, 9, CR,CG,CB, 5); // crossbar
  line(157,144,157,205, 9, CR,CG,CB, 5); // stem

  // Circuit endpoints — tip superior (219,97)
  circle(219, 97,  6,   CR,CG,CB, 5);
  line(219,97, 249,67,  2.2, CR,CG,CB, 0);
  circle(249, 67,  3.5, CR,CG,CB, 0);
  line(249,67, 276,67,  1.6, 68,178,217, 0); // opacity ~85%

  // Circuit endpoints — tip inferior (219,243)
  circle(219, 243, 6,   CR,CG,CB, 5);
  line(219,243, 249,273, 2.2, CR,CG,CB, 0);
  circle(249, 273, 3.5, CR,CG,CB, 0);
  line(249,273, 276,273, 1.6, 68,178,217, 0);

  return pix;
}

// ── Generar archivos ─────────────────────────────────────────────
const OUT = path.join(__dirname, 'assets', 'img');
const SIZES = [16,32,48,180,192,512];

console.log('Renderizando íconos combinados (C + circuit + T)...');
const rendered = {};
for (const s of SIZES) { process.stdout.write(`  ${s}px... `); rendered[s]=render(s); console.log('OK'); }

[['favicon-16x16.png',16],['favicon-32x32.png',32],['apple-touch-icon.png',180],['android-chrome-192x192.png',192],['android-chrome-512x512.png',512]].forEach(([name,sz])=>{
  fs.writeFileSync(path.join(OUT,name), png(sz,sz,rendered[sz]));
  console.log(`  ✓ ${name}`);
});

fs.writeFileSync(path.join(OUT,'favicon.ico'), ico([
  {size:16,pngBuf:png(16,16,rendered[16])},
  {size:32,pngBuf:png(32,32,rendered[32])},
  {size:48,pngBuf:png(48,48,rendered[48])},
]));
console.log('  ✓ favicon.ico (16+32+48px)');
console.log('\nListo.');
