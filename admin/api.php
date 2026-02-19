<?php
/**
 * CLARICENT ADMIN — api.php
 * Fixed: PHP errors leaking into JSON, MySQL subquery bug, flexible marker matching.
 */

/* ─── CRITICAL: Catch ALL PHP output so errors never break JSON ── */
ob_start();

/* ─── Suppress PHP display_errors leaking HTML into JSON ──────── */
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Convert PHP errors to exceptions so they're caught properly
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/* ─── CONFIG ──────────────────────────────────────────────── */
define("DB_HOST",        "localhost");
define("DB_NAME",        "claricen_db");
define("DB_USER",        "root");    // ← YOUR DB USERNAME
define("DB_PASS",        "");    // ← YOUR DB PASSWORD
define("DB_CHARSET",     "utf8mb4");

define("ADMIN_USER",     "admin");          // ← CHANGE THIS
define("ADMIN_PASS",     "claricent2024");  // ← CHANGE THIS

define("UPLOAD_DIR",     "../images/");
define("PROJECTS_FILE",  "../projects.html");
define("BLOG_FILE",      "../blog.html");
define("POSTS_PER_PAGE", 6);
define("MAX_FILESIZE",   5 * 1024 * 1024);
define("ALLOWED_MIME",   ["image/jpeg","image/png","image/webp","image/gif"]);
define("ALLOWED_EXT",    ["jpg","jpeg","png","webp","gif"]);

/* ─── SQL INSTALL — run once in phpMyAdmin ──────────────────
CREATE TABLE IF NOT EXISTS `projects` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200) NOT NULL,
  `slug`        VARCHAR(220) NOT NULL,
  `category`    VARCHAR(80)  NOT NULL DEFAULT '',
  `status`      ENUM('ongoing','completed','upcoming') NOT NULL DEFAULT 'upcoming',
  `year`        SMALLINT     NULL,
  `location`    VARCHAR(200) NULL,
  `description` TEXT         NOT NULL,
  `image_path`  VARCHAR(300) NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
──────────────────────────────────────────────────────────── */

/* ─── HEADERS ─────────────────────────────────────────────── */
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

/* ─── SESSION ─────────────────────────────────────────────── */
session_name("claricent_admin");
session_set_cookie_params([
    "lifetime" => 0, "path" => "/",
    "secure"   => !empty($_SERVER["HTTPS"]),
    "httponly" => true, "samesite" => "Strict"
]);
session_start();

/* ─── ROUTE ───────────────────────────────────────────────── */
try {
    $action = trim($_POST["action"] ?? $_GET["action"] ?? "");

    switch ($action) {
        case "login":                 handleLogin();        break;
        case "logout":                handleLogout();       break;
        case "check_auth":            checkAuth();          break;
        case "get_projects":          requireAuth(); getProjects();          break;
        case "create_project":        requireAuth(); createProject();        break;
        case "update_project":        requireAuth(); updateProject();        break;
        case "delete_project":        requireAuth(); deleteProject();        break;
        case "upload_image":          requireAuth(); uploadImage();          break;
        case "rebuild_projects_page": requireAuth(); rebuildProjectsPage();  break;
        /* ── Blog ── */
        case "get_posts":             requireAuth(); getPosts();             break;
        case "create_post":           requireAuth(); createPost();           break;
        case "update_post":           requireAuth(); updatePost();           break;
        case "delete_post":           requireAuth(); deletePost();           break;
        case "rebuild_blog_page":     requireAuth(); rebuildBlogPage();      break;
        /* ── Shared ── */
        case "diagnose":              requireAuth(); diagnose();             break;
        default: respond(false, null, "Unknown action: '$action'");
    }
} catch (Throwable $e) {
    // Catch absolutely everything — PHP errors, exceptions, type errors — return as JSON
    respond(false, null, $e->getMessage() . " (in " . basename($e->getFile()) . " line " . $e->getLine() . ")");
}

/* ══════════════════════════════════════════════════════════
   AUTH
   ══════════════════════════════════════════════════════════ */
function handleLogin(): void {
    $u = trim($_POST["username"] ?? "");
    $p = $_POST["password"] ?? "";
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION["auth"] = true;
        respond(true);
    } else {
        usleep(500000);
        respond(false, null, "Invalid credentials.");
    }
}

function handleLogout(): void {
    $_SESSION = []; session_destroy();
    respond(true);
}

function checkAuth(): void {
    respond(true, ["authenticated" => !empty($_SESSION["auth"])]);
}

function requireAuth(): void {
    if (empty($_SESSION["auth"])) {
        http_response_code(401);
        respond(false, null, "Not authenticated.");
    }
}

/* ══════════════════════════════════════════════════════════
   DATABASE
   ══════════════════════════════════════════════════════════ */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* ══════════════════════════════════════════════════════════
   SLUG
   ══════════════════════════════════════════════════════════ */
function makeSlug(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
    $slug = preg_replace('/[\s\-]+/', '-', $slug);
    return trim($slug, '-') ?: 'project';
}

function uniqueSlug(string $name, int $excludeId = 0): string {
    $base = makeSlug($name);
    $slug = $base;
    $i    = 2;
    while (true) {
        $stmt = db()->prepare("SELECT id FROM projects WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

/* ══════════════════════════════════════════════════════════
   PROJECTS CRUD
   ══════════════════════════════════════════════════════════ */
function getProjects(): void {
    $rows = db()->query("SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC")->fetchAll();
    respond(true, ["projects" => $rows]);
}

function createProject(): void {
    $d = sanitizeInput();
    $d[":slug"] = uniqueSlug($_POST["name"] ?? "");

    // FIX: get next sort_order in a separate query to avoid MySQL subquery self-reference error
    $maxOrder = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) FROM projects")->fetchColumn();
    $d[":sort_order"] = $maxOrder + 1;

    $sql = "INSERT INTO projects
                (name, slug, category, status, year, location, description, image_path, sort_order)
            VALUES
                (:name, :slug, :category, :status, :year, :location, :description, :image_path, :sort_order)";

    db()->prepare($sql)->execute($d);
    $id = (int) db()->lastInsertId();
    respond(true, ["id" => $id, "slug" => $d[":slug"]]);
}

function updateProject(): void {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) respond(false, null, "Invalid project ID.");

    $d         = sanitizeInput();
    $d[":slug"] = uniqueSlug($_POST["name"] ?? "", $id);
    $d[":id"]   = $id;

    $sql = "UPDATE projects SET
                name=:name, slug=:slug, category=:category, status=:status,
                year=:year, location=:location, description=:description, image_path=:image_path
            WHERE id=:id";
    db()->prepare($sql)->execute($d);
    respond(true, ["id" => $id, "slug" => $d[":slug"]]);
}

function deleteProject(): void {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) respond(false, null, "Invalid project ID.");

    // Safely remove image file from disk
    $row = db()->prepare("SELECT image_path FROM projects WHERE id = ?");
    $row->execute([$id]);
    $p = $row->fetch();
    if ($p && !empty($p["image_path"])) {
        $real = realpath("../" . $p["image_path"]);
        $dir  = realpath(UPLOAD_DIR);
        if ($real && $dir && strpos($real, $dir) === 0) {
            @unlink($real);
        }
    }

    db()->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    respond(true);
}

function sanitizeInput(): array {
    $validStatus = ["ongoing", "completed", "upcoming"];
    $status = in_array($_POST["status"] ?? "", $validStatus, true) ? $_POST["status"] : "upcoming";
    $year   = filter_var($_POST["year"] ?? null, FILTER_VALIDATE_INT,
                  ["options" => ["min_range" => 1900, "max_range" => 2100]]);
    return [
        ":name"        => mb_substr(trim($_POST["name"]        ?? ""), 0, 200),
        ":category"    => mb_substr(trim($_POST["category"]    ?? ""), 0, 80),
        ":status"      => $status,
        ":year"        => $year ?: null,
        ":location"    => mb_substr(trim($_POST["location"]    ?? ""), 0, 200) ?: null,
        ":description" => trim($_POST["description"] ?? ""),
        ":image_path"  => mb_substr(trim($_POST["image_path"]  ?? ""), 0, 300) ?: null,
    ];
}

/* ══════════════════════════════════════════════════════════
   IMAGE UPLOAD
   ══════════════════════════════════════════════════════════ */
function uploadImage(): void {
    if (!isset($_FILES["image"]) || $_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
        $codes = [
            1 => "File too large (server limit)",
            2 => "File too large (form limit)",
            3 => "Only partially uploaded",
            4 => "No file was uploaded",
            6 => "Missing temp folder",
            7 => "Failed to write to disk",
        ];
        $code = $_FILES["image"]["error"] ?? 4;
        respond(false, null, $codes[$code] ?? "Upload error code $code");
    }

    $file = $_FILES["image"];
    if ($file["size"] > MAX_FILESIZE) {
        respond(false, null, "File too large. Maximum is 5MB.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file["tmp_name"]);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        respond(false, null, "Invalid file type: $mime. Allowed: JPG, PNG, WEBP, GIF.");
    }

    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        respond(false, null, "Invalid file extension: .$ext");
    }

    // Use project slug as filename for clean, readable image names
    $id       = (int)($_POST["projectId"] ?? 0);
    $slugBase = "project-" . date("Ymd-His");
    if ($id > 0) {
        $row = db()->prepare("SELECT slug FROM projects WHERE id = ?");
        $row->execute([$id]);
        $r = $row->fetch();
        if ($r && !empty($r["slug"])) $slugBase = $r["slug"];
    }
    $filename = $slugBase . "." . $ext;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!is_writable(UPLOAD_DIR)) {
        respond(false, null, "Upload directory is not writable: " . realpath(UPLOAD_DIR));
    }

    if (!move_uploaded_file($file["tmp_name"], UPLOAD_DIR . $filename)) {
        respond(false, null, "Could not move uploaded file to destination.");
    }

    $webPath = "images/" . $filename;
    if ($id > 0) {
        db()->prepare("UPDATE projects SET image_path = ? WHERE id = ?")->execute([$webPath, $id]);
    }

    respond(true, ["path" => $webPath]);
}

/* ══════════════════════════════════════════════════════════
   AUTO-REBUILD projects.html
   ══════════════════════════════════════════════════════════ */
function rebuildProjectsPage(): void {
    $file = PROJECTS_FILE;

    if (!file_exists($file)) {
        respond(false, null,
            "projects.html not found. Expected at: " . realpath(dirname(PROJECTS_FILE)) . "/projects.html — " .
            "make sure you uploaded the new projects.html (with marker comments) to your website root.");
    }
    if (!is_writable($file)) {
        respond(false, null,
            "projects.html is not writable. SSH into your server and run: chmod 664 projects.html");
    }

    $html = str_replace("\r\n", "\n", file_get_contents($file)); // normalize CRLF

    // Check markers exist — flexible regex that handles any whitespace
    if (!preg_match('/<!--\s*ADMIN:PROJECTS:START\s*-->/', $html)) {
        respond(false, null,
            "Marker <!-- ADMIN:PROJECTS:START --> not found in projects.html. " .
            "Please upload the new projects.html file that was provided — it contains the required marker comments.");
    }

    // Auto-insert missing end marker right after start if absent
    if (!preg_match('/<!--\s*ADMIN:PROJECTS:END\s*-->/', $html)) {
        $html = preg_replace(
            '/<!--\s*ADMIN:PROJECTS:START\s*-->/',
            "<!-- ADMIN:PROJECTS:START -->\n                <!-- ADMIN:PROJECTS:END -->",
            $html
        );
    }

    // Fetch all projects
    $projects = db()->query("SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC")->fetchAll();

    // Build inner HTML
    $delays = ["0.25s","0.5s","0.75s","1s","1.25s","1.5s"];
    $inner  = "<!-- ADMIN:PROJECTS:START -->";

    if (empty($projects)) {
        $inner .= "\n                <!-- No projects yet -->";
    } else {
        foreach ($projects as $i => $p) {
            $delay   = $delays[$i % count($delays)];
            $imgSrc  = htmlspecialchars($p["image_path"] ?: "images/placeholder.jpg", ENT_QUOTES, "UTF-8");
            $name    = htmlspecialchars($p["name"], ENT_QUOTES, "UTF-8");
            $nameLow = htmlspecialchars(strtolower($p["name"]), ENT_QUOTES, "UTF-8");
            $desc    = htmlspecialchars($p["description"], ENT_QUOTES, "UTF-8");
            $slug    = htmlspecialchars($p["slug"], ENT_QUOTES, "UTF-8");
            $href    = "project-detail.php?slug={$slug}";

            $inner .= "
                <div class=\"col-lg-4 col-md-6\">
                    <!-- Project Item Start -->
                    <div class=\"project-item wow fadeInUp\" data-wow-delay=\"{$delay}\">
                        <!-- Project Image Start -->
                        <div class=\"project-image\" data-cursor-text=\"View\">
                            <a href=\"{$href}\">
                                <figure>
                                    <img src=\"{$imgSrc}\" alt=\"{$name}\">
                                </figure>
                            </a>
                        </div>
                        <!-- Project Image End -->

                        <!-- Project Body Start -->
                        <div class=\"project-body\">
                            <!-- Project Body Title Start -->
                            <div class=\"project-body-title\">
                                <h3>{$nameLow}</h3>
                            </div>
                            <!-- Project Body Title End -->

                            <!-- Project Content Start -->
                            <div class=\"project-content\">
                                <p>{$desc}</p>
                                <div class=\"project-content-footer\">
                                    <a href=\"{$href}\" class=\"readmore-btn\">view more</a>
                                </div>
                            </div>
                            <!-- Project Content End -->
                        </div>
                        <!-- Project Body End -->
                    </div>
                    <!-- Project Item End -->
                </div>";
        }
    }

    $inner .= "\n                <!-- ADMIN:PROJECTS:END -->";

    // Replace content between markers — flexible whitespace in pattern
    $pattern = '/<!--\s*ADMIN:PROJECTS:START\s*-->.*?<!--\s*ADMIN:PROJECTS:END\s*-->/s';
    $updated = preg_replace($pattern, $inner, $html);

    if ($updated === null) {
        respond(false, null, "Regex replacement failed.");
    }
    if ($updated === $html) {
        respond(false, null, "Markers found but content unchanged — regex matched nothing. Check START and END markers are on their own lines.");
    }

    if (file_put_contents($file, $updated, LOCK_EX) === false) {
        respond(false, null, "Write failed. Check permissions on projects.html.");
    }

    respond(true, ["project_count" => count($projects)]);
}

/* ══════════════════════════════════════════════════════════
   DIAGNOSE — call from admin to check everything is wired up
   ══════════════════════════════════════════════════════════ */
function diagnose(): void {
    $file    = PROJECTS_FILE;
    $imgDir  = UPLOAD_DIR;
    $html    = file_exists($file) ? file_get_contents($file) : "";

    $blogFile = BLOG_FILE;
    $blogHtml = file_exists($blogFile) ? file_get_contents($blogFile) : "";
    $checks = [
        "projects_html_exists"    => file_exists($file),
        "projects_html_writable"  => is_writable($file),
        "projects_html_has_start_marker" => (bool) preg_match('/<!--\s*ADMIN:PROJECTS:START\s*-->/', $html),
        "projects_html_has_end_marker"   => (bool) preg_match('/<!--\s*ADMIN:PROJECTS:END\s*-->/',   $html),
        "blog_html_exists"        => file_exists($blogFile),
        "blog_html_writable"      => is_writable($blogFile),
        "blog_html_has_start_marker" => (bool) preg_match('/<!--\s*ADMIN:BLOG:START\s*-->/', $blogHtml),
        "blog_html_has_end_marker"   => (bool) preg_match('/<!--\s*ADMIN:BLOG:END\s*-->/',   $blogHtml),
        "images_dir_exists"       => is_dir($imgDir),
        "images_dir_writable"     => is_writable($imgDir),
        "php_version"             => PHP_VERSION,
        "projects_file_path"      => realpath($file) ?: $file . " (not found)",
        "blog_file_path"          => realpath($blogFile) ?: $blogFile . " (not found)",
        "images_dir_path"         => realpath($imgDir) ?: $imgDir . " (not found)",
    ];

    // Test DB connection
    try {
        $count = (int) db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $checks["db_connected"]      = true;
        $checks["db_project_count"]  = $count;
    } catch (Throwable $e) {
        $checks["db_connected"] = false;
        $checks["db_error"]     = $e->getMessage();
    }

    respond(true, ["checks" => $checks]);
}

/* ─── RESPONSE — clears any leaked PHP output before JSON ─── */
function respond(bool $ok, ?array $data = null, ?string $error = null): void {
    // Discard anything PHP printed before this (errors, warnings, notices)
    if (ob_get_level()) {
        $leaked = ob_get_clean();
        // If something leaked, attach it to the error for debugging
        if ($leaked && $error) {
            $error .= " [PHP output: " . strip_tags(trim($leaked)) . "]";
        } elseif ($leaked && !$error && !$ok) {
            $error = "PHP output: " . strip_tags(trim($leaked));
        }
    }

    $out = ["success" => $ok];
    if ($data)  $out = array_merge($out, $data);
    if ($error) $out["error"] = $error;

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ══════════════════════════════════════════════════════════
   BLOG SQL INSTALL — run once in phpMyAdmin
   ══════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(300) NOT NULL,
  `slug`         VARCHAR(320) NOT NULL,
  `excerpt`      VARCHAR(500) NULL,
  `body`         LONGTEXT     NOT NULL,
  `author`       VARCHAR(120) NOT NULL DEFAULT 'Claricent Team',
  `category`     VARCHAR(80)  NOT NULL DEFAULT '',
  `tags`         VARCHAR(400) NULL,
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `image_path`   VARCHAR(300) NULL,
  `views`        INT UNSIGNED NOT NULL DEFAULT 0,
  `published_at` DATETIME     NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
─────────────────────────────────────────────────────────── */

/* ── Blog Slug ── */
function makeBlogSlug(string $title): string {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
    $slug = preg_replace('/[\s\-]+/', '-', $slug);
    return trim($slug, '-') ?: 'post';
}

function uniqueBlogSlug(string $title, int $excludeId = 0): string {
    $base = makeBlogSlug($title);
    $slug = $base; $i = 2;
    while (true) {
        $stmt = db()->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

/* ══════════════════════════════════════════════════════════
   BLOG CRUD
   ══════════════════════════════════════════════════════════ */
function getPosts(): void {
    $rows = db()->query("SELECT id, title, slug, excerpt, author, category, tags, status, image_path, views, published_at, created_at FROM blog_posts ORDER BY created_at DESC")->fetchAll();
    respond(true, ["posts" => $rows]);
}

function createPost(): void {
    $d = sanitizeBlogInput();
    $d[":slug"] = uniqueBlogSlug($_POST["title"] ?? "");

    $sql = "INSERT INTO blog_posts (title, slug, excerpt, body, author, category, tags, status, image_path, published_at)
            VALUES (:title,:slug,:excerpt,:body,:author,:category,:tags,:status,:image_path,:published_at)";
    db()->prepare($sql)->execute($d);
    $id = (int) db()->lastInsertId();
    respond(true, ["id" => $id, "slug" => $d[":slug"]]);
}

function updatePost(): void {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) respond(false, null, "Invalid post ID.");

    $d = sanitizeBlogInput();
    $d[":slug"] = uniqueBlogSlug($_POST["title"] ?? "", $id);
    $d[":id"]   = $id;

    $sql = "UPDATE blog_posts SET title=:title, slug=:slug, excerpt=:excerpt, body=:body,
                author=:author, category=:category, tags=:tags, status=:status,
                image_path=:image_path, published_at=:published_at
            WHERE id=:id";
    db()->prepare($sql)->execute($d);
    respond(true, ["id" => $id, "slug" => $d[":slug"]]);
}

function deletePost(): void {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) respond(false, null, "Invalid post ID.");

    $row = db()->prepare("SELECT image_path FROM blog_posts WHERE id = ?");
    $row->execute([$id]);
    $p = $row->fetch();
    if ($p && !empty($p["image_path"])) {
        $real = realpath("../" . $p["image_path"]);
        $dir  = realpath(UPLOAD_DIR);
        if ($real && $dir && strpos($real, $dir) === 0) @unlink($real);
    }

    db()->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
    respond(true);
}

function sanitizeBlogInput(): array {
    $status = in_array($_POST["status"] ?? "", ["draft","published"], true) ? $_POST["status"] : "draft";
    $pubAt  = null;
    if ($status === "published") {
        $pubAt = !empty($_POST["published_at"]) ? $_POST["published_at"] : date("Y-m-d H:i:s");
    }
    // Sanitize HTML body — allow safe tags only
    $body = $_POST["body"] ?? "";
    $allowedTags = '<p><br><b><strong><i><em><u><h2><h3><h4><ul><ol><li><a><img><blockquote><figure><figcaption>';
    $body = strip_tags($body, $allowedTags);

    return [
        ":title"        => mb_substr(trim($_POST["title"]    ?? ""), 0, 300),
        ":excerpt"      => mb_substr(trim($_POST["excerpt"]  ?? ""), 0, 500) ?: null,
        ":body"         => $body,
        ":author"       => mb_substr(trim($_POST["author"]   ?? "Claricent Team"), 0, 120),
        ":category"     => mb_substr(trim($_POST["category"] ?? ""), 0, 80),
        ":tags"         => mb_substr(trim($_POST["tags"]     ?? ""), 0, 400) ?: null,
        ":status"       => $status,
        ":image_path"   => mb_substr(trim($_POST["image_path"] ?? ""), 0, 300) ?: null,
        ":published_at" => $pubAt,
    ];
}

/* ══════════════════════════════════════════════════════════
   AUTO-REBUILD blog.html
   ══════════════════════════════════════════════════════════ */
function rebuildBlogPage(): void {
    $file = BLOG_FILE;

    if (!file_exists($file)) {
        respond(false, null, "blog.html not found. Upload the new blog.html with marker comments.");
    }
    if (!is_writable($file)) {
        respond(false, null, "blog.html is not writable. Run: chmod 664 blog.html");
    }

    $html = str_replace("\r\n", "\n", file_get_contents($file)); // normalize CRLF

    // Check start marker exists
    if (!preg_match('/<!--\s*ADMIN:BLOG:START\s*-->/', $html)) {
        respond(false, null, "Marker <!-- ADMIN:BLOG:START --> not found in blog.html. Upload the new blog.html provided.");
    }

    // If end marker is missing, auto-insert it right after the start marker
    // so the regex always has something to replace between
    if (!preg_match('/<!--\s*ADMIN:BLOG:END\s*-->/', $html)) {
        $html = preg_replace(
            '/<!--\s*ADMIN:BLOG:START\s*-->/',
            "<!-- ADMIN:BLOG:START -->\n                <!-- ADMIN:BLOG:END -->",
            $html
        );
    }

    // Only show published posts
    $posts = db()->query("SELECT * FROM blog_posts WHERE status='published' ORDER BY published_at DESC, created_at DESC")->fetchAll();

    $delays = ["0s","0.25s","0.5s","0.75s","1s","1.25s"];
    $inner  = "<!-- ADMIN:BLOG:START -->";

    if (empty($posts)) {
        $inner .= "\n                <!-- No published posts yet -->";
    } else {
        foreach ($posts as $i => $p) {
            $delay     = $delays[$i % count($delays)];
            $imgSrc    = htmlspecialchars($p["image_path"] ?: "images/post-placeholder.jpg", ENT_QUOTES, "UTF-8");
            $title     = htmlspecialchars($p["title"], ENT_QUOTES, "UTF-8");
            $slug      = htmlspecialchars($p["slug"],  ENT_QUOTES, "UTF-8");
            $href      = "blog-detail.php?slug={$slug}";
            $delayAttr = $delay !== "0s" ? " data-wow-delay=\"{$delay}\"" : "";

            $inner .= "
                <div class=\"col-lg-4 col-md-6\">
                    <!-- Blog Item Start -->
                    <div class=\"blog-item wow fadeInUp\"{$delayAttr}>
                        <!-- Post Featured Image Start-->
                        <div class=\"post-featured-image\" data-cursor-text=\"View\">
                            <figure>
                                <a href=\"{$href}\" class=\"image-anime\">
                                    <img src=\"{$imgSrc}\" alt=\"{$title}\">
                                </a>
                            </figure>
                        </div>
                        <!-- Post Featured Image End -->

                        <!-- post Item Content Start -->
                        <div class=\"post-item-content\">
                            <!-- post Item Body Start -->
                            <div class=\"post-item-body\">
                                <h2><a href=\"{$href}\">{$title}</a></h2>
                            </div>
                            <!-- Post Item Body End-->

                            <!-- Post Item Footer Start-->
                            <div class=\"post-item-footer\">
                                <a href=\"{$href}\" class=\"readmore-btn\">read more</a>
                            </div>
                            <!-- Post Item Footer End-->
                        </div>
                        <!-- post Item Content End -->
                    </div>
                    <!-- Blog Item End -->
                </div>";
        }
    }

    $inner .= "\n                <!-- ADMIN:BLOG:END -->";

    // Replace between markers (flexible whitespace, dotall for multiline)
    $pattern = '/<!--\s*ADMIN:BLOG:START\s*-->.*?<!--\s*ADMIN:BLOG:END\s*-->/s';
    $updated = preg_replace($pattern, $inner, $html);

    if ($updated === null) {
        respond(false, null, "Regex replacement returned null — PHP regex error.");
    }
    if ($updated === $html) {
        respond(false, null, "Markers found but content was unchanged — the regex matched nothing. Check that START and END markers are on separate lines with no extra characters.");
    }

    if (file_put_contents($file, $updated, LOCK_EX) === false) {
        respond(false, null, "Write failed. Check file permissions: chmod 664 blog.html");
    }

    respond(true, ["post_count" => count($posts)]);
}