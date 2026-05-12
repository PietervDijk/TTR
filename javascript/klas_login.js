document.addEventListener("DOMContentLoaded", () => {
    // Zoekers voor school- en klasselectie
    const scholenKeuze = document.getElementById("school_id");
    const klasKeuze = document.getElementById("klas_id");

    // Controleer of beide dropdowns aanwezig zijn
    if (!scholenKeuze || !klasKeuze) {
        return;
    }

    // Haal placeholder-optie en alle klasopties op
    const tijdelijkeOptie = klasKeuze.querySelector('option[value=""]');
    const klasOpties = Array.from(klasKeuze.querySelectorAll("option[data-school-id]"));

    /**
     * Werkfunctie: bijwerk klasopties op basis van geselecteerde school
     * Verbergt klassen die niet tot de school behoren
     */
    const werkNieuweKlasOpties = () => {
        const geselecteerdeSchoolId = scholenKeuze.value;
        let heeftZichtbareKlassen = false;

        // Loop door alle klasopties en toon/verberg ze afhankelijk van school
        klasOpties.forEach(optie => {
            const isZichtbaar = geselecteerdeSchoolId !== "" && optie.dataset.schoolId === geselecteerdeSchoolId;
            optie.hidden = !isZichtbaar;
            optie.disabled = !isZichtbaar;

            if (isZichtbaar) {
                heeftZichtbareKlassen = true;
            }
        });

        // Werk placeholder-tekst bij en controleer beschikbaarheid
        if (tijdelijkeOptie) {
            tijdelijkeOptie.textContent = geselecteerdeSchoolId === ""
                ? "Kies eerst een school"
                : heeftZichtbareKlassen
                    ? "-- Kies klas --"
                    : "Geen klassen voor deze school";
            tijdelijkeOptie.hidden = false;
            tijdelijkeOptie.disabled = true;
            tijdelijkeOptie.selected = klasKeuze.value === "";
        }

        // Schakel klasdropdown uit als geen school is gekozen of geen klassen beschikbaar zijn
        klasKeuze.disabled = geselecteerdeSchoolId === "" || !heeftZichtbareKlassen;

        if (klasKeuze.disabled) {
            klasKeuze.value = "";
        } else {
            // Reset keuze als geselecteerde optie nu verborgen is
            const geselecteerdeOptie = klasKeuze.selectedOptions[0];
            if (!geselecteerdeOptie || geselecteerdeOptie.hidden) {
                klasKeuze.value = "";
            }
        }
    };

    // Luister naar schoolselectie en werk klasopties bij
    scholenKeuze.addEventListener("change", () => {
        klasKeuze.value = "";
        werkNieuweKlasOpties();
    });

    // Voer eerste keer uit bij laden pagina
    werkNieuweKlasOpties();
});