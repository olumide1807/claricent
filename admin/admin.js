/**
 * CLARICENT ADMIN — admin.js  (Auto-Rebuild Edition)
 * Saves to MySQL via api.php, then triggers a live rebuild
 * of projects.html automatically. No copy-paste needed.
 */

"use strict";

/* ============================================================
   AUTH
   ============================================================ */
async function doLogin() {
    const u   = document.getElementById("loginUser").value.trim();
    const p   = document.getElementById("loginPass").value;
    const err = document.getElementById("loginError");
    try {
        const res = await api("login", { username: u, password: p });
        if (res.success) {
            document.getElementById("loginOverlay").style.display = "none";
            document.getElementById("adminShell").style.display  = "flex";
            loadProjects();
        } else {
            err.textContent = "Incorrect credentials. Try again.";
            err.classList.add("visible");
            setTimeout(() => err.classList.remove("visible"), 3000);
        }
    } catch (e) {
        err.textContent = "Server error — is api.php reachable?";
        err.classList.add("visible");
    }
}

async function doLogout() {
    await api("logout");
    location.reload();
}

function togglePw() {
    const pw   = document.getElementById("loginPass");
    const icon = document.getElementById("eyeIcon");
    pw.type    = pw.type === "password" ? "text" : "password";
    icon.className = pw.type === "text" ? "fa fa-eye-slash" : "fa fa-eye";
}

document.addEventListener("keydown", e => {
    if (e.key === "Enter" && document.getElementById("loginOverlay").style.display !== "none") doLogin();
});

window.addEventListener("DOMContentLoaded", async () => {
    try {
        const res = await api("check_auth");
        if (res.authenticated) {
            document.getElementById("loginOverlay").style.display = "none";
            document.getElementById("adminShell").style.display  = "flex";
            loadProjects();
        }
    } catch { /* show login */ }

    // Drag & drop
    const zone = document.getElementById("uploadZone");
    zone.addEventListener("dragover",  e => { e.preventDefault(); zone.classList.add("drag-over"); });
    zone.addEventListener("dragleave", () => zone.classList.remove("drag-over"));
    zone.addEventListener("drop", e => {
        e.preventDefault(); zone.classList.remove("drag-over");
        const f = e.dataTransfer.files[0];
        if (f && f.type.startsWith("image/")) previewImageFile(f);
    });
});

/* ============================================================
   API WRAPPER
   ============================================================ */
async function api(action, data = {}) {
    const body = new URLSearchParams({ action, ...data });
    const res  = await fetch("api.php", {
        method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString()
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

async function apiUpload(file, projectId) {
    const fd = new FormData();
    fd.append("action", "upload_image");
    fd.append("image",  file);
    fd.append("projectId", projectId);
    const res = await fetch("api.php", { method: "POST", credentials: "same-origin", body: fd });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

/* ============================================================
   AUTO-REBUILD projects.html
   Called after every save or delete operation.
   ============================================================ */
async function rebuildPage() {
    try {
        const res = await api("rebuild_projects_page");
        if (res.success) {
            showAlert(`✓ projects.html updated live — ${res.project_count} project${res.project_count !== 1 ? "s" : ""} on the page.`, "success");
        } else {
            showAlert("Page rebuild failed: " + (res.error || "unknown error"), "error");
        }
    } catch (e) {
        showAlert("Could not rebuild page: " + e.message, "error");
    }
}

/* ============================================================
   PROJECT TABLE
   ============================================================ */
let allProjects = [];

async function loadProjects() {
    showTableLoader();
    try {
        const res = await api("get_projects");
        allProjects = res.projects || [];
        renderTable(allProjects);
        updateCount(allProjects.length);
    } catch (e) {
        showAlert("Could not load projects: " + e.message, "error");
        renderTable([]);
    }
}

function showTableLoader() {
    document.getElementById("projectsTableBody").innerHTML = `
        <tr class="empty-row"><td colspan="7">
            <div class="empty-state"><i class="fa fa-spinner fa-spin"></i><p>Loading projects…</p></div>
        </td></tr>`;
}

function renderTable(projects) {
    const tbody = document.getElementById("projectsTableBody");
    if (!projects.length) {
        tbody.innerHTML = `
            <tr class="empty-row"><td colspan="7">
                <div class="empty-state">
                    <i class="fa fa-hard-hat"></i>
                    <p>No projects yet. Click <strong>Add Project</strong> to get started.</p>
                </div>
            </td></tr>`;
        return;
    }
    tbody.innerHTML = projects.map(p => {
        const thumb = p.image_path
            ? `<img class="project-thumb" src="../${escHtml(p.image_path)}" alt="${escHtml(p.name)}" loading="lazy">`
            : `<div class="thumb-placeholder"><i class="fa fa-image"></i></div>`;
        const statusClass = `status-${p.status || "upcoming"}`;
        const dot = { completed:"✓", ongoing:"●", upcoming:"◌" }[p.status] || "◌";
        const detailLink = `../project-detail.php?slug=${escHtml(p.slug)}`;
        return `
        <tr data-id="${p.id}">
            <td>${thumb}</td>
            <td>
                <div class="proj-name">${escHtml(p.name)}</div>
                ${p.slug ? `<div class="proj-location"><i class="fa fa-link"></i> <a href="${detailLink}" target="_blank" style="color:var(--accent);font-size:11px;">project-detail.php?slug=${escHtml(p.slug)}</a></div>` : ""}
            </td>
            <td><span class="cat-badge">${escHtml(p.category || "—")}</span></td>
            <td><span class="status-badge ${statusClass}">${dot} ${escHtml(p.status || "—")}</span></td>
            <td>${escHtml(p.location || "—")}</td>
            <td>${escHtml(String(p.year || "—"))}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" title="Edit"   onclick="editProject('${p.id}')"><i class="fa fa-pen"></i></button>
                    <button class="btn-icon" title="View"   onclick="window.open('../project-detail.php?slug=${escHtml(p.slug)}','_blank')"><i class="fa fa-eye"></i></button>
                    <button class="btn-icon delete" title="Delete" onclick="openDelete('${p.id}')"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join("");
}

function updateCount(n) {
    document.getElementById("projectCount").textContent = n;
}

function filterProjects() {
    const q      = document.getElementById("searchProjects").value.toLowerCase();
    const cat    = document.getElementById("categoryFilter").value;
    const status = document.getElementById("statusFilter").value;
    const filtered = allProjects.filter(p =>
        (!q      || p.name.toLowerCase().includes(q) || (p.location || "").toLowerCase().includes(q)) &&
        (!cat    || p.category === cat) &&
        (!status || p.status   === status)
    );
    renderTable(filtered);
}

/* ============================================================
   ADD / EDIT MODAL
   ============================================================ */
let editingId        = null;
let pendingImageFile = null;

function openModal(id = null) {
    editingId        = id;
    pendingImageFile = null;
    document.getElementById("projectForm").reset();
    document.getElementById("uploadPlaceholder").style.display = "block";
    document.getElementById("imagePreview").style.display      = "none";
    document.getElementById("imagePreview").src                = "";
    document.getElementById("imageFile").value                 = "";
    document.getElementById("projImagePath").value             = "";

    if (id) {
        const p = allProjects.find(x => x.id == id);
        if (!p) return;
        document.getElementById("modalTitle").textContent  = "Edit Project";
        document.getElementById("projectId").value         = p.id;
        document.getElementById("projName").value          = p.name;
        document.getElementById("projCategory").value      = p.category || "";
        document.getElementById("projStatus").value        = p.status   || "";
        document.getElementById("projYear").value          = p.year     || "";
        document.getElementById("projLocation").value      = p.location || "";
        document.getElementById("projDesc").value          = p.description || "";
        document.getElementById("projImagePath").value     = p.image_path || "";
        if (p.image_path) showImagePreview(`../${p.image_path}`);
        // Show the auto-generated slug
        const slugDisplay = document.getElementById("slugDisplay");
        if (slugDisplay) slugDisplay.textContent = p.slug || "";
    } else {
        document.getElementById("modalTitle").textContent = "Add New Project";
        document.getElementById("projectId").value        = "";
        const slugDisplay = document.getElementById("slugDisplay");
        if (slugDisplay) slugDisplay.textContent = "";
    }

    document.getElementById("modalOverlay").classList.add("open");
    document.getElementById("projName").focus();
}

function closeModal() {
    document.getElementById("modalOverlay").classList.remove("open");
    editingId = null;
}
function closeModalOutside(e) {
    if (e.target === document.getElementById("modalOverlay")) closeModal();
}
function editProject(id) { openModal(id); }

// Live slug preview as user types the project name
document.addEventListener("input", e => {
    if (e.target.id === "projName") {
        const slugDisplay = document.getElementById("slugDisplay");
        if (slugDisplay) {
            const slug = e.target.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, "")
                .replace(/[\s-]+/g, "-")
                .replace(/^-|-$/g, "");
            slugDisplay.textContent = slug ? `project-detail.php?slug=${slug}` : "";
        }
    }
});

async function saveProject(e) {
    e.preventDefault();
    const name        = document.getElementById("projName").value.trim();
    const category    = document.getElementById("projCategory").value;
    const status      = document.getElementById("projStatus").value;
    const year        = document.getElementById("projYear").value;
    const location    = document.getElementById("projLocation").value.trim();
    const description = document.getElementById("projDesc").value.trim();
    let   image_path  = document.getElementById("projImagePath").value.trim();

    if (!name || !category || !status || !description) {
        showAlert("Please fill in all required fields.", "error"); return;
    }

    const saveBtn = document.querySelector("#projectForm .btn-primary");
    saveBtn.disabled = true;
    saveBtn.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Saving…`;

    try {
        // 1. Save project text data
        const projRes = await api(editingId ? "update_project" : "create_project", {
            id: editingId || "", name, category, status, year, location, description,
            image_path: pendingImageFile ? (image_path || "") : image_path
        });
        if (!projRes.success) throw new Error(projRes.error || "Save failed");
        const savedId = projRes.id;

        // 2. Upload image if a new file was chosen
        if (pendingImageFile) {
            const upRes = await apiUpload(pendingImageFile, savedId);
            if (!upRes.success) {
                showAlert(`Saved but image upload failed: ${upRes.error || ""}`, "error");
            }
        }

        // 3. Rebuild projects.html automatically
        closeModal();
        await loadProjects();
        await rebuildPage();

    } catch (err) {
        showAlert("Error: " + err.message, "error");
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = `<i class="fa fa-save"></i> Save Project`;
    }
}

/* ============================================================
   IMAGE HANDLING
   ============================================================ */
function handleImageSelect(e) {
    const f = e.target.files[0]; if (f) previewImageFile(f);
}

function previewImageFile(file) {
    if (!file.type.startsWith("image/")) { showAlert("Please select a valid image.", "error"); return; }
    if (file.size > 5 * 1024 * 1024)    { showAlert("Image must be under 5MB.", "error");    return; }
    pendingImageFile = file;
    const reader  = new FileReader();
    reader.onload = ev => {
        showImagePreview(ev.target.result);
        if (!document.getElementById("projImagePath").value) {
            document.getElementById("projImagePath").value = `images/${file.name}`;
        }
    };
    reader.readAsDataURL(file);
}

function showImagePreview(src) {
    document.getElementById("uploadPlaceholder").style.display = "none";
    const img = document.getElementById("imagePreview");
    img.src = src; img.style.display = "block";
}

/* ============================================================
   DELETE
   ============================================================ */
let deleteTargetId = null;

function openDelete(id) {
    deleteTargetId = id;
    const p = allProjects.find(x => x.id == id);
    document.getElementById("deleteProjectName").textContent = p ? p.name : "this project";
    document.getElementById("deleteOverlay").classList.add("open");
}
function closeDelete() {
    document.getElementById("deleteOverlay").classList.remove("open");
    deleteTargetId = null;
}
function closeDeleteOutside(e) {
    if (e.target === document.getElementById("deleteOverlay")) closeDelete();
}

async function confirmDelete() {
    if (!deleteTargetId) return;
    const btn = document.querySelector("#deleteOverlay .btn-danger");
    btn.disabled = true;
    btn.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Deleting…`;
    try {
        const res = await api("delete_project", { id: deleteTargetId });
        if (!res.success) throw new Error(res.error || "Delete failed");
        closeDelete();
        await loadProjects();
        await rebuildPage();           // ← auto-update projects.html
    } catch (err) {
        showAlert("Error: " + err.message, "error");
        btn.disabled = false;
        btn.innerHTML = `<i class="fa fa-trash"></i> Delete`;
    }
}

/* ============================================================
   DIAGNOSE
   ============================================================ */
async function runDiagnose() {
    document.getElementById("diagnoseOverlay").classList.add("open");
    document.getElementById("diagnoseBody").innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:20px 0"><i class="fa fa-spinner fa-spin"></i> Running checks…</p>`;

    try {
        const res = await api("diagnose");
        if (!res.success) {
            document.getElementById("diagnoseBody").innerHTML = `<p style="color:var(--danger)">${escHtml(res.error || "Diagnose failed")}</p>`;
            return;
        }

        const c = res.checks;
        const row = (label, ok, note = "") => {
            const icon  = ok ? `<i class="fa fa-check-circle" style="color:var(--success)"></i>` : `<i class="fa fa-times-circle" style="color:var(--danger)"></i>`;
            const color = ok ? "var(--success)" : "var(--danger)";
            return `<div class="diag-row">
                <div>${icon} <span style="color:${color};font-weight:600">${escHtml(label)}</span></div>
                ${note ? `<div class="diag-note">${escHtml(note)}</div>` : ""}
            </div>`;
        };

        let html = `<div class="diag-list">`;
        html += `<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Projects</div>`;
        html += row("projects.html exists",       c.projects_html_exists,          c.projects_file_path || "");
        html += row("projects.html is writable",  c.projects_html_writable,        !c.projects_html_writable ? "Run: chmod 664 projects.html" : "");
        html += row("Projects start marker",      c.projects_html_has_start_marker,!c.projects_html_has_start_marker ? "Upload the NEW projects.html" : "");
        html += row("Projects end marker",        c.projects_html_has_end_marker,  "");
        html += `<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;color:var(--text-muted);text-transform:uppercase;margin:12px 0 4px">Blog</div>`;
        html += row("blog.html exists",           c.blog_html_exists,              c.blog_file_path || "");
        html += row("blog.html is writable",      c.blog_html_writable,            !c.blog_html_writable ? "Run: chmod 664 blog.html" : "");
        html += row("Blog start marker",          c.blog_html_has_start_marker,    !c.blog_html_has_start_marker ? "Upload the NEW blog.html" : "");
        html += row("Blog end marker",            c.blog_html_has_end_marker,      "");
        html += `<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;color:var(--text-muted);text-transform:uppercase;margin:12px 0 4px">Server</div>`;
        html += row("images/ folder exists",      c.images_dir_exists,             c.images_dir_path || "");
        html += row("images/ folder is writable", c.images_dir_writable,           !c.images_dir_writable ? "Run: chmod 755 images/" : "");
        html += row("Database connected",         c.db_connected,                  c.db_error || (c.db_connected ? `${c.db_project_count} project(s) in DB` : ""));
        html += `<div class="diag-row" style="margin-top:8px;padding-top:12px;border-top:1px solid var(--border)">
            <span style="color:var(--text-muted);font-size:12px">PHP ${escHtml(c.php_version)}</span>
        </div>`;
        html += `</div>`;

        const allOk = c.projects_html_exists && c.projects_html_writable &&
                      c.projects_html_has_start_marker && c.projects_html_has_end_marker &&
                      c.blog_html_exists && c.blog_html_writable &&
                      c.blog_html_has_start_marker && c.blog_html_has_end_marker &&
                      c.images_dir_exists && c.images_dir_writable && c.db_connected;

        html += allOk
            ? `<p class="diag-status ok"><i class="fa fa-check-circle"></i> Everything looks good! Auto-rebuild is ready.</p>`
            : `<p class="diag-status fail"><i class="fa fa-triangle-exclamation"></i> Fix the red items above, then try again.</p>`;

        document.getElementById("diagnoseBody").innerHTML = html;
    } catch (e) {
        document.getElementById("diagnoseBody").innerHTML = `<p style="color:var(--danger)">Error: ${escHtml(e.message)}</p>`;
    }
}

/* ============================================================
   ALERTS
   ============================================================ */
let alertTimeout;
function showAlert(msg, type = "info") {
    const zone  = document.getElementById("alertZone");
    const icons = { success:"fa-check-circle", error:"fa-times-circle", info:"fa-info-circle" };
    zone.innerHTML = `<div class="alert alert-${type}"><i class="fa ${icons[type]}"></i><span>${escHtml(msg)}</span></div>`;
    clearTimeout(alertTimeout);
    alertTimeout = setTimeout(() => { zone.innerHTML = ""; }, 6000);
}

/* ============================================================
   UTILS
   ============================================================ */
function escHtml(str) {
    return String(str)
        .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;").replace(/'/g,"&#39;");
}

/* ============================================================
   SECTION SWITCHER (Projects ↔ Blog)
   ============================================================ */
function switchSection(section, el) {
    // Toggle nav active state
    document.querySelectorAll('.nav-item[data-section]').forEach(n => n.classList.remove('active'));
    if (el) el.classList.add('active');

    // Toggle sections
    const projectsSection = document.querySelector('.content-card');
    const projectsTopbar  = document.querySelector('.topbar');
    const projectsAlert   = document.getElementById('alertZone');
    const blogSection     = document.getElementById('blogSection');

    // Wrap project elements in a named section for easy toggling
    const projectSection = document.getElementById('projectsSection');

    if (section === 'blog') {
        if (projectSection) projectSection.style.display = 'none';
        blogSection.style.display = 'block';
        loadPosts();
    } else {
        if (projectSection) projectSection.style.display = 'block';
        blogSection.style.display = 'none';
    }
}

/* ============================================================
   BLOG — TABLE
   ============================================================ */
let allPosts = [];

async function loadPosts() {
    document.getElementById("postsTableBody").innerHTML = `
        <tr class="empty-row"><td colspan="7">
            <div class="empty-state"><i class="fa fa-spinner fa-spin"></i><p>Loading posts…</p></div>
        </td></tr>`;
    try {
        const res = await api("get_posts");
        allPosts = res.posts || [];
        renderPostsTable(allPosts);
        document.getElementById("postCount").textContent = allPosts.length;
    } catch (e) {
        showBlogAlert("Could not load posts: " + e.message, "error");
    }
}

function renderPostsTable(posts) {
    const tbody = document.getElementById("postsTableBody");
    if (!posts.length) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">
            <div class="empty-state"><i class="fa fa-newspaper"></i>
            <p>No posts yet. Click <strong>New Post</strong> to get started.</p></div>
        </td></tr>`;
        return;
    }
    tbody.innerHTML = posts.map(p => {
        const thumb = p.image_path
            ? `<img class="project-thumb" src="../${escHtml(p.image_path)}" loading="lazy" alt="${escHtml(p.title)}">`
            : `<div class="thumb-placeholder"><i class="fa fa-newspaper"></i></div>`;
        const statusClass = `status-${p.status}`;
        const dot  = p.status === "published" ? "✓" : "○";
        const date = p.published_at ? new Date(p.published_at).toLocaleDateString("en-GB",{day:"numeric",month:"short",year:"numeric"}) : "—";
        return `
        <tr data-id="${p.id}">
            <td>${thumb}</td>
            <td>
                <div class="proj-name">${escHtml(p.title)}</div>
                <div class="proj-location"><i class="fa fa-link"></i> <a href="../blog-detail.php?slug=${escHtml(p.slug)}" target="_blank" style="color:var(--accent);font-size:11px;">blog-detail.php?slug=${escHtml(p.slug)}</a></div>
            </td>
            <td><span class="cat-badge">${escHtml(p.category || "—")}</span></td>
            <td><span class="status-badge ${statusClass}">${dot} ${escHtml(p.status)}</span></td>
            <td>${escHtml(p.author || "—")}</td>
            <td>${date}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" title="Edit"   onclick="editPost('${p.id}')"><i class="fa fa-pen"></i></button>
                    <button class="btn-icon" title="View"   onclick="window.open('../blog-detail.php?slug=${escHtml(p.slug)}','_blank')"><i class="fa fa-eye"></i></button>
                    <button class="btn-icon delete" title="Delete" onclick="openDeletePost('${p.id}')"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join("");
}

function filterPosts() {
    const q      = document.getElementById("searchPosts").value.toLowerCase();
    const cat    = document.getElementById("postCategoryFilter").value;
    const status = document.getElementById("postStatusFilter").value;
    const filtered = allPosts.filter(p =>
        (!q      || p.title.toLowerCase().includes(q) || (p.author || "").toLowerCase().includes(q)) &&
        (!cat    || p.category === cat) &&
        (!status || p.status   === status)
    );
    renderPostsTable(filtered);
}

/* ============================================================
   BLOG — ADD/EDIT MODAL
   ============================================================ */
let editingPostId       = null;
let pendingPostImageFile = null;

function openPostModal(id = null) {
    editingPostId        = id;
    pendingPostImageFile = null;

    document.getElementById("postForm").reset();
    document.getElementById("postUploadPlaceholder").style.display = "block";
    document.getElementById("postImagePreview").style.display      = "none";
    document.getElementById("postImagePreview").src                = "";
    document.getElementById("postImageFile").value                 = "";
    document.getElementById("postImagePath").value                 = "";
    document.getElementById("postBodyEditor").innerHTML            = "";
    document.getElementById("postBody").value                      = "";
    document.getElementById("postSlugDisplay").textContent         = "Enter a title above…";
    document.getElementById("postAuthor").value                    = "Claricent Team";

    if (id) {
        const p = allPosts.find(x => x.id == id);
        if (!p) return;
        document.getElementById("postModalTitle").textContent  = "Edit Post";
        document.getElementById("postId").value                = p.id;
        document.getElementById("postTitle").value             = p.title;
        document.getElementById("postCategory").value          = p.category || "";
        document.getElementById("postStatus").value            = p.status   || "draft";
        document.getElementById("postAuthor").value            = p.author   || "Claricent Team";
        document.getElementById("postTags").value              = p.tags     || "";
        document.getElementById("postExcerpt").value           = p.excerpt  || "";
        document.getElementById("postImagePath").value         = p.image_path || "";
        document.getElementById("postSlugDisplay").textContent = p.slug ? `blog-detail.php?slug=${p.slug}` : "";
        // Load body HTML into the rich editor
        document.getElementById("postBodyEditor").innerHTML    = p.body || "";
        if (p.image_path) showPostImagePreview(`../${p.image_path}`);
    } else {
        document.getElementById("postModalTitle").textContent = "New Blog Post";
        document.getElementById("postId").value               = "";
    }

    document.getElementById("postModalOverlay").classList.add("open");
    document.getElementById("postTitle").focus();
}

function closePostModal() {
    document.getElementById("postModalOverlay").classList.remove("open");
    editingPostId = null;
}
function closePostModalOutside(e) {
    if (e.target === document.getElementById("postModalOverlay")) closePostModal();
}
function editPost(id) { openPostModal(id); }

// Live slug preview from title
document.addEventListener("input", e => {
    if (e.target.id === "postTitle") {
        const slug = e.target.value.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, "")
            .replace(/[\s-]+/g, "-")
            .replace(/^-|-$/g, "");
        document.getElementById("postSlugDisplay").textContent = slug ? `blog-detail.php?slug=${slug}` : "Enter a title above…";
    }
});

function savePostAs(status) {
    document.getElementById("postStatus").value = status;
    document.getElementById("postForm").requestSubmit();
}

async function savePost(e) {
    e.preventDefault();
    // Sync rich editor HTML → hidden textarea
    const bodyHtml = document.getElementById("postBodyEditor").innerHTML.trim();
    document.getElementById("postBody").value = bodyHtml;

    const title      = document.getElementById("postTitle").value.trim();
    const category   = document.getElementById("postCategory").value;
    const status     = document.getElementById("postStatus").value;
    const author     = document.getElementById("postAuthor").value.trim() || "Claricent Team";
    const tags       = document.getElementById("postTags").value.trim();
    const excerpt    = document.getElementById("postExcerpt").value.trim();
    const body       = bodyHtml;
    let   image_path = document.getElementById("postImagePath").value.trim();

    // Manual validation (can't use 'required' on hidden fields)
    if (!title) {
        showBlogAlert("Please enter a post title.", "error");
        document.getElementById("postTitle").focus();
        return;
    }
    // Strip HTML tags to check if body actually has text content
    const bodyText = body.replace(/<[^>]*>/g, "").trim();
    if (!bodyText) {
        showBlogAlert("Post body cannot be empty. Write some content above.", "error");
        document.getElementById("postBodyEditor").focus();
        return;
    }

    const btn = document.getElementById("postSaveBtn");
    btn.disabled = true;
    btn.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Saving…`;

    try {
        const projRes = await api(editingPostId ? "update_post" : "create_post", {
            id: editingPostId || "", title, category, status, author, tags,
            excerpt, body, image_path: pendingPostImageFile ? (image_path || "") : image_path
        });
        if (!projRes.success) throw new Error(projRes.error || "Save failed");
        const savedId = projRes.id;

        if (pendingPostImageFile) {
            const upRes = await apiUploadPost(pendingPostImageFile, savedId);
            if (!upRes.success) showBlogAlert("Post saved but image upload failed: " + (upRes.error || ""), "error");
        }

        closePostModal();
        await loadPosts();

        // Auto-rebuild blog.html only for published posts
        if (status === "published") {
            const rb = await api("rebuild_blog_page");
            if (rb.success) {
                showBlogAlert(`✓ "${title}" published! blog.html updated with ${rb.post_count} post${rb.post_count !== 1 ? "s" : ""}.`, "success");
            } else {
                showBlogAlert("Post saved but blog.html rebuild failed: " + (rb.error || ""), "error");
            }
        } else {
            showBlogAlert(`"${title}" saved as draft.`, "success");
        }

    } catch (err) {
        showBlogAlert("Error: " + err.message, "error");
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa fa-paper-plane"></i> Publish Post`;
    }
}

async function apiUploadPost(file, postId) {
    const fd = new FormData();
    fd.append("action",    "upload_image");
    fd.append("image",     file);
    fd.append("projectId", postId);   // api.php uses projectId for naming — works for both
    const res = await fetch("api.php", { method: "POST", credentials: "same-origin", body: fd });
    return res.json();
}

/* ── Post image handling ── */
function handlePostImageSelect(e) {
    const f = e.target.files[0]; if (f) previewPostImageFile(f);
}
function previewPostImageFile(file) {
    if (!file.type.startsWith("image/")) { showBlogAlert("Invalid image.", "error"); return; }
    if (file.size > 5*1024*1024)         { showBlogAlert("Max 5MB.", "error"); return; }
    pendingPostImageFile = file;
    const reader = new FileReader();
    reader.onload = ev => {
        showPostImagePreview(ev.target.result);
        if (!document.getElementById("postImagePath").value)
            document.getElementById("postImagePath").value = `images/${file.name}`;
    };
    reader.readAsDataURL(file);
}
function showPostImagePreview(src) {
    document.getElementById("postUploadPlaceholder").style.display = "none";
    const img = document.getElementById("postImagePreview");
    img.src = src; img.style.display = "block";
}

// Drag & drop on post upload zone
document.addEventListener("DOMContentLoaded", () => {
    const zone = document.getElementById("postUploadZone");
    if (!zone) return;
    zone.addEventListener("dragover",  e => { e.preventDefault(); zone.classList.add("drag-over"); });
    zone.addEventListener("dragleave", () => zone.classList.remove("drag-over"));
    zone.addEventListener("drop", e => {
        e.preventDefault(); zone.classList.remove("drag-over");
        const f = e.dataTransfer.files[0];
        if (f && f.type.startsWith("image/")) previewPostImageFile(f);
    });
});

/* ============================================================
   BLOG — DELETE
   ============================================================ */
let deletePostTargetId = null;

function openDeletePost(id) {
    deletePostTargetId = id;
    const p = allPosts.find(x => x.id == id);
    document.getElementById("deletePostTitle").textContent = p ? p.title : "this post";
    document.getElementById("deletePostOverlay").classList.add("open");
}
function closeDeletePost() {
    document.getElementById("deletePostOverlay").classList.remove("open");
    deletePostTargetId = null;
}

async function confirmDeletePost() {
    if (!deletePostTargetId) return;
    const btn = document.querySelector("#deletePostOverlay .btn-danger");
    btn.disabled = true; btn.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Deleting…`;
    try {
        const res = await api("delete_post", { id: deletePostTargetId });
        if (!res.success) throw new Error(res.error || "Delete failed");
        closeDeletePost();
        await loadPosts();
        const rb = await api("rebuild_blog_page");
        showBlogAlert(rb.success ? "Post deleted and blog.html updated." : "Deleted but rebuild failed: " + rb.error, rb.success ? "success" : "error");
    } catch (err) {
        showBlogAlert("Error: " + err.message, "error");
        btn.disabled = false; btn.innerHTML = `<i class="fa fa-trash"></i> Delete`;
    }
}

/* ============================================================
   RICH TEXT EDITOR COMMANDS
   ============================================================ */
function fmt(cmd) {
    document.getElementById("postBodyEditor").focus();
    document.execCommand(cmd, false, null);
}
function fmtBlock(tag) {
    document.getElementById("postBodyEditor").focus();
    document.execCommand("formatBlock", false, tag);
}
function insertLink() {
    const url = prompt("Enter URL:");
    if (url) {
        document.getElementById("postBodyEditor").focus();
        document.execCommand("createLink", false, url);
    }
}

/* ============================================================
   BLOG ALERTS (separate zone from projects)
   ============================================================ */
let blogAlertTimeout;
function showBlogAlert(msg, type = "info") {
    const zone  = document.getElementById("blogAlertZone");
    const icons = { success:"fa-check-circle", error:"fa-times-circle", info:"fa-info-circle" };
    zone.innerHTML = `<div class="alert alert-${type}"><i class="fa ${icons[type]}"></i><span>${escHtml(msg)}</span></div>`;
    clearTimeout(blogAlertTimeout);
    blogAlertTimeout = setTimeout(() => { zone.innerHTML = ""; }, 6000);
}