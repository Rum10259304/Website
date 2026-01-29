// VerzTec Avatar System v3.0 - Complete Rebuild
// Clean, simple, and effective 3D avatar with speech and animation

class AvatarSystemV3 {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      elevenlabsApiKey: options.elevenlabsApiKey || 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
      voice: options.voice || 'EXAVITQu4vr4xnSDxMaL',
      ...options
    };
    
    // Core components
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.avatar = null;
    this.mixer = null;
    this.clock = new THREE.Clock();
    
    // Animation system
    this.animations = {
      idle: null,
      talking: null,
      current: null
    };
    
    // Audio system
    this.audioContext = null;
    this.audioElement = null;
    this.analyser = null;
    this.audioSource = null;
    
    // State
    this.isReady = false;
    this.isSpeaking = false;
    
    this.init();
  }
  
  async init() {
    try {
      console.log('🚀 Initializing Avatar System v3...');
      
      this.setupScene();
      this.setupCamera();
      this.setupRenderer();
      this.setupLighting();
      this.setupAudio();
      
      await this.loadAvatar();
      await this.loadAnimations();
      
      this.setupControls();
      this.startRenderLoop();
      
      this.isReady = true;
      console.log('✅ Avatar System v3 ready!');
      this.updateStatus('Ready to help');
      
    } catch (error) {
      console.error('❌ Failed to initialize avatar:', error);
      this.createSimpleAvatar();
    }
  }
  
  setupScene() {
    this.scene = new THREE.Scene();
    this.scene.background = null; // Transparent
  }
  
  setupCamera() {
    this.camera = new THREE.PerspectiveCamera(
      45,
      this.container.clientWidth / this.container.clientHeight,
      0.1,
      100
    );
    this.camera.position.set(0, 1.6, 2.5);
    this.camera.lookAt(0, 1.4, 0);
  }
  
  setupRenderer() {
    this.renderer = new THREE.WebGLRenderer({ 
      antialias: true,
      alpha: true
    });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.setClearColor(0x000000, 0);
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    
    this.container.appendChild(this.renderer.domElement);
    
    window.addEventListener('resize', () => this.handleResize());
  }
  
  setupLighting() {
    // Ambient light
    const ambientLight = new THREE.AmbientLight(0x404040, 0.8);
    this.scene.add(ambientLight);
    
    // Main light
    const directionalLight = new THREE.DirectionalLight(0xffffff, 1.2);
    directionalLight.position.set(2, 4, 2);
    directionalLight.castShadow = true;
    directionalLight.shadow.mapSize.width = 1024;
    directionalLight.shadow.mapSize.height = 1024;
    this.scene.add(directionalLight);
    
    // Fill light
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.3);
    fillLight.position.set(-2, 0, 2);
    this.scene.add(fillLight);
  }
  
  setupAudio() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 512;
      this.analyser.smoothingTimeConstant = 0.8;
      
      this.audioElement = document.createElement('audio');
      this.audioElement.crossOrigin = 'anonymous';
      
      console.log('Audio system ready');
    } catch (error) {
      console.error('Audio setup failed:', error);
    }
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      console.log('Loading avatar...');
      
      const gltf = await new Promise((resolve, reject) => {
        loader.load(
          this.options.avatarUrl,
          resolve,
          (progress) => console.log('Loading:', Math.round(progress.loaded / progress.total * 100) + '%'),
          reject
        );
      });
      
      this.avatar = gltf.scene;
      this.avatar.scale.set(1.8, 1.8, 1.8);
      this.avatar.position.set(0, -0.5, 0);
      
      // Fix any T-pose immediately
      this.fixAvatarPose();
      
      this.scene.add(this.avatar);
      console.log('Avatar loaded successfully');
      
    } catch (error) {
      console.error('Avatar loading failed:', error);
      throw error;
    }
  }
  
  fixAvatarPose() {
    // Aggressive T-pose fix
    this.avatar.traverse((child) => {
      if (child.isBone || child.type === 'Bone') {
        const name = child.name.toLowerCase();
        
        // Fix arms
        if (name.includes('shoulder') || name.includes('upperarm')) {
          if (name.includes('left') || name.includes('l_')) {
            child.rotation.z = -0.5;
            child.rotation.x = 0.2;
          } else if (name.includes('right') || name.includes('r_')) {
            child.rotation.z = 0.5;
            child.rotation.x = 0.2;
          }
        }
        
        // Fix forearms
        if (name.includes('forearm') || name.includes('lowerarm')) {
          child.rotation.x = -0.2;
        }
        
        // Fix hands
        if (name.includes('hand')) {
          child.rotation.x = -0.1;
        }
      }
    });
    
    console.log('Applied pose fix');
  }
  
  async loadAnimations() {
    const animationFiles = [
      'assets/avatars/animations/Idle.fbx',
      'assets/avatars/animations/Talking.fbx',
      'assets/avatars/animations/Thinking.fbx'
    ];
    
    const fbxLoader = new THREE.FBXLoader();
    
    for (const file of animationFiles) {
      try {
        console.log('Loading animation:', file);
        
        const fbx = await new Promise((resolve, reject) => {
          fbxLoader.load(file, resolve, undefined, reject);
        });
        
        if (fbx.animations && fbx.animations.length > 0) {
          if (!this.mixer) {
            this.mixer = new THREE.AnimationMixer(this.avatar);
          }
          
          const animation = fbx.animations[0];
          const action = this.mixer.clipAction(animation);
          action.setLoop(THREE.LoopRepeat);
          
          // Assign animations based on filename
          if (file.includes('Idle') || file.includes('Thinking')) {
            this.animations.idle = action;
            console.log('Loaded idle animation');
          } else if (file.includes('Talking')) {
            this.animations.talking = action;
            console.log('Loaded talking animation');
          }
        }
      } catch (error) {
        console.log('Could not load:', file);
      }
    }
    
    // Start idle animation
    if (this.animations.idle) {
      this.animations.idle.play();
      this.animations.current = 'idle';
      console.log('Started idle animation');
    } else {
      console.log('No animations loaded - creating basic movement');
      this.createBasicAnimation();
    }
  }
  
  createBasicAnimation() {
    // Simple breathing animation if no FBX animations work
    this.basicAnimation = () => {
      if (!this.avatar) return;
      
      const time = Date.now() * 0.001;
      this.avatar.rotation.y = Math.sin(time * 0.3) * 0.02;
      this.avatar.position.y = -0.5 + Math.sin(time * 2) * 0.01;
    };
  }
  
  setupControls() {
    let isDragging = false;
    let previousMousePosition = { x: 0, y: 0 };
    
    this.container.addEventListener('mousedown', (event) => {
      isDragging = true;
      previousMousePosition = { x: event.clientX, y: event.clientY };
    });
    
    this.container.addEventListener('mousemove', (event) => {
      if (!isDragging) return;
      
      const deltaMove = {
        x: event.clientX - previousMousePosition.x,
        y: event.clientY - previousMousePosition.y
      };
      
      const spherical = new THREE.Spherical();
      spherical.setFromVector3(this.camera.position);
      spherical.theta -= deltaMove.x * 0.01;
      spherical.phi += deltaMove.y * 0.01;
      spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi));
      
      this.camera.position.setFromSpherical(spherical);
      this.camera.lookAt(0, 1.4, 0);
      
      previousMousePosition = { x: event.clientX, y: event.clientY };
    });
    
    this.container.addEventListener('mouseup', () => {
      isDragging = false;
    });
    
    this.container.addEventListener('wheel', (event) => {
      const distance = this.camera.position.length();
      const newDistance = Math.max(1.5, Math.min(5, distance + event.deltaY * 0.01));
      
      this.camera.position.normalize().multiplyScalar(newDistance);
      this.camera.lookAt(0, 1.4, 0);
    });
  }
  
  startRenderLoop() {
    const animate = () => {
      requestAnimationFrame(animate);
      
      const delta = this.clock.getDelta();
      
      // Update mixer
      if (this.mixer) {
        this.mixer.update(delta);
      }
      
      // Update basic animation
      if (this.basicAnimation) {
        this.basicAnimation();
      }
      
      this.renderer.render(this.scene, this.camera);
    };
    
    animate();
  }
  
  // Main speech function
  async speakWithAnimation(text, onTextUpdate = null) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('No API key - text only mode');
      if (onTextUpdate) {
        await this.typeText(text, onTextUpdate);
      }
      return;
    }
    
    try {
      console.log('Speaking:', text.substring(0, 50) + '...');
      
      // Switch to talking animation
      this.switchAnimation('talking');
      
      // Generate speech and type text simultaneously
      const [audioBlob] = await Promise.all([
        this.generateSpeech(text),
        onTextUpdate ? this.typeText(text, onTextUpdate) : Promise.resolve()
      ]);
      
      // Play audio
      if (audioBlob) {
        await this.playAudio(audioBlob);
      }
      
      // Back to idle
      this.switchAnimation('idle');
      
    } catch (error) {
      console.error('Speech failed:', error);
      this.switchAnimation('idle');
    }
  }
  
  async typeText(text, onTextUpdate) {
    const words = text.split(' ');
    let current = '';
    
    for (let i = 0; i < words.length; i++) {
      current += (i > 0 ? ' ' : '') + words[i];
      onTextUpdate(current);
      
      const delay = words[i].endsWith('.') ? 200 : 80;
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  async generateSpeech(text) {
    try {
      const response = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${this.options.voice}`, {
        method: 'POST',
        headers: {
          'Accept': 'audio/mpeg',
          'Content-Type': 'application/json',
          'xi-api-key': this.options.elevenlabsApiKey
        },
        body: JSON.stringify({
          text: text,
          model_id: 'eleven_monolingual_v1',
          voice_settings: {
            stability: 0.5,
            similarity_boost: 0.75
          }
        })
      });
      
      if (!response.ok) {
        throw new Error(`TTS failed: ${response.status}`);
      }
      
      return await response.blob();
    } catch (error) {
      console.error('TTS error:', error);
      throw error;
    }
  }
  
  async playAudio(audioBlob) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(audioBlob);
      this.audioElement.src = url;
      
      const onEnded = () => {
        this.audioElement.removeEventListener('ended', onEnded);
        this.audioElement.removeEventListener('error', onError);
        URL.revokeObjectURL(url);
        resolve();
      };
      
      const onError = (error) => {
        this.audioElement.removeEventListener('ended', onEnded);
        this.audioElement.removeEventListener('error', onError);
        URL.revokeObjectURL(url);
        reject(error);
      };
      
      this.audioElement.addEventListener('ended', onEnded);
      this.audioElement.addEventListener('error', onError);
      
      this.audioElement.play().catch(reject);
    });
  }
  
  switchAnimation(type) {
    if (!this.mixer) return;
    
    const newAnimation = this.animations[type];
    if (!newAnimation) return;
    
    if (this.animations.current !== type) {
      // Fade out current
      const currentAnimation = this.animations[this.animations.current];
      if (currentAnimation) {
        currentAnimation.fadeOut(0.5);
      }
      
      // Fade in new
      newAnimation.reset().fadeIn(0.5).play();
      this.animations.current = type;
      
      console.log('Switched to', type, 'animation');
    }
  }
  
  createSimpleAvatar() {
    console.log('Creating simple avatar fallback');
    
    const group = new THREE.Group();
    
    // Head
    const head = new THREE.Mesh(
      new THREE.SphereGeometry(0.3, 16, 16),
      new THREE.MeshStandardMaterial({ color: 0xffdbac })
    );
    head.position.y = 1.5;
    group.add(head);
    
    // Body
    const body = new THREE.Mesh(
      new THREE.CapsuleGeometry(0.25, 0.8, 4, 8),
      new THREE.MeshStandardMaterial({ color: 0x4a90e2 })
    );
    body.position.y = 0.6;
    group.add(body);
    
    this.avatar = group;
    this.scene.add(this.avatar);
    
    this.createBasicAnimation();
  }
  
  updateStatus(text) {
    const statusEl = document.querySelector('.avatar-status');
    if (statusEl) statusEl.textContent = text;
  }
  
  handleResize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }
  
  // Debug functions
  debugInfo() {
    console.log('Avatar System v3 Debug Info:');
    console.log('- Ready:', this.isReady);
    console.log('- Avatar loaded:', !!this.avatar);
    console.log('- Mixer created:', !!this.mixer);
    console.log('- Idle animation:', !!this.animations.idle);
    console.log('- Talking animation:', !!this.animations.talking);
    console.log('- Current animation:', this.animations.current);
  }
  
  testAnimation() {
    console.log('Testing animation switch...');
    this.switchAnimation('talking');
    setTimeout(() => this.switchAnimation('idle'), 3000);
  }
  
  destroy() {
    if (this.audioElement) {
      this.audioElement.pause();
      this.audioElement.src = '';
    }
    
    if (this.audioContext) {
      this.audioContext.close();
    }
    
    if (this.renderer) {
      this.container.removeChild(this.renderer.domElement);
    }
    
    window.removeEventListener('resize', this.handleResize);
  }
}

// Export for global use
window.AvatarSystemV3 = AvatarSystemV3;
