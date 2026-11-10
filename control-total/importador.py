"""Importa los datos del Excel CONTROL TOTAL a la base de datos.

Solo toma las hojas del año indicado (por defecto 2026) para Requis,
Panamericano y Servicios, y las hojas de Tarjetas (Dispersión, Combustible e IAVE).
"""
import re
from collections import Counter, defaultdict

from modulos import MODULOS_POR_ID
from xlsx_reader import XlsxReader, excel_serial_to_date

VACIOS = {"", "-", "--", "N/A NO", "NONE"}


def _limpiar(valor, tipo):
    if valor is None:
        return None
    if isinstance(valor, str):
        v = valor.strip()
        if v.upper() in VACIOS:
            return None
        if tipo == "money":
            try:
                return round(float(v.replace(",", "").replace("$", "")), 2)
            except ValueError:
                return None
        if tipo == "date":
            m = re.fullmatch(r"(\d{1,2})[/-](\d{1,2})[/-](\d{4})", v)
            if m:
                return f"{m.group(3)}-{int(m.group(2)):02d}-{int(m.group(1)):02d}"
        return v
    # numérico
    if tipo == "money":
        return round(float(valor), 2)
    if tipo == "date":
        return excel_serial_to_date(valor) or str(valor)
    if float(valor).is_integer():
        return str(int(valor))
    return str(valor)


def _filas(reader, hoja, mapa, modulo, min_campos=("",)):
    """Convierte las filas de una hoja en dicts según 'mapa' (lista de campos por columna)."""
    tipos = {c["id"]: c["type"] for c in modulo["campos"]}
    out = []
    for n, row in enumerate(reader.rows(hoja)):
        if n == 0:
            continue  # encabezados
        reg = {}
        for i, campo in enumerate(mapa):
            if campo is None or i >= len(row):
                continue
            v = _limpiar(row[i], tipos[campo])
            if v is not None:
                reg[campo] = v
        if any(reg.get(c) for c in min_campos):
            out.append(reg)
    return out


# ---------------------------------------------------------------- REQUIS
MAPA_REQUI = ["almacen", "uo", "material", "fecha", "importe", "req", "oc", "recepcion",
              "estatus", "comentarios", "cuenta_contable", "nombre_cc", "item"]
MAPA_REQUI_SIN_CC = ["almacen", "uo", "material", "fecha", "importe", "req", "oc", "recepcion",
                     "estatus", "comentarios", "cuenta_contable", "item"]


def importar_requis(reader, anio):
    mod = MODULOS_POR_ID["requis"]
    principal = reader.find_sheet(f"REQUI TIJ {anio}", f"REQUI {anio}")
    secundaria = reader.find_sheet(f"REQUI MXC {anio}")
    if not principal and not secundaria:
        return None, []
    regs, notas = [], []
    vistos = set()
    if principal:
        for r in _filas(reader, principal, MAPA_REQUI, mod, ("material", "req")):
            regs.append(r)
            vistos.add((str(r.get("uo", "")).strip(), r.get("req")))
        notas.append(f"'{principal}': {len(regs)} registros")
    materiales = {r.get("material", "").strip() for r in regs}
    if secundaria:
        extra = 0
        for r in _filas(reader, secundaria, MAPA_REQUI_SIN_CC, mod, ("material", "req")):
            clave = (str(r.get("uo", "")).strip(), r.get("req"))
            if clave in vistos or (not r.get("req") and r.get("material", "").strip() in materiales):
                continue  # ya está en la hoja principal (la hoja MXC es copia)
            vistos.add(clave)
            regs.append(r)
            extra += 1
        notas.append(f"'{secundaria}': {extra} registros adicionales (los demás ya estaban en la hoja principal)")
    for r in regs:
        for k in ("almacen", "uo", "estatus"):
            if k in r:
                r[k] = r[k].strip().upper()
    return regs, notas


# ---------------------------------------------------------------- PANAMERICANO
MAPA_PANAM = ["sucursal", "folio_fiscal", "fecha_servicio", "fecha_factura", "subtotal",
              "estatus_actual", "sol_int", "oc_abierta", "despacho_oracle", "despacho",
              "aprobacion", "recepcion", "supply", "precio_neg", "dif_nc", "prog_pago",
              "sol_pago", "estatus", "comentarios"]


def importar_panamericano(reader, anio):
    """Las hojas TIJ y MXC traen las mismas facturas, pero cada una lleva al día
    el estatus de su propia sucursal. Se toma cada factura de la hoja de su sucursal."""
    mod = MODULOS_POR_ID["panamericano"]
    hojas = []
    for suc, nombre in (("TIJUANA", f"PANAMERICANO TIJ {anio}"), ("MEXICALI", f"PANAMERICANO MXC {anio}")):
        h = reader.find_sheet(nombre)
        if h:
            hojas.append((suc, h, _filas(reader, h, MAPA_PANAM, mod, ("sucursal", "folio_fiscal", "sol_int"))))
    if not hojas:
        return None, []

    # Sucursal por OC abierta (para filas donde no capturaste sucursal)
    oc_suc = defaultdict(Counter)
    for _, _, filas in hojas:
        for r in filas:
            if r.get("sucursal") and r.get("oc_abierta"):
                oc_suc[r["oc_abierta"]][r["sucursal"].upper()] += 1

    elegidos = {}   # clave -> (registro, es_hoja_de_su_sucursal)
    orden = []
    for suc_hoja, _, filas in hojas:
        for r in filas:
            suc = (r.get("sucursal") or "").strip().upper()
            if not suc and r.get("oc_abierta") in oc_suc:
                suc = oc_suc[r["oc_abierta"]].most_common(1)[0][0]
            if suc:
                r["sucursal"] = suc
            preferida = suc == suc_hoja
            clave = r.get("sol_int") or (r.get("folio_fiscal") or "").strip() or None
            if clave is None:
                if preferida:
                    orden.append(("__sin_clave__", r))
                continue
            if clave not in elegidos:
                elegidos[clave] = (r, preferida)
                orden.append((clave, None))
            elif preferida and not elegidos[clave][1]:
                elegidos[clave] = (r, True)
    regs = []
    for clave, r in orden:
        regs.append(r if clave == "__sin_clave__" else elegidos[clave][0])
    for r in regs:
        if r.get("folio_fiscal"):
            r["folio_fiscal"] = r["folio_fiscal"].strip()
    notas = [f"'{h}': {len(f)} filas leídas" for _, h, f in hojas]
    notas.append(f"{len(regs)} facturas únicas (cada una con el estatus de la hoja de su sucursal)")
    return regs, notas


# ---------------------------------------------------------------- SERVICIOS
MAPA_SERV = ["uo", "almacen", "proveedor", "servicio", "factura", "req", "oc", "recepcion",
             "supply", "comentarios"]


def importar_servicios(reader, anio):
    mod = MODULOS_POR_ID["servicios"]
    h = reader.find_sheet(f"SERVICIOS {anio}")
    if not h:
        return None, []
    regs = _filas(reader, h, MAPA_SERV, mod, ("proveedor", "servicio"))
    for r in regs:
        for k in ("uo", "almacen", "supply"):
            if k in r:
                r[k] = r[k].strip().upper()
    return regs, [f"'{h}': {len(regs)} registros"]


# ---------------------------------------------------------------- TARJETAS
MAPA_DISP = ["posicion", "zona", "usuario", "puesto", "num_empleado", "zona2", "suc", "almacen",
             "num_tarjeta", "vigencia", "estatus_reposicion", "nueva_tarjeta", "nueva_vigencia",
             "estatus_nueva"]
MAPA_COMB = ["posicion", "zona", "usuario", "puesto", "num_empleado", "zona2", "suc", "almacen",
             "vehiculo", "placas", "tanque", "combustible", "fecha", "estatus"]
MAPA_IAVE = ["posicion", "zona", "usuario", "puesto", "num_empleado", "zona2", "suc", "almacen",
             "vehiculo", "placas", "fecha", "estatus"]


def _tarjetas(reader, modulo_id, nombres, mapa, min_campos):
    h = reader.find_sheet(*nombres)
    if not h:
        return None, []
    regs = _filas(reader, h, mapa, MODULOS_POR_ID[modulo_id], min_campos)
    return regs, [f"'{h}': {len(regs)} registros"]


IMPORTADORES = {
    "requis": importar_requis,
    "panamericano": importar_panamericano,
    "servicios": importar_servicios,
    "tarjetas_dispersion": lambda r, a: _tarjetas(
        r, "tarjetas_dispersion", ["Tarjetas Dispersion FNA TIJ", "TARJETAS DISPERSION"], MAPA_DISP,
        ("usuario", "num_tarjeta")),
    "tarjetas_combustible": lambda r, a: _tarjetas(
        r, "tarjetas_combustible", ["TARJETAS DE COMBUSTIBLE", "TARJETAS COMBUSTIBLE"], MAPA_COMB,
        ("usuario", "vehiculo", "placas")),
    "tarjetas_iave": lambda r, a: _tarjetas(
        r, "tarjetas_iave", ["TARJETAS IAVE", "TARJETAS DE IAVE"], MAPA_IAVE,
        ("usuario", "vehiculo", "placas")),
}


def leer_excel(archivo, anio=2026, modulos=None):
    """Devuelve {modulo_id: {"registros": [...], "notas": [...]}} sin tocar la base de datos."""
    reader = XlsxReader(archivo)
    resultado = {}
    for mid, fn in IMPORTADORES.items():
        if modulos and mid not in modulos:
            continue
        regs, notas = fn(reader, anio)
        if regs is None:
            continue
        resultado[mid] = {"registros": regs, "notas": notas}
    return resultado
