// Humo de los flujos de reparacion con reservas por sucursal.
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
const INV = fixtures().inv;
const fs=require('fs');
let pass=0,fail=0; const ok=(n,c,x='')=>{c?pass++:fail++;console.log((c?'PASS ':'FAIL ')+n+(c?'':'  -> '+x))};
const st=async(cl,id,suc)=>(await cl.get('/api/inventario.php?sucursal='+suc)).json.data.find(x=>x.id_repuesto===id);
const nueva=(cl,e)=>cl.postForm('/api/reparaciones.php',{nombre_cliente:'S',telefono_cliente:'+56955555555',dano_ingreso:'x',marca_ingreso:'X',modelo_ingreso:'Y',tipo_ingreso:'Telefono',...e});
(async()=>{
 const adm=await new Client().login('adminA'),tc=await new Client().login('tecCentro'),tn=await new Client().login('tecNorte');
 const P=INV['Pantalla A54'];
 const s0=await st(adm,P,ids.cen);
 let r=await nueva(tc,{id_repuesto_usado:P}); const id=r.json.data.id;
 ok('crear con repuesto reserva 1 en Centro',(await st(adm,P,ids.cen)).cantidad_reservada===s0.cantidad_reservada+1);
 r=await tn.put('/api/reparaciones.php',{id,status:'En Reparacion'});
 ok('tecNorte no edita reparacion de Centro (403)',r.status===403,r.status);
 r=await tc.put('/api/reparaciones.php',{id,status:'En Reparacion',obs:'x'});
 ok('tecCentro cambia estado sin tocar stock',r.json?.ok&&(await st(adm,P,ids.cen)).cantidad===s0.cantidad,r.text.slice(0,80));
 r=await adm.put('/api/reparaciones.php',{id,id_sucursal:ids.nor});
 ok('traslado con reserva pendiente se bloquea (dentro de la transaccion)',r.json?.ok===false&&/reservad/i.test(r.json.msg),r.text.slice(0,120));
 ok('...y la reparacion sigue en Centro con su reserva',(await st(adm,P,ids.cen)).cantidad_reservada===s0.cantidad_reservada+1);
 r=await tc.put('/api/reparaciones.php',{id,status:'Reparado'});
 const s1=await st(adm,P,ids.cen);
 ok('Reparado descuenta 1 de Centro y libera la reserva',r.json?.data?.stock_descontado===1&&s1.cantidad===s0.cantidad-1&&s1.cantidad_reservada===s0.cantidad_reservada,JSON.stringify(s1));
 r=await adm.put('/api/reparaciones.php',{id,id_sucursal:ids.nor});
 ok('ya consumido, el traslado SI se permite',r.json?.ok===true,r.text.slice(0,100));
 r=await adm.req('DELETE','/api/reparaciones.php',{json:{id}});
 ok('eliminar no devuelve stock ya consumido',r.json?.ok&&(await st(adm,P,ids.cen)).cantidad===s0.cantidad-1,'');
 await adm.put('/api/inventario.php',{id:P,cantidad_delta:1,id_sucursal:ids.cen});
 // asignar/cambiar repuesto en PUT
 r=await nueva(tc,{}); const id2=r.json.data.id;
 r=await tc.put('/api/reparaciones.php',{id:id2,id_repuesto_usado:P});
 ok('asignar repuesto inicial via PUT reserva en la sucursal de la reparacion',r.json?.ok&&(await st(adm,P,ids.cen)).cantidad_reservada===s0.cantidad_reservada+1,r.text.slice(0,100));
 r=await tc.put('/api/reparaciones.php',{id:id2,id_repuesto_usado:0});
 ok('quitarlo libera la reserva',r.json?.ok&&(await st(adm,P,ids.cen)).cantidad_reservada===s0.cantidad_reservada,r.text.slice(0,100));
 await adm.req('DELETE','/api/reparaciones.php',{json:{id:id2}});
 console.log(`\n${pass} OK, ${fail} fallos`);process.exit(fail?1:0);
})().catch(e=>{console.error('ERR',e.stack.split('\n').slice(0,3).join('\n'));process.exit(2)});
