// VerzTec Avatar Manager v3.0 - Clean rebuild from scratch
// Focused on using your FBX animations and eliminating T-pose

class AvatarManagerV3 {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      elevenlabsApiKey: options.elevenlabsApiKey || 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
      voice: options.voice || 'EXAVITQu4vr4xnSDxMaL',
      ...options
    };
    
    // Core Three.js components
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.avatar = null;
    this.clock = new THREE.Clock();
    
    // Animation system
    this.mixer = null;
    this.idleAction = null;
    this.talkingAction = null;
    this.currentAnimation = 'idle';
    
    // Audio and lipsync
    this.audioContext = null;
    this.audioElement = null;
    this.analyser = null;
    this.audioSource = null;
    this.morphTargets = {};
    this.isSpeaking = false;
    this.lipsyncUpdateId = null;
    
    // State
    this.isInitialized = false;
    
    this.init();
  }
  
  async init() {
    try {
      console.log('🤖 Initializing Avatar Manager v3...');
      
      this.setupScene();
      this.setupCamera();
      this.setupRenderer();
      this.setupLighting();
      this.setupAudio();
      
      await this.loadAvatar();
      await this.loadAnimations();
      
      this.setupControls();
      this.startRenderLoop();
      
      this.isInitialized = true;
      console.log('✅ Avatar Manager v3 initialized successfully');
      
    } catch (error) {
      console.error('❌ Failed to initialize Avatar Manager v3:', error);
      this.createFallbackAvatar();
    }
  }
  
  setupScene() {
    this.scene = new THREE.Scene();
    this.scene.background = null; // Transparent
  }
  
  setupCamera() {
    this.camera = new THREE.PerspectiveCamera(50, 
      this.container.clientWidth / this.container.clientHeight, 0.1, 1000);
    this.camera.position.set(0, 1.6, 2.0);
    this.camera.lookAt(0, 1.5, 0);
  }
  
  setupRenderer() {
    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.setClearColor(0x000000, 0);
    
    this.container.appendChild(this.renderer.domElement);
    window.addEventListener('resize', () => this.onWindowResize());
  }
  
  setupLighting() {
    // Ambient light
    const ambientLight = new THREE.AmbientLight(0x404040, 0.8);
    this.scene.add(ambientLight);
    
    // Key light
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.2);
    keyLight.position.set(3, 3, 3);
    keyLight.castShadow = true;
    this.scene.add(keyLight);
    
    // Fill light
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.6);
    fillLight.position.set(-2, 2, 2);
    this.scene.add(fillLight);
  }
  
  setupAudio() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 256;
      this.analyser.smoothingTimeConstant = 0.8;
      
      this.audioElement = document.createElement('audio');
      this.audioElement.crossOrigin = 'anonymous';
      this.audioElement.preload = 'auto';
      
      console.log('Audio context setup complete');
    } catch (error) {
      console.error('Failed to setup audio:', error);
    }
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      console.log('Loading avatar from:', this.options.avatarUrl);
      
      const gltf = await new Promise((resolve, reject) => {
        loader.load(this.options.avatarUrl, resolve, 
          (progress) => {
            console.log('Loading progress:', Math.round(progress.loaded / progress.total * 100) + '%');
          }, 
          reject);
      });
      
      this.avatar = gltf.scene;
      this.avatar.scale.set(1.5, 1.5, 1.5);
      this.avatar.position.set(0, 0, 0);
      this.avatar.rotation.set(0, 0, 0);
      
      this.scene.add(this.avatar);
      
      // Setup morph targets for lipsync
      this.setupMorphTargets();
      
      console.log('Avatar loaded successfully');
      
    } catch (error) {
      console.error('Failed to load avatar:', error);
      throw error;
    }
  }
  
  async loadAnimations() {
    console.log('Loading animations from assets/avatars/animations/...');
    
    const fbxLoader = new THREE.FBXLoader();
    const animationFiles = [
      { name: 'idle', path: 'assets/avatars/animations/Idle.fbx' },
      { name: 'talking', path: 'assets/avatars/animations/Talking.fbx' },
      { name: 'thinking', path: 'assets/avatars/animations/Thinking.fbx' }
    ];
    
    this.mixer = new THREE.AnimationMixer(this.avatar);
    
    for (const animFile of animationFiles) {
      try {
        console.log(`Loading ${animFile.name} animation from ${animFile.path}...`);
        
        const fbx = await new Promise((resolve, reject) => {
          fbxLoader.load(animFile.path, resolve, 
            (progress) => {
              console.log(`${animFile.name}: ${Math.round(progress.loaded / progress.total * 100)}%`);
            }, 
            reject);
        });
        
        if (fbx.animations && fbx.animations.length > 0) {
          const clip = fbx.animations[0]; // Use first animation from each file
          const action = this.mixer.clipAction(clip);
          action.setLoop(THREE.LoopRepeat);
          
          // Assign animations based on file name
          if (animFile.name === 'idle' || animFile.name === 'thinking') {
            this.idleAction = action;
            console.log(`✅ Loaded ${animFile.name} animation successfully`);
          } else if (animFile.name === 'talking') {
            this.talkingAction = action;
            console.log(`✅ Loaded ${animFile.name} animation successfully`);
          }
        }
        
      } catch (error) {
        console.warn(`Could not load ${animFile.name} animation:`, error);
      }
    }
    
    // Start with idle animation
    if (this.idleAction) {
      this.idleAction.play();
      this.currentAnimation = 'idle';
      console.log('Started idle animation');
    } else {
      console.warn('No idle animation found - avatar may be in T-pose');
      this.fixTPose();
    }
    
    // Ensure we have a talking animation
    if (!this.talkingAction && this.idleAction) {
      this.talkingAction = this.idleAction.clone();
      console.log('Using idle animation as talking animation');
    }
  }
  
  fixTPose() {
    console.log('Applying T-pose fix...');
    
    let adjustments = 0;
    
    this.avatar.traverse((child) => {
      if (child.isBone || child.type === 'Bone') {
        const boneName = child.name.toLowerCase();
        
        // Fix arm positions
        if (boneName.includes('shoulder') || boneName.includes('clavicle')) {
          if (boneName.includes('left') || boneName.includes('l_')) {
            child.rotation.z = -0.6;
            child.rotation.y = 0.2;
            adjustments++;
          } else if (boneName.includes('right') || boneName.includes('r_')) {
            child.rotation.z = 0.6;
            child.rotation.y = -0.2;
            adjustments++;
          }
        }
        
        if (boneName.includes('upperarm') || boneName.includes('arm')) {
          if (boneName.includes('left') || boneName.includes('l_')) {
            child.rotation.z = -1.0;
            child.rotation.x = 0.3;
            adjustments++;
          } else if (boneName.includes('right') || boneName.includes('r_')) {
            child.rotation.z = 1.0;
            child.rotation.x = 0.3;
            adjustments++;
          }
        }
        
        if (boneName.includes('forearm') || boneName.includes('lowerarm')) {
          child.rotation.x = -0.4;
          adjustments++;
        }
        
        if (boneName.includes('hand')) {
          child.rotation.x = -0.3;
          adjustments++;
        }
      }
    });
    
    console.log(`Applied ${adjustments} bone adjustments to fix T-pose`);
  }
  
  setupMorphTargets() {
    const morphTargets = {};
    
    // Simple viseme mapping for Ready Player Me avatars
    const visemeMap = {
      'viseme_sil': ['mouthClose', 'mouthNeutral'],
      'viseme_aa': ['mouthOpen', 'jawOpen', 'mouthAh'],
      'viseme_E': ['mouthSmile', 'mouthEh'],
      'viseme_I': ['mouthSmile', 'mouthIh'],
      'viseme_O': ['mouthFunnel', 'mouthOh'],
      'viseme_U': ['mouthPucker', 'mouthOoh'],
      'viseme_PP': ['mouthPucker', 'mouthPress'],
      'viseme_FF': ['mouthShrugLower'],
      'viseme_DD': ['mouthOpen', 'jawOpen'],
      'viseme_TH': ['mouthShrugLower'],
      'viseme_kk': ['mouthOpen'],
      'viseme_CH': ['mouthShrugUpper'],
      'viseme_SS': ['mouthFrown'],
      'viseme_nn': ['mouthClose'],
      'viseme_RR': ['mouthPucker']
    };
    
    let foundCount = 0;
    
    this.avatar.traverse((child) => {
      if (child.isMesh && child.morphTargetDictionary) {
        const availableTargets = Object.keys(child.morphTargetDictionary);
        console.log('Found morph targets:', availableTargets);
        
        Object.entries(visemeMap).forEach(([viseme, targetNames]) => {
          if (!morphTargets[viseme]) {
            for (const targetName of targetNames) {
              if (child.morphTargetDictionary[targetName] !== undefined) {
                morphTargets[viseme] = {
                  mesh: child,
                  index: child.morphTargetDictionary[targetName]
                };
                foundCount++;
                console.log(`Mapped ${viseme} to ${targetName}`);
                break;
              }
            }
          }
        });
      }
    });
    
    this.morphTargets = morphTargets;
    console.log(`Found ${foundCount} morph targets for lipsync`);
  }
  
  setupControls() {
    let isMouseDown = false;
    let mouseX = 0;
    let mouseY = 0;
    
    this.container.addEventListener('mousedown', (event) => {
      isMouseDown = true;
      mouseX = event.clientX;
      mouseY = event.clientY;
    });
    
    this.container.addEventListener('mousemove', (event) => {
      if (!isMouseDown) return;
      
      const deltaX = event.clientX - mouseX;
      const deltaY = event.clientY - mouseY;
      
      const spherical = new THREE.Spherical();
      spherical.setFromVector3(this.camera.position);
      spherical.theta -= deltaX * 0.01;
      spherical.phi += deltaY * 0.01;
      spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi));
      
      this.camera.position.setFromSpherical(spherical);
      this.camera.lookAt(0, 1.5, 0);
      
      mouseX = event.clientX;
      mouseY = event.clientY;
    });
    
    this.container.addEventListener('mouseup', () => {
      isMouseDown = false;
    });
    
    this.container.addEventListener('wheel', (event) => {
      const distance = this.camera.position.distanceTo(new THREE.Vector3(0, 1.5, 0));
      const newDistance = Math.max(1.0, Math.min(8, distance + event.deltaY * 0.01));
      
      this.camera.position.normalize().multiplyScalar(newDistance);
      this.camera.position.y = Math.max(0.5, this.camera.position.y);
      this.camera.lookAt(0, 1.5, 0);
    });
  }
  
  startRenderLoop() {
    const animate = () => {
      requestAnimationFrame(animate);
      
      const delta = this.clock.getDelta();
      
      if (this.mixer) {
        this.mixer.update(delta);
      }
      
      if (this.isSpeaking) {
        this.updateLipsync();
      }
      
      this.renderer.render(this.scene, this.camera);
    };
    
    animate();
  }
  
  // Main method for speech with animation
  async speakWithAnimation(text, onTextUpdate = null) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('No ElevenLabs API key - text only mode');
      if (onTextUpdate) {
        await this.typewriterEffect(text, onTextUpdate);
      }
      return;
    }
    
    try {
      console.log('Starting speech:', text.substring(0, 50) + '...');
      
      // 1. Switch to talking animation
      this.switchToTalking();
      
      // 2. Generate speech and show text in parallel
      const speechPromise = this.generateSpeech(text);
      const textPromise = onTextUpdate ? this.typewriterEffect(text, onTextUpdate) : Promise.resolve();
      
      const [audioBlob] = await Promise.all([speechPromise, textPromise]);
      
      // 3. Play audio with lipsync
      if (audioBlob) {
        await this.playAudioWithLipsync(audioBlob);
      }
      
      // 4. Return to idle
      this.switchToIdle();
      
    } catch (error) {
      console.error('Speech failed:', error);
      this.switchToIdle();
    }
  }
  
  async typewriterEffect(text, onTextUpdate) {
    const words = text.split(' ');
    let currentText = '';
    
    for (let i = 0; i < words.length; i++) {
      currentText += (i > 0 ? ' ' : '') + words[i];
      onTextUpdate(currentText);
      
      let delay = 80 + Math.random() * 40;
      if (words[i].endsWith('.')) delay = 250;
      else if (words[i].endsWith(',')) delay = 150;
      
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
        throw new Error(`Speech generation failed: ${response.status}`);
      }
      
      return await response.blob();
      
    } catch (error) {
      console.error('Speech generation error:', error);
      throw error;
    }
  }
  
  async playAudioWithLipsync(audioBlob) {
    return new Promise((resolve, reject) => {
      const audioUrl = URL.createObjectURL(audioBlob);
      
      this.audioElement.src = audioUrl;
      this.audioElement.load();
      
      const onCanPlay = () => {
        console.log('Starting audio playback with lipsync...');
        
        // Connect audio to analyser for lipsync
        if (!this.audioSource) {
          this.audioSource = this.audioContext.createMediaElementSource(this.audioElement);
          this.audioSource.connect(this.analyser);
          this.analyser.connect(this.audioContext.destination);
        }
        
        this.startLipsync();
        this.audioElement.play().catch(reject);
      };
      
      const onEnded = () => {
        console.log('Audio playback ended');
        this.stopLipsync();
        
        // Cleanup
        this.audioElement.removeEventListener('canplay', onCanPlay);
        this.audioElement.removeEventListener('ended', onEnded);
        this.audioElement.removeEventListener('error', onError);
        URL.revokeObjectURL(audioUrl);
        
        resolve();
      };
      
      const onError = (error) => {
        console.error('Audio error:', error);
        this.stopLipsync();
        
        // Cleanup
        this.audioElement.removeEventListener('canplay', onCanPlay);
        this.audioElement.removeEventListener('ended', onEnded);
        this.audioElement.removeEventListener('error', onError);
        URL.revokeObjectURL(audioUrl);
        
        reject(error);
      };
      
      this.audioElement.addEventListener('canplay', onCanPlay);
      this.audioElement.addEventListener('ended', onEnded);
      this.audioElement.addEventListener('error', onError);
    });
  }
  
  startLipsync() {
    this.isSpeaking = true;
    
    const updateLipsync = () => {
      if (!this.isSpeaking) return;
      
      const bufferLength = this.analyser.frequencyBinCount;
      const dataArray = new Uint8Array(bufferLength);
      this.analyser.getByteFrequencyData(dataArray);
      
      const average = dataArray.reduce((sum, value) => sum + value, 0) / bufferLength;
      const intensity = Math.min(average / 100, 1.0);
      
      // Simple viseme selection based on audio intensity
      let viseme = 'viseme_sil';
      if (intensity > 0.1) {
        const visemes = ['viseme_aa', 'viseme_E', 'viseme_I', 'viseme_O', 'viseme_U'];
        viseme = visemes[Math.floor(Math.random() * visemes.length)];
      }
      
      // Apply morph targets
      this.applyViseme(viseme, intensity);
      
      this.lipsyncUpdateId = requestAnimationFrame(updateLipsync);
    };
    
    this.lipsyncUpdateId = requestAnimationFrame(updateLipsync);
  }
  
  stopLipsync() {
    this.isSpeaking = false;
    if (this.lipsyncUpdateId) {
      cancelAnimationFrame(this.lipsyncUpdateId);
      this.lipsyncUpdateId = null;
    }
    
    // Reset mouth to neutral
    this.applyViseme('viseme_sil', 1.0);
  }
  
  applyViseme(viseme, intensity) {
    // Reset all morph targets
    Object.values(this.morphTargets).forEach(target => {
      if (target.mesh && target.mesh.morphTargetInfluences) {
        target.mesh.morphTargetInfluences[target.index] = 0;
      }
    });
    
    // Apply current viseme
    const target = this.morphTargets[viseme];
    if (target && target.mesh && target.mesh.morphTargetInfluences) {
      target.mesh.morphTargetInfluences[target.index] = Math.min(intensity * 0.8, 1.0);
    }
  }
  
  switchToTalking() {
    if (this.talkingAction && this.currentAnimation !== 'talking') {
      console.log('Switching to talking animation');
      
      if (this.idleAction) {
        this.idleAction.fadeOut(0.3);
      }
      
      this.talkingAction.reset().fadeIn(0.3).play();
      this.currentAnimation = 'talking';
    }
  }
  
  switchToIdle() {
    if (this.idleAction && this.currentAnimation !== 'idle') {
      console.log('Switching to idle animation');
      
      if (this.talkingAction) {
        this.talkingAction.fadeOut(0.3);
      }
      
      this.idleAction.reset().fadeIn(0.3).play();
      this.currentAnimation = 'idle';
    }
  }
  
  createFallbackAvatar() {
    console.log('Creating fallback avatar...');
    
    const group = new THREE.Group();
    
    // Simple robot
    const headGeometry = new THREE.SphereGeometry(0.4, 16, 16);
    const headMaterial = new THREE.MeshStandardMaterial({ color: 0x8cc8ff });
    const head = new THREE.Mesh(headGeometry, headMaterial);
    head.position.set(0, 1.4, 0);
    group.add(head);
    
    // Eyes
    const eyeGeometry = new THREE.SphereGeometry(0.06, 8, 8);
    const eyeMaterial = new THREE.MeshStandardMaterial({ color: 0x000000 });
    
    const leftEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    leftEye.position.set(-0.12, 1.5, 0.3);
    group.add(leftEye);
    
    const rightEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    rightEye.position.set(0.12, 1.5, 0.3);
    group.add(rightEye);
    
    // Body
    const bodyGeometry = new THREE.CapsuleGeometry(0.25, 0.6, 4, 8);
    const bodyMaterial = new THREE.MeshStandardMaterial({ color: 0x6ba3ff });
    const body = new THREE.Mesh(bodyGeometry, bodyMaterial);
    body.position.set(0, 0.4, 0);
    group.add(body);
    
    this.avatar = group;
    this.scene.add(this.avatar);
    
    console.log('Fallback avatar created');
  }
  
  // Debug methods
  debugInfo() {
    console.log('Avatar Debug Info:');
    console.log('- Initialized:', this.isInitialized);
    console.log('- Avatar loaded:', !!this.avatar);
    console.log('- Mixer:', !!this.mixer);
    console.log('- Idle action:', !!this.idleAction);
    console.log('- Talking action:', !!this.talkingAction);
    console.log('- Current animation:', this.currentAnimation);
    console.log('- Morph targets:', Object.keys(this.morphTargets));
  }
  
  testAnimation() {
    if (this.talkingAction) {
      console.log('Testing talking animation...');
      this.switchToTalking();
      setTimeout(() => {
        this.switchToIdle();
      }, 3000);
    } else {
      console.log('No talking animation available');
    }
  }
  
  onWindowResize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }
  
  setElevenlabsApiKey(apiKey) {
    this.options.elevenlabsApiKey = apiKey;
  }
  
  destroy() {
    this.stopLipsync();
    
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
    
    window.removeEventListener('resize', this.onWindowResize);
  }
}

// Export for global use
window.AvatarManagerV3 = AvatarManagerV3;
