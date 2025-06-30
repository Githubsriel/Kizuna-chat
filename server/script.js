// script.js
document.addEventListener('DOMContentLoaded', () => {
    // Check which view to initialize based on query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const view = urlParams.get('view');

    if (view === 'playlists') {
        fetchVideos();
    } else {
        fetchVideos();
    }

    // Set up search bar filtering
    if (document.getElementById('searchBar')) {
        document.getElementById('searchBar').addEventListener('input', filterVideos);
    }

    // Set up random video button
    const randomVideoButton = document.getElementById('randomVideoButton');
    if (randomVideoButton) {
        randomVideoButton.addEventListener('click', playRandomVideo);
    }

    // Set up tags button
    setupTagButton();

    // Set up dark mode toggle
    setupDarkModeToggle();

    // Event delegation for dynamically added elements
    document.addEventListener('click', (event) => {
        if (event.target.closest('.video-item')) {
            const videoItem = event.target.closest('.video-item');
            const videoID = videoItem.getAttribute('data-video-id');
            playVideo(videoID);
        }
    });
});

let currentPage = 1;
const itemsPerPage = 40;
let allVideos = [];

// Fetch all videos
function fetchVideos() {
    fetch('server/server.php')
        .then(response => response.json())
        .then(data => {
            allVideos = data.videos;
            const totalVideosElement = document.getElementById('total-videos');
            if (totalVideosElement) {
                totalVideosElement.textContent = allVideos.length;
            }
            displayVideos(allVideos);
        })
        .catch(error => console.error('Error fetching videos:', error));
}

// Display the given array of videos
function displayVideos(videos) {
    const videoList = document.getElementById('video-list');
    const pagination = document.getElementById('pagination');
    if (!videoList || !pagination) return;

    videoList.innerHTML = '';
    pagination.innerHTML = '';

    const totalPages = Math.ceil(videos.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const currentVideos = videos.slice(startIndex, startIndex + itemsPerPage);

    currentVideos.forEach(video => {
        const videoItem = document.createElement('div');
        videoItem.classList.add('video-item');
        videoItem.setAttribute('data-video-id', video.id);

        // Encode spaces and # for the thumbnail
        const sanitizedThumbnail = video.thumbnail
            .replace(/ /g, '%20')
            .replace(/#/g, '%23');

        const thumbnail = document.createElement('img');
        thumbnail.src = sanitizedThumbnail;
        thumbnail.alt = `Thumbnail for ${video.title}`;
        thumbnail.classList.add('thumbnail');

        const videoInfo = document.createElement('div');
        videoInfo.classList.add('video-info');

        const title = document.createElement('h3');
        title.textContent = video.title;
        videoInfo.appendChild(title);

        const uploadDate = document.createElement('p');
        uploadDate.classList.add('upload-date');
        uploadDate.textContent = `Original upload date: ${video.upload_date}`;
        videoInfo.appendChild(uploadDate);

        const description = document.createElement('p');
        description.textContent = video.description;
        videoInfo.appendChild(description);

        videoItem.appendChild(thumbnail);
        videoItem.appendChild(videoInfo);
        videoList.appendChild(videoItem);
    });

    createPagination(totalPages);
}

// Create pagination buttons
function createPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;

    pagination.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
        const button = document.createElement('button');
        button.textContent = i;
        button.onclick = () => {
            currentPage = i;
            displayVideos(allVideos);
        };
        pagination.appendChild(button);
    }
}

// Navigate to video
function playVideo(videoID) {
    window.location.href = `video.php?id=${videoID}`;
}

// Play a random video
function playRandomVideo() {
    if (allVideos.length > 0) {
        const randomIndex = Math.floor(Math.random() * allVideos.length);
        const randomVideo = allVideos[randomIndex];
        if (randomVideo) {
            window.location.href = `video.php?id=${randomVideo.id}`;
        }
    } else {
        console.error('No videos available to play randomly.');
    }
}

// Filter videos
function filterVideos() {
    const searchQuery = document.getElementById('searchBar').value.toLowerCase();
    const filteredVideos = allVideos.filter(video =>
        video.title.toLowerCase().includes(searchQuery)
    );
    displayVideos(filteredVideos);
}

// Setup the Tags button
function setupTagButton() {
    const tagsButton = document.getElementById('tagsButton');
    const tagsOverlay = document.getElementById('tagsOverlay');
    const closeTagsOverlay = document.getElementById('closeTagsOverlay');
    const tagsList = document.getElementById('tagsList');

    if (tagsButton && tagsOverlay && closeTagsOverlay && tagsList) {
        tagsButton.addEventListener('click', () => {
            fetchTags();
            tagsOverlay.style.display = 'block';
        });

        closeTagsOverlay.addEventListener('click', () => {
            tagsOverlay.style.display = 'none';
        });
    }
}

// (Optional) fetch tags for the overlay
function fetchTags() {
    // Your code for retrieving tags from server
    // e.g. fetch('tags/all-tags.json')...
}

// Dark mode toggle
function setupDarkModeToggle() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const isDarkMode = getCookie('darkMode') === 'true';

    if (isDarkMode) {
        document.body.classList.add('dark-mode');
    }

    darkModeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const darkModeEnabled = document.body.classList.contains('dark-mode');
        setCookie('darkMode', darkModeEnabled, 7);
    });
}

// Cookie helpers
function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(name) {
    const cname = name + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(cname) === 0) {
            return c.substring(cname.length, c.length);
        }
    }
    return "";
}
