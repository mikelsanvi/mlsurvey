<?php
/*
 * Aplica el tema guardado ANTES del primer pintado.
 * Si esto se hiciera desde js/theme.js (al final del <body>) se vería
 * un destello del tema claro antes de cambiar al oscuro.
 */
?>
<script>
(function () {
    try {
        var m = localStorage.getItem("mlsurvey:theme-mode");
        if (m === "light" || m === "dark") {
            document.documentElement.setAttribute("data-theme", m);
        }
        var a = localStorage.getItem("mlsurvey:theme-accent");
        document.documentElement.setAttribute("data-accent", a || "esmeralda");
    } catch (e) {
        document.documentElement.setAttribute("data-accent", "esmeralda");
    }
})();
</script>
