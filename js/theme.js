/* =====================================================================
   mlsurvey · selector de tema
   ---------------------------------------------------------------------
   Dos ejes independientes, persistidos en localStorage:
     · modo   -> auto | light | dark   (auto = sigue al sistema operativo)
     · acento -> nombre de la paleta de css/theme.css

   La aplicación del tema se hace antes de pintar con el script en línea
   de <head> (ver index.php). Este archivo sólo monta la interfaz.
   ===================================================================== */
(function () {
    "use strict";

    var STORE_MODE = "mlsurvey:theme-mode";
    var STORE_ACCENT = "mlsurvey:theme-accent";
    var DEFAULT_MODE = "auto";
    var DEFAULT_ACCENT = "esmeralda";
    var root = document.documentElement;

    /* localStorage puede lanzar (modo privado, cookies bloqueadas). */
    function read(key, fallback) {
        try {
            return window.localStorage.getItem(key) || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function write(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* Sin persistencia, pero la sesión actual sigue funcionando. */
        }
    }

    function applyMode(mode) {
        if (mode === "light" || mode === "dark") {
            root.setAttribute("data-theme", mode);
        } else {
            /* Sin atributo, manda prefers-color-scheme. */
            root.removeAttribute("data-theme");
        }
    }

    function applyAccent(accent) {
        root.setAttribute("data-accent", accent);
    }

    function init() {
        var panel = document.getElementById("ml-theme-panel");
        var trigger = document.getElementById("ml-theme-btn");
        if (!panel || !trigger) {
            return;
        }

        var mode = read(STORE_MODE, DEFAULT_MODE);
        var accent = read(STORE_ACCENT, DEFAULT_ACCENT);
        var modeButtons = panel.querySelectorAll("[data-mode]");
        var accentButtons = panel.querySelectorAll("[data-accent]");

        function syncChecked(buttons, attr, value) {
            Array.prototype.forEach.call(buttons, function (btn) {
                btn.setAttribute(
                    "aria-checked",
                    btn.getAttribute(attr) === value ? "true" : "false"
                );
            });
        }

        function refresh() {
            syncChecked(modeButtons, "data-mode", mode);
            syncChecked(accentButtons, "data-accent", accent);
        }

        Array.prototype.forEach.call(modeButtons, function (btn) {
            btn.addEventListener("click", function () {
                mode = btn.getAttribute("data-mode");
                applyMode(mode);
                write(STORE_MODE, mode);
                refresh();
            });
        });

        Array.prototype.forEach.call(accentButtons, function (btn) {
            btn.addEventListener("click", function () {
                accent = btn.getAttribute("data-accent");
                applyAccent(accent);
                write(STORE_ACCENT, accent);
                refresh();
            });
        });

        function open() {
            panel.hidden = false;
            trigger.setAttribute("aria-expanded", "true");
        }

        function close() {
            panel.hidden = true;
            trigger.setAttribute("aria-expanded", "false");
        }

        trigger.addEventListener("click", function (event) {
            event.stopPropagation();
            if (panel.hidden) {
                open();
            } else {
                close();
            }
        });

        /* Cerrar al pulsar fuera o con Escape. */
        document.addEventListener("click", function (event) {
            if (!panel.hidden && !panel.contains(event.target)) {
                close();
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && !panel.hidden) {
                close();
                trigger.focus();
            }
        });

        refresh();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
