<?php
include 'main.php';
// check if the data from the login form was submitted, isset() will check if the data exists.
if (!isset($_POST['username'], $_POST['password'])) {
	// Could not get the data that should have been sent.
	exit('Please fill both the username and password fields!');
}
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
$stmt = $con->prepare('SELECT id, password, rememberme, activation_code, role FROM accounts WHERE username = ?');
// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
$stmt->bind_param('s', $_POST['username']);
$stmt->execute();
// Store the result so we can check if the account exists in the database.
$stmt->store_result();
// Check if the account exists:
if ($stmt->num_rows > 0) {
	$stmt->bind_result($id, $password, $rememberme, $activation_code, $role);
	$stmt->fetch();
	$stmt->close();
	// Account exists, now we verify the password.
	// Note: remember to use password_hash in your registration file to store the hashed passwords.
	if (password_verify($_POST['password'], $password)) {
		// Check if the account is activated
		if (account_activation && $activation_code != 'activated') {
			// User has not activated their account, output the message
			echo 'Please activate your account to login, click <a href="resendactivation.php">here</a> to resend the activation email!';
		} else {
			// Verification success! User has loggedin!
			// Create sessions so we know the user is logged in, they basically act like cookies but remember the data on the server.
			session_regenerate_id();
			$_SESSION['loggedin'] = TRUE;
			$_SESSION['name'] = $_POST['username'];
			$_SESSION['id'] = $id;
			$_SESSION['role'] = $role;
			// IF the user checked the remember me check box:
			if (isset($_POST['rememberme'])) {
				// Create a hash that will be stored as a cookie and in the database, this will be used to identify the user.
				$cookiehash = !empty($rememberme) ? $rememberme : password_hash($id . $_POST['username'] . 'yoursecretkey', PASSWORD_DEFAULT);
				// The amount of days a user will be remembered:
				$days = 30;
				setcookie('rememberme', $cookiehash, (int)(time()+60*60*24*$days));
				/// Update the "rememberme" field in the accounts table
				$stmt = $con->prepare('UPDATE accounts SET rememberme = ? WHERE id = ?');
				$stmt->bind_param('si', $cookiehash, $id);
				$stmt->execute();
				$stmt->close();
			}
			echo 'Success'; // Do not change this line as it will be used to check with the AJAX code
		}
	} else {
		// Incorrect password
		echo 'Incorrect username and/or password!';
	}
} else {
	// Incorrect username
	echo 'Incorrect username and/or password!';
}
?>
