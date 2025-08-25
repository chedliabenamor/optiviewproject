document.addEventListener('DOMContentLoaded', function () {
    const quickViewModal = document.querySelector('.js-modal1');
    if (!quickViewModal) return;

    let productData = {};

    // --- UTILITY FUNCTIONS ---
    const formatPrice = (price, currency = 'EUR') => {
        const symbol = currency === 'EUR' ? '€' : currency;
        return `${symbol}${parseFloat(price).toFixed(2)}`;
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
            priceEl.textContent = formatPrice(offerData.discounted_price, productData.currency);
            priceEl.style.color = '#e74c3c';
            priceEl.style.fontWeight = 'bold';
            
            originalPriceEl.textContent = formatPrice(offerData.original_price, productData.currency);
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
        
        // Update offer display for variant
        if (variant.offer) {
            updateOfferDisplay(variant.offer);
        } else {
            // Fallback to regular price display
            quickViewModal.querySelector('.js-product-price').textContent = formatPrice(variant.price, variant.currency);
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
        const container = quickViewModal.querySelector('.js-variant-selectors-container');
        container.innerHTML = '';
        
        console.log('Setting up variant selectors for:', variants);
        
        if (!variants || variants.length === 0) {
            console.log('No variants found, showing debug selector');
            // Show a test selector for debugging
            const testWrapper = document.createElement('div');
            testWrapper.className = 'flex-w flex-r-m p-b-10';
            testWrapper.innerHTML = `
                <div class="size-203 flex-c-m respon6">
                    Test Selector
                </div>
                <div class="size-204 respon6-next">
                    <div class="rs1-select2 bor8 bg0">
                        <select class="js-select2" name="test">
                            <option value="">No variants available</option>
                        </select>
                        <div class="dropDownSelect2"></div>
                    </div>
                </div>
            `;
            container.appendChild(testWrapper);
            return;
        }

        // Collect all unique attributes from variants
        const attributes = { 
            color: new Set(), 
            style: new Set(), 
            genre: new Set(),
            size: new Set(),
            material: new Set()
        };
        
        variants.forEach(variant => {
            console.log('Processing variant:', variant);
            
            // Check various possible attribute names
            if (variant.color) {
                const colorObj = {
                    id: variant.color.id || variant.color, 
                    name: variant.color.name || variant.color
                };
                attributes.color.add(JSON.stringify(colorObj));
                console.log('Added color:', colorObj);
            }
            if (variant.style) {
                const styleObj = {
                    id: variant.style.id || variant.style, 
                    name: variant.style.name || variant.style
                };
                attributes.style.add(JSON.stringify(styleObj));
                console.log('Added style:', styleObj);
            }
            if (variant.genre) {
                const genreObj = {
                    id: variant.genre.id || variant.genre, 
                    name: variant.genre.name || variant.genre
                };
                attributes.genre.add(JSON.stringify(genreObj));
                console.log('Added genre:', genreObj);
            }
            if (variant.size) {
                const sizeObj = {
                    id: variant.size.id || variant.size, 
                    name: variant.size.name || variant.size
                };
                attributes.size.add(JSON.stringify(sizeObj));
                console.log('Added size:', sizeObj);
            }
            if (variant.material) {
                const materialObj = {
                    id: variant.material.id || variant.material, 
                    name: variant.material.name || variant.material
                };
                attributes.material.add(JSON.stringify(materialObj));
                console.log('Added material:', materialObj);
            }
            
            // Also check direct properties
            if (variant.colorName) {
                attributes.color.add(JSON.stringify({id: variant.id, name: variant.colorName}));
                console.log('Added colorName:', variant.colorName);
            }
            if (variant.styleName) {
                attributes.style.add(JSON.stringify({id: variant.id, name: variant.styleName}));
                console.log('Added styleName:', variant.styleName);
            }
            if (variant.sizeName) {
                attributes.size.add(JSON.stringify({id: variant.id, name: variant.sizeName}));
                console.log('Added sizeName:', variant.sizeName);
            }
        });

        console.log('Final attributes:', attributes);

        // Create selectors for attributes that have values
        Object.keys(attributes).forEach(attr => {
            if (attributes[attr].size > 0) {
                console.log(`Creating selector for ${attr} with ${attributes[attr].size} options`);
                const options = Array.from(attributes[attr]).map(item => JSON.parse(item));
                
                const wrapper = document.createElement('div');
                wrapper.className = 'flex-w flex-r-m p-b-10';
                
                wrapper.innerHTML = `
                    <div class="size-203 flex-c-m respon6">
                        ${attr.charAt(0).toUpperCase() + attr.slice(1)}
                    </div>
                    <div class="size-204 respon6-next">
                        <div class="rs1-select2 bor8 bg0">
                            <select class="js-select2 js-variant-selector" name="${attr}" data-attribute="${attr}">
                                <option value="">Choose a ${attr}</option>
                                ${options.map(opt => `<option value="${opt.id}" data-name="${opt.name}">${opt.name}</option>`).join('')}
                            </select>
                            <div class="dropDownSelect2"></div>
                        </div>
                    </div>
                `;
                
                container.appendChild(wrapper);
                console.log(`Added selector for ${attr}`);
            }
        });

        // Initialize select2 for new selectors
        container.querySelectorAll('.js-select2').forEach(select => {
            if (typeof $.fn.select2 !== 'undefined') {
                $(select).select2({
                    minimumResultsForSearch: 20,
                    dropdownParent: $(select).next('.dropDownSelect2')
                });
            }
        });

        container.addEventListener('change', handleVariantSelection);
        console.log('Variant selectors setup complete');
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
            quickViewModal.querySelector('.js-product-price').textContent = formatPrice(data.price, data.currency);
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