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
  let overlayAnchor = { ax: 0, ay: 0 }; // pixels in image space, center-based

  // DOM refs
  const video = document.querySelector(SEL.video);
  const canvas = document.querySelector(SEL.canvas);
  const ctx = canvas.getContext('2d');

  // Display mapping for object-fit: contain on the video
  let display = { scale: 1, offsetX: 0, offsetY: 0, cw: 0, ch: 0, vw: 640, vh: 480 };
  let mirrorX = false; // detect CSS mirror on video

  // Landmark indices to use (MediaPipe/FaceMesh)
  // Standard FaceMesh temples: 234 (subject's left), 454 (subject's right)
  const KP = { leftEye: 33, rightEye: 263, noseTip: 1, noseBridge: 168, leftTemple: 234, rightTemple: 454, leftEar: 127, rightEar: 356 };

  // UI helpers
  function show(el) { const n = document.querySelector(el); if (n) n.style.display = 'block'; }
  function hide(el) { const n = document.querySelector(el); if (n) n.style.display = 'none'; }
  function showFlex(el){ const n=document.querySelector(el); if (n) n.style.display='flex'; }

  // Smoothing factors (0..1); higher = snappier
  const SMOOTH = { pos: 0.18, scale: 0.18, rot: 0.18 };
  function parseAnchorsFromUrl(url){
    try{
      const u = new URL(url, window.location.href);
      const s = u.searchParams.get('anchors');
      if(!s) return null;
      const a = s.split(',').map(function(v){ return parseFloat(v); });
      if (a.length >= 6 && a.every(function(v){ return isFinite(v); })) {
        return { bx:a[0], by:a[1], lx:a[2], ly:a[3], rx:a[4], ry:a[5] };
      }
    }catch(_){ }
    return null;
  }
  let smoothState = { x: null, y: null, angle: null, width: null };

  function isVideoMirrored(el){
    try {
      const cs = getComputedStyle(el);
      const tr = cs.transform || cs.webkitTransform || '';
      if (!tr || tr === 'none') return false;
      // matrix(a, b, c, d, e, f) -> a<0 indicates horizontal flip
      const m = tr.match(/matrix\(([-0-9e\.]+),\s*([-0-9e\.]+),\s*([-0-9e\.]+),\s*([-0-9e\.]+),/i);
      if (m && m[1]) return parseFloat(m[1]) < 0;
      // matrix3d(a, ...), a<0 also implies flip on X
      const m3 = tr.match(/matrix3d\(([-0-9e\.]+),/i);
      if (m3 && m3[1]) return parseFloat(m3[1]) < 0;
    } catch(_){}
    return false;
  }

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
    mirrorX = isVideoMirrored(video);
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

  // Compute center of opaque content to compensate transparent padding in PNGs
  function computeOverlayAnchor(img){
    try {
      const w = img.naturalWidth, h = img.naturalHeight;
      if (!w || !h) return { ax: 0, ay: 0 };
      const tmp = document.createElement('canvas');
      tmp.width = w; tmp.height = h;
      const ictx = tmp.getContext('2d');
      ictx.drawImage(img, 0, 0);
      const data = ictx.getImageData(0, 0, w, h).data;
      let minX = w, minY = h, maxX = -1, maxY = -1;
      for (let y = 0; y < h; y++){
        for (let x = 0; x < w; x++){
          const a = data[(y * w + x) * 4 + 3];
          if (a > 5){
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
          }
        }
      }
      if (maxX < minX || maxY < minY) return { ax: 0, ay: 0 };
      const cx = (minX + maxX) / 2;
      const cy = (minY + maxY) / 2;
      // Offset from image center (positive ax means content center is to the right in image space)
      return { ax: cx - w / 2, ay: cy - h / 2 };
    } catch(_) { return { ax: 0, ay: 0 }; }
  }

  function drawOverlayForFace(face){
    if (!overlayReady) return;
    const leP = face.scaledMesh[KP.leftEye];
    const reP = face.scaledMesh[KP.rightEye];
    const noseP = face.scaledMesh[KP.noseTip];
    const nbP = face.scaledMesh[KP.noseBridge] || noseP;
    const ltP = face.scaledMesh[KP.leftTemple] || face.scaledMesh[KP.leftEar];
    const rtP = face.scaledMesh[KP.rightTemple] || face.scaledMesh[KP.rightEar];
    if (!leP || !reP || !nbP) return;

    const toCanvas = (p) => {
      const x = mirrorX ? (display.vw - p[0]) : p[0];
      return [display.offsetX + x * display.scale, display.offsetY + p[1] * display.scale];
    };
    const le = toCanvas(leP);
    const re = toCanvas(reP);
    const nb = toCanvas(nbP);
    const lt = ltP ? toCanvas(ltP) : null;
    const rt = rtP ? toCanvas(rtP) : null;

    const dx = re[0] - le[0];
    const dy = re[1] - le[1];
    const angleEyes = Math.atan2(dy, dx);

    // Horizontal center: midpoint between eyes; Vertical: nose bridge
    const cx = (le[0] + re[0]) / 2;
    const cy = nb[1];

    // Width: temple-to-temple if available, else eye-outer span
    let widthPx = null;
    if (lt && rt) widthPx = Math.hypot(rt[0] - lt[0], rt[1] - lt[1]);
    else widthPx = Math.hypot(dx, dy);
    widthPx = Math.max(1, widthPx);

    // Smoothing
    const lerp = (a, b, t) => (a == null ? b : a + (b - a) * t);
    const tX = lerp(smoothState.x, cx, SMOOTH.pos);
    const tY = lerp(smoothState.y, cy, SMOOTH.pos);
    const tA = lerp(smoothState.angle, angleEyes, SMOOTH.rot);
    const tW = lerp(smoothState.width, widthPx, SMOOTH.scale);
    smoothState.x = tX; smoothState.y = tY; smoothState.angle = tA; smoothState.width = tW;

    const w = tW;
    const h = w * (overlayImg.naturalHeight / overlayImg.naturalWidth);

    // Draw overlay
    ctx.save();
    ctx.translate(tX, tY);
    ctx.rotate(tA);
    // Apply anchor offset derived from opaque content bbox to counter transparent padding
    const sx = w / (overlayImg.naturalWidth || 1);
    const sy = h / (overlayImg.naturalHeight || 1);
    ctx.translate(-overlayAnchor.ax * sx, -overlayAnchor.ay * sy);
    ctx.drawImage(overlayImg, -w/2, -h/2, w, h);
    ctx.restore();

    // Simple nose occlusion: erase region overlapping the nose-bridge polygon
    try {
      const noseIdx = [1, 6, 5, 197, 195, 4]; // small loop around bridge; safe fallback
      ctx.save();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      for (let i = 0; i < noseIdx.length; i++) {
        const p = face.scaledMesh[noseIdx[i]];
        if (!p) continue;
        const q = toCanvas(p);
        if (i === 0) ctx.moveTo(q[0], q[1]); else ctx.lineTo(q[0], q[1]);
      }
      ctx.closePath();
      ctx.fillStyle = 'rgba(0,0,0,1)';
      ctx.fill();
      ctx.restore();
    } catch(_) { /* ignore occlusion errors */ }
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
    // Reset smoothing state on open so the overlay doesn't jump from a previous session
    smoothState = { x: null, y: null, angle: null, width: null };

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

  const switchBtn = SEL.switchBtn ? document.querySelector(SEL.switchBtn) : null;
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
