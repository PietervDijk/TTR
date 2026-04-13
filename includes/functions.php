<?php

/**
 * Genereert een lijst van schooljaren rondom het huidige schooljaar.
 * Formaat: "2025 - 2026".
 *
 * Het schooljaar begint in augustus. Voorbeeld: in april 2026 is het
 * huidige schooljaar 2025 - 2026.
 *
 * @param int $jaarTerug   Aantal schooljaren terug (default 1)
 * @param int $jaarVooruit Aantal schooljaren vooruit (default 2)
 * @return string[]
 */
function get_schooljaren(int $jaarTerug = 1, int $jaarVooruit = 2): array
{
    $huidigJaar    = (int)date('Y');
    $huidigeMaand  = (int)date('n');
    $startJaarHuidig = ($huidigeMaand >= 8) ? $huidigJaar : $huidigJaar - 1;

    $schooljaren = [];
    for ($i = -$jaarTerug; $i <= $jaarVooruit; $i++) {
        $startJaar     = $startJaarHuidig + $i;
        $schooljaren[] = $startJaar . ' - ' . ($startJaar + 1);
    }

    return $schooljaren;
}

/**
 * Geeft het huidige schooljaar terug als string.
 * Formaat: "2025 - 2026".
 */
function get_huidig_schooljaar(): string
{
    $huidigJaar   = (int)date('Y');
    $huidigeMaand = (int)date('n');
    $startJaar    = ($huidigeMaand >= 8) ? $huidigJaar : $huidigJaar - 1;

    return $startJaar . ' - ' . ($startJaar + 1);
}
