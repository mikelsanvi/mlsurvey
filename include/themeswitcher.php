<?php
/*
 * Selector de tema para la barra de navegación.
 * El comportamiento está en js/theme.js; las paletas, en css/theme.css.
 */
$ml_accents = [
    'esmeralda' => ['label' => 'Esmeralda', 'swatch' => '#059669'],
    'indigo'    => ['label' => 'Índigo',    'swatch' => '#4f46e5'],
    'oceano'    => ['label' => 'Océano',    'swatch' => '#0284c7'],
    'violeta'   => ['label' => 'Violeta',   'swatch' => '#7c3aed'],
    'ambar'     => ['label' => 'Ámbar',     'swatch' => '#f59e0b'],
    'rosa'      => ['label' => 'Rosa',      'swatch' => '#e11d48'],
];

$ml_modes = [
    'auto'  => 'Auto',
    'light' => 'Claro',
    'dark'  => 'Oscuro',
];
?>
<div class="ml-theme">
    <button type="button" id="ml-theme-btn" class="ml-theme-btn"
            aria-haspopup="dialog" aria-expanded="false"
            aria-controls="ml-theme-panel" title="Apariencia">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
            <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
            <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
            <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
            <path d="M12 2a10 10 0 1 0 0 20 2 2 0 0 0 1.6-3.2 2 2 0 0 1 1.6-3.2H19a3 3 0 0 0 3-3 10 10 0 0 0-10-10z"/>
        </svg>
        <span class="sr-only">Apariencia</span>
    </button>

    <div class="ml-theme-panel" id="ml-theme-panel" role="dialog"
         aria-label="Apariencia" hidden>
        <p class="ml-theme-title" id="ml-theme-mode-label">Modo</p>
        <div class="ml-seg" role="radiogroup" aria-labelledby="ml-theme-mode-label">
            <?php foreach ($ml_modes as $ml_value => $ml_label) { ?>
            <button type="button" role="radio" aria-checked="false"
                    data-mode="<?= $ml_value; ?>"><?= $ml_label; ?></button>
            <?php } ?>
        </div>

        <p class="ml-theme-title" id="ml-theme-accent-label">Color</p>
        <div class="ml-swatches" role="radiogroup" aria-labelledby="ml-theme-accent-label">
            <?php foreach ($ml_accents as $ml_value => $ml_accent) { ?>
            <button type="button" role="radio" aria-checked="false"
                    data-accent="<?= $ml_value; ?>"
                    title="<?= $ml_accent['label']; ?>"
                    aria-label="<?= $ml_accent['label']; ?>"
                    style="--swatch: <?= $ml_accent['swatch']; ?>;"></button>
            <?php } ?>
        </div>
    </div>
</div>
