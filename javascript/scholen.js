// scholen.js: Bevestiging dialogs

document.addEventListener("DOMContentLoaded", () => {
    // Voeg bevestiging toe aan verwijder-links
    const confirmLinks = document.querySelectorAll(".js-confirm");

    confirmLinks.forEach(koppeling => {
        koppeling.addEventListener("click", gebeurtenis => {
            const bevestigingsbericht = koppeling.dataset.confirm || "Weet je het zeker?";
            if (!window.confirm(bevestigingsbericht)) {
                gebeurtenis.preventDefault();
            }
        });
    });
});
