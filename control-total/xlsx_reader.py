"""Lector mínimo de archivos .xlsx usando solo la librería estándar de Python.

No depende de openpyxl ni de nada externo, así la app sigue siendo 100% portable.
Devuelve los valores ya calculados (los que guarda Excel), no las fórmulas.
"""
import re
import zipfile
import posixpath
import xml.etree.ElementTree as ET
from datetime import datetime, timedelta

NS_MAIN = "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}"
NS_REL = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
NS_PKG_REL = "{http://schemas.openxmlformats.org/package/2006/relationships}"

_COL_RE = re.compile(r"([A-Z]+)(\d+)")


def col_index(letters):
    n = 0
    for ch in letters:
        n = n * 26 + (ord(ch) - 64)
    return n - 1


def excel_serial_to_date(value):
    """Convierte un número de serie de Excel a 'AAAA-MM-DD'."""
    try:
        v = float(value)
    except (TypeError, ValueError):
        return None
    if v < 1 or v > 200000:
        return None
    return (datetime(1899, 12, 30) + timedelta(days=v)).strftime("%Y-%m-%d")


class XlsxReader:
    def __init__(self, path_or_file):
        self.zip = zipfile.ZipFile(path_or_file)
        self.shared = self._load_shared_strings()
        self.sheets = self._load_sheet_index()  # nombre -> ruta interna

    def _load_shared_strings(self):
        try:
            data = self.zip.read("xl/sharedStrings.xml")
        except KeyError:
            return []
        root = ET.fromstring(data)
        out = []
        for si in root.iter(NS_MAIN + "si"):
            # Texto simple <t> o texto enriquecido <r><t>; se ignoran <rPh> (fonética)
            parts = []
            for node in si:
                if node.tag == NS_MAIN + "t":
                    parts.append(node.text or "")
                elif node.tag == NS_MAIN + "r":
                    t = node.find(NS_MAIN + "t")
                    if t is not None:
                        parts.append(t.text or "")
            out.append("".join(parts))
        return out

    def _load_sheet_index(self):
        wb = ET.fromstring(self.zip.read("xl/workbook.xml"))
        rels = ET.fromstring(self.zip.read("xl/_rels/workbook.xml.rels"))
        targets = {}
        for rel in rels.iter(NS_PKG_REL + "Relationship"):
            target = rel.get("Target")
            if target.startswith("/"):
                path = target.lstrip("/")
            else:
                path = posixpath.normpath(posixpath.join("xl", target))
            targets[rel.get("Id")] = path
        sheets = {}
        for sh in wb.iter(NS_MAIN + "sheet"):
            rid = sh.get(NS_REL + "id")
            if rid in targets:
                sheets[sh.get("name")] = targets[rid]
        return sheets

    def find_sheet(self, *candidates):
        """Busca una hoja por nombre sin importar mayúsculas/espacios."""
        norm = {re.sub(r"\s+", " ", k).strip().upper(): k for k in self.sheets}
        for c in candidates:
            key = re.sub(r"\s+", " ", c).strip().upper()
            if key in norm:
                return norm[key]
        return None

    def rows(self, sheet_name):
        """Itera filas como listas de valores (str, float o None)."""
        path = self.sheets[sheet_name]
        with self.zip.open(path) as fh:
            for _, el in ET.iterparse(fh, events=("end",)):
                if el.tag != NS_MAIN + "row":
                    continue
                values = {}
                for c in el.iter(NS_MAIN + "c"):
                    ref = c.get("r")
                    m = _COL_RE.match(ref or "")
                    if not m:
                        continue
                    idx = col_index(m.group(1))
                    values[idx] = self._cell_value(c)
                el.clear()
                if not values:
                    yield []
                    continue
                row = [None] * (max(values) + 1)
                for i, v in values.items():
                    row[i] = v
                yield row

    def _cell_value(self, c):
        t = c.get("t")
        if t == "inlineStr":
            is_ = c.find(NS_MAIN + "is")
            if is_ is None:
                return None
            return "".join(x.text or "" for x in is_.iter(NS_MAIN + "t"))
        v = c.find(NS_MAIN + "v")
        if v is None or v.text is None:
            return None
        text = v.text
        if t == "s":
            try:
                return self.shared[int(text)]
            except (ValueError, IndexError):
                return None
        if t in ("str", "e"):
            return None if t == "e" else text
        if t == "b":
            return "SI" if text == "1" else "NO"
        try:
            return float(text)
        except ValueError:
            return text
