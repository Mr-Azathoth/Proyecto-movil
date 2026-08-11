/* ─── WebGL Shader ─── */
(function(){
  var canvas=document.getElementById('gl-canvas');
  if(!canvas)return;
  var gl=canvas.getContext('webgl')||canvas.getContext('experimental-webgl');
  if(!gl)return;
  var vert='attribute vec2 a_pos;void main(){gl_Position=vec4(a_pos,0.,1.);}';
  var frag='precision highp float;\n'+
    'uniform vec2 u_res;uniform vec2 u_mouse;uniform float u_t;\n'+
    'float hash(vec2 p){p=fract(p*vec2(234.34,435.345));p+=dot(p,p+34.23);return fract(p.x*p.y);}\n'+
    'float noise(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);return mix(mix(hash(i),hash(i+vec2(1,0)),f.x),mix(hash(i+vec2(0,1)),hash(i+vec2(1,1)),f.x),f.y);}\n'+
    'float fbm(vec2 p){float v=0.,a=.5;for(int i=0;i<6;i++){v+=a*noise(p);p=p*2.1+vec2(1.7,9.2);a*=.5;}return v;}\n'+
    'void main(){\n'+
    '  vec2 uv=gl_FragCoord.xy/u_res;uv.y=1.-uv.y;\n'+
    '  vec2 m=u_mouse/u_res;m.y=1.-m.y;\n'+
    '  float dist=length(uv-m);float mg=exp(-dist*dist*6.)*.55;\n'+
    '  float n1=fbm(uv*2.2+u_t*.038);\n'+
    '  float n2=fbm(uv*1.8+n1*.9+vec2(u_t*.025,-u_t*.042));\n'+
    '  float n3=fbm(uv*2.8+n2*.55-u_t*.018);\n'+
    '  vec3 base=vec3(.010,.018,.036);vec3 teal=vec3(.022,.12,.24);vec3 cyan=vec3(.04,.28,.44);vec3 mtint=vec3(.08,.48,.72);\n'+
    '  vec3 col=mix(base,teal,n2*.85);col=mix(col,cyan,max(0.,n3-.38)*1.6);col+=mtint*mg;\n'+
    '  vec2 vig=uv*2.-1.;col*=1.-dot(vig*.62,vig*.62);\n'+
    '  float sc=sin(gl_FragCoord.y*1.2)*.012+.988;col*=sc;\n'+
    '  gl_FragColor=vec4(col,1.);}';
  function mkShader(t,s){var sh=gl.createShader(t);gl.shaderSource(sh,s);gl.compileShader(sh);return sh;}
  var prog=gl.createProgram();
  gl.attachShader(prog,mkShader(gl.VERTEX_SHADER,vert));
  gl.attachShader(prog,mkShader(gl.FRAGMENT_SHADER,frag));
  gl.linkProgram(prog);gl.useProgram(prog);
  var buf=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,buf);
  gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),gl.STATIC_DRAW);
  var aPos=gl.getAttribLocation(prog,'a_pos');gl.enableVertexAttribArray(aPos);gl.vertexAttribPointer(aPos,2,gl.FLOAT,false,0,0);
  var uRes=gl.getUniformLocation(prog,'u_res');
  var uMouse=gl.getUniformLocation(prog,'u_mouse');
  var uT=gl.getUniformLocation(prog,'u_t');
  var mx=window.innerWidth/2,my=window.innerHeight/2,tmx=mx,tmy=my;
  document.addEventListener('mousemove',function(e){tmx=e.clientX;tmy=e.clientY;});
  document.addEventListener('touchmove',function(e){if(e.touches[0]){tmx=e.touches[0].clientX;tmy=e.touches[0].clientY;}},{passive:true});
  function resize(){canvas.width=canvas.offsetWidth;canvas.height=canvas.offsetHeight;gl.viewport(0,0,canvas.width,canvas.height);}
  new ResizeObserver(resize).observe(canvas);resize();
  function loop(t){mx+=(tmx-mx)*.06;my+=(tmy-my)*.06;gl.uniform2f(uRes,canvas.width,canvas.height);gl.uniform2f(uMouse,mx,my);gl.uniform1f(uT,t*.001);gl.drawArrays(gl.TRIANGLE_STRIP,0,4);requestAnimationFrame(loop);}
  requestAnimationFrame(loop);
})();

/* ─── Tabs ─── */
document.querySelectorAll('.cue-tab').forEach(function(tab){
  tab.addEventListener('click',function(){
    var t=tab.dataset.tab;
    document.querySelectorAll('.cue-tab').forEach(function(x){x.classList.remove('active');x.setAttribute('aria-selected','false');});
    document.querySelectorAll('.cue-panel').forEach(function(p){p.classList.remove('active');});
    tab.classList.add('active');tab.setAttribute('aria-selected','true');
    var p=document.getElementById('tab-'+t);if(p)p.classList.add('active');
  });
});

/* ─── Reveal ─── */
var obs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');obs.unobserve(e.target);}});
},{threshold:0.1});
document.querySelectorAll('.reveal').forEach(function(el){obs.observe(el);});

/* ─── Counter ─── */
var cobs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(!e.isIntersecting)return;
    var el=e.target,target=parseInt(el.dataset.count),dur=1100,s=performance.now();
    var tick=function(now){var p=Math.min((now-s)/dur,1),ease=1-Math.pow(1-p,3);el.textContent=Math.round(ease*target);if(p<1)requestAnimationFrame(tick);else el.textContent=target+(target===98?'%+':'+');};
    requestAnimationFrame(tick);cobs.unobserve(el);
  });
},{threshold:0.4});
document.querySelectorAll('[data-count]').forEach(function(el){cobs.observe(el);});
