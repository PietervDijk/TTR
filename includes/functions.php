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

/**
 * Controleert of een schooljaar geldig is.
 * Geldig als het in de dynamische lijst voorkomt of het formaat
 * "YYYY - YYYY" heeft met opvolgende jaren.
 */
function is_geldig_schooljaar(string $schooljaar, int $jaarTerug = 2, int $jaarVooruit = 3): bool
{
    $schooljaar = preg_replace('/\s+/', ' ', trim($schooljaar));
    if ($schooljaar === '') {
        return false;
    }

    if (in_array($schooljaar, get_schooljaren($jaarTerug, $jaarVooruit), true)) {
        return true;
    }

    if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $schooljaar, $matches)) {
        return false;
    }

    return ((int)$matches[2] === ((int)$matches[1] + 1));
}
