// klassen.js

document.addEventListener("DOMContentLoaded", () => {
    /* ============
       Confirm dialogs
    ============ */
    const confirmLinks = document.querySelectorAll(".js-confirm");
    confirmLinks.forEach(link => {
        link.addEventListener("click", event => {
            const message = link.dataset.confirm || "Weet je zeker dat je dit wilt verwijderen?";
            if (!window.confirm(message)) {
                event.preventDefault();
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
            const div = document.createElement("div");
            div.classList.add("mb-2", "d-flex", "gap-2");
            div.innerHTML = `
                <input type="text" name="voorkeuren[]" class="form-control klas-input" placeholder="Voorkeur naam">
                <input type="number" name="max_studenten[]" class="form-control klas-input klas-max-input" placeholder="Max leerlingen" min="1">
            `;
            voorkeurenWrapper.appendChild(div);
        });
    }

    /* ============
       Extra voorkeur toevoegen (bewerk-formulier)
    ============ */
    const addNieuweVoorkeurBtn = document.querySelector(".js-add-nieuwe-voorkeur");
    const nieuweVoorkeurenWrapper = document.getElementById("nieuweVoorkeurenWrapper");

    if (addNieuweVoorkeurBtn && nieuweVoorkeurenWrapper) {
        addNieuweVoorkeurBtn.addEventListener("click", () => {
            const div = document.createElement("div");
            div.classList.add("mb-2", "d-flex", "gap-2");
            div.innerHTML = `
                <input type="text" name="nieuwe_voorkeuren[]" class="form-control klas-input" placeholder="Naam">
                <input type="number" name="nieuwe_voorkeuren_max[]" class="form-control klas-input klas-max-input" min="1" placeholder="Max leerlingen">
            `;
            nieuweVoorkeurenWrapper.appendChild(div);
        });
    }
});
