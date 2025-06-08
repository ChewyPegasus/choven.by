document.addEventListener('DOMContentLoaded', function() {
    // Initialize Swiper
    const swiper = new Swiper('.hero-swiper', {
        // Optional parameters
        loop: true,
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        
        // Navigation arrows
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        
        // Pagination
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
    });

    // Popup functionality
    const detailButtons = document.querySelectorAll('.btn-details');
    const popups = document.querySelectorAll('.popup-overlay');
    const closeButtons = document.querySelectorAll('.popup-close');
    
    // Open popup when detail button is clicked
    detailButtons.forEach(button => {
        button.addEventListener('click', function() {
            const packageType = this.getAttribute('data-package');
            const popup = document.getElementById(`popup-${packageType}`);
            if (popup) {
                popup.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling of background
            }
        });
    });
    
    // Close popup when close button is clicked
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const popup = this.closest('.popup-overlay');
            if (popup) {
                popup.classList.remove('active');
                document.body.style.overflow = ''; // Restore scrolling
            }
        });
    });
    
    // Close popup when clicking outside of content
    popups.forEach(popup => {
        popup.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close popup with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            popups.forEach(popup => {
                popup.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });
});