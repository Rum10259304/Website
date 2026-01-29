// VerzTec Avatar Manager v2.0 - Redesigned for better coordination
// Handles 3D avatar with synchronized animation, lipsync, and text streaming

class AvatarManagerV2 {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      animationsUrl: options.animationsUrl || 'assets/avatars/models/animations.glb',
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
    
    // Audio and speech system
    this.audioContext = null;
    this.audioElement = null;
    this.analyser = null;
    this.audioSource = null;
    
    // Lipsync system
    this.morphTargets = {};
    this.currentViseme = 'viseme_sil';
    this.visemeIntensity = 0;
    this.lipsyncUpdateId = null;
    
    // State management
    this.isInitialized = false;
    this.isSpeaking = false;
    this.isTyping = false;
    
    this.init();
  }
  
  async init() {
    try {
      console.log('🤖 Initializing Avatar Manager v2...');
      
      // Initialize in order
      this.setupAudioContext();
      this.setupScene();
      this.setupCamera();
      this.setupRenderer();
      this.setupLighting();
      await this.loadAvatar();
      this.setupLipsync();
      this.setupControls();
      this.startRenderLoop();
      
      this.isInitialized = true;
      console.log('✅ Avatar Manager v2 initialized successfully');
      
      // Update status display
      this.updateStatusDisplay('Ready to help');
      
    } catch (error) {
      console.error('❌ Failed to initialize Avatar Manager v2:', error);
      this.createFallbackAvatar();
      this.updateStatusDisplay('Basic mode active');
    }
  }
  
  updateStatusDisplay(status) {
    const statusElement = document.querySelector('.avatar-status');
    if (statusElement) {
      statusElement.textContent = status;
    }
  }
  
  setupAudioContext() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 256;
      this.analyser.smoothingTimeConstant = 0.8;
      
      // Create audio element
      this.audioElement = document.createElement('audio');
      this.audioElement.crossOrigin = 'anonymous';
      this.audioElement.preload = 'auto';
      
      console.log('Audio context setup complete');
    } catch (error) {
      console.error('Failed to setup audio context:', error);
    }
  }
  
  setupScene() {
    this.scene = new THREE.Scene();
    this.scene.background = null; // Transparent for container gradient
  }
  
  setupCamera() {
    this.camera = new THREE.PerspectiveCamera(
      50,
      this.container.clientWidth / this.container.clientHeight,
      0.1,
      1000
    );
    this.camera.position.set(0, 1.6, 1.8);
    this.camera.lookAt(0, 1.5, 0);
  }
  
  setupRenderer() {
    this.renderer = new THREE.WebGLRenderer({ 
      antialias: true,
      alpha: true
    });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.setClearColor(0x000000, 0);
    
    this.container.appendChild(this.renderer.domElement);
    
    // Handle resize
    window.addEventListener('resize', () => this.onWindowResize());
  }
  
  setupLighting() {
    // Ambient light
    const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
    this.scene.add(ambientLight);
    
    // Key light
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.0);
    keyLight.position.set(2, 3, 2);
    keyLight.castShadow = true;
    this.scene.add(keyLight);
    
    // Fill light
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
    fillLight.position.set(-2, 1, 2);
    this.scene.add(fillLight);
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      console.log('Loading avatar from:', this.options.avatarUrl);
      
      const gltf = await new Promise((resolve, reject) => {
        loader.load(this.options.avatarUrl, resolve, 
          (progress) => {
            console.log('Loading progress:', (progress.loaded / progress.total * 100) + '%');
          }, 
          reject);
      });
      
      this.avatar = gltf.scene;
      this.avatar.scale.set(1.5, 1.5, 1.5);
      
      // Ensure avatar is positioned properly and not in T-pose
      this.avatar.position.set(0, 0, 0);
      this.avatar.rotation.set(0, 0, 0);
      
      // Add to scene
      this.scene.add(this.avatar);
      
      // Setup animations
      await this.setupAnimations(gltf.animations);
      
      // Setup morph targets for lipsync
      this.setupMorphTargets();
      
      // Force a specific pose if the avatar is in T-pose
      this.forceOutOfTPose();
      
      console.log('Avatar loaded successfully');
      
    } catch (error) {
      console.error('Failed to load avatar:', error);
      this.createFallbackAvatar();
    }
  }
  
  forceOutOfTPose() {
    console.log('Forcing avatar out of T-pose...');
    
    let adjustmentsMade = 0;
    
    // More aggressive T-pose fix
    this.avatar.traverse((child) => {
      if (child.isBone || child.type === 'Bone') {
        const boneName = child.name.toLowerCase();
        
        // Aggressive arm lowering
        if (boneName.includes('shoulder') || boneName.includes('clavicle')) {
          if (boneName.includes('left') || boneName.includes('l_')) {
            child.rotation.z = -0.5; // Lower left arm significantly
            child.rotation.y = 0.2;
            adjustmentsMade++;
          } else if (boneName.includes('right') || boneName.includes('r_')) {
            child.rotation.z = 0.5; // Lower right arm significantly
            child.rotation.y = -0.2;
            adjustmentsMade++;
          }
        }
        
        // Upper arm adjustments
        if (boneName.includes('upperarm') || boneName.includes('arm')) {
          if (boneName.includes('left') || boneName.includes('l_')) {
            child.rotation.z = -0.8; // Much lower left arm
            child.rotation.x = 0.3;
            adjustmentsMade++;
          } else if (boneName.includes('right') || boneName.includes('r_')) {
            child.rotation.z = 0.8; // Much lower right arm
            child.rotation.x = 0.3;
            adjustmentsMade++;
          }
        }
        
        // Forearm adjustments
        if (boneName.includes('forearm') || boneName.includes('lowerarm')) {
          child.rotation.x = -0.3;
          child.rotation.z = child.name.toLowerCase().includes('left') ? -0.2 : 0.2;
          adjustmentsMade++;
        }
        
        // Hand adjustments
        if (boneName.includes('hand')) {
          child.rotation.x = -0.3;
          child.rotation.z = child.name.toLowerCase().includes('left') ? -0.2 : 0.2;
          adjustmentsMade++;
        }
        
        // Spine adjustments for better posture
        if (boneName.includes('spine')) {
          child.rotation.x = -0.1; // Slight forward lean
          adjustmentsMade++;
        }
      }
    });
    
    console.log(`Forced T-pose fix: Made ${adjustmentsMade} aggressive adjustments`);
    
    // Also try to adjust the entire avatar if no bones were found
    if (adjustmentsMade === 0) {
      console.log('No bones found for individual adjustment, trying mesh-level fix');
      this.avatar.rotation.x = -0.1;
      this.avatar.position.y = -0.2;
    }
  }
  
  // Alternative approach: try to apply a rest pose
  applyRestPose() {
    console.log('Trying to apply rest pose...');
    
    // Look for skeleton and try to apply rest pose
    this.avatar.traverse((child) => {
      if (child.isSkinnedMesh && child.skeleton) {
        console.log('Found skeleton, trying to apply rest pose');
        
        // Try to reset skeleton to rest pose
        child.skeleton.bones.forEach((bone, index) => {
          // Reset rotation to a more natural pose
          if (bone.name.toLowerCase().includes('shoulder') || bone.name.toLowerCase().includes('clavicle')) {
            if (bone.name.toLowerCase().includes('left') || bone.name.toLowerCase().includes('l_')) {
              bone.rotation.set(0, 0, -0.5);
            } else if (bone.name.toLowerCase().includes('right') || bone.name.toLowerCase().includes('r_')) {
              bone.rotation.set(0, 0, 0.5);
            }
          }
          
          if (bone.name.toLowerCase().includes('upperarm') || bone.name.toLowerCase().includes('arm')) {
            if (bone.name.toLowerCase().includes('left') || bone.name.toLowerCase().includes('l_')) {
              bone.rotation.set(0.2, 0, -0.8);
            } else if (bone.name.toLowerCase().includes('right') || bone.name.toLowerCase().includes('r_')) {
              bone.rotation.set(0.2, 0, 0.8);
            }
          }
        });
        
        // Update skeleton
        child.skeleton.update();
        console.log('Applied rest pose to skeleton');
      }
    });
  }
  
  async setupAnimations(animations) {
    console.log('Setting up animations, found:', animations ? animations.length : 0, 'animations');
    
    if (!animations || animations.length === 0) {
      console.warn('No animations found in GLTF, trying to load individual animation files...');
      await this.loadSeparateAnimations();
      
      // If still no animations after loading separate files, force create basic animation
      if (!this.idleAction && !this.talkingAction) {
        console.log('No animations loaded, creating basic animation and forcing out of T-pose');
        this.createBasicBreathingAnimation();
        this.forceOutOfTPose();
        return;
      }
    } else {
      this.mixer = new THREE.AnimationMixer(this.avatar);
      
      // Find and setup animations
      animations.forEach((clip, index) => {
        const action = this.mixer.clipAction(clip);
        const clipName = clip.name.toLowerCase();
        
        console.log(`Processing animation clip ${index}: ${clip.name} (duration: ${clip.duration}s)`);
        
        if (clipName.includes('idle') || clipName.includes('breathing') || clipName.includes('rest') || clipName.includes('thinking')) {
          this.idleAction = action;
          console.log('Found idle animation:', clip.name);
        } else if (clipName.includes('talk') || clipName.includes('speaking') || clipName.includes('speech')) {
          this.talkingAction = action;
          console.log('Found talking animation:', clip.name);
        }
      });
      
      // If no specific animations found, use first two animations as idle and talking
      if (!this.idleAction && animations.length > 0) {
        this.idleAction = this.mixer.clipAction(animations[0]);
        console.log('Using first animation as idle:', animations[0].name);
      }
      
      if (!this.talkingAction && animations.length > 1) {
        this.talkingAction = this.mixer.clipAction(animations[1]);
        console.log('Using second animation as talking:', animations[1].name);
      } else if (!this.talkingAction && this.idleAction) {
        // Clone idle animation for talking if no separate talking animation
        this.talkingAction = this.idleAction.clone();
        console.log('Cloned idle animation for talking');
      }
    }
    
    // Start idle animation if we have one
    if (this.idleAction) {
      this.idleAction.setLoop(THREE.LoopRepeat);
      this.idleAction.play();
      this.currentAnimation = 'idle';
      console.log('Started idle animation');
    } else {
      console.warn('No suitable animations found - forcing out of T-pose with basic animation');
      this.createBasicBreathingAnimation();
      this.forceOutOfTPose();
    }
  }
  
  async loadSeparateAnimations() {
    // Try to load separate animation files based on actual file names found in the assets folder
    const animationFiles = [
      'assets/avatars/animations/Idle.fbx',
      'assets/avatars/animations/Talking.fbx',
      'assets/avatars/animations/Thinking.fbx',
      'assets/avatars/animations/idle.glb',
      'assets/avatars/animations/talking.glb',
      'assets/avatars/animations/idle.fbx',
      'assets/avatars/animations/talking.fbx'
    ];
    
    const loader = new THREE.GLTFLoader();
    const fbxLoader = new THREE.FBXLoader();
    
    for (const filePath of animationFiles) {
      try {
        console.log('Trying to load animation:', filePath);
        
        let animationData;
        if (filePath.endsWith('.glb')) {
          animationData = await new Promise((resolve, reject) => {
            loader.load(filePath, resolve, undefined, reject);
          });
        } else if (filePath.endsWith('.fbx')) {
          animationData = await new Promise((resolve, reject) => {
            fbxLoader.load(filePath, resolve, 
              (progress) => {
                console.log(`Loading ${filePath}: ${(progress.loaded / progress.total * 100).toFixed(1)}%`);
              }, 
              reject);
          });
        }
        
        if (animationData && animationData.animations && animationData.animations.length > 0) {
          this.mixer = this.mixer || new THREE.AnimationMixer(this.avatar);
          
          console.log(`Loaded ${animationData.animations.length} animations from ${filePath}`);
          
          animationData.animations.forEach((clip, index) => {
            const action = this.mixer.clipAction(clip);
            const fileName = filePath.toLowerCase();
            
            console.log(`Processing animation ${index} from ${filePath}: ${clip.name} (duration: ${clip.duration}s)`);
            
            // Apply animation to avatar immediately
            action.setLoop(THREE.LoopRepeat);
            
            if ((fileName.includes('idle') || fileName.includes('thinking')) && !this.idleAction) {
              this.idleAction = action;
              console.log('Loaded idle animation from:', filePath);
              
              // Start idle animation immediately
              this.idleAction.play();
              this.currentAnimation = 'idle';
              console.log('Started idle animation immediately');
              
            } else if (fileName.includes('talking') && !this.talkingAction) {
              this.talkingAction = action;
              console.log('Loaded talking animation from:', filePath);
            }
          });
          
          // Break out of the loop once we've loaded at least one animation
          if (this.idleAction) {
            console.log('Successfully loaded and started animation from:', filePath);
            break;
          }
        } else {
          console.log(`No animations found in ${filePath}`);
        }
      } catch (error) {
        console.log('Could not load animation file:', filePath, '- Error:', error.message);
      }
    }
    
    // Final check - if still no animations, create basic ones and start them
    if (!this.idleAction && !this.talkingAction) {
      console.log('No animations loaded from separate files, creating basic animation');
      this.createBasicBreathingAnimation();
      this.forceOutOfTPose();
    } else {
      // Make sure we have both idle and talking animations
      if (this.idleAction && !this.talkingAction) {
        this.talkingAction = this.idleAction.clone();
        this.talkingAction.setLoop(THREE.LoopRepeat);
        console.log('Cloned idle as talking animation');
      } else if (this.talkingAction && !this.idleAction) {
        this.idleAction = this.talkingAction.clone();
        this.idleAction.setLoop(THREE.LoopRepeat);
        console.log('Cloned talking as idle animation');
      }
      
      // Make sure idle animation is running if we have one but no current animation
      if (this.idleAction && !this.currentAnimation) {
        this.idleAction.play();
        this.currentAnimation = 'idle';
        console.log('Started idle animation');
      }
      
      // Apply T-pose fix after animations are loaded
      this.forceOutOfTPose();
    }
  }
  
  createBasicBreathingAnimation() {
    if (!this.avatar) {
      console.warn('Cannot create basic animation - no avatar');
      return;
    }
    
    console.log('Creating basic breathing animation to avoid T-pose and provide movement');
    
    // Create a simple breathing animation using the avatar's scale or position
    const breathingAnimation = () => {
      if (!this.avatar) return;
      
      const time = Date.now() * 0.001;
      const breathingIntensity = 0.02;
      
      // Subtle breathing motion
      this.avatar.scale.y = 1.5 + Math.sin(time * 2) * breathingIntensity;
      
      // Slight swaying motion
      this.avatar.rotation.y = Math.sin(time * 0.5) * 0.05;
      
      // Subtle head movement if we can find the head
      this.avatar.traverse((child) => {
        if (child.name && child.name.toLowerCase().includes('head')) {
          child.rotation.y = Math.sin(time * 0.8) * 0.03;
          child.rotation.x = Math.sin(time * 1.2) * 0.02;
        }
      });
    };
    
    this.basicAnimation = breathingAnimation;
    console.log('Basic breathing animation created and will run in render loop');
  }
  
  setupMorphTargets() {
    const morphTargets = {};
    
    // Comprehensive viseme mapping for Ready Player Me avatars
    const visemeMapping = {
      'viseme_sil': ['mouthClose', 'mouthNeutral', 'neutral', 'mouthClosed'],
      'viseme_PP': ['mouthPucker', 'mouthFunnel', 'mouthKiss', 'mouthPout'],
      'viseme_FF': ['mouthShrugLower', 'mouthLowerDown', 'mouthPress'],
      'viseme_TH': ['mouthShrugLower', 'mouthLowerDown', 'tongueOut'],
      'viseme_DD': ['mouthOpen', 'jawOpen', 'mouthAh', 'jawDrop'],
      'viseme_kk': ['mouthOpen', 'jawOpen', 'mouthWide'],
      'viseme_CH': ['mouthShrugUpper', 'mouthUpperUp', 'mouthSh'],
      'viseme_SS': ['mouthFrown', 'mouthSad', 'mouthSs'],
      'viseme_nn': ['mouthClose', 'mouthPress', 'mouthMm'],
      'viseme_RR': ['mouthPucker', 'mouthRoll', 'mouthRr'],
      'viseme_aa': ['mouthOpen', 'jawOpen', 'mouthAh', 'mouthAa'],
      'viseme_E': ['mouthSmile', 'mouthHappy', 'mouthEh', 'mouthEe'],
      'viseme_I': ['mouthSmile', 'mouthEe', 'mouthIh'],
      'viseme_O': ['mouthFunnel', 'mouthPucker', 'mouthOh', 'mouthOo'],
      'viseme_U': ['mouthPucker', 'mouthFunnel', 'mouthOoh', 'mouthUu']
    };
    
    let foundTargetsCount = 0;
    
    // Find morph targets in avatar
    this.avatar.traverse((child) => {
      if (child.isMesh && child.morphTargetDictionary) {
        const availableTargets = Object.keys(child.morphTargetDictionary);
        console.log('Found mesh with morph targets:', child.name, availableTargets);
        
        // Map visemes to available morph targets
        Object.entries(visemeMapping).forEach(([viseme, targetNames]) => {
          if (!morphTargets[viseme]) { // Only map if not already found
            for (const targetName of targetNames) {
              // Try exact match first
              if (child.morphTargetDictionary[targetName] !== undefined) {
                morphTargets[viseme] = {
                  mesh: child,
                  index: child.morphTargetDictionary[targetName]
                };
                foundTargetsCount++;
                console.log(`Mapped ${viseme} to ${targetName} (index: ${morphTargets[viseme].index})`);
                break;
              }
              
              // Try case-insensitive match
              const lowerTargetName = targetName.toLowerCase();
              const matchingKey = availableTargets.find(key => key.toLowerCase() === lowerTargetName);
              if (matchingKey) {
                morphTargets[viseme] = {
                  mesh: child,
                  index: child.morphTargetDictionary[matchingKey]
                };
                foundTargetsCount++;
                console.log(`Mapped ${viseme} to ${matchingKey} (case-insensitive, index: ${morphTargets[viseme].index})`);
                break;
              }
            }
          }
        });
      }
    });
    
    this.morphTargets = morphTargets;
    console.log(`Morph targets setup complete: ${foundTargetsCount} visemes mapped out of ${Object.keys(visemeMapping).length}`);
    
    if (foundTargetsCount === 0) {
      console.warn('No morph targets found! Lipsync will not work. Available morph targets:');
      this.avatar.traverse((child) => {
        if (child.isMesh && child.morphTargetDictionary) {
          console.warn('Mesh:', child.name, 'Targets:', Object.keys(child.morphTargetDictionary));
        }
      });
    }
  }
  
  setupLipsync() {
    if (!this.audioContext || !this.analyser) {
      console.warn('Audio context not available for lipsync');
      return;
    }
    
    console.log('Lipsync system ready');
  }
  
  setupControls() {
    // Mouse controls for camera
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
    
    // Zoom with mouse wheel
    this.container.addEventListener('wheel', (event) => {
      const distance = this.camera.position.distanceTo(new THREE.Vector3(0, 1.5, 0));
      const newDistance = Math.max(0.8, Math.min(8, distance + event.deltaY * 0.01));
      
      this.camera.position.normalize().multiplyScalar(newDistance);
      this.camera.position.y = Math.max(0.5, this.camera.position.y);
      this.camera.lookAt(0, 1.5, 0);
    });
  }
  
  startRenderLoop() {
    const animate = () => {
      requestAnimationFrame(animate);
      
      const delta = this.clock.getDelta();
      
      // Update animations
      if (this.mixer) {
        this.mixer.update(delta);
      }
      
      // Update basic animation if no proper animations are available
      if (this.basicAnimation) {
        this.basicAnimation();
      }
      
      // Update lipsync if speaking
      if (this.isSpeaking) {
        this.updateLipsync();
      }
      
      // Render
      this.renderer.render(this.scene, this.camera);
    };
    
    animate();
  }
  
  // Main method for coordinated speech with animation and lipsync
  async speakWithAnimation(text, onTextUpdate = null) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('No ElevenLabs API key - showing text only');
      if (onTextUpdate) {
        await this.simulateTypewriter(text, onTextUpdate);
      }
      return;
    }
    
    try {
      console.log('Starting coordinated speech for:', text.substring(0, 50) + '...');
      
      // 1. Switch to talking animation immediately
      this.switchToTalkingAnimation();
      
      // 2. Start generating speech audio (async)
      const audioPromise = this.generateSpeechAudio(text);
      
      // 3. Start typewriter effect (async)
      const typingPromise = onTextUpdate ? this.simulateTypewriter(text, onTextUpdate) : Promise.resolve();
      
      // 4. Wait for both to complete
      const [audioBlob] = await Promise.all([audioPromise, typingPromise]);
      
      // 5. Play audio with synchronized lipsync
      if (audioBlob) {
        await this.playAudioWithLipsync(audioBlob);
      }
      
      // 6. Return to idle animation
      this.switchToIdleAnimation();
      
    } catch (error) {
      console.error('Speech failed:', error);
      this.switchToIdleAnimation();
    }
  }
  
  async simulateTypewriter(text, onTextUpdate) {
    const words = text.split(' ');
    let currentText = '';
    
    for (let i = 0; i < words.length; i++) {
      currentText += (i > 0 ? ' ' : '') + words[i];
      onTextUpdate(currentText);
      
      // Variable delay based on word length and punctuation
      let delay = 100 + Math.random() * 50; // Base delay
      
      if (words[i].endsWith('.') || words[i].endsWith('!') || words[i].endsWith('?')) {
        delay = 300; // Longer pause at sentence end
      } else if (words[i].endsWith(',') || words[i].endsWith(';')) {
        delay = 200; // Medium pause at comma
      }
      
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  async generateSpeechAudio(text) {
    try {
      console.log('Generating speech audio...');
      
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
      
      const audioBlob = await response.blob();
      console.log('Speech audio generated successfully');
      return audioBlob;
      
    } catch (error) {
      console.error('Speech generation error:', error);
      throw error;
    }
  }
  
  async playAudioWithLipsync(audioBlob) {
    return new Promise((resolve, reject) => {
      try {
        const audioUrl = URL.createObjectURL(audioBlob);
        
        // Setup audio element
        this.audioElement.src = audioUrl;
        this.audioElement.load();
        
        const onCanPlayThrough = () => {
          console.log('Audio ready, starting playback with lipsync...');
          
          // Connect audio to analyser
          if (!this.audioSource) {
            this.audioSource = this.audioContext.createMediaElementSource(this.audioElement);
            this.audioSource.connect(this.analyser);
            this.analyser.connect(this.audioContext.destination);
          }
          
          // Start lipsync processing
          this.startLipsyncProcessing();
          
          // Play audio
          this.audioElement.play().catch(reject);
        };
        
        const onEnded = () => {
          console.log('Audio playback ended');
          this.stopLipsyncProcessing();
          this.resetMouthToNeutral();
          
          // Cleanup
          this.audioElement.removeEventListener('canplaythrough', onCanPlayThrough);
          this.audioElement.removeEventListener('ended', onEnded);
          this.audioElement.removeEventListener('error', onError);
          URL.revokeObjectURL(audioUrl);
          
          resolve();
        };
        
        const onError = (error) => {
          console.error('Audio playback error:', error);
          this.stopLipsyncProcessing();
          
          // Cleanup
          this.audioElement.removeEventListener('canplaythrough', onCanPlayThrough);
          this.audioElement.removeEventListener('ended', onEnded);
          this.audioElement.removeEventListener('error', onError);
          URL.revokeObjectURL(audioUrl);
          
          reject(error);
        };
        
        this.audioElement.addEventListener('canplaythrough', onCanPlayThrough);
        this.audioElement.addEventListener('ended', onEnded);
        this.audioElement.addEventListener('error', onError);
        
      } catch (error) {
        reject(error);
      }
    });
  }
  
  startLipsyncProcessing() {
    this.isSpeaking = true;
    
    const processLipsync = () => {
      if (!this.isSpeaking) return;
      
      // Get frequency data
      const bufferLength = this.analyser.frequencyBinCount;
      const dataArray = new Uint8Array(bufferLength);
      this.analyser.getByteFrequencyData(dataArray);
      
      // Calculate audio intensity
      const average = dataArray.reduce((sum, value) => sum + value, 0) / bufferLength;
      this.visemeIntensity = Math.min(average / 100, 1.0);
      
      // Determine viseme based on frequency analysis
      if (average < 5) {
        this.currentViseme = 'viseme_sil';
      } else {
        this.currentViseme = this.analyzeFrequenciesForViseme(dataArray);
      }
      
      // Continue processing
      this.lipsyncUpdateId = requestAnimationFrame(processLipsync);
    };
    
    this.lipsyncUpdateId = requestAnimationFrame(processLipsync);
  }
  
  stopLipsyncProcessing() {
    this.isSpeaking = false;
    if (this.lipsyncUpdateId) {
      cancelAnimationFrame(this.lipsyncUpdateId);
      this.lipsyncUpdateId = null;
    }
  }
  
  analyzeFrequenciesForViseme(dataArray) {
    // Simple frequency-based viseme detection
    const lowFreq = dataArray.slice(0, 8).reduce((sum, value) => sum + value, 0) / 8;
    const midFreq = dataArray.slice(8, 32).reduce((sum, value) => sum + value, 0) / 24;
    const highFreq = dataArray.slice(32, 64).reduce((sum, value) => sum + value, 0) / 32;
    
    const total = lowFreq + midFreq + highFreq;
    const lowRatio = lowFreq / total;
    const midRatio = midFreq / total;
    
    if (lowRatio > 0.5) {
      return Math.random() > 0.5 ? 'viseme_aa' : 'viseme_O';
    } else if (midRatio > 0.4) {
      return Math.random() > 0.5 ? 'viseme_E' : 'viseme_I';
    } else {
      const consonants = ['viseme_PP', 'viseme_FF', 'viseme_TH', 'viseme_DD'];
      return consonants[Math.floor(Math.random() * consonants.length)];
    }
  }
  
  updateLipsync() {
    if (!this.isSpeaking) return;
    
    // Reset all morph targets
    Object.values(this.morphTargets).forEach(target => {
      if (target.mesh && target.mesh.morphTargetInfluences) {
        target.mesh.morphTargetInfluences[target.index] = 0;
      }
    });
    
    // Apply current viseme
    const target = this.morphTargets[this.currentViseme];
    if (target && target.mesh && target.mesh.morphTargetInfluences) {
      const intensity = Math.min(this.visemeIntensity * 0.8, 1.0);
      target.mesh.morphTargetInfluences[target.index] = intensity;
    }
  }
  
  resetMouthToNeutral() {
    Object.values(this.morphTargets).forEach(target => {
      if (target.mesh && target.mesh.morphTargetInfluences) {
        target.mesh.morphTargetInfluences[target.index] = 0;
      }
    });
  }
  
  switchToTalkingAnimation() {
    if (!this.mixer) {
      console.log('No mixer available - using basic animation instead');
      return;
    }
    
    if (!this.talkingAction) {
      console.log('No talking animation available - staying with current animation');
      return;
    }
    
    if (this.currentAnimation !== 'talking') {
      console.log('Switching to talking animation');
      
      if (this.idleAction) {
        this.idleAction.fadeOut(0.3);
      }
      
      this.talkingAction.reset().fadeIn(0.3).play();
      this.currentAnimation = 'talking';
    }
  }
  
  switchToIdleAnimation() {
    if (!this.mixer) {
      console.log('No mixer available - using basic animation instead');
      return;
    }
    
    if (!this.idleAction) {
      console.log('No idle animation available - staying with current animation');
      return;
    }
    
    if (this.currentAnimation !== 'idle') {
      console.log('Switching to idle animation');
      
      if (this.talkingAction) {
        this.talkingAction.fadeOut(0.3);
      }
      
      this.idleAction.reset().fadeIn(0.3).play();
      this.currentAnimation = 'idle';
    }
  }
  
  createFallbackAvatar() {
    console.log('Creating fallback avatar');
    
    const group = new THREE.Group();
    
    // Simple robot head
    const headGeometry = new THREE.SphereGeometry(0.5, 16, 16);
    const headMaterial = new THREE.MeshStandardMaterial({ color: 0x8cc8ff });
    const head = new THREE.Mesh(headGeometry, headMaterial);
    head.position.set(0, 1.5, 0);
    group.add(head);
    
    // Eyes
    const eyeGeometry = new THREE.SphereGeometry(0.08, 8, 8);
    const eyeMaterial = new THREE.MeshStandardMaterial({ color: 0x000000 });
    
    const leftEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    leftEye.position.set(-0.15, 1.6, 0.4);
    group.add(leftEye);
    
    const rightEye = new THREE.Mesh(eyeGeometry, eyeMaterial);
    rightEye.position.set(0.15, 1.6, 0.4);
    group.add(rightEye);
    
    // Simple body
    const bodyGeometry = new THREE.CapsuleGeometry(0.3, 0.8, 4, 8);
    const bodyMaterial = new THREE.MeshStandardMaterial({ color: 0x6ba3ff });
    const body = new THREE.Mesh(bodyGeometry, bodyMaterial);
    body.position.set(0, 0.5, 0);
    group.add(body);
    
    this.avatar = group;
    this.scene.add(this.avatar);
    
    console.log('Fallback avatar created');
  }
  
  // API methods
  async testApiKey() {
    if (!this.options.elevenlabsApiKey) {
      return { success: false, error: 'No API key provided' };
    }
    
    try {
      const response = await fetch('https://api.elevenlabs.io/v1/voices', {
        method: 'GET',
        headers: {
          'xi-api-key': this.options.elevenlabsApiKey
        }
      });
      
      if (!response.ok) {
        return { success: false, error: `API test failed: ${response.status}` };
      }
      
      const data = await response.json();
      return { success: true, voices: data.voices };
      
    } catch (error) {
      return { success: false, error: error.message };
    }
  }
  
  setElevenlabsApiKey(apiKey) {
    this.options.elevenlabsApiKey = apiKey;
  }
  
  onWindowResize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }
  
  destroy() {
    // Stop all processing
    this.stopLipsyncProcessing();
    
    // Clean up audio
    if (this.audioElement) {
      this.audioElement.pause();
      this.audioElement.src = '';
    }
    
    // Clean up audio context
    if (this.audioContext) {
      this.audioContext.close();
    }
    
    // Clean up renderer
    if (this.renderer) {
      this.container.removeChild(this.renderer.domElement);
    }
    
    // Remove event listeners
    window.removeEventListener('resize', this.onWindowResize);
  }
  
  // Manual function to reload animations - for debugging
  async reloadAnimations() {
    console.log('🔄 Manually reloading animations...');
    
    // Clear existing animations
    this.idleAction = null;
    this.talkingAction = null;
    this.currentAnimation = null;
    
    // Try to load animations again
    await this.loadSeparateAnimations();
    
    // Force out of T-pose
    this.forceOutOfTPose();
    
    console.log('✅ Animation reload completed');
  }
}

// Export for global use
window.AvatarManagerV2 = AvatarManagerV2;
