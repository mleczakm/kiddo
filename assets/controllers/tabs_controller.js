import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["tab", "tabpanel"];

    selectTab(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10) - 1; // Dopasowanie indeksu do tablicy

        // Deaktywuj wszystkie przyciski i ukryj wszystkie tabpanel
        this.tabTargets.forEach((tab) => {
            tab.setAttribute("aria-selected", "false");
            tab.dataset.state = "inactive";
        });

        this.tabpanelTargets.forEach((panel) => {
            panel.dataset.state = "inactive";
            panel.classList.add("hidden");
        });

        // Aktywuj wybrany przycisk i pokaż odpowiedni tabpanel
        event.currentTarget.setAttribute("aria-selected", "true");
        event.currentTarget.dataset.state = "active";

        const activePanel = this.tabpanelTargets[index];
        if (activePanel) {
            activePanel.dataset.state = "active";
            activePanel.classList.remove("hidden");
        }
    }
}