//Create Direct Chat JS
document.addEventListener("click", async function (e) {
    if (e.target.id !== "create-direct-btn") {
        return;
    }

    const userId = document.getElementById("direct-user-id").value;

    const response = await axios.post("/chat/direct", {
        user_id: userId,
    });

    await reloadConversationList();

    loadRoom(response.data.room_id);
});

//Group Chat JS
document.addEventListener("click", async function (e) {
    if (e.target.id !== "create-group-btn") {
        return;
    }

    const name = document.getElementById("group-name").value;

    const members = Array.from(
        document.getElementById("group-members").selectedOptions,
    ).map((option) => option.value); //members = [ "2", "4" ]

    const response = await axios.post("/chat/group", {
        name,
        members,
    });

    await reloadConversationList(); 

    loadRoom(response.data.room_id);
});

async function reloadConversationList() {
    const response = await axios.get("/chat"); //not reload bc Js stor it

    const html = new DOMParser().parseFromString(response.data, "text/html");

    const list = html.querySelector(".conversation-list");

    document.querySelector(".conversation-list").innerHTML = list.innerHTML;
}
