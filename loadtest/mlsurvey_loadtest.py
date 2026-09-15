#!/usr/bin/env python3
"""
Prueba de carga para mlsurvey.

Por cada fila del CSV (email, hash, url) simula un participante que:
  1. FASE «request»: pide el código de participación en /get_code
     (paso A: fija la consulta en sesión; paso B: envía el email y dispara
      el correo con el enlace de voto).
  2. FASE «vote»: abre el enlace de voto (url) en /participate, que fija la
     cookie de firma `lacookie` y devuelve el formulario, y envía un voto.

Solo usa la librería estándar de Python 3 (sin dependencias). La concurrencia
son hilos: --concurrency N mantiene hasta N peticiones en vuelo a la vez.

IMPORTANTE (leer antes de lanzarlo):
  * Las URLs de voto del CSV deben ser enlaces de participación válidos y SIN
    usar (pid+auth), pre-sembrados en la BD. En el sistema real llegan por
    email; esta prueba no lee buzones. Cada enlace es de un solo uso
    (hasParticipated bloquea el segundo voto), así que para repetir la fase de
    voto hay que resembrar la BD.
  * La fase «request» ENVÍA correos de verdad por el SMTP configurado. Para una
    prueba de carga, apunta el SMTP a un sumidero de pruebas (MailHog / smtp4dev)
    o a un relay de captura, no a un proveedor real.
  * Los emails deben pertenecer a un dominio permitido (SystemConfig
    .alloweddomains) o el paso B fallará con «dominio autorizado».

Ejemplos:
  python3 mlsurvey_loadtest.py -b http://localhost:8080 -c 200 \
      --phase both --survey 1 participants.csv
  python3 mlsurvey_loadtest.py -b https://consultas.ejemplo.org -c 200 \
      --phase vote --insecure votos.csv
"""

import argparse
import csv
import http.cookiejar
import re
import ssl
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass, field

# --------------------------------------------------------------------------- #
# Utilidades HTTP (una cookie jar por participante = sesión aislada)
# --------------------------------------------------------------------------- #


def build_opener(insecure: bool) -> urllib.request.OpenerDirector:
    cj = http.cookiejar.CookieJar()
    ctx = ssl.create_default_context()
    if insecure:
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(cj),
        urllib.request.HTTPSHandler(context=ctx),
    )


def http_call(opener, method, url, data=None, timeout=30.0):
    """Devuelve (status, body_texto, segundos). Los 4xx/5xx no lanzan."""
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(url, data=body, method=method)
    req.add_header("User-Agent", "mlsurvey-loadtest/1.0")
    if body is not None:
        req.add_header("Content-Type", "application/x-www-form-urlencoded")
    t0 = time.perf_counter()
    try:
        with opener.open(req, timeout=timeout) as resp:
            text = resp.read().decode("utf-8", "replace")
            status = resp.status
    except urllib.error.HTTPError as e:
        text = e.read().decode("utf-8", "replace")
        status = e.code
    except Exception as e:  # noqa: BLE001 - se reporta como fallo de red
        return (0, f"__neterror__:{type(e).__name__}:{e}", time.perf_counter() - t0)
    return (status, text, time.perf_counter() - t0)


def join_url(base: str, path: str) -> str:
    return base.rstrip("/") + "/" + path.lstrip("/")


# --------------------------------------------------------------------------- #
# Parseo del HTML devuelto por la app
# --------------------------------------------------------------------------- #

_TOKEN_RE = re.compile(
    r"name=['\"]token['\"][^>]*?value=['\"]([0-9a-fA-F]+)['\"]"
    r"|value=['\"]([0-9a-fA-F]+)['\"][^>]*?name=['\"]token['\"]"
)
_MULTIPLE_RE = re.compile(r"name=['\"]multiple-(\d+)['\"][^>]*?value=['\"]([01])['\"]")
_TOPT_RE = re.compile(r"name=['\"]topt-(\d+)['\"][^>]*?value=['\"](\d+)['\"]")
_OPTIONAL_RE = re.compile(r"name=['\"]optional-(\d+)['\"][^>]*?value=['\"]([01])['\"]")
_TOTALQ_RE = re.compile(r"name=['\"]totalquestions['\"][^>]*?value=['\"](\d+)['\"]")


def find_token(html: str):
    m = _TOKEN_RE.search(html)
    if not m:
        return None
    return m.group(1) or m.group(2)


def first_radio_value(html: str, qid: str):
    """Primer optionid de la pregunta de respuesta única `qid`."""
    m = re.search(
        r"name=['\"]op-%s['\"][^>]*?value=['\"](\d+)['\"]" % re.escape(qid), html
    )
    return m.group(1) if m else None


def build_vote_form(html: str):
    """Construye los campos POST de un voto válido a partir del formulario.

    - Pregunta única (multiple=0): manda op-<qid> con el primer optionid.
    - Pregunta múltiple (multiple=1): marca op-<qid>-1 (el servidor lee
      op-<qid>-<i> con i=1..topt, de forma posicional).
    Devuelve (form_dict, None) o (None, motivo_error).
    """
    token = find_token(html)
    if not token:
        return None, "sin-token"

    form = {"token": token, "Enviar": "Enviar"}

    mt = _TOTALQ_RE.search(html)
    if mt:
        form["totalquestions"] = mt.group(1)

    topt = dict(_TOPT_RE.findall(html))
    optional = dict(_OPTIONAL_RE.findall(html))
    multiples = _MULTIPLE_RE.findall(html)
    if not multiples:
        return None, "sin-preguntas"

    for qid, mult in multiples:
        # Reenviamos los ocultos como haría el navegador (inocuos para el servidor).
        if qid in topt:
            form["topt-%s" % qid] = topt[qid]
        form["multiple-%s" % qid] = mult
        if qid in optional:
            form["optional-%s" % qid] = optional[qid]

        if mult == "0":
            val = first_radio_value(html, qid)
            if val is None:
                return None, "opcion-no-encontrada-q%s" % qid
            form["op-%s" % qid] = val
        else:
            form["op-%s-1" % qid] = "on"

    return form, None


# --------------------------------------------------------------------------- #
# Métricas
# --------------------------------------------------------------------------- #


@dataclass
class Metrics:
    lock: threading.Lock = field(default_factory=threading.Lock)
    lat: dict = field(default_factory=dict)          # etiqueta -> [segundos]
    ok: dict = field(default_factory=dict)           # etiqueta -> nº éxitos
    fail: dict = field(default_factory=dict)         # etiqueta -> {motivo: nº}
    inflight: int = 0
    peak: int = 0

    def record(self, label, seconds, ok=True, reason=None):
        with self.lock:
            self.lat.setdefault(label, []).append(seconds)
            if ok:
                self.ok[label] = self.ok.get(label, 0) + 1
            else:
                d = self.fail.setdefault(label, {})
                d[reason] = d.get(reason, 0) + 1

    def enter(self):
        with self.lock:
            self.inflight += 1
            self.peak = max(self.peak, self.inflight)

    def leave(self):
        with self.lock:
            self.inflight -= 1


def pct(values, p):
    if not values:
        return 0.0
    s = sorted(values)
    k = max(0, min(len(s) - 1, int(round((p / 100.0) * (len(s) - 1)))))
    return s[k]


# --------------------------------------------------------------------------- #
# Fases
# --------------------------------------------------------------------------- #

_ERR_MARKERS_REQUEST = [
    ("dominio autorizado", "dominio-no-permitido"),
    ("ya ha participado", "ya-participo"),
    ("no está configurado", "sin-configurar"),
    ("es incorrecta", "email-incorrecto"),
    ("token de seguridad", "token"),
]

_ERR_MARKERS_VOTE_GET = [
    ("no existe o ha caducado", "enlace-invalido"),
    ("ya se ha participado", "ya-participo"),
    ("problema de seguridad", "clave-corrupta"),
    ("no son", "credenciales"),
]

_ERR_MARKERS_VOTE_POST = [
    ("token de seguridad", "token"),
    ("obligatioras", "faltan-obligatorias"),  # (sic) tal cual en el código
    ("cookies para firmar", "sin-cookie"),
    ("cryptográfico", "firma"),
    ("guardando respuestas", "insert"),
]


def classify(body_lower, markers, default):
    for needle, reason in markers:
        if needle in body_lower:
            return reason
    return default


def phase_request(opener, base, survey_id, email, timeout, metrics):
    """Pide código en /get_code (paso A + paso B). Devuelve True si ok."""
    url = join_url(base, "get_code")

    # Paso A: fija la consulta en la sesión y obtiene el token.
    st, body, dt = http_call(
        opener, "POST", url,
        {"response": "Ver y participar", "responseid": survey_id},
        timeout,
    )
    if st != 200 or body.startswith("__neterror__"):
        metrics.record("request_A", dt, ok=False, reason=_net_reason(st, body))
        return False
    token = find_token(body)
    if not token:
        metrics.record("request_A", dt, ok=False, reason="sin-token")
        return False
    metrics.record("request_A", dt, ok=True)

    # Paso B: envía el email y dispara el correo.
    st, body, dt = http_call(
        opener, "POST", url,
        {"token": token, "email": email, "Solicitar": "Solicitar"},
        timeout,
    )
    if st != 200 or body.startswith("__neterror__"):
        metrics.record("request_B", dt, ok=False, reason=_net_reason(st, body))
        return False
    if "ha sido enviado" in body:
        metrics.record("request_B", dt, ok=True)
        return True
    reason = classify(body.lower(), _ERR_MARKERS_REQUEST, "desconocido")
    metrics.record("request_B", dt, ok=False, reason=reason)
    return False


def phase_vote(opener, base, vote_url, timeout, metrics):
    """Abre el enlace de voto y envía un voto. Devuelve True si ok."""
    # Paso A (GET): fija PHPSESSID + lacookie y devuelve el formulario.
    st, body, dt = http_call(opener, "GET", join_url(base, vote_url), None, timeout)
    if st != 200 or body.startswith("__neterror__"):
        metrics.record("vote_GET", dt, ok=False, reason=_net_reason(st, body))
        return False
    low = body.lower()
    if 'name="participate"' not in body and "id='participate'" not in low:
        reason = classify(low, _ERR_MARKERS_VOTE_GET, "sin-formulario")
        metrics.record("vote_GET", dt, ok=False, reason=reason)
        return False
    form, err = build_vote_form(body)
    if err:
        metrics.record("vote_GET", dt, ok=False, reason=err)
        return False
    metrics.record("vote_GET", dt, ok=True)

    # Paso B (POST): envía el voto (usa lacookie de la jar para firmar).
    st, body, dt = http_call(
        opener, "POST", join_url(base, "participate"), form, timeout
    )
    if st != 200 or body.startswith("__neterror__"):
        metrics.record("vote_POST", dt, ok=False, reason=_net_reason(st, body))
        return False
    if "Respuestas guardadas" in body:
        metrics.record("vote_POST", dt, ok=True)
        return True
    reason = classify(body.lower(), _ERR_MARKERS_VOTE_POST, "desconocido")
    metrics.record("vote_POST", dt, ok=False, reason=reason)
    return False


def _net_reason(status, body):
    if body.startswith("__neterror__"):
        return body.split(":", 2)[1] if ":" in body else "neterror"
    return "http-%d" % status


# --------------------------------------------------------------------------- #
# Orquestación
# --------------------------------------------------------------------------- #


@dataclass
class Row:
    email: str
    hashval: str
    url: str
    survey_id: str


def run_participant(row, base, phases, survey_default, timeout, insecure, metrics):
    opener = build_opener(insecure)
    metrics.enter()
    try:
        if "request" in phases:
            sid = row.survey_id or survey_default
            if not sid:
                metrics.record("request_A", 0.0, ok=False, reason="sin-surveyid")
                return
            if not phase_request(opener, base, sid, row.email, timeout, metrics):
                # Si falla la solicitud y también tocaba votar, seguimos igual:
                # la url de voto es independiente (pre-sembrada).
                pass
        if "vote" in phases:
            phase_vote(opener, base, row.url, timeout, metrics)
    finally:
        metrics.leave()


def load_rows(path):
    rows = []
    with open(path, newline="", encoding="utf-8") as fh:
        reader = csv.reader(fh)
        for i, rec in enumerate(reader):
            if not rec or all(not c.strip() for c in rec):
                continue
            cells = [c.strip() for c in rec]
            if i == 0 and cells[0].lower() in ("email", "correo", "e-mail"):
                continue  # cabecera
            email = cells[0] if len(cells) > 0 else ""
            hashval = cells[1] if len(cells) > 1 else ""
            url = cells[2] if len(cells) > 2 else ""
            survey_id = cells[3] if len(cells) > 3 else ""
            rows.append(Row(email, hashval, url, survey_id))
    return rows


def human_ms(seconds):
    return "%.0f" % (seconds * 1000.0)


def print_report(metrics, phases, walltime, tasks):
    order = []
    if "request" in phases:
        order += ["request_A", "request_B"]
    if "vote" in phases:
        order += ["vote_GET", "vote_POST"]

    print("\n" + "=" * 72)
    print("RESULTADO DE LA PRUEBA DE CARGA")
    print("=" * 72)
    print("Participantes (tareas): %d" % tasks)
    print("Tiempo total:           %.2f s" % walltime)
    if walltime > 0:
        print("Throughput:             %.1f participantes/s" % (tasks / walltime))
    print("Concurrencia pico:      %d peticiones simultáneas" % metrics.peak)
    print("-" * 72)
    print("%-12s %7s %7s %7s %8s %8s %8s %8s" %
          ("paso", "ok", "fail", "n", "p50(ms)", "p90(ms)", "p99(ms)", "max(ms)"))
    total_reqs = 0
    for label in order:
        lat = metrics.lat.get(label, [])
        total_reqs += len(lat)
        ok = metrics.ok.get(label, 0)
        failn = sum(metrics.fail.get(label, {}).values())
        print("%-12s %7d %7d %7d %8s %8s %8s %8s" % (
            label, ok, failn, len(lat),
            human_ms(pct(lat, 50)), human_ms(pct(lat, 90)),
            human_ms(pct(lat, 99)), human_ms(max(lat) if lat else 0),
        ))
    print("-" * 72)
    if walltime > 0:
        print("Peticiones HTTP totales: %d  (%.1f req/s)" %
              (total_reqs, total_reqs / walltime))

    # Desglose de fallos
    any_fail = any(metrics.fail.get(l) for l in order)
    if any_fail:
        print("\nFallos por motivo:")
        for label in order:
            fails = metrics.fail.get(label)
            if not fails:
                continue
            detalle = ", ".join("%s=%d" % (r, n) for r, n in sorted(fails.items()))
            print("  %-12s %s" % (label, detalle))
    print("=" * 72)


def main(argv=None):
    ap = argparse.ArgumentParser(
        description="Prueba de carga para mlsurvey (get_code + participate).",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    ap.add_argument("csv", help="CSV con columnas: email,hash,url[,surveyid]")
    ap.add_argument("-b", "--base", required=True,
                    help="URL base, p.ej. http://localhost:8080")
    ap.add_argument("-c", "--concurrency", type=int, default=200,
                    help="Peticiones concurrentes máximas")
    ap.add_argument("--phase", choices=["both", "request", "vote"], default="both",
                    help="Fases a ejecutar")
    ap.add_argument("--survey", default="",
                    help="surveyid para la fase request (si no viene en el CSV)")
    ap.add_argument("--repeat", type=int, default=1,
                    help="Repite cada fila N veces (útil para sostener carga en "
                         "la fase request; la fase vote es de un solo uso por url)")
    ap.add_argument("--timeout", type=float, default=30.0,
                    help="Timeout por petición HTTP (s)")
    ap.add_argument("--insecure", action="store_true",
                    help="No verificar el certificado TLS (certs de prueba)")
    args = ap.parse_args(argv)

    phases = {"both": ("request", "vote"),
              "request": ("request",),
              "vote": ("vote",)}[args.phase]

    rows = load_rows(args.csv)
    if not rows:
        print("El CSV no tiene filas.", file=sys.stderr)
        return 2
    tasks = []
    for _ in range(max(1, args.repeat)):
        tasks.extend(rows)

    if "request" in phases:
        print("AVISO: la fase 'request' envía correos reales por el SMTP "
              "configurado. Usa un sumidero de pruebas (MailHog/smtp4dev).",
              file=sys.stderr)

    print("Lanzando %d participantes (%d filas x%d), concurrencia=%d, fases=%s"
          % (len(tasks), len(rows), args.repeat, args.concurrency,
             "+".join(phases)))

    metrics = Metrics()
    t0 = time.perf_counter()
    with ThreadPoolExecutor(max_workers=args.concurrency) as pool:
        futs = [
            pool.submit(run_participant, row, args.base, phases, args.survey,
                        args.timeout, args.insecure, metrics)
            for row in tasks
        ]
        for _ in as_completed(futs):
            pass
    walltime = time.perf_counter() - t0

    print_report(metrics, phases, walltime, len(tasks))
    return 0


if __name__ == "__main__":
    sys.exit(main())
