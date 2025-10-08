const webcamElement = document.getElementById('webcam');
const canvasElement = document.getElementById('canvas');
const webcam = new Webcam(webcamElement, 'user');
let selectedglasses = $(".selected-glasses img");
let isVideo = false;
let model = null;
let cameraFrame = null;
let detectFace = false;
let clearglasses = false;
let glassesOnImage = false;
let glassesArray = [];
let scene;
let camera;
let renderer;
let obControls;
let glassesKeyPoints = {midEye:168, leftEye:143, noseBottom:2, rightEye:372};

$( document ).ready(function() {
    // Deprecated: this file is no longer used. See assets/js/vto-tryon.js
});


function setup3dCamera(){  
    if(isVideo){
        camera = new THREE.PerspectiveCamera( 45, 1, 0.1, 2000 );
        let videoWidth = webcamElement.width;
        let videoHeight = webcamElement.height;
        camera.position.x = videoWidth / 2;
        camera.position.y = -videoHeight / 2;
        camera.position.z = -( videoHeight / 2 ) / Math.tan( 45 / 2 ); 
        camera.lookAt( { x: videoWidth / 2, y: -videoHeight / 2, z: 0, isVector3: true } );
        renderer.setSize(videoWidth, videoHeight);
        renderer.setClearColor(0x000000, 0);
    }
    renderer.render(scene, camera);
};