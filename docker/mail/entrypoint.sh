#!/bin/sh
set -e

MAIL_PASSWORD="${MAIL_PASSWORD:-mlsurvey}"

# La clave entra en la configuracion de dovecot al arrancar: su passdb
# estatico no lee variables de entorno.
sed "s|__MAIL_PASSWORD__|${MAIL_PASSWORD}|" \
    /etc/dovecot/dovecot.conf > /etc/dovecot/dovecot-runtime.conf
chmod 600 /etc/dovecot/dovecot-runtime.conf

echo "[mlmail] configurando postfix."

# Sin syslog en el contenedor, los registros irian a un socket que no
# existe: asi salen por docker compose logs.
postconf -e "maillog_file = /dev/stdout"

# --- Identidad y alcance -------------------------------------------------
postconf -e "myhostname = mail.mlsurvey.test"
postconf -e "mydestination ="
postconf -e "mynetworks = 127.0.0.0/8 [::1]/128 172.16.0.0/12 192.168.0.0/16 10.0.0.0/8"
postconf -e "inet_interfaces = all"
postconf -e "inet_protocols = ipv4"

# Sin salida a internet: ningun mensaje puede escaparse a una direccion
# real aunque alguien escriba mal un destinatario en una prueba.
postconf -e "default_transport = local:"
postconf -e "relayhost ="
postconf -e "disable_dns_lookups = yes"

# --- Entrega: todo a una sola usuaria ------------------------------------
# Con local_recipient_maps vacio, Postfix acepta cualquier destinatario y
# luser_relay se lleva los que no existen, que son todos menos catchall.
postconf -e "transport_maps = static:local:"
postconf -e "local_recipient_maps ="
postconf -e "luser_relay = catchall"
postconf -e "home_mailbox = Maildir/"
postconf -e "mailbox_size_limit = 0"

# --- Autenticacion (SASL via dovecot) ------------------------------------
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_security_options = noanonymous"
postconf -e "broken_sasl_auth_clients = yes"
# La aplicacion se autentica sin cifrar dentro de la red de compose.
postconf -e "smtpd_tls_auth_only = no"
postconf -e "smtpd_tls_security_level = none"

# Autenticada, puede enviar a donde quiera: da igual, todo acaba en el
# mismo buzon.
postconf -e "smtpd_relay_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination"
postconf -e "smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination"

# --- Puerto de envio (submission) ----------------------------------------
postconf -M "submission/inet=submission inet n - n - - smtpd"
postconf -P "submission/inet/syslog_name=postfix/submission"
postconf -P "submission/inet/smtpd_sasl_auth_enable=yes"
postconf -P "submission/inet/smtpd_tls_security_level=none"

# smtpd tiene que leer el socket de dovecot, que esta fuera del chroot.
postconf -F "smtp/inet/chroot=n"
postconf -F "submission/inet/chroot=n"

# --- Buzon ---------------------------------------------------------------
mkdir -p /var/mail/catchall/Maildir/new \
         /var/mail/catchall/Maildir/cur \
         /var/mail/catchall/Maildir/tmp
chown -R catchall:catchall /var/mail/catchall

mkdir -p /var/spool/postfix/private
chown postfix:postfix /var/spool/postfix/private

echo "[mlmail] arrancando dovecot (solo SASL)."
dovecot -c /etc/dovecot/dovecot-runtime.conf

echo "[mlmail] postfix escuchando en 587. Todo el correo va a /var/mail/catchall/Maildir."
echo "[mlmail] para leerlo: docker compose exec mail mlmail"

exec postfix start-fg
