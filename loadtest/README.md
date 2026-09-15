# Prueba de carga de mlsurvey

`mlsurvey_loadtest.py` simula participantes concurrentes contra los dos endpoints
que nos preocupan por escalabilidad:

- **`get_code`** — pedir el código de participación (genera el par de claves en
  el servidor y **envía un correo** con el enlace de voto).
- **`participate`** — abrir el enlace de voto y enviar la respuesta firmada.

Solo usa la librería estándar de Python 3 (no hay que instalar nada). La
concurrencia son hilos: `--concurrency N` mantiene hasta N peticiones en vuelo.

## Formato del CSV

Una fila por participante, columnas separadas por comas:

```
email,hash,url[,surveyid]
```

| Columna    | Uso                                                                    |
|------------|------------------------------------------------------------------------|
| `email`    | Se envía en la fase `request`. Debe ser de un dominio permitido.       |
| `hash`     | Informativo (p.ej. `sha256(email)`); el script no lo necesita.         |
| `url`      | Enlace de voto **pre-sembrado**, p.ej. `participate?pid=42&auth=xyz`.   |
| `surveyid` | Opcional. surveyid para la fase `request` (si no, se usa `--survey`).  |

Se admite una fila de cabecera (si la primera celda es `email`/`correo` se salta).

## Antes de lanzarlo: dos avisos importantes

1. **Los enlaces de voto (`url`) hay que sembrarlos en la BD.** En el sistema
   real llegan por email y la prueba no lee buzones. Tienen que ser enlaces
   válidos y **sin usar** (cada `pid+auth` es de un solo uso: `hasParticipated`
   bloquea el segundo voto). Para repetir la fase de voto hay que resembrar.

2. **La fase `request` envía correos de verdad** por el SMTP configurado. Para
   una prueba de carga, apunta el SMTP a un **sumidero de pruebas**
   (MailHog, smtp4dev, o un relay de captura), no a un proveedor real.

## Sembrar datos de prueba

El objetivo es tener, para una consulta activa, N participantes con su par de
claves y N filas en `Participation` cuyos `pid`+código conozcas para construir
las URLs. La forma limpia es un pequeño script PHP que reutilice
`views/get_code.php::insertParticipant()` y la lógica de `generateCode()` pero
que, en vez de enviar el correo, **escriba el enlace en el CSV**. Con eso
obtienes el `pid`, el `code` en claro y `url_base64_encode($code)`, que es
justo lo que va en la columna `url`.

(Si prefieres, puedo generarte ese script de siembra: dime y lo añado aquí.)

## Uso

```bash
# Solo la fase de voto (enlaces ya sembrados), 200 concurrentes:
python3 mlsurvey_loadtest.py -b http://localhost:8080 -c 200 \
    --phase vote votos.csv

# Solo la fase de solicitud de código para la consulta 1 (SMTP a un sumidero):
python3 mlsurvey_loadtest.py -b http://localhost:8080 -c 200 \
    --phase request --survey 1 participantes.csv

# Ambas fases seguidas por participante:
python3 mlsurvey_loadtest.py -b http://localhost:8080 -c 200 \
    --phase both --survey 1 participantes.csv

# Contra HTTPS con certificado de prueba:
python3 mlsurvey_loadtest.py -b https://consultas.local -c 200 \
    --phase vote --insecure votos.csv
```

Opciones útiles: `--repeat N` repite cada fila N veces (sostiene carga en la
fase `request`; recuerda que la fase `vote` es de un solo uso por URL),
`--timeout` el timeout por petición.

## Interpretar el resultado

Se informa por paso HTTP (`request_A`, `request_B`, `vote_GET`, `vote_POST`):
éxitos, fallos, y latencias p50/p90/p99/max. Además:

- **Throughput** (participantes/s y peticiones/s).
- **Concurrencia pico** realmente alcanzada (para confirmar que llegas a ~200).
- **Desglose de fallos por motivo** (`dominio-no-permitido`, `enlace-invalido`,
  `ya-participo`, `http-500`, timeouts de red, etc.).

Qué mirar según lo que discutíamos:

- Si `request_B` se dispara en p99/max mientras subes concurrencia → es el
  **envío SMTP síncrono** bloqueando workers (el cuello de botella real).
- Si empiezan a aparecer timeouts o `http-500` en *cualquier* paso al subir la
  concurrencia → probable **agotamiento del pool de workers** de Apache/PHP.
- La latencia de `vote_*` aísla el coste de firma/BD sin el email de por medio.
