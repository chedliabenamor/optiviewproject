document.addEventListener('DOMContentLoaded', function () {
    const quickViewModal = document.querySelector('.js-modal1');
    if (!quickViewModal) return;

    let productData = {};

    // --- UTILITY FUNCTIONS ---
    const formatPrice = (price) => {
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
        const url = `/api/wishlist/check/${productId}${variantId ? `?variantId=${variantId}` : ''}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                updateWishlistButton(data.inWishlist);
            })
            .catch(error => {
                console.error('Failed to check wishlist status:', error);
            });
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
        fetch(`/api/wishlist/toggle/${productId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ variantId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateWishlistButton(data.inWishlist);
                
                // Show user feedback - same style as homepage cards
                const productName = productData.name || 'Product';
                
                if (typeof Swal !== 'undefined') {
                    if (data.action === 'added') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Added to Wishlist',
                            text: productName + ' has been added to your wishlist.',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Removed from Wishlist',
                            text: productName + ' has been removed from your wishlist.',
                            showConfirmButton: false,
                            timer: 1200
                        });
                    }
                } else if (typeof swal !== 'undefined') {
                    if (data.action === 'added') {
                        swal(productName, "is added to wishlist!", "success");
                    } else {
                        swal(productName, "is removed from wishlist!", "info");
                    }
                } else {
                    const message = data.action === 'added' ? 
                        productName + ' has been added to your wishlist.' : 
                        productName + ' has been removed from your wishlist.';
                    alert(message);
                }
            } else {
                console.error('Failed to update wishlist:', data.message);
            }
        })
        .catch(error => {
            console.error('Failed to toggle wishlist:', error);
        });
    }

    // Quantity increment/decrement functionality
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
        
        // Handle quantity increase
        if (e.target.closest('.btn-num-product-up')) {
            e.preventDefault();
            const btn = e.target.closest('.btn-num-product-up');
            const input = btn.previousElementSibling;
            const currentValue = parseInt(input.value) || 1;
            input.value = currentValue + 1;
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