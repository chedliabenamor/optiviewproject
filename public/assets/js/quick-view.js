document.addEventListener('DOMContentLoaded', function () {
    const quickViewModal = document.querySelector('.js-modal1');
    if (!quickViewModal) return;

    let productData = {};

    // Auth helpers
    function isGuest() {
        return !(typeof window.isAuthenticated !== 'undefined' && 
               (window.isAuthenticated === true || window.isAuthenticated === 'true'));
    }

    // --- UTILITY FUNCTIONS ---
    const formatPrice = (price) => {
        try {
            if (window.Currency && typeof window.Currency.format === 'function') {
                return window.Currency.format(price);
            }
        } catch(e){}
        return `€${parseFloat(price).toFixed(2)}`;
    };

    // --- OFFER COUNTDOWN TIMER ---
    let countdownInterval = null;

    const startCountdown = (endTimestamp) => {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        const updateCountdown = () => {
            const now = new Date().getTime();
            const timeLeft = endTimestamp - now;

            if (timeLeft <= 0) {
                // Offer expired
                clearInterval(countdownInterval);
                const timerEl = quickViewModal.querySelector('.countdown-timer');
                const expiredEl = quickViewModal.querySelector('.js-countdown-expired');
                if (timerEl) timerEl.style.display = 'none';
                if (expiredEl) expiredEl.style.display = 'block';
                return;
            }

            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

            const daysEl = quickViewModal.querySelector('.js-countdown-days');
            const hoursEl = quickViewModal.querySelector('.js-countdown-hours');
            const minutesEl = quickViewModal.querySelector('.js-countdown-minutes');
            const secondsEl = quickViewModal.querySelector('.js-countdown-seconds');

            if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
            if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
            if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
            if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
        };

        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    };

    const updateOfferDisplay = (offerData) => {
        const offerBadge = quickViewModal.querySelector('.js-offer-badge');
        const offerCountdown = quickViewModal.querySelector('.js-offer-countdown');
        const originalPriceEl = quickViewModal.querySelector('.js-original-price');
        const priceEl = quickViewModal.querySelector('.js-product-price');

        if (offerData && offerData.has_offer) {
            // Show discount badge - calculate discount percentage
            const discountPercentage = Math.round(offerData.discount_percentage);
            const discountLabel = `-${discountPercentage}%`;
            
            offerBadge.querySelector('.offer-badge').textContent = discountLabel;
            offerBadge.style.display = 'block';

            // Update prices
            priceEl.textContent = formatPrice(offerData.discounted_price);
            priceEl.style.color = '#e74c3c';
            priceEl.style.fontWeight = 'bold';
            
            originalPriceEl.textContent = formatPrice(offerData.original_price);
            originalPriceEl.style.display = 'inline';

            // Show countdown if offer has end date
            if (offerData.offer_end_date && offerData.time_remaining && !offerData.time_remaining.expired) {
                let endTimestamp;
                
                // Handle different date formats
                if (typeof offerData.offer_end_date === 'string') {
                    endTimestamp = new Date(offerData.offer_end_date).getTime();
                } else if (offerData.offer_end_date.date) {
                    endTimestamp = new Date(offerData.offer_end_date.date).getTime();
                } else {
                    endTimestamp = new Date(offerData.offer_end_date).getTime();
                }
                
                offerCountdown.style.display = 'block';
                startCountdown(endTimestamp);
            } else {
                offerCountdown.style.display = 'none';
            }
        } else {
            // No offer - hide offer elements
            offerBadge.style.display = 'none';
            offerCountdown.style.display = 'none';
            originalPriceEl.style.display = 'none';
            priceEl.style.color = '';
            priceEl.style.fontWeight = '';
        }
    };

    const updateStockStatus = (quantity) => {
        const stockStatusEl = quickViewModal.querySelector('.js-stock-status');
        const addToCartBtn = quickViewModal.querySelector('.js-addcart-detail');
        const stockInfo = quickViewModal.querySelector('.js-stock-info');
        const stockQtyEl = quickViewModal.querySelector('.js-stock-quantity');
        if (!stockStatusEl || !addToCartBtn) return;

        if (typeof quantity !== 'number') {
            quantity = parseInt(quantity || 0, 10);
        }

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

        if (stockInfo && stockQtyEl) {
            stockQtyEl.textContent = String(quantity);
            stockInfo.style.display = 'block';
        }
    };

    // --- SLICK CAROUSEL HANDLING ---
    const $gallery = $('.js-modal1 .slick3');
    const $dots = $('.js-modal1 .wrap-slick3-dots');
    const $arrows = $('.js-modal1 .wrap-slick3-arrows');

    // Helper to get a safe dropdown parent inside the modal for Select2
    function getSelect2ParentFor(el) {
        // Prefer the sibling .dropDownSelect2 container; fallback to the modal
        const $el = $(el);
        const $sib = $el.next('.dropDownSelect2');
        if ($sib && $sib.length) return $sib;
        return $(quickViewModal);
    }

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

    // Reformat visible price when currency changes (if modal is open)
    document.addEventListener('currencyChanged', function(){
        try {
            if (!quickViewModal.classList.contains('show-modal1')) return;
            var priceEl = quickViewModal.querySelector('.js-product-price');
            var originalEl = quickViewModal.querySelector('.js-original-price');
            if (!priceEl) return;
            // Determine current context: variant selected or main
            var variantId = quickViewModal.querySelector('.js-addcart-detail')?.dataset?.variantId || '';
            function applyPair(discounted, original){
                if (original != null && original !== '') {
                    priceEl.textContent = formatPrice(discounted);
                    priceEl.style.color = '#e74c3c';
                    priceEl.style.fontWeight = 'bold';
                    if (originalEl) { originalEl.style.display = 'inline'; originalEl.textContent = formatPrice(original); }
                } else {
                    priceEl.textContent = formatPrice(discounted);
                    priceEl.style.color = '';
                    priceEl.style.fontWeight = '';
                    if (originalEl) originalEl.style.display = 'none';
                }
            }
            if (variantId) {
                var v = (productData.productVariants || []).find(function(vv){ return String(vv.id) === String(variantId); });
                if (v) {
                    if (v.offer && v.offer.has_offer) applyPair(v.offer.discounted_price, v.offer.original_price);
                    else applyPair(v.price, null);
                    return;
                }
            }
            // Fallback to main product
            if (productData.offer) applyPair(productData.offer.discounted_price, productData.offer.original_price);
            else applyPair(productData.price, null);
        } catch(e){}
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
        
        // Update offer display for variant
        if (variant.offer) {
            updateOfferDisplay(variant.offer);
        } else {
            // Fallback to regular price display
            quickViewModal.querySelector('.js-product-price').textContent = formatPrice(variant.price);
            updateOfferDisplay({ has_offer: false });
        }
        
        updateStockStatus(variant.quantityInStock);
        initSlick(variant.productVariantImages.length > 0 ? variant.productVariantImages : [{ imageUrl: productData.overviewImage, altText: 'Product Overview' }]);
        const addToCartBtn = quickViewModal.querySelector('.js-addcart-detail');
        addToCartBtn.dataset.variantId = variant.id;
        
        // Update wishlist status for the selected variant
        checkWishlistStatus(productData.id, variant.id);
    }

    function handleVariantSelection() {
        const selectors = quickViewModal.querySelectorAll('.js-variant-selector');
        const selectedOptions = {};
        selectors.forEach(s => { selectedOptions[s.name] = s.value; });

        const matchedVariant = productData.productVariants.find(v => 
            (!selectedOptions.color || v.color?.name === selectedOptions.color) &&
            (!selectedOptions.style || v.style?.name === selectedOptions.style) &&
            (!selectedOptions.genre || v.genre?.name === selectedOptions.genre)
        );

        if (matchedVariant) {
            updateVariantDetails(matchedVariant);
        }
    }

    function setupVariantSelectors(variants) {
        const colorContainer = quickViewModal.querySelector('.js-color-selector');
        const colorSelect = colorContainer.querySelector('.js-select-color');
        
        // Tear down any previous Select2 & handlers to avoid stale state between products
        if (typeof window.jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
            const $select = $(colorSelect);
            $select.off('change', handleVariantSelectionChange);
            if ($select.hasClass('select2-hidden-accessible')) {
                try { $select.select2('destroy'); } catch (e) { /* ignore */ }
            }
        } else {
            colorSelect.removeEventListener('change', handleVariantSelectionChange);
        }

        // Clear existing options
        colorSelect.innerHTML = '';
        
        if (!variants || variants.length === 0) {
            colorContainer.style.display = 'none';
            return;
        }

        // Add default 'Main Product' option (selected by default)
        const mainOption = document.createElement('option');
        mainOption.value = 'main';
        const mainLabel = (productData.productColor && productData.productColor.name) ? productData.productColor.name : 'Main Product';
        mainOption.textContent = mainLabel;
        // Use overview image or first product model image as thumbnail
        let mainThumb = productData.overviewImage;
        if (!mainThumb && productData.productModelImages && productData.productModelImages.length > 0) {
            mainThumb = productData.productModelImages[0].imageUrl;
        }
        if (mainThumb) {
            mainOption.setAttribute('data-img', mainThumb);
        }
        colorSelect.appendChild(mainOption);

        // Collect unique colors from variants
        const colors = new Set();
        variants.forEach(variant => {
            if (variant.color && variant.color.name) {
                colors.add(JSON.stringify({
                    id: variant.color.id,
                    name: variant.color.name,
                    variantId: variant.id
                }));
            }
        });

        // Populate color selector with color options
        Array.from(colors).forEach(colorStr => {
            const color = JSON.parse(colorStr);
            const option = document.createElement('option');
            option.value = color.id;
            option.textContent = color.name;
            option.setAttribute('data-variant-id', color.variantId);
            
            // Find variant for this color to get thumbnail
            const variant = variants.find(v => v.color && v.color.id === color.id);
            if (variant && variant.productVariantImages && variant.productVariantImages.length > 0) {
                option.setAttribute('data-img', variant.productVariantImages[0].imageUrl);
            }
            
            colorSelect.appendChild(option);
        });

        colorContainer.style.display = 'block';

        // Initialize Select2 for color
        if (typeof window.jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
            const $select = $(colorSelect);
            $select.select2({
                templateResult: formatVariantOption,
                templateSelection: formatVariantOption,
                minimumResultsForSearch: -1,
                dropdownParent: getSelect2ParentFor(colorSelect),
                width: '100%'
            });
        }

        // Add change event listener
        if (typeof window.jQuery !== 'undefined') {
            $(colorSelect).on('change', handleVariantSelectionChange);
        } else {
            colorSelect.addEventListener('change', handleVariantSelectionChange);
        }

        // Select 'Main Product' by default and keep current main product info
        colorSelect.value = 'main';
        $(colorSelect).trigger('change');
    }

    function formatVariantOption(opt) {
        if (!opt.id) return opt.text;
        const $opt = $(opt.element);
        const img = $opt.data('img');
        const text = opt.text;
        
        if (img) {
            return $('<span class="d-flex align-items-center"><img src="'+img+'" style="width:28px;height:28px;object-fit:cover;border-radius:4px;margin-right:8px;" />'+text+'</span>');
        }
        return text;
    }

    function handleVariantSelectionChange() {
        const colorSelect = quickViewModal.querySelector('.js-select-color');
        const selectedColor = colorSelect ? colorSelect.value : null;
        
        // Find matching variant based on selected color
        let selectedVariant = null;
        
        if (selectedColor && selectedColor !== 'Choose an option' && selectedColor !== 'main') {
            selectedVariant = productData.productVariants.find(v => 
                v.color && v.color.id == selectedColor
            );
        }
        
        if (selectedVariant) {
            // Use centralized updater for variant
            updateVariantDetails(selectedVariant);

            // Update meta information (style/genre) to reflect variant specifics if available
            const updateMetaField = (metaName, value) => {
                const container = quickViewModal.querySelector(`.js-meta-${metaName}`);
                if (!container) return;

                if (value && value !== '-' && value !== '') {
                    container.querySelector(`.js-product-${metaName}`).textContent = value;
                    container.style.display = 'flex';
                } else {
                    container.style.display = 'none';
                }
            };
            
            updateMetaField('style', selectedVariant.style?.name || productData.originalStyle);
            updateMetaField('genre', selectedVariant.genre?.name || productData.originalGenre);
        } else {
            // Reset to original product data
            if (productData.offer) {
                updateOfferDisplay(productData.offer);
            } else {
                updateOfferDisplay({ has_offer: false });
                quickViewModal.querySelector('.js-product-price').textContent = formatPrice(productData.price);
            }
            updateStockStatus(productData.quantityInStock || 0);
            
            // Reset images to product images
            const allImages = [];
            if (productData.overviewImage) allImages.push({ imageUrl: productData.overviewImage, altText: 'Product Overview' });
            if (productData.productModelImages) allImages.push(...productData.productModelImages);
            initSlick(allImages);
            
            // Reset add to cart button
            const addToCartBtn = quickViewModal.querySelector('.js-addcart-detail');
            addToCartBtn.dataset.variantId = '';
            
            // Reset wishlist status
            checkWishlistStatus(productData.id, null);
        }
    }

    // --- MODAL POPULATION ---
    function populateQuickViewModal(data) {
        productData = data;

        // Basic Info
        quickViewModal.querySelector('.js-product-name').textContent = data.name || '';
        quickViewModal.querySelector('.js-product-description').innerHTML = data.description || '';
        
        // Category above product name
        const categoryTopEl = quickViewModal.querySelector('.js-product-category-top');
        if (categoryTopEl) {
            if (data.category) {
                categoryTopEl.textContent = data.category;
                categoryTopEl.style.display = 'block';
            } else {
                categoryTopEl.style.display = 'none';
            }
        }
        
        // Brand below product name
        const brandBelowEl = quickViewModal.querySelector('.js-product-brand-below');
        if (brandBelowEl) {
            if (data.brand) {
                brandBelowEl.textContent = data.brand;
                brandBelowEl.style.display = 'block';
            } else {
                brandBelowEl.style.display = 'none';
            }
        }

        // Check wishlist status
        checkWishlistStatus(data.id);
        
        // Meta Info (conditionally displayed) - These should remain static and show main product info
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

        // Store original product metadata to prevent it from being overwritten by variants
        productData.originalStyle = data.style;
        productData.originalShape = data.shape;
        productData.originalGenre = data.genre;

        updateMetaField('category', data.category);
        updateMetaField('brand', data.brand);
        updateMetaField('style', data.style);
        updateMetaField('shape', data.shape);
        updateMetaField('genre', data.genre);
        updateMetaField('loyalty-points', data.loyaltyPoints > 0 ? data.loyaltyPoints : null);
        quickViewModal.querySelector('.js-addcart-detail').dataset.productId = data.id;

        // Always show main product images initially
        const allImages = [];
        if (data.overviewImage) allImages.push({ imageUrl: data.overviewImage, altText: 'Product Overview' });
        if (data.productModelImages) allImages.push(...data.productModelImages);
        initSlick(allImages);

        // Handle offer display for main product
        if (data.offer) {
            updateOfferDisplay(data.offer);
        } else {
            updateOfferDisplay({ has_offer: false });
            quickViewModal.querySelector('.js-product-price').textContent = formatPrice(data.price);
        }

        // Debug: Log variant data
        console.log('Product variants:', data.productVariants);
        
        // Always try to setup variant selectors, even if array is empty (for debugging)
        setupVariantSelectors(data.productVariants || []);
        updateStockStatus(data.quantityInStock);

        // Check initial wishlist status
        checkWishlistStatus(data.id);
        
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
            // Clear countdown timer when modal closes
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }

            // Destroy Select2 on close to prevent stale state on next open
            const colorSelect = quickViewModal.querySelector('.js-select-color');
            if (colorSelect && typeof window.jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                const $select = $(colorSelect);
                $select.off('change', handleVariantSelectionChange);
                if ($select.hasClass('select2-hidden-accessible')) {
                    try { $select.select2('destroy'); } catch (e) { /* ignore */ }
                }
            }
        }
    });

    // --- WISHLIST FUNCTIONS ---
    function checkWishlistStatus(productId, variantId = null) {
        if (isGuest()) {
            try {
                const items = JSON.parse(localStorage.getItem('wishlist') || '[]');
                const found = items.some(it => String(it.id) === String(productId) && String(it.variantId || '') === String(variantId || ''));
                updateWishlistButton(found);
            } catch (e) {
                updateWishlistButton(false);
            }
            return;
        }
        const url = `/api/wishlist/check/${productId}${variantId ? `?variantId=${variantId}` : ''}`;
        fetch(url)
            .then(response => response.json())
            .then(data => { updateWishlistButton(data.inWishlist); })
            .catch(() => { updateWishlistButton(false); });
    }

    function updateWishlistButton(isInWishlist) {
        const wishlistBtn = quickViewModal.querySelector('.js-addwish-detail');
        const icon = wishlistBtn.querySelector('i');
        
        if (isInWishlist) {
            icon.classList.remove('zmdi-favorite-outline');
            icon.classList.add('zmdi-favorite');
            wishlistBtn.setAttribute('data-tooltip', 'Remove from Wishlist');
        } else {
            icon.classList.remove('zmdi-favorite');
            icon.classList.add('zmdi-favorite-outline');
            wishlistBtn.setAttribute('data-tooltip', 'Add to Wishlist');
        }
    }

    function toggleWishlist(productId, variantId = null) {
        // Helper to gather display price info from modal
        function readPriceInfo() {
            const priceEl = quickViewModal.querySelector('.js-product-price');
            const originalEl = quickViewModal.querySelector('.js-original-price');
            const priceText = priceEl ? priceEl.textContent.trim() : '';
            const originalText = (originalEl && originalEl.style.display !== 'none') ? originalEl.textContent.trim() : '';
            const currency = priceText.replace(/[0-9.,\s-]/g, '') || '€';
            function toNumber(txt){ try { return parseFloat(String(txt).replace(/[^0-9.\-]/g,'')); } catch(e){ return null; } }
            return {
                currency: currency,
                price: toNumber(priceText),
                originalPrice: originalText ? toNumber(originalText) : null,
                hasOffer: !!originalText
            };
        }

        if (isGuest()) {
            let items = [];
            try { items = JSON.parse(localStorage.getItem('wishlist') || '[]'); } catch(e) {}
            const idx = items.findIndex(it => String(it.id) === String(productId) && String(it.variantId || '') === String(variantId || ''));
            let action = 'added';
            if (idx >= 0) {
                items.splice(idx, 1);
                action = 'removed';
            } else {
                const info = readPriceInfo();
                // Compose a name including color (if selected) to differentiate visually
                let name = productData.name || 'Product';
                // Try to append selected color if visible
                const colorSel = quickViewModal.querySelector('.js-select-color');
                if (colorSel && colorSel.value && colorSel.value !== 'main' && colorSel.options[colorSel.selectedIndex]) {
                    const colorName = colorSel.options[colorSel.selectedIndex].textContent.trim();
                    if (colorName && colorName.toLowerCase() !== 'choose an option') {
                        name = name + ' - ' + colorName;
                    }
                }
                const imgEl = quickViewModal.querySelector('.slick3 .item-slick3 img');
                const image = imgEl ? imgEl.getAttribute('src') : (productData.overviewImage || '');
                items.push({
                    id: productId,
                    variantId: variantId || '',
                    name: name,
                    image: image,
                    price: info.price,
                    originalPrice: info.originalPrice,
                    hasOffer: info.hasOffer,
                    currency: info.currency,
                    priceFormatted: info.currency + (info.price != null ? info.price.toFixed(2) : ''),
                    originalPriceFormatted: info.originalPrice != null ? (info.currency + info.originalPrice.toFixed(2)) : ''
                });
            }
            localStorage.setItem('wishlist', JSON.stringify(items));
            // Update header wishlist count for guests
            try {
                var count = items.length;
                document.querySelectorAll('.icon-header-noti').forEach(function(icon) {
                    if (icon.querySelector('.zmdi-favorite-outline') || icon.querySelector('.zmdi-favorite')) {
                        icon.setAttribute('data-notify', count);
                    }
                });
            } catch(e){}
            // Notify other parts of the app (home/listing) to refresh heart icons
            try {
                document.dispatchEvent(new CustomEvent('wishlistUpdated', { detail: { items: items } }));
            } catch(e){}
            const inWishlist = action === 'added';
            updateWishlistButton(inWishlist);
            const productName = (items.find(it => it.id == productId && String(it.variantId||'') === String(variantId||'')) || {}).name || (productData.name || 'Product');
            const info = readPriceInfo();
            if (typeof Swal !== 'undefined') {
                if (action === 'added') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Added to Wishlist',
                        html: '<div style="font-weight:600">' + (productName || 'Product') + '</div>' +
                              '<div style="margin-top:6px">' + (info.hasOffer && info.originalPrice != null ? 
                                ('<span style="color:#e74c3c;font-weight:700">' + info.currency + (info.price != null ? info.price.toFixed(2) : '') + '</span>' +
                                 '<span style="margin-left:8px;color:#777;text-decoration:line-through">' + info.currency + (info.originalPrice != null ? info.originalPrice.toFixed(2) : '') + '</span>') :
                                ('<span>' + info.currency + (info.price != null ? info.price.toFixed(2) : '') + '</span>')) +
                              '</div>' +
                              '<div style="margin-top:8px;color:#333">is added to wishlist!</div>',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Removed from Wishlist', text: (productName || 'Product') + ' has been removed from your wishlist.', showConfirmButton: false, timer: 1200 });
                }
            } else if (typeof swal !== 'undefined') {
                if (action === 'added') {
                    swal(productName || 'Product', 'is added to wishlist!', 'success');
                } else {
                    swal(productName || 'Product', 'is removed from wishlist!', 'info');
                }
            }
            return;
        }

        // Authenticated users -> use API
        fetch(`/api/wishlist/toggle/${productId}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ variantId })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            updateWishlistButton(data.inWishlist);
            // Broadcast update so product cards/home can refresh wishlist icons
            try { document.dispatchEvent(new CustomEvent('wishlistUpdated')); } catch(e){}
            const productName = productData.name || 'Product';
            if (typeof Swal !== 'undefined') {
                if (data.action === 'added') {
                    Swal.fire({ icon: 'success', title: 'Added to Wishlist', text: productName + ' has been added to your wishlist.', showConfirmButton: false, timer: 1500 });
                } else {
                    Swal.fire({ icon: 'info', title: 'Removed from Wishlist', text: productName + ' has been removed from your wishlist.', showConfirmButton: false, timer: 1200 });
                }
            } else if (typeof swal !== 'undefined') {
                if (data.action === 'added') {
                    swal(productName || 'Product', 'is added to wishlist!', 'success');
                } else {
                    swal(productName || 'Product', 'is removed from wishlist!', 'info');
                }
            }
        })
        .catch(() => {});
    }

    // Quantity increment/decrement functionality and actions inside modal
    quickViewModal.addEventListener('click', function (e) {
        // Handle quantity decrease
        if (e.target.closest('.btn-num-product-down')) {
            e.preventDefault();
            const btn = e.target.closest('.btn-num-product-down');
            const input = btn.nextElementSibling;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
            return;
        }
        
        // Handle quantity increase with stock cap
        if (e.target.closest('.btn-num-product-up')) {
            e.preventDefault();
            const btn = e.target.closest('.btn-num-product-up');
            const input = btn.previousElementSibling;
            const currentValue = parseInt(input.value) || 1;
            // Determine available from DOM (preferred)
            let available = null;
            try {
                const stockQtyEl = quickViewModal.querySelector('.js-stock-quantity');
                if (stockQtyEl && stockQtyEl.textContent) {
                    const n = parseInt(stockQtyEl.textContent.replace(/[^0-9\-]/g, ''));
                    if (!isNaN(n)) available = n;
                }
            } catch(_){}
            const next = currentValue + 1;
            if (available !== null && next > available) {
                const msg = available === 1 ? 'Only 1 item is available. Please update your quantity.' : `Only ${available} items are available. Please update your quantity.`;
                if (typeof Swal !== 'undefined') { Swal.fire('Stock limit', msg, 'warning'); } else { alert(msg); }
                input.value = available > 0 ? available : 1;
                return;
            }
            input.value = next;
            return;
        }

        // Handle direct typing in quantity input (enforce stock cap)
        const qtyInput = e.target.closest('.num-product');
        if (qtyInput && e.type === 'change') {
            try {
                let val = parseInt(qtyInput.value) || 1;
                if (val < 1) val = 1;
                let available = null;
                const stockQtyEl = quickViewModal.querySelector('.js-stock-quantity');
                if (stockQtyEl && stockQtyEl.textContent) {
                    const n = parseInt(stockQtyEl.textContent.replace(/[^0-9\-]/g, ''));
                    if (!isNaN(n)) available = n;
                }
                if (available !== null && val > available) {
                    const msg = available === 1 ? 'Only 1 item is available. Please update your quantity.' : `Only ${available} items are available. Please update your quantity.`;
                    if (typeof Swal !== 'undefined') { Swal.fire('Stock limit', msg, 'warning'); } else { alert(msg); }
                    qtyInput.value = available > 0 ? available : 1;
                } else {
                    qtyInput.value = val;
                }
            } catch(_){}
            return;
        }

        // Handle Add to Cart
        const addToCartBtn = e.target.closest('.js-addcart-detail');
        if (addToCartBtn) {
            e.preventDefault();
            e.stopPropagation();

            try {
                // Block if out of stock (button disabled by updateStockStatus)
                if (addToCartBtn.disabled) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Out of Stock', text: 'This product is currently unavailable.', timer: 1200, showConfirmButton: false });
                    }
                    return;
                }
                const productId = addToCartBtn.dataset.productId;
                const variantId = addToCartBtn.dataset.variantId || '';
                const qtyInput = quickViewModal.querySelector('.num-product');
                const quantity = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;

                // Final guard: don't allow exceeding available
                let available = null;
                try {
                    const stockQtyEl = quickViewModal.querySelector('.js-stock-quantity');
                    if (stockQtyEl && stockQtyEl.textContent) {
                        const n = parseInt(stockQtyEl.textContent.replace(/[^0-9\-]/g, ''));
                        if (!isNaN(n)) available = n;
                    }
                } catch(_){}
                if (available !== null && quantity > available) {
                    const msg = available === 1 ? 'Only 1 item is available. Please update your quantity.' : `Only ${available} items are available. Please update your quantity.`;
                    if (typeof Swal !== 'undefined') { Swal.fire('Stock limit', msg, 'warning'); } else { alert(msg); }
                    if (qtyInput) qtyInput.value = available > 0 ? available : 1;
                    return;
                }

                // Read displayed name
                const nameEl = quickViewModal.querySelector('.js-product-name');
                const productName = nameEl ? nameEl.textContent.trim() : '';

                // Read displayed price (already formatted in UI)
                const priceEl = quickViewModal.querySelector('.js-product-price');
                let priceNum = 0;
                if (priceEl) {
                    try { priceNum = parseFloat(String(priceEl.textContent).replace(/[^0-9.\-]/g, '')) || 0; } catch(_) { priceNum = 0; }
                }

                // Use the first/main image visible in the slider
                let productImage = '';
                const currentImg = quickViewModal.querySelector('.slick3 .item-slick3.slick-current img') || quickViewModal.querySelector('.slick3 .item-slick3 img');
                if (currentImg) { productImage = currentImg.getAttribute('src') || ''; }
                if (!productImage && productData && productData.overviewImage) { productImage = productData.overviewImage; }

                // Derive variant display name (e.g., selected color) if available
                let variantName = '';
                const colorSel = quickViewModal.querySelector('.js-select-color');
                if (colorSel && colorSel.value && colorSel.value !== 'main' && colorSel.options[colorSel.selectedIndex]) {
                    variantName = colorSel.options[colorSel.selectedIndex].textContent.trim();
                }

                // Dispatch global addToCart so the cart panel logic handles storage and UI
                const addToCartEvent = new CustomEvent('addToCart', {
                    detail: {
                        productId: productId,
                        productName: productName,
                        productPrice: priceNum,
                        productImage: productImage,
                        quantity: quantity,
                        variantId: variantId,
                        variantName: variantName
                    },
                    bubbles: true
                });
                document.dispatchEvent(addToCartEvent);

                // Optional: immediate feedback
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ position: 'top-center', icon: 'success', title: 'Added to cart!', showConfirmButton: false, timer: 1000 });
                }
            } catch (err) {
                console.error('Failed to add to cart from quick-view:', err);
            }
            return;
        }
        
        // Handle wishlist
        const wishlistBtn = e.target.closest('.js-addwish-detail');
        if (wishlistBtn) {
            e.preventDefault();
            e.stopPropagation();
            const productId = quickViewModal.querySelector('.js-addcart-detail').dataset.productId;
            const variantId = quickViewModal.querySelector('.js-addcart-detail').dataset.variantId || null;
            
            toggleWishlist(productId, variantId);
        }
    });
});