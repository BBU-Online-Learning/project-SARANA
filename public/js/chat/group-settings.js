(() => {
    "use strict";

    const ROLE_LABELS = {
        super_admin: "Super administrator",
        admin: "Administrator",
        teacher: "Teacher",
        student: "Student",
    };
    const DISPLAY_LIMIT = 50;

    function roleGroup(role) {
        return ["super_admin", "admin", "manager"].includes(role) ? "admin" : role;
    }

    function filterPeople(people, query = "", role = "all") {
        const needle = String(query).trim().toLocaleLowerCase();

        return people.filter((person) => {
            const matchesText = !needle || person.name.toLocaleLowerCase().includes(needle);
            const matchesRole = role === "all" || roleGroup(person.role) === role;

            return matchesText && matchesRole;
        });
    }

    function filterMembers(searchTexts, query = "") {
        const needle = String(query).trim().toLocaleLowerCase();

        return searchTexts.map((text) => !needle || String(text).toLocaleLowerCase().includes(needle));
    }

    window.GroupSettingsUX = { filterPeople, filterMembers, roleGroup };

    const page = document.querySelector("[data-group-settings]");
    if (!page) {
        return;
    }

    const data = page.querySelector("[data-available-people]");
    let people = [];
    try {
        people = JSON.parse(data?.textContent || "[]");
    } catch (error) {
        console.error("Available group members could not be loaded", error);
    }

    const form = page.querySelector("[data-add-group-members]");
    const select = page.querySelector("[data-add-people-select]");
    const results = page.querySelector("[data-add-people-results]");
    const search = page.querySelector("[data-add-people-search]");
    const clear = page.querySelector("[data-clear-add-people]");
    const chips = page.querySelector("[data-add-people-chips]");
    const count = page.querySelector("[data-add-people-count]");
    const submit = page.querySelector("[data-add-people-submit]");
    const maxSelectable = Number(page.dataset.maxSelectable || 0);
    const selected = new Set(select ? [...select.selectedOptions].map((option) => option.value) : []);
    const byId = new Map(people.map((person) => [String(person.id), person]));
    let query = "";
    let role = "all";
    const avatarInput = page.querySelector("[data-group-avatar-input]");
    const avatarPreview = page.querySelector("[data-group-avatar-preview]");
    const initialAvatarPreview = avatarPreview?.innerHTML;
    let avatarPreviewUrl = null;

    function roleLabel(value) {
        return ROLE_LABELS[value] || "User";
    }

    function avatar(person) {
        const element = document.createElement("span");
        element.className = "create-chat-person-avatar";
        if (person.avatar) {
            const image = document.createElement("img");
            image.src = person.avatar;
            image.alt = "";
            image.loading = "lazy";
            element.appendChild(image);
        } else {
            element.textContent = person.initials;
        }

        return element;
    }

    function personRow(person) {
        const id = String(person.id);
        const isSelected = selected.has(id);
        const button = document.createElement("button");
        button.type = "button";
        button.className = "create-chat-user";
        button.classList.toggle("is-selected", isSelected);
        button.dataset.addPerson = id;
        button.setAttribute("aria-pressed", String(isSelected));
        button.setAttribute("aria-label", `${isSelected ? "Deselect" : "Select"} ${person.name}, ${roleLabel(person.role)}`);

        const copy = document.createElement("span");
        copy.className = "create-chat-person-copy";
        const name = document.createElement("strong");
        name.textContent = person.name;
        const personRole = document.createElement("span");
        personRole.textContent = roleLabel(person.role);
        copy.append(name, personRole);

        const mark = document.createElement("span");
        mark.className = "create-chat-selected-mark";
        mark.setAttribute("aria-hidden", "true");
        mark.textContent = "✓";
        button.append(avatar(person), copy, mark);

        return button;
    }

    function renderResults() {
        if (!results) {
            return;
        }
        results.replaceChildren();
        const matches = filterPeople(people, query, role);
        if (matches.length === 0) {
            const empty = document.createElement("div");
            empty.className = "create-chat-no-results";
            const icon = document.createElement("i");
            icon.className = "ti ti-user-search";
            icon.setAttribute("aria-hidden", "true");
            const message = document.createElement("p");
            message.textContent = query ? "No users found" : "No users available for this filter";
            empty.append(icon, message);
            results.appendChild(empty);

            return;
        }

        matches.slice(0, DISPLAY_LIMIT).forEach((person) => results.appendChild(personRow(person)));
        if (matches.length > DISPLAY_LIMIT) {
            const hint = document.createElement("p");
            hint.className = "create-chat-result-limit";
            hint.textContent = `${matches.length - DISPLAY_LIMIT} more results. Continue typing to narrow the list.`;
            results.appendChild(hint);
        }
    }

    function syncSelection() {
        if (!form) {
            return;
        }
        [...select.options].forEach((option) => { option.selected = selected.has(option.value); });
        chips.replaceChildren();
        selected.forEach((id) => {
            const person = byId.get(id);
            if (!person) {
                return;
            }
            const chip = document.createElement("button");
            chip.type = "button";
            chip.className = "create-chat-chip";
            chip.dataset.removePerson = id;
            chip.setAttribute("aria-label", `Remove ${person.name} from selection`);
            const name = document.createElement("span");
            name.textContent = person.name;
            const close = document.createElement("b");
            close.textContent = "×";
            close.setAttribute("aria-hidden", "true");
            chip.append(name, close);
            chips.appendChild(chip);
        });
        count.textContent = `${selected.size} selected`;
        submit.disabled = selected.size === 0;
        renderResults();
    }

    page.addEventListener("input", (event) => {
        if (event.target === search) {
            query = search.value;
            clear.hidden = query === "";
            renderResults();
        }
        if (event.target.matches("[data-member-search]")) {
            const cards = [...page.querySelectorAll("[data-member-card]")];
            const visible = filterMembers(cards.map((card) => card.dataset.searchText), event.target.value);
            cards.forEach((card, index) => { card.hidden = !visible[index]; });
            page.querySelector("[data-member-empty]").hidden = visible.some(Boolean);
        }
    });

    avatarInput?.addEventListener("change", () => {
        const file = avatarInput.files?.[0];
        if (!file) {
            return;
        }
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            window.AppNotifications?.warning("Choose a JPG, PNG or WebP group image.");
            avatarInput.value = "";
            avatarPreview.innerHTML = initialAvatarPreview;

            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            window.AppNotifications?.warning("The group image must be no larger than 2 MB.");
            avatarInput.value = "";
            avatarPreview.innerHTML = initialAvatarPreview;

            return;
        }
        if (avatarPreviewUrl) {
            URL.revokeObjectURL(avatarPreviewUrl);
        }
        avatarPreviewUrl = URL.createObjectURL(file);
        const image = document.createElement("img");
        image.src = avatarPreviewUrl;
        image.alt = "";
        avatarPreview.replaceChildren(image);
    });

    page.addEventListener("click", (event) => {
        const clearButton = event.target.closest("[data-clear-add-people]");
        if (clearButton) {
            query = "";
            search.value = "";
            clear.hidden = true;
            renderResults();
            search.focus();

            return;
        }

        const filter = event.target.closest("[data-add-role-filter]");
        if (filter) {
            role = filter.dataset.addRoleFilter;
            page.querySelectorAll("[data-add-role-filter]").forEach((button) => {
                const active = button === filter;
                button.classList.toggle("active", active);
                button.setAttribute("aria-pressed", String(active));
            });
            renderResults();

            return;
        }

        const person = event.target.closest("[data-add-person]");
        if (person) {
            const id = person.dataset.addPerson;
            if (selected.has(id)) {
                selected.delete(id);
            } else if (selected.size >= maxSelectable) {
                window.AppNotifications?.warning(`You can add ${maxSelectable} more ${maxSelectable === 1 ? "member" : "members"} to this group.`);
            } else {
                selected.add(id);
            }
            syncSelection();
            page.querySelector(`[data-add-person="${id}"]`)?.focus();

            return;
        }

        const remove = event.target.closest("[data-remove-person]");
        if (remove) {
            selected.delete(remove.dataset.removePerson);
            syncSelection();
            search.focus();
        }
    });

    syncSelection();

    window.addEventListener("beforeunload", () => {
        if (avatarPreviewUrl) {
            URL.revokeObjectURL(avatarPreviewUrl);
        }
    });
})();
