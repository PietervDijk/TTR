// klassen.js

document.addEventListener("DOMContentLoaded", () => {
    /* ============
       Confirm dialogs
    ============ */
    const confirmLinks = document.querySelectorAll(".js-confirm");
    confirmLinks.forEach(koppeling => {
        koppeling.addEventListener("click", gebeurtenis => {
            const bevestigingsbericht = koppeling.dataset.confirm || "Weet je zeker dat je dit wilt verwijderen?";
            if (!window.confirm(bevestigingsbericht)) {
                gebeurtenis.preventDefault();
            }
        });
    });

    /* ============
       Voorkeur toevoegen (nieuw klas-formulier)
    ============ */
    const addVoorkeurBtn = document.querySelector(".js-add-voorkeur");
    const voorkeurenWrapper = document.getElementById("voorkeurenWrapper");

    if (addVoorkeurBtn && voorkeurenWrapper) {
        addVoorkeurBtn.addEventListener("click", () => {
            const nieuwVeld = document.createElement("div");
            nieuwVeld.classList.add("mb-2", "d-flex", "gap-2");
            nieuwVeld.innerHTML = `
                <input type="text" name="voorkeuren[]" class="form-control klas-input" placeholder="Bijv: Elektrotechniek">
                <input type="number" name="max_studenten[]" class="form-control klas-input klas-max-input" placeholder="Bijv: 25" min="1">
            `;
            voorkeurenWrapper.appendChild(nieuwVeld);
        });
    }

    /* ============
       Extra voorkeur toevoegen (bewerk-formulier)
    ============ */
    const addNieuweVoorkeurBtn = document.querySelector(".js-add-nieuwe-voorkeur");
    const nieuweVoorkeurenWrapper = document.getElementById("nieuweVoorkeurenWrapper");

    if (addNieuweVoorkeurBtn && nieuweVoorkeurenWrapper) {
        addNieuweVoorkeurBtn.addEventListener("click", () => {
            const nieuwVeld = document.createElement("div");
            nieuwVeld.classList.add("mb-2", "d-flex", "gap-2");
            nieuwVeld.innerHTML = `
                <input type="text" name="nieuwe_voorkeuren[]" class="form-control klas-input" placeholder="Bijv: Elektrotechniek">
                <input type="number" name="nieuwe_voorkeuren_max[]" class="form-control klas-input klas-max-input" min="1" placeholder="Bijv: 25">
            `;
            nieuweVoorkeurenWrapper.appendChild(nieuwVeld);
        });
    }
});
