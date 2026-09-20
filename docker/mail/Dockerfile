# Postfix de desarrollo: acepta correo autenticado de la aplicacion y lo
# entrega TODO a un unico buzon local. No tiene salida a internet, asi
# que es imposible que un mensaje de prueba acabe en una direccion real.
#
# Dovecot esta aqui solo por su SASL: MLMailer::configSMTP() fija
# SMTPAuth=true siempre, de modo que el servidor tiene que ofrecer AUTH,
# y Postfix no autentica por si mismo.
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# El paquete de postfix pregunta por consola: se le responde antes.
RUN echo "postfix postfix/main_mailer_type string Local only" | debconf-set-selections \
    && echo "postfix postfix/mailname string mail.mlsurvey.test" | debconf-set-selections \
    && apt-get update \
    && apt-get install -y --no-install-recommends postfix dovecot-core \
    && rm -rf /var/lib/apt/lists/*

# Usuaria que recibe absolutamente todo.
RUN useradd --create-home --home-dir /var/mail/catchall --shell /usr/sbin/nologin catchall

COPY docker/mail/dovecot-sasl.conf /etc/dovecot/dovecot.conf
COPY docker/mail/entrypoint.sh /usr/local/bin/mlmail-entrypoint
COPY docker/mail/readmail.sh /usr/local/bin/mlmail
RUN chmod +x /usr/local/bin/mlmail-entrypoint /usr/local/bin/mlmail

EXPOSE 587

ENTRYPOINT ["mlmail-entrypoint"]
