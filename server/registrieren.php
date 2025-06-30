<!DOCTYPE html>
<html>
	<head>
	
	<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-M9LMV364');</script>
<!-- End Google Tag Manager -->


	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kizuna Chat - Registration</title>
    <meta name="description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta name="keywords" content="Anime Chat, Grafischer Chat, Anime Community, Manga, Anime Fans, Online Chat, Sozial, Kizuna Chat">
    <meta name="author" content="Kizuna Chat">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kizuna-chat.de/">
    <meta property="og:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="og:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="og:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://kizuna-chat.de/">
    <meta property="twitter:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="twitter:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="twitter:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">">

    <meta name="robots" content="index, follow">

    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <title>Kizuna Chat - Login</title>
		<link href="style.css" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	</head>
	<body>


<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-M9LMV364"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->


		<!-- Site Navigation (matches index.php style) -->
		<div class="navtop">
			<div class="wrapper">
				<h1><img src="img/chinochat.png" alt="Chinochat Icon" class="nav-icon">Kizuna Chat</h1>
				<a href="login">Login</a>
			</div>
		</div>
		<div class="register">
			<h1>registrieren</h1>
			<div class="links">
				<a href="login">Login</a>
				<a href="registrieren" class="active">registrieren</a>
			</div>
			<form action="register" method="post" autocomplete="off">
				<label for="username">
					<i class="fas fa-user"></i>
				</label>
				<input type="text" name="username" placeholder="Username" id="username" required>
				<label for="password">
					<i class="fas fa-lock"></i>
				</label>
				<input type="password" name="password" placeholder="Password" id="password" required>
				<label for="cpassword">
					<i class="fas fa-lock"></i>
				</label>
				<input type="password" name="cpassword" placeholder="Confirm Password" id="cpassword" required>
				<label for="email">
					<i class="fas fa-envelope"></i>
				</label>
				<input type="email" name="email" placeholder="Email" id="email" required>
				<div class="msg"></div>
				<input type="submit" value="Register">
			</form>
		</div>
		<script>
		var form = document.querySelector('.register form');
		form.onsubmit = function(event) {
			event.preventDefault();
			var form_data = new FormData(form);
			var xhr = new XMLHttpRequest();
			xhr.open('POST', form.action, true);
			xhr.onload = function () {
				document.querySelector('.msg').innerHTML = this.responseText;
			};
			xhr.send(form_data);
		};
		</script>
	</body>
</html>
