document.addEventListener('DOMContentLoaded', function() {
    initProcessTimeline();
    initFaqAccordion();
});

function initProcessTimeline() {
    document.querySelector('.timeline-card').classList.add('active');
    document.querySelector('.nav-btn').classList.add('active');

    const navButtons = document.querySelectorAll('.nav-btn');
    let currentCardIndex = 0;
    const cards = document.querySelectorAll('.timeline-card');

    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.timeline-card').forEach(card => card.classList.remove('active'));
            this.classList.add('active');
            const targetCard = this.getAttribute('data-target');
            document.getElementById(targetCard).classList.add('active');
        });
    });

    function rotateCards() {
        document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.timeline-card').forEach(card => card.classList.remove('active'));
        currentCardIndex = (currentCardIndex + 1) % cards.length;
        const currentCard = cards[currentCardIndex];
        currentCard.classList.add('active');
        const currentBtn = document.querySelector(`.nav-btn[data-target="${currentCard.id}"]`);
        currentBtn.classList.add('active');
    }

    const autoRotateInterval = setInterval(rotateCards, 5000);

    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            clearInterval(autoRotateInterval);
            const targetCardId = this.getAttribute('data-target');
            const targetCard = document.getElementById(targetCardId);
            currentCardIndex = Array.from(cards).indexOf(targetCard);
        });
    });
}

function initFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            faqItems.forEach(faqItem => {
                faqItem.classList.remove('active');
            });
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });
    if (faqItems.length > 0) {
        faqItems[0].classList.add('active');
    }
}
