<?php
// --- Error Reporting (Development Only - Remove/Modify for Production) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Includes and Session ---
require_once 'server/main.php'; // Includes DB connection ($con), starts session etc.

// --- Authentication ---
check_loggedin($con); // Enforces login if configured in main.php
if (!isset($_SESSION['loggedin'], $_SESSION['id'])) { // Double-check session vars needed
    header('Location: server/login.php');
    exit;
}

// --- Get User Level ---
$user_level = 0;
$stmt_level = $con->prepare('SELECT level FROM accounts WHERE id = ?');
if ($stmt_level) {
    $stmt_level->bind_param('i', $_SESSION['id']);
    $stmt_level->execute();
    $stmt_level->bind_result($user_level);
    $stmt_level->fetch();
    $stmt_level->close();
}

// --- Minimum Level Check ---
$min_post_level = 5; // Set your required minimum level here
$can_post = ($user_level >= $min_post_level);

// --- Data Fetching ---

// Get all users (excluding self) for the user list
$users = []; // Initialize
$stmt_users = $con->prepare('SELECT id, username, title, profile_pic, role FROM accounts WHERE id != ?');
if ($stmt_users) {
    $stmt_users->bind_param('i', $_SESSION['id']);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    $users = $result_users->fetch_all(MYSQLI_ASSOC);
    $stmt_users->close();
} else {
    error_log("Failed to prepare user list query: " . $con->error);
}

// --- Include and Instantiate Parsedown ---
require_once 'server/lib/Parsedown.php'; // Ensure this path is correct
$parsedown = new Parsedown();
// $parsedown->setSafeMode(true); // It's generally safe by default

?>
<!DOCTYPE html>
<html lang="en"> 
<head>



    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kizuna Chat - Grafischer Anime Chat für Anime-Fans</title>
    <meta name="description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta name="keywords" content="Anime Chat, Grafischer Chat, Anime Community, Manga, Anime Fans, Online Chat, Sozial, Kizuna Chat">
    <meta name="author" content="Kizuna Chat">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kizuna-chat.de/">
    <meta property="og:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="og:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="og:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://kizuna-chat.de/">
    <meta property="twitter:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="twitter:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="twitter:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">

    <meta name="robots" content="index, follow">

    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <title>Kizuna Chat - Grafischer Anime Chat für Anime-Fans</title>
    <link rel="apple-touch-icon" href="server/img/apple_favicon.png">
    <link rel="stylesheet" href="style.css"> <?php // Ensure path is correct ?>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
    <style>
        /* Simple modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; position: relative; }
        .close { position: absolute; right: 10px; top: 10px; font-size: 24px; font-weight: bold; cursor: pointer; }
        .indicator { background-color: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; vertical-align: super; }
        .level-progress { margin: 10px 0; padding: 5px; background: #f5f5f5; border-radius: 5px; }
        .progress-bar { background: #ddd; height: 20px; border-radius: 10px; margin-bottom: 5px; }
        .progress-fill { background: #4CAF50; height: 100%; border-radius: 10px; transition: width 0.3s; }
        .progress-text { font-size: 0.8em; color: #666; text-align: center; }
        .disabled-btn { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body>



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

<div id="topbar">
  <div class="wrapper" style="display: flex; align-items: center; justify-content: space-between">
    <div class="coinblock">
      </div>
  </div>
</div>

<div class="navigation">
  <div class="wrapper">
    <div class="flex_between">
      <a class="chatbutton chatArrow" href="chat" target="_blank">Zum Chat</a>
    </div>
  </div>
</div>

<nav class="navtop">
  <div>
    <h1><img src="server/img/chinochat.png" alt="Chinochat Icon" class="nav-icon">Kizuna Chat</h1>
    <a href="index"><i class="fas fa-home"></i>Home</a>
    <a href="profile"><i class="fas fa-user-circle"></i>Profil (Level <?= $user_level ?>)</a>
    <a href="marketplace"><i class="fas fa-store"></i>Marktplatz</a>

    <?php // Link for Administrators ?>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Admin'): ?>
      <a href="server/admin/index" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
    <?php endif; ?>

    <?php // Link for Moderators ?>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Moderator'): ?>
      <?php $moderator_link = "server/moderator/index"; ?>
      <a href="<?= $moderator_link ?>" target="_blank"><i class="fas fa-user-shield"></i>Moderator</a>
    <?php endif; ?>

    <a href="server/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    <button id="darkModeToggle" class="dark-mode-btn" type="button">Dark Mode</button>
  </div>
</nav>


<div class="main-content wrapper">

  <section class="user-feed">
    <h2>User Feed</h2>

    <button id="togglePostForm" class="profile-btn <?= !$can_post ? 'disabled-btn' : '' ?>" style="margin-bottom: 1rem;" <?= !$can_post ? 'disabled title="Du benötigst mindestens Level '.$min_post_level.' um Beiträge zu erstellen"' : '' ?>>Add New Post</button>

    <?php if (!$can_post): ?>
        <div class="level-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, ($user_level / $min_post_level) * 100) ?>%"></div>
            </div>
            <div class="progress-text">
                Fortschritt bis du selber Posts erstellen kannst: <?= min(100, round(($user_level / $min_post_level) * 100)) ?>% (Aktuelles Level: <?= $user_level ?>/<?= $min_post_level ?>)
            </div>
        </div>
    <?php endif; ?>

    <div id="postFormContainer" class="add-post" style="display: none;">
      <h3>Add New Post</h3>
      <form id="clubPostForm" enctype="multipart/form-data">
        <textarea name="content" placeholder="Write your post here (Markdown supported)..." required></textarea>
        <input type="file" name="image" accept="image/*">
        <button type="submit" class="profile-btn" style="margin-top: 0.5rem;">Submit</button>
      </form>
    </div>

    <div class="user-posts">
      <div id="clubpostMain">
        <?php
        // Fetch all club posts joined with user information, ordered newest first
        $query_posts = "SELECT cp.id, cp.user_id, cp.content, cp.image, cp.created_at, a.username
                        FROM club_posts cp
                        JOIN accounts a ON cp.user_id = a.id
                        ORDER BY cp.created_at DESC";
        $result_posts = $con->query($query_posts);

        if ($result_posts && $result_posts->num_rows > 0):
          // Loop through each post
          while ($post = $result_posts->fetch_assoc()):
            $postId = $post['id'];
            $postDate = date("F j, Y, g:i a", strtotime($post['created_at']));

            // Initialize variables for this post iteration
            $commentsHtml = "";
            $commentCount = 0;
            $userHasLiked = false;
            $totalLikes = 0;
            $likerUsernames = [];
            $likersTooltipText = 'Be the first to like this!';

            // --- Check if the CURRENT logged-in user liked this post ---
            if (isset($_SESSION['id'])) {
                $stmtCheckLike = $con->prepare("SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ?");
                if ($stmtCheckLike) {
                     $stmtCheckLike->bind_param("ii", $postId, $_SESSION['id']);
                     $stmtCheckLike->execute();
                     $stmtCheckLike->store_result();
                     if ($stmtCheckLike->num_rows > 0) { $userHasLiked = true; }
                     $stmtCheckLike->close();
                } else { error_log("Prepare failed for like check: " . $con->error); }
            }

            // --- Get Like Count AND Liker Usernames ---
            $stmtGetLikers = $con->prepare("SELECT a.username FROM post_likes pl JOIN accounts a ON pl.user_id = a.id WHERE pl.post_id = ? ORDER BY a.username ASC");
            if ($stmtGetLikers) {
                 $stmtGetLikers->bind_param("i", $postId);
                 if ($stmtGetLikers->execute()){
                      $likersResult = $stmtGetLikers->get_result();
                      while ($liker = $likersResult->fetch_assoc()) {
                           // Sanitize username for display in tooltip
                           $likerUsernames[] = htmlspecialchars($liker['username']);
                      }
                      $totalLikes = count($likerUsernames);
                      if ($totalLikes > 0) {
                          $likersTooltipText = "Liked by: " . implode(', ', $likerUsernames);
                      }
                 } else { error_log("Execute failed for get likers: " . $stmtGetLikers->error); }
                 $stmtGetLikers->close();
            } else { error_log("Prepare failed for get likers: " . $con->error); }

            // --- Get Comments for the post and Process their content ---
            $stmtComments = $con->prepare("SELECT pc.id, pc.user_id, pc.content, pc.created_at, a.username FROM post_comments pc JOIN accounts a ON pc.user_id = a.id WHERE pc.post_id = ? ORDER BY pc.created_at ASC");
            if ($stmtComments) {
                 $stmtComments->bind_param("i", $postId);
                 if ($stmtComments->execute()) {
                     $commentsResult = $stmtComments->get_result();
                     while ($comment = $commentsResult->fetch_assoc()){
                         $commentCount++;
                         $commentDate = date("F j, Y, g:i a", strtotime($comment['created_at']));
                         $processedCommentContent = $parsedown->text($comment['content']); // Use Parsedown

                         $commentsHtml .= "<div class='comment' data-commentid='".htmlspecialchars($comment['id'])."'>";
                         $commentsHtml .= "<span class='comment-author'>" . htmlspecialchars($comment['username']) . "</span>";
                         $commentsHtml .= "<span class='comment-date'>" . htmlspecialchars($commentDate) . "</span>";
                         $commentsHtml .= "<div class='comment-content'>" . $processedCommentContent . "</div>"; // Output processed HTML
                         // Add delete button for Admin users
                         if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
                             $commentsHtml .= "<button class='delete-comment-btn' data-commentid='".htmlspecialchars($comment['id'])."'>Delete</button>";
                         }
                         $commentsHtml .= "</div>"; // Close comment div
                     }
                 } else { error_log("Failed to execute comments query for post $postId: " . $stmtComments->error); }
                 $stmtComments->close();
            } else { error_log("Failed to prepare comments query for post $postId: " . $con->error); }

            // --- Process post content with Parsedown ---
            $processedPostContent = $parsedown->text($post['content']);

          ?>
          <div class="post-box" data-postid="<?= htmlspecialchars($postId) ?>">
            <div class="post-header">
              <span class="posted-by">Posted by <?= htmlspecialchars($post['username']) ?></span>
              <span class="post-date"><?= htmlspecialchars($postDate) ?></span>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <button class="delete-post-btn" data-postid="<?= htmlspecialchars($postId) ?>">Delete</button>
              <?php endif; ?>
            </div>
            <div class="post-content">
              <?= $processedPostContent /* Parsedown output */ ?>
              <?php if (!empty($post['image'])):
                 $imgSrc = 'server/' . ltrim(htmlspecialchars($post['image']), '/');
              ?>
                <img src="<?= $imgSrc ?>" alt="Club Post Image" style="max-width:100%; margin-top:10px;">
              <?php endif; ?>
            </div>

            <div class="post-actions">
              <button class="like-btn <?= $userHasLiked ? 'liked' : '' ?>" data-postid="<?= htmlspecialchars($postId) ?>">
                  <?= $userHasLiked ? 'Unlike' : 'Like' ?>
              </button>
              <span class="like-count" id="like-count-<?= htmlspecialchars($postId) ?>" title="<?= htmlspecialchars($likersTooltipText) ?>">
                  <?= htmlspecialchars($totalLikes) ?>
              </span> likes
              <button class="toggle-comments-btn" data-postid="<?= htmlspecialchars($postId) ?>">
                Comments (<span class="comment-count"><?= $commentCount ?></span>)
              </button>
            </div>

            <div class="comments-section" id="comments-section-<?= htmlspecialchars($postId) ?>" style="display: none;">
              <div class="existing-comments" id="existing-comments-<?= htmlspecialchars($postId) ?>">
                <?= $commentsHtml /* Output pre-generated comments HTML */ ?>
              </div>
              <form class="comment-form" data-postid="<?= htmlspecialchars($postId) ?>">
                <textarea name="comment" placeholder="Write a comment (Markdown supported)..." required></textarea>
                <button type="submit">Submit Comment</button>
              </form>
            </div>
          </div> <?php // End post-box ?>
          <?php endwhile; // End the loop for posts ?>
        <?php else: // Handle case where there are no posts or query failed ?>
            <p style="text-align: center; padding: 20px;">No posts yet. Be the first to add one!</p>
            <?php if (!$result_posts) { error_log("Failed to execute posts query: " . $con->error); } ?>
        <?php endif; ?>
        <?php if ($result_posts) $result_posts->close(); // Close the posts result set if it was opened ?>
      </div> <?php // End clubpostMain ?>
    </div> <?php // End user-posts ?>
  </section> <?php // End user-feed section ?>


  <section class="all-users"> <?php // Added class for potential styling ?>
    <h2 style="text-align: center;">All Users</h2> <?php // Removed color: white style, handle in CSS ?>
    <div class="search-bar">
      <input type="text" id="searchInput" placeholder="Search users by name or title...">
    </div>
    <div class="user-list" id="userList">
      <?php if (!empty($users)): ?>
          <?php foreach ($users as $user): ?>
              <?php
                  // Determine profile picture path
                  $profilePicPath = 'server/uploads/default.png'; // Default
                  if (!empty($user['profile_pic'])) {
                      // IMPORTANT: Ensure this path construction is correct for URL access
                      $potentialPath = 'server/uploads/' . basename(htmlspecialchars($user['profile_pic']));
                      // Optional: Check if file actually exists on server before using it
                      // if (file_exists(__DIR__ . '/' . $potentialPath)) {
                      //      $profilePicPath = $potentialPath;
                      // } // This check might be slow if there are many users
                      $profilePicPath = $potentialPath; // Assume path is correct for now
                  }
                  $roleClass = strtolower(htmlspecialchars($user['role']));
              ?>
              <div class="user-card" data-username="<?=htmlspecialchars(strtolower($user['username']))?>" data-title="<?=htmlspecialchars(strtolower($user['title'] ?? ''))?>">
                  <img src="<?=$profilePicPath?>" alt="Profile Picture">
                  <p class="username"><?=htmlspecialchars($user['username'])?></p>
                  <span class="badge <?= $roleClass ?>"><?=htmlspecialchars($user['role'])?></span>
                  <p class="user-title"><?=!empty($user['title']) ? htmlspecialchars($user['title']) : 'No title'?></p>
                  
                  <a href="view_profile?id=<?=htmlspecialchars($user['id'])?>" class="view-profile-button">View Profile</a>
              </div>
          <?php endforeach; ?>
      <?php else: ?>
          <p style="text-align: center; padding: 15px;">No other users found.</p>
      <?php endif; ?>
    </div>
  </section>

<footer id="footer">
  <div class="wrapper">
    <div class="coinblock">
      <a href="regeln">Regeln</a>
      <a href="handbuch">Handbuch</a>
      <a href="impressum">Impressum</a>
    </div>
    <p>&copy; 2025 Kizuna-Chat</p>
  </div>
</footer>

<script>
// --- DOMContentLoaded Wrapper (Good Practice) ---
document.addEventListener("DOMContentLoaded", function () {

    // --- Element References (Optional but can improve readability) ---
    const clubPostForm = document.getElementById("clubPostForm");
    const togglePostBtn = document.getElementById("togglePostForm");
    const searchInput = document.getElementById("searchInput");
    const darkModeBtn = document.getElementById('darkModeToggle');
    const alphaPopup = document.getElementById("alpha-warning-popup");
    const acceptAlphaButton = document.getElementById("accept-alpha-warning");
    const canPost = <?= $can_post ? 'true' : 'false' ?>;

    // --- Consolidated Event Listener for Clicks (Event Delegation) ---
    document.body.addEventListener('click', function(e) {

        // --- Like Button Click ---
        if (e.target && e.target.classList.contains('like-btn')) {
            e.preventDefault();
            var postId = e.target.getAttribute('data-postid');
            var likeButton = e.target;
            var likeCountSpan = document.getElementById('like-count-' + postId);

            if (likeButton.disabled) return; // Prevent double clicks

            likeButton.disabled = true;
            var originalText = likeButton.textContent;
            likeButton.textContent = '...';

            fetch('server/like_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'post_id=' + encodeURIComponent(postId)
            })
            .then(response => response.ok ? response.json() : response.json().then(err => Promise.reject(err)))
            .then(data => {
                if (data.success) {
                    if(likeCountSpan) {
                        likeCountSpan.innerText = data.newTotal;
                        likeCountSpan.setAttribute('title', data.likersTooltip);
                    }
                    if (data.newState === 'liked') {
                        likeButton.classList.add('liked');
                        likeButton.textContent = 'Unlike';
                    } else {
                        likeButton.classList.remove('liked');
                        likeButton.textContent = 'Like';
                    }
                } else {
                    alert('Could not update like status: ' + (data.message || 'Unknown server error'));
                    likeButton.textContent = originalText; // Restore on error
                }
            })
            .catch(err => {
                console.error("Error liking/unliking post:", err);
                alert("An error occurred: " + (err.message || "Check console"));
                likeButton.textContent = originalText; // Restore on error
            })
            .finally(() => {
                likeButton.disabled = false; // Re-enable button
            });
        }

        // --- Toggle Comments Button Click ---
        else if (e.target && e.target.classList.contains('toggle-comments-btn')) {
           var postId = e.target.getAttribute('data-postid');
           var commentsSection = document.getElementById('comments-section-' + postId);
           if (commentsSection) {
               commentsSection.style.display = (commentsSection.style.display === 'none' || commentsSection.style.display === '') ? 'block' : 'none';
           }
        }

        // --- Delete Post Button Click ---
        else if (e.target && e.target.classList.contains('delete-post-btn')) {
            let postId = e.target.getAttribute('data-postid');
            if (confirm("Are you sure you want to delete this post? This action cannot be undone.")) {
                fetch("server/delete_club_post.php", {
                    method: "POST",
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ post_id: postId })
                })
                .then(response => response.ok ? response.json() : response.json().then(err => Promise.reject(err)))
                .then(data => {
                    if (data.success) {
                        // Use closest('.post-box') for potentially more robust element finding
                        let postElem = e.target.closest('.post-box');
                        // Fallback to querySelector if closest doesn't work (e.g., button moved outside)
                        if (!postElem) {
                             postElem = document.querySelector(`.post-box[data-postid='${postId}']`);
                        }
                        if (postElem) {
                            postElem.remove();
                        }
                    } else {
                        alert("Error deleting post: " + (data.message || "Unknown error"));
                    }
                })
                .catch(err => {
                    console.error("Error deleting post:", err);
                    alert("An error occurred while trying to delete the post: " + (err.message || "Check console"));
                });
            }
        }

        // --- Delete Comment Button Click ---
        else if (e.target && e.target.classList.contains('delete-comment-btn')) {
            let commentId = e.target.getAttribute('data-commentid');
            if (confirm("Are you sure you want to delete this comment?")) {
                fetch("server/delete_comment.php", { // Assuming this endpoint exists and works similarly
                    method: "POST",
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ comment_id: commentId })
                })
                 .then(response => response.ok ? response.json() : response.json().then(err => Promise.reject(err)))
                .then(data => {
                    if (data.success) {
                        let commentElem = e.target.closest('.comment');
                        if (commentElem) {
                            // Find post ID to update comment count
                            let postBox = commentElem.closest('.post-box');
                            let postId = postBox ? postBox.dataset.postid : null;
                            commentElem.remove(); // Remove the comment visually

                            // Optional: Update comment count display
                            if(postId) {
                                let countSpan = document.querySelector(`.toggle-comments-btn[data-postid='${postId}'] .comment-count`);
                                if(countSpan) {
                                    let currentCount = parseInt(countSpan.textContent);
                                    if (!isNaN(currentCount) && currentCount > 0) {
                                         countSpan.textContent = currentCount - 1;
                                    }
                                }
                            }
                        }
                    } else {
                        alert("Error deleting comment: " + (data.message || "Unknown error"));
                    }
                })
                .catch(err => {
                    console.error("Error deleting comment:", err);
                     alert("An error occurred while trying to delete the comment: " + (err.message || "Check console"));
                });
            }
        }

        // --- Dark Mode Button Click ---
        else if (darkModeBtn && e.target === darkModeBtn) {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
                darkModeBtn.textContent = 'Light Mode';
            } else {
                localStorage.setItem('darkMode', 'disabled');
                darkModeBtn.textContent = 'Dark Mode';
            }
        }

        // --- Alpha Warning Accept Button Click ---
        else if (acceptAlphaButton && e.target === acceptAlphaButton) {
             if(alphaPopup) alphaPopup.classList.add("hidden");
             // Set cookie (using the helper function below)
             setCookie("alphaWarningAccepted", "true", 365);
        }

        // --- Toggle Add Post Form visibility ---
        else if (togglePostBtn && e.target === togglePostBtn) {
            if (!canPost) {
                alert("You need at least level <?= $min_post_level ?> to create posts. Your current level is <?= $user_level ?>.");
                return;
            }
            
            var formContainer = document.getElementById("postFormContainer");
            if (formContainer) {
                 formContainer.style.display = (formContainer.style.display === "none" || formContainer.style.display === "") ? "block" : "none";
            }
        }

    }); // --- End of Consolidated Click Listener ---


    // --- Consolidated Event Listener for Form Submissions ---
    document.body.addEventListener('submit', function(e) {

        // --- Submit Comment Form ---
        if (e.target && e.target.classList.contains('comment-form')) {
            e.preventDefault();
            var form = e.target;
            var postId = form.getAttribute('data-postid');
            var formData = new FormData(form);
            var submitButton = form.querySelector('button[type="submit"]');
            var originalButtonText = submitButton.textContent;

            if (!formData.has('post_id')) { formData.append('post_id', postId); } // Ensure post_id is sent
            if (submitButton.disabled) return; // Prevent double submit

            submitButton.disabled = true;
            submitButton.textContent = '...';

            fetch('server/add_comment.php', { // Assuming this returns processed comment_html
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.ok ? response.json() : response.json().then(err => Promise.reject(err)))
            .then(data => {
                if (data.success && data.comment_html) {
                    var commentsContainer = document.getElementById('existing-comments-' + postId);
                    if(commentsContainer) {
                        // Safer way to append HTML string
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data.comment_html; // Let browser parse
                        while (tempDiv.firstChild) { // Append parsed elements
                            commentsContainer.appendChild(tempDiv.firstChild);
                        }
                    }
                    form.reset();

                    // Update comment count display
                    let countSpan = document.querySelector(`.toggle-comments-btn[data-postid='${postId}'] .comment-count`);
                    if(countSpan) {
                        let currentCount = parseInt(countSpan.textContent);
                         countSpan.textContent = isNaN(currentCount) ? 1 : currentCount + 1;
                    }
                } else {
                    alert('Error adding comment: ' + (data.message || 'Unknown server error'));
                }
            })
            .catch(err => {
                console.error("Error adding comment:", err);
                alert("An error occurred while adding the comment: " + (err.message || "Check console"));
            })
            .finally(() => {
                 submitButton.disabled = false;
                 submitButton.textContent = originalButtonText;
            });
        }

        // --- Submit Add Post Form ---
        else if (e.target && e.target.id === 'clubPostForm') {
            e.preventDefault();
            
            // Double-check if user can post (in case page state changed)
            if (!canPost) {
                alert("You do not have the required level to post.");
                return;
            }
            
            let form = e.target;
            let formData = new FormData(form);
            let submitButton = form.querySelector('button[type="submit"]');
            let originalButtonText = submitButton.textContent;

            if (submitButton.disabled) return; // Prevent double submit

            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';

            fetch("server/add_club_post.php", { // Assuming this returns full post_html
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.ok ? response.json() : response.json().then(err => Promise.reject(err)))
            .then(data => {
                if (data.success && data.post_html) {
                    let clubpostMain = document.getElementById("clubpostMain");
                    if (clubpostMain) {
                        // Safer way to prepend HTML string
                        let tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data.post_html; // Let browser parse

                        // Ensure we are prepending the actual .post-box element
                        let postBoxElement = tempDiv.querySelector('.post-box') || tempDiv.firstChild;

                        if (postBoxElement) {
                           if (clubpostMain.firstChild) {
                               clubpostMain.insertBefore(postBoxElement, clubpostMain.firstChild);
                           } else {
                               clubpostMain.appendChild(postBoxElement); // If feed was empty
                           }
                        } else {
                             console.error("Received post HTML did not contain a .post-box element.");
                        }
                    }
                    form.reset();
                    let formContainer = document.getElementById("postFormContainer");
                    if(formContainer) formContainer.style.display = "none"; // Hide form
                } else {
                    alert("Error adding post: " + (data.message || "Unknown error"));
                }
            })
            .catch(err => {
                console.error("Error submitting post:", err);
                alert("An error occurred while adding the post: " + (err.message || "Check console"));
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            });
        }

    }); // --- End of Consolidated Submit Listener ---


    // --- User Search Filter ---
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const search = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll("#userList .user-card");
            cards.forEach(card => {
                const username = card.dataset.username || '';
                const title = card.dataset.title || '';
                // Show card if search term is found in username or title
                card.style.display = (username.includes(search) || title.includes(search)) ? "block" : "none";
            });
        });
    }

    // --- Initial Dark Mode Check ---
    if (localStorage.getItem('darkMode') === 'enabled' && darkModeBtn) {
        document.body.classList.add('dark-mode');
        darkModeBtn.textContent = 'Light Mode';
    } else if (darkModeBtn) {
         darkModeBtn.textContent = 'Dark Mode';
    }

    // --- Initial Alpha Warning Check ---
    if (alphaPopup && acceptAlphaButton) {
        if (getCookie("alphaWarningAccepted") !== "true") {
             alphaPopup.classList.remove("hidden");
        }
    }

    // --- Cookie Helper Functions (Needed for Alpha Warning) ---
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        // Added SameSite=Lax for modern browsers
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

}); // --- End of DOMContentLoaded Wrapper ---
</script>

</body>
</html>
