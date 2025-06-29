function closeFlash(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.opacity = '0';
        element.style.transform = 'translateY(-20px)';
        element.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

        setTimeout(function () {
            element.style.display = 'none';
        }, 300);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const closeButtons = document.querySelectorAll('.close-btn');
    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            this.parentElement.parentElement.style.display = 'none';
        });
    });

    const form = document.querySelector('.reservation-form');
    if (form) {
        // Add mask for date input
        const dateInput = form.querySelector('input[type="date"]');
        if (dateInput) {
            // Set minimum date (today)
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
        }

        // Form validation before submission
        form.addEventListener('submit', function (event) {
            let isValid = true;

            // Email validation
            const emailInput = form.querySelector('input[type="email"]');
            if (emailInput && !validateEmail(emailInput.value)) {
                showError(emailInput, 'Please enter a valid email');
                isValid = false;
            } else {
                removeError(emailInput);
            }

            // Number of participants validation
            const peopleInput = form.querySelector('input[name*="amountOfPeople"]');
            if (peopleInput) {
                const peopleCount = parseInt(peopleInput.value);
                if (isNaN(peopleCount) || peopleCount < 1 || peopleCount > 50) {
                    showError(peopleInput, 'Number of participants must be between 1 and 50');
                    isValid = false;
                } else {
                    removeError(peopleInput);
                }
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    }

    // Function for email validation
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    // Show error message
    function showError(input, message) {
        removeError(input);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.innerText = message;
        input.parentNode.appendChild(errorDiv);
        input.classList.add('is-invalid');
    }

    // Remove error message
    function removeError(input) {
        const errorDiv = input.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
        input.classList.remove('is-invalid');
    }

    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function (message, index) {
        setTimeout(function () {
            closeFlash(message.id);
        }, 5000 + (index * 500));
    });

    const dropdownToggle = document.querySelector('.dropdown-toggle');
    if (dropdownToggle) {
        dropdownToggle.addEventListener('click', function (e) {
            if (window.innerWidth < 768) {
                e.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown') && window.innerWidth < 768) {
            const dropdownMenus = document.querySelectorAll('.dropdown-menu');
            dropdownMenus.forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
});
