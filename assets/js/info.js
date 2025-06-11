document.addEventListener('DOMContentLoaded', function() {
    // Функции для секции процесса сплава
    initProcessTimeline();
    
    // Функции для FAQ
    initFaqAccordion();
});

// Инициализация интерактивной таймлайн карточек процесса
function initProcessTimeline() {
    // Выбор первой карточки по умолчанию
    document.querySelector('.timeline-card').classList.add('active');
    document.querySelector('.nav-btn').classList.add('active');
    
    // Обработка кликов по кнопкам навигации
    const navButtons = document.querySelectorAll('.nav-btn');
    let currentCardIndex = 0;
    const cards = document.querySelectorAll('.timeline-card');
    
    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Удаляем активный класс у всех кнопок и карточек
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.timeline-card').forEach(card => card.classList.remove('active'));
            
            // Добавляем активный класс выбранной кнопке
            this.classList.add('active');
            
            // Активируем соответствующую карточку
            const targetCard = this.getAttribute('data-target');
            document.getElementById(targetCard).classList.add('active');
        });
    });
    
    // Автоматическое переключение карточек
    function rotateCards() {
        document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.timeline-card').forEach(card => card.classList.remove('active'));
        
        currentCardIndex = (currentCardIndex + 1) % cards.length;
        
        const currentCard = cards[currentCardIndex];
        currentCard.classList.add('active');
        
        const currentBtn = document.querySelector(`.nav-btn[data-target="${currentCard.id}"]`);
        currentBtn.classList.add('active');
    }
    
    // Запускаем автоматическое переключение каждые 5 секунд
    const autoRotateInterval = setInterval(rotateCards, 5000);
    
    // Останавливаем автоматическое переключение при взаимодействии пользователя
    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            clearInterval(autoRotateInterval);
            
            // Получаем индекс текущей карточки для последующего автопереключения
            const targetCardId = this.getAttribute('data-target');
            const targetCard = document.getElementById(targetCardId);
            currentCardIndex = Array.from(cards).indexOf(targetCard);
        });
    });
}

// Инициализация аккордеона для FAQ секции
function initFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        question.addEventListener('click', () => {
            // Проверяем, активен ли текущий элемент
            const isActive = item.classList.contains('active');
            
            // Закрываем все элементы
            faqItems.forEach(faqItem => {
                faqItem.classList.remove('active');
            });
            
            // Если элемент не был активен, открываем его
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });
    
    // Открываем первый вопрос по умолчанию
    if (faqItems.length > 0) {
        faqItems[0].classList.add('active');
    }
}