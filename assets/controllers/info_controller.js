import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // Variables for storing state, so we can clear them later
    autoRotateInterval = null;
    eventListeners = [];

    connect() {
        console.log("Info controller connected!"); // For debugging
        this.initializeInfoPage();
    }

    disconnect() {
        console.log("Info controller disconnected!"); // For debugging
        this.cleanupInfoPage();
    }

    // --- Main Turbo lifecycle functions ---

    /**
     * Initializes everything on the page.
     */
    initializeInfoPage() {
        // Check if the required elements exist on the page to avoid running code unnecessarily
        if (!document.querySelector('.timeline-card')) {
            return;
        }
        
        this.initProcessTimeline();
        this.initFaqAccordion();
    }

    /**
     * Cleans up everything before leaving the page.
     */
    cleanupInfoPage() {
        // 1. Stop the auto-rotation interval
        if (this.autoRotateInterval) {
            clearInterval(this.autoRotateInterval);
            this.autoRotateInterval = null;
        }

        // 2. Remove all event listeners that we added
        this.eventListeners.forEach(({ element, type, handler }) => {
            element.removeEventListener(type, handler);
        });
        this.eventListeners = []; // Clear the array
    }

    addTrackedListener(element, type, handler) {
        const boundHandler = handler.bind(this);
        element.addEventListener(type, boundHandler);
        this.eventListeners.push({ element, type, handler: boundHandler });
    };

    initProcessTimeline() {
        const cards = document.querySelectorAll('.timeline-card');
        const navButtons = document.querySelectorAll('.nav-btn');
        if (cards.length === 0 || navButtons.length === 0) return;

        let currentCardIndex = 0;

        const setActiveElements = (index) => {
            cards.forEach(card => card.classList.remove('active'));
            navButtons.forEach(btn => btn.classList.remove('active'));
            
            if (cards[index]) {
                cards[index].classList.add('active');
                const targetId = cards[index].id;
                const correspondingButton = document.querySelector(`.nav-btn[data-target="${targetId}"]`);
                if (correspondingButton) {
                    correspondingButton.classList.add('active');
                }
            }
        };
        
        // Handler for navigation button click
        const handleNavButtonClick = function() {
            clearInterval(this.autoRotateInterval); // Stop auto-rotation on manual control
            const targetCardId = this.getAttribute('data-target');
            const targetCard = document.getElementById(targetCardId);
            currentCardIndex = Array.from(cards).indexOf(targetCard);
            setActiveElements(currentCardIndex);
        };

        navButtons.forEach(button => {
            this.addTrackedListener(button, 'click', handleNavButtonClick);
        });
        
        // Function for automatic rotation
        const rotateCards = () => {
            currentCardIndex = (currentCardIndex + 1) % cards.length;
            setActiveElements(currentCardIndex);
        };
        
        // Start and save the interval
        setActiveElements(0); // Activate the first element on load
        this.autoRotateInterval = setInterval(rotateCards, 5000);
    }

    initFaqAccordion() {
        const faqItems = document.querySelectorAll('.faq-item');
        if (faqItems.length === 0) return;

        const handleQuestionClick = (e) => {
            const clickedItem = e.currentTarget.parentElement;
            const isActive = clickedItem.classList.contains('active');
            
            // First, remove active from all
            faqItems.forEach(item => item.classList.remove('active'));
            
            // If the clicked element was not active, make it active
            if (!isActive) {
                clickedItem.classList.add('active');
            }
        };
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            if (question) {
                this.addTrackedListener(question, 'click', handleQuestionClick);
            }
        });

        // Activate the first element by default
        if (faqItems.length > 0) {
            faqItems[0].classList.add('active');
        }
    }
}