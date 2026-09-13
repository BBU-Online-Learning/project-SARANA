(() => {
    "use strict";

    const ROLE_LABELS = {
        super_admin: "Super administrator",
        admin: "Administrator",
        teacher: "Teacher",
        student: "Student",
    };
    const DISPLAY_LIMIT = 50;
    const SUGGESTION_LIMIT = 6;
    const GROUP_MEMBER_LIMIT = 30;

    function roleGroup(role) {
        return ["super_admin", "admin", "manager"].includes(role) ? "admin" : role;
    }

    function filterPeople(people, query = "", role = "all") {
        const needle = String(query).trim().toLocaleLowerCase();

        return people.filter((person) => {
            const matchesName = !needle || person.name.toLocaleLowerCase().includes(needle);
            const matchesRole = role === "all" || roleGroup(person.role) === role;

            return matchesName && matchesRole;
        });
    }

    function validGroupName(name) {
        const length = String(name).trim().length;

        return length >= 3 && length <= 100;
    }

    function directPayload(userId) {
        return { user_id: String(userId || "") };
    }

    function groupPayload(name, memberIds) {
        return {
            name: String(name).trim(),
            members: [...memberIds].map(String),
        };
    }

    window.CreateChatUX = { filterPeople, validGroupName, directPayload, groupPayload, roleGroup };

    const modal = document.getElementById("createChatModal");
    if (!modal) {
        return;
    }

    let people = [];
    try {
        people = JSON.parse(document.getElementById("create-chat-users")?.textContent || "[]");
    } catch (error) {
        console.error("Create chat people could not be loaded", error);
    }

    const state = {
        directSelected: null,
        groupSelected: new Set(),
        query: { direct: "", group: "" },
        role: { direct: "all", group: "all" },
        groupStep: "details",
    };

    const byId = new Map(people.map((person) => [String(person.id), person]));
    const errorBox = document.getElementById("create-chat-error");
    const directSelect = document.getElementById("direct-user-id");
    const groupSelect = document.getElementById("group-members");
    const groupName = document.getElementById("group-name");
    const directButton = document.getElementById("create-direct-btn");
    const nextButton = document.getElementById("group-next-btn");
    const groupButton = document.getElementById("create-group-btn");

    function roleLabel(role) {
        return ROLE_LABELS[role] || "User";
    }

    function createAvatar(person) {
        const avatar = document.createElement("span");
        avatar.className = "create-chat-person-avatar";
        if (person.avatar) {
            const image = document.createElement("img");
            image.src = person.avatar;
            image.alt = "";
            image.loading = "lazy";
            avatar.appendChild(image);
        } else {
            avatar.textContent = person.initials;
        }

        const presence = document.createElement("span");
        presence.className = "presence-dot";
        presence.dataset.userId = person.id;
        presence.setAttribute("aria-hidden", "true");
        avatar.appendChild(presence);

        return avatar;
    }

    function createPersonRow(person, picker, interactive = true) {
        const row = document.createElement(interactive ? "button" : "div");
        if (interactive) {
            row.type = "button";
            row.dataset.selectPerson = picker;
            row.dataset.userId = person.id;
        }
        row.className = "create-chat-user";
        const selected = picker === "direct"
            ? state.directSelected === String(person.id)
            : state.groupSelected.has(String(person.id));
        row.classList.toggle("is-selected", selected);
        if (interactive) {
            row.setAttribute("aria-pressed", String(selected));
            row.setAttribute("aria-label", `${selected ? "Deselect" : "Select"} ${person.name}, ${roleLabel(person.role)}`);
        }

        const copy = document.createElement("span");
        copy.className = "create-chat-person-copy";
        const name = document.createElement("strong");
        name.textContent = person.name;
        const role = document.createElement("span");
        role.textContent = roleLabel(person.role);
        copy.append(name, role);
        row.append(createAvatar(person), copy);

        if (interactive) {
            const mark = document.createElement("span");
            mark.className = "create-chat-selected-mark";
            mark.setAttribute("aria-hidden", "true");
            mark.textContent = "✓";
            row.appendChild(mark);
        }

        return row;
    }

    function appendSection(container, title, sectionPeople, picker) {
        if (sectionPeople.length === 0) {
            return;
        }
        const heading = document.createElement("p");
        heading.className = "create-chat-section-title";
        heading.textContent = title;
        container.appendChild(heading);
        sectionPeople.forEach((person) => container.appendChild(createPersonRow(person, picker)));
    }

    function renderPicker(picker) {
        const container = document.querySelector(`[data-people-results="${picker}"]`);
        if (!container) {
            return;
        }
        container.replaceChildren();
        const matches = filterPeople(people, state.query[picker], state.role[picker]);

        if (matches.length === 0) {
            const empty = document.createElement("div");
            empty.className = "create-chat-no-results";
            const icon = document.createElement("i");
            icon.className = "ti ti-user-search";
            icon.setAttribute("aria-hidden", "true");
            const message = document.createElement("p");
            message.textContent = state.query[picker] ? "No users found" : "No users available for this filter";
            empty.append(icon, message);
            container.appendChild(empty);

            return;
        }

        if (!state.query[picker] && state.role[picker] === "all") {
            const recent = matches.filter((person) => person.recent).slice(0, SUGGESTION_LIMIT);
            const suggestions = matches.filter((person) => !person.recent).slice(0, SUGGESTION_LIMIT);
            appendSection(container, "Recent contacts", recent, picker);
            appendSection(container, recent.length ? "Suggestions" : "People", suggestions, picker);
        } else {
            appendSection(container, "Results", matches.slice(0, DISPLAY_LIMIT), picker);
            if (matches.length > DISPLAY_LIMIT) {
                const hint = document.createElement("p");
                hint.className = "create-chat-result-limit";
                hint.textContent = `${matches.length - DISPLAY_LIMIT} more results. Continue typing to narrow the list.`;
                container.appendChild(hint);
            }
        }

        if (typeof window.updatePresenceUI === "function") {
            window.updatePresenceUI();
        }
    }

    function syncDirectSelection() {
        directSelect.value = state.directSelected || "";
        directButton.disabled = !state.directSelected;
        renderPicker("direct");
    }

    function syncGroupSelection() {
        [...groupSelect.options].forEach((option) => {
            option.selected = state.groupSelected.has(option.value);
        });
        const chips = document.getElementById("group-selected-chips");
        chips.replaceChildren();
        state.groupSelected.forEach((id) => {
            const person = byId.get(id);
            if (!person) {
                return;
            }
            const chip = document.createElement("button");
            chip.type = "button";
            chip.className = "create-chat-chip";
            chip.dataset.removeGroupPerson = id;
            chip.setAttribute("aria-label", `Remove ${person.name} from group`);
            const name = document.createElement("span");
            name.textContent = person.name;
            const close = document.createElement("b");
            close.textContent = "×";
            close.setAttribute("aria-hidden", "true");
            chip.append(name, close);
            chips.appendChild(chip);
        });
        document.getElementById("group-selection-count").textContent = `${state.groupSelected.size} selected`;
        nextButton.disabled = !validGroupName(groupName.value);
        renderPicker("group");
    }

    function showGroupStep(step, moveFocus = true) {
        state.groupStep = step;
        modal.querySelector('[data-group-step="details"]').hidden = step !== "details";
        modal.querySelector('[data-group-step="review"]').hidden = step !== "review";
        if (step === "review") {
            document.getElementById("group-review-name").textContent = groupName.value.trim();
            document.getElementById("group-review-count").textContent = `(${state.groupSelected.size})`;
            const review = document.getElementById("group-review-members");
            review.replaceChildren();
            state.groupSelected.forEach((id) => {
                const person = byId.get(id);
                if (person) {
                    review.appendChild(createPersonRow(person, "group", false));
                }
            });
            document.getElementById("group-review-empty").hidden = state.groupSelected.size !== 0;
            if (moveFocus) {
                groupButton.focus();
            }
        } else if (moveFocus) {
            nextButton.focus();
        }
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = "";
    }

    function reset() {
        state.directSelected = null;
        state.groupSelected.clear();
        state.query = { direct: "", group: "" };
        state.role = { direct: "all", group: "all" };
        groupName.value = "";
        modal.querySelectorAll("[data-people-search]").forEach((input) => { input.value = ""; });
        modal.querySelectorAll("[data-clear-people-search]").forEach((button) => { button.hidden = true; });
        modal.querySelectorAll("[data-role-filter]").forEach((button) => {
            const active = button.dataset.roleFilter === "all";
            button.classList.toggle("active", active);
            button.setAttribute("aria-pressed", String(active));
        });
        directButton.textContent = "Start chat";
        groupButton.textContent = "Create group";
        directButton.disabled = true;
        groupButton.disabled = false;
        clearError();
        showGroupStep("details", false);
        syncDirectSelection();
        syncGroupSelection();
    }

    async function reloadConversationList() {
        const response = await axios.get("/chat");
        const html = new DOMParser().parseFromString(response.data, "text/html");
        const list = html.querySelector(".conversation-list");
        if (!list) {
            throw new Error("Conversation list unavailable");
        }
        document.querySelector(".conversation-list").innerHTML = list.innerHTML;
    }

    async function createConversation(type) {
        const button = type === "direct" ? directButton : groupButton;
        if (button.disabled) {
            return;
        }
        clearError();
        const label = button.textContent;
        let created = false;
        directButton.disabled = true;
        groupButton.disabled = true;
        button.textContent = type === "direct" ? "Starting..." : "Creating...";

        try {
            const payload = type === "direct"
                ? directPayload(state.directSelected)
                : groupPayload(groupName.value, state.groupSelected);
            const response = await axios.post(type === "direct" ? "/chat/direct" : "/chat/group", payload);
            if (!response.data.room_id) {
                throw new Error("Session changed");
            }
            created = true;
            bootstrap.Modal.getInstance(modal)?.hide();
            await reloadConversationList();
            await loadRoom(response.data.room_id);
        } catch (error) {
            const messages = error.response?.data?.errors;
            const text = created
                ? "Conversation created, but the list could not refresh. Reload Chats before trying again."
                : messages
                    ? Object.values(messages).flat().join(" ")
                    : "Could not create the conversation. Check your connection or reload to sign in again.";
            if (created) {
                showChatLoadStatus(text, true);
            } else {
                if (messages && type === "group") {
                    showGroupStep("details", false);
                }
                errorBox.textContent = text;
                errorBox.hidden = false;
                errorBox.scrollIntoView({ block: "nearest" });
                errorBox.focus();
                if (!messages) {
                    window.AppNotifications?.fromAxios(error, text);
                }
            }
        } finally {
            button.textContent = label;
            if (!created) {
                directButton.disabled = !state.directSelected;
                groupButton.disabled = false;
            }
        }
    }

    modal.addEventListener("input", (event) => {
        const picker = event.target.dataset.peopleSearch;
        if (picker) {
            state.query[picker] = event.target.value;
            modal.querySelector(`[data-clear-people-search="${picker}"]`).hidden = !event.target.value;
            renderPicker(picker);
        }
        if (event.target === groupName) {
            nextButton.disabled = !validGroupName(groupName.value);
        }
    });

    modal.addEventListener("click", (event) => {
        const clear = event.target.closest("[data-clear-people-search]");
        if (clear) {
            const picker = clear.dataset.clearPeopleSearch;
            const search = modal.querySelector(`[data-people-search="${picker}"]`);
            search.value = "";
            state.query[picker] = "";
            clear.hidden = true;
            renderPicker(picker);
            search.focus();

            return;
        }

        const filter = event.target.closest("[data-role-filter]");
        if (filter) {
            const picker = filter.dataset.picker;
            state.role[picker] = filter.dataset.roleFilter;
            modal.querySelectorAll(`[data-role-filter][data-picker="${picker}"]`).forEach((button) => {
                const active = button === filter;
                button.classList.toggle("active", active);
                button.setAttribute("aria-pressed", String(active));
            });
            renderPicker(picker);

            return;
        }

        const personButton = event.target.closest("[data-select-person]");
        if (personButton) {
            const id = personButton.dataset.userId;
            if (personButton.dataset.selectPerson === "direct") {
                state.directSelected = id;
                syncDirectSelection();
                directButton.focus();
            } else {
                if (state.groupSelected.has(id)) {
                    state.groupSelected.delete(id);
                } else if (state.groupSelected.size >= GROUP_MEMBER_LIMIT) {
                    window.AppNotifications?.warning(`Groups can include up to ${GROUP_MEMBER_LIMIT} selected members.`);
                } else {
                    state.groupSelected.add(id);
                }
                syncGroupSelection();
                modal.querySelector(`[data-select-person="group"][data-user-id="${id}"]`)?.focus();
            }

            return;
        }

        const remove = event.target.closest("[data-remove-group-person]");
        if (remove) {
            state.groupSelected.delete(remove.dataset.removeGroupPerson);
            syncGroupSelection();

            return;
        }

        if (event.target.closest("#group-next-btn") && validGroupName(groupName.value)) {
            showGroupStep("review");
        } else if (event.target.closest("#group-back-btn, #group-review-back-btn")) {
            showGroupStep("details");
        } else if (event.target.closest("#create-direct-btn")) {
            createConversation("direct");
        } else if (event.target.closest("#create-group-btn")) {
            createConversation("group");
        }
    });

    modal.addEventListener("shown.bs.modal", () => {
        modal.querySelector('[data-people-search="direct"]')?.focus();
    });
    modal.addEventListener("hidden.bs.modal", reset);
    modal.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener("shown.bs.tab", () => {
            const picker = tab.id === "group-chat-tab" ? "group" : "direct";
            modal.querySelector(`[data-people-search="${picker}"]`)?.focus();
        });
    });

    reset();
})();
