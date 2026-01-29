// Three.js Avatar Manager for VerzTec Chatbot
// Integrates Ready Player Me avatar with lipsync and ElevenLabs TTS

class AvatarManager {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = {
      avatarUrl: options.avatarUrl || 'assets/avatars/models/64f1a714fe61576b46f27ca2.glb',
      animationsUrl: options.animationsUrl || 'assets/avatars/models/animations.glb',
      elevenlabsApiKey: options.elevenlabsApiKey || 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7',
      voice: options.voice || 'EXAVITQu4vr4xnSDxMaL', // Bella voice
      ...options
    };
    
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.avatar = null;
    this.mixer = null;
    this.clock = new THREE.Clock();
    this.rhubarbLipsync = new RhubarbLipsync();
    this.audioElement = null;
    this.morphTargets = {};
    this.isInitialized = false;
    this.readyPlayerMeController = null;
    
    // Animation state
    this.idleAction = null;
    this.talkingAction = null;
    this.currentAnimation = null;
    
    // Speech state
    this.isSpeaking = false;
    this.speechStartTime = 0;
    
    this.init();
  }
  
  async init() {
    try {
      this.setupScene();
      this.setupLighting();
      this.setupCamera();
      this.setupRenderer();
      await this.loadAvatar();
      this.setupLipsync();
      this.setupControls();
      this.animate();
      
      this.isInitialized = true;
      console.log('Avatar Manager initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Avatar Manager:', error);
    }
  }
  
  setupScene() {
    this.scene = new THREE.Scene();
    this.scene.background = null; // Transparent background
    
    const ambientLight = new THREE.AmbientLight(0x404040, 0.8);
    this.scene.add(ambientLight);
  }
  
  setupLighting() {
    // Key light
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.2);
    keyLight.position.set(5, 5, 5);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.width = 2048;
    keyLight.shadow.mapSize.height = 2048;
    this.scene.add(keyLight);
    
    // Fill light
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
    fillLight.position.set(-3, 2, 4);
    this.scene.add(fillLight);
    
    // Rim light
    const rimLight = new THREE.DirectionalLight(0xffffff, 0.5);
    rimLight.position.set(0, 3, -5);
    this.scene.add(rimLight);
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
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.2;
    this.renderer.setClearColor(0x000000, 0);
    
    this.container.appendChild(this.renderer.domElement);
    
    window.addEventListener('resize', () => this.onWindowResize());
  }
  
  async loadAvatar() {
    const loader = new THREE.GLTFLoader();
    
    try {
      const gltf = await new Promise((resolve, reject) => {
        loader.load(this.options.avatarUrl, resolve, 
          (progress) => {
            console.log('Avatar loading progress:', (progress.loaded / progress.total * 100) + '%');
          }, 
          reject);
      });
      
      this.avatar = gltf.scene;
      this.scene.add(this.avatar);
      
      // Load animations
      let animationClips = gltf.animations || [];
      
      if (this.options.animationsUrl && this.options.animationsUrl !== this.options.avatarUrl) {
        try {
          const animationGltf = await new Promise((resolve, reject) => {
            loader.load(this.options.animationsUrl, resolve, 
              (progress) => {
                console.log('Animation loading progress:', (progress.loaded / progress.total * 100) + '%');
              }, 
              reject);
          });
          
          if (animationGltf.animations && animationGltf.animations.length > 0) {
            animationClips = [...animationClips, ...animationGltf.animations];
          }
        } catch (error) {
          console.warn('Failed to load separate animations:', error);
        }
      }
      
      // Setup animations
      if (animationClips && animationClips.length > 0) {
        this.mixer = new THREE.AnimationMixer(this.avatar);
        
        animationClips.forEach((clip) => {
          const action = this.mixer.clipAction(clip);
          const clipName = clip.name.toLowerCase();
          
          if (clipName.includes('idle')) {
            this.idleAction = action;
            console.log('Found idle animation:', clip.name);
          } else if (clipName.includes('talk') || clipName.includes('speaking')) {
            this.talkingAction = action;
            console.log('Found talking animation:', clip.name);
          }
        });
        
        // Start with idle animation
        if (this.idleAction) {
          this.idleAction.play();
          this.currentAnimation = 'idle';
          console.log('Started idle animation');
        }
      }
      
      // Setup Ready Player Me controller
      this.readyPlayerMeController = new ReadyPlayerMeAvatar(this);
      this.morphTargets = this.readyPlayerMeController.findMorphTargets(this.avatar);
      this.readyPlayerMeController.setupExpressionController(this.avatar, this.morphTargets);
      
      // Position avatar
      this.avatar.position.set(0, 0, 0);
      this.avatar.scale.set(1.5, 1.5, 1.5);
      
      console.log('Avatar loaded successfully');
      
    } catch (error) {
      console.error('Failed to load avatar:', error);
    }
  }
  
  setupLipsync() {
    this.audioElement = document.createElement('audio');
    this.audioElement.crossOrigin = 'anonymous';
    this.audioElement.preload = 'auto';
    
    // Connect audio to lipsync analyzer
    if (this.rhubarbLipsync.connectAudio(this.audioElement)) {
      console.log('Audio connected to lipsync system');
    } else {
      console.warn('Failed to connect audio to lipsync system');
    }
    
    console.log('Lipsync system initialized');
  }
  
  setupControls() {
    // Setup any additional controls if needed
  }
  
  animate() {
    requestAnimationFrame(() => this.animate());
    
    const deltaTime = this.clock.getDelta();
    
    // Update animations
    if (this.mixer) {
      this.mixer.update(deltaTime);
    }
    
    // Update lipsync
    this.updateMorphTargets();
    
    // Render
    this.renderer.render(this.scene, this.camera);
  }
  
  updateMorphTargets() {
    if (!this.readyPlayerMeController) return;
    
    // Get current viseme and intensity from lipsync
    const viseme = this.rhubarbLipsync.getCurrentViseme();
    const intensity = this.rhubarbLipsync.getCurrentIntensity();
    
    // Apply lipsync morphs with proper intensity
    if (this.isSpeaking && intensity > 0.05) {
      this.readyPlayerMeController.updateLipsync(viseme, intensity * 0.8);
    } else if (this.isSpeaking) {
      // Keep mouth slightly open during talking animation
      this.readyPlayerMeController.updateLipsync('viseme_aa', 0.2);
    } else {
      // Idle state - closed mouth
      this.readyPlayerMeController.updateLipsync('viseme_sil', 1.0);
    }
  }
  
  switchAnimation(type) {
    if (!this.mixer) return;
    
    console.log(`Switching animation to: ${type}`);
    
    if (type === 'talking' && this.talkingAction && this.currentAnimation !== 'talking') {
      if (this.idleAction) {
        this.idleAction.fadeOut(0.3);
      }
      this.talkingAction.reset().fadeIn(0.3).play();
      this.talkingAction.setLoop(THREE.LoopRepeat);
      this.currentAnimation = 'talking';
      console.log('Switched to talking animation');
    } else if (type === 'idle' && this.idleAction && this.currentAnimation !== 'idle') {
      if (this.talkingAction) {
        this.talkingAction.fadeOut(0.3);
      }
      this.idleAction.reset().fadeIn(0.3).play();
      this.idleAction.setLoop(THREE.LoopRepeat);
      this.currentAnimation = 'idle';
      console.log('Switched to idle animation');
    }
  }
  
  // Type text with a realistic typing effect
  async typeText(text, onTextUpdate, delay = 30) {
    let currentText = '';
    for (let i = 0; i < text.length; i++) {
      currentText += text[i];
      onTextUpdate(currentText);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  // Play audio with proper synchronization of animation and lipsync
  async playAudioWithSynchronization(audioUrl) {
    if (!this.audioElement) {
      console.error('Audio element not initialized');
      return;
    }

    try {
      // Set up audio
      this.audioElement.src = audioUrl;
      
      // Wait for audio to be ready
      await new Promise((resolve, reject) => {
        const timeoutId = setTimeout(() => {
          reject(new Error('Audio load timeout'));
        }, 10000);
        
        this.audioElement.addEventListener('loadeddata', () => {
          clearTimeout(timeoutId);
          resolve();
        }, { once: true });
        
        this.audioElement.addEventListener('error', (e) => {
          clearTimeout(timeoutId);
          reject(e);
        }, { once: true });
        
        this.audioElement.load();
      });

      // Start talking animation and speaking state
      this.switchAnimation('talking');
      this.isSpeaking = true;
      this.speechStartTime = Date.now();
      
      // Initialize lipsync
      this.rhubarbLipsync.reset();
      await this.rhubarbLipsync.loadAudio(audioUrl);
      
      // Start audio playback
      const playPromise = this.audioElement.play();
      if (playPromise !== undefined) {
        await playPromise;
      }
      
      // Start lipsync
      this.rhubarbLipsync.start();
      
      // Wait for audio to finish
      await new Promise((resolve) => {
        this.audioElement.addEventListener('ended', () => {
          // Keep talking animation for a bit longer for natural feel
          setTimeout(() => {
            this.isSpeaking = false;
            this.switchAnimation('idle');
            this.rhubarbLipsync.stop();
            resolve();
          }, 300);
        }, { once: true });
      });
      
    } catch (error) {
      console.error('Audio playback failed:', error);
      this.isSpeaking = false;
      this.switchAnimation('idle');
      throw error;
    }
  }
  
  async speak(text, options = {}) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('ElevenLabs API key not provided');
      return;
    }

    try {
      // Generate speech audio
      const audioBlob = await this.generateSpeech(text);
      const audioUrl = URL.createObjectURL(audioBlob);
      
      // Play audio with proper synchronization
      await this.playAudioWithSynchronization(audioUrl);
      
      URL.revokeObjectURL(audioUrl);
      
    } catch (error) {
      console.error('Failed to speak:', error);
      this.switchAnimation('idle');
      this.isSpeaking = false;
    }
  }
  
  // Method to start speaking immediately and stream text in sync
  async speakWithTextStream(text, onTextUpdate = null) {
    if (!this.options.elevenlabsApiKey) {
      console.warn('ElevenLabs API key not provided, showing text only');
      if (onTextUpdate) {
        // Stream the text letter by letter for typing effect
        await this.typeText(text, onTextUpdate);
      }
      return;
    }

    try {
      // Generate speech audio in parallel with text display
      const audioPromise = this.generateSpeech(text);
      
      // Start displaying text immediately with typing effect
      if (onTextUpdate) {
        await this.typeText(text, onTextUpdate);
      }
      
      // Wait for audio to be ready
      const audioBlob = await audioPromise;
      const audioUrl = URL.createObjectURL(audioBlob);
      
      // Play audio with proper synchronization
      await this.playAudioWithSynchronization(audioUrl);
      
      // Clean up
      URL.revokeObjectURL(audioUrl);
      
    } catch (error) {
      console.error('Speech failed, but text is displayed:', error);
      this.switchAnimation('idle');
      this.isSpeaking = false;
    }
  }
  
  async generateSpeech(text) {
    try {
      console.log('Generating speech for:', text.substring(0, 50) + '...');
      console.log('Using voice:', this.options.voice);
      
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
        const errorText = await response.text();
        console.error('ElevenLabs API error:', response.status, errorText);
        throw new Error(`ElevenLabs API error: ${response.status} - ${errorText}`);
      }
      
      const audioBlob = await response.blob();
      console.log('Speech generated successfully, blob size:', audioBlob.size);
      return audioBlob;
      
    } catch (error) {
      console.error('Speech generation failed:', error);
      throw error;
    }
  }
  
  // Test the ElevenLabs API key
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
        const errorText = await response.text();
        return { success: false, error: `API key test failed: ${response.status} - ${errorText}` };
      }
      
      const data = await response.json();
      console.log('API key test successful. Available voices:', data.voices.length);
      return { success: true, voices: data.voices };
      
    } catch (error) {
      return { success: false, error: `API key test error: ${error.message}` };
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
    // Clean up intervals
    if (this.lipsyncUpdateInterval) {
      clearInterval(this.lipsyncUpdateInterval);
    }
    
    // Clean up controllers
    if (this.readyPlayerMeController) {
      this.readyPlayerMeController.destroy();
    }
    if (this.rhubarbLipsync) {
      this.rhubarbLipsync.destroy();
    }
    
    // Clean up renderer
    if (this.renderer) {
      this.container.removeChild(this.renderer.domElement);
    }
    
    // Clean up audio
    if (this.audioElement) {
      this.audioElement.pause();
      this.audioElement.src = '';
    }
  }
}

// Export for global use
window.AvatarManager = AvatarManager;
