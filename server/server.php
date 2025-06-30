<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -----------------------------------------------------
// Folder / Format Configuration
// -----------------------------------------------------
$videos = [];
$categories = [];
$defaultThumbnail = 'media/default.jpg'; 
$videoRootDir = '../media/youtube';
$preferredFormats = ['mp4', 'mkv', 'ogv', 'webm', 'ogg'];
$thumbnailExtensions = ['jpg', 'jpeg', 'png', 'gif'];

// -----------------------------------------------------
// Fetch all video directories
// -----------------------------------------------------
$videoFolders = [];
if (is_dir($videoRootDir)) {
    $videoFolders = glob("$videoRootDir/*", GLOB_ONLYDIR) ?: [];
}

foreach ($videoFolders as $videoFolder) {
    $videoName = basename($videoFolder);
    $encodedVideoName = urlencode($videoName);
    $categoryName = trim(strtolower($videoName));

    // -------------------------------------------------
    // Find available video file in preferred formats
    // -------------------------------------------------
    $availableFormats = [];
    foreach ($preferredFormats as $format) {
        $videoFile = "$videoFolder/$videoName.$format";
        if (file_exists($videoFile)) {
            $availableFormats[] = [
                'src' => str_replace('../', '', $videoFile),
                'type' => "video/$format"
            ];
        }
    }
    if (empty($availableFormats)) {
        continue;
    }

    // -------------------------------------------------
    // Find the correct thumbnail (same folder as video)
    // -------------------------------------------------
    $thumbnailPath = $defaultThumbnail;
    foreach ($thumbnailExtensions as $ext) {
        $tempThumbnailPath = "$videoFolder/$videoName.$ext";
        if (file_exists($tempThumbnailPath)) {
            $thumbnailPath = str_replace('../', '', $tempThumbnailPath);
            break;
        }
    }

    // Encode any special chars (#, space) in the path
    $thumbnailPath = str_replace([' ', '#'], ['%20', '%23'], $thumbnailPath);

    // -------------------------------------------------
    // Load description
    // -------------------------------------------------
    $descriptionPath = "$videoFolder/description.txt";
    $description = file_exists($descriptionPath)
        ? file_get_contents($descriptionPath)
        : "No description available.";

    // -------------------------------------------------
    // Load metadata (tags, title, upload date)
    // -------------------------------------------------
    $metaPath = "$videoFolder/metadata.json";
    $videoTitle = $videoName;
    $uploadDate = '';
    $tags = [];

    if (file_exists($metaPath)) {
        $metaContent = file_get_contents($metaPath);
        if ($metaContent !== false) {
            $metaData = json_decode($metaContent, true);

            if ($metaData === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error for file: $metaPath - " . json_last_error_msg());
            }

            if (!empty($metaData['title'])) {
                $videoTitle = $metaData['title'];
            }

            if (!empty($metaData['upload_date'])) {
                $formattedDate = DateTime::createFromFormat('Ymd', $metaData['upload_date'])->format('d-m-Y');
                $uploadDate = $formattedDate;
            }

            if (!empty($metaData['tags']) && is_array($metaData['tags'])) {
                $tags = $metaData['tags'];
            }
        }
    }

    // -------------------------------------------------
    // Generate a unique ID for the video
    // -------------------------------------------------
    $videoID = substr(md5($videoName), 0, 8);

    $videos[] = [
        'id'          => $videoID,
        'title'       => $videoTitle,
        'sources'     => $availableFormats,
        'thumbnail'   => $thumbnailPath,
        'description' => $description,
        'category'    => $categoryName,
        'upload_date' => $uploadDate,
        'tags'        => $tags
    ];
}

// -----------------------------------------------------
// Handle video fetching based on ?id=
// -----------------------------------------------------
if (isset($_GET['id'])) {
    $videoID = $_GET['id'];
    $videoData = null;

    foreach ($videos as $video) {
        if ($video['id'] === $videoID) {
            $videoData = $video;
            break;
        }
    }

    if (!$videoData) {
        http_response_code(404);
        echo json_encode(['error' => 'Video not found']);
        exit;
    }

    // If AJAX request, return JSON directly
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode($videoData);
        exit;
    }

    // Otherwise render the HTML page with Open Graph
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="../favicon.ico" type="image/x-icon">
        <title><?php echo htmlspecialchars($videoData['title']); ?></title>
        <link rel="stylesheet" href="../player-styles.css">

        <!-- Open Graph meta tags -->
        <meta property="og:type" content="video.movie">
        <meta property="og:url" content="<?php echo htmlspecialchars("http://taddl-hub.de/t-tube/server/server.php?id=" . $videoID); ?>">
        <meta property="og:title" content="<?php echo htmlspecialchars($videoData['title']); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($videoData['description']); ?>">
        <meta property="og:image" content="<?php echo htmlspecialchars($videoData['thumbnail']); ?>">
        <meta http-equiv="refresh" content="0;url=../video.php?id=<?php echo $videoID; ?>">
    </head>
    <body>
        <p>If you are not redirected, 
           <a href="../video.php?id=<?php echo $videoID; ?>">click here</a>.
        </p>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------
// Output JSON response (for script.js etc.)
// -----------------------------------------------------
echo json_encode([
    'videos' => $videos,
    'totalVideos' => count($videos)
]);
?>
