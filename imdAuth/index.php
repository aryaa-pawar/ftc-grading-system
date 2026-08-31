<?php
// index.php - Official Landing Page for IMD Research & Guide Portal

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect logged-in users directly to their role-specific dashboard
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role_name'] ?? '';
    if ($role === 'guide') header('Location: ' . baseUrl('teacher/dashboard.php'));
    else header('Location: ' . baseUrl('admin/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMD Research & Guide Portal — India Meteorological Department</title>
    <!-- Meta Description for SEO -->
    <meta name="description" content="Official Training Division Portal of India Meteorological Department (IMD) for Research Supervisors and Administrative Officers.">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
    <style>
        .official-masthead {
            background: linear-gradient(180deg, #3b8bc5 0%, #3a86bd 35%, #286b9d 72%, #0a416b 100%);
            color: #fff;
            border: 0;
        }

        .official-masthead-inner {
            max-width: 1440px;
            margin: 0 auto;
            min-height: 125px;
            padding: 2px 28px;
            display: grid;
            grid-template-columns: 390px minmax(0, 1fr) 190px;
            align-items: center;
            gap: 20px;
        }

        .masthead-brand,
        .masthead-social a,
        .masthead-languages a {
            color: #fff;
            text-decoration: none;
        }

        .masthead-brand {
            display: flex;
            align-items: center;
            gap: 6px;
            transform: translateX(-18px);
        }

        .masthead-brand img {
            width: 104px;
            height: 112px;
            object-fit: contain;
        }

        .masthead-brand .emblem {
            width: 94px;
            object-position: right center;
            transform: translateX(-12px);
            margin-right: -12px;
        }

        .masthead-brand .esso {
            width: 116px;
        }

        .masthead-brand .anniversary {
            width: 136px;
        }

        .masthead-copy {
            text-align: center;
            font-size: clamp(12px, 1.35vw, 20px);
            line-height: 1.45;
            white-space: nowrap;
            transform: translateX(-72px);
        }

        .masthead-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            text-align: left;
        }

        .masthead-line .separator {
            opacity: 0.9;
        }

        .masthead-line .hindi {
            font-weight: 600;
            text-align: right;
        }

        .masthead-copy .hindi {
            font-weight: 600;
        }

        .masthead-social {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            font-size: 25px;
        }

        .masthead-social .x-icon {
            width: 25px;
            height: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            font-family: Arial, sans-serif;
        }

        .masthead-right {
            display: flex;
            height: 100%;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
        }

        .masthead-right .masthead-social {
            justify-content: flex-end;
        }

        .masthead-right img {
            width: 112px;
            height: 110px;
            object-fit: contain;
        }

        .masthead-languages {
            display: flex;
            gap: 15px;
            font-size: 15px;
        }

        .masthead-actions {
            position: relative;
            background: linear-gradient(180deg, #3b8bc5 0%, #3a86bd 35%, #286b9d 72%, #0a416b 100%);
            border: 0;
            padding: 4px 0 8px;
        }

        .masthead-actions-inner {
            max-width: 1440px;
            margin: 0 auto;
            padding: 4px 28px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .masthead-actions .btn {
            box-shadow: 0 3px 8px rgba(0, 32, 64, .2);
        }

        body {
            background: #eef3f7 !important;
            color: #183247;
        }

        .portal-hero {
            background: #0a416b !important;
            border-top: 0;
            border-bottom: 5px solid #0c4774;
        }

        .portal-hero .container {
            padding-top: 40px !important;
            padding-bottom: 48px !important;
        }

        .portal-hero .badge {
            background: #f4b41a !important;
            border-radius: 3px !important;
            letter-spacing: .2px;
        }

        .portal-hero h1 {
            max-width: 760px;
            font-size: clamp(2rem, 3.8vw, 3.35rem) !important;
            line-height: 1.05;
            letter-spacing: 0;
        }

        .hero-break {
            display: inline;
        }

        .portal-hero .lead {
            max-width: 680px;
            color: #d9e8f3 !important;
        }

        .portal-access {
            border: 3px solid #f4b41a !important;
            border-radius: 6px !important;
            box-shadow: 0 14px 30px rgba(0, 24, 48, .22) !important;
            transform: translateY(-10px);
        }

        .portal-access h4 {
            color: #063862 !important;
        }

        .portal-access .btn-imd,
        .btn-imd {
            background: #0c4774;
            border-color: #0c4774;
            color: #fff;
        }

        .portal-access .btn-imd:hover,
        .btn-imd:hover {
            background: #063862;
            border-color: #063862;
            color: #fff;
        }

        .portal-actions-register {
            margin-left: auto;
        }

        .portal-main {
            max-width: 1180px;
        }

        .portal-main > .text-center {
            border-bottom: 1px solid #ccd9e2;
            padding-bottom: 24px;
        }

        .portal-main h2,
        .portal-main h4,
        .portal-main h5 {
            color: #0c4774 !important;
        }

        .portal-tile {
            border: 1px solid #d3e0e8 !important;
            border-top: 4px solid #3b8bc5 !important;
            border-radius: 5px !important;
            box-shadow: 0 5px 14px rgba(13, 52, 79, .08) !important;
        }

        .portal-tile:hover {
            transform: translateY(-3px);
            border-top-color: #f4b41a !important;
            box-shadow: 0 10px 20px rgba(13, 52, 79, .13) !important;
        }

        .portal-tile .rounded-circle {
            border-radius: 4px !important;
            background: #e4f0f8 !important;
            color: #0c4774 !important;
        }

        .portal-guide {
            border: 1px solid #d3e0e8 !important;
            border-left: 5px solid #f4b41a !important;
            border-radius: 5px !important;
        }

        .portal-footer {
            background: #123e62;
            border-top: 5px solid #f4b41a;
        }

        .portal-footer h6 {
            color: #f4b41a !important;
        }

        .portal-footer .copyright {
            color: #ffffff;
        }

        @media (max-width: 800px) {
            .official-masthead-inner {
                overflow: hidden;
            }

            .portal-hero .container {
                padding-top: 38px !important;
                padding-bottom: 38px !important;
            }

            .portal-access {
                transform: none;
            }

            .hero-break {
                display: none;
            }
        }

        @media (max-width: 800px) {
            .official-masthead-inner {
                grid-template-columns: 1fr 1fr;
                min-height: 0;
                padding: 5px 16px;
            }

            .masthead-brand {
                grid-column: 1 / -1;
                justify-content: center;
                transform: none;
            }

            .masthead-brand img {
                height: 100px;
            }

            .masthead-brand .emblem {
                width: 64px;
                transform: none;
                margin-right: 0;
            }

            .masthead-brand .esso {
                width: 96px;
            }

            .masthead-brand .anniversary {
                width: 110px;
            }

            .masthead-copy {
                grid-column: 1 / -1;
                grid-row: 2;
                white-space: normal;
                font-size: 12px;
                transform: none;
            }

            .masthead-line {
                grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                gap: 4px 8px;
                font-size: 11px;
            }

            .masthead-social {
                justify-content: flex-start;
            }

            .masthead-right {
                align-items: flex-end;
            }

            .masthead-right img {
                width: 72px;
                height: 70px;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

    <!-- Recreated official IMD masthead with clickable regions -->
    <header class="official-masthead">
        <div class="official-masthead-inner">
            <a href="<?= baseUrl() ?>" class="masthead-brand" aria-label="IMD Training Division Portal home">
                <img class="emblem" src="<?= baseUrl('national_emblem.png') ?>" alt="National emblem of India">
                <img class="esso" src="<?= baseUrl('esso.png') ?>?v=<?= filemtime(__DIR__ . '/../esso.png') ?>" alt="ESSO logo">
                <img class="anniversary" src="<?= baseUrl('150yrs.png') ?>" alt="150 years anniversary logo">
            </a>
            <div class="masthead-copy">
                <div class="masthead-line">
                    <span class="hindi">जलवायु अनुसंधान एवं सेवाएं, पुणे</span>
                    <span class="separator">|</span>
                    <span>CLIMATE RESEARCH &amp; SERVICES, PUNE</span>
                </div>
                <div class="masthead-line">
                    <span class="hindi">भारत मौसम विज्ञान विभाग</span>
                    <span class="separator">|</span>
                    <span>India Meteorological Department</span>
                </div>
                <div class="masthead-line">
                    <span class="hindi">पृथ्वी विज्ञान मंत्रालय</span>
                    <span class="separator">|</span>
                    <span>Ministry of Earth Sciences</span>
                </div>
                <div class="masthead-line">
                    <span class="hindi">भारत सरकार</span>
                    <span class="separator">|</span>
                    <span>Government of India</span>
                </div>
            </div>
            <div class="masthead-right">
                <div class="masthead-social">
                    <a href="https://www.facebook.com/IMDPune/" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                    <a href="https://twitter.com/climateimd" target="_blank" rel="noopener" aria-label="X"><span class="x-icon" aria-hidden="true">X</span></a>
                    <a href="https://www.youtube.com/channel/UCgBa7vNdcw4q_mjW3TPOfmA/videos" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <img src="<?= baseUrl('IMD Logo.png') ?>" alt="India Meteorological Department emblem">
               
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="portal-hero text-white py-5 position-relative overflow-hidden">
        <div class="container py-4 position-relative z-1">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <span class="badge bg-warning text-dark mb-3 px-3 py-2 fs-6 fw-bold">Training &amp; Evaluation Division</span>
                    <h1 class="display-4 fw-extrabold mb-3">Trainee Evaluation &amp; Performance Management</h1>
                    <p class="lead mb-4 text-light opacity-90">
                        The centralized portal for managing trainee assessments, internal marks, scrutiny, evaluation, and academic results across IMD training programmes.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= baseUrl('register.php') ?>" class="btn btn-warning btn-lg fw-bold px-4 py-3 shadow">
                            <i class="fa-solid fa-user-tie me-2"></i> Register as Evaluator
                        </a>
                        <a href="<?= baseUrl('login.php') ?>" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fa-solid fa-lock me-2"></i> Secure Login
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 mt-5 mt-lg-0 text-center">
                    <div class="portal-access bg-white text-dark rounded-4 p-4 shadow-lg border border-4 border-warning">
                        <div class="text-imd-navy mb-3">
                            <i class="fa-solid fa-shield-halved fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-imd-navy mb-2">Authorized Access Only</h4>
                        <p class="text-muted small">
                            Faculty evaluators, scrutiny officers, and administrators must authenticate to securely access the trainee evaluation system.
                        </p>
                        <div class="d-grid gap-2 mt-4">
                            <a href="<?= baseUrl('login.php') ?>" class="btn btn-imd py-2 fw-semibold">Secure Login</a>
                            <a href="<?= baseUrl('register.php') ?>" class="btn btn-light border py-2 text-muted small">Register as Evaluator</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Government Footer -->
    <footer class="portal-footer mt-auto">
        <div class="container py-4">
            <div class="row g-4">
                <div class="col-12">
                    <h6 class="text-uppercase fw-bold text-warning mb-2">India Meteorological Department</h6>
                    <p class="small text-light opacity-75 mb-0">
                        Ministry of Earth Sciences, Government of India.<br>
                        Meteorological Department Bldg, Near Pashan Goan, Dr Homi Bhabha Road,<br>
                        Pashan, Pune-411021,<br>
                        Maharashtra.
                    </p>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <div class="copyright text-center small">
                <strong>&copy; 2026 India Meteorological Department (IMD). All Rights Reserved.</strong><br>
                <strong>Training &amp; Evaluation Portal</strong> |
                <strong>Disclaimer</strong> |
                <strong>Designed, Developed by Students of Vishwakarma Institute of Technology, Pune</strong>
                <div class="mt-2">
                    <span class="text-uppercase fw-bold text-warning small me-2">Built by:</span>
                    <a href="https://www.linkedin.com/in/arya-pawar-34237a326/" target="_blank" rel="noopener noreferrer" class="text-reset text-decoration-none me-2">Arya Pawar</a>
                    <span class="text-secondary me-2">|</span>
                    <a href="https://www.linkedin.com/in/anushka-patil-42585832a/" target="_blank" rel="noopener noreferrer" class="text-reset text-decoration-none me-2">Anushka Patil</a>
                    <span class="text-secondary me-2">|</span>
                    <a href="https://www.linkedin.com/in/samarth-bidkar-24nov2005/" target="_blank" rel="noopener noreferrer" class="text-reset text-decoration-none me-2">Samarth Bidkar</a>
                    <span class="text-secondary me-2">|</span>
                    <a href="https://www.linkedin.com/in/nidhish-chincholkar-881821343/" target="_blank" rel="noopener noreferrer" class="text-reset text-decoration-none">Nidhish Chincholkar</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
