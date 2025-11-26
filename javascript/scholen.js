// scholen.js

document.addEventListener("DOMContentLoaded", () => {
    const confirmLinks = document.querySelectorAll(".js-confirm");

    confirmLinks.forEach(link => {
        link.addEventListener("click", event => {
            const message = link.dataset.confirm || "Weet je het zeker?";
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
