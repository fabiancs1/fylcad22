/* ================================================================
   FYLCAD — Motor Topográfico v8
   Renderizado profesional tipo Civil 3D / AutoCAD Map 3D
================================================================ */
(function () {
"use strict";

/* ──────────────────────────────────────────────────────────────
   SIMBOLOGÍA TOPOGRÁFICA PROFESIONAL
   Colores y formas por código de levantamiento
────────────────────────────────────────────────────────────── */
const SIM = {
  "tn":                  { color:"#1A1A1A", r:2.0, sym:"dot",    capa:"Terreno Natural"   },
  "tn bv":               { color:"#2A2A2A", r:2.0, sym:"dot",    capa:"Terreno Natural"   },
  "tn arbol":            { color:"#1A7A1A", r:4.0, sym:"arbol",  capa:"Vegetación"        },
  "tn desplane":         { color:"#7B3F00", r:2.8, sym:"diamond",capa:"Terreno Natural"   },
  "tn pata talu":        { color:"#9B4500", r:3.2, sym:"tri_up", capa:"Talud"             },
  "talu rio":            { color:"#1565C0", r:3.0, sym:"dot",    capa:"Talud Río"         },
  "talu rio y bv":       { color:"#1565C0", r:3.0, sym:"dot",    capa:"Talud Río"         },
  "corona talu rio":     { color:"#0D47A1", r:3.5, sym:"tri_dn", capa:"Corona Talud"      },
  "lin":                 { color:"#CC0000", r:3.2, sym:"cross",  capa:"Línea Banca Vía"   },
  "lin bv":              { color:"#CC0000", r:3.0, sym:"cross",  capa:"Línea Banca Vía"   },
  "lin bv cruce agua":   { color:"#00AAAA", r:4.0, sym:"diamond",capa:"Cruce Hídrico"     },
  "lin bv poste hierro": { color:"#B05A00", r:4.0, sym:"circle", capa:"Postes"            },
  "paramento":           { color:"#CC0000", r:3.5, sym:"square", capa:"Paramento"         },
  "paramento bv":        { color:"#CC0000", r:3.2, sym:"square", capa:"Paramento"         },
  "poste":               { color:"#B05A00", r:4.5, sym:"circle", capa:"Postes"            },
  "poste hierro":        { color:"#B05A00", r:4.5, sym:"circle", capa:"Postes"            },
  "gns1":                { color:"#7700CC", r:6.0, sym:"gps",    capa:"Control GPS"       },
  "gns2":                { color:"#7700CC", r:6.0, sym:"gps",    capa:"Control GPS"       },
  "_":                   { color:"#555",    r:2.0, sym:"dot",    capa:"Otro"              },
};
function getSim(d){ return SIM[(d||"").toLowerCase().trim()] || SIM["_"]; }

/* ──────────────────────────────────────────────────────────────
   GRADIENTE HIPSOMÉTRICO (verde→amarillo→naranja→marrón)
────────────────────────────────────────────────────────────── */
const HIPSO_STOPS = [
  [0.00, [195,230,170]],
  [0.20, [220,242,155]],
  [0.40, [250,230,100]],
  [0.60, [230,175, 55]],
  [0.80, [190,125, 45]],
  [1.00, [148, 88, 38]],
];
function hipsoColor(t, a=1) {
  let i=0;
  while (i < HIPSO_STOPS.length-1 && HIPSO_STOPS[i+1][0] < t) i++;
  const lo=HIPSO_STOPS[i], hi=HIPSO_STOPS[Math.min(i+1,HIPSO_STOPS.length-1)];
  const f = lo[0]===hi[0] ? 0 : (t-lo[0])/(hi[0]-lo[0]);
  const r = ~~(lo[1][0]+(hi[1][0]-lo[1][0])*f);
  const g = ~~(lo[1][1]+(hi[1][1]-lo[1][1])*f);
  const b = ~~(lo[1][2]+(hi[1][2]-lo[1][2])*f);
  return `rgba(${r},${g},${b},${a})`;
}

/* ──────────────────────────────────────────────────────────────
   ALGORITMO DELAUNAY (Bowyer-Watson)
────────────────────────────────────────────────────────────── */
function inCircum(ax,ay,bx,by,cx,cy,px,py) {
  const D = 2*(ax*(by-cy)+bx*(cy-ay)+cx*(ay-by));
  if (Math.abs(D)<1e-10) return false;
  const ux = ((ax*ax+ay*ay)*(by-cy)+(bx*bx+by*by)*(cy-ay)+(cx*cx+cy*cy)*(ay-by))/D;
  const uy = ((ax*ax+ay*ay)*(cx-bx)+(bx*bx+by*by)*(ax-cx)+(cx*cx+cy*cy)*(bx-ax))/D;
  return Math.hypot(px-ux,py-uy) < Math.hypot(ax-ux,ay-uy)+1e-8;
}
function delaunay(pts) {
  const n=pts.length; if(n<3) return [];
  let x0=Infinity,y0=Infinity,x1=-Infinity,y1=-Infinity;
  for(const p of pts){x0=Math.min(x0,p.x);y0=Math.min(y0,p.y);x1=Math.max(x1,p.x);y1=Math.max(y1,p.y);}
  const dm=Math.max(x1-x0,y1-y0)*3, mx=(x0+x1)/2, my=(y0+y1)/2;
  const sup=[{x:mx-dm*2,y:my-dm},{x:mx,y:my+dm*2},{x:mx+dm*2,y:my-dm}];
  const all=[...pts,...sup];
  let tris=[{a:n,b:n+1,c:n+2}];
  for(let i=0;i<n;i++){
    const p=all[i]; const bad=[];
    for(const t of tris){
      const a=all[t.a],b=all[t.b],c=all[t.c];
      if(inCircum(a.x,a.y,b.x,b.y,c.x,c.y,p.x,p.y)) bad.push(t);
    }
    const poly=[];
    for(const t of bad) for(const [e0,e1] of [[t.a,t.b],[t.b,t.c],[t.c,t.a]])
      if(!bad.some(u=>u!==t&&((u.a===e0||u.b===e0||u.c===e0)&&(u.a===e1||u.b===e1||u.c===e1)))) poly.push([e0,e1]);
    tris=tris.filter(t=>!bad.includes(t));
    for(const e of poly) tris.push({a:e[0],b:e[1],c:i});
  }
  return tris.filter(t=>t.a<n&&t.b<n&&t.c<n);
}

/* ──────────────────────────────────────────────────────────────
   CURVAS DE NIVEL (Marching Lines sobre TIN)
────────────────────────────────────────────────────────────── */
function buildIso(pts, tris, levels) {
  const iso={};
  for(const z of levels){
    const segs=[];
    for(const t of tris){
      const v=[pts[t.a],pts[t.b],pts[t.c]], cr=[];
      for(const [i,j] of [[0,1],[1,2],[2,0]]){
        const a=v[i],b=v[j];
        if((a.z<z&&b.z>=z)||(b.z<z&&a.z>=z)){
          const f=(z-a.z)/(b.z-a.z);
          cr.push({x:a.x+(b.x-a.x)*f, y:a.y+(b.y-a.y)*f});
        }
      }
      if(cr.length===2) segs.push(cr);
    }
    iso[z]=segs;
  }
  return iso;
}

/* ──────────────────────────────────────────────────────────────
   DOM refs
────────────────────────────────────────────────────────────── */
const CVS      = document.getElementById("visor3D");
const CTX      = CVS.getContext("2d", {alpha:false});
const dropZone = document.getElementById("dropZone");
const fileInp  = document.getElementById("fileInput");
const fileInfo = document.getElementById("fileInfo");
const fileNameEl = document.getElementById("fileName");
const fileSizeEl = document.getElementById("fileSize");
const btnProc  = document.getElementById("btnProcesar");
const emptyEl  = document.getElementById("emptyState");
const loadEl   = document.getElementById("loadingOverlay");
const statusEl = document.getElementById("viewerStatus");
const coordEl  = document.getElementById("coordReadout");
const coordTxt = document.getElementById("coordText");
const zLegEl   = document.getElementById("zLegend");
const hoverLbl = document.getElementById("puntoHoverLabel");

/* ──────────────────────────────────────────────────────────────
   ESTADO
────────────────────────────────────────────────────────────── */
let PTS=[], ORIG=[], TRIS=[], ISO={}, NIV=[];
let CX=0, CY=0, CZ=0, SCL=1;
let ZMIN=0, ZMAX=1;
let MODO="2D";
let ZOOM=1, PX=0, PY=0, AX=0.5, AY=-0.4;
let DRAG={on:false,x:0,y:0,btn:0};
let MACT=null;   // métricas actuales
let IDX={};      // índice N° → punto
let SEL_DESC=new Set();   // descripciones seleccionadas para filtro
let RESH=[];     // puntos resaltados

const OPT={hipso:true, curvas:true, npts:true, cotas:true, codigos:false, tin:false};

/* ──────────────────────────────────────────────────────────────
   RESIZE
────────────────────────────────────────────────────────────── */
function resize(){
  CVS.width  = CVS.parentElement.clientWidth;
  CVS.height = CVS.parentElement.clientHeight;
}
window.addEventListener("resize",()=>{resize();draw();});
resize();

/* ──────────────────────────────────────────────────────────────
   DROP / FILE
────────────────────────────────────────────────────────────── */
dropZone.addEventListener("dragover",  e=>{e.preventDefault();dropZone.classList.add("drag-over");});
dropZone.addEventListener("dragleave", ()=>dropZone.classList.remove("drag-over"));
dropZone.addEventListener("drop", e=>{
  e.preventDefault(); dropZone.classList.remove("drag-over");
  const f=e.dataTransfer.files[0]; if(f){fileInp.files=e.dataTransfer.files;setFile(f);}
});
dropZone.addEventListener("click", ()=>fileInp.click());
fileInp.addEventListener("change", ()=>{ if(fileInp.files[0]) setFile(fileInp.files[0]); });
function setFile(f){
  fileNameEl.textContent=f.name; fileSizeEl.textContent=fmtBytes(f.size);
  fileInfo.style.display="flex"; btnProc.disabled=false;
}

/* ──────────────────────────────────────────────────────────────
   PROCESAR CSV
────────────────────────────────────────────────────────────── */
document.getElementById("formCSV").addEventListener("submit",async e=>{
  e.preventDefault();
  if(!fileInp.files[0]) return;
  loadEl.style.display="flex"; emptyEl.style.display="none";
  const fd=new FormData(); fd.append("archivo",fileInp.files[0]);
  try{
    const r=await fetch("proyecto.php",{method:"POST",body:fd});
    const d=await r.json();
    loadEl.style.display="none";
    if(d.error){toast(d.error,"error");return;}
    if(!d.length){toast("Sin puntos válidos","error");return;}
    PTS=d; ORIG=[...d]; rebuildIdx(); init();
  }catch(e){
    loadEl.style.display="none"; toast("Error: "+e,"error");
  }
});

/* ──────────────────────────────────────────────────────────────
   INICIALIZAR
────────────────────────────────────────────────────────────── */
function init(){
  ZMIN=Math.min(...PTS.map(p=>p.z));
  ZMAX=Math.max(...PTS.map(p=>p.z));
  CX=PTS.reduce((s,p)=>s+p.x,0)/PTS.length;
  CY=PTS.reduce((s,p)=>s+p.y,0)/PTS.length;
  CZ=PTS.reduce((s,p)=>s+p.z,0)/PTS.length;
  const mxD=Math.max(...PTS.map(p=>Math.hypot(p.x-CX,p.y-CY)));
  SCL = mxD>0 ? Math.min(CVS.width,CVS.height)*0.42/mxD : 1;

  statusEl.textContent="Calculando triangulación TIN...";
  setTimeout(()=>{
    TRIS=delaunay(PTS.map(p=>({x:p.x,y:p.y})));

    // Niveles: equidistancia ~1m, max 25 curvas
    const eq = Math.max(0.25, (ZMAX-ZMIN)/22);
    const b  = Math.ceil(ZMIN/eq)*eq;
    NIV=[];
    for(let z=b; z<=ZMAX; z+=eq) NIV.push(+z.toFixed(4));

    ISO=buildIso(PTS,TRIS,NIV);
    resetVista();

    // Leyenda elevación
    buildZLegend();
    zLegEl.style.display="flex";
    coordEl.style.display="block";

    showPanels();
    buildDescTags();
    const m=calcMetrics(PTS);
    MACT=m;
    renderMetrics(m);
    updateCotLink(m);
    fillTable(PTS);

    const eq2=((ZMAX-ZMIN)/(NIV.length+1)).toFixed(3);
    statusEl.textContent=`${PTS.length} pts · ${TRIS.length} △ TIN · ${NIV.length} curvas (Eq=${eq2}m) · desnivel ${(ZMAX-ZMIN).toFixed(3)}m`;
    statusEl.classList.add("active");
    draw();
  },20);
}

function resetVista(){
  ZOOM=1; PX=0; PY=0;
  if(MODO==="3D"){AX=0.50;AY=-0.38;}
}

/* ──────────────────────────────────────────────────────────────
   LEYENDA ELEVACIÓN (gradiente canvas)
────────────────────────────────────────────────────────────── */
function buildZLegend(){
  const bar=document.getElementById("zLegBar");
  if(!bar) return;
  const grad=document.createElement("canvas");
  grad.width=1; grad.height=100;
  const gctx=grad.getContext("2d");
  for(let i=0;i<100;i++){
    gctx.fillStyle=hipsoColor(1-i/100);
    gctx.fillRect(0,i,1,1);
  }
  bar.style.background=`url(${grad.toDataURL()})`;
  bar.style.backgroundSize="100% 100%";
  const mn=document.getElementById("zLegMin");
  const mx=document.getElementById("zLegMax");
  if(mn) mn.textContent=ZMIN.toFixed(2)+"m";
  if(mx) mx.textContent=ZMAX.toFixed(2)+"m";
}

/* ──────────────────────────────────────────────────────────────
   PROYECCIÓN
────────────────────────────────────────────────────────────── */
function proj(p){
  const x=(p.x-CX)*SCL, y=(p.y-CY)*SCL, z=(p.z-CZ)*SCL;
  if(MODO==="2D"){
    return {sx: CVS.width/2+x*ZOOM+PX, sy: CVS.height/2-y*ZOOM+PY, d:z};
  }
  const x1= x*Math.cos(AY)+z*Math.sin(AY);
  const z1=-x*Math.sin(AY)+z*Math.cos(AY);
  const y2= y*Math.cos(AX)-z1*Math.sin(AX);
  const z2= y*Math.sin(AX)+z1*Math.cos(AX);
  const fov=900, pr=fov/(fov+z2+280);
  return {sx:CVS.width/2+x1*ZOOM*pr+PX, sy:CVS.height/2-y2*ZOOM*pr+PY, d:z2};
}
const tnorm = z=>(z-ZMIN)/(ZMAX-ZMIN||1);

/* ──────────────────────────────────────────────────────────────
   DRAW PRINCIPAL
────────────────────────────────────────────────────────────── */
function draw(){
  CTX.clearRect(0,0,CVS.width,CVS.height);

  if(MODO==="2D"){
    // Fondo papel topográfico auténtico
    CTX.fillStyle="#F4F0E6";
    CTX.fillRect(0,0,CVS.width,CVS.height);
    drawGrid2D();
    if(OPT.hipso)  drawHipso2D();
    if(OPT.tin)    drawTINlines();
    if(OPT.curvas) drawContours();
    drawSymbols();
    if(OPT.npts||OPT.cotas||OPT.codigos) drawLabels();
    drawHighlights();
    drawScaleBar();
    drawNorthArrow();
    drawTitleBlock();
    drawLegend();
  } else {
    CTX.fillStyle="#060A12";
    CTX.fillRect(0,0,CVS.width,CVS.height);
    drawGrid3D();
    drawAxes3D();
    if(OPT.hipso)  drawHipso3D();
    if(OPT.tin)    drawTINlines();
    if(OPT.curvas) drawContours();
    drawSymbols();
    if(OPT.npts||OPT.cotas||OPT.codigos) drawLabels();
    drawHighlights();
  }
}

/* ──────────────────────────────────────────────────────────────
   GRID UTM 2D
────────────────────────────────────────────────────────────── */
function drawGrid2D(){
  const mp=SCL*ZOOM;
  const steps=[1,2,5,10,20,25,50,100,200,500,1000];
  const paso_m=steps.find(s=>s*mp>=60)||1000;
  const pp=paso_m*mp;
  const ox=CVS.width/2+PX, oy=CVS.height/2+PY;

  // Líneas de cuadrícula tenues
  CTX.strokeStyle="rgba(100,130,180,0.15)";
  CTX.lineWidth=0.6;
  for(let x=((ox%pp)+pp)%pp; x<CVS.width; x+=pp){
    CTX.beginPath(); CTX.moveTo(x,0); CTX.lineTo(x,CVS.height); CTX.stroke();
  }
  for(let y=((oy%pp)+pp)%pp; y<CVS.height; y+=pp){
    CTX.beginPath(); CTX.moveTo(0,y); CTX.lineTo(CVS.width,y); CTX.stroke();
  }

  // Cruces de cuadrícula UTM (+ pequeños)
  if(pp>70){
    CTX.strokeStyle="rgba(0,0,128,0.30)";
    CTX.lineWidth=0.8;
    for(let x=((ox%pp)+pp)%pp; x<CVS.width; x+=pp){
      for(let y=((oy%pp)+pp)%pp; y<CVS.height; y+=pp){
        CTX.beginPath();
        CTX.moveTo(x-5,y); CTX.lineTo(x+5,y);
        CTX.moveTo(x,y-5); CTX.lineTo(x,y+5);
        CTX.stroke();
      }
    }

    // Etiquetas de coordenadas UTM
    CTX.font="8px 'DM Mono',monospace";
    CTX.fillStyle="rgba(0,0,128,0.50)";
    for(let x=((ox%pp)+pp)%pp; x<CVS.width; x+=pp){
      const utmE=CX+(x-ox)/mp;
      CTX.save(); CTX.translate(x+3,CVS.height-18);
      CTX.fillText(utmE.toFixed(0)+"E",0,0);
      CTX.restore();
    }
    for(let y=((oy%pp)+pp)%pp; y<CVS.height-20; y+=pp){
      const utmN=CY-(y-oy)/mp;
      CTX.save(); CTX.translate(4,y-2);
      CTX.fillText(utmN.toFixed(0)+"N",0,0);
      CTX.restore();
    }
  }
}

/* ──────────────────────────────────────────────────────────────
   HIPSOMÉTRICO 2D (relleno TIN + hillshade)
────────────────────────────────────────────────────────────── */
function drawHipso2D(){
  // Ordenar triángulos por Z media (painter's algorithm)
  const sorted=[...TRIS].sort((a,b)=>
    (PTS[a.a].z+PTS[a.b].z+PTS[a.c].z)-(PTS[b.a].z+PTS[b.b].z+PTS[b.c].z));

  for(const t of sorted){
    const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c];
    const pa=proj(a),pb=proj(b),pc=proj(c);
    CTX.beginPath();
    CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy);
    CTX.closePath();
    CTX.fillStyle=hipsoColor(tnorm((a.z+b.z+c.z)/3), 0.80);
    CTX.fill();
  }

  // Hillshade suave (normal del triángulo vs luz del NW)
  CTX.globalAlpha=0.14;
  for(const t of TRIS){
    const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c];
    const ux=b.x-a.x,uy=b.y-a.y,uz=b.z-a.z;
    const vx=c.x-a.x,vy=c.y-a.y,vz=c.z-a.z;
    const nx=uy*vz-uz*vy, ny=uz*vx-ux*vz, nz=ux*vy-uy*vx;
    const nm=Math.hypot(nx,ny,nz)||1;
    // Luz desde noroeste
    const sh=Math.max(0, ((-nx+ny)*0.5+nz*0.8)/nm);
    const pa=proj(a),pb=proj(b),pc=proj(c);
    CTX.beginPath();
    CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy);
    CTX.closePath();
    CTX.fillStyle=sh>0.5?`rgba(255,255,230,${(sh-0.4)*0.45})`:`rgba(0,0,0,${(0.5-sh)*0.30})`;
    CTX.fill();
  }
  CTX.globalAlpha=1;
}

function drawHipso3D(){
  const sorted=[...TRIS].sort((a,b)=>{
    const da=(proj(PTS[a.a]).d+proj(PTS[a.b]).d+proj(PTS[a.c]).d)/3;
    const db=(proj(PTS[b.a]).d+proj(PTS[b.b]).d+proj(PTS[b.c]).d)/3;
    return db-da;
  });
  for(const t of sorted){
    const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c];
    const pa=proj(a),pb=proj(b),pc=proj(c);
    CTX.beginPath();
    CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy);
    CTX.closePath();
    CTX.fillStyle=hipsoColor(tnorm((a.z+b.z+c.z)/3),0.65);
    CTX.fill();
    CTX.strokeStyle="rgba(0,0,0,0.06)"; CTX.lineWidth=0.3; CTX.stroke();
  }
}

/* ──────────────────────────────────────────────────────────────
   TIN LINES
────────────────────────────────────────────────────────────── */
function drawTINlines(){
  CTX.strokeStyle=MODO==="2D"?"rgba(60,60,120,0.20)":"rgba(0,229,192,0.10)";
  CTX.lineWidth=0.4;
  for(const t of TRIS){
    const a=proj(PTS[t.a]),b=proj(PTS[t.b]),c=proj(PTS[t.c]);
    CTX.beginPath(); CTX.moveTo(a.sx,a.sy); CTX.lineTo(b.sx,b.sy); CTX.lineTo(c.sx,c.sy); CTX.closePath(); CTX.stroke();
  }
}

/* ──────────────────────────────────────────────────────────────
   CURVAS DE NIVEL — estilo plano topográfico real
────────────────────────────────────────────────────────────── */
function drawContours(){
  const eq=(ZMAX-ZMIN)/(NIV.length+1);
  // Maestra cada 5m (o cada 4 curvas mínimo)
  const mStep=Math.max(1,Math.round(5/eq));

  NIV.forEach((z,idx)=>{
    const segs=ISO[z]; if(!segs||!segs.length) return;
    const maestra=(idx%mStep===0);

    if(MODO==="2D"){
      CTX.strokeStyle=maestra?"rgba(105,60,5,0.95)":"rgba(155,100,30,0.50)";
      CTX.lineWidth  =maestra?1.6:0.75;
    } else {
      CTX.strokeStyle=maestra?"rgba(255,255,255,0.80)":"rgba(0,229,192,0.28)";
      CTX.lineWidth  =maestra?1.3:0.5;
    }

    const mids=[];
    for(const s of segs){
      const p1=proj({x:s[0].x,y:s[0].y,z});
      const p2=proj({x:s[1].x,y:s[1].y,z});
      if(p1.sx<-300||p1.sx>CVS.width+300) continue;
      CTX.beginPath(); CTX.moveTo(p1.sx,p1.sy); CTX.lineTo(p2.sx,p2.sy); CTX.stroke();
      mids.push({x:(p1.sx+p2.sx)/2, y:(p1.sy+p2.sy)/2});
    }

    // Cota sobre curva maestra — punto más cercano al centro
    if(maestra && mids.length){
      const cx=CVS.width/2, cy=CVS.height/2;
      const best=mids.reduce((b,p)=>Math.hypot(p.x-cx,p.y-cy)<Math.hypot(b.x-cx,b.y-cy)?p:b);
      const lbl=z.toFixed(2)+"m";
      CTX.save();
      CTX.font=MODO==="2D"?"bold 8.5px 'DM Mono',monospace":"bold 8px 'DM Sans',sans-serif";
      const tw=CTX.measureText(lbl).width;
      // Cortar la curva con fondo blanco (halo)
      CTX.fillStyle=MODO==="2D"?"rgba(244,240,230,0.93)":"rgba(6,10,18,0.80)";
      CTX.fillRect(best.x-tw/2-3, best.y-9, tw+6, 13);
      CTX.fillStyle=MODO==="2D"?"rgba(105,60,5,1)":"rgba(255,255,255,0.9)";
      CTX.textAlign="center";
      CTX.fillText(lbl, best.x, best.y+3);
      CTX.textAlign="left";
      CTX.restore();
    }
  });
}

/* ──────────────────────────────────────────────────────────────
   SÍMBOLOS TOPOGRÁFICOS
────────────────────────────────────────────────────────────── */
function drawSymbols(){
  for(const p of PTS){
    const pp=proj(p);
    if(pp.sx<-30||pp.sx>CVS.width+30||pp.sy<-30||pp.sy>CVS.height+30) continue;
    const s=getSim(p.desc);
    const r=s.r*Math.min(Math.max(ZOOM*0.75,0.4),3.0);
    drawSym(CTX,pp.sx,pp.sy,s.sym,r,s.color,MODO);
  }
}

function drawSym(ctx,x,y,sym,r,col,modo){
  const lk=modo==="2D"; // light/dark
  ctx.save();
  ctx.fillStyle   = col;
  ctx.strokeStyle = lk?"rgba(0,0,0,0.80)":"rgba(255,255,255,0.55)";
  ctx.lineWidth   = lk?0.7:0.5;

  switch(sym){
    case"dot":
      ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fill(); ctx.stroke(); break;
    case"circle":
      ctx.beginPath(); ctx.arc(x,y,r*1.3,0,Math.PI*2); ctx.stroke();
      ctx.beginPath(); ctx.arc(x,y,r*0.5,0,Math.PI*2); ctx.fill(); break;
    case"square":
      ctx.beginPath(); ctx.rect(x-r,y-r,r*2,r*2); ctx.fill(); ctx.stroke(); break;
    case"diamond":
      ctx.beginPath();
      ctx.moveTo(x,y-r*1.5); ctx.lineTo(x+r*1.1,y); ctx.lineTo(x,y+r*1.5); ctx.lineTo(x-r*1.1,y);
      ctx.closePath(); ctx.fill(); ctx.stroke(); break;
    case"tri_up":
      ctx.beginPath();
      ctx.moveTo(x,y-r*1.4); ctx.lineTo(x+r*1.2,y+r*0.9); ctx.lineTo(x-r*1.2,y+r*0.9);
      ctx.closePath(); ctx.fill(); ctx.stroke(); break;
    case"tri_dn":
      ctx.beginPath();
      ctx.moveTo(x,y+r*1.4); ctx.lineTo(x+r*1.2,y-r*0.9); ctx.lineTo(x-r*1.2,y-r*0.9);
      ctx.closePath(); ctx.fill(); ctx.stroke(); break;
    case"cross":
      ctx.strokeStyle=col; ctx.lineWidth=lk?1.6:1.2;
      ctx.beginPath();
      ctx.moveTo(x-r*1.5,y); ctx.lineTo(x+r*1.5,y);
      ctx.moveTo(x,y-r*1.5); ctx.lineTo(x,y+r*1.5);
      ctx.stroke(); break;
    case"arbol":{
      const ri=r*1.4;
      ctx.beginPath(); ctx.arc(x,y-ri*0.25,ri,0,Math.PI*2); ctx.fill(); ctx.stroke();
      ctx.strokeStyle=lk?"#4A2800":"#8B4513"; ctx.lineWidth=lk?1:0.8;
      ctx.beginPath(); ctx.moveTo(x,y+ri*0.75); ctx.lineTo(x,y+ri*0.75+r*0.8); ctx.stroke();
      break;
    }
    case"gps":{
      ctx.strokeStyle=col; ctx.lineWidth=1.5;
      ctx.beginPath();
      ctx.moveTo(x,y-r*2); ctx.lineTo(x+r*1.5,y+r*0.8); ctx.lineTo(x-r*1.5,y+r*0.8);
      ctx.closePath(); ctx.stroke();
      ctx.beginPath(); ctx.arc(x,y-r*0.2,r*0.7,0,Math.PI*2); ctx.fill(); break;
    }
    default:
      ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fill(); ctx.stroke();
  }
  ctx.restore();
}

/* ──────────────────────────────────────────────────────────────
   ETIQUETAS DE PUNTO — estilo plano de campo topográfico real
   Número (azul) + Cota (rojo) + Código opcional
────────────────────────────────────────────────────────────── */
function drawLabels(){
  // Reducir densidad según zoom
  const skip = ZOOM<0.35?10: ZOOM<0.55?6: ZOOM<0.75?3: ZOOM<1.0?2:1;
  const lk   = MODO==="2D";

  CTX.save();
  PTS.forEach((p,i)=>{
    if(i%skip!==0) return;
    const pp=proj(p);
    if(pp.sx<-40||pp.sx>CVS.width+40||pp.sy<-40||pp.sy>CVS.height+40) return;

    // Desplazamiento de la etiqueta respecto al símbolo
    const s = getSim(p.desc);
    const r = s.r*Math.min(Math.max(ZOOM*0.75,0.4),3.0);
    const ox = pp.sx + r + 2;
    const oy = pp.sy;

    if(lk){
      /* ════ VISTA 2D — etiqueta topográfica real ════
         Arriba: número de punto (azul oscuro, negrita)
         Medio:  cota Z (rojo sangre, mono)
         Abajo:  código/descripción si está activo (gris) */

      if(OPT.npts && p.n!=null){
        CTX.font      = "bold 8.5px 'DM Mono',monospace";
        CTX.fillStyle = "#00008B";
        CTX.fillText(String(p.n), ox, oy+1);
      }

      if(OPT.cotas){
        CTX.font      = "7.5px 'DM Mono',monospace";
        CTX.fillStyle = "#AA0000";
        CTX.fillText(p.z.toFixed(3), ox, oy+11);
      }

      if(OPT.codigos && p.desc && ZOOM>=0.75){
        CTX.font      = "italic 7px 'DM Sans',sans-serif";
        CTX.fillStyle = "#444";
        CTX.fillText(p.desc, ox, oy+21);
      }
    } else {
      /* ════ VISTA 3D — etiqueta compacta sobre fondo oscuro ════ */
      if(OPT.npts && p.n!=null){
        CTX.font = "bold 8px 'DM Mono',monospace";
        const lbl=String(p.n);
        const tw=CTX.measureText(lbl).width;
        CTX.fillStyle="rgba(6,10,18,0.55)";
        CTX.fillRect(ox-1,oy-9,tw+2,10);
        CTX.fillStyle="#00E5C0";
        CTX.fillText(lbl,ox,oy);
      }
      if(OPT.cotas){
        CTX.font="6.5px 'DM Mono',monospace";
        CTX.fillStyle="#FFD070";
        CTX.fillText(p.z.toFixed(2)+"m", ox, oy+10);
      }
    }
  });
  CTX.restore();
}

/* ──────────────────────────────────────────────────────────────
   RESALTADOS (puntos seleccionados en cálculos)
────────────────────────────────────────────────────────────── */
function drawHighlights(){
  if(!RESH.length) return;
  const lk=MODO==="2D";

  // Línea entre 2 puntos con distancia
  if(RESH.length===2){
    const pa=proj(RESH[0]),pb=proj(RESH[1]);
    CTX.save();
    CTX.strokeStyle=lk?"#0000CC":"rgba(0,229,192,0.9)";
    CTX.lineWidth=1.8; CTX.setLineDash([8,5]);
    CTX.beginPath(); CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.stroke();
    CTX.setLineDash([]);
    // Cota diferencial en punto medio
    const mx=(pa.sx+pb.sx)/2, my=(pa.sy+pb.sy)/2;
    const a=RESH[0],b=RESH[1];
    const dH=Math.hypot(b.x-a.x,b.y-a.y);
    const dZ=Math.abs(b.z-a.z);
    const d3=Math.hypot(dH,dZ);
    const lbl=`ΔH:${dH.toFixed(3)}  ΔZ:${dZ.toFixed(3)}  D:${d3.toFixed(3)}m`;
    CTX.font="bold 10px 'DM Mono',monospace";
    const tw=CTX.measureText(lbl).width;
    CTX.fillStyle=lk?"rgba(244,240,230,0.95)":"rgba(6,10,18,0.90)";
    rrect(CTX,mx-tw/2-6,my-13,tw+12,18,3); CTX.fill();
    CTX.strokeStyle=lk?"#0000CC":"#00E5C0"; CTX.lineWidth=0.8; CTX.stroke();
    CTX.fillStyle=lk?"#0000CC":"#00E5C0";
    CTX.textAlign="center"; CTX.fillText(lbl,mx,my+2); CTX.textAlign="left";
    CTX.restore();
  }

  // Línea de polilínea si >2 puntos
  if(RESH.length>2){
    CTX.save();
    CTX.strokeStyle=lk?"rgba(0,0,200,0.5)":"rgba(0,229,192,0.4)";
    CTX.lineWidth=1.2; CTX.setLineDash([5,4]);
    CTX.beginPath();
    RESH.forEach((p,i)=>{ const pp=proj(p); i===0?CTX.moveTo(pp.sx,pp.sy):CTX.lineTo(pp.sx,pp.sy); });
    CTX.stroke(); CTX.setLineDash([]); CTX.restore();
  }

  // Anillo + ficha de datos por punto
  for(const p of RESH){
    const pp=proj(p);
    const s=getSim(p.desc);
    CTX.save();

    // Anillo exterior
    CTX.beginPath(); CTX.arc(pp.sx,pp.sy,11,0,Math.PI*2);
    CTX.strokeStyle=lk?"#0000CC":"#00E5C0"; CTX.lineWidth=2; CTX.stroke();

    // Punto central relleno
    CTX.beginPath(); CTX.arc(pp.sx,pp.sy,4,0,Math.PI*2);
    CTX.fillStyle=lk?"#0000CC":"#00E5C0"; CTX.fill();

    // Ficha de datos
    const rows=[
      {t:`N° ${p.n??'?'}`,  bold:true,  col:lk?"#00008B":"#00E5C0"},
      {t:`X: ${p.x.toFixed(3)}`, bold:false, col:lk?"#333":"#ccc"},
      {t:`Y: ${p.y.toFixed(3)}`, bold:false, col:lk?"#333":"#ccc"},
      {t:`Z: ${p.z.toFixed(4)}m`,bold:true,  col:lk?"#AA0000":"#FFD070"},
      ...(p.desc?[{t:p.desc, bold:false, col:lk?"#555":"#aaa"}]:[]),
    ];

    const fw=168, fh=rows.length*13+12;
    let fx=pp.sx+14, fy=pp.sy-fh-10;
    if(fx+fw>CVS.width-10)  fx=pp.sx-fw-14;
    if(fy<10)                fy=pp.sy+14;

    CTX.shadowColor=lk?"rgba(0,0,0,0.20)":"rgba(0,0,0,0.60)";
    CTX.shadowBlur=8;
    CTX.fillStyle=lk?"rgba(244,240,230,0.97)":"rgba(6,10,18,0.95)";
    CTX.strokeStyle=lk?"#0000CC":"#00E5C0"; CTX.lineWidth=1;
    rrect(CTX,fx,fy,fw,fh,5); CTX.fill(); CTX.stroke();
    CTX.shadowBlur=0;

    // Barra lateral de color del código
    CTX.fillStyle=s.color;
    rrect(CTX,fx,fy,5,fh,5); CTX.fill();

    rows.forEach((row,i)=>{
      CTX.font=(row.bold?"bold ":"")+"10px 'DM Mono',monospace";
      CTX.fillStyle=row.col;
      CTX.fillText(row.t, fx+10, fy+13+i*13);
    });
    CTX.restore();
  }
}

function rrect(ctx,x,y,w,h,r){
  ctx.beginPath();
  ctx.moveTo(x+r,y); ctx.lineTo(x+w-r,y); ctx.arcTo(x+w,y,x+w,y+r,r);
  ctx.lineTo(x+w,y+h-r); ctx.arcTo(x+w,y+h,x+w-r,y+h,r);
  ctx.lineTo(x+r,y+h); ctx.arcTo(x,y+h,x,y+h-r,r);
  ctx.lineTo(x,y+r); ctx.arcTo(x,y,x+r,y,r);
  ctx.closePath();
}

/* ──────────────────────────────────────────────────────────────
   BARRA DE ESCALA GRÁFICA
────────────────────────────────────────────────────────────── */
function drawScaleBar(){
  const mp=SCL*ZOOM;
  const steps=[0.5,1,2,5,10,20,25,50,100,200,500,1000];
  const bm=steps.find(s=>s*mp>90)||1000;
  const bp=bm*mp;
  const bx=22, by=CVS.height-30;

  CTX.save();
  // Barra alternada negro/blanco (estilo topografía)
  for(let s=0;s<4;s++){
    CTX.fillStyle=s%2===0?"#00008B":"#FFFFFF";
    CTX.fillRect(bx+s*bp/4, by-6, bp/4, 6);
    CTX.strokeStyle="#00008B"; CTX.lineWidth=0.8;
    CTX.strokeRect(bx+s*bp/4, by-6, bp/4, 6);
  }
  // Etiquetas
  CTX.font="bold 8px 'DM Mono',monospace";
  CTX.fillStyle="#00008B"; CTX.textAlign="center";
  CTX.fillText("0",   bx,          by+10);
  CTX.fillText(bm/2+"m", bx+bp/2,  by+10);
  CTX.fillText(bm+"m",   bx+bp,    by+10);
  // Nota escala
  CTX.textAlign="left"; CTX.font="7px 'DM Mono',monospace";
  CTX.fillStyle="rgba(0,0,128,0.60)";
  const gridStep=[1,2,5,10,20,25,50,100,200,500,1000].find(v=>v*mp>=60)||1000;
  CTX.fillText(`Paso de grilla: ${gridStep}m`,bx,by-12);
  CTX.restore();
}

/* ──────────────────────────────────────────────────────────────
   ROSA DEL NORTE
────────────────────────────────────────────────────────────── */
function drawNorthArrow(){
  const nx=CVS.width-50, ny=56, r=22;
  CTX.save();

  // Fondo circular
  CTX.beginPath(); CTX.arc(nx,ny,r+8,0,Math.PI*2);
  CTX.fillStyle="rgba(244,240,230,0.88)"; CTX.fill();
  CTX.strokeStyle="#00008B"; CTX.lineWidth=0.8; CTX.stroke();

  // Flecha: mitad norte rellena (azul) + mitad sur vacía
  CTX.fillStyle="#00008B";
  CTX.beginPath(); CTX.moveTo(nx,ny-r); CTX.lineTo(nx+7,ny+4); CTX.lineTo(nx,ny); CTX.closePath(); CTX.fill();
  CTX.fillStyle="#FFF";
  CTX.beginPath(); CTX.moveTo(nx,ny-r); CTX.lineTo(nx-7,ny+4); CTX.lineTo(nx,ny); CTX.closePath(); CTX.fill();
  CTX.strokeStyle="#00008B"; CTX.lineWidth=0.8;
  CTX.beginPath(); CTX.moveTo(nx,ny-r); CTX.lineTo(nx+7,ny+4); CTX.lineTo(nx,ny); CTX.moveTo(nx,ny-r); CTX.lineTo(nx-7,ny+4); CTX.lineTo(nx,ny); CTX.stroke();

  // "N"
  CTX.font="bold 11px 'DM Sans',sans-serif";
  CTX.fillStyle="#00008B"; CTX.textAlign="center";
  CTX.fillText("N",nx,ny-r-3); CTX.textAlign="left";
  CTX.restore();
}

/* ──────────────────────────────────────────────────────────────
   CARTUCHO DE PLANO (info técnica)
────────────────────────────────────────────────────────────── */
function drawTitleBlock(){
  const w=210, h=78;
  const x=CVS.width-w-10, y=CVS.height-h-10;
  CTX.save();

  // Marco exterior
  CTX.fillStyle="rgba(244,240,230,0.96)"; CTX.strokeStyle="#00008B"; CTX.lineWidth=1;
  rrect(CTX,x,y,w,h,2); CTX.fill(); CTX.stroke();

  // Línea divisoria header
  CTX.beginPath(); CTX.moveTo(x,y+18); CTX.lineTo(x+w,y+18); CTX.stroke();

  // Título
  CTX.font="bold 9.5px 'Syne',sans-serif"; CTX.fillStyle="#00008B";
  CTX.textAlign="center"; CTX.fillText("FYLCAD · PLANO TOPOGRÁFICO",x+w/2,y+13);

  // Datos técnicos
  CTX.textAlign="left"; CTX.font="7.5px 'DM Mono',monospace"; CTX.fillStyle="#222";
  CTX.fillText(`Puntos: ${PTS.length}   TIN: ${TRIS.length} △`,    x+6, y+28);
  CTX.fillText(`Z min: ${ZMIN.toFixed(3)}m`,                         x+6, y+38);
  CTX.fillText(`Z max: ${ZMAX.toFixed(3)}m`,                         x+6, y+48);
  CTX.fillText(`Desnivel: ${(ZMAX-ZMIN).toFixed(3)}m`,               x+6, y+58);
  CTX.fillText(`Eq. curvas: ${((ZMAX-ZMIN)/(NIV.length+1)).toFixed(3)}m`, x+6, y+68);
  // Fecha
  CTX.textAlign="right"; CTX.font="6.5px 'DM Mono',monospace"; CTX.fillStyle="#666";
  CTX.fillText(new Date().toLocaleDateString("es-CO"),x+w-5,y+68);
  CTX.textAlign="left"; CTX.restore();
}

/* ──────────────────────────────────────────────────────────────
   LEYENDA DE CAPAS (solo en pantallas ≥700px)
────────────────────────────────────────────────────────────── */
function drawLegend(){
  if(CVS.width<700) return;

  // Capas presentes
  const capas=new Map();
  for(const p of PTS){
    const s=getSim(p.desc);
    if(!capas.has(s.capa)) capas.set(s.capa,{s,cnt:0});
    capas.get(s.capa).cnt++;
  }
  const items=[...capas.entries()];
  const lh=14, lw=178, y0=95;

  CTX.save();
  CTX.fillStyle="rgba(244,240,230,0.92)"; CTX.strokeStyle="rgba(0,0,128,0.35)"; CTX.lineWidth=0.7;
  rrect(CTX,8,y0,lw,items.length*lh+22,2); CTX.fill(); CTX.stroke();

  CTX.font="bold 8px 'DM Sans',sans-serif"; CTX.fillStyle="#00008B";
  CTX.fillText("LEYENDA DE CAPAS",14,y0+12);

  // Línea bajo título
  CTX.beginPath(); CTX.moveTo(8,y0+16); CTX.lineTo(8+lw,y0+16); CTX.stroke();

  items.forEach(([capa,{s,cnt}],i)=>{
    const iy=y0+22+i*lh;
    drawSym(CTX,18,iy+3,s.sym,3.5,s.color,"2D");
    CTX.font="7px 'DM Mono',monospace"; CTX.fillStyle="#222";
    CTX.fillText(`${capa}  (${cnt})`,30,iy+6);
  });
  CTX.restore();
}

/* ──────────────────────────────────────────────────────────────
   GRID / EJES 3D
────────────────────────────────────────────────────────────── */
function drawGrid3D(){
  const paso=80, rng=5;
  CTX.strokeStyle="rgba(0,229,192,0.05)"; CTX.lineWidth=0.4;
  for(let i=-rng;i<=rng;i++){
    const p1=proj({x:CX+i*paso,y:CY-rng*paso,z:ZMIN}),p2=proj({x:CX+i*paso,y:CY+rng*paso,z:ZMIN});
    CTX.beginPath(); CTX.moveTo(p1.sx,p1.sy); CTX.lineTo(p2.sx,p2.sy); CTX.stroke();
    const p3=proj({x:CX-rng*paso,y:CY+i*paso,z:ZMIN}),p4=proj({x:CX+rng*paso,y:CY+i*paso,z:ZMIN});
    CTX.beginPath(); CTX.moveTo(p3.sx,p3.sy); CTX.lineTo(p4.sx,p4.sy); CTX.stroke();
  }
}
function drawAxes3D(){
  const L=SCL*ZOOM;
  const o=proj({x:CX,y:CY,z:CZ});
  [[{x:CX+L/SCL,y:CY,z:CZ},"E","rgba(239,68,68,0.85)"],
   [{x:CX,y:CY+L/SCL,z:CZ},"N","rgba(59,130,246,0.85)"],
   [{x:CX,y:CY,z:CZ+L/SCL},"Z","rgba(0,229,192,0.90)"]].forEach(([pt,l,c])=>{
    const p=proj(pt);
    CTX.beginPath(); CTX.moveTo(o.sx,o.sy); CTX.lineTo(p.sx,p.sy);
    CTX.strokeStyle=c; CTX.lineWidth=1.5; CTX.stroke();
    CTX.font="bold 10px 'DM Sans',sans-serif"; CTX.fillStyle=c;
    CTX.fillText(l,p.sx+3,p.sy-3);
  });
}

/* ──────────────────────────────────────────────────────────────
   CONTROLES DE VISTA
────────────────────────────────────────────────────────────── */
function setModo(m){
  MODO=m;
  if(m==="3D"){AX=0.50;AY=-0.38;}
  document.getElementById("btn2D")?.classList.toggle("active",m==="2D");
  document.getElementById("btn3D")?.classList.toggle("active",m==="3D");
  draw();
}
document.getElementById("btn2D")?.addEventListener("click",()=>setModo("2D"));
document.getElementById("btn3D")?.addEventListener("click",()=>setModo("3D"));
document.getElementById("btn2D")?.classList.add("active");

// Botones de capas
[["btnHipso","hipso"],["btnContornos","curvas"],["btnNpts","npts"],
 ["btnCotas","cotas"],["btnCodigos","codigos"],["btnTIN","tin"]].forEach(([id,key])=>{
  document.getElementById(id)?.addEventListener("click",()=>{
    OPT[key]=!OPT[key];
    document.getElementById(id).classList.toggle("active",OPT[key]);
    draw();
  });
});

document.getElementById("btnReset")?.addEventListener("click",()=>{resetVista();draw();});
document.getElementById("btnFullscreen")?.addEventListener("click",()=>{
  document.fullscreenElement?document.exitFullscreen():CVS.parentElement.requestFullscreen?.();
});
document.addEventListener("fullscreenchange",()=>{setTimeout(()=>{resize();draw();},100);});

/* ──────────────────────────────────────────────────────────────
   MOUSE / TOUCH
────────────────────────────────────────────────────────────── */
CVS.addEventListener("mousedown",e=>{DRAG={on:true,x:e.clientX,y:e.clientY,btn:e.button};e.preventDefault();});
CVS.addEventListener("contextmenu",e=>e.preventDefault());

CVS.addEventListener("mousemove",e=>{
  if(DRAG.on){
    const dx=e.clientX-DRAG.x, dy=e.clientY-DRAG.y;
    DRAG.x=e.clientX; DRAG.y=e.clientY;
    if(e.shiftKey||DRAG.btn===2){PX+=dx;PY+=dy;}
    else if(MODO==="3D"){AY+=dx*0.005;AX+=dy*0.005;AX=Math.max(-Math.PI/2,Math.min(Math.PI/2,AX));}
    else{PX+=dx;PY+=dy;}
    draw();
  }

  // Hover: punto más cercano
  if(!PTS.length) return;
  const rect=CVS.getBoundingClientRect();
  const mx=e.clientX-rect.left, my=e.clientY-rect.top;
  let best=null,bestD=Infinity;
  for(const p of PTS){
    const pp=proj(p);
    const d=Math.hypot(pp.sx-mx,pp.sy-my);
    if(d<bestD){bestD=d;best=p;}
  }
  if(best&&bestD<18){
    const s=getSim(best.desc);
    coordTxt.textContent=`N°${best.n??'—'}  X:${best.x.toFixed(3)}  Y:${best.y.toFixed(3)}  Z:${best.z.toFixed(4)}m  ${best.desc?'· '+best.desc:''}`;
    if(hoverLbl){
      hoverLbl.style.display="block";
      hoverLbl.style.left=(mx+16)+"px"; hoverLbl.style.top=(my-52)+"px";
      hoverLbl.innerHTML=
        `<b>N° ${best.n??'?'}</b>`+
        `<br><span class="hz">${best.z.toFixed(4)} m</span>`+
        `<br>X: ${best.x.toFixed(3)}`+
        `<br>Y: ${best.y.toFixed(3)}`+
        (best.desc?`<br><span class="hd">${best.desc}</span>`:'');
    }
  } else {
    coordTxt.textContent="X: —   Y: —   Z: —";
    if(hoverLbl) hoverLbl.style.display="none";
  }
});

CVS.addEventListener("mouseup",  ()=>{DRAG.on=false;});
CVS.addEventListener("mouseleave",()=>{DRAG.on=false; if(hoverLbl) hoverLbl.style.display="none";});

CVS.addEventListener("wheel",e=>{
  e.preventDefault();
  ZOOM=Math.max(0.04,Math.min(60,ZOOM*(e.deltaY<0?1.12:0.89)));
  draw();
},{passive:false});

// Touch pinch
let _td=0;
CVS.addEventListener("touchstart",e=>{
  e.preventDefault();
  if(e.touches.length===1) DRAG={on:true,x:e.touches[0].clientX,y:e.touches[0].clientY,btn:0};
  if(e.touches.length===2) _td=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);
},{passive:false});
CVS.addEventListener("touchmove",e=>{
  e.preventDefault();
  if(e.touches.length===1&&DRAG.on){
    const dx=e.touches[0].clientX-DRAG.x,dy=e.touches[0].clientY-DRAG.y;
    DRAG.x=e.touches[0].clientX;DRAG.y=e.touches[0].clientY;
    if(MODO==="3D"){AY+=dx*0.005;AX+=dy*0.005;}else{PX+=dx;PY+=dy;}
    draw();
  }
  if(e.touches.length===2){
    const d=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);
    ZOOM=Math.max(0.04,Math.min(60,ZOOM*(d/_td))); _td=d; draw();
  }
},{passive:false});
CVS.addEventListener("touchend",()=>{DRAG.on=false;},{passive:false});

/* ──────────────────────────────────────────────────────────────
   PANELES & MÉTRICAS
────────────────────────────────────────────────────────────── */
function showPanels(){
  ["panel-metrics","panel-calculos","panel-cot-link","panel-tabla"].forEach(id=>{
    const el=document.getElementById(id); if(el) el.style.display="block";
  });
}

function updateCotLink(m){
  // Actualizar mini-preview del botón de cotización
  const fmt2=v=>v>=1e6?(v/1e6).toFixed(2)+"M":v>=1e3?(v/1e3).toFixed(1)+"k":Math.round(v).toString();
  const el=id=>document.getElementById(id);
  if(el("clp-area"))    el("clp-area").textContent    = fmt2(m.area);
  if(el("clp-vol"))     el("clp-vol").textContent     = fmt2(m.volumen);
  if(el("clp-desnivel"))el("clp-desnivel").textContent= m.desnivel.toFixed(1);
  // Actualizar URL del botón cotización con el ID del proyecto si está cargado
  const cotLink=el("btnCotLink");
  if(cotLink && PROYECTO_ID) cotLink.href="cotizacion.php?proyecto="+PROYECTO_ID;

  try {
    // Métricas generales
    localStorage.setItem("fylcad_metricas", JSON.stringify({
      n:        m.n,
      area:     m.area,
      perimetro:m.perimetro,
      volumen:  m.volumen,
      zMin:     m.zMin,
      zMax:     m.zMax,
      desnivel: m.desnivel,
      tris:     TRIS.length,
      eq:       NIV.length>0?(ZMAX-ZMIN)/(NIV.length+1):1,
      cx: CX, cy: CY, cz: CZ,
      timestamp: Date.now()
    }));

    // Puntos — guardamos versión completa para el plano de cotización
    // Si son muchos puntos, submuestreamos para no sobrepasar localStorage (~5MB)
    let ptsToSave = PTS;
    if(PTS.length > 3000){
      // submuestreo uniforme manteniendo distribución espacial
      const step = Math.ceil(PTS.length / 3000);
      ptsToSave = PTS.filter((_,i)=>i%step===0);
    }
    localStorage.setItem("fylcad_puntos", JSON.stringify(ptsToSave));

    // Triangulación TIN (índices) para el render del plano
    const trisIdx = TRIS.map(t=>({a:t.a,b:t.b,c:t.c}));
    // Limitar a 6000 triángulos si hay demasiados
    const trisToSave = trisIdx.length>6000 ? trisIdx.slice(0,6000) : trisIdx;
    localStorage.setItem("fylcad_tris", JSON.stringify(trisToSave));

    // Curvas de nivel (solo los valores Z, no los segmentos — se recalculan)
    localStorage.setItem("fylcad_niveles", JSON.stringify(NIV.slice(0,50)));

  } catch(e){
    // Si falla (sessionStorage lleno), guardar solo métricas
    console.warn("FYLCAD: sessionStorage limitado, guardando solo métricas");
  }
}

function calcMetrics(pts){
  if(!pts.length) return {n:0,area:0,perimetro:0,volumen:0,zMin:0,zMax:0,desnivel:0};
  const n=pts.length;
  const zs=pts.map(p=>p.z);
  const zMn=Math.min(...zs),zMx=Math.max(...zs),zMd=zs.reduce((a,b)=>a+b)/n;
  let area=0;
  for(let i=0;i<n;i++){const j=(i+1)%n;area+=pts[i].x*pts[j].y-pts[j].x*pts[i].y;}
  area=Math.abs(area)/2;
  let perim=0;
  for(let i=0;i<n;i++){const j=(i+1)%n;perim+=Math.hypot(pts[j].x-pts[i].x,pts[j].y-pts[i].y,pts[j].z-pts[i].z);}
  return {n,area,perimetro:perim,volumen:area*(zMd-zMn),zMin:zMn,zMax:zMx,desnivel:zMx-zMn};
}

function renderMetrics(m){
  // KPIs grandes
  set("m-puntos",   m.n);
  set("m-tris",     TRIS.length);
  set("m-area",     fmt(m.area));
  set("m-area-ha",  (m.area/10000).toFixed(4));
  set("m-perimetro",fmt(m.perimetro));
  set("m-volumen",  fmt(m.volumen));
  set("m-zmin",     m.zMin.toFixed(2));
  set("m-zmax",     m.zMax.toFixed(2));
  set("m-desnivel", m.desnivel.toFixed(2));
  set("m-eq",       ((ZMAX-ZMIN)/(NIV.length+1)).toFixed(2));

  // Barra de elevación: el fill cubre desde max hasta min (inverted)
  const fillEl = document.getElementById("elevBarFill");
  const midEl  = document.getElementById("elevBarMid");
  if(fillEl) { /* la barra va de min(izq) a max(der) — no hay fill que bloquee */ fillEl.style.display="none"; }
  if(midEl && m.desnivel > 0){
    const pct = ((m.zMax - m.zMin - m.desnivel/2) / (m.zMax - m.zMin) * 100);
    midEl.style.left = "50%"; // marca cota media
  }

  // Clasificación topográfica por pendiente media
  const pendMedia = m.perimetro > 0 ? (m.desnivel / (m.area > 0 ? Math.sqrt(m.area) : 1) * 100) : 0;
  const badge = document.getElementById("terrainBadge");
  if(badge){
    let cls="tb-plano", txt="Plano";
    if(m.desnivel > 5 && m.desnivel <= 20)  { cls="tb-ondulado"; txt="Ondulado"; }
    else if(m.desnivel > 20 && m.desnivel <= 50){ cls="tb-quebrado"; txt="Quebrado"; }
    else if(m.desnivel > 50) { cls="tb-escarpado"; txt="Escarpado"; }
    badge.className="terrain-badge "+cls;
    badge.textContent=txt;
  }
}

function fillTable(pts){
  const tb=document.getElementById("tablaCuerpo"); if(!tb) return;
  tb.innerHTML="";
  pts.slice(0,2000).forEach((p,i)=>{
    const s=getSim(p.desc);
    const tr=document.createElement("tr");
    tr.style.cursor="pointer";
    tr.innerHTML=`
      <td style="color:#999;font-size:10px;">${i+1}</td>
      <td style="color:#00008B;font-weight:700;font-family:'DM Mono',monospace;">${p.n??''}</td>
      <td style="font-family:'DM Mono',monospace;font-size:11px;">${p.x.toFixed(3)}</td>
      <td style="font-family:'DM Mono',monospace;font-size:11px;">${p.y.toFixed(3)}</td>
      <td style="color:#AA0000;font-weight:700;font-family:'DM Mono',monospace;font-size:11px;">${p.z.toFixed(4)}</td>
      <td><span style="background:${s.color};color:#fff;font-size:9px;padding:1px 6px;border-radius:3px;white-space:nowrap;">${p.desc??''}</span></td>`;
    tr.addEventListener("click",()=>{
      RESH=[p]; const pp=proj(p); PX+=(CVS.width/2-pp.sx); PY+=(CVS.height/2-pp.sy); draw();
      toast(`N°${p.n} · Z:${p.z.toFixed(4)}m · ${p.desc||''}`,"success");
    });
    tb.appendChild(tr);
  });
}

/* ──────────────────────────────────────────────────────────────
   ÍNDICE & FILTROS
────────────────────────────────────────────────────────────── */
function rebuildIdx(){
  IDX={}; for(const p of PTS) if(p.n!=null) IDX[p.n]=p;
}
function getByN(n){ const k=parseInt(n); return IDX[k]||PTS[k-1]||null; }

function buildDescTags(){
  const c=document.getElementById("descTagsContainer"); if(!c) return;
  const descs=[...new Set(ORIG.map(p=>p.desc).filter(Boolean))].sort();
  c.innerHTML=""; SEL_DESC.clear();
  descs.forEach(desc=>{
    const s=getSim(desc);
    const cnt=ORIG.filter(p=>p.desc===desc).length;
    const tag=document.createElement("span");
    tag.className="desc-tag"; tag.dataset.desc=desc;
    tag.innerHTML=`<span class="dot" style="background:${s.color};"></span>${desc} <span style="opacity:.5;font-size:9px;">(${cnt})</span>`;
    tag.addEventListener("click",()=>{
      tag.classList.toggle("selected");
      SEL_DESC[tag.classList.contains("selected")?"add":"delete"](desc);
    });
    c.appendChild(tag);
  });
}

document.getElementById("btnSelectAllDesc")?.addEventListener("click",()=>{
  document.querySelectorAll(".desc-tag").forEach(t=>{ t.classList.add("selected"); SEL_DESC.add(t.dataset.desc); });
});
document.getElementById("btnClearDesc")?.addEventListener("click",()=>{
  document.querySelectorAll(".desc-tag").forEach(t=>{ t.classList.remove("selected"); });
  SEL_DESC.clear(); applyFilter(ORIG.slice()); toast("Vista completa restaurada","success");
});
document.getElementById("btnFiltrarDesc")?.addEventListener("click",()=>{
  if(!SEL_DESC.size){toast("Selecciona al menos un código","error");return;}
  const f=ORIG.filter(p=>SEL_DESC.has(p.desc));
  if(!f.length){toast("Sin puntos con ese código","error");return;}
  applyFilter(f); toast(`✓ ${f.length} puntos · ${[...SEL_DESC].join(', ')}`,"success");
});

function applyFilter(pts){
  PTS=pts;
  ZMIN=Math.min(...PTS.map(p=>p.z)); ZMAX=Math.max(...PTS.map(p=>p.z));
  CX=PTS.reduce((s,p)=>s+p.x,0)/PTS.length;
  CY=PTS.reduce((s,p)=>s+p.y,0)/PTS.length;
  CZ=PTS.reduce((s,p)=>s+p.z,0)/PTS.length;
  const mx=Math.max(...PTS.map(p=>Math.hypot(p.x-CX,p.y-CY)));
  SCL=mx>0?Math.min(CVS.width,CVS.height)*0.42/mx:1;
  TRIS=delaunay(PTS.map(p=>({x:p.x,y:p.y})));
  ISO=buildIso(PTS,TRIS,NIV);
  rebuildIdx();
  const m=calcMetrics(PTS); MACT=m;
  renderMetrics(m); updateCotLink(m); fillTable(PTS);
  draw();
  statusEl.textContent=`${PTS.length} pts filtrados · ${TRIS.length}△`;
}

/* ══════════════════════════════════════════════════════════════
   CÁLCULOS TOPOGRÁFICOS PROFESIONALES v3
   Herramientas: Filtro · Dist+Az · Perfil+Rasante · Área+Vol·3D
                 Cubicación multi-sección · Histograma pendientes
                 Coordenadas/Estadísticas · Buscar
══════════════════════════════════════════════════════════════ */

/* ─── Labels de tabs ─── */
const TAB_LABELS = {
  filtro: 'Filtrar por capas',
  distaz: 'Distancia · Azimut · Ángulo cenital',
  perfil: 'Perfil longitudinal con rasante',
  area:   'Área y Volumen · Gauss · Prismoide · 3D',
  corte:  'Cubicación C/R multi-sección',
  pend:   'Pendiente · Histograma · IGAC',
  coordi: 'Consulta y conversión de coordenadas',
  buscar: 'Buscar y centrar punto',
};

/* ══════════════════════════════════════════════
   TOOLKIT PRO — navegación por cards
══════════════════════════════════════════════ */

function openTool(toolId) {
  const grid    = document.getElementById("tkGrid");
  const panes   = document.getElementById("tkPanes");
  const header  = document.getElementById("tkPaneHeader");
  const title   = document.getElementById("tkPaneTitle");
  const cats    = document.getElementById("tkCats");
  const search  = document.querySelector(".tk-search-wrap");
  const catbar  = document.getElementById("tkCats");
  const zona    = document.getElementById("zonaSelector");

  document.querySelectorAll(".calc-pane").forEach(p => p.classList.remove("active"));
  const pane = document.getElementById("tab-" + toolId);
  if (!pane) return;
  pane.classList.add("active");

  grid.style.display = "none";
  catbar.style.display = "none";
  search.style.display = "none";
  panes.style.display  = "block";
  header.style.display = "flex";

  const card = document.querySelector(`.tk-card[data-tool="${toolId}"]`);
  title.textContent = card ? card.querySelector(".tk-card-title").textContent.replace("NUEVO","").trim() : toolId;

  // Zone badge
  const zoneBadge = document.getElementById("tkZoneBadge");
  if (zoneBadge) zoneBadge.style.display = _ZONA_ACTIVA ? "block" : "none";

  // Area tool hint
  const areaHint = document.getElementById("areaZoneHint");
  if (areaHint) areaHint.style.display = _ZONA_ACTIVA ? "flex" : "none";
}

function closeTool() {
  const grid   = document.getElementById("tkGrid");
  const panes  = document.getElementById("tkPanes");
  const header = document.getElementById("tkPaneHeader");
  const catbar = document.getElementById("tkCats");
  const search = document.querySelector(".tk-search-wrap");

  document.querySelectorAll(".calc-pane").forEach(p => p.classList.remove("active"));
  grid.style.display   = "";
  catbar.style.display = "";
  search.style.display = "";
  panes.style.display  = "none";
  header.style.display = "none";
  filterToolkitCards("", "all");
}

// Card click → open tool
document.querySelectorAll(".tk-card").forEach(card => {
  card.addEventListener("click", () => openTool(card.dataset.tool));
});

// Back button
document.getElementById("tkBackBtn")?.addEventListener("click", closeTool);

// Category filter
let _activeCat = "all";
document.querySelectorAll(".tk-cat").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".tk-cat").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    _activeCat = btn.dataset.cat;
    filterToolkitCards(document.getElementById("tkSearch")?.value || "", _activeCat);
  });
});

// Search
document.getElementById("tkSearch")?.addEventListener("input", e => {
  filterToolkitCards(e.target.value, _activeCat);
});
document.getElementById("tkSearch")?.addEventListener("keydown", e => {
  if (e.key === "Escape") { e.target.value = ""; filterToolkitCards("", _activeCat); }
});

function filterToolkitCards(query, cat) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll(".tk-card").forEach(card => {
    const matchCat = cat === "all" || card.dataset.cat === cat;
    const matchQ   = !q || card.dataset.keywords.includes(q)
                       || card.querySelector(".tk-card-title").textContent.toLowerCase().includes(q);
    card.classList.toggle("hidden", !(matchCat && matchQ));
  });
}

/* ══════════════════════════════════════════════
   ZONA DE ANÁLISIS — selector integrado
══════════════════════════════════════════════ */

let _ZONA_ACTIVA = null; // {pts_indices, area, n, vol}

document.getElementById("btnToggleZona")?.addEventListener("click", () => {
  const btn = document.getElementById("btnToggleZona");
  if (_ZONA_ACTIVA) {
    limpiarZonaAnalisis();
    return;
  }
  // Indicar al visor que entre en modo "dibujo de zona"
  btn.classList.add("active");
  btn.textContent = "Dibujando...";
  toast("Haz clic en el plano para marcar vértices · Doble clic para cerrar", "success");
  // Trigger draw mode if viewer supports it
  if (typeof window.startZonaDraw === "function") window.startZonaDraw(onZonaDefinida);
  else toast("Dibuja el polígono en el plano 2D", "success");
});

document.getElementById("btnZonaClear")?.addEventListener("click", limpiarZonaAnalisis);

function limpiarZonaAnalisis() {
  _ZONA_ACTIVA = null;
  const btn = document.getElementById("btnToggleZona");
  if (btn) { btn.classList.remove("active"); btn.textContent = "Definir"; }
  const bar  = document.getElementById("zonaSelectorBar");
  const sub  = document.getElementById("zonaSelectorSub");
  if (bar) bar.style.display = "none";
  if (sub) sub.textContent = "Todo el terreno";
  if (typeof window.clearZonaOverlay === "function") window.clearZonaOverlay();
}

function onZonaDefinida(zonaData) {
  // Called by viewer when polygon is closed
  _ZONA_ACTIVA = zonaData;
  const btn = document.getElementById("btnToggleZona");
  if (btn) { btn.classList.remove("active"); btn.textContent = "Cambiar"; }
  const sub = document.getElementById("zonaSelectorSub");
  if (sub) sub.textContent = `${zonaData.n} pts · ${fmt(zonaData.area)} m²`;
  const bar = document.getElementById("zonaSelectorBar");
  if (bar) bar.style.display = "flex";
  const zsArea = document.getElementById("zsArea");
  const zsN    = document.getElementById("zsN");
  const zsVol  = document.getElementById("zsVol");
  if (zsArea) zsArea.textContent = fmt(zonaData.area);
  if (zsN)    zsN.textContent    = zonaData.n;
  if (zsVol)  zsVol.textContent  = fmt(zonaData.vol);
  toast(`⬡ Zona definida · ${zonaData.n} puntos · ${fmt(zonaData.area)} m²`, "success");
}

/* ─── Filtro por capas ─── */
document.getElementById("btnFiltrarDesc")?.addEventListener("click", () => {
  if (!SEL_DESC.size) { toast("Selecciona al menos un código", "error"); return; }
  const f = ORIG.filter(p => SEL_DESC.has(p.desc));
  if (!f.length) { toast("Sin puntos con ese código", "error"); return; }
  applyFilter(f);
  toast(`✓ ${f.length} pts · ${[...SEL_DESC].join(", ")}`, "success");
});

function applyFilter(pts) {
  PTS = pts;
  ZMIN = Math.min(...PTS.map(p => p.z));
  ZMAX = Math.max(...PTS.map(p => p.z));
  CX = PTS.reduce((s, p) => s + p.x, 0) / PTS.length;
  CY = PTS.reduce((s, p) => s + p.y, 0) / PTS.length;
  CZ = PTS.reduce((s, p) => s + p.z, 0) / PTS.length;
  const mx = Math.max(...PTS.map(p => Math.hypot(p.x - CX, p.y - CY)));
  SCL = mx > 0 ? Math.min(CVS.width, CVS.height) * 0.42 / mx : 1;
  TRIS = delaunay(PTS.map(p => ({ x: p.x, y: p.y })));
  ISO  = buildIso(PTS, TRIS, NIV);
  rebuildIdx();
  const m = calcMetrics(PTS); MACT = m;
  renderMetrics(m); updateCotLink(m); fillTable(PTS);
  draw();
  statusEl.textContent = `${PTS.length} pts filtrados · ${TRIS.length}△`;
}

/* ════════════════════════════════════════════
   UTILIDADES CANVAS
════════════════════════════════════════════ */
function mkCanvas(id, h) {
  const cv = document.getElementById(id);
  if (!cv) return null;
  cv.width  = cv.offsetWidth || 240;
  cv.height = h;
  const ctx = cv.getContext("2d");
  ctx.fillStyle = "#060A12";
  ctx.fillRect(0, 0, cv.width, cv.height);
  return { cv, ctx, W: cv.width, H: h };
}

function drawAxes(ctx, PAD, W, H, ticks = 4) {
  ctx.strokeStyle = "rgba(255,255,255,.08)";
  ctx.lineWidth   = 0.4;
  for (let i = 0; i <= ticks; i++) {
    const y = PAD.t + (H - PAD.t - PAD.b) * i / ticks;
    ctx.beginPath(); ctx.moveTo(PAD.l, y); ctx.lineTo(W - PAD.r, y); ctx.stroke();
  }
  ctx.strokeStyle = "rgba(255,255,255,.14)"; ctx.lineWidth = 0.6;
  ctx.beginPath(); ctx.moveTo(PAD.l, PAD.t); ctx.lineTo(PAD.l, H - PAD.b); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(PAD.l, H - PAD.b); ctx.lineTo(W - PAD.r, H - PAD.b); ctx.stroke();
}

/* ════════════════════════════════════════════
   1. DISTANCIA + AZIMUT + ÁNGULO CENITAL
════════════════════════════════════════════ */
function azToDMS(d) {
  const deg = Math.floor(d);
  const mf  = (d - deg) * 60;
  const min = Math.floor(mf);
  const sec = ((mf - min) * 60).toFixed(2);
  return `${deg}° ${String(min).padStart(2,"0")}' ${String(sec).padStart(5,"0")}"`;
}
function azRumbo(az) {
  const Q = [["N","E"],["S","E"],["S","O"],["N","O"]];
  const qi = Math.floor(az / 90) % 4;
  return `${Q[qi][0]} ${(az % 90).toFixed(4)}° ${Q[qi][1]}`;
}

function drawRosaBrujula(id, az) {
  const m = mkCanvas(id, 128); if (!m) return;
  const { ctx, W, H } = m;
  const cx = W * 0.38, cy = H / 2, R = 44;

  // Círculos concéntricos decorativos
  [R, R * 0.65, R * 0.3].forEach((r, i) => {
    ctx.strokeStyle = `rgba(0,229,192,${0.08 + i * 0.04})`;
    ctx.lineWidth = 0.5;
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();
  });

  // Ticks de graduación
  ctx.strokeStyle = "rgba(0,229,192,.18)"; ctx.lineWidth = 0.6;
  for (let a = 0; a < 360; a += 10) {
    const r = (a - 90) * Math.PI / 180;
    const inner = a % 30 === 0 ? R - 9 : R - 5;
    ctx.beginPath();
    ctx.moveTo(cx + Math.cos(r) * inner, cy + Math.sin(r) * inner);
    ctx.lineTo(cx + Math.cos(r) * R,     cy + Math.sin(r) * R);
    ctx.stroke();
  }

  // Cardinales
  ctx.font = "bold 9px 'DM Mono',monospace"; ctx.textAlign = "center";
  [["N", 0, "#ef4444"], ["E", 90, "rgba(0,229,192,.7)"],
   ["S", 180, "rgba(0,229,192,.45)"], ["O", 270, "rgba(0,229,192,.45)"]].forEach(([l, a, c]) => {
    const r = (a - 90) * Math.PI / 180;
    ctx.fillStyle = c;
    ctx.fillText(l, cx + Math.cos(r) * (R + 13), cy + Math.sin(r) * (R + 13) + 3);
  });

  // Norte (rojo punteado)
  const nr = -Math.PI / 2;
  ctx.strokeStyle = "rgba(239,68,68,.45)"; ctx.lineWidth = 1.2; ctx.setLineDash([2, 3]);
  ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(nr) * (R + 1), cy + Math.sin(nr) * (R + 1));
  ctx.stroke(); ctx.setLineDash([]);

  // Flecha de azimut (gradiente teal)
  const rad = (az - 90) * Math.PI / 180;
  const ex  = cx + Math.cos(rad) * R;
  const ey  = cy + Math.sin(rad) * R;
  const grd = ctx.createLinearGradient(cx, cy, ex, ey);
  grd.addColorStop(0, "rgba(0,229,192,.15)"); grd.addColorStop(1, "#00e5c0");
  ctx.strokeStyle = grd; ctx.lineWidth = 2.5;
  ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(ex, ey); ctx.stroke();
  // Punta
  const ah = 0.42;
  ctx.fillStyle = "#00e5c0"; ctx.beginPath();
  ctx.moveTo(ex, ey);
  ctx.lineTo(ex + Math.cos(rad + Math.PI + ah) * 11, ey + Math.sin(rad + Math.PI + ah) * 11);
  ctx.lineTo(ex + Math.cos(rad + Math.PI - ah) * 11, ey + Math.sin(rad + Math.PI - ah) * 11);
  ctx.closePath(); ctx.fill();

  // Panel derecho de datos
  const px = W * 0.64;
  const rows = [
    [`Az: ${az.toFixed(4)}°`, "#e8edf5", "11px 'Syne',sans-serif"],
    [azToDMS(az),              "rgba(0,229,192,.7)", "9px 'DM Mono',monospace"],
    [azRumbo(az),              "rgba(0,229,192,.5)", "9px 'DM Mono',monospace"],
    [`⟲ ${((az+180)%360).toFixed(2)}°`, "rgba(255,255,255,.3)", "8px 'DM Mono',monospace"],
  ];
  rows.forEach(([txt, color, font], i) => {
    ctx.font = font; ctx.fillStyle = color; ctx.textAlign = "left";
    ctx.fillText(txt, px, 20 + i * 16);
  });
}

document.getElementById("btnCalcDist")?.addEventListener("click", () => {
  const a = getByN(document.getElementById("calcDistA")?.value);
  const b = getByN(document.getElementById("calcDistB")?.value);
  if (!a || !b) { toast("Punto(s) no encontrado(s)", "error"); return; }

  const dx = b.x - a.x, dy = b.y - a.y, dz = b.z - a.z;
  const dH  = Math.hypot(dx, dy);
  const d3  = Math.hypot(dx, dy, dz);
  const az  = ((Math.atan2(dx, dy) * 180 / Math.PI) + 360) % 360;
  const caz = (az + 180) % 360;
  const pct = dH > 0 ? Math.abs(dz) / dH * 100 : 0;
  const grd = Math.atan(Math.abs(dz) / (dH || 1e-9)) * 180 / Math.PI;
  const cen = d3 > 0 ? Math.acos(Math.min(1, Math.abs(dz) / d3)) * 180 / Math.PI : 90;

  set("rDistH",       dH.toFixed(4) + " m");
  set("rDist3D",      d3.toFixed(4) + " m");
  set("rDistDZ",      (dz >= 0 ? "+" : "") + dz.toFixed(4) + " m");
  set("rDistPend",    pct.toFixed(4) + "% (" + grd.toFixed(3) + "°)");
  set("rDistCenital", cen.toFixed(4) + "° — " + azToDMS(cen));
  set("rDistAz",      azToDMS(az) + " (" + az.toFixed(4) + "°)");
  set("rDistRumbo",   azRumbo(az));
  set("rDistContraAz", azToDMS(caz) + " (" + caz.toFixed(4) + "°)");
  set("rDistMedio",   `${((a.x+b.x)/2).toFixed(3)}, ${((a.y+b.y)/2).toFixed(3)}, ${((a.z+b.z)/2).toFixed(3)}`);

  document.getElementById("resDistancia").style.display = "block";
  RESH = [a, b]; draw();
  setTimeout(() => drawRosaBrujula("miniCanvasAzimut", az), 50);
  toast(`D: ${dH.toFixed(3)} m · Az: ${az.toFixed(2)}° · Cenital: ${cen.toFixed(2)}°`, "success");
});

/* ════════════════════════════════════════════
   2. PERFIL LONGITUDINAL + RASANTE
════════════════════════════════════════════ */
function drawPerfil(pts, rZ1, rZ2) {
  const m = mkCanvas("miniCanvasPerfil", 130); if (!m || !pts.length) return;
  const { ctx, W, H } = m;
  const PAD = { t: 14, r: 12, b: 28, l: 44 };

  // Distancias acumuladas
  const dist = [0];
  for (let i = 1; i < pts.length; i++)
    dist.push(dist[i-1] + Math.hypot(pts[i].x - pts[i-1].x, pts[i].y - pts[i-1].y));
  const dMax = dist[dist.length - 1] || 1;

  // Rango Z con margen
  const zs  = pts.map(p => p.z);
  let zAll  = [...zs];
  if (rZ1 != null) zAll.push(rZ1, rZ2);
  const zMin = Math.min(...zAll), zMax = Math.max(...zAll);
  const zRng = (zMax - zMin) || 1;
  const zLo  = zMin - zRng * 0.10, zHi = zMax + zRng * 0.08;

  const tX = d  => PAD.l + (d / dMax)  * (W - PAD.l - PAD.r);
  const tY = z  => H - PAD.b - ((z - zLo) / (zHi - zLo)) * (H - PAD.t - PAD.b);

  drawAxes(ctx, PAD, W, H);

  // Etiquetas eje Y
  ctx.font = "7px 'DM Mono',monospace"; ctx.textAlign = "right"; ctx.fillStyle = "rgba(255,255,255,.35)";
  [zMin, (zMin + zMax) / 2, zMax].forEach(z => ctx.fillText(z.toFixed(1), PAD.l - 3, tY(z) + 3));

  // Etiquetas eje X (distancia)
  ctx.textAlign = "center"; ctx.fillStyle = "rgba(0,229,192,.4)";
  [0, dMax / 2, dMax].forEach(d => ctx.fillText(d.toFixed(0) + "m", tX(d), H - 4));

  // Relleno C/R si hay rasante
  if (rZ1 != null && rZ2 != null) {
    for (let i = 0; i < pts.length - 1; i++) {
      const x0 = tX(dist[i]), x1 = tX(dist[i+1]);
      const rasA = rZ1 + (rZ2 - rZ1) * (dist[i]   / dMax);
      const rasB = rZ1 + (rZ2 - rZ1) * (dist[i+1] / dMax);
      const yTA = tY(pts[i].z), yTB = tY(pts[i+1].z);
      const yRA = tY(rasA),     yRB = tY(rasB);
      const isCorte = rasA < pts[i].z; // terreno sobre rasante = corte
      ctx.fillStyle = isCorte ? "rgba(239,68,68,.2)" : "rgba(34,197,94,.2)";
      ctx.beginPath();
      ctx.moveTo(x0, yTA); ctx.lineTo(x1, yTB);
      ctx.lineTo(x1, yRB); ctx.lineTo(x0, yRA);
      ctx.closePath(); ctx.fill();
    }
  }

  // Relleno bajo terreno
  const grad = ctx.createLinearGradient(0, PAD.t, 0, H - PAD.b);
  grad.addColorStop(0, "rgba(0,229,192,.22)"); grad.addColorStop(1, "rgba(0,229,192,.01)");
  ctx.beginPath(); ctx.moveTo(tX(0), H - PAD.b);
  pts.forEach((p, i) => ctx.lineTo(tX(dist[i]), tY(p.z)));
  ctx.lineTo(tX(dMax), H - PAD.b); ctx.closePath();
  ctx.fillStyle = grad; ctx.fill();

  // Línea de terreno
  ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 2;
  ctx.beginPath();
  pts.forEach((p, i) => i === 0 ? ctx.moveTo(tX(dist[i]), tY(p.z)) : ctx.lineTo(tX(dist[i]), tY(p.z)));
  ctx.stroke();

  // Rasante
  if (rZ1 != null && rZ2 != null) {
    ctx.strokeStyle = "#f59e0b"; ctx.lineWidth = 1.8; ctx.setLineDash([6, 3]);
    ctx.beginPath(); ctx.moveTo(tX(0), tY(rZ1)); ctx.lineTo(tX(dMax), tY(rZ2)); ctx.stroke();
    ctx.setLineDash([]);
    // Leyenda
    ctx.font = "8px 'DM Mono',monospace"; ctx.textAlign = "right";
    ctx.fillStyle = "rgba(245,158,11,.65)"; ctx.fillText("— rasante", W - PAD.r, PAD.t - 2);
  }

  // Puntos de quiebre de pendiente máxima
  let pMax = 0, iMax = 1;
  for (let i = 1; i < pts.length; i++) {
    const dh = dist[i] - dist[i-1];
    if (dh > 0) { const p = Math.abs(pts[i].z - pts[i-1].z) / dh * 100; if (p > pMax) { pMax = p; iMax = i; } }
  }
  if (pMax > 0) {
    const px = tX(dist[iMax]), py = tY(pts[iMax].z);
    ctx.strokeStyle = "#f59e0b"; ctx.lineWidth = 1; ctx.setLineDash([2,2]);
    ctx.beginPath(); ctx.moveTo(px, PAD.t); ctx.lineTo(px, H - PAD.b); ctx.stroke(); ctx.setLineDash([]);
    ctx.font = "7px 'DM Mono',monospace"; ctx.fillStyle = "#f59e0b"; ctx.textAlign = "center";
    ctx.fillText(pMax.toFixed(1)+"%", px, PAD.t + 8);
  }
}

document.getElementById("btnCalcPerfil")?.addEventListener("click", () => {
  const d = parseInt(document.getElementById("calcPerfilDesde")?.value);
  const h = parseInt(document.getElementById("calcPerfilHasta")?.value);
  if (isNaN(d) || isNaN(h) || d >= h) { toast("Rango inválido", "error"); return; }

  const rng = ORIG.filter(p => p.n != null && p.n >= d && p.n <= h).sort((a, b) => a.n - b.n);
  if (rng.length < 2) { toast("Menos de 2 puntos en el rango", "error"); return; }

  let L = 0;
  for (let i = 1; i < rng.length; i++)
    L += Math.hypot(rng[i].x - rng[i-1].x, rng[i].y - rng[i-1].y);

  const zs = rng.map(p => p.z);
  const zMn = Math.min(...zs), zMx = Math.max(...zs);
  const pMedia = L > 0 ? (zMx - zMn) / L * 100 : 0;
  const clasifP = pMedia < 3 ? "Plano" : pMedia < 8 ? "Ondulado" : pMedia < 15 ? "Montañoso" : "Escarpado";

  // Pendiente máxima por tramo
  let pMax = 0, pMaxLabel = "";
  for (let i = 1; i < rng.length; i++) {
    const dh = Math.hypot(rng[i].x - rng[i-1].x, rng[i].y - rng[i-1].y);
    if (dh > 0) {
      const p = Math.abs(rng[i].z - rng[i-1].z) / dh * 100;
      if (p > pMax) { pMax = p; pMaxLabel = `N°${rng[i-1].n}→${rng[i].n}`; }
    }
  }

  set("rPerfilN",        rng.length);
  set("rPerfilLong",     L.toFixed(4) + " m");
  set("rPerfilZmin",     zMn.toFixed(4) + " m");
  set("rPerfilZmax",     zMx.toFixed(4) + " m");
  set("rPerfilDesnivel", (zMx - zMn).toFixed(4) + " m");
  set("rPerfilPend",     pMedia.toFixed(4) + "% — " + clasifP);
  set("rPerfilPendMax",  pMax.toFixed(4) + "% (" + pMaxLabel + ")");

  // Rasante opcional
  const rZ1 = parseFloat(document.getElementById("perfilRasanteZ1")?.value);
  const rZ2 = parseFloat(document.getElementById("perfilRasanteZ2")?.value);
  const hasRas = !isNaN(rZ1) && !isNaN(rZ2);
  const prDiv = document.getElementById("resPendRasante");

  if (hasRas && prDiv) {
    prDiv.style.display = "block";
    const pendRas = L > 0 ? Math.abs(rZ2 - rZ1) / L * 100 : 0;
    let vC = 0, vR = 0, maxCR = 0;
    for (let i = 0; i < rng.length; i++) {
      const rasZ = rZ1 + (rZ2 - rZ1) * (i / (rng.length - 1 || 1));
      const cr = rasZ - rng[i].z;
      if (Math.abs(cr) > maxCR) maxCR = Math.abs(cr);
      // vol estimado con ancho 6m y equidistancia L/n
      const seg = L / rng.length;
      if (cr > 0) vR += cr * 6 * seg; else vC += Math.abs(cr) * 6 * seg;
    }
    set("rRasantePend",   pendRas.toFixed(4) + "%");
    set("rRasanteMaxCR",  maxCR.toFixed(4) + " m");
    set("rRasanteVolC",   vC.toFixed(2) + " m³ (banca 6m est.)");
    set("rRasanteVolR",   vR.toFixed(2) + " m³ (banca 6m est.)");
  } else if (prDiv) prDiv.style.display = "none";

  document.getElementById("resPerfil").style.display = "block";
  RESH = rng; draw();
  setTimeout(() => drawPerfil(rng, hasRas ? rZ1 : null, hasRas ? rZ2 : null), 50);
  toast(`Perfil ${rng.length} pts · Desn: ${(zMx-zMn).toFixed(2)} m · PMax: ${pMax.toFixed(1)}%`, "success");
});

/* ════════════════════════════════════════════
   3. ÁREA Y VOLUMEN (Gauss + 3D TIN + Prismoide)
════════════════════════════════════════════ */
function area3D_TIN(sel) {
  if (!TRIS.length || sel.length < 3) return 0;
  const setIdx = new Set(sel.map(p => PTS.indexOf(p)));
  let a3 = 0;
  for (const t of TRIS) {
    if (!setIdx.has(t.a) || !setIdx.has(t.b) || !setIdx.has(t.c)) continue;
    const A = PTS[t.a], B = PTS[t.b], C = PTS[t.c]; if (!A || !B || !C) continue;
    const ux = B.x-A.x, uy = B.y-A.y, uz = B.z-A.z;
    const vx = C.x-A.x, vy = C.y-A.y, vz = C.z-A.z;
    a3 += 0.5 * Math.hypot(uy*vz-uz*vy, uz*vx-ux*vz, ux*vy-uy*vx);
  }
  return a3;
}

function drawAreaPoly(sel) {
  const m = mkCanvas("miniCanvasArea", 100); if (!m || sel.length < 2) return;
  const { ctx, W, H } = m;
  const PAD = 16;
  const xs = sel.map(p => p.x), ys = sel.map(p => p.y);
  const x0 = Math.min(...xs), x1 = Math.max(...xs);
  const y0 = Math.min(...ys), y1 = Math.max(...ys);
  const rng = Math.max(x1 - x0, y1 - y0) || 1;
  const tX = x => PAD + (x - x0) / rng * (W - PAD * 2);
  const tY = y => (H - PAD) - (y - y0) / rng * (H - PAD * 2);

  // Triángulos de fondo (TIN visible)
  ctx.strokeStyle = "rgba(0,229,192,.07)"; ctx.lineWidth = 0.4;
  const setIdx = new Set(sel.map(p => PTS.indexOf(p)));
  for (const t of TRIS) {
    if (!setIdx.has(t.a) || !setIdx.has(t.b) || !setIdx.has(t.c)) continue;
    const A = PTS[t.a], B = PTS[t.b], C = PTS[t.c]; if (!A || !B || !C) continue;
    ctx.beginPath(); ctx.moveTo(tX(A.x), tY(A.y));
    ctx.lineTo(tX(B.x), tY(B.y)); ctx.lineTo(tX(C.x), tY(C.y)); ctx.closePath(); ctx.stroke();
  }
  // Polígono relleno
  ctx.beginPath(); ctx.moveTo(tX(sel[0].x), tY(sel[0].y));
  sel.slice(1).forEach(p => ctx.lineTo(tX(p.x), tY(p.y))); ctx.closePath();
  ctx.fillStyle = "rgba(0,229,192,.12)"; ctx.fill();
  ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 2; ctx.stroke();
  // Vértices
  sel.forEach((p, i) => {
    const px = tX(p.x), py = tY(p.y);
    ctx.beginPath(); ctx.arc(px, py, 3.5, 0, Math.PI * 2);
    ctx.fillStyle = "#00e5c0"; ctx.fill();
    ctx.font = "7px 'DM Mono',monospace"; ctx.fillStyle = "rgba(255,255,255,.5)";
    ctx.textAlign = "center"; ctx.fillText(p.n ?? i+1, px, py - 7);
  });
}

document.getElementById("btnCalcArea")?.addEventListener("click", () => {
  let sel = [];
  const txt = document.getElementById("calcAreaPuntos")?.value.trim();
  const d   = parseInt(document.getElementById("calcAreaDesde")?.value);
  const h   = parseInt(document.getElementById("calcAreaHasta")?.value);
  if (txt) sel = txt.split(",").map(v => getByN(v.trim())).filter(Boolean);
  else if (!isNaN(d) && !isNaN(h)) sel = ORIG.filter(p => p.n != null && p.n >= d && p.n <= h);
  if (sel.length < 3) { toast("Se necesitan ≥3 puntos", "error"); return; }

  const mt   = calcMetrics(sel);
  const zs   = sel.map(p => p.z);
  const zMed = zs.reduce((a, b) => a + b, 0) / zs.length;
  const zMn  = Math.min(...zs), zMx = Math.max(...zs);
  const a3D  = area3D_TIN(sel);
  // Volumen medio secciones (alternativo)
  const volMS = mt.area * (zMx - zMn) / 2;

  set("rAreaN",        sel.length);
  set("rAreaVal",      mt.area.toFixed(4) + " m²");
  set("rAreaHa",       (mt.area / 10000).toFixed(6) + " ha");
  set("rArea3D",       a3D > 0 ? a3D.toFixed(4) + " m²" : "(TIN insuf.)");
  set("rAreaPerim",    mt.perimetro.toFixed(4) + " m");
  set("rAreaVol",      mt.volumen.toFixed(4) + " m³");
  set("rAreaVolMedia", volMS.toFixed(4) + " m³");
  set("rAreaZmed",     zMed.toFixed(4) + " m");
  set("rAreaDesn",     (zMx - zMn).toFixed(4) + " m");

  document.getElementById("resArea").style.display = "block";
  RESH = sel; draw();
  setTimeout(() => drawAreaPoly(sel), 50);
  toast(`Área: ${mt.area.toFixed(2)} m² · ${(mt.area/10000).toFixed(4)} ha · Vol: ${mt.volumen.toFixed(2)} m³`, "success");
});

/* ════════════════════════════════════════════
   4. CUBICACIÓN MULTI-SECCIÓN C/R
════════════════════════════════════════════ */
// Añadir sección
document.getElementById("crAddSec")?.addEventListener("click", () => {
  const cont = document.getElementById("crSecciones");
  const rows = cont.querySelectorAll(".cr-sec-row");
  if (rows.length >= 6) { toast("Máximo 6 secciones", "error"); return; }
  const row = document.createElement("div"); row.className = "cr-sec-row";
  const n = rows.length + 1;
  row.innerHTML = `
    <div class="cfield" style="flex:.8"><label>Punto N°</label><input class="cr-pto" type="number" placeholder="${n*5}"></div>
    <div class="cfield"><label>Z proyecto (m)</label><input class="cr-zp" type="number" step="0.001"></div>
    <button class="cbtn-sec cr-del" style="padding:5px 8px;align-self:flex-end">✕</button>`;
  row.querySelector(".cr-del").addEventListener("click", () => row.remove());
  cont.appendChild(row);
});
// Botones eliminar iniciales
document.querySelectorAll(".cr-del").forEach(b => {
  if (b.style.display !== "none")
    b.addEventListener("click", () => b.closest(".cr-sec-row")?.remove());
});

function drawCubicacion(secs) {
  const m = mkCanvas("miniCanvasCorte", 110); if (!m || secs.length < 2) return;
  const { ctx, W, H } = m;
  const PAD = { t: 18, r: 10, b: 26, l: 10 };
  const n = secs.length;
  const crs = secs.map(s => s.cr);
  const mxA = Math.max(...crs.map(c => Math.abs(c)), 0.1);
  const base = H / 2;
  const tX  = i => PAD.l + (i / (n - 1)) * (W - PAD.l - PAD.r);
  const barMaxH = (H - PAD.t - PAD.b) / 2;

  // Sub-rasante
  ctx.strokeStyle = "rgba(245,158,11,.4)"; ctx.lineWidth = 1; ctx.setLineDash([4, 3]);
  ctx.beginPath(); ctx.moveTo(PAD.l, base); ctx.lineTo(W - PAD.r, base); ctx.stroke();
  ctx.setLineDash([]);
  ctx.font = "8px 'DM Mono',monospace"; ctx.textAlign = "right"; ctx.fillStyle = "rgba(245,158,11,.5)";
  ctx.fillText("sub-rasante", W - PAD.r, base - 3);

  // Barras por sección
  secs.forEach((s, i) => {
    const x    = tX(i);
    const bH   = Math.abs(s.cr) / mxA * barMaxH;
    const isC  = s.cr < 0;
    const col  = isC ? "#ef4444" : "#22c55e";

    const grd = ctx.createLinearGradient(0, isC ? base : base - bH, 0, isC ? base + bH : base);
    grd.addColorStop(0, col + "88"); grd.addColorStop(1, col + "22");
    ctx.fillStyle = grd;
    ctx.fillRect(x - 9, isC ? base : base - bH, 18, bH);
    ctx.strokeStyle = col; ctx.lineWidth = 1.5;
    ctx.strokeRect(x - 9, isC ? base : base - bH, 18, bH);

    // Valor
    ctx.fillStyle = col; ctx.font = "8px 'DM Mono',monospace"; ctx.textAlign = "center";
    ctx.fillText((s.cr >= 0 ? "+" : "") + s.cr.toFixed(2), x, isC ? base + bH + 10 : base - bH - 3);
    // N° punto
    ctx.fillStyle = "rgba(255,255,255,.4)"; ctx.font = "8px 'DM Mono',monospace";
    ctx.fillText("N°" + s.n, x, H - 4);
  });

  // Línea de terreno interpolada
  ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 2;
  ctx.beginPath();
  secs.forEach((s, i) => {
    const y = base - (s.cr / mxA) * barMaxH;
    i === 0 ? ctx.moveTo(tX(i), y) : ctx.lineTo(tX(i), y);
  });
  ctx.stroke();

  // Leyenda
  ctx.font = "8px 'DM Mono',monospace"; ctx.textAlign = "left";
  ctx.fillStyle = "rgba(239,68,68,.7)"; ctx.fillText("■ Corte", PAD.l + 2, H - PAD.b + 8);
  ctx.fillStyle = "rgba(34,197,94,.7)"; ctx.fillText("■ Relleno", PAD.l + 52, H - PAD.b + 8);
}

document.getElementById("btnCalcCorteRelleno")?.addEventListener("click", () => {
  const ancho   = parseFloat(document.getElementById("crAncho")?.value)   || 6;
  const distSec = parseFloat(document.getElementById("crDistSec")?.value) || 20;

  const rows = document.querySelectorAll("#crSecciones .cr-sec-row");
  const secs = [];
  for (const row of rows) {
    const pt  = getByN(row.querySelector(".cr-pto")?.value);
    const zp  = parseFloat(row.querySelector(".cr-zp")?.value);
    if (pt && !isNaN(zp)) secs.push({ pt, zp, n: pt.n, cr: zp - pt.z });
  }
  if (secs.length < 2) { toast("Ingresa ≥2 secciones con punto y cota de proyecto", "error"); return; }

  let vCorte = 0, vRell = 0;
  const tabla = [];

  secs.forEach((s, i) => {
    const aS = Math.abs(s.cr) * ancho;
    const tipo = s.cr < 0 ? "CORTE" : s.cr > 0 ? "RELLENO" : "NEUTRO";
    const row = { n: s.n, zT: s.pt.z, zP: s.zp, cr: s.cr, area: aS, tipo, vol: null };
    if (i > 0) {
      const aPrev = Math.abs(secs[i-1].cr) * ancho;
      const vol   = (aPrev + aS) / 2 * distSec;
      row.vol = vol;
      const prevC = secs[i-1].cr < 0, currC = s.cr < 0;
      if (prevC && currC)   vCorte += vol;
      else if (!prevC && !currC) vRell += vol;
      else { vCorte += vol / 2; vRell += vol / 2; }
    }
    tabla.push(row);
  });

  const totVol = vCorte + vRell;
  const balance = vRell - vCorte;
  const dominante = vCorte >= vRell ? "CORTE" : "RELLENO";

  const badge = document.getElementById("crTipoBadge");
  if (badge) badge.className = "cres-highlight " + (dominante === "CORTE" ? "cr-corte" : "cr-relleno");

  set("crTipoLabel",  dominante + " DOMINANTE");
  set("crVolumen",    totVol.toFixed(4) + " m³");
  set("crVolCorte",   vCorte.toFixed(4) + " m³");
  set("crVolRelleno", vRell.toFixed(4) + " m³");
  set("crBalance",    (balance >= 0 ? "+" : "") + balance.toFixed(4) + " m³ (" + (balance >= 0 ? "exceso relleno" : "exceso corte") + ")");
  set("crLongTotal",  ((secs.length - 1) * distSec).toFixed(1) + " m");

  // Tabla HTML de secciones
  const tb = document.getElementById("crTabla");
  if (tb) {
    const cols = "grid-template-columns:1.2fr 1.5fr 1.5fr 1.5fr 1.5fr 1.8fr 1.5fr";
    const hd   = `<div style="display:grid;${cols};gap:2px;background:rgba(0,0,100,.35);border-radius:4px 4px 0 0;
      padding:4px 6px;font:700 8px 'DM Sans';color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:1px;">
      <span>Sec.</span><span>Z terreno</span><span>Z proy.</span><span>Cota roja</span><span>Área secc.</span><span>Tipo</span><span>Vol. tramo</span></div>`;
    const bds = tabla.map(r => `<div style="display:grid;${cols};gap:2px;
      border-bottom:1px solid rgba(255,255,255,.03);padding:3px 6px;font-size:9px;align-items:center;">
      <span style="font-family:'DM Mono';color:#64748b">N°${r.n}</span>
      <span style="font-family:'DM Mono'">${r.zT.toFixed(3)}</span>
      <span style="font-family:'DM Mono'">${r.zP.toFixed(3)}</span>
      <span style="font-family:'DM Mono';color:${r.cr<0?"#ef4444":r.cr>0?"#22c55e":"#64748b"};font-weight:700">
        ${(r.cr >= 0 ? "+" : "") + r.cr.toFixed(3)} m</span>
      <span style="font-family:'DM Mono'">${r.area.toFixed(2)} m²</span>
      <span style="color:${r.tipo==="CORTE"?"#ef4444":r.tipo==="RELLENO"?"#22c55e":"#64748b"};font-weight:600">${r.tipo}</span>
      <span style="font-family:'DM Mono';color:#94a3b8">${r.vol != null ? r.vol.toFixed(2)+" m³" : "—"}</span>
    </div>`).join("");
    tb.innerHTML = hd + bds;
  }

  document.getElementById("resCorteRelleno").style.display = "block";
  RESH = secs.map(s => s.pt); draw();
  setTimeout(() => drawCubicacion(secs), 50);
  toast(`C: ${vCorte.toFixed(1)} m³ · R: ${vRell.toFixed(1)} m³ · Balance: ${balance.toFixed(1)} m³`, "success");
});

/* ════════════════════════════════════════════
   5. PENDIENTE + TABLA IGAC + HISTOGRAMA TIN
════════════════════════════════════════════ */
document.getElementById("btnCalcPend")?.addEventListener("click", () => {
  const a = getByN(document.getElementById("pendPtoA")?.value);
  const b = getByN(document.getElementById("pendPtoB")?.value);
  if (!a || !b) { toast("Punto(s) no encontrado(s)", "error"); return; }
  const dH = Math.hypot(b.x - a.x, b.y - a.y);
  if (dH < 1e-4) { toast("Puntos coincidentes o muy cercanos", "error"); return; }
  const dZ  = b.z - a.z;
  const pct = Math.abs(dZ) / dH * 100;
  const grd = Math.atan(Math.abs(dZ) / dH) * 180 / Math.PI;
  const hv  = Math.abs(dZ) > 1e-4 ? (dH / Math.abs(dZ)).toFixed(2) + "H:1V" : "— (plano)";

  const CLASES = [
    [0,3,   "Plano",              "tb-plano",     "No aplica"],
    [3,7,   "Ligeramente ondulado","tb-plano",    "1:4 – 1:3"],
    [7,12,  "Ondulado",           "tb-ondulado",  "1:2 – 1:3"],
    [12,25, "F. ondulado",        "tb-ondulado",  "1:1.5 – 1:2"],
    [25,50, "Quebrado",           "tb-quebrado",  "1:1 – 1:1.5"],
    [50,75, "Muy quebrado",       "tb-quebrado",  "3:4 – 1:1"],
    [75,999,"Escarpado",          "tb-escarpado", "Muros / enrocado"],
  ];
  const cls = CLASES.find(([lo,hi]) => pct >= lo && pct < hi) ?? CLASES[6];

  const badge = document.getElementById("pendBadge");
  const sub   = document.getElementById("pendBadgeSub");
  if (badge) { badge.textContent = cls[2]; badge.className = "pend-badge " + cls[3]; }
  if (sub)   sub.textContent = `${pct.toFixed(2)}% · ${grd.toFixed(2)}° · N°${a.n} → N°${b.n}`;

  set("rPendPct",   pct.toFixed(4) + " %");
  set("rPendGrad",  grd.toFixed(4) + "°");
  set("rPendDistH", dH.toFixed(4) + " m");
  set("rPendDZ",    (dZ >= 0 ? "+" : "") + dZ.toFixed(4) + " m");
  set("rPendTalud", cls[4]);
  set("rPendHV",    hv);

  document.querySelectorAll(".pt-row").forEach(row => {
    const lo = parseFloat(row.dataset.min), hi = parseFloat(row.dataset.max);
    row.classList.toggle("active-row", pct >= lo && pct < hi);
  });

  document.getElementById("resPend").style.display = "block";
  RESH = [a, b]; draw();
  toast(`${pct.toFixed(2)}% · ${cls[2]} · Talud: ${cls[4]}`, "success");
});

document.getElementById("btnHistoPend")?.addEventListener("click", () => {
  if (!TRIS.length) { toast("No hay triangulación — carga un CSV primero", "error"); return; }

  // Pendiente de cada triángulo por normal de plano
  const pends = [];
  for (const t of TRIS) {
    const A = PTS[t.a], B = PTS[t.b], C = PTS[t.c]; if (!A || !B || !C) continue;
    const ux = B.x-A.x, uy = B.y-A.y, uz = B.z-A.z;
    const vx = C.x-A.x, vy = C.y-A.y, vz = C.z-A.z;
    const nx = uy*vz - uz*vy, ny = uz*vx - ux*vz, nz = ux*vy - uy*vx;
    const nm = Math.hypot(nx, ny, nz);
    if (nm < 1e-10) continue;
    const cosZ = Math.abs(nz) / nm;
    const ang  = Math.acos(Math.min(1, cosZ));
    const pct  = Math.tan(ang) * 100;
    if (isFinite(pct)) pends.push(pct);
  }
  if (!pends.length) { toast("No se pudo calcular pendientes del TIN", "error"); return; }

  pends.sort((a, b) => a - b);
  const n    = pends.length;
  const mean = pends.reduce((a, b) => a + b, 0) / n;
  const p10  = pends[Math.floor(n * 0.10)];
  const p25  = pends[Math.floor(n * 0.25)];
  const p50  = pends[Math.floor(n * 0.50)];
  const p75  = pends[Math.floor(n * 0.75)];
  const p90  = pends[Math.floor(n * 0.90)];
  const pMax = pends[n - 1];
  const std  = Math.sqrt(pends.reduce((s, v) => s + (v - mean) ** 2, 0) / n);

  // Distribución por clase
  const CLS = [
    [0, 3, "Plano"],
    [3, 12, "Ondulado"],
    [12, 50, "Quebrado"],
    [50, 999, "Escarpado"]
  ];
  const dist = CLS.map(([lo, hi]) => ({ lo, hi, n: pends.filter(p => p >= lo && p < hi).length }));

  const sg = document.getElementById("histoStats");
  if (sg) sg.innerHTML = `
    <div class="cres-cell accent"><span class="cres-lbl">Media</span><span class="cres-val accent">${mean.toFixed(2)}%</span></div>
    <div class="cres-cell"><span class="cres-lbl">Mediana P50</span><span class="cres-val">${p50.toFixed(2)}%</span></div>
    <div class="cres-cell"><span class="cres-lbl">P10 / P25</span><span class="cres-val">${p10.toFixed(1)} / ${p25.toFixed(1)}%</span></div>
    <div class="cres-cell"><span class="cres-lbl">P75 / P90</span><span class="cres-val">${p75.toFixed(1)} / ${p90.toFixed(1)}%</span></div>
    <div class="cres-cell"><span class="cres-lbl">Desv. estándar</span><span class="cres-val">${std.toFixed(2)}%</span></div>
    <div class="cres-cell"><span class="cres-lbl">Máxima (TIN)</span><span class="cres-val">${pMax.toFixed(2)}%</span></div>
    ${dist.map(d => `<div class="cres-cell"><span class="cres-lbl">${d.lo}–${d.hi === 999 ? ">" + d.lo : d.hi}%</span>
      <span class="cres-val">${(d.n/n*100).toFixed(1)}% (${d.n}△)</span></div>`).join("")}`;

  document.getElementById("resHistoPend").style.display = "block";
  setTimeout(() => drawHistograma(pends), 50);
  toast(`Histograma: ${n.toLocaleString("es-CO")} △ · Media ${mean.toFixed(1)}% · P50 ${p50.toFixed(1)}%`, "success");
});

function drawHistograma(pends) {
  const m = mkCanvas("miniCanvasHisto", 110); if (!m) return;
  const { ctx, W, H } = m;
  const PAD = { t: 14, r: 8, b: 28, l: 38 };

  const BINS   = [0, 3, 7, 12, 25, 50, 75, 150];
  const LABELS = ["0–3", "3–7", "7–12", "12–25", "25–50", "50–75", ">75"];
  const COLORS = ["#22c55e", "#86efac", "#fbbf24", "#f97316", "#ef4444", "#dc2626", "#991b1b"];

  const counts = new Array(BINS.length - 1).fill(0);
  pends.forEach(p => {
    for (let i = 0; i < BINS.length - 1; i++)
      if (p >= BINS[i] && p < BINS[i+1]) { counts[i]++; break; }
  });
  const maxC = Math.max(...counts) || 1;
  const bw   = (W - PAD.l - PAD.r) / counts.length;

  drawAxes(ctx, PAD, W, H);

  // Escala Y
  ctx.font = "7px 'DM Mono',monospace"; ctx.textAlign = "right"; ctx.fillStyle = "rgba(255,255,255,.3)";
  [0, 0.25, 0.5, 0.75, 1].forEach(f => {
    const y = H - PAD.b - f * (H - PAD.t - PAD.b);
    ctx.fillText(Math.round(maxC * f), PAD.l - 2, y + 3);
  });

  // Barras
  counts.forEach((c, i) => {
    const x   = PAD.l + i * bw + bw * 0.08;
    const barW = bw * 0.84;
    const barH = (c / maxC) * (H - PAD.t - PAD.b);
    const y   = H - PAD.b - barH;

    const grd = ctx.createLinearGradient(0, y, 0, H - PAD.b);
    grd.addColorStop(0, COLORS[i]); grd.addColorStop(1, COLORS[i] + "33");
    ctx.fillStyle = grd;
    if (ctx.roundRect) ctx.roundRect(x, y, barW, barH, [3, 3, 0, 0]);
    else ctx.rect(x, y, barW, barH);
    ctx.fill();

    // Porcentaje encima
    if (c > 0) {
      const pct = (c / pends.length * 100).toFixed(0);
      ctx.font = "7px 'DM Mono',monospace"; ctx.fillStyle = "rgba(255,255,255,.75)";
      ctx.textAlign = "center"; ctx.fillText(pct + "%", x + barW / 2, y - 2);
    }
    // Label eje X
    ctx.fillStyle = "rgba(255,255,255,.35)"; ctx.font = "7px 'DM Mono',monospace";
    ctx.fillText(LABELS[i], x + barW / 2, H - 4);
  });

  // Línea de media (percentil 50)
  const n   = pends.length;
  const p50 = pends[Math.floor(n * 0.5)];
  const BINS_MIDS = BINS.slice(0, -1).map((b, i) => (b + BINS[i+1]) / 2);
  // Aproximar posición X de P50
  let cumSum = 0;
  for (let i = 0; i < counts.length; i++) {
    cumSum += counts[i];
    if (cumSum / n >= 0.5) {
      const xLine = PAD.l + i * bw + bw / 2;
      ctx.strokeStyle = "rgba(245,158,11,.65)"; ctx.lineWidth = 1.5; ctx.setLineDash([3, 3]);
      ctx.beginPath(); ctx.moveTo(xLine, PAD.t); ctx.lineTo(xLine, H - PAD.b); ctx.stroke();
      ctx.setLineDash([]);
      ctx.font = "7px 'DM Mono',monospace"; ctx.fillStyle = "rgba(245,158,11,.8)";
      ctx.textAlign = "center"; ctx.fillText("P50", xLine, PAD.t + 7);
      break;
    }
  }
}

/* ════════════════════════════════════════════
   6. CONSULTA DE COORDENADAS
════════════════════════════════════════════ */
document.getElementById("btnCoordFicha")?.addEventListener("click", () => {
  const p = getByN(document.getElementById("coordPtoN")?.value);
  if (!p) { toast("Punto no encontrado", "error"); return; }
  const idx  = PTS.indexOf(p);
  const totP = PTS.length;
  set("fichaNum",      " " + p.n);
  set("fichaCota",     p.z.toFixed(4) + " m");
  set("fichaX",        p.x.toFixed(4) + " m");
  set("fichaY",        p.y.toFixed(4) + " m");
  set("fichaZ",        p.z.toFixed(4) + " m s.n.m.");
  set("fichaDesc",     p.desc || "—");
  set("fichaCotaRel",  ZMIN != null ? (p.z - ZMIN).toFixed(4) + " m s/ mín" : "—");
  set("fichaPosicion", idx >= 0 ? `${idx+1}/${totP} (${((idx+1)/totP*100).toFixed(1)}%)` : "—");
  document.getElementById("resCoordFicha").style.display = "block";
  const pp = proj(p); PX += (CVS.width / 2 - pp.sx); PY += (CVS.height / 2 - pp.sy);
  RESH = [p]; draw();
  toast(`N°${p.n} · X:${p.x.toFixed(3)} Y:${p.y.toFixed(3)} Z:${p.z.toFixed(4)}`, "success");
});

document.getElementById("btnCoordStats")?.addEventListener("click", () => {
  if (!PTS.length) { toast("No hay puntos cargados", "error"); return; }
  const xs = [...PTS.map(p => p.x)].sort((a,b) => a-b);
  const ys = [...PTS.map(p => p.y)].sort((a,b) => a-b);
  const zs = [...PTS.map(p => p.z)].sort((a,b) => a-b);
  const n  = PTS.length;
  const avg = arr => arr.reduce((a,b) => a+b, 0) / arr.length;
  const std = arr => { const m = avg(arr); return Math.sqrt(arr.reduce((s,v) => s + (v-m)**2, 0) / arr.length); };
  const ptl = (arr, q) => arr[Math.floor(arr.length * q)];

  const grid = document.getElementById("coordStatsGrid");
  if (grid) grid.innerHTML = `
    <div class="cres-cell accent"><span class="cres-lbl">Puntos totales</span><span class="cres-val accent">${n.toLocaleString("es-CO")}</span></div>
    <div class="cres-cell"><span class="cres-lbl">Zmin / Zmax</span><span class="cres-val">${zs[0].toFixed(3)} / ${zs[n-1].toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Z media ± σ</span><span class="cres-val">${avg(zs).toFixed(3)} ± ${std(zs).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Z mediana P50</span><span class="cres-val">${ptl(zs,.5).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Z P25 / P75</span><span class="cres-val">${ptl(zs,.25).toFixed(3)} / ${ptl(zs,.75).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Rango IQR (Z)</span><span class="cres-val">${(ptl(zs,.75)-ptl(zs,.25)).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">X rango (ΔX)</span><span class="cres-val">${(xs[n-1]-xs[0]).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Y rango (ΔY)</span><span class="cres-val">${(ys[n-1]-ys[0]).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">X centroide</span><span class="cres-val">${avg(xs).toFixed(3)} m</span></div>
    <div class="cres-cell"><span class="cres-lbl">Y centroide</span><span class="cres-val">${avg(ys).toFixed(3)} m</span></div>`;
  document.getElementById("resCoordStats").style.display = "block";
  toast(`Estadísticas de ${n.toLocaleString("es-CO")} puntos`, "success");
});

document.getElementById("btnCoordBuscarXY")?.addEventListener("click", () => {
  const bx = parseFloat(document.getElementById("coordBuscarX")?.value);
  const by = parseFloat(document.getElementById("coordBuscarY")?.value);
  if (isNaN(bx) || isNaN(by)) { toast("Ingresa coordenadas X e Y", "error"); return; }
  let best = null, minD = Infinity;
  for (const p of PTS) {
    const d = Math.hypot(p.x - bx, p.y - by);
    if (d < minD) { minD = d; best = p; }
  }
  if (!best) { toast("Sin puntos", "error"); return; }
  set("rCercanoN",   "N° " + best.n);
  set("rCercanoD",   minD.toFixed(4) + " m");
  set("rCercanoXYZ", `${best.x.toFixed(3)},  ${best.y.toFixed(3)},  ${best.z.toFixed(3)}`);
  document.getElementById("resCoordCercano").style.display = "block";
  const pp = proj(best); PX += (CVS.width / 2 - pp.sx); PY += (CVS.height / 2 - pp.sy);
  RESH = [best]; draw();
  toast(`N°${best.n} — dist: ${minD.toFixed(3)} m`, "success");
});

/* ════════════════════════════════════════════
   7. BUSCAR PUNTO
════════════════════════════════════════════ */
document.getElementById("btnBuscarPunto")?.addEventListener("click", () => {
  const p = getByN(document.getElementById("calcBuscarN")?.value);
  if (!p) { toast("Punto no encontrado", "error"); return; }
  set("rBuscarN",    p.n ?? "—");
  set("rBuscarX",    p.x.toFixed(4));
  set("rBuscarY",    p.y.toFixed(4));
  set("rBuscarZ",    p.z.toFixed(4) + " m");
  set("rBuscarDesc", p.desc || "—");
  document.getElementById("resBuscar").style.display = "block";
  const pp = proj(p); PX += (CVS.width / 2 - pp.sx); PY += (CVS.height / 2 - pp.sy);
  RESH = [p]; draw();
  toast(`N°${p.n} · ${p.desc || ""} · Z: ${p.z.toFixed(4)} m`, "success");
});



/* ──────────────────────────────────────────────────────────────
   EXPORTAR CSV
────────────────────────────────────────────────────────────── */
document.getElementById("btnExport")?.addEventListener("click",()=>{
  if(!PTS.length) return;
  const csv="N,X,Y,Z,DESCRIPCION\n"+PTS.map(p=>[p.n??'',p.x,p.y,p.z,p.desc??''].join(",")).join("\n");
  Object.assign(document.createElement("a"),{
    href:URL.createObjectURL(new Blob([csv],{type:"text/csv"})),download:"fylcad.csv"
  }).click();
});

/* ──────────────────────────────────────────────────────────────
   EXPORTAR PNG
────────────────────────────────────────────────────────────── */
document.getElementById("btnExportPNG")?.addEventListener("click",()=>{
  if(!PTS.length){toast("Carga un archivo primero","error");return;}
  const sc=2;
  const tmp=document.createElement("canvas");
  tmp.width=CVS.width*sc; tmp.height=CVS.height*sc;
  const tc=tmp.getContext("2d");
  tc.scale(sc,sc); tc.drawImage(CVS,0,0);
  tc.setTransform(1,0,0,1,0,0);
  tc.font=`bold ${11*sc}px 'Syne',sans-serif`;
  tc.fillStyle="rgba(0,0,128,0.65)"; tc.textAlign="right";
  tc.fillText("FYLCAD · fylcad.com",tmp.width-14,tmp.height-14);
  Object.assign(document.createElement("a"),{download:`fylcad_${Date.now()}.png`,href:tmp.toDataURL("image/png")}).click();
  toast("✓ PNG exportado","success");
});

/* ──────────────────────────────────────────────────────────────
   EXPORTAR PDF
────────────────────────────────────────────────────────────── */
document.getElementById("btnExportPDF")?.addEventListener("click", async () => {
  if (!PTS.length) { toast("Carga un archivo CSV primero", {type:"warn",ico:"⚠️"}); return; }

  // Esperar a que jsPDF esté disponible (puede cargar tarde)
  let attempts = 0;
  while (!window.jspdf && attempts < 20) {
    await new Promise(r => setTimeout(r, 150));
    attempts++;
  }
  if (!window.jspdf) { toast("jsPDF no disponible — revisa tu conexión", {type:"err",ico:"✕"}); return; }

  toast("Generando PDF...", {type:"info", ico:"⏳", dur:8000});

  try {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation:"landscape", unit:"mm", format:"a4" });
    const W = doc.internal.pageSize.getWidth();   // 297mm
    const H = doc.internal.pageSize.getHeight();  // 210mm

    const m = MACT || {};
    const fmtN = v => (isNaN(v)||v==null) ? "—" : Number(v).toLocaleString("es-CO", {maximumFractionDigits:2});
    const fmtM = v => (isNaN(v)||v==null) ? "—" : "$" + Math.round(v).toLocaleString("es-CO");

    // Tarifas por defecto (iguales a cotización.php)
    const TAR = { replanteo:1850, cerramiento:29370, descapote:3464, tierra:21800, relleno:14912, nivelacion:860 };

    /* ── Fondo papel ── */
    doc.setFillColor(244, 240, 230);
    doc.rect(0, 0, W, H, "F");

    /* ── Marco doble ── */
    doc.setDrawColor(0, 0, 100);
    doc.setLineWidth(0.6);
    doc.rect(6, 6, W-12, H-12);
    doc.setLineWidth(0.2);
    doc.rect(7.5, 7.5, W-15, H-15);

    /* ── Cabecera azul ── */
    doc.setFillColor(0, 0, 100);
    doc.rect(6, 6, W-12, 14, "F");
    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.setTextColor(255, 255, 255);
    doc.text("FYLCAD — PLANO TOPOGRÁFICO", W/2, 15.5, { align:"center" });
    doc.setFontSize(7);
    doc.setFont("helvetica", "normal");
    const fecha = new Date().toLocaleDateString("es-CO", {day:"2-digit", month:"long", year:"numeric"});
    doc.text(fecha, W-9, 15.5, { align:"right" });
    doc.text("fylcad.com", 10, 15.5);

    /* ── Canvas del plano ── */
    const imgX = 8, imgY = 22;
    const imgW = W - 80, imgH = H - 32;
    try {
      const imgData = CVS.toDataURL("image/png");
      doc.addImage(imgData, "PNG", imgX, imgY, imgW, imgH, "", "FAST");
    } catch(e) {
      doc.setFillColor(230,235,245);
      doc.rect(imgX, imgY, imgW, imgH, "F");
      doc.setTextColor(100,100,150); doc.setFontSize(9);
      doc.text("Plano no disponible", imgX + imgW/2, imgY + imgH/2, {align:"center"});
    }
    doc.setDrawColor(0,0,100); doc.setLineWidth(0.3);
    doc.rect(imgX, imgY, imgW, imgH);

    /* ── Panel lateral derecho ── */
    const px = imgX + imgW + 3;
    const pw = W - px - 8;
    let ry = 24;

    // Fondo panel
    doc.setFillColor(250, 249, 245);
    doc.rect(px-1, imgY-1, pw+2, imgH+2, "F");
    doc.setDrawColor(200,200,220); doc.setLineWidth(0.15);
    doc.rect(px-1, imgY-1, pw+2, imgH+2);

    // Helper sección
    const sec = label => {
      doc.setFillColor(0,0,100);
      doc.rect(px-1, ry-3, pw+2, 6, "F");
      doc.setFont("helvetica","bold"); doc.setFontSize(6);
      doc.setTextColor(255,255,255);
      doc.text(label, px+1, ry+1.5);
      ry += 8;
    };

    // Helper fila
    const fila = (label, valor, highlight=false) => {
      if (ry > imgY+imgH-4) return;
      if (highlight) {
        doc.setFillColor(240,245,255);
        doc.rect(px-1, ry-3.5, pw+2, 5.5, "F");
      }
      doc.setFont("helvetica","normal"); doc.setFontSize(6);
      doc.setTextColor(80,80,80);
      doc.text(label, px+1, ry);
      doc.setFont("helvetica", highlight?"bold":"normal");
      doc.setTextColor(highlight?0:50, highlight?0:50, highlight?80:80);
      doc.text(String(valor), px+pw-1, ry, {align:"right"});
      doc.setDrawColor(220,220,230); doc.setLineWidth(0.08);
      doc.line(px, ry+1, px+pw-1, ry+1);
      ry += 5.2;
    };

    /* Métricas */
    sec("LEVANTAMIENTO TOPOGRÁFICO");
    fila("Puntos GPS", fmtN(PTS.length), true);
    fila("Triángulos TIN", fmtN(TRIS.length));
    fila("Curvas de nivel", NIV.length);
    if(NIV.length>0) fila("Equidistancia", ((ZMAX-ZMIN)/(NIV.length+1)).toFixed(3)+" m");
    fila("Área aprox.", fmtN(m.area)+" m²", true);
    fila("Perímetro", fmtN(m.perimetro)+" m");
    fila("Volumen est.", fmtN(m.volumen)+" m³");
    ry += 2;
    sec("COTAS (m.s.n.m.)");
    fila("Cota mínima", (m.zMin!=null?m.zMin.toFixed(3):"-")+" m", true);
    fila("Cota máxima", (m.zMax!=null?m.zMax.toFixed(3):"-")+" m", true);
    fila("Desnivel total", (m.desnivel!=null?m.desnivel.toFixed(3):"-")+" m");

    /* Presupuesto estimado */
    ry += 3;
    sec("PRESUPUESTO EST. (COP)");
    const area = m.area||0, perim = m.perimetro||0, vol = m.volumen||0;
    const cRe = area  * TAR.replanteo;
    const cCe = perim * TAR.cerramiento;
    const cDe = area  * TAR.descapote;
    const cTi = vol   * TAR.tierra;
    const cRe2= vol   * 0.35 * TAR.relleno;
    const cNi = area  * TAR.nivelacion;
    const total = cRe+cCe+cDe+cTi+cRe2+cNi;
    fila("Localiz. y replanteo", fmtM(cRe));
    fila("Cerramiento prov.",    fmtM(cCe));
    fila("Descapote e=25cm",     fmtM(cDe));
    fila("Excavación mecánica",  fmtM(cTi));
    fila("Relleno+compactación", fmtM(cRe2));
    fila("Nivelación rasante",   fmtM(cNi));
    ry += 2;
    // Caja total
    if (ry < imgY+imgH-14) {
      doc.setFillColor(0,0,100);
      doc.rect(px-1, ry-1, pw+2, 10, "F");
      doc.setFont("helvetica","bold"); doc.setFontSize(7);
      doc.setTextColor(255,255,255);
      doc.text("TOTAL COP", px+2, ry+5);
      doc.text(fmtM(total), px+pw-1, ry+5, {align:"right"});
      ry += 12;
      doc.setFont("helvetica","normal"); doc.setFontSize(5.5);
      doc.setTextColor(120,120,160);
      doc.text("≈ USD "+Math.round(total/4200).toLocaleString("es-CO"), px+pw-1, ry, {align:"right"});
    }

    /* ── Leyenda de elevaciones (barra de color) ── */
    const bx=imgX+4, by=imgY+imgH-22, bw=40, bh=5;
    const grad = ["#228b22","#90ee64","#ffeb64","#ffa500","#d23c14","#8b0000"];
    const sw = bw/grad.length;
    grad.forEach((col,i) => {
      const rgb = parseInt(col.slice(1),16);
      doc.setFillColor((rgb>>16)&255,(rgb>>8)&255,rgb&255);
      doc.rect(bx+i*sw, by, sw, bh, "F");
    });
    doc.setDrawColor(100,100,100); doc.setLineWidth(0.2);
    doc.rect(bx, by, bw, bh);
    doc.setFont("helvetica","normal"); doc.setFontSize(5.5);
    doc.setTextColor(50,50,50);
    doc.text(ZMIN.toFixed(1)+"m", bx, by+8);
    doc.text(((ZMIN+ZMAX)/2).toFixed(1)+"m", bx+bw/2, by+8, {align:"center"});
    doc.text(ZMAX.toFixed(1)+"m", bx+bw, by+8, {align:"right"});
    doc.setFontSize(5); doc.setTextColor(80,80,80);
    doc.text("Elevación", bx+bw/2, by-1, {align:"center"});

    /* ── Flecha Norte ── */
    const nx=imgX+imgW-12, ny=imgY+12;
    doc.setDrawColor(0,0,80); doc.setLineWidth(0.5);
    doc.line(nx, ny+6, nx, ny-6);
    doc.setFillColor(0,0,80);
    doc.triangle(nx-3, ny-2, nx+3, ny-2, nx, ny-8, "F");
    doc.setFont("helvetica","bold"); doc.setFontSize(6);
    doc.setTextColor(0,0,80);
    doc.text("N", nx, ny-10, {align:"center"});

    /* ── Pie de página ── */
    doc.setFont("helvetica","normal"); doc.setFontSize(5.5);
    doc.setTextColor(100,100,140);
    doc.text("Generado por FYLCAD · fylcad.com · Solo estimación referencial", imgX, H-4);
    doc.text(`Z: ${ZMIN.toFixed(3)} – ${ZMAX.toFixed(3)} m  ·  Δ ${(ZMAX-ZMIN).toFixed(3)} m  ·  ${PTS.length} puntos GPS`, W-9, H-4, {align:"right"});

    const fname = (document.getElementById("fileName")?.textContent||"plano").replace(/\.[^.]+$/,"");
    doc.save(`FYLCAD_${fname}_${new Date().toISOString().slice(0,10)}.pdf`);
    toast("✓ PDF exportado correctamente", {type:"ok", ico:"✓"});

  } catch(err) {
    console.error("PDF error:", err);
    toast("Error al generar PDF: " + err.message, {type:"err", ico:"✕"});
  }
});

/* ──────────────────────────────────────────────────────────────
   MODAL GUARDAR EN DB
   - Crea proyecto nuevo O actualiza si ya fue cargado desde DB
   - Guarda puntos completos (N,X,Y,Z,DESC) para poder recargar
────────────────────────────────────────────────────────────── */
let PROYECTO_ID = null;   // ID del proyecto cargado desde DB (null = nuevo)
let PROYECTO_NOMBRE = ""; // nombre actual del proyecto

const mOv = document.getElementById("modalGuardar");
const mNom = document.getElementById("modalNombre");
const mDesc = document.getElementById("modalDescText");

document.getElementById("btnGuardar")?.addEventListener("click", () => {
  if (!PTS.length) { toast("Carga un archivo primero", "error"); return; }
  // Pre-rellenar con nombre actual
  if (mNom) mNom.value = PROYECTO_NOMBRE || fileNameEl.textContent.replace(/\.(csv|txt)$/i, "") || "";
  // Mostrar indicador si es actualización
  const titulo = document.querySelector("#modalGuardar h3");
  if (titulo) titulo.textContent = PROYECTO_ID ? "💾 Actualizar proyecto" : "💾 Guardar proyecto";
  const confirmBtn = document.getElementById("modalConfirmar");
  if (confirmBtn) confirmBtn.textContent = PROYECTO_ID ? "Actualizar" : "Guardar";
  mOv?.classList.add("open"); mNom?.focus();
});

document.getElementById("modalCancelar")?.addEventListener("click", () => mOv?.classList.remove("open"));
mOv?.addEventListener("click", e => { if (e.target === mOv) mOv.classList.remove("open"); });

document.getElementById("modalConfirmar")?.addEventListener("click", async () => {
  const nombre = mNom?.value.trim() || "Proyecto sin nombre";
  const m = MACT;
  const btn = document.getElementById("modalConfirmar");
  btn.textContent = PROYECTO_ID ? "Actualizando..." : "Guardando...";
  btn.disabled = true;

  try {
    const body = {
      nombre,
      puntos: PTS,
      archivo: fileNameEl?.textContent || "coordenadas.csv",
      metricas: {
        triangulos:  TRIS.length,
        area:        m?.area        || 0,
        perimetro:   m?.perimetro   || 0,
        volumen:     m?.volumen     || 0,
        zMin:        m?.zMin        || 0,
        zMax:        m?.zMax        || 0,
        desnivel:    m?.desnivel    || 0,
        centroideX:  CX || 0,
        centroideY:  CY || 0,
        centroideZ:  CZ || 0,
      },
    };
    // Si ya tiene ID, enviar para actualizar (no crear duplicado)
    if (PROYECTO_ID) body.proyecto_id = PROYECTO_ID;

    const r = await fetch("guardar_proyecto.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    });
    const d = await r.json();
    mOv.classList.remove("open");

    if (d.ok) {
      PROYECTO_ID     = d.proyecto_id;  
      PROYECTO_NOMBRE = nombre;
     
      const hTag = document.querySelector(".header-tag");
      if (hTag) hTag.textContent = `// ${nombre}`;
      
      const btnG = document.getElementById("btnGuardar");
      if (btnG) { btnG.title = `Proyecto guardado: ${nombre}`; }
      toast("✓ " + d.mensaje, "success");
    } else {
      toast("✗ " + d.error, "error");
    }
  } catch (e) {
    toast("✗ Error de conexión", "error");
  } finally {
    btn.textContent = PROYECTO_ID ? "Actualizar" : "Guardar";
    btn.disabled = false;
  }
});

/* ══════════════════════════════════════════════
   CÁLCULO: SECCIÓN TRANSVERSAL
══════════════════════════════════════════════ */
document.getElementById("btnCalcSeccion")?.addEventListener("click", () => {
  if (!PTS.length) { toast("Carga datos primero", "error"); return; }

  const idxCentro = +document.getElementById("secCentro").value - 1;
  const azimut    = +document.getElementById("secAzimut").value || 0;
  const anchoMed  = (+document.getElementById("secAncho").value || 20) / 2;
  const zProy     = document.getElementById("secZproy").value !== ""
                      ? +document.getElementById("secZproy").value : null;

  if (idxCentro < 0 || idxCentro >= PTS.length) { toast("Punto fuera de rango", "error"); return; }

  const centro = PTS[idxCentro];
  const azRad  = azimut * Math.PI / 180;
  const perpX  = Math.cos(azRad + Math.PI/2);
  const perpY  = Math.sin(azRad + Math.PI/2);

  const SAMPLE = 60;
  const step   = (anchoMed * 2) / SAMPLE;
  const profile = [];

  for (let i = 0; i <= SAMPLE; i++) {
    const d    = -anchoMed + i * step;
    const qx   = centro.x + perpX * d;
    const qy   = centro.y + perpY * d;
    let zTerr  = interpZfromTIN(qx, qy);
    if (zTerr === null) {
      let minD = Infinity, zN = centro.z;
      for (const p of PTS) {
        const dd = Math.hypot(p.x - qx, p.y - qy);
        if (dd < minD) { minD = dd; zN = p.z; }
      }
      zTerr = zN;
    }
    profile.push({ d, z: zTerr });
  }

  const zProyEfect = zProy !== null ? zProy : centro.z;
  let areaC = 0, areaR = 0;

  for (let i = 0; i < profile.length - 1; i++) {
    const h1 = profile[i].z   - zProyEfect;
    const h2 = profile[i+1].z - zProyEfect;
    const dA = Math.abs(profile[i+1].d - profile[i].d);
    if (h1 > 0 && h2 > 0) areaC += (h1 + h2) / 2 * dA;
    else if (h1 < 0 && h2 < 0) areaR += Math.abs(h1 + h2) / 2 * dA;
    else {
      const ratio = Math.abs(h1) / (Math.abs(h1) + Math.abs(h2));
      const dCross = dA * ratio;
      if (h1 > 0) { areaC += h1 * dCross / 2; areaR += Math.abs(h2) * (dA - dCross) / 2; }
      else        { areaR += Math.abs(h1) * dCross / 2; areaC += h2 * (dA - dCross) / 2; }
    }
  }

  const desnIzq = Math.max(...profile.slice(0, SAMPLE/2).map(p => Math.abs(p.z - zProyEfect)));
  const desnDer = Math.max(...profile.slice(SAMPLE/2).map(p => Math.abs(p.z - zProyEfect)));

  const taludRec = (d, label) => {
    if (d < 0.3) return `${label}: Plano`;
    if (d < 1.0) return `${label}: 1:1`;
    if (d < 2.5) return `${label}: 1:1.5`;
    return `${label}: 1:2`;
  };

  const tipo = areaC >= areaR ? "CORTE" : "RELLENO";
  set("rSecTipoLabel", tipo);
  set("rSecVolTotal",  fmt(Math.max(areaC, areaR)) + " m²");
  set("rSecAreaC",     fmt(areaC) + " m²");
  set("rSecAreaR",     fmt(areaR) + " m²");
  set("rSecDesnIzq",   fmt(desnIzq) + " m");
  set("rSecDesnDer",   fmt(desnDer) + " m");
  set("rSecTaludIzq",  taludRec(desnIzq / anchoMed, "Izq"));
  set("rSecTaludDer",  taludRec(desnDer / anchoMed, "Der"));

  const cv = document.getElementById("miniCanvasSeccion");
  if (cv) {
    const ctx = cv.getContext("2d");
    const W = cv.width, H = cv.height;
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = "#0f172a"; ctx.fillRect(0, 0, W, H);
    const zVals2 = profile.map(p => p.z);
    const zmin  = Math.min(...zVals2, zProyEfect) - 0.5;
    const zmax  = Math.max(...zVals2, zProyEfect) + 0.5;
    const dmin  = -anchoMed, dmax = anchoMed;
    const mxx = d => 8 + (d - dmin) / (dmax - dmin) * (W - 16);
    const myy = z => (H - 16) - (z - zmin) / (zmax - zmin) * (H - 24);

    ctx.beginPath();
    ctx.moveTo(mxx(dmin), H);
    for (const p of profile) ctx.lineTo(mxx(p.d), myy(p.z));
    ctx.lineTo(mxx(dmax), H); ctx.closePath();
    ctx.fillStyle = "rgba(100,116,139,.25)"; ctx.fill();

    ctx.beginPath();
    profile.forEach((p, i) => i === 0 ? ctx.moveTo(mxx(p.d), myy(p.z)) : ctx.lineTo(mxx(p.d), myy(p.z)));
    ctx.strokeStyle = "#94a3b8"; ctx.lineWidth = 1.5; ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(mxx(dmin), myy(zProyEfect)); ctx.lineTo(mxx(dmax), myy(zProyEfect));
    ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 1.5;
    ctx.setLineDash([4,3]); ctx.stroke(); ctx.setLineDash([]);

    ctx.beginPath();
    ctx.arc(mxx(0), myy(centro.z), 4, 0, Math.PI*2);
    ctx.fillStyle = "#f59e0b"; ctx.fill();
  }

  document.getElementById("resSeccion").style.display = "";
});

function interpZfromTIN(qx, qy) {
  if (!TRIS || !PTS) return null;
  for (const t of TRIS) {
    const a = PTS[t[0]], b = PTS[t[1]], c = PTS[t[2]];
    const denom = (b.y - c.y)*(a.x - c.x) + (c.x - b.x)*(a.y - c.y);
    if (Math.abs(denom) < 1e-10) continue;
    const u = ((b.y - c.y)*(qx - c.x) + (c.x - b.x)*(qy - c.y)) / denom;
    const v = ((c.y - a.y)*(qx - c.x) + (a.x - c.x)*(qy - c.y)) / denom;
    const w = 1 - u - v;
    if (u >= -0.001 && v >= -0.001 && w >= -0.001) return u * a.z + v * b.z + w * c.z;
  }
  return null;
}

/* ══════════════════════════════════════════════
   CÁLCULO: NIVELACIÓN DE PLATAFORMA
══════════════════════════════════════════════ */
document.getElementById("btnCalcPlataforma")?.addEventListener("click", () => {
  if (!PTS.length) { toast("Carga datos primero", "error"); return; }

  let pts = PTS;
  const platDesde  = document.getElementById("platDesde").value;
  const platHasta  = document.getElementById("platHasta").value;
  const platZmin   = document.getElementById("platZmin").value !== "" ? +document.getElementById("platZmin").value : null;
  const platZmax   = document.getElementById("platZmax").value !== "" ? +document.getElementById("platZmax").value : null;

  if (platDesde || platHasta) {
    const d = platDesde ? +platDesde - 1 : 0;
    const h = platHasta ? +platHasta - 1 : PTS.length - 1;
    pts = PTS.slice(d, h + 1);
  }

  if (!pts.length) { toast("No hay puntos en el rango", "error"); return; }

  const zVals = pts.map(p => p.z);
  const zmed  = zVals.reduce((a, b) => a + b, 0) / zVals.length;

  function computeBalance(zCota) {
    let c = 0, r = 0;
    for (const z of zVals) {
      if (z > zCota) c += (z - zCota);
      else r += (zCota - z);
    }
    return { c, r, net: c - r };
  }

  let lo = Math.min(...zVals), hi = Math.max(...zVals);
  if (platZmin !== null) lo = Math.max(lo, platZmin);
  if (platZmax !== null) hi = Math.min(hi, platZmax);

  let zOpt = zmed, iterData = [];
  const balLo = computeBalance(lo), balHi = computeBalance(hi);
  if (balLo.net * balHi.net <= 0) {
    for (let iter = 0; iter < 100; iter++) {
      zOpt = (lo + hi) / 2;
      const bal = computeBalance(zOpt);
      iterData.push({ z: zOpt, net: bal.net });
      if (Math.abs(bal.net) < 0.001) break;
      if (bal.net > 0) lo = zOpt; else hi = zOpt;
    }
  }

  const finalBal = computeBalance(zOpt);
  const pctCorte = zVals.filter(z => z > zOpt).length / zVals.length * 100;

  set("rPlatCota",    fmt(zOpt) + " m.s.n.m.");
  set("rPlatVolC",    fmt(finalBal.c) + " m³");
  set("rPlatVolR",    fmt(finalBal.r) + " m³");
  set("rPlatBalance", fmt(Math.abs(finalBal.net)) + " m³ " + (Math.abs(finalBal.net) < 1 ? "✓ Balanceado" : finalBal.net > 0 ? "(exceso corte)" : "(exceso relleno)"));
  set("rPlatN",       pts.length + " puntos");
  set("rPlatZmed",    fmt(zmed) + " m");
  set("rPlatPctC",    pctCorte.toFixed(1) + "% en corte");

  const cv = document.getElementById("miniCanvasPlataforma");
  if (cv && iterData.length > 1) {
    const ctx = cv.getContext("2d");
    const W = cv.width, H = cv.height;
    ctx.clearRect(0, 0, W, H); ctx.fillStyle = "#0f172a"; ctx.fillRect(0, 0, W, H);
    const zmin2 = Math.min(...iterData.map(d => d.z)) - 0.1;
    const zmax2 = Math.max(...iterData.map(d => d.z)) + 0.1;
    const mxi = i => 10 + i / (iterData.length - 1) * (W - 20);
    const myi = z => (H - 10) - (z - zmin2) / (zmax2 - zmin2) * (H - 20);
    ctx.beginPath();
    iterData.forEach((d, i) => i === 0 ? ctx.moveTo(mxi(i), myi(d.z)) : ctx.lineTo(mxi(i), myi(d.z)));
    ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 1.5; ctx.stroke();
    ctx.strokeStyle = "rgba(251,191,36,.4)"; ctx.lineWidth = 1; ctx.setLineDash([3,3]);
    ctx.beginPath(); ctx.moveTo(10, myi(zOpt)); ctx.lineTo(W-10, myi(zOpt));
    ctx.stroke(); ctx.setLineDash([]);
    ctx.fillStyle = "#94a3b8"; ctx.font = "9px DM Mono";
    ctx.fillText(`Convergencia Z = ${fmt(zOpt)} m`, 12, 12);
  }

  document.getElementById("resPlataforma").style.display = "";
  toast(`✓ Cota óptima: ${fmt(zOpt)} m · Balance: ${fmt(Math.abs(finalBal.net))} m³`, "success");
});

/* ══════════════════════════════════════════════
   CÁLCULO: CURVA DE MASA (BRUCKNER)
══════════════════════════════════════════════ */
document.getElementById("btnCalcMasa")?.addEventListener("click", () => {
  if (!PTS.length) { toast("Carga datos primero", "error"); return; }

  const desde  = +document.getElementById("masaDesde").value - 1 || 0;
  const hasta  = document.getElementById("masaHasta").value ? +document.getElementById("masaHasta").value - 1 : PTS.length - 1;
  const z1     = document.getElementById("masaZ1").value !== "" ? +document.getElementById("masaZ1").value : null;
  const z2     = document.getElementById("masaZ2").value !== "" ? +document.getElementById("masaZ2").value : null;
  const esponj = (1 + (+document.getElementById("masaEsponj").value || 12) / 100);
  const banca  = +document.getElementById("masaBanca").value || 6;

  const pts = PTS.slice(desde, hasta + 1);
  if (pts.length < 2) { toast("Se necesitan al menos 2 puntos", "error"); return; }

  let cumDist = [0];
  for (let i = 1; i < pts.length; i++) {
    cumDist.push(cumDist[i-1] + Math.hypot(pts[i].x - pts[i-1].x, pts[i].y - pts[i-1].y));
  }
  const totalDist = cumDist[cumDist.length - 1];
  const zRef = (z1 === null) ? pts.reduce((s, p) => s + p.z, 0) / pts.length : null;

  const rasante = pts.map((p, i) =>
    (z1 !== null && z2 !== null) ? z1 + (z2 - z1) * (cumDist[i] / totalDist) : zRef);

  const estaciones = [];
  let totalCorte = 0, totalRelleno = 0, acum = 0, zonas = 0;
  let prevSign = null;

  for (let i = 0; i < pts.length - 1; i++) {
    const h1 = pts[i].z - rasante[i], h2 = pts[i+1].z - rasante[i+1];
    const d  = cumDist[i+1] - cumDist[i];
    const vC = (Math.max(h1,0) + Math.max(h2,0)) / 2 * banca * d * esponj;
    const vR = (Math.max(-h1,0) + Math.max(-h2,0)) / 2 * banca * d;
    totalCorte += vC; totalRelleno += vR;
    acum += vC - vR;
    const curSign = Math.sign(acum);
    if (prevSign !== null && curSign !== prevSign && prevSign !== 0) zonas++;
    prevSign = curSign;
    estaciones.push({ d: cumDist[i+1], volC: vC, volR: vR, acum });
  }

  let sumDist = 0, sumVol = 0;
  estaciones.forEach(e => { sumDist += e.d * Math.abs(e.acum); sumVol += Math.abs(e.acum); });
  const dma = sumVol > 0 ? sumDist / sumVol : 0;

  set("rMasaBalance", (acum >= 0 ? "+" : "") + fmt(acum) + " m³");
  set("rMasaCorte",   fmt(totalCorte) + " m³");
  set("rMasaRelleno", fmt(totalRelleno) + " m³");
  set("rMasaZonas",   zonas + " punto(s)");
  set("rMasaDMA",     fmt(dma) + " m");

  const cv = document.getElementById("miniCanvasMasa");
  if (cv) {
    const ctx = cv.getContext("2d");
    const W = cv.width, H = cv.height;
    ctx.clearRect(0, 0, W, H); ctx.fillStyle = "#0f172a"; ctx.fillRect(0, 0, W, H);
    const acums = estaciones.map(e => e.acum);
    const amin  = Math.min(0, ...acums), amax = Math.max(0, ...acums);
    const mxm = d => 10 + d / totalDist * (W - 20);
    const mym = a => (H - 10) - (a - amin) / ((amax - amin) || 1) * (H - 20);
    ctx.strokeStyle = "rgba(255,255,255,.15)"; ctx.lineWidth = 1;
    ctx.setLineDash([3,3]); ctx.beginPath();
    ctx.moveTo(10, mym(0)); ctx.lineTo(W-10, mym(0));
    ctx.stroke(); ctx.setLineDash([]);
    ctx.beginPath(); ctx.moveTo(mxm(0), mym(0));
    estaciones.forEach(e => ctx.lineTo(mxm(e.d), mym(e.acum)));
    ctx.strokeStyle = "#00e5c0"; ctx.lineWidth = 2; ctx.stroke();
    ctx.fillStyle = "#94a3b8"; ctx.font = "9px DM Mono";
    ctx.fillText("Curva de masa (Bruckner)", 12, 12);
  }

  const tabla = document.getElementById("masaTabla");
  if (tabla) {
    const step = Math.max(1, Math.floor(estaciones.length / 8));
    tabla.innerHTML = `<table style="width:100%;border-collapse:collapse;">
      <thead><tr style="color:#64748b;font-size:10px;">
        <th style="text-align:left;padding:2px 4px;">Dist.(m)</th>
        <th style="text-align:right;padding:2px 4px;">C(m³)</th>
        <th style="text-align:right;padding:2px 4px;">R(m³)</th>
        <th style="text-align:right;padding:2px 4px;">Acum.</th>
      </tr></thead><tbody>` +
      estaciones.filter((_, i) => i % step === 0).map(e =>
        `<tr style="border-top:1px solid rgba(255,255,255,.04);font-size:10px;">
          <td style="padding:2px 4px;color:#94a3b8;">${fmt(e.d)}</td>
          <td style="padding:2px 4px;color:#00e5c0;text-align:right;">${fmt(e.volC)}</td>
          <td style="padding:2px 4px;color:#f87171;text-align:right;">${fmt(e.volR)}</td>
          <td style="padding:2px 4px;color:#e8edf5;font-weight:600;text-align:right;">${(e.acum>=0?"+":"") + fmt(e.acum)}</td>
        </tr>`).join("") +
      `</tbody></table>`;
  }

  document.getElementById("resMasa").style.display = "";
  toast("✓ Curva de masa · " + estaciones.length + " estaciones", "success");
});

/* ══════════════════════════════════════════════
   CÁLCULO: ANÁLISIS DE DRENAJE
══════════════════════════════════════════════ */
document.getElementById("btnCalcDrenaje")?.addEventListener("click", () => {
  if (!PTS.length) { toast("Carga datos primero", "error"); return; }

  const idxDesague = +document.getElementById("drenajeDesague").value - 1;
  const radio      = +document.getElementById("drenajeRadio").value || 50;
  const intensidad = +document.getElementById("drenajeI").value || 80;
  const coefC      = +document.getElementById("drenajeC").value || 0.5;

  if (idxDesague < 0 || idxDesague >= PTS.length) { toast("Punto fuera de rango", "error"); return; }

  const pDesague = PTS[idxDesague];
  const cuenca   = PTS.filter((p, i) => {
    if (i === idxDesague) return false;
    return Math.hypot(p.x - pDesague.x, p.y - pDesague.y) <= radio && p.z >= pDesague.z;
  });

  if (!cuenca.length) { toast("No hay puntos de aporte en el radio definido", "error"); return; }

  const sorted = cuenca.slice().sort((a, b) =>
    Math.atan2(a.y - pDesague.y, a.x - pDesague.x) - Math.atan2(b.y - pDesague.y, b.x - pDesague.x));

  let area = 0;
  for (let i = 0; i < sorted.length; i++) {
    const j = (i + 1) % sorted.length;
    area += sorted[i].x * sorted[j].y - sorted[j].x * sorted[i].y;
  }
  area = Math.abs(area) / 2;
  if (area < 1) area = Math.PI * radio * radio * 0.3;

  const areaHa  = area / 10000;
  const zMed    = cuenca.reduce((s, p) => s + p.z, 0) / cuenca.length;
  const pendMed = (zMed - pDesague.z) / radio * 100;
  const Q       = coefC * intensidad * areaHa / 360;
  const S       = (pendMed / 100) || 0.01;
  const Tc      = 0.0195 * Math.pow(radio, 0.77) / Math.pow(S, 0.385);

  let seccion = "";
  if (Q < 0.05)      seccion = "Cuneta V 0.3m × 0.3m";
  else if (Q < 0.15) seccion = "Cuneta V 0.5m × 0.5m";
  else if (Q < 0.4)  seccion = "Cuneta trap. 0.6m base";
  else if (Q < 1.0)  seccion = "Cuneta trap. 0.8m base";
  else               seccion = "Canal revestido";

  const longCuneta = cuenca.reduce((s, p) => s + Math.hypot(p.x - pDesague.x, p.y - pDesague.y), 0) / cuenca.length * 2;

  set("rDrenajeQ",    Q.toFixed(3) + " m³/s");
  set("rDrenajeArea", fmt(area) + " m² (" + areaHa.toFixed(4) + " ha)");
  set("rDrenajeN",    cuenca.length + " puntos");
  set("rDrenajeL",    fmt(longCuneta) + " m");
  set("rDrenajePend", fmt(pendMed) + "%");
  set("rDrenajeSec",  seccion);
  set("rDrenajeTc",   Tc.toFixed(1) + " min");

  const cv = document.getElementById("miniCanvasDrenaje");
  if (cv) {
    const ctx = cv.getContext("2d");
    const W = cv.width, H = cv.height;
    ctx.clearRect(0, 0, W, H); ctx.fillStyle = "#0f172a"; ctx.fillRect(0, 0, W, H);
    const allPts = [pDesague, ...cuenca];
    const xs = allPts.map(p => p.x), ys = allPts.map(p => p.y);
    const xmin = Math.min(...xs), xmax = Math.max(...xs);
    const ymin = Math.min(...ys), ymax = Math.max(...ys);
    const scl  = Math.min((W-20)/((xmax-xmin)||1), (H-20)/((ymax-ymin)||1));
    const txp  = x => 10 + (x - xmin) * scl;
    const typ  = y => H - 10 - (y - ymin) * scl;
    ctx.fillStyle = "rgba(0,229,192,.5)";
    for (const p of cuenca) {
      ctx.beginPath(); ctx.arc(txp(p.x), typ(p.y), 2, 0, Math.PI*2); ctx.fill();
    }
    ctx.beginPath();
    ctx.arc(txp(pDesague.x), typ(pDesague.y), radio * scl, 0, Math.PI*2);
    ctx.strokeStyle = "rgba(251,191,36,.3)"; ctx.lineWidth = 1;
    ctx.setLineDash([3,3]); ctx.stroke(); ctx.setLineDash([]);
    ctx.beginPath(); ctx.arc(txp(pDesague.x), typ(pDesague.y), 5, 0, Math.PI*2);
    ctx.fillStyle = "#f59e0b"; ctx.fill();
    ctx.fillStyle = "#94a3b8"; ctx.font = "9px DM Mono";
    ctx.fillText(`Q=${Q.toFixed(3)} m³/s · A=${areaHa.toFixed(3)} ha`, 8, 12);
  }

  document.getElementById("resDrenaje").style.display = "";
  toast(`✓ Q=${Q.toFixed(3)} m³/s · Área aporte: ${areaHa.toFixed(3)} ha`, "success");
});

/* ──────────────────────────────────────────────────────────────
   HELPERS
────────────────────────────────────────────────────────── */
const set     = (id,v)=>{const el=document.getElementById(id);if(el)el.textContent=v;};
const fmt     = n=>isNaN(n)?"—":Number(n).toLocaleString("es",{maximumFractionDigits:2});
const usd     = n=>isNaN(n)?"—":"$ "+Number(n).toLocaleString("es",{maximumFractionDigits:0});
const fmtBytes= b=>b<1024?b+"B":b<1048576?(b/1024).toFixed(1)+"KB":(b/1048576).toFixed(1)+"MB";
const toast   = (msg,tipo="success")=>{
  let t=document.getElementById("fylcad-toast");
  if(!t){t=document.createElement("div");t.id="fylcad-toast";document.body.appendChild(t);}
  t.textContent=msg; t.className=`toast toast-${tipo} show`;
  clearTimeout(t._tt); t._tt=setTimeout(()=>t.classList.remove("show"),3500);
};

  // Exponer variables al scope global para irACotizacion
  window.__FYLCAD_STATE__ = {
    getPTS:         () => PTS,
    getTRIS:        () => TRIS,
    getPROYECTO_ID: () => PROYECTO_ID,
    setPROYECTO_ID: (id) => { PROYECTO_ID = id; },
    getMACT:        () => MACT,
    getZMIN:        () => ZMIN,
    getZMAX:        () => ZMAX,
    getCX:          () => CX,
    getCY:          () => CY,
    getCZ:          () => CZ,
    getToast:       () => toast,
    getFileName:    () => document.getElementById("fileName")?.textContent || "proyecto.csv",
  };


})();
/* ──────────────────────────────────────────────────────────────
   COTIZACIÓN DIRECTA — ir a cotizar con plano cargado
   Si el proyecto ya está guardado → cotizacion.php?proyecto=ID
   Si no está guardado → guardarlo primero, luego redirigir
────────────────────────────────────────────────────── */
window.irACotizacion = async function() {
  const S = window.__FYLCAD_STATE__;
  if (!S) { alert("Error: módulo no cargado"); return; }

  const PTS        = S.getPTS();
  const PROYECTO_ID = S.getPROYECTO_ID();
  const toast      = S.getToast();

  // 1) Si ya tenemos ID de proyecto → ir directo
  if (PROYECTO_ID) {
    window.location.href = "cotizacion.php?proyecto=" + PROYECTO_ID;
    return;
  }

  // 2) Proyecto cargado desde mis_proyectos (tiene __FYLCAD_CARGAR__)
  const cargado = window.__FYLCAD_CARGAR__;
  if (cargado && cargado.id) {
    window.location.href = "cotizacion.php?proyecto=" + cargado.id;
    return;
  }

  // 3) Sin puntos → error
  if (!PTS.length) {
    toast("Carga un CSV primero antes de cotizar", "error");
    return;
  }

  // 4) CSV cargado manualmente sin ID → guardar y redirigir
  const btn   = document.getElementById("btnIrCotizacion");
  const sub   = document.getElementById("cotLinkSub");
  const arrow = document.getElementById("cotLinkArrow");
  if (btn)   { btn.style.opacity = ".65"; btn.style.pointerEvents = "none"; }
  if (sub)   sub.textContent = "Guardando proyecto...";
  if (arrow) arrow.textContent = "⏳";

  const resetBtn = () => {
    if (btn)   { btn.style.opacity = "1"; btn.style.pointerEvents = ""; }
    if (sub)   sub.textContent = "Abre el módulo completo de cotización";
    if (arrow) arrow.textContent = "→";
  };

  try {
    const MACT = S.getMACT() || {};
    const nombreProv = (S.getFileName().replace(/\.(csv|txt)$/i, "").trim()) || "Proyecto sin nombre";

    const r = await fetch("guardar_proyecto.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        nombre:  nombreProv,
        archivo: S.getFileName(),
        puntos:  PTS,
        metricas: {
          triangulos: S.getTRIS().length,
          area:       MACT.area      ?? 0,
          perimetro:  MACT.perimetro ?? 0,
          volumen:    MACT.volumen   ?? 0,
          zMin: S.getZMIN() ?? 0, zMax: S.getZMAX() ?? 0,
          desnivel: ((S.getZMAX() ?? 0) - (S.getZMIN() ?? 0)),
          centroideX: S.getCX() ?? 0, centroideY: S.getCY() ?? 0, centroideZ: S.getCZ() ?? 0,
        }
      })
    });
    const d = await r.json();

    if (d.ok) {
      S.setPROYECTO_ID(d.proyecto_id);
      window.location.href = "cotizacion.php?proyecto=" + d.proyecto_id;
    } else {
      // Si falla el guardado (ej: plan free), asegurar que localStorage tiene los datos y redirigir
      console.warn("FYLCAD: no se pudo guardar, redirigiendo con datos en localStorage:", d.error);
      try {
        const MACT2 = S.getMACT() || {};
        const PTS2  = S.getPTS();
        const TRIS2 = S.getTRIS();
        localStorage.setItem("fylcad_metricas", JSON.stringify({
          n:        PTS2.length,
          area:     MACT2.area     ?? 0,
          perimetro:MACT2.perimetro?? 0,
          volumen:  MACT2.volumen  ?? 0,
          zMin:     S.getZMIN()   ?? 0,
          zMax:     S.getZMAX()   ?? 0,
          desnivel: (S.getZMAX()??0)-(S.getZMIN()??0),
          cx: S.getCX()??0, cy: S.getCY()??0,
          timestamp: Date.now()
        }));
        const step = PTS2.length > 3000 ? Math.ceil(PTS2.length/3000) : 1;
        localStorage.setItem("fylcad_puntos", JSON.stringify(PTS2.filter((_,i)=>i%step===0)));
        const trisToSave = TRIS2.length>6000 ? TRIS2.slice(0,6000) : TRIS2;
        localStorage.setItem("fylcad_tris", JSON.stringify(trisToSave));
      } catch(le){ console.warn("localStorage lleno:", le); }
      window.location.href = "cotizacion.php";
    }
  } catch(e) {
    console.error("FYLCAD irACotizacion error:", e);
    window.location.href = "cotizacion.php";
  }
};