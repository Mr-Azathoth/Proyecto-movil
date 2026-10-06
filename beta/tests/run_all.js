// Ejecuta todas las suites contra beta, reiniciando los datos de prueba antes de cada grupo.
// Uso: node tests/run_all.js        (desde la carpeta beta/)
// Al terminar deja datos de prueba nuevos y claves nuevas en tests/.creds.json.
const { execFileSync } = require('child_process');
const path = require('path');

const BETA = path.resolve(__dirname, '..');
const PHP  = process.env.PHP_BIN || 'E:/Servicios/centrotec/bin/php/php.exe';
const run  = (cmd, args) => { try { return { out: execFileSync(cmd, args, { cwd: BETA, encoding: 'utf8', maxBuffer: 1 << 26 }), code: 0 }; }
                              catch (e) { return { out: (e.stdout || '') + (e.stderr || ''), code: e.status || 1 }; } };
const fresh = () => { run(PHP, ['tests/reset.php']); const r = run(PHP, ['tests/seed.php']); if (r.code) throw new Error('seed fallo: ' + r.out); };

// stock.js y ui_inventario.js comparten estado (la segunda parte del resultado de la primera).
const grupos = [['suc.js'], ['stock.js', 'ui_inventario.js'], ['fix.js'], ['tel.js'], ['smoke.js']];
let totalOk = 0, totalFail = 0, errores = 0;

for (const g of grupos) {
  fresh();
  for (const suite of g) {
    const r = run(process.execPath, [path.join('tests', suite)]);
    const m = r.out.match(/(\d+) OK, (\d+) fallos/);
    const ok = m ? +m[1] : 0, fail = m ? +m[2] : 0;
    totalOk += ok; totalFail += fail;
    if (!m || r.code) errores++;
    console.log(`${suite.padEnd(18)} ${m ? `${ok} OK, ${fail} fallos` : 'ERROR de ejecucion'}`);
    if (fail || !m) console.log(r.out.split('\n').filter(l => /^FAIL|ERR/.test(l)).join('\n') || r.out.slice(-600));
  }
}
fresh();
console.log(`\nTotal: ${totalOk} OK, ${totalFail} fallos${errores ? `, ${errores} suite(s) con error` : ''}`);
process.exit(totalFail || errores ? 1 : 0);
