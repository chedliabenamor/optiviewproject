document.addEventListener('DOMContentLoaded', function () {
    const quickViewModal = document.querySelector('.js-modal1');
    if (!quickViewModal) return;

    let productData = {};

    // --- UTILITY FUNCTIONS ---
    const formatPrice = (price, currency = 'EUR') => new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(price);

    const updateStockStatus = (quantity) => {
        const stockStatusEl = quickViewModal.querySelector('.js-stock-status');
        const addToCartBtn = quickViewModal.querySelector('.js-addcart-detail');
        if (!stockStatusEl || !addToCartBtn) return;

        if (quantity > 10) {
            stockStatusEl.textContent = 'Available';
            stockStatusEl.style.color = '#28a745';
            addToCartBtn.disabled = false;
        } else if (quantity > 0) {
            stockStatusEl.textContent = 'Low Stock';
            stockStatusEl.style.color = '#fd7e14';
            addToCartBtn.disabled = false;
        } else {
            stockStatusEl.textContent = 'Out of Stock';
            stockStatusEl.style.color = '#dc3545';
            addToCartBtn.disabled = true;
        }
    };

    // --- SLICK CAROUSEL HANDLING ---
    const $gallery = $('.js-modal1 .slick3');
    const $dots = $('.js-modal1 .wrap-slick3-dots');
    const $arrows = $('.js-modal1 .wrap-slick3-arrows');

    function initSlick(images) {
        if ($gallery.hasClass('slick-initialized')) {
            $gallery.slick('unslick');
        }
        $gallery.empty();

        if (!images || images.length === 0) {
            const defaultImage = productData.overviewImage || '/path/to/default-placeholder.jpg';
            $gallery.html(`<div><img src="${defaultImage}" alt="No Image Available"></div>`);
        } else {
            images.forEach(image => {
                const item = `
                    <div class="item-slick3" data-thumb="${image.imageUrl}">
                        <div class="wrap-pic-w pos-relative">
                            <img src="${image.imageUrl}" alt="${image.altText || 'Product Image'}">
                            <a class="flex-c-m size-108 how-pos1 bor0 fs-16 cl10 bg0 hov-btn3 trans-04 lightbox-gallery" href="${image.imageUrl}">
                                <i class="fa fa-expand"></i>
                            </a>
                        </div>
                    </div>`;
                $gallery.append(item);
            });
        }

        $gallery.slick({
            slidesToShow: 1, slidesToScroll: 1, fade: true, dots: true, appendDots: $dots,
            dotsClass: 'slick3-dots', arrows: true, appendArrows: $arrows,
            prevArrow: '<button class="arrow-slick3 prev-slick3"><i class="fa fa-angle-left"></i></button>',
            nextArrow: '<button class="arrow-slick3 next-slick3"><i class="fa fa-angle-right"></i></button>',
            customPaging: (slider, i) => `<img src="${$(slider.$slides[i]).data('thumb')}">`
        });
    }

    // --- VARIANT HANDLING ---
    function updateVariantDetails(variant) {
        if (!variant) return;
        quickViewModal.querySelector('.js-product-price').textContent = formatPrice(variant.price, productData.currency);
        updateStockStatus(variant.stock);
        initSlick(variant.productVariantImages.length > 0 ? variant.productVariantImages : [{ imageUrl: productData.overviewImage, altText: 'Product Overview' }]);
        const addToCartBtn = quickViewModal.querySelector('.js-addcart-detail');
        addToCartBtn.dataset.variantId = variant.id;
    }

    function handleVariantSelection() {
        const selectors = quickViewModal.querySelectorAll('.js-variant-selector');
        const selectedOptions = {};
        selectors.forEach(s => { selectedOptions[s.name] = s.value; });

        const matchedVariant = productData.product_variants.find(v => 
            (!selectedOptions.color || v.color?.name === selectedOptions.color) &&
            (!selectedOptions.style || v.style?.name === selectedOptions.style) &&
            (!selectedOptions.genre || v.genre?.name === selectedOptions.genre)
        );

        if (matchedVariant) {
            updateVariantDetails(matchedVariant);
        }
    }

    function setupVariantSelectors(variants) {
        const container = quickViewModal.querySelector('.js-variant-selectors-container');
        container.innerHTML = '';
        if (!variants || variants.length === 0) return;

        const attributes = { color: [], style: [], genre: [] };
        variants.forEach(v => {
            if (v.color && !attributes.color.includes(v.color.name)) attributes.color.push(v.color.name);
            if (v.style && !attributes.style.includes(v.style.name)) attributes.style.push(v.style.name);
            if (v.genre && !attributes.genre.includes(v.genre.name)) attributes.genre.push(v.genre.name);
        });

        Object.keys(attributes).forEach(attr => {
            if (attributes[attr].length > 0) {
                const select = document.createElement('select');
                select.className = 'js-variant-selector';
                select.name = attr;
                select.innerHTML = `<option value="">Choose a ${attr}</option>` + attributes[attr].map(opt => `<option value="${opt}">${opt}</option>`).join('');
                
                const wrapper = document.createElement('div');
                wrapper.className = 'rs1-select2 bor8 bg0 m-b-12';
                wrapper.innerHTML = `<label class="stext-102 cl3 m-r-15">${attr.charAt(0).toUpperCase() + attr.slice(1)}</label>`;
                wrapper.appendChild(select);
                container.appendChild(wrapper);
            }
        });

        container.addEventListener('change', handleVariantSelection);
    }

    // --- MODAL POPULATION ---
    function populateQuickViewModal(data) {
        productData = data;

        // Basic Info
        quickViewModal.querySelector('.js-product-name').textContent = data.name || '';
        quickViewModal.querySelector('.js-product-description').innerHTML = data.description || '';
        // Meta Info (conditionally displayed)
        const updateMetaField = (metaName, value) => {
            const container = quickViewModal.querySelector(`.js-meta-${metaName}`);
            if (!container) return;

            if (value) {
                container.querySelector(`.js-product-${metaName}`).textContent = value;
                container.style.display = 'flex'; // Use flex for better alignment
            } else {
                container.style.display = 'none';
            }
        };

        updateMetaField('category', data.category?.name);
        updateMetaField('brand', data.brand?.name);
        updateMetaField('style', data.style?.name);
        updateMetaField('shape', data.shape?.name);
        updateMetaField('genre', data.genre?.name);
        updateMetaField('loyalty-points', data.loyaltyPoints > 0 ? data.loyaltyPoints : null);
        quickViewModal.querySelector('.js-addcart-detail').dataset.productId = data.id;

        if (data.product_variants && data.product_variants.length > 0) {
            setupVariantSelectors(data.product_variants);
            updateVariantDetails(data.product_variants[0]); // Show first variant by default
        } else {
            quickViewModal.querySelector('.js-variant-selectors-container').innerHTML = '';
            quickViewModal.querySelector('.js-product-price').textContent = formatPrice(data.price, data.currency);
            updateStockStatus(data.quantityInStock);
            const allImages = [];
            if (data.overviewImage) allImages.push({ imageUrl: data.overviewImage, altText: 'Product Overview' });
            if (data.productModelImages) allImages.push(...data.productModelImages);
            initSlick(allImages);
        }

        // Show Modal
        quickViewModal.classList.add('show-modal1');
    }

    // --- EVENT LISTENERS ---
    document.addEventListener('click', function (e) {
        const quickViewBtn = e.target.closest('.js-show-modal1');
        if (!quickViewBtn) return;

        e.preventDefault();
        const productId = quickViewBtn.dataset.productId;
        if (!productId) return;

        quickViewModal.classList.add('loading');

        fetch(`/api/products/${productId}/quick-view`)
            .then(response => response.ok ? response.json() : Promise.reject('API Error'))
            .then(populateQuickViewModal)
            .catch(error => console.error('Failed to load quick view data:', error))
            .finally(() => quickViewModal.classList.remove('loading'));
    });

    // Close modal
    quickViewModal.addEventListener('click', function (e) {
        if (e.target.classList.contains('js-hide-modal1') || e.target.closest('.js-hide-modal1')) {
            quickViewModal.classList.remove('show-modal1');
        }
    });
});