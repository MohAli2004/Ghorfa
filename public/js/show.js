/* =========================
   Property Show Page JavaScript
========================= */

// Image Modal Variables
let currentImageIndex = 0;
let imageSources = [];
let totalImages = 0;

// Image Modal Functions
function openImageModal(imageSrc) {
    const thumbImages = document.querySelectorAll('.gallery-thumb img');
    const mainImage = document.getElementById('galleryMainImage');
    if (thumbImages.length) {
        imageSources = Array.from(thumbImages).map((img) => img.src);
    } else if (mainImage) {
        imageSources = [mainImage.src];
    } else {
        imageSources = [];
    }
    totalImages = imageSources.length;
    
    // Find the index of the clicked image
    currentImageIndex = imageSources.indexOf(imageSrc);
    
    // If image not found in gallery (shouldn't happen in normal flow), just use index 0
    if (currentImageIndex === -1) {
        currentImageIndex = typeof gallerySlideIndex === 'number' ? gallerySlideIndex : 0;
    }
    
    // Show modal first
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Update modal content (this will show the correct image)
    updateModalImage();
    updateImageCounter();
    updateNavigationButtons();
    createThumbnails();
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function updateModalImage() {
    const modalImage = document.getElementById('modalImage');
    const newSrc = imageSources[currentImageIndex];
    if (modalImage.src === newSrc) return;

    modalImage.classList.add('loading');
    modalImage.style.opacity = '0.5';
    modalImage.src = newSrc;

    modalImage.onload = function() {
        this.classList.remove('loading');
        this.style.opacity = '1';
    };
    modalImage.onerror = function() {
        this.classList.remove('loading');
        this.style.opacity = '1';
    };
}

function updateImageCounter() {
    document.getElementById('currentImageIndex').textContent = currentImageIndex + 1;
    document.getElementById('totalImages').textContent = totalImages;
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    prevBtn.disabled = currentImageIndex === 0;
    nextBtn.disabled = currentImageIndex === totalImages - 1;
}

function createThumbnails() {
    const thumbnailStrip = document.getElementById('thumbnailStrip');
    thumbnailStrip.innerHTML = '';
    
    imageSources.forEach((src, index) => {
        const thumbnailItem = document.createElement('div');
        thumbnailItem.className = `thumbnail-item ${index === currentImageIndex ? 'active' : ''}`;
        thumbnailItem.onclick = () => goToImage(index);
        
        const img = document.createElement('img');
        img.src = src;
        img.alt = `Thumbnail ${index + 1}`;
        
        thumbnailItem.appendChild(img);
        thumbnailStrip.appendChild(thumbnailItem);
    });
}

function goToImage(index) {
    if (index >= 0 && index < totalImages) {
        currentImageIndex = index;
        updateModalImage();
        updateImageCounter();
        updateNavigationButtons();
        updateThumbnailSelection();
    }
}

function updateThumbnailSelection() {
    const thumbnails = document.querySelectorAll('.thumbnail-item');
    thumbnails.forEach((thumb, index) => {
        thumb.classList.toggle('active', index === currentImageIndex);
    });
}

function nextImage() {
    if (currentImageIndex < totalImages - 1) {
        goToImage(currentImageIndex + 1);
    }
}

function previousImage() {
    if (currentImageIndex > 0) {
        goToImage(currentImageIndex - 1);
    }
}

// Close modal when clicking outside the image
document.addEventListener('click', function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
        closeImageModal();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('imageModal');
    if (modal.style.display === 'flex') {
        switch(event.key) {
            case 'Escape':
                closeImageModal();
                break;
            case 'ArrowLeft':
                event.preventDefault();
                previousImage();
                break;
            case 'ArrowRight':
                event.preventDefault();
                nextImage();
                break;
            case ' ':
                event.preventDefault();
                nextImage();
                break;
        }
    }
});

// Legacy primary/secondary contact buttons only (not Call/WhatsApp links)
function initContactButtons() {
    const contactBtns = document.querySelectorAll('.contact-btn.primary, .contact-btn.secondary');
    
    contactBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.classList.contains('primary')) {
                showContactModal('call');
            } else if (this.classList.contains('secondary')) {
                showContactModal('message');
            }
        });
    });
}

// Show contact modal (placeholder for future implementation)
function showContactModal(type) {
    const modal = document.createElement('div');
    modal.className = 'contact-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;
    
    const content = document.createElement('div');
    content.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 20px;
        text-align: center;
        max-width: 400px;
        width: 90%;
    `;
    
    if (type === 'call') {
        content.innerHTML = `
            <h3>Call Property Owner</h3>
            <p>This feature will be implemented soon. For now, please contact us through other means.</p>
            <button onclick="this.closest('.contact-modal').remove()" style="
                background: #667eea;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 10px;
                cursor: pointer;
                margin-top: 20px;
            ">Close</button>
        `;
    } else {
        content.innerHTML = `
            <h3>Send Message</h3>
            <p>This feature will be implemented soon. For now, please contact us through other means.</p>
            <button onclick="this.closest('.contact-modal').remove()" style="
                background: #667eea;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 10px;
                cursor: pointer;
                margin-top: 20px;
            ">Close</button>
        `;
    }
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Smooth scroll for back button
function initBackButton() {
    const backBtn = document.querySelector('.back-btn');
    if (backBtn) {
        // Check if the previous page was an edit page
        const referrer = document.referrer;
        const isFromEditPage = referrer && (referrer.includes('/edit') || referrer.includes('edit-property'));
        
        backBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Add loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Going back...';
            this.disabled = true;
            
            // Use history.back() with a small delay for better UX
            setTimeout(() => {
                if (isFromEditPage) {
                    // If coming from edit page (after form submission),
                    // go back 2 pages to skip the edit page completely
                    history.go(-2);
                } else {
                    // Otherwise, use browser back
                    history.back();
                }
            }, 300);
        });
    }
}

// Gallery preview state
let gallerySlideIndex = 0;
let gallerySlideCount = 0;
let galleryImageSources = [];

function setGalleryIndex(index) {
    const mainImage = document.getElementById('galleryMainImage');
    const thumbs = document.querySelectorAll('.gallery-thumb');
    if (!mainImage || !galleryImageSources.length) return;

    gallerySlideIndex = Math.max(0, Math.min(index, galleryImageSources.length - 1));
    const src = galleryImageSources[gallerySlideIndex];

    mainImage.src = src;
    mainImage.dataset.imageSrc = src;

    thumbs.forEach((thumb, i) => {
        const active = i === gallerySlideIndex;
        thumb.classList.toggle('is-active', active);
        thumb.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');
    if (prevBtn) prevBtn.disabled = gallerySlideIndex === 0;
    if (nextBtn) nextBtn.disabled = gallerySlideIndex >= gallerySlideCount - 1;
}

function initGalleryPreview() {
    const mainImage = document.getElementById('galleryMainImage');
    const thumbs = document.querySelectorAll('.gallery-thumb');
    if (!mainImage) return;

    galleryImageSources = Array.from(document.querySelectorAll('.gallery-thumb')).map(
        (thumb) => thumb.getAttribute('data-image-src')
    );
    if (!galleryImageSources.length) {
        galleryImageSources = [mainImage.getAttribute('data-image-src') || mainImage.src];
    }
    gallerySlideCount = galleryImageSources.length;
    gallerySlideIndex = 0;

    document.getElementById('galleryMainFrame')?.addEventListener('click', () => {
        openImageModal(galleryImageSources[gallerySlideIndex]);
    });

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            const index = parseInt(thumb.getAttribute('data-gallery-index'), 10);
            if (!Number.isNaN(index)) {
                setGalleryIndex(index);
            }
        });
    });

    document.getElementById('galleryPrev')?.addEventListener('click', () => {
        if (gallerySlideIndex > 0) setGalleryIndex(gallerySlideIndex - 1);
    });

    document.getElementById('galleryNext')?.addEventListener('click', () => {
        if (gallerySlideIndex < gallerySlideCount - 1) setGalleryIndex(gallerySlideIndex + 1);
    });

    setGalleryIndex(0);
}

function initGallerySlider() {
    initGalleryPreview();
}

// Add loading states to action buttons
function initActionButtons() {
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Deleting...</span>';
            this.disabled = true;

            setTimeout(() => {
                this.innerHTML = originalHtml;
                this.disabled = false;
            }, 3000);
        });
    });
}

// Initialize all functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initLikeButtons === 'function') {
        window.initLikeButtons();
    }
    initContactButtons();
    initBackButton();
    initGallerySlider();
    initActionButtons();
    initReviewSystem();
    
    // Add fade-in animation to main content
    const mainContent = document.querySelector('.property-details');
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            mainContent.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        }, 100);
    }
});

// Review functionality
function initReviewSystem() {
    // Star rating functionality
    const starInputs = document.querySelectorAll('.star-rating input[type="radio"]');
    const ratingText = document.getElementById('ratingText');
    const ratingTexts = {
        1: 'Poor',
        2: 'Fair', 
        3: 'Good',
        4: 'Very Good',
        5: 'Excellent'
    };

    starInputs.forEach(input => {
        input.addEventListener('change', function() {
            const rating = this.value;
            ratingText.textContent = ratingTexts[rating];
            
            // Add visual feedback
            ratingText.style.color = '#f59e0b';
            ratingText.style.transform = 'scale(1.05)';
            setTimeout(() => {
                ratingText.style.transform = 'scale(1)';
            }, 200);
        });
    });

    // Character counter for review text
    const commentTextarea = document.getElementById('comment');
    const charCount = document.getElementById('charCount');
    
    if (commentTextarea && charCount) {
        commentTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            
            // Change color based on character count
            if (length > 900) {
                charCount.style.color = '#ef4444';
            } else if (length > 800) {
                charCount.style.color = '#f59e0b';
            } else {
                charCount.style.color = '#64748b';
            }
        });
    }

    // Form validation
    const reviewForm = document.querySelector('.review-form');
    const submitBtn = document.getElementById('submitReviewBtn');
    
    if (reviewForm && submitBtn) {
        reviewForm.addEventListener('submit', function(e) {
            const rating = document.querySelector('input[name="rating"]:checked');
            const comment = document.getElementById('comment');
            
            if (!rating) {
                e.preventDefault();
                showNotification('Please select a rating before submitting.', 'error');
                return;
            }
            
            if (!comment.value.trim() || comment.value.trim().length < 10) {
                e.preventDefault();
                showNotification('Please write a review with at least 10 characters.', 'error');
                return;
            }
            
            // Add loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        });
    }
}

// Show all reviews functionality
function showAllReviews() {
    // This would typically load more reviews via AJAX
    // For now, we'll just show a message
    showNotification('Loading all reviews...', 'info');
    
    // In a real implementation, you would:
    // 1. Make an AJAX request to load more reviews
    // 2. Update the reviews list
    // 3. Handle pagination
}

// Edit user review functionality
function editUserReview(reviewId) {
    // This would typically load the review data and populate the modal
    // For now, we'll just open the modal
    showNotification('Loading your review for editing...', 'info');
    openReviewModal();
    
    // In a real implementation, you would:
    // 1. Make an AJAX request to get the review data
    // 2. Populate the form with existing data
    // 3. Change the form action to update instead of create
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'error' ? '#fee2e2' : type === 'success' ? '#d1fae5' : '#dbeafe'};
        color: ${type === 'error' ? '#991b1b' : type === 'success' ? '#065f46' : '#1e40af'};
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 400px;
        border: 1px solid ${type === 'error' ? '#fca5a5' : type === 'success' ? '#a7f3d0' : '#93c5fd'};
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Enhanced review modal functionality
function openReviewModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Focus on first input
        setTimeout(() => {
            const firstStar = document.querySelector('.star-rating input[type="radio"]');
            if (firstStar) {
                firstStar.focus();
            }
        }, 100);
    }
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Reset form
        const form = modal.querySelector('.review-form');
        if (form) {
            form.reset();
            const ratingText = document.getElementById('ratingText');
            if (ratingText) {
                ratingText.textContent = 'Select a rating';
                ratingText.style.color = '#64748b';
            }
            const charCount = document.getElementById('charCount');
            if (charCount) {
                charCount.textContent = '0';
                charCount.style.color = '#64748b';
            }
        }
    }
}

// Export functions for global access
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;
window.nextImage = nextImage;
window.previousImage = previousImage;
window.openReviewModal = openReviewModal;
window.closeReviewModal = closeReviewModal;
window.showAllReviews = showAllReviews;
window.editUserReview = editUserReview;
