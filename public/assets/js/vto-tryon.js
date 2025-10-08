(function(){
  // Virtual Try-On using face-landmarks-detection and 2D overlay from product data-overlay
  const SEL = {
    modal: '#FaceMeshVTOModal',
    video: '#FaceMeshVideo',
    canvas: '#FaceMeshCanvas',
    loading: '#VTOLoading',
    error: '#VTOError',
    errorMsg: '#VTOErrorMessage',
    closeBtn: '#VTOClose',
   
    retryBtn: '#VTOError .retry-btn'
  };

  let model = null;
  let stream = null;
  let animId = null;
  let facingMode = 'user';
  let overlayImg = new Image();
  let overlayReady = false;

  const video = document.querySelector(SEL.video);
  const canvas = document.querySelector(SEL.canvas);
  const ctx = canvas.getContext('2d');

  // Calculated each resize: how video pixels map into canvas pixels when using object-fit: contain
  let display = { scale: 1, offsetX: 0, offsetY: 0, cw: 0, ch: 0, vw: 640, vh: 480 };

  const KP = { midEye: 168, leftEye: 143, noseBottom: 2, rightEye: 372 };

  function show(el) { document.querySelector(el).style.display = 'block'; }
  function hide(el) { document.querySelector(el).style.display = 'none'; }
  function showFlex(el){ const n=document.querySelector(el); n.style.display='flex'; }

  function resizeCanvasToContainer(){
    const container = video.parentElement;
    const rect = container.getBoundingClientRect();
    const cw = Math.max(1, Math.floor(rect.width));
    const ch = Math.max(1, Math.floor(rect.height));

    // Set canvas to container size so it overlays perfectly
    canvas.width = cw;
    canvas.height = ch;

    const vw = video.videoWidth || 640;
    const vh = video.videoHeight || 480;
    const scale = Math.min(cw / vw, ch / vh);
    const dispW = vw * scale;
    const dispH = vh * scale;
    const offsetX = (cw - dispW) / 2;
    const offsetY = (ch - dispH) / 2;

    display = { scale, offsetX, offsetY, cw, ch, vw, vh };
  }

  async function ensureModel(){
    if (model) return model;
    show(SEL.loading);
    model = await faceLandmarksDetection.load(faceLandmarksDetection.SupportedPackages.mediapipeFacemesh);
    return model;
  }

  async function startCamera(){
    stopCamera();
    try {
      const constraints = { video: { facingMode } };
      stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = stream;
      await video.play();
      resizeCanvasToContainer();
      hide(SEL.error);
      return true;
    } catch (e) {
      showError('Failed to start camera. Please allow permission and try another browser if needed.');
      return false;
    }
  }

  function stopCamera(){
    if (animId) { cancelAnimationFrame(animId); animId = null; }
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
  }

  function showError(msg){
    const el = document.querySelector(SEL.errorMsg);
    if (el) el.textContent = msg || 'Error';
    hide(SEL.loading);
    show(SEL.error);
  }

  function clearCanvas(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
  }

  function drawOverlayForFace(face){
    if (!overlayReady) return;
    const mid = face.scaledMesh[KP.midEye];
    const le = face.scaledMesh[KP.leftEye];
    const re = face.scaledMesh[KP.rightEye];
    if (!mid || !le || !re) return;

    // Convert video coordinates to canvas coordinates
    const toCanvas = (p) => [display.offsetX + p[0] * display.scale, display.offsetY + p[1] * display.scale];
    const midC = toCanvas(mid);
    const leC = toCanvas(le);
    const reC = toCanvas(re);

    const dx = reC[0] - leC[0];
    const dy = reC[1] - leC[1];
    const eyeDist = Math.sqrt(dx*dx + dy*dy);
    const angle = Math.atan2(dy, dx);

    // Tunable factors per overlay artwork
    const width = eyeDist * 2.4; // how wide the glasses are vs eye distance
    const height = width * (overlayImg.naturalHeight / overlayImg.naturalWidth);
    const x = midC[0];
    const y = midC[1] - eyeDist * 0.15; // raise slightly above mid eye

    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.drawImage(overlayImg, -width/2, -height/2, width, height);
    ctx.restore();
  }

  async function loop(){
    try {
      const faces = await model.estimateFaces({
        input: video,
        returnTensors: false,
        flipHorizontal: false,
        predictIrises: false
      });
      clearCanvas();
      for (let i=0;i<faces.length;i++){
        drawOverlayForFace(faces[i]);
      }
      hide(SEL.loading);
    } catch(e) {
      // transient error; keep trying
    }
    animId = requestAnimationFrame(loop);
  }

  async function openVTO(overlaySrc){
    // load overlay first
    overlayReady = false;
    overlayImg = new Image();
    // Validate extension and origin to help debug
    try {
      const a = document.createElement('a');
      a.href = overlaySrc;
      const path = a.pathname || '';
      const ext = (path.split('.').pop() || '').toLowerCase();
      const sameOrigin = a.origin === window.location.origin || a.origin === 'null';
      const allowed = ['png','webp','jpg','jpeg','gif'];
      if (!allowed.includes(ext)) {
        showError('Overlay must be an image (png/webp/jpg). Got: ' + (ext || 'unknown'));
        console.error('VTO overlay invalid extension:', overlaySrc);
        return;
      }
      if (!sameOrigin) {
        console.warn('VTO overlay is cross-origin. Ensure proper CORS headers if needed:', overlaySrc);
      }
    } catch(e) { /* ignore parsing issues */ }

    overlayImg.onload = function(){ overlayReady = true; };
    overlayImg.onerror = function(){ showError('Failed to load overlay image: ' + overlaySrc); };
    overlayImg.src = overlaySrc;

    showFlex(SEL.modal);
    show(SEL.loading);
    hide(SEL.error);

    const camOk = await startCamera();
    if (!camOk) return;

    await ensureModel();
    loop();
  }

  function closeVTO(){
    stopCamera();
    hide(SEL.error);
    hide(SEL.loading);
    clearCanvas();
    document.querySelector(SEL.modal).style.display = 'none';
  }

  async function switchCamera(){
    facingMode = (facingMode === 'user') ? 'environment' : 'user';
    const playing = !!stream;
    if (playing) {
      await startCamera();
    }
  }

  // Event bindings
  document.addEventListener('click', function(e){
    const t = e.target;
    // Try On buttons
    if (t && (t.classList.contains('js-tryon') || t.closest('.js-tryon'))){
      e.preventDefault();
      const btn = t.classList.contains('js-tryon') ? t : t.closest('.js-tryon');
      const overlay = btn.getAttribute('data-overlay');
      if (!overlay) {
        showError('No overlay configured for this product.');
        return;
      }
      openVTO(overlay);
    }
  });

  // Allow other scripts to start Try-On explicitly
  document.addEventListener('tryon:start', function(e){
    try {
      const url = e && e.detail && e.detail.overlayUrl;
      if (url) {
        openVTO(url);
      } else {
        showError('No overlay provided for Try-On.');
      }
    } catch(err) {
      showError('Failed to start Try-On');
    }
  });

  const closeBtn = document.querySelector(SEL.closeBtn);
  if (closeBtn) closeBtn.addEventListener('click', function(){ closeVTO(); });

  const switchBtn = document.querySelector(SEL.switchBtn);
  if (switchBtn) switchBtn.addEventListener('click', function(){ switchCamera(); });

  const retryBtn = document.querySelector(SEL.retryBtn);
  if (retryBtn) retryBtn.addEventListener('click', async function(){ hide(SEL.error); await startCamera(); });

  // Resize canvas when metadata is available
  if (video) {
    video.addEventListener('loadedmetadata', resizeCanvasToContainer);
  }
  window.addEventListener('resize', resizeCanvasToContainer);

  // Close with Esc
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeVTO(); } });

  // Expose for debugging
  // Auto-integrate on Product Detail page: add Try-On button and sync overlays
  document.addEventListener('DOMContentLoaded', function(){
    try {
      const detailSection = document.querySelector('.sec-product-detail');
      if (!detailSection) return; // not on product detail page
      const addBtn = detailSection.querySelector('.js-addcart-detail');
      if (!addBtn) return; // safety
      const variantSelect = detailSection.querySelector('.js-variant-select');

      // Ensure Try-On button exists next to Add to cart (we'll style it to appear underneath)
      let tryBtn = detailSection.querySelector('.js-tryon');
      if (!tryBtn) {
        tryBtn = document.createElement('button');
        tryBtn.type = 'button';
        tryBtn.className = 'flex-c-m stext-101 cl0 size-101 bg3 bor1 hov-btn1 p-lr-15 trans-04 js-tryon';
        tryBtn.textContent = 'Try On';
        addBtn.insertAdjacentElement('afterend', tryBtn);
      }

      // Style the Try-On button to be on a new line below Add to cart within the flex container
      try {
        tryBtn.style.flexBasis = '100%';
        tryBtn.style.width = '100%';
        tryBtn.style.marginTop = '12px';
        tryBtn.style.marginLeft = '0';
        tryBtn.style.justifyContent = 'center';
      } catch(_) {}

      function applyOverlayToButton(url){
        if (!tryBtn) return;
        tryBtn.setAttribute('data-overlay', url || '');
        if (!url) { tryBtn.style.display = 'none'; tryBtn.disabled = true; }
        else { tryBtn.style.display = ''; tryBtn.disabled = false; }
      }

      const productId = addBtn.getAttribute('data-product-id');
      const baseOption = variantSelect ? variantSelect.querySelector('option[value="product"]') : null;
      let productOverlay = baseOption ? (baseOption.getAttribute('data-overlay') || '') : '';

      function updateButton(){
        let url = productOverlay || '';
        if (variantSelect && variantSelect.selectedIndex >= 0) {
          const opt = variantSelect.options[variantSelect.selectedIndex];
          if (opt) {
            const ov = opt.getAttribute('data-overlay') || '';
            if (ov) url = ov; else if (opt.value === 'product') url = productOverlay || '';
          }
        }
        applyOverlayToButton(url);
      }

      // Fetch overlays from API and populate option data-overlay
      if (productId) {
        fetch('/api/products/' + productId + '/quick-view')
          .then(r => r.json())
          .then(function(data){
            try {
              if (data && data.overlayImage) {
                productOverlay = data.overlayImage;
                if (baseOption) baseOption.setAttribute('data-overlay', productOverlay);
              }
              if (variantSelect && data && Array.isArray(data.productVariants)) {
                const map = {};
                data.productVariants.forEach(v => { map[String(v.id)] = v.overlayImage || ''; });
                for (let i = 0; i < variantSelect.options.length; i++) {
                  const opt = variantSelect.options[i];
                  if (opt && opt.value && opt.value !== 'product') {
                    const ov = map[String(opt.value)] || '';
                    if (ov) opt.setAttribute('data-overlay', ov);
                  }
                }
              }
            } catch(_) {}
            updateButton();
            if (variantSelect) variantSelect.addEventListener('change', updateButton);
          })
          .catch(function(){
            updateButton();
            if (variantSelect) variantSelect.addEventListener('change', updateButton);
          });
      } else {
        updateButton();
        if (variantSelect) variantSelect.addEventListener('change', updateButton);
      }
    } catch(_) {}
  });

  window.VTO2D = { open: openVTO, close: closeVTO, switchCamera };
})();
