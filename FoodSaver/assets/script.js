// assets/js/script.js

/**
 * Toggles the visibility of the navigation links for mobile view.
 */
function toggleNav() {
    const navLinks = document.querySelector('.nav-links');
    navLinks.classList.toggle('active');
}

// Example: Close mobile menu when a link is clicked
document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelector('.nav-links');
    const links = navLinks.querySelectorAll('a');

    links.forEach(link => {
        link.addEventListener('click', () => {
            if (navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
            }
        });
    });
});