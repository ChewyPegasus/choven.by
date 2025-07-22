document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll('.language-dropdown, .user-dropdown, .guest-dropdown');

    dropdowns.forEach(dropdown => {
        let hideTimeout;

        dropdown.addEventListener('mouseenter', () => {
            clearTimeout(hideTimeout);
            const content = dropdown.querySelector('.dropdown-content');
            if (content) {
                content.style.display = 'block';
            }
        });

        dropdown.addEventListener('mouseleave', () => {
            hideTimeout = setTimeout(() => {
                const content = dropdown.querySelector('.dropdown-content');
                if (content) {
                    content.style.display = 'none';
                }
            }, 200);
        });
    });
});