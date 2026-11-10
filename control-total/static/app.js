"use strict";
/* CONTROL TOTAL - interfaz */

const $ = (s, el = document) => el.querySelector(s);
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const money = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const fmtMoney = (v) => (v === null || v === undefined || v === "" ? "" : money.format(Number(v)));
const MESES = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];
const isISO = (v) => /^\d{4}-\d{2}-\d{2}$/.test(v || "");
const fmtDate = (v) => {
  if (!isISO(v)) return v ?? "";
  const [y, m, d] = v.split("-");
  return `${d}/${MESES[+m - 1]}/${y}`;
};

const S = {
  schema: null,
  anio: Number(localStorage.getItem("ct.anio")) || 2026,
  datos: {},          // modulo -> filas
  filtros: {},        // modulo -> {q, estatus, lugar, pend, sort, desc}
  editando: null,     // {modulo, fila}
};

/* ---------------------------------------------------------------- API */
async function api(path, opts = {}) {
  const r = await fetch(path, opts);
  const ct = r.headers.get("content-type") || "";
  const body = ct.includes("json") ? await r.json() : await r.text();
  if (!r.ok) throw new Error(body && body.error ? body.error : `Error ${r.status}`);
  return body;
}
const modulo = (id) => S.schema.modulos.find((m) => m.id === id);
async function cargar(id, force = false) {
  const m = modulo(id);
  const key = id + (m.anual ? ":" + S.anio : "");
  if (!force && S.datos[key]) return S.datos[key];
  const q = m.anual ? `?anio=${S.anio}` : "";
  S.datos[key] = await api(`/api/${id}${q}`);
  return S.datos[key];
}
const invalidar = () => { S.datos = {}; };

/* ---------------------------------------------------------------- estatus */
function grupoEstatus(v) {
  if (!v) return "";
  let s = String(v).toUpperCase().trim();
  // quita fechas tipo 17/03/2026, 09-MAR-26 y lo que sigue
  s = s.replace(/\s*[-–]?\s*\d{1,2}[\/-](\d{1,2}|[A-Z]{3})[\/-]\d{2,4}.*$/, "").trim();
  return s.replace(/\s+/g, " ");
}
function claseEstatus(v) {
  const s = grupoEstatus(v);
  if (!s) return "none";
  if (/CANCEL|RECHAZ|VACANTE|BAJA|TERMINADO|PRUEBA/.test(s)) return "off";
  if (/SIN SUPPLY|^NO$|SIN SEGUIMIENTO|ERROR/.test(s)) return "bad";
  if (/PAGAD|^OK$|ACTIVA|ENTREGADA|APROBADA|COMPLET/.test(s)) return "ok";
  if (/FLUJO|CONTRALOR|PRECARGA|REVISI/.test(s)) return "info";
  return "warn";
}
const esPendiente = (v) => !["ok", "off"].includes(claseEstatus(v));
const badge = (v) => `<span class="badge ${claseEstatus(v)}">${esc(v || "Sin estatus")}</span>`;

/* ---------------------------------------------------------------- utilidades UI */
function toast(msg, error = false) {
  const t = $("#toast");
  t.textContent = msg;
  t.className = "show" + (error ? " error" : "");
  clearTimeout(t._h);
  t._h = setTimeout(() => (t.className = ""), error ? 5000 : 2500);
}
function filtrosDe(id) {
  if (!S.filtros[id]) {
    let saved = {};
    try { saved = JSON.parse(sessionStorage.getItem("ct.f." + id) || "{}"); } catch (e) { /* nada */ }
    S.filtros[id] = Object.assign({ q: "", estatus: null, lugar: "", pend: false, sort: null, desc: false }, saved);
  }
  return S.filtros[id];
}
function guardarFiltros(id) {
  try { sessionStorage.setItem("ct.f." + id, JSON.stringify(S.filtros[id])); } catch (e) { /* nada */ }
}
function setAcciones(html) { $("#acciones").innerHTML = html; }
function setTitulo(t) { $("#titulo").textContent = t; document.title = t + " · Control Total"; }

/* ---------------------------------------------------------------- navegación */
function renderNav() {
  const nav = $("#nav");
  const ruta = location.hash || "#/inicio";
  let html = `<a class="nav-link" href="#/inicio">🏠 Inicio</a>`;
  let grupo = null;
  for (const m of S.schema.modulos) {
    if (m.grupo !== grupo) { grupo = m.grupo; html += `<div class="nav-group">${esc(grupo)}</div>`; }
    html += `<a class="nav-link" href="#/m/${m.id}">${m.icono} ${esc(m.titulo)}</a>`;
  }
  html += `<div class="nav-group">Herramientas</div>
    <a class="nav-link" href="#/importar">📥 Importar Excel</a>
    <a class="nav-link" href="#/respaldos">🗂️ Respaldos y bitácora</a>`;
  nav.innerHTML = html;
  nav.querySelectorAll(".nav-link").forEach((a) => a.classList.toggle("active", a.getAttribute("href") === ruta));
}

async function router() {
  const ruta = location.hash || "#/inicio";
  renderNav();
  $("#sidebar").classList.remove("open");
  const vista = $("#vista");
  try {
    if (ruta.startsWith("#/m/")) {
      const id = ruta.slice(4).split("?")[0];
      if (!modulo(id)) return (location.hash = "#/inicio");
      await vistaModulo(id);
    } else if (ruta === "#/importar") {
      vistaImportar();
    } else if (ruta === "#/respaldos") {
      await vistaRespaldos();
    } else {
      await vistaInicio();
    }
  } catch (e) {
    vista.innerHTML = `<div class="card empty">⚠️ ${esc(e.message)}</div>`;
  }
}

/* ---------------------------------------------------------------- INICIO */
function barras(conteos, total, alClick, valor = (x) => x.n) {
  return `<div class="bars">${conteos.map((c) => `
    <div class="bar-row" data-go="${esc(c.key)}">
      <span class="bar-label">${badge(c.key)}</span>
      <span class="bar-val">${esc(valor(c))}</span>
      <div class="bar-track"><div class="bar-fill ${claseEstatus(c.key)}" style="width:${total ? (c.n / total) * 100 : 0}%"></div></div>
    </div>`).join("")}</div>`;
}
function contar(filas, campo, importe) {
  const mapa = new Map();
  for (const f of filas) {
    const k = grupoEstatus(f[campo]);
    const x = mapa.get(k) || { key: k, n: 0, imp: 0 };
    x.n++; x.imp += importe ? Number(f[importe] || 0) : 0;
    mapa.set(k, x);
  }
  return [...mapa.values()].sort((a, b) => b.n - a.n);
}

async function vistaInicio() {
  setTitulo("Inicio");
  setAcciones(`<a class="btn" href="#/importar">📥 Importar Excel</a>`);
  const ids = S.schema.modulos.map((m) => m.id);
  const todos = await Promise.all(ids.map((id) => cargar(id)));
  const D = Object.fromEntries(ids.map((id, i) => [id, todos[i]]));
  const totalRegs = todos.reduce((a, b) => a + b.length, 0);
  const vista = $("#vista");

  if (!totalRegs) {
    vista.innerHTML = `<div class="card empty">
      <div style="font-size:40px">📂</div>
      <h2>Aún no hay datos</h2>
      <p>Importa tu archivo <b>CONTROL TOTAL.xlsx</b> para cargar las requis, Panamericano, servicios y tarjetas de ${S.anio}.</p>
      <a class="btn primary" href="#/importar">Importar Excel</a></div>`;
    return;
  }

  const req = D.requis, pan = D.panamericano, serv = D.servicios;
  const sum = (arr, c) => arr.reduce((a, f) => a + Number(f[c] || 0), 0);
  const reqPend = req.filter((f) => esPendiente(f.estatus));
  const panPend = pan.filter((f) => esPendiente(f.estatus));
  const servPend = serv.filter((f) => grupoEstatus(f.supply) !== "OK");

  const card = (id, cuerpo) => {
    const m = modulo(id);
    return `<div class="card"><h3>${m.icono} ${esc(m.titulo)} <a href="#/m/${id}">Ver todo →</a></h3>${cuerpo}</div>`;
  };
  const cReq = contar(req, "estatus", "importe");
  const cPan = contar(pan, "estatus", "subtotal");
  const cServ = contar(serv, "supply");
  const tarjetas = ["tarjetas_dispersion", "tarjetas_combustible", "tarjetas_iave"].map((id) => {
    const m = modulo(id);
    const c = contar(D[id], m.campo_estatus);
    return card(id, `<div class="kpi-row"><div><div class="kpi">${D[id].length}</div><div class="kpi-sub">registros</div></div></div>
      ${barras(c.slice(0, 6), D[id].length)}`);
  }).join("");

  vista.innerHTML = `
    <div class="grid">
      ${card("requis", `<div class="kpi-row">
          <div><div class="kpi">${req.length}</div><div class="kpi-sub">requis ${S.anio}</div></div>
          <div><div class="kpi">${fmtMoney(sum(req, "importe"))}</div><div class="kpi-sub">importe total</div></div>
        </div>
        <div class="kpi-sub">Pendientes: <b>${reqPend.length}</b> · ${fmtMoney(sum(reqPend, "importe"))}</div>
        ${barras(cReq, req.length, null, (x) => `${x.n} · ${fmtMoney(x.imp)}`)}`)}
      ${card("panamericano", `<div class="kpi-row">
          <div><div class="kpi">${pan.length}</div><div class="kpi-sub">facturas ${S.anio}</div></div>
          <div><div class="kpi">${fmtMoney(sum(pan, "subtotal"))}</div><div class="kpi-sub">subtotal</div></div>
        </div>
        <div class="kpi-sub">${["TIJUANA", "MEXICALI"].map((s) => `${s}: <b>${pan.filter((f) => (f.sucursal || "") === s).length}</b>`).join(" · ")} · Por pagar: <b>${panPend.length}</b></div>
        ${barras(cPan, pan.length, null, (x) => `${x.n} · ${fmtMoney(x.imp)}`)}`)}
      ${card("servicios", `<div class="kpi-row">
          <div><div class="kpi">${serv.length}</div><div class="kpi-sub">servicios ${S.anio}</div></div>
          <div><div class="kpi">${servPend.length}</div><div class="kpi-sub">sin supply OK</div></div>
        </div>${barras(cServ, serv.length)}`)}
      ${tarjetas}
    </div>
    <h2 class="section-title">📌 Requis pendientes (${reqPend.length})</h2>
    <div id="pendReq"></div>
    <h2 class="section-title">🧾 Servicios por completar (${servPend.length})</h2>
    <div id="pendServ"></div>`;

  vista.querySelectorAll(".card").forEach((c) => {
    const link = c.querySelector("h3 a");
    if (!link) return;
    const id = link.getAttribute("href").slice(4);
    c.querySelectorAll(".bar-row").forEach((row) => row.addEventListener("click", () => {
      const f = filtrosDe(id);
      Object.assign(f, { estatus: row.dataset.go, q: "", lugar: "", pend: false });
      guardarFiltros(id);
      location.hash = `#/m/${id}`;
    }));
  });
  tablaSimple($("#pendReq"), "requis", reqPend.sort((a, b) => (a.fecha || "").localeCompare(b.fecha || "")));
  tablaSimple($("#pendServ"), "servicios", servPend);
}

function tablaSimple(cont, id, filas) {
  const m = modulo(id);
  if (!filas.length) { cont.innerHTML = `<div class="card empty">Nada pendiente 🎉</div>`; return; }
  const campos = m.campos.filter((c) => c.list);
  cont.innerHTML = `<div class="table-wrap" style="max-height:420px"><table><thead><tr>${campos.map((c) => `<th class="${c.type === "money" ? "num" : ""}">${esc(c.label)}</th>`).join("")}</tr></thead>
    <tbody>${filas.map((f) => filaHTML(m, campos, f)).join("")}</tbody></table></div>`;
  cont.querySelectorAll("tbody tr").forEach((tr) => tr.addEventListener("click", () => abrirForm(id, filas.find((f) => f.id === +tr.dataset.id))));
}

/* ---------------------------------------------------------------- MÓDULO (tabla) */
function celda(m, c, v) {
  if (c.id === m.campo_estatus || c.id === "estatus" || c.id === "estatus_actual" || c.id === "supply" || c.id === "estatus_reposicion" || c.id === "estatus_nueva")
    return `<td>${v ? badge(v) : ""}</td>`;
  if (c.type === "money") return `<td class="num">${fmtMoney(v)}</td>`;
  if (c.type === "date") return `<td class="date">${esc(fmtDate(v))}</td>`;
  if (c.type === "ref") return `<td class="ref">${esc(v)}</td>`;
  if (c.type === "long") return `<td class="long"><div class="clamp" title="${esc(v)}">${esc(v)}</div></td>`;
  return `<td>${esc(v)}</td>`;
}
const filaHTML = (m, campos, f) => `<tr data-id="${f.id}">${campos.map((c) => celda(m, c, f[c.id])).join("")}</tr>`;

function filtrar(m, filas, F) {
  const q = F.q.trim().toLowerCase();
  const terms = q ? q.split(/\s+/) : [];
  let out = filas.filter((f) => {
    if (F.estatus !== null && F.estatus !== undefined && grupoEstatus(f[m.campo_estatus]) !== F.estatus) return false;
    if (F.lugar && (f[m.campo_lugar] || "") !== F.lugar) return false;
    if (F.pend && !esPendiente(f[m.campo_estatus])) return false;
    if (terms.length) {
      const txt = m.campos.map((c) => f[c.id] ?? "").join(" ").toLowerCase();
      if (!terms.every((t) => txt.includes(t))) return false;
    }
    return true;
  });
  if (F.sort) {
    const c = m.campos.find((x) => x.id === F.sort);
    const num = c && (c.type === "money" || c.type === "ref");
    out = out.slice().sort((a, b) => {
      let x = a[F.sort], y = b[F.sort];
      if (x === null || x === undefined || x === "") return 1;
      if (y === null || y === undefined || y === "") return -1;
      let r;
      if (num && !isNaN(+x) && !isNaN(+y)) r = +x - +y;
      else r = String(x).localeCompare(String(y), "es", { numeric: true });
      return F.desc ? -r : r;
    });
  }
  return out;
}

async function vistaModulo(id) {
  const m = modulo(id);
  setTitulo(`${m.icono} ${m.titulo}${m.anual ? " " + S.anio : ""}`);
  setAcciones(`
    <input type="search" class="search" id="buscar" placeholder="Buscar… (REQ, OC, material, nombre)" autocomplete="off">
    <a class="btn" id="btnCsv" title="Descargar como CSV (se abre en Excel)">⬇️ Exportar</a>
    <button class="btn primary" id="btnNuevo">＋ Nuevo <kbd>N</kbd></button>`);
  const vista = $("#vista");
  vista.innerHTML = `<div class="card empty">Cargando…</div>`;
  const filas = await cargar(id);
  const F = filtrosDe(id);
  const campos = m.campos.filter((c) => c.list);

  $("#buscar").value = F.q;
  $("#btnCsv").href = `/api/${id}/csv${m.anual ? "?anio=" + S.anio : ""}`;
  $("#btnNuevo").onclick = () => abrirForm(id, null);

  let tabsHTML = "";
  const hermanos = S.schema.modulos.filter((x) => x.grupo === m.grupo);
  if (m.grupo === "Tarjetas") {
    tabsHTML = `<div class="tabs">${hermanos.map((h) => `<a class="tab ${h.id === id ? "active" : ""}" href="#/m/${h.id}">${h.icono} ${esc(h.titulo)}</a>`).join("")}</div>`;
  }

  const pintar = () => {
    const conteo = contar(filas, m.campo_estatus);
    const lugares = [...new Set(filas.map((f) => f[m.campo_lugar]).filter(Boolean))].sort();
    const lista = filtrar(m, filas, F);
    const etiquetaEst = m.campos.find((c) => c.id === m.campo_estatus).label;
    const etiquetaLugar = m.campos.find((c) => c.id === m.campo_lugar).label;
    const totalImp = m.campo_importe ? lista.reduce((a, f) => a + Number(f[m.campo_importe] || 0), 0) : null;

    vista.innerHTML = `${tabsHTML}
      <div class="filters">
        <span class="chip ${F.estatus === null ? "active" : ""}" data-est="__all">Todos <b>${filas.length}</b></span>
        ${conteo.map((c) => `<span class="chip ${F.estatus === c.key ? "active" : ""}" data-est="${esc(c.key)}">${esc(c.key || "Sin " + etiquetaEst.toLowerCase())} <b>${c.n}</b></span>`).join("")}
        <span class="sep"></span>
        <label class="chip ${F.pend ? "active" : ""}"><input type="checkbox" id="soloPend" ${F.pend ? "checked" : ""} hidden>⏳ Solo pendientes</label>
        <select id="lugar" title="${esc(etiquetaLugar)}"><option value="">${esc(etiquetaLugar)}: todos</option>
          ${lugares.map((l) => `<option ${F.lugar === l ? "selected" : ""}>${esc(l)}</option>`).join("")}</select>
      </div>
      ${filas.length ? `<div class="table-wrap"><table>
        <thead><tr>${campos.map((c) => `<th data-sort="${c.id}" class="${c.type === "money" ? "num" : ""} ${F.sort === c.id ? "sorted" + (F.desc ? " desc" : "") : ""}">${esc(c.label)}</th>`).join("")}</tr></thead>
        <tbody>${lista.map((f) => filaHTML(m, campos, f)).join("") || `<tr><td colspan="${campos.length}" class="empty">Sin resultados con estos filtros</td></tr>`}</tbody>
        <tfoot><tr><td colspan="${campos.length}">${lista.length} de ${filas.length} registros${totalImp !== null ? ` · Total: ${fmtMoney(totalImp)}` : ""}</td></tr></tfoot>
      </table></div>` : `<div class="card empty">No hay registros${m.anual ? " en " + S.anio : ""}.<br>
        <button class="btn primary" id="vacioNuevo">＋ Agregar el primero</button> <a class="btn" href="#/importar">📥 Importar Excel</a></div>`}`;

    vista.querySelectorAll("[data-est]").forEach((ch) => ch.addEventListener("click", () => {
      F.estatus = ch.dataset.est === "__all" ? null : (F.estatus === ch.dataset.est ? null : ch.dataset.est);
      guardarFiltros(id); pintar();
    }));
    const sp = $("#soloPend");
    if (sp) sp.parentElement.addEventListener("click", (e) => { e.preventDefault(); F.pend = !F.pend; guardarFiltros(id); pintar(); });
    const lg = $("#lugar");
    if (lg) lg.onchange = () => { F.lugar = lg.value; guardarFiltros(id); pintar(); };
    vista.querySelectorAll("th[data-sort]").forEach((th) => th.addEventListener("click", () => {
      if (F.sort === th.dataset.sort) { if (F.desc) { F.sort = null; F.desc = false; } else F.desc = true; }
      else { F.sort = th.dataset.sort; F.desc = false; }
      guardarFiltros(id); pintar();
    }));
    vista.querySelectorAll("tbody tr[data-id]").forEach((tr) => tr.addEventListener("click", () =>
      abrirForm(id, filas.find((f) => f.id === +tr.dataset.id))));
    const vn = $("#vacioNuevo");
    if (vn) vn.onclick = () => abrirForm(id, null);
  };

  let t;
  $("#buscar").oninput = (e) => { clearTimeout(t); t = setTimeout(() => { F.q = e.target.value; guardarFiltros(id); pintar(); }, 120); };
  pintar();
}

/* ---------------------------------------------------------------- formulario */
function abrirForm(id, fila, copia = false) {
  const m = modulo(id);
  S.editando = { id, fila: copia ? null : fila };
  const nuevo = !S.editando.fila;
  $("#modalTitulo").textContent = `${m.icono} ${nuevo ? (copia ? "Copia de registro" : "Nuevo registro") : "Editar registro"} · ${m.titulo}`;

  // sugerencias a partir de lo ya capturado
  const key = id + (m.anual ? ":" + S.anio : "");
  const existentes = S.datos[key] || [];
  const datalists = [];
  let html = "";
  if (m.anual) {
    html += `<div class="field"><label>Año</label><input name="anio" type="number" min="2000" max="2100" value="${esc(fila?.anio ?? S.anio)}"></div>`;
  }
  for (const c of m.campos) {
    const v = fila ? fila[c.id] ?? "" : "";
    const req = c.required ? ` required` : "";
    const lab = `<label for="f_${c.id}">${esc(c.label)}${c.required ? ' <span class="req">*</span>' : ""}</label>`;
    let input;
    if (c.type === "long") {
      input = `<textarea id="f_${c.id}" name="${c.id}" rows="2"${req}>${esc(v)}</textarea>`;
    } else if (c.type === "money") {
      input = `<input id="f_${c.id}" name="${c.id}" type="number" step="0.01" inputmode="decimal" value="${esc(v)}"${req}>`;
    } else if (c.type === "date" && (v === "" || isISO(v))) {
      input = `<input id="f_${c.id}" name="${c.id}" type="date" value="${esc(v)}"${req}>`;
    } else {
      let dl = "";
      if (c.suggest) {
        const opts = [...new Set(existentes.map((f) => f[c.id]).filter(Boolean))].sort().slice(0, 200);
        dl = ` list="dl_${c.id}"`;
        datalists.push(`<datalist id="dl_${c.id}">${opts.map((o) => `<option value="${esc(o)}">`).join("")}</datalist>`);
      }
      input = `<input id="f_${c.id}" name="${c.id}" type="text" value="${esc(v)}"${dl}${req} autocomplete="off">`;
    }
    html += `<div class="field ${c.wide ? "wide" : ""}">${lab}${input}</div>`;
  }
  $("#formCampos").innerHTML = html + datalists.join("");
  $("#formMeta").textContent = S.editando.fila
    ? `ID ${fila.id} · origen: ${fila.origen || "-"} · creado ${fila.creado || "-"} · última modificación ${fila.actualizado || "-"}`
    : "";
  $("#btnEliminar").style.display = nuevo ? "none" : "";
  $("#btnDuplicar").style.display = nuevo ? "none" : "";
  $("#modal").showModal();
  const primero = $("#formCampos").querySelector("input, textarea");
  if (primero) setTimeout(() => primero.focus(), 30);
}

async function guardarForm() {
  const { id, fila } = S.editando;
  const form = $("#form");
  if (!form.reportValidity()) return;
  const datos = Object.fromEntries(new FormData(form).entries());
  const btn = $("#btnGuardar");
  btn.disabled = true;
  try {
    const opts = { method: fila ? "PUT" : "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(datos) };
    await api(fila ? `/api/${id}/${fila.id}` : `/api/${id}`, opts);
    $("#modal").close();
    toast(fila ? "Cambios guardados ✔" : "Registro agregado ✔");
    invalidar();
    router();
  } catch (e) {
    toast(e.message, true);
  } finally {
    btn.disabled = false;
  }
}

async function eliminarActual() {
  const { id, fila } = S.editando;
  if (!fila || !confirm("¿Eliminar este registro? (queda guardado en la bitácora)")) return;
  try {
    await api(`/api/${id}/${fila.id}`, { method: "DELETE" });
    $("#modal").close();
    toast("Registro eliminado");
    invalidar();
    router();
  } catch (e) { toast(e.message, true); }
}

/* ---------------------------------------------------------------- IMPORTAR */
function vistaImportar() {
  setTitulo("📥 Importar Excel");
  setAcciones("");
  const mods = S.schema.modulos;
  $("#vista").innerHTML = `
    <div class="card" style="max-width:820px">
      <p>Selecciona tu archivo <b>CONTROL TOTAL.xlsx</b>. Se leen solo las hojas del año elegido
      (<i>REQUI TIJ / MXC ${S.anio}, PANAMERICANO TIJ / MXC ${S.anio}, SERVICIOS ${S.anio}</i>) y las hojas de tarjetas.</p>
      <div class="checks">
        <label>Año: <input type="number" id="impAnio" value="${S.anio}" min="2000" max="2100" style="width:90px"></label>
      </div>
      <div class="checks">${mods.map((m) => `<label><input type="checkbox" class="impMod" value="${m.id}" checked> ${m.icono} ${esc(m.titulo)}</label>`).join("")}</div>
      <label class="drop" id="drop">
        <input type="file" id="archivo" accept=".xlsx,.xlsm" hidden>
        <strong>Arrastra aquí el archivo o haz clic para elegirlo</strong>
        <span class="kpi-sub">Formato .xlsx</span>
      </label>
      <p class="kpi-sub" style="margin-top:12px">⚠️ Los módulos marcados se <b>reemplazan</b> con lo que trae el Excel
      (solo el año elegido; los demás años no se tocan). Antes de importar se guarda un respaldo automático en la carpeta <code>respaldos</code>.</p>
      <div id="impResultado"></div>
    </div>`;
  const drop = $("#drop"), input = $("#archivo");
  const subir = async (file) => {
    if (!file) return;
    const sel = [...document.querySelectorAll(".impMod:checked")].map((x) => x.value);
    if (!sel.length) return toast("Selecciona al menos un módulo", true);
    const anio = +$("#impAnio").value || S.anio;
    if (!confirm(`Se reemplazarán los datos ${anio} de: ${sel.map((s) => modulo(s).titulo).join(", ")}.\n\n¿Continuar?`)) return;
    $("#impResultado").innerHTML = `<p>⏳ Importando <b>${esc(file.name)}</b>…</p>`;
    try {
      const r = await api(`/api/importar?anio=${anio}&modulos=${sel.join(",")}`, { method: "POST", body: file, headers: { "Content-Type": "application/octet-stream" } });
      invalidar();
      const s = await api("/api/schema");
      S.schema.anios = s.anios;
      S.anio = anio; localStorage.setItem("ct.anio", anio); pintarAnios();
      $("#impResultado").innerHTML = `<h3>✅ Importación terminada</h3>${Object.entries(r.resumen).map(([id, x]) => `
        <div class="log-item"><b>${modulo(id).icono} ${esc(x.titulo)}</b>: ${x.importados} registros importados
        ${x.reemplazados ? `(${x.reemplazados} anteriores reemplazados)` : ""}
        <ul class="result-list">${x.notas.map((n) => `<li>${esc(n)}</li>`).join("")}</ul></div>`).join("")}
        <p><a class="btn primary" href="#/inicio">Ir al inicio</a></p>`;
      toast("Importación completa ✔");
    } catch (e) {
      $("#impResultado").innerHTML = `<p class="badge bad">Error: ${esc(e.message)}</p>`;
    }
    input.value = "";
  };
  input.onchange = () => subir(input.files[0]);
  drop.addEventListener("dragover", (e) => { e.preventDefault(); drop.classList.add("over"); });
  drop.addEventListener("dragleave", () => drop.classList.remove("over"));
  drop.addEventListener("drop", (e) => { e.preventDefault(); drop.classList.remove("over"); subir(e.dataTransfer.files[0]); });
}

/* ---------------------------------------------------------------- RESPALDOS */
async function vistaRespaldos() {
  setTitulo("🗂️ Respaldos y bitácora");
  setAcciones("");
  const log = await api("/api/bitacora");
  const nombreMod = (id) => (id && modulo(id) ? modulo(id).titulo : "General");
  $("#vista").innerHTML = `
    <div class="grid">
      <div class="card">
        <h3>💾 Respaldo</h3>
        <p class="kpi-sub">La base de datos es un solo archivo:<br><code>${esc(S.schema.db)}</code></p>
        <p class="kpi-sub">Para llevarte todo a otra computadora, copia la carpeta completa de la app (incluye ese archivo).</p>
        <a class="btn primary" href="/api/respaldo">⬇️ Descargar respaldo</a>
        <button class="btn" id="btnRespLocal">Guardar copia en carpeta "respaldos"</button>
      </div>
      <div class="card">
        <h3>📤 Exportar a Excel (CSV)</h3>
        <div class="bars">${S.schema.modulos.map((m) => `<a class="btn" href="/api/${m.id}/csv${m.anual ? "?anio=" + S.anio : ""}">${m.icono} ${esc(m.titulo)}${m.anual ? " " + S.anio : ""}</a>`).join("")}</div>
      </div>
    </div>
    <h2 class="section-title">📝 Bitácora de cambios (últimos 300)</h2>
    <div class="card">${log.length ? log.map((l) => `
      <div class="log-item"><b>${esc(l.fecha)}</b> · ${esc(l.accion)} · ${esc(nombreMod(l.modulo))}${l.registro_id ? " #" + l.registro_id : ""}
      ${l.detalle && l.accion === "editar" ? `<br><code>${esc(Object.entries(JSON.parse(l.detalle)).map(([k, [a, b]]) => `${k}: "${a ?? ""}" → "${b ?? ""}"`).join(" | "))}</code>` : ""}
      ${l.accion === "eliminar" && l.detalle ? `<br><code>${esc(l.detalle).slice(0, 300)}</code>` : ""}</div>`).join("") : '<div class="empty">Sin movimientos todavía</div>'}</div>`;
  $("#btnRespLocal").onclick = async () => {
    try { const r = await api("/api/respaldo", { method: "POST" }); toast("Respaldo guardado: " + r.archivo); }
    catch (e) { toast(e.message, true); }
  };
}

/* ---------------------------------------------------------------- arranque */
function pintarAnios() {
  const sel = $("#anio");
  const anios = [...new Set([...S.schema.anios, S.anio])].sort((a, b) => b - a);
  sel.innerHTML = anios.map((a) => `<option ${a === S.anio ? "selected" : ""}>${a}</option>`).join("");
}

async function init() {
  S.schema = await api("/api/schema");
  $("#version").textContent = "v" + S.schema.version;
  $("#dbpath").textContent = "💾 " + S.schema.db;
  pintarAnios();
  $("#anio").onchange = (e) => { S.anio = +e.target.value; localStorage.setItem("ct.anio", S.anio); router(); };
  $("#menuBtn").onclick = () => $("#sidebar").classList.toggle("open");
  $("#form").addEventListener("submit", (e) => { e.preventDefault(); guardarForm(); });
  $("#cerrarModal").onclick = $("#btnCancelar").onclick = () => $("#modal").close();
  $("#btnEliminar").onclick = eliminarActual;
  $("#btnDuplicar").onclick = () => { const { id, fila } = S.editando; $("#modal").close(); abrirForm(id, fila, true); };
  $("#modal").addEventListener("keydown", (e) => { if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); guardarForm(); } });
  document.addEventListener("keydown", (e) => {
    if ($("#modal").open || e.ctrlKey || e.metaKey || e.altKey) return;
    const tag = (document.activeElement?.tagName || "").toLowerCase();
    if (["input", "textarea", "select"].includes(tag)) return;
    const ruta = location.hash;
    if (e.key === "n" && ruta.startsWith("#/m/")) { e.preventDefault(); abrirForm(ruta.slice(4), null); }
    if (e.key === "/") { const b = $("#buscar"); if (b) { e.preventDefault(); b.focus(); } }
  });
  window.addEventListener("hashchange", router);
  router();
}
init().catch((e) => { document.body.innerHTML = `<p style="padding:40px">No se pudo iniciar: ${esc(e.message)}</p>`; });
