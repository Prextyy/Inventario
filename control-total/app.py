"""CONTROL TOTAL - aplicación portable para administrar Requis, Panamericano,
Servicios y Tarjetas.

Uso:
    python app.py                      -> abre la app en el navegador
    python app.py --importar ARCHIVO   -> importa el Excel (año 2026) y sale
    python app.py --puerto 8800        -> usa otro puerto

Solo usa la librería estándar de Python (sin instalar nada). Los datos se
guardan en 'control_total.db' (SQLite) en la misma carpeta de la app.
"""
import argparse
import csv
import io
import json
import mimetypes
import os
import shutil
import sqlite3
import sys
import threading
import webbrowser
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

APP_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, APP_DIR)

from modulos import MODULOS, MODULOS_POR_ID, columnas  # noqa: E402
import importador  # noqa: E402

VERSION = "1.0.0"

if getattr(sys, "frozen", False):  # ejecutable generado con PyInstaller
    DATA_DIR = os.path.dirname(sys.executable)
    STATIC_DIR = os.path.join(getattr(sys, "_MEIPASS", DATA_DIR), "static")
else:
    DATA_DIR = APP_DIR
    STATIC_DIR = os.path.join(APP_DIR, "static")

DB_PATH = os.environ.get("CONTROL_TOTAL_DB") or os.path.join(DATA_DIR, "control_total.db")
RESPALDOS_DIR = os.path.join(os.path.dirname(os.path.abspath(DB_PATH)), "respaldos")
SQL_TYPES = {"money": "REAL"}
_db_lock = threading.Lock()


# ------------------------------------------------------------------ base de datos
def conectar():
    con = sqlite3.connect(DB_PATH, timeout=10)
    con.row_factory = sqlite3.Row
    con.execute("PRAGMA foreign_keys = ON")
    return con


def inicializar_db():
    with conectar() as con:
        con.execute("PRAGMA journal_mode = DELETE")
        for m in MODULOS:
            cols = ["id INTEGER PRIMARY KEY AUTOINCREMENT"]
            if m["anual"]:
                cols.append("anio INTEGER NOT NULL DEFAULT 2026")
            for c in m["campos"]:
                cols.append(f'"{c["id"]}" {SQL_TYPES.get(c["type"], "TEXT")}')
            cols += ["origen TEXT DEFAULT 'manual'", "creado TEXT", "actualizado TEXT"]
            con.execute(f'CREATE TABLE IF NOT EXISTS "{m["id"]}" ({", ".join(cols)})')
            # Migración: agrega columnas nuevas que se hayan definido en modulos.py
            existentes = {r["name"] for r in con.execute(f'PRAGMA table_info("{m["id"]}")')}
            for c in m["campos"]:
                if c["id"] not in existentes:
                    con.execute(f'ALTER TABLE "{m["id"]}" ADD COLUMN "{c["id"]}" {SQL_TYPES.get(c["type"], "TEXT")}')
            if m["anual"]:
                con.execute(f'CREATE INDEX IF NOT EXISTS "ix_{m["id"]}_anio" ON "{m["id"]}"(anio)')
        con.execute("""CREATE TABLE IF NOT EXISTS bitacora (
            id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT, modulo TEXT,
            registro_id INTEGER, accion TEXT, detalle TEXT)""")


def ahora():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def respaldar(motivo="manual"):
    """Copia segura de la base de datos a la carpeta 'respaldos'."""
    if not os.path.exists(DB_PATH):
        return None
    os.makedirs(RESPALDOS_DIR, exist_ok=True)
    destino = os.path.join(RESPALDOS_DIR, f"control_total_{datetime.now():%Y%m%d_%H%M%S}_{motivo}.db")
    with conectar() as src, sqlite3.connect(destino) as dst:
        src.backup(dst)
    # conserva solo los 30 respaldos más recientes
    archivos = sorted(f for f in os.listdir(RESPALDOS_DIR) if f.endswith(".db"))
    for viejo in archivos[:-30]:
        try:
            os.remove(os.path.join(RESPALDOS_DIR, viejo))
        except OSError:
            pass
    return destino


def _valores(modulo, datos):
    tipos = {c["id"]: c["type"] for c in modulo["campos"]}
    out = {}
    for campo in columnas(modulo):
        if campo not in datos:
            continue
        v = datos[campo]
        if isinstance(v, str):
            v = v.strip()
            if v == "":
                v = None
        if v is not None and tipos[campo] == "money":
            try:
                v = round(float(str(v).replace(",", "").replace("$", "")), 2)
            except ValueError:
                raise ValueError(f"El importe '{v}' no es un número válido")
        elif v is not None and not isinstance(v, str):
            v = str(v)
        out[campo] = v
    return out


def listar(modulo, anio=None):
    sql = f'SELECT * FROM "{modulo["id"]}"'
    params = []
    if modulo["anual"] and anio:
        sql += " WHERE anio = ?"
        params.append(int(anio))
    sql += " ORDER BY id"
    with conectar() as con:
        return [dict(r) for r in con.execute(sql, params)]


def obtener(con, modulo, rid):
    r = con.execute(f'SELECT * FROM "{modulo["id"]}" WHERE id = ?', (rid,)).fetchone()
    return dict(r) if r else None


def crear(modulo, datos, origen="manual", con=None):
    vals = _valores(modulo, datos)
    if modulo["anual"]:
        vals["anio"] = int(datos.get("anio") or datetime.now().year)
    vals.update(origen=origen, creado=ahora(), actualizado=ahora())
    cols = ", ".join(f'"{k}"' for k in vals)
    marks = ", ".join("?" for _ in vals)
    propio = con is None
    con = con or conectar()
    try:
        cur = con.execute(f'INSERT INTO "{modulo["id"]}" ({cols}) VALUES ({marks})', list(vals.values()))
        rid = cur.lastrowid
        if propio:
            con.execute("INSERT INTO bitacora (fecha, modulo, registro_id, accion, detalle) VALUES (?,?,?,?,?)",
                        (ahora(), modulo["id"], rid, "crear", None))
            con.commit()
        return obtener(con, modulo, rid)
    finally:
        if propio:
            con.close()


def actualizar(modulo, rid, datos):
    vals = _valores(modulo, datos)
    if modulo["anual"] and datos.get("anio"):
        vals["anio"] = int(datos["anio"])
    with conectar() as con:
        antes = obtener(con, modulo, rid)
        if not antes:
            return None
        vals["actualizado"] = ahora()
        sets = ", ".join(f'"{k}" = ?' for k in vals)
        con.execute(f'UPDATE "{modulo["id"]}" SET {sets} WHERE id = ?', list(vals.values()) + [rid])
        cambios = {k: [antes.get(k), v] for k, v in vals.items() if k != "actualizado" and antes.get(k) != v}
        if cambios:
            con.execute("INSERT INTO bitacora (fecha, modulo, registro_id, accion, detalle) VALUES (?,?,?,?,?)",
                        (ahora(), modulo["id"], rid, "editar", json.dumps(cambios, ensure_ascii=False)))
        return obtener(con, modulo, rid)


def eliminar(modulo, rid):
    with conectar() as con:
        antes = obtener(con, modulo, rid)
        if not antes:
            return False
        con.execute(f'DELETE FROM "{modulo["id"]}" WHERE id = ?', (rid,))
        con.execute("INSERT INTO bitacora (fecha, modulo, registro_id, accion, detalle) VALUES (?,?,?,?,?)",
                    (ahora(), modulo["id"], rid, "eliminar", json.dumps(antes, ensure_ascii=False)))
        return True


def importar_archivo(archivo, anio=2026, modulos=None):
    """Reemplaza los datos de cada módulo encontrado en el Excel (del año indicado)."""
    datos = importador.leer_excel(archivo, anio, modulos)
    if not datos:
        raise ValueError("No encontré hojas reconocibles en el archivo (¿es el CONTROL TOTAL?).")
    with _db_lock:
        respaldar("antes_importar")
        resumen = {}
        con = conectar()
        try:
            for mid, info in datos.items():
                m = MODULOS_POR_ID[mid]
                if m["anual"]:
                    borrados = con.execute(f'DELETE FROM "{mid}" WHERE anio = ?', (anio,)).rowcount
                else:
                    borrados = con.execute(f'DELETE FROM "{mid}"').rowcount
                for reg in info["registros"]:
                    reg = dict(reg)
                    if m["anual"]:
                        reg["anio"] = anio
                    crear(m, reg, origen="excel", con=con)
                resumen[mid] = {"titulo": m["titulo"], "importados": len(info["registros"]),
                                "reemplazados": borrados, "notas": info["notas"]}
            con.execute("INSERT INTO bitacora (fecha, modulo, registro_id, accion, detalle) VALUES (?,?,?,?,?)",
                        (ahora(), None, None, "importar", json.dumps(resumen, ensure_ascii=False)))
            con.commit()
        except Exception:
            con.rollback()
            raise
        finally:
            con.close()
    return resumen


def anios_disponibles():
    anios = {datetime.now().year, 2026}
    with conectar() as con:
        for m in MODULOS:
            if m["anual"]:
                anios.update(r[0] for r in con.execute(f'SELECT DISTINCT anio FROM "{m["id"]}"'))
    return sorted(anios, reverse=True)


def csv_modulo(modulo, anio=None):
    filas = listar(modulo, anio)
    buf = io.StringIO()
    w = csv.writer(buf)
    cab = ([("anio", "Año")] if modulo["anual"] else []) + [(c["id"], c["label"]) for c in modulo["campos"]]
    w.writerow([c[1] for c in cab])
    for f in filas:
        w.writerow(["" if f.get(k) is None else f.get(k) for k, _ in cab])
    return "﻿" + buf.getvalue()  # BOM para que Excel respete acentos


# ------------------------------------------------------------------ servidor HTTP
class Handler(BaseHTTPRequestHandler):
    server_version = "ControlTotal/" + VERSION

    def log_message(self, fmt, *args):  # silencioso
        pass

    # --- utilidades
    def _send(self, code, body, ctype="application/json; charset=utf-8", extra=None):
        if isinstance(body, (dict, list)):
            body = json.dumps(body, ensure_ascii=False, default=str)
        if isinstance(body, str):
            body = body.encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        for k, v in (extra or {}).items():
            self.send_header(k, v)
        self.end_headers()
        self.wfile.write(body)

    def _error(self, code, msg):
        self._send(code, {"error": msg})

    def _body(self):
        n = int(self.headers.get("Content-Length") or 0)
        return self.rfile.read(n) if n else b""

    def _json(self):
        raw = self._body()
        return json.loads(raw.decode("utf-8")) if raw else {}

    def _route(self):
        u = urlparse(self.path)
        partes = [p for p in u.path.split("/") if p]
        q = {k: v[0] for k, v in parse_qs(u.query).items()}
        return partes, q

    def _check_origin(self):
        # Evita que otras páginas web abiertas en el navegador modifiquen tus datos
        origin = self.headers.get("Origin")
        if origin and urlparse(origin).hostname not in ("127.0.0.1", "localhost"):
            self._error(403, "Origen no permitido")
            return False
        return True

    # --- GET
    def do_GET(self):
        partes, q = self._route()
        try:
            if not partes or partes[0] != "api":
                return self._static(partes)
            if partes[1:] == ["schema"]:
                return self._send(200, {"modulos": MODULOS, "anios": anios_disponibles(),
                                        "version": VERSION, "db": DB_PATH})
            if partes[1:] == ["respaldo"]:
                ruta = respaldar("descarga")
                with open(ruta, "rb") as fh:
                    data = fh.read()
                nombre = os.path.basename(ruta)
                return self._send(200, data, "application/octet-stream",
                                  {"Content-Disposition": f'attachment; filename="{nombre}"'})
            if partes[1:] == ["bitacora"]:
                with conectar() as con:
                    filas = [dict(r) for r in con.execute(
                        "SELECT * FROM bitacora ORDER BY id DESC LIMIT 300")]
                return self._send(200, filas)
            if len(partes) >= 2 and partes[1] in MODULOS_POR_ID:
                m = MODULOS_POR_ID[partes[1]]
                if len(partes) == 3 and partes[2] == "csv":
                    nombre = f'{m["id"]}_{q.get("anio", "todo")}.csv'
                    return self._send(200, csv_modulo(m, q.get("anio")), "text/csv; charset=utf-8",
                                      {"Content-Disposition": f'attachment; filename="{nombre}"'})
                return self._send(200, listar(m, q.get("anio")))
            return self._error(404, "No encontrado")
        except Exception as e:  # pragma: no cover
            return self._error(500, str(e))

    def _static(self, partes):
        rel = "/".join(partes) or "index.html"
        ruta = os.path.normpath(os.path.join(STATIC_DIR, rel))
        if not ruta.startswith(os.path.normpath(STATIC_DIR)) or not os.path.isfile(ruta):
            return self._error(404, "No encontrado")
        ctype = mimetypes.guess_type(ruta)[0] or "application/octet-stream"
        if ctype.startswith("text/") or ctype in ("application/javascript",):
            ctype += "; charset=utf-8"
        with open(ruta, "rb") as fh:
            self._send(200, fh.read(), ctype)

    # --- POST / PUT / DELETE
    def do_POST(self):
        if not self._check_origin():
            return
        partes, q = self._route()
        try:
            if partes[:2] == ["api", "importar"]:
                data = self._body()
                if not data:
                    return self._error(400, "No se recibió ningún archivo")
                anio = int(q.get("anio") or 2026)
                mods = [x for x in (q.get("modulos") or "").split(",") if x] or None
                resumen = importar_archivo(io.BytesIO(data), anio, mods)
                return self._send(200, {"ok": True, "resumen": resumen})
            if partes[:2] == ["api", "respaldo"]:
                return self._send(200, {"ok": True, "archivo": respaldar("manual")})
            if len(partes) == 2 and partes[0] == "api" and partes[1] in MODULOS_POR_ID:
                with _db_lock:
                    return self._send(201, crear(MODULOS_POR_ID[partes[1]], self._json()))
            return self._error(404, "No encontrado")
        except ValueError as e:
            return self._error(400, str(e))
        except Exception as e:
            return self._error(500, f"{type(e).__name__}: {e}")

    def do_PUT(self):
        if not self._check_origin():
            return
        partes, _ = self._route()
        try:
            if len(partes) == 3 and partes[0] == "api" and partes[1] in MODULOS_POR_ID:
                with _db_lock:
                    r = actualizar(MODULOS_POR_ID[partes[1]], int(partes[2]), self._json())
                return self._send(200, r) if r else self._error(404, "Registro no encontrado")
            return self._error(404, "No encontrado")
        except ValueError as e:
            return self._error(400, str(e))
        except Exception as e:
            return self._error(500, f"{type(e).__name__}: {e}")

    def do_DELETE(self):
        if not self._check_origin():
            return
        partes, _ = self._route()
        try:
            if len(partes) == 3 and partes[0] == "api" and partes[1] in MODULOS_POR_ID:
                with _db_lock:
                    ok = eliminar(MODULOS_POR_ID[partes[1]], int(partes[2]))
                return self._send(200, {"ok": True}) if ok else self._error(404, "Registro no encontrado")
            return self._error(404, "No encontrado")
        except Exception as e:
            return self._error(500, f"{type(e).__name__}: {e}")


def servir(puerto, abrir=True):
    for p in range(puerto, puerto + 20):
        try:
            httpd = ThreadingHTTPServer(("127.0.0.1", p), Handler)
            break
        except OSError:
            continue
    else:
        print("No encontré un puerto libre.")
        sys.exit(1)
    url = f"http://127.0.0.1:{p}/"
    print("=" * 60)
    print("  CONTROL TOTAL  v" + VERSION)
    print(f"  Abierto en:     {url}")
    print(f"  Base de datos:  {DB_PATH}")
    print("  Para cerrar la app, cierra esta ventana (o Ctrl+C).")
    print("=" * 60)
    if abrir:
        threading.Timer(0.8, lambda: webbrowser.open(url)).start()
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        httpd.server_close()


def main():
    ap = argparse.ArgumentParser(description="CONTROL TOTAL")
    ap.add_argument("--puerto", type=int, default=8765)
    ap.add_argument("--importar", metavar="ARCHIVO_XLSX", help="Importa el Excel y sale")
    ap.add_argument("--anio", type=int, default=2026, help="Año a importar (default 2026)")
    ap.add_argument("--no-navegador", action="store_true", help="No abrir el navegador")
    args = ap.parse_args()

    inicializar_db()
    if args.importar:
        resumen = importar_archivo(args.importar, args.anio)
        for info in resumen.values():
            print(f'{info["titulo"]}: {info["importados"]} importados ({info["reemplazados"]} reemplazados)')
            for n in info["notas"]:
                print("   -", n)
        return
    servir(args.puerto, abrir=not args.no_navegador)


if __name__ == "__main__":
    main()
