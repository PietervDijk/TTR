// scholen.js

document.addEventListener("DOMContentLoaded", () => {
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
