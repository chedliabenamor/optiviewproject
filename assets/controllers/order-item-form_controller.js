import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['productSelect', 'variantSelect'];

    connect() {
        // If a product is already selected on form load (e.g., on edit), trigger the variant load.
        if (this.productSelectTarget.value) {
            this.onProductSelect();
        }
    }

    async onProductSelect() {
        console.log('Product selected:', this.productSelectTarget.value);
        const productId = this.productSelectTarget.value;
        this.variantSelectTarget.innerHTML = '<option>Loading...</option>';

        if (!productId) {
            this.variantSelectTarget.innerHTML = '<option>Select a product first</option>';
            return;
        }

        try {
            const response = await fetch(`/api/product/${productId}/variants`);
            const variants = await response.json();

            let options = '<option value="">Select a variant</option>';
            variants.forEach(variant => {
                options += `<option value="${variant.id}">${variant.text}</option>`;
            });
            
            this.variantSelectTarget.innerHTML = options;
        } catch (error) {
            console.error('Error loading variants:', error);
            this.variantSelectTarget.innerHTML = '<option value="">Error loading variants</option>';
        }

        this.variantSelectTarget.innerHTML = options;
    }
}
