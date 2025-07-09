

// Your custom admin JavaScript goes here
import './fontawesome.js';
document.addEventListener('DOMContentLoaded', () => {
    console.log('Custom admin JavaScript loaded!');
});
import { Application } from '@hotwired/stimulus';
import OrderItemFormController from './controllers/order-item-form_controller';

window.Stimulus = Application.start();
window.Stimulus.register('order-item-form', OrderItemFormController);