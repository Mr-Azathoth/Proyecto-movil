// Cliente HTTP y fixtures para las pruebas de integracion contra beta.
// Variables opcionales: TEST_HOST (por defecto beta.centrotec.cl), TEST_PORT (8081), PHP_BIN.
const http = require('http');
const fs   = require('fs');
const path = require('path');

const HOST     = process.env.TEST_HOST || 'beta.centrotec.cl';
const PORT     = parseInt(process.env.TEST_PORT || '8081', 10);
const ADDR     = process.env.TEST_ADDR || '127.0.0.1'; // direccion a la que se conecta (el Host sigue siendo beta.centrotec.cl)
const BETA_DIR = path.resolve(__dirname, '..').replace(/\\/g, '/');
const PHP_BIN  = process.env.PHP_BIN || 'E:/Servicios/centrotec/bin/php/php.exe';

function fixtures() {
  const f = path.join(__dirname, '.creds.json');
  if (!fs.existsSync(f)) throw new Error('Falta tests/.creds.json: ejecuta "php tests/reset.php" y luego "php tests/seed.php".');
  return JSON.parse(fs.readFileSync(f, 'utf8'));
}
const { creds, ids } = fixtures();

class Client {
  constructor() { this.cookies = {}; this.csrf = ''; }
  req(method, path, { body, form, json } = {}) {
    return new Promise((resolve, reject) => {
      const headers = { Host: HOST };
      const c = Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; ');
      if (c) headers.Cookie = c;
      let payload = null;
      if (json !== undefined) { payload = JSON.stringify(json); headers['Content-Type'] = 'application/json'; headers['X-CSRF-Token'] = this.csrf; }
      else if (form) { payload = new URLSearchParams(form).toString(); headers['Content-Type'] = 'application/x-www-form-urlencoded'; }
      if (payload) headers['Content-Length'] = Buffer.byteLength(payload);
      const r = http.request({ host: ADDR, port: PORT, path, method, headers }, res => {
        let d = ''; res.on('data', x => d += x);
        res.on('end', () => {
          (res.headers['set-cookie'] || []).forEach(s => { const [kv] = s.split(';'); const i = kv.indexOf('='); this.cookies[kv.slice(0, i)] = kv.slice(i + 1); });
          let j = null; try { j = JSON.parse(d); } catch (e) {}
          resolve({ status: res.statusCode, text: d, json: j, headers: res.headers });
        });
      });
      r.on('error', reject);
      if (payload) r.write(payload);
      r.end();
    });
  }
  async login(user) {
    let r = await this.req('GET', '/ingresar.php');
    const m = r.text.match(/name="csrf_token"[^>]*value="([^"]+)"/) || r.text.match(/value="([^"]+)"[^>]*name="csrf_token"/);
    if (!m) throw new Error('sin csrf en login');
    r = await this.req('POST', '/ingresar.php', { form: { csrf_token: m[1], user, pass: creds[user] } });
    if (r.status !== 302) throw new Error('login fallo ' + user + ' ' + r.status);
    r = await this.req('GET', '/app.php');
    const t = r.text.match(/data-csrf="([^"]+)"/);
    if (!t) throw new Error('sin csrf de sesion ' + user);
    this.csrf = t[1];
    return this;
  }
  get(p)       { return this.req('GET', p); }
  put(p, j)    { return this.req('PUT', p, { json: j }); }
  post(p, j)   { return this.req('POST', p, { json: j }); }
  postForm(p, f) { return this.req('POST', p, { form: { ...f, csrf_token: this.csrf } }); }
}


module.exports = { Client, creds, ids, fixtures, HOST, PORT, BETA_DIR, PHP_BIN };
