<?php

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$scriptPath   = $_SERVER["SCRIPT_NAME"] ?? "";
$appBase      = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $scriptPath);
if ($appBase === "/" || $appBase === "\\") {
    $appBase = "";
}
$verifBase    = $appBase . "/verification";

?>

<style>

/* =========================================================
   VERIFICATION SIDEBAR
========================================================= */

.sidebar {
    width: 310px;
    min-width: 310px;
    background: #ffffff;
    border-right: 1px solid #d9dee3;
    min-height: calc(100vh - 72px);
    display: flex;
    flex-direction: column;
}


/* =========================================================
   BRAND
========================================================= */

.sidebar-brand {
    padding: 28px 30px;
    border-bottom: 1px solid #e5e5e5;
    background: #ffffff;
}

.brand-icon {
    display: none;
}

.brand-text h2 {
    margin: 0 0 6px;
    color: #1f4e79;
    font-size: 28px;
    font-weight: 600;
}

.brand-text span {
    display: block;
    color: #666;
    font-size: 14px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.sidebar-nav {
    flex: 1;
}


.nav-section {
    padding: 18px 30px 9px;
}


.nav-section-title {
    color: #777;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.6px;
}


/* =========================================================
   NAV ITEM
========================================================= */

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;

    min-height: 54px;

    padding: 0 30px;

    color: #111;

    text-decoration: none;

    font-size: 16px;

    border-bottom: 1px solid #eeeeee;

    transition:
        background 0.15s ease,
        color 0.15s ease;
}


.nav-item:hover {
    background: #f3f7fa;
    color: #1f4e79;
}


.nav-item.active {
    background: #e7f0f7;
    color: #1f4e79;
    font-weight: 700;
    border-left: 4px solid #1f4e79;
    padding-left: 26px;
}


.nav-icon {
    width: 18px;
    text-align: center;
    color: inherit;
}


/* =========================================================
   FOOTER
========================================================= */

.sidebar-footer {
    margin-top: auto;
}


.sidebar-divider {
    height: 1px;
    background: #e5e5e5;
}


.sidebar-footer .nav-item {
    border-bottom: none;
}

.sidebar-footer .logout-item {
    color: #c0392b;
    font-weight: 700;
}

.sidebar-footer .logout-item:hover {
    background: #fdecea;
    color: #a12622;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {
        width: 250px;
        min-width: 250px;
    }

    .sidebar-brand {
        padding: 22px 20px;
    }

    .nav-item {
        padding-left: 20px;
        padding-right: 20px;
    }

    .nav-item.active {
        padding-left: 16px;
    }

}

</style>


<aside class="sidebar">


    <!-- =====================================================
         BRAND
    ====================================================== -->

    <div class="sidebar-brand">

        <div class="brand-text">

            <h2>
                Marks Verification
            </h2>

            <span>
                FTC Grading & Tabulation
            </span>

        </div>

    </div>


    <!-- =====================================================
         NAVIGATION
    ====================================================== -->

    <nav class="sidebar-nav">


        <!-- =================================================
             SYSTEM
        ================================================== -->

        <div class="nav-section">

            <span class="nav-section-title">
                SYSTEM
            </span>

        </div>


        <!-- =================================================
             MARKS VERIFICATION
        ================================================== -->

        <div class="nav-section">

            <span class="nav-section-title">
                MARKS VERIFICATION
            </span>

        </div>


        <!-- VERIFICATION DASHBOARD -->

        <a
            href="<?php echo $verifBase; ?>/dashboard.php"
            class="nav-item
            <?php

            echo (
                $current_dir === "verification"
                &&
                $current_page === "dashboard.php"
            )
                ? "active"
                : "";

            ?>"
        >

            <span class="nav-icon">
                ▣
            </span>

            <span>
                Verification Dashboard
            </span>

        </a>


        <!-- MARKS REVIEW -->

        <a
            href="<?php echo $verifBase; ?>/marks.php"
            class="nav-item
            <?php

            echo (
                $current_dir === "verification"
                &&
                $current_page === "marks.php"
            )
                ? "active"
                : "";

            ?>"
        >

            <span class="nav-icon">
                ▤
            </span>

            <span>
                Marks Review
            </span>

        </a>

        <a
            href="<?php echo htmlspecialchars($appBase . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>"
            class="nav-item logout-item"
        >
            <span class="nav-icon">&#10132;</span>
            <span>Logout</span>
        </a>


    </nav>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

</aside>