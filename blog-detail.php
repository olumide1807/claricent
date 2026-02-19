<?php
/**
 * CLARICENT — blog-detail.php
 * Dynamic blog post page. URL: blog-detail.php?slug=post-slug
 * Place in the WEBSITE ROOT (same level as blog.html).
 */

/* ─── CONFIG (match admin/api.php) ───────────────────────── */
define("DB_HOST",    "localhost");
define("DB_NAME",    "claricen_db");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");

/* ─── DB ──────────────────────────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

/* ─── FETCH POST ──────────────────────────────────────────── */
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET["slug"] ?? "")));
if (!$slug) { header("Location: blog.html"); exit; }

try {
    $stmt = db()->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
} catch (PDOException $e) { $post = null; }

if (!$post) { header("Location: blog.html"); exit; }

// Increment view count
try { db()->prepare("UPDATE blog_posts SET views = views + 1 WHERE slug = ?")->execute([$slug]); } catch(Exception $e){}

// Prev / Next posts
try {
    $prev = db()->prepare("SELECT title, slug FROM blog_posts WHERE status='published' AND id < ? ORDER BY id DESC LIMIT 1");
    $prev->execute([$post["id"]]);
    $prevPost = $prev->fetch();

    $next = db()->prepare("SELECT title, slug FROM blog_posts WHERE status='published' AND id > ? ORDER BY id ASC LIMIT 1");
    $next->execute([$post["id"]]);
    $nextPost = $next->fetch();
} catch(Exception $e) { $prevPost = $nextPost = null; }

// Related posts (same category, excluding current)
try {
    $rel = db()->prepare("SELECT title, slug, image_path, published_at FROM blog_posts WHERE status='published' AND category=? AND id != ? ORDER BY published_at DESC LIMIT 3");
    $rel->execute([$post["category"], $post["id"]]);
    $related = $rel->fetchAll();
} catch(Exception $e) { $related = []; }

/* ─── HELPERS ─────────────────────────────────────────────── */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_HTML5, "UTF-8"); }
function formatDate(string $d): string { return date("F j, Y", strtotime($d)); }
function readingTime(string $content): int {
    $words = str_word_count(strip_tags($content));
    return max(1, (int) ceil($words / 200));
}

$title     = e($post["title"]);
$excerpt   = e($post["excerpt"] ?? "");
$body      = $post["body"] ?? ""; // rendered as HTML — stored as rich text
$author    = e($post["author"] ?? "Claricent Team");
$category  = e(ucfirst($post["category"] ?? ""));
$tags      = array_filter(array_map('trim', explode(',', $post["tags"] ?? "")));
$published = $post["published_at"] ? formatDate($post["published_at"]) : formatDate($post["created_at"]);
$imgSrc    = $post["image_path"] ? e($post["image_path"]) : null;
$readTime  = readingTime($post["body"] ?? "");
$pageUrl   = urlencode("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
$pageTitle = urlencode($post["title"] . " — Claricent Company Limited");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $excerpt ?: $title ?>">
    <meta name="keywords" content="construction, Ghana, Claricent, <?= $category ?>">
    <title><?= $title ?> — Claricent Company Limited</title>
    <meta property="og:title"       content="<?= $title ?>">
    <meta property="og:description" content="<?= $excerpt ?: $title ?>">
    <meta property="og:type"        content="article">
    <?php if ($imgSrc): ?><meta property="og:image" content="<?= $imgSrc ?>"><?php endif; ?>

    <link rel="shortcut icon" type="image/x-icon" href="images/CLARICENT_COMPANY_LIMITED_LOADER.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slicknav@1.0.10/dist/slicknav.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/mousecursor.css">
    <link href="css/custom.css" rel="stylesheet">

    <style>
        .blog-detail-wrap { padding: 80px 0; }

        /* ── Hero image ── */
        .blog-hero-img {
            width: 100%; max-height: 500px; object-fit: cover;
            border-radius: 14px; margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }
        .blog-hero-placeholder {
            width: 100%; height: 300px; border-radius: 14px;
            background: linear-gradient(135deg, #2C3E50, #1E90FF22);
            display: flex; align-items: center; justify-content: center;
            color: #667282; font-size: 56px; margin-bottom: 40px;
        }

        /* ── Meta bar ── */
        .blog-meta-bar {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 20px; margin-bottom: 20px;
            padding-bottom: 20px; border-bottom: 1px solid #e8edf2;
        }
        .blog-meta-item {
            display: flex; align-items: center; gap: 7px;
            font-size: 14px; color: #667282;
        }
        .blog-meta-item i { color: #1E90FF; font-size: 13px; }
        .blog-cat-badge {
            display: inline-block; padding: 3px 12px;
            background: #1E90FF15; color: #1E90FF;
            border: 1px solid #1E90FF30; border-radius: 999px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* ── Title ── */
        .blog-detail-title {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(26px, 4vw, 44px);
            font-weight: 800; color: #2C3E50;
            line-height: 1.2; margin-bottom: 32px;
        }

        /* ── Body content ── */
        .blog-body {
            font-size: 17px; line-height: 1.85; color: #667282;
        }
        .blog-body h2, .blog-body h3 {
            font-family: 'Manrope', sans-serif;
            color: #2C3E50; margin: 36px 0 14px;
        }
        .blog-body h2 { font-size: 26px; font-weight: 800; }
        .blog-body h3 { font-size: 20px; font-weight: 700; }
        .blog-body p  { margin-bottom: 20px; }
        .blog-body ul, .blog-body ol {
            padding-left: 24px; margin-bottom: 20px;
        }
        .blog-body li { margin-bottom: 8px; }
        .blog-body img {
            max-width: 100%; border-radius: 10px;
            margin: 24px 0; box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .blog-body blockquote {
            border-left: 4px solid #1E90FF;
            padding: 16px 24px; margin: 28px 0;
            background: #1E90FF08; border-radius: 0 8px 8px 0;
            font-style: italic; color: #2C3E50;
        }
        .blog-body a { color: #1E90FF; text-decoration: underline; }

        /* ── Tags ── */
        .blog-tags {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 8px; margin-top: 40px; padding-top: 24px;
            border-top: 1px solid #e8edf2;
        }
        .tags-label { font-size: 13px; font-weight: 700; color: #2C3E50; }
        .tag-pill {
            display: inline-block; padding: 4px 14px;
            background: #f4f6f9; border: 1px solid #e8edf2;
            border-radius: 999px; font-size: 13px; color: #667282;
            transition: all 0.2s;
        }
        .tag-pill:hover { background: #1E90FF15; color: #1E90FF; border-color: #1E90FF30; }

        /* ── Share ── */
        .share-strip {
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; margin-top: 28px;
        }
        .share-label { font-size: 13px; font-weight: 700; color: #2C3E50; }
        .share-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px; border-radius: 6px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            transition: all 0.2s;
        }
        .share-fb { background:#1877f210;color:#1877f2;border:1px solid #1877f230; }
        .share-tw { background:#00000010;color:#000;border:1px solid #00000020; }
        .share-wa { background:#25d36610;color:#25d366;border:1px solid #25d36630; }
        .share-btn:hover { transform: translateY(-1px); opacity: 0.8; }

        /* ── Nav ── */
        .post-nav {
            display: flex; justify-content: space-between;
            gap: 16px; margin-top: 48px; padding-top: 32px;
            border-top: 1px solid #e8edf2;
        }
        .post-nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px; border: 1px solid #e8edf2;
            border-radius: 10px; text-decoration: none; color: #2C3E50;
            transition: all 0.2s; max-width: 46%;
            font-family: 'Manrope', sans-serif;
        }
        .post-nav-link:hover { border-color:#1E90FF; color:#1E90FF; background:#1E90FF08; }
        .post-nav-link.next { flex-direction: row-reverse; text-align: right; }
        .nav-label { font-size: 11px; color:#667282; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:3px; }
        .nav-title { font-size: 14px; font-weight: 700; line-height: 1.3; }
        .nav-center { color:#667282; font-size:13px; }
        .nav-center a { color:#1E90FF; font-weight:600; }

        /* ── Sidebar ── */
        .blog-sidebar { position: sticky; top: 100px; }

        .sidebar-card {
            background: #f8fafc; border: 1px solid #e8edf2;
            border-radius: 12px; padding: 28px; margin-bottom: 24px;
        }
        .sidebar-card h4 {
            font-family: 'Manrope', sans-serif;
            font-size: 16px; font-weight: 800; color: #2C3E50;
            margin-bottom: 20px; padding-bottom: 12px;
            border-bottom: 2px solid #1E90FF;
            display: inline-block;
        }

        .author-avatar {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, #2C3E50, #1E90FF);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 24px; font-weight: 800;
            font-family: 'Manrope', sans-serif; margin-bottom: 12px;
        }
        .author-name { font-family:'Manrope',sans-serif; font-weight:700; color:#2C3E50; font-size:16px; }
        .author-role { font-size:13px; color:#667282; }

        .related-item {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 12px 0; border-bottom: 1px solid #e8edf2;
        }
        .related-item:last-child { border-bottom: none; padding-bottom: 0; }
        .related-thumb {
            width: 64px; height: 52px; object-fit: cover;
            border-radius: 7px; flex-shrink: 0;
        }
        .related-thumb-placeholder {
            width: 64px; height: 52px; border-radius: 7px;
            background: #e8edf2; display:flex; align-items:center;
            justify-content:center; color:#9aa; flex-shrink:0;
        }
        .related-title {
            font-size: 14px; font-weight: 600; color: #2C3E50;
            line-height: 1.4; text-decoration: none;
            display: block; margin-bottom: 4px;
        }
        .related-title:hover { color: #1E90FF; }
        .related-date { font-size: 12px; color: #667282; }
    </style>
</head>
<body>

    <!-- Preloader -->
    <div class="preloader">
        <div class="loading-container">
            <div class="loading"></div>
            <div id="loading-icon"><img src="images/CLARICENT_COMPANY_LIMITED_LOADER.png" alt=""></div>
        </div>
    </div>

    <!-- Header -->
    <header class="main-header">
        <div class="header-sticky active-sticky-header">
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <a class="navbar-brand" href="./"><img src="images/CLARICENT_COMPANY_LIMITED_FULL_LOGO.png" alt="Logo"></a>
                    <div class="collapse navbar-collapse main-menu">
                        <div class="nav-menu-wrapper">
                            <ul class="navbar-nav mr-auto" id="menu">
                                <li class="nav-item"><a class="nav-link" href="./">Home</a></li>
                                <li class="nav-item"><a class="nav-link" href="about-us.html">About Us</a></li>
                                <li class="nav-item"><a class="nav-link" href="services.html">Services</a></li>
                                <li class="nav-item"><a class="nav-link" href="projects.html">Projects</a></li>
                                <li class="nav-item"><a class="nav-link" href="blog.html">Blog</a></li>
                                <li class="nav-item highlighted-menu"><a class="nav-link" href="contact.html">Contact Us</a></li>
                            </ul>
                        </div>
                        <div class="header-btn d-inline-flex">
                            <a href="contact.html" class="btn-default">Contact Us</a>
                        </div>
                    </div>
                    <div class="navbar-toggle"></div>
                </div>
            </nav>
            <div class="responsive-menu"></div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="page-header parallaxie">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="text-anime-style-3" data-cursor="-opaque">Our Blog</h1>
                        <nav class="wow fadeInUp">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="./">Home</a></li>
                                <li class="breadcrumb-item"><a href="blog.html">Blog</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?= $title ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Blog Detail -->
    <section class="blog-detail-wrap">
        <div class="container">
            <div class="row">

                <!-- Main Content -->
                <div class="col-lg-8">
                    <?php if ($imgSrc): ?>
                        <img src="<?= $imgSrc ?>" alt="<?= $title ?>" class="blog-hero-img wow fadeInUp">
                    <?php else: ?>
                        <div class="blog-hero-placeholder wow fadeInUp"><i class="fa fa-newspaper"></i></div>
                    <?php endif; ?>

                    <!-- Category & Meta -->
                    <div class="blog-meta-bar wow fadeInUp">
                        <?php if ($category): ?>
                            <span class="blog-cat-badge"><?= $category ?></span>
                        <?php endif; ?>
                        <div class="blog-meta-item"><i class="fa fa-calendar"></i> <?= $published ?></div>
                        <div class="blog-meta-item"><i class="fa fa-user"></i> <?= $author ?></div>
                        <div class="blog-meta-item"><i class="fa fa-clock"></i> <?= $readTime ?> min read</div>
                        <div class="blog-meta-item"><i class="fa fa-eye"></i> <?= (int)($post["views"] ?? 0) ?> views</div>
                    </div>

                    <h1 class="blog-detail-title wow fadeInUp"><?= $title ?></h1>

                    <!-- Body -->
                    <div class="blog-body wow fadeInUp">
                        <?= $body /* stored as HTML — sanitized on input */ ?>
                    </div>

                    <!-- Tags -->
                    <?php if ($tags): ?>
                    <div class="blog-tags wow fadeInUp">
                        <span class="tags-label"><i class="fa fa-tag"></i> Tags:</span>
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag-pill"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Share -->
                    <div class="share-strip wow fadeInUp">
                        <span class="share-label">Share:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $pageUrl ?>" target="_blank" class="share-btn share-fb"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
                        <a href="https://twitter.com/intent/tweet?url=<?= $pageUrl ?>&text=<?= $pageTitle ?>" target="_blank" class="share-btn share-tw"><i class="fa-brands fa-x-twitter"></i> X</a>
                        <a href="https://wa.me/?text=<?= $pageTitle ?>%20<?= $pageUrl ?>" target="_blank" class="share-btn share-wa"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    </div>

                    <!-- Prev / Next -->
                    <div class="post-nav">
                        <?php if ($prevPost): ?>
                        <a href="blog-detail.php?slug=<?= e($prevPost["slug"]) ?>" class="post-nav-link prev">
                            <i class="fa fa-arrow-left" style="font-size:18px;flex-shrink:0"></i>
                            <div>
                                <div class="nav-label">Previous Post</div>
                                <div class="nav-title"><?= e($prevPost["title"]) ?></div>
                            </div>
                        </a>
                        <?php else: ?><div></div><?php endif; ?>

                        <div class="nav-center"><a href="blog.html">All Posts</a></div>

                        <?php if ($nextPost): ?>
                        <a href="blog-detail.php?slug=<?= e($nextPost["slug"]) ?>" class="post-nav-link next">
                            <div>
                                <div class="nav-label">Next Post</div>
                                <div class="nav-title"><?= e($nextPost["title"]) ?></div>
                            </div>
                            <i class="fa fa-arrow-right" style="font-size:18px;flex-shrink:0"></i>
                        </a>
                        <?php else: ?><div></div><?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="blog-sidebar">

                        <!-- Author Card -->
                        <div class="sidebar-card wow fadeInUp">
                            <h4>About the Author</h4>
                            <div class="author-avatar"><?= strtoupper(substr($post["author"] ?? "C", 0, 1)) ?></div>
                            <div class="author-name"><?= $author ?></div>
                            <div class="author-role" style="margin-top:4px;">Claricent Content Team</div>
                        </div>

                        <!-- Post Details -->
                        <div class="sidebar-card wow fadeInUp" data-wow-delay="0.15s">
                            <h4>Post Details</h4>
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <?php if ($category): ?>
                                <div style="display:flex;justify-content:space-between;font-size:14px;">
                                    <span style="color:#667282">Category</span>
                                    <span style="color:#2C3E50;font-weight:600"><?= $category ?></span>
                                </div>
                                <?php endif; ?>
                                <div style="display:flex;justify-content:space-between;font-size:14px;">
                                    <span style="color:#667282">Published</span>
                                    <span style="color:#2C3E50;font-weight:600"><?= $published ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:14px;">
                                    <span style="color:#667282">Read Time</span>
                                    <span style="color:#2C3E50;font-weight:600"><?= $readTime ?> min</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:14px;">
                                    <span style="color:#667282">Views</span>
                                    <span style="color:#2C3E50;font-weight:600"><?= (int)($post["views"] ?? 0) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Related Posts -->
                        <?php if ($related): ?>
                        <div class="sidebar-card wow fadeInUp" data-wow-delay="0.3s">
                            <h4>Related Posts</h4>
                            <?php foreach ($related as $r): ?>
                            <div class="related-item">
                                <?php if ($r["image_path"]): ?>
                                    <img src="<?= e($r["image_path"]) ?>" class="related-thumb" alt="<?= e($r["title"]) ?>">
                                <?php else: ?>
                                    <div class="related-thumb-placeholder"><i class="fa fa-newspaper"></i></div>
                                <?php endif; ?>
                                <div>
                                    <a href="blog-detail.php?slug=<?= e($r["slug"]) ?>" class="related-title"><?= e($r["title"]) ?></a>
                                    <div class="related-date"><i class="fa fa-calendar" style="font-size:11px;margin-right:4px"></i><?= $r["published_at"] ? formatDate($r["published_at"]) : "" ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- CTA -->
                        <div class="sidebar-card" style="background:linear-gradient(135deg,#2C3E50,#1a2d3f);border:none;">
                            <h4 style="color:#fff;border-color:rgba(255,255,255,0.3)">Have a Project?</h4>
                            <p style="color:rgba(255,255,255,0.7);font-size:14px;margin-bottom:16px;line-height:1.6">Let's build something great together. Contact us today.</p>
                            <a href="contact.html" class="btn-default" style="display:block;text-align:center">Get In Touch</a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-12">
                    <div class="about-footer">
                        <div class="footer-logo"><figure><img src="images/CLARICENT_COMPANY_LIMITED_FULL_LOGO.png" alt=""></figure></div>
                        <div class="footer-content"><p>Claricent Company Limited delivers innovative construction and engineering solutions that transform Ghana's built environment.</p></div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="footer-links"><h3>our services</h3>
                        <ul>
                            <li><a href="services.html#construction">Construction Services</a></li>
                            <li><a href="services.html#engineering">Engineering Solutions</a></li>
                            <li><a href="services.html#management">Project Management</a></li>
                            <li><a href="services.html#design">Design & Build / Planning</a></li>
                            <li><a href="services.html#maintenance">Construction Maintenance & Rehabilitation</a></li>
                            <li><a href="services.html#consultancy">Consultancy Services</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="footer-links"><h3>company</h3>
                        <ul>
                            <li><a href="about-us.html">About Us</a></li>
                            <li><a href="services.html">Services</a></li>
                            <li><a href="projects.html">Projects</a></li>
                            <li><a href="blog.html">Blog</a></li>
                            <li><a href="contact.html">Contact Us</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="footer-links footer-contact-box"><h3>contact us</h3>
                        <div class="footer-info-box"><div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-phone.svg" alt=""></div><p>+233 243368425,<br>+233 500153340,<br>+233 209128276</p></div>
                        <div class="footer-info-box"><div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-mail.svg" alt=""></div><p>info@claricentgroup.com</p></div>
                        <div class="footer-info-box"><div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-location.svg" alt=""></div><p>P.O. Box 1075, Cape Coast</p></div>
                    </div>
                </div>
            </div>
            <div class="footer-copyright">
                <div class="row align-items-center">
                    <div class="col-lg-6 col-md-7"><div class="footer-copyright-text"><p>Copyright &copy; 2026 Basitech Solutions. All Rights Reserved.</p></div></div>
                    <div class="col-lg-6 col-md-5">
                        <div class="footer-social-links"><ul>
                            <li><a href="https://www.instagram.com/jeriscot/"><i class="fa-brands fa-instagram"></i></a></li>
                            <li><a href="https://www.facebook.com/jeriscot/"><i class="fa-brands fa-facebook-f"></i></a></li>
                            <li><a href="https://x.com/EngrQuay"><i class="fa-brands fa-x-twitter"></i></a></li>
                            <li><a href="https://www.linkedin.com/in/jeriscot-claricent-7090773a9/"><i class="fa-brands fa-linkedin-in"></i></a></li>
                        </ul></div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/slicknav@1.0.10/dist/jquery.slicknav.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/parallaxie@0.5.0/parallaxie.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
    <script src="js/gsap-splittext.min.js"></script>
    <script src="js/function.js"></script>
</body>
</html>