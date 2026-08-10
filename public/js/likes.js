// public/js/likes.js

document.addEventListener('DOMContentLoaded', function() {
    // Обробка лайків для всіх елементів
    document.querySelectorAll('.like-button, .dislike-button').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            await handleVote(this);
        });
    });
    
    async function handleVote(button) {
        const form = button.closest('form');
        const likeButtons = button.closest('.like-buttons');
        const entityType = likeButtons.dataset.entityType;
        const entityId = likeButtons.dataset.entityId;
        
        try {
            const formData = new FormData(form);
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Оновлення лічильників
                const likeCountEl = likeButtons.querySelector('.like-count');
                const dislikeCountEl = likeButtons.querySelector('.dislike-count');
                
                if (likeCountEl) likeCountEl.textContent = data.likes;
                if (dislikeCountEl) dislikeCountEl.textContent = data.dislikes;
                
                // Оновлення стану кнопок
                const likeButton = likeButtons.querySelector('.like-button');
                const dislikeButton = likeButtons.querySelector('.dislike-button');
                
                if (likeButton) {
                    likeButton.classList.toggle('active', data.userLiked);
                }
                
                if (dislikeButton) {
                    dislikeButton.classList.toggle('active', data.userDisliked);
                }
                
                // Показати сповіщення
                showNotification(data.message, 'success');
            } else {
                showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Помилка при обробці запиту', 'error');
        }
    }
    
    function showNotification(message, type) {
        // Ваша реалізація сповіщень
        alert(`${type}: ${message}`);
    }
});