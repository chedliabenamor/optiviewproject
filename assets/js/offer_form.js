document.addEventListener('DOMContentLoaded', function() {
    // Use jQuery selector for consistency and to target the new multi-select field
    const productsField = $('#ProductOffer_products');
    const variantField = $('#ProductOffer_productVariants');

    // Ensure both fields exist before adding the event listener
    if (productsField.length && variantField.length) {
        productsField.on('change', function() {
            // When the product selection changes, clear the variants field.
            // This triggers Select2 to re-fetch data using the updated product IDs.
            variantField.val(null).trigger('change');
        });
    }
});

