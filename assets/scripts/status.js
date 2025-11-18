async function loadBotStatus() {
    try {
        const response = await fetch("http://193.46.81.88/bot-status.json?cacheBust=" + Date.now());
        const data = await response.json();

        const el = document.getElementById("botStatus");
        if (!el) return;

        if (data.status === "online") {
            el.innerHTML = "🟢 <b>Bot ist online</b>";
            el.classList.add("status-online");
            el.classList.remove("status-offline");
        } else {
            el.innerHTML = "🔴 <b>Bot ist offline</b>";
            el.classList.add("status-offline");
            el.classList.remove("status-online");
        }

    } catch (e) {
        const el = document.getElementById("botStatus");
        if (el) {
            el.innerHTML = "🔴 <b>Bot-Status nicht erreichbar</b>";
            el.classList.add("status-offline");
        }
    }
}

setInterval(loadBotStatus, 15000);
loadBotStatus();
