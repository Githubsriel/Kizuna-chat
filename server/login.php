<?php
session_start();
include 'main.php';

// Already logged in? Redirect to home.
if (isset($_SESSION['loggedin'])) {
    header('Location: ../index');
    exit;
}

// Check for "remember me" cookie.
if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme'])) {
    $stmt = $con->prepare('SELECT id, username, role, banned FROM accounts WHERE rememberme = ?');
    $stmt->bind_param('s', $_COOKIE['rememberme']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $role, $banned);
        $stmt->fetch();
        $stmt->close();
        if ($banned == 1) {
            die("Your account has been banned. Please contact the administrator.");
        }
        session_regenerate_id();
        $_SESSION['loggedin'] = TRUE;
        $_SESSION['name'] = $username;
        $_SESSION['id'] = $id;
        $_SESSION['role'] = $role;
        header('Location: ../index');
        exit;
    }
    $stmt->close();
}

// Process login on POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $con->prepare("SELECT id, username, password, role, banned FROM accounts WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_username, $db_password, $role, $banned);
        $stmt->fetch();
        if ($banned == 1) {
            echo "Your account has been banned. Please contact the administrator.";
            exit;
        }
        if (password_verify($password, $db_password)) {
            session_regenerate_id();
            $_SESSION['loggedin'] = TRUE;
            $_SESSION['name'] = $db_username;
            $_SESSION['id'] = $id;
            $_SESSION['role'] = $role;
            // If "Remember me" is selected:
            if (isset($_POST['rememberme'])) {
                $token = bin2hex(random_bytes(16));
                setcookie("rememberme", $token, time() + (86400 * 30), "/");
                $stmt_update = $con->prepare("UPDATE accounts SET rememberme = ? WHERE id = ?");
                $stmt_update->bind_param("si", $token, $id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            echo "success";
            exit;
        } else {
            echo "Invalid credentials.";
            exit;
        }
    } else {
        echo "No account found with that username.";
        exit;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-M9LMV364');</script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Kizuna Chat - Login</title>

    <!-- SEO & Open Graph / Twitter -->
    <meta name="description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, ...">
    <meta name="keywords" content="Anime Chat, Grafischer Chat, Anime Community, Manga...">
    <meta name="author" content="Kizuna Chat">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kizuna-chat.de/">
    <meta property="og:title" content="Kizuna Chat - Grafischer Anime Chat">
    <meta property="og:description" content="Verbinde dich mit anderen Anime-Enthusiasten ...">
    <meta property="og:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Kizuna Chat - Grafischer Anime Chat">
    <meta property="twitter:description" content="Verbinde dich mit anderen Anime-Enthusiasten ...">
    <meta property="twitter:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- Include your main CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">


</head>
<body>

<!-- Google Tag Manager (noscript) -->
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-M9LMV364"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->

<!-- Navigation Bar -->
<div class="navtop">
    <div class="wrapper">
        <h1><img src="img/chinochat.png" alt="Chinochat Icon" class="nav-icon">Kizuna Chat</h1>
        <a href="registrieren">registrieren</a>
    </div>
</div>

<!-- Main container resembling hero section -->
<div class="login-hero-container">
    <h2>Willkommen zurück!</h2>
    <p>Logge dich jetzt ein, um wieder in den Kizuna Chat einzutauchen und deine Anime-Leidenschaft zu teilen.</p>

    <!-- Login Form Panel -->
    <div class="login">
        <h1>Login</h1>
        <div class="links">
            <a href="login.php" class="active">Login</a>
            <a href="registrieren">Registrieren</a>
        </div>
        <!-- The form posts to the same file -->
        <form action="" method="post">
            <label for="username"><i class="fas fa-user"></i></label>
            <input type="text" name="username" placeholder="Username" id="username" required>

            <label for="password"><i class="fas fa-lock"></i></label>
            <input type="password" name="password" placeholder="Password" id="password" required>

            <label id="rememberme">
                <input type="checkbox" name="rememberme"> Remember me
            </label>
            <div class="msg"></div>

            <input type="submit" value="Login">
        </form>
    </div>
</div>

<!-- Alpha Warning Popup -->
<div id="alpha-warning-popup" class="alpha-popup hidden">
    <div class="alpha-popup-content">
        <p>
            Willkommen! Bitte beachte, dass sich diese Webseite derzeit in einer frühen
            <strong>Alpha-Entwicklungsphase</strong> befindet.
        </p>
        <p>
            Viele Funktionen sind möglicherweise unvollständig, enthalten Fehler oder funktionieren nicht wie erwartet.
            Wir bitten um dein Verständnis und dein Feedback, während wir die Seite weiterentwickeln!
        </p>
        <button id="accept-alpha-warning">Verstanden, Fortfahren</button>
    </div>
</div>

<script>
    // AJAX form submission for login
    document.querySelector(".login form").onsubmit = function(event) {
        event.preventDefault();
        var form_data = new FormData(document.querySelector(".login form"));
        var xhr = new XMLHttpRequest();
        xhr.open("POST", document.querySelector(".login form").action, true);
        xhr.onload = function () {
            if (this.responseText.toLowerCase().indexOf("success") !== -1) {
                window.location.href = "../index.php";
            } else {
                document.querySelector(".msg").innerHTML = this.responseText;
            }
        };
        xhr.send(form_data);
    };

    // Alpha popup logic
    document.addEventListener("DOMContentLoaded", function () {
        const popup = document.getElementById("alpha-warning-popup");
        const acceptButton = document.getElementById("accept-alpha-warning");
        const cookieName = "alphaWarningAccepted";
        const cookieValue = "true";
        const daysToExpire = 365;

        function setCookie(name, value, days) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        }
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(";");
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === " ") c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
            return null;
        }
        if (getCookie(cookieName) !== cookieValue) {
            popup.classList.remove("hidden");
        }
        acceptButton.addEventListener("click", function () {
            popup.classList.add("hidden");
            setCookie(cookieName, cookieValue, daysToExpire);
        });
    });
</script>

</body>
</html>
