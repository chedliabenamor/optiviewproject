import 'bootstrap';

import { Application } from '@hotwired/stimulus';
import OrderItemFormController from '../controllers/order-item-form_controller';

// Optional debug log
document.addEventListener('DOMContentLoaded', () => {
    console.log('Custom admin JavaScript loaded!');
});

// Start Stimulus
window.Stimulus = Application.start();
window.Stimulus.register('order-item-form', OrderItemFormController);

document.addEventListener('DOMContentLoaded', () => {
    const imageGalleryModalEl = document.getElementById('imageGalleryModal');
    if (imageGalleryModalEl) {
        const modalCarousel = new bootstrap.Carousel(imageGalleryModalEl.querySelector('#modalCarousel'), {
            interval: false // Disable auto-sliding
        });

        imageGalleryModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (button) {
                const slideToIndex = parseInt(button.getAttribute('data-bs-slide-to'), 10);
                if (!isNaN(slideToIndex)) {
                    modalCarousel.to(slideToIndex);
                }
            }
        });
    }
});