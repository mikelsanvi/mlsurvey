#!/bin/sh
# Lee el buzon donde Postfix deja todo el correo de desarrollo.
#
#   mlmail          lista los mensajes
#   mlmail <n>      muestra el mensaje n entero
#   mlmail -c       vacia el buzon
set -e

MAILDIR=/var/mail/catchall/Maildir

listar () {
    n=0
    for f in "$MAILDIR"/new/* "$MAILDIR"/cur/*; do
        [ -f "$f" ] || continue
        n=$((n + 1))
        printf '%3d  %s\n' "$n" "$(grep -m1 '^Subject:' "$f" | cut -c10-)"
        printf '     de %s  para %s\n' \
            "$(grep -m1 '^From:' "$f" | cut -c7-)" \
            "$(grep -m1 '^To:' "$f" | cut -c5-)"
    done
    [ "$n" -eq 0 ] && echo "Buzon vacio."
    return 0
}

mostrar () {
    n=0
    for f in "$MAILDIR"/new/* "$MAILDIR"/cur/*; do
        [ -f "$f" ] || continue
        n=$((n + 1))
        if [ "$n" -eq "$1" ]; then
            cat "$f"
            return 0
        fi
    done
    echo "No hay mensaje $1." >&2
    return 1
}

case "$1" in
    "")   listar ;;
    -c)   rm -f "$MAILDIR"/new/* "$MAILDIR"/cur/* 2>/dev/null || true
          echo "Buzon vaciado." ;;
    *)    mostrar "$1" ;;
esac
