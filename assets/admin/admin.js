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