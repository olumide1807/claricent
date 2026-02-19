<?php
/**
 * CLARICENT — project-detail.php
 * Dynamic project page. URL: project-detail.php?slug=aspen-heights
 *
 * Place this file in your WEBSITE ROOT (same level as projects.html).
 * It shares the same DB config as admin/api.php — edit the CONFIG block below.
 */

/* ─── CONFIG (must match admin/api.php) ───────────────────── */
define("DB_HOST",    "localhost");
define("DB_NAME",    "claricen_db");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");

/* ─── DB ──────────────────────────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/* ─── FETCH PROJECT ───────────────────────────────────────── */
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET["slug"] ?? "")));

if (!$slug) {
    header("Location: projects.html");
    exit;
}

try {
    $stmt = db()->prepare("SELECT * FROM projects WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $project = $stmt->fetch();
} catch (PDOException $e) {
    $project = null;
}

if (!$project) {
    header("HTTP/1.0 404 Not Found");
    // Graceful 404 — redirect back to projects page
    header("Location: projects.html");
    exit;
}

// Fetch prev/next projects for navigation
try {
    $prev = db()->prepare("SELECT name, slug FROM projects WHERE sort_order < ? OR (sort_order = ? AND id < ?) ORDER BY sort_order DESC, id DESC LIMIT 1");
    $prev->execute([$project["sort_order"], $project["sort_order"], $project["id"]]);
    $prevProject = $prev->fetch();

    $next = db()->prepare("SELECT name, slug FROM projects WHERE sort_order > ? OR (sort_order = ? AND id > ?) ORDER BY sort_order ASC, id ASC LIMIT 1");
    $next->execute([$project["sort_order"], $project["sort_order"], $project["id"]]);
    $nextProject = $next->fetch();
} catch (PDOException $e) {
    $prevProject = $nextProject = null;
}

/* ─── HELPERS ─────────────────────────────────────────────── */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, "UTF-8");
}

function statusLabel(string $s): string {
    return match($s) {
        "completed" => "Completed",
        "ongoing"   => "Ongoing",
        "upcoming"  => "Upcoming",
        default     => ucfirst($s)
    };
}

function statusColor(string $s): string {
    return match($s) {
        "completed" => "#4cbb7a",
        "ongoing"   => "#c9a84c",
        "upcoming"  => "#e8a838",
        default     => "#9a9da8"
    };
}

$title       = e($project["name"]);
$description = e($project["description"]);
$category    = e(ucfirst($project["category"] ?? ""));
$status      = $project["status"] ?? "upcoming";
$year        = $project["year"] ? e((string)$project["year"]) : null;
$location    = $project["location"] ? e($project["location"]) : null;
$imageSrc    = $project["image_path"] ? e($project["image_path"]) : null;
$statusLbl   = statusLabel($status);
$statusClr   = statusColor($status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $description ?>">
    <meta name="keywords" content="construction, Ghana, <?= $title ?>, Claricent">
    <title><?= $title ?> — Claricent Company Limited</title>

    <!-- Open Graph for sharing -->
    <meta property="og:title"       content="<?= $title ?> — Claricent">
    <meta property="og:description" content="<?= $description ?>">
    <?php if ($imageSrc): ?>
    <meta property="og:image"       content="<?= $imageSrc ?>">
    <?php endif; ?>

    <link rel="shortcut icon" type="image/x-icon" href="images/CLARICENT_COMPANY_LIMITED_LOADER.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slicknav@1.0.10/dist/slicknav.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css">
    <link rel="stylesheet" href="css/mousecursor.css">
    <link href="css/custom.css" rel="stylesheet">

    <style>
        /* ── Project Detail Page Styles ── */
        .project-detail-section { padding: 80px 0; }

        .project-hero-image {
            width: 100%; height: 480px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .project-hero-placeholder {
            width: 100%; height: 320px;
            background: linear-gradient(135deg, #2C3E50 0%, #1E90FF22 100%);
            border-radius: 12px;
            margin-bottom: 40px;
            display: flex; align-items: center; justify-content: center;
            color: #667282; font-size: 48px;
        }

        .project-meta-card {
            background: #f8fafc;
            border: 1px solid #e8edf2;
            border-radius: 12px;
            padding: 32px;
            position: sticky;
            top: 100px;
        }

        .project-meta-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #e8edf2;
        }
        .project-meta-item:last-child { border-bottom: none; padding-bottom: 0; }
        .project-meta-item:first-child { padding-top: 0; }

        .meta-icon {
            width: 40px; height: 40px;
            background: #2C3E5010;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #1E90FF; font-size: 16px;
            flex-shrink: 0;
        }

        .meta-label {
            font-size: 12px; font-weight: 600;
            color: #667282; text-transform: uppercase;
            letter-spacing: 0.08em; margin-bottom: 3px;
        }
        .meta-value {
            font-size: 15px; font-weight: 600;
            color: #2C3E50; font-family: 'Manrope', sans-serif;
        }

        .status-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            background: <?= $statusClr ?>;
        }

        .project-title-main {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(28px, 5vw, 48px);
            font-weight: 800;
            color: #2C3E50;
            line-height: 1.15;
            margin-bottom: 20px;
            text-transform: capitalize;
        }

        .project-description {
            font-size: 17px;
            line-height: 1.8;
            color: #667282;
            margin-bottom: 32px;
        }

        /* ── Project Nav ── */
        .project-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 32px 0;
            border-top: 1px solid #e8edf2;
            margin-top: 48px;
            gap: 16px;
        }

        .proj-nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            border: 1px solid #e8edf2;
            border-radius: 10px;
            text-decoration: none;
            color: #2C3E50;
            transition: all 0.2s;
            max-width: 45%;
            font-family: 'Manrope', sans-serif;
        }
        .proj-nav-link:hover {
            border-color: #1E90FF;
            color: #1E90FF;
            background: #1E90FF08;
            transform: translateY(-2px);
        }
        .proj-nav-link.next { flex-direction: row-reverse; text-align: right; }

        .proj-nav-label { font-size: 11px; color: #667282; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 3px; }
        .proj-nav-name  { font-size: 14px; font-weight: 700; line-height: 1.3; }
        .proj-nav-icon  { font-size: 18px; flex-shrink: 0; }

        .proj-nav-center { color: #667282; font-size: 13px; }
        .proj-nav-center a { color: #1E90FF; font-weight: 600; }

        /* ── Share buttons ── */
        .share-strip {
            display: flex; align-items: center; gap: 12px;
            margin-top: 32px; flex-wrap: wrap;
        }
        .share-label { font-size: 13px; color: #667282; font-weight: 600; }
        .share-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px; font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .share-fb    { background: #1877f210; color: #1877f2; border: 1px solid #1877f230; }
        .share-tw    { background: #00000010; color: #000;    border: 1px solid #00000020; }
        .share-wa    { background: #25d36610; color: #25d366; border: 1px solid #25d36630; }
        .share-btn:hover { transform: translateY(-1px); opacity: 0.85; }
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

    <!-- Header Start -->
    <header class="main-header">
        <div class="header-sticky active-sticky-header">
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <a class="navbar-brand" href="./">
                        <img src="images/CLARICENT_COMPANY_LIMITED_FULL_LOGO.png" alt="Logo">
                    </a>
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
    <!-- Header End -->

    <!-- Page Header -->
    <div class="page-header parallaxie">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="page-header-box">
                        <h1 class="text-anime-style-3" data-cursor="-opaque"><?= $title ?></h1>
                        <nav class="wow fadeInUp">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="./">Home</a></li>
                                <li class="breadcrumb-item"><a href="projects.html">Projects</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?= $title ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Detail Section -->
    <section class="project-detail-section">
        <div class="container">
            <div class="row">

                <!-- Main Content -->
                <div class="col-lg-8">
                    <?php if ($imageSrc): ?>
                        <img src="<?= $imageSrc ?>" alt="<?= $title ?>" class="project-hero-image wow fadeInUp">
                    <?php else: ?>
                        <div class="project-hero-placeholder wow fadeInUp">
                            <i class="fa fa-hard-hat"></i>
                        </div>
                    <?php endif; ?>

                    <h1 class="project-title-main wow fadeInUp"><?= $title ?></h1>

                    <p class="project-description wow fadeInUp"><?= nl2br($description) ?></p>

                    <!-- Share Strip -->
                    <div class="share-strip wow fadeInUp">
                        <span class="share-label">Share:</span>
                        <?php
                            $pageUrl   = urlencode("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                            $pageTitle = urlencode($project["name"] . " — Claricent Company Limited");
                        ?>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $pageUrl ?>" target="_blank" class="share-btn share-fb">
                            <i class="fa-brands fa-facebook-f"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= $pageUrl ?>&text=<?= $pageTitle ?>" target="_blank" class="share-btn share-tw">
                            <i class="fa-brands fa-x-twitter"></i> X
                        </a>
                        <a href="https://wa.me/?text=<?= $pageTitle ?>%20<?= $pageUrl ?>" target="_blank" class="share-btn share-wa">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                    </div>

                    <!-- Prev / Next Navigation -->
                    <div class="project-nav">
                        <?php if ($prevProject): ?>
                        <a href="project-detail.php?slug=<?= e($prevProject["slug"]) ?>" class="proj-nav-link prev">
                            <i class="fa fa-arrow-left proj-nav-icon"></i>
                            <div>
                                <div class="proj-nav-label">Previous Project</div>
                                <div class="proj-nav-name"><?= e($prevProject["name"]) ?></div>
                            </div>
                        </a>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>

                        <div class="proj-nav-center">
                            <a href="projects.html">All Projects</a>
                        </div>

                        <?php if ($nextProject): ?>
                        <a href="project-detail.php?slug=<?= e($nextProject["slug"]) ?>" class="proj-nav-link next">
                            <div>
                                <div class="proj-nav-label">Next Project</div>
                                <div class="proj-nav-name"><?= e($nextProject["name"]) ?></div>
                            </div>
                            <i class="fa fa-arrow-right proj-nav-icon"></i>
                        </a>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Meta Card -->
                <div class="col-lg-4">
                    <div class="project-meta-card wow fadeInUp" data-wow-delay="0.3s">
                        <h3 style="font-family:'Manrope',sans-serif;font-size:18px;font-weight:800;color:#2C3E50;margin-bottom:20px;">
                            Project Details
                        </h3>

                        <div class="project-meta-item">
                            <div class="meta-icon"><i class="fa fa-building"></i></div>
                            <div>
                                <div class="meta-label">Project Name</div>
                                <div class="meta-value"><?= $title ?></div>
                            </div>
                        </div>

                        <?php if ($category): ?>
                        <div class="project-meta-item">
                            <div class="meta-icon"><i class="fa fa-tag"></i></div>
                            <div>
                                <div class="meta-label">Category</div>
                                <div class="meta-value"><?= $category ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="project-meta-item">
                            <div class="meta-icon"><i class="fa fa-circle-check"></i></div>
                            <div>
                                <div class="meta-label">Status</div>
                                <div class="meta-value">
                                    <span class="status-dot"></span><?= e($statusLbl) ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($year): ?>
                        <div class="project-meta-item">
                            <div class="meta-icon"><i class="fa fa-calendar"></i></div>
                            <div>
                                <div class="meta-label">Year</div>
                                <div class="meta-value"><?= $year ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($location): ?>
                        <div class="project-meta-item">
                            <div class="meta-icon"><i class="fa fa-map-marker-alt"></i></div>
                            <div>
                                <div class="meta-label">Location</div>
                                <div class="meta-value"><?= $location ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top:24px;">
                            <a href="contact.html" class="btn-default" style="display:block;text-align:center;">
                                <i class="fa fa-envelope"></i> Enquire About This Project
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer Start -->
    <footer class="main-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-12">
                    <div class="about-footer">
                        <div class="footer-logo">
                            <figure><img src="images/CLARICENT_COMPANY_LIMITED_FULL_LOGO.png" alt=""></figure>
                        </div>
                        <div class="footer-content">
                            <p>Claricent Company Limited delivers innovative construction and engineering solutions that transform Ghana's built environment.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="footer-links">
                        <h3>our services</h3>
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
                    <div class="footer-links">
                        <h3>company</h3>
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
                    <div class="footer-links footer-contact-box">
                        <h3>contact us</h3>
                        <div class="footer-info-box">
                            <div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-phone.svg" alt=""></div>
                            <p>+233 243368425, <br>+233 500153340, <br>+233 209128276</p>
                        </div>
                        <div class="footer-info-box">
                            <div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-mail.svg" alt=""></div>
                            <p>info@claricentgroup.com</p>
                        </div>
                        <div class="footer-info-box">
                            <div class="icon-box"><img src="https://html.awaikenthemes.com/builtup/images/icon-location.svg" alt=""></div>
                            <p>P.O. Box 1075, Cape Coast</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-copyright">
                <div class="row align-items-center">
                    <div class="col-lg-6 col-md-7">
                        <div class="footer-copyright-text">
                            <p>Copyright &copy; 2026 Basitech Solutions. All Rights Reserved.</p>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-5">
                        <div class="footer-social-links">
                            <ul>
                                <li><a href="https://www.instagram.com/jeriscot/"><i class="fa-brands fa-instagram"></i></a></li>
                                <li><a href="https://www.facebook.com/jeriscot/"><i class="fa-brands fa-facebook-f"></i></a></li>
                                <li><a href="https://x.com/EngrQuay"><i class="fa-brands fa-x-twitter"></i></a></li>
                                <li><a href="https://www.linkedin.com/in/jeriscot-claricent-7090773a9/"><i class="fa-brands fa-linkedin-in"></i></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- Footer End -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/slicknav@1.0.10/dist/jquery.slicknav.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/waypoints/4.0.1/jquery.waypoints.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Counter-Up/1.0.0/jquery.counterup.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/parallaxie@0.5.0/parallaxie.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
    <script src="js/gsap-splittext.min.js"></script>
    <script src="js/function.js"></script>
</body>
</html>