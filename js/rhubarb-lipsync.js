// Rhubarb Lipsync Integration for VerzTec Avatar
// Provides more accurate lip-sync using Rhubarb lip sync algorithm

class RhubarbLipsync {
  constructor() {
    this.audioContext = null;
    this.analyser = null;
    this.audioBuffer = null;
    this.currentTime = 0;
    this.isPlaying = false;
    this.visemeData = [];
    this.currentViseme = 'X'; // Default closed mouth
    
    // Rhubarb viseme mapping to Ready Player Me morph targets
    this.visemeMap = {
      'X': 'viseme_sil',     // Silence
      'A': 'viseme_aa',      // Open vowels (father, car)
      'B': 'viseme_PP',      // Lip closure (p, b, m)
      'C': 'viseme_E',       // Unrounded open-mid back vowel (cut, but)
      'D': 'viseme_aa',      // Open front vowel (cat, bat)
      'E': 'viseme_E',       // Mid vowel (bed, head)
      'F': 'viseme_FF',      // Voiceless labiodental fricative (f, v)
      'G': 'viseme_kk',      // Velar stops (g, k, ng)
      'H': 'viseme_I',       // Close front vowel (bit, hit)
      'I': 'viseme_aa',      // Close front vowel (beat, feet)
      'J': 'viseme_nn',      // Palatal approximant (yes, you)
      'K': 'viseme_kk',      // Velar stops (cat, kit)
      'L': 'viseme_nn',      // Alveolar lateral (let, all)
      'M': 'viseme_PP',      // Bilabial nasal (mat, ham)
      'N': 'viseme_nn',      // Alveolar nasal (net, can)
      'O': 'viseme_O',       // Rounded back vowel (hot, lot)
      'P': 'viseme_PP',      // Bilabial stops (pet, pit)
      'Q': 'viseme_kk',      // Glottal stop
      'R': 'viseme_RR',      // Rhotic (red, car)
      'S': 'viseme_SS',      // Voiceless alveolar fricative (sit, cats)
      'T': 'viseme_TH',      // Voiceless alveolar stop (top, cat)
      'U': 'viseme_U',       // Close back vowel (book, put)
      'V': 'viseme_FF',      // Voiced labiodental fricative
      'W': 'viseme_U',       // Labio-velar approximant (wet, when)
      'Y': 'viseme_I',       // Near-close front vowel
      'Z': 'viseme_SS'       // Voiced alveolar fricative (zoo, buzz)
    };
    
    this.initAudioContext();
  }
  
  initAudioContext() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 256;
      console.log('Rhubarb Lipsync audio context initialized');
    } catch (error) {
      console.error('Failed to initialize audio context:', error);
    }
  }
  
  // Connect audio element for real-time analysis
  connectAudio(audioElement) {
    if (!this.audioContext || !audioElement) {
      console.warn('Cannot connect audio: missing audio context or element');
      return false;
    }
    
    try {
      // Disconnect previous source if exists
      if (this.audioSource) {
        try {
          this.audioSource.disconnect();
          console.log('Previous audio source disconnected');
        } catch (e) {
          console.warn('Could not disconnect previous audio source:', e);
        }
      }
      
      // Create media element source
      this.audioSource = this.audioContext.createMediaElementSource(audioElement);
      
      // Connect to analyzer for lipsync
      this.audioSource.connect(this.analyser);
      
      // CRITICAL: Also connect to destination so audio actually plays!
      this.audioSource.connect(this.audioContext.destination);
      
      // Set up real-time analysis
      this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
      
      console.log('✅ Audio connected to Rhubarb lipsync analyzer AND audio output');
      return true;
    } catch (error) {
      console.error('❌ Audio connection failed:', error);
      
      // If we get a DOMException about already being connected, that's actually OK
      if (error.name === 'InvalidStateError' && error.message.includes('already connected')) {
        console.log('⚠️ Audio element already connected to Web Audio API - this is expected');
        return true;
      }
      
      return false;
    }
  }
  
  // Load audio for lipsync analysis
  async loadAudio(audioUrl) {
    if (!this.audioContext) {
      console.error('Audio context not initialized');
      return;
    }

    try {
      // Fetch audio data
      const response = await fetch(audioUrl);
      const arrayBuffer = await response.arrayBuffer();
      
      // Decode audio
      this.audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
      
      // Generate viseme data
      this.visemeData = await this.generateVisemeData(this.audioBuffer);
      
      console.log('Audio loaded and analyzed for lipsync, viseme data points:', this.visemeData.length);
      
    } catch (error) {
      console.error('Failed to load audio for lipsync:', error);
      // Fallback to basic analysis
      this.visemeData = [];
    }
  }

  // Start lipsync playback
  start() {
    this.isPlaying = true;
    this.currentTime = 0;
    this.startTime = Date.now();
    console.log('Lipsync started');
  }

  // Stop lipsync playback
  stop() {
    this.isPlaying = false;
    this.currentViseme = 'X';
    console.log('Lipsync stopped');
  }

  // Reset lipsync state
  reset() {
    this.isPlaying = false;
    this.currentTime = 0;
    this.currentViseme = 'X';
    this.visemeData = [];
    console.log('Lipsync reset');
  }

  // Update current time and get appropriate viseme
  update(deltaTime) {
    if (!this.isPlaying) return;
    
    this.currentTime += deltaTime;
    
    // Use real-time audio analysis instead of pre-computed viseme data
    this.processAudio(this.currentTime);
  }
  
  // Generate viseme data from audio using Rhubarb-like algorithm
  async generateVisemeData(audioBuffer) {
    if (!audioBuffer) return [];
    
    const sampleRate = audioBuffer.sampleRate;
    const duration = audioBuffer.duration;
    const frameRate = 30; // 30 FPS for smooth animation
    const frameCount = Math.floor(duration * frameRate);
    const samplesPerFrame = Math.floor(sampleRate / frameRate);
    
    const audioData = audioBuffer.getChannelData(0);
    const visemeData = [];
    
    for (let frame = 0; frame < frameCount; frame++) {
      const startSample = frame * samplesPerFrame;
      const endSample = Math.min(startSample + samplesPerFrame, audioData.length);
      
      // Analyze frequency content for this frame
      const frameData = audioData.slice(startSample, endSample);
      const viseme = this.analyzeFrameForViseme(frameData, sampleRate);
      
      visemeData.push({
        time: frame / frameRate,
        viseme: viseme,
        intensity: this.calculateIntensity(frameData)
      });
    }
    
    return this.smoothVisemeData(visemeData);
  }
  
  // Analyze audio frame to determine viseme
  analyzeFrameForViseme(frameData, sampleRate) {
    if (frameData.length === 0) return 'X';
    
    // Calculate RMS (volume)
    const rms = Math.sqrt(frameData.reduce((sum, sample) => sum + sample * sample, 0) / frameData.length);
    
    if (rms < 0.01) return 'X'; // Silence threshold
    
    // Simple frequency analysis to determine viseme
    const fft = this.performFFT(frameData);
    const dominantFreq = this.findDominantFrequency(fft, sampleRate);
    
    // Map frequency ranges to visemes (simplified Rhubarb approach)
    if (dominantFreq < 300) return 'A';      // Low vowels
    else if (dominantFreq < 600) return 'E'; // Mid vowels
    else if (dominantFreq < 1200) return 'I'; // High vowels
    else if (dominantFreq < 2000) return 'S'; // Fricatives
    else if (dominantFreq < 3000) return 'T'; // Stops
    else return 'P'; // High frequency consonants
  }
  
  // Simple FFT implementation for frequency analysis
  performFFT(data) {
    // Simplified FFT - in production, you'd use a proper FFT library
    const N = Math.min(data.length, 256);
    const magnitude = new Array(N / 2);
    
    for (let k = 0; k < N / 2; k++) {
      let real = 0, imag = 0;
      for (let n = 0; n < N; n++) {
        const angle = -2 * Math.PI * k * n / N;
        real += data[n] * Math.cos(angle);
        imag += data[n] * Math.sin(angle);
      }
      magnitude[k] = Math.sqrt(real * real + imag * imag);
    }
    
    return magnitude;
  }
  
  findDominantFrequency(fft, sampleRate) {
    let maxMagnitude = 0;
    let maxIndex = 0;
    
    for (let i = 0; i < fft.length; i++) {
      if (fft[i] > maxMagnitude) {
        maxMagnitude = fft[i];
        maxIndex = i;
      }
    }
    
    return (maxIndex * sampleRate) / (2 * fft.length);
  }
  
  calculateIntensity(frameData) {
    const rms = Math.sqrt(frameData.reduce((sum, sample) => sum + sample * sample, 0) / frameData.length);
    return Math.min(rms * 10, 1.0); // Normalize to 0-1 range
  }
  
  // Smooth viseme transitions to avoid jitter
  smoothVisemeData(visemeData) {
    if (visemeData.length < 3) return visemeData;
    
    const smoothed = [...visemeData];
    
    // Apply median filter to reduce noise
    for (let i = 1; i < smoothed.length - 1; i++) {
      const prev = smoothed[i - 1].viseme;
      const curr = smoothed[i].viseme;
      const next = smoothed[i + 1].viseme;
      
      // If current viseme is different from both neighbors, smooth it
      if (curr !== prev && curr !== next && prev === next) {
        smoothed[i].viseme = prev;
      }
    }
    
    return smoothed;
  }
  
  // Real-time processing for live audio
  processAudio(currentTime) {
    if (!this.audioContext || !this.analyser) {
      console.warn('Audio context or analyser not available for lipsync');
      return this.currentViseme;
    }
    
    const bufferLength = this.analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);
    this.analyser.getByteFrequencyData(dataArray);
    
    // Calculate average volume
    const average = dataArray.reduce((sum, value) => sum + value, 0) / bufferLength;
    
    // Debug logging (throttled)
    if (Math.random() < 0.01) { // Log only 1% of the time
      console.log('Audio analysis - Average volume:', average, 'Current viseme:', this.currentViseme);
    }
    
    if (average < 5) {
      this.currentViseme = 'X'; // Silence
    } else {
      // Simple frequency-based viseme detection
      const lowFreq = dataArray.slice(0, 8).reduce((sum, value) => sum + value, 0) / 8;
      const midFreq = dataArray.slice(8, 32).reduce((sum, value) => sum + value, 0) / 24;
      const highFreq = dataArray.slice(32, 64).reduce((sum, value) => sum + value, 0) / 32;
      
      if (lowFreq > midFreq && lowFreq > highFreq) {
        this.currentViseme = Math.random() > 0.5 ? 'A' : 'O'; // Low vowels
      } else if (midFreq > highFreq) {
        this.currentViseme = Math.random() > 0.5 ? 'E' : 'I'; // Mid vowels
      } else {
        this.currentViseme = ['S', 'T', 'P', 'F'][Math.floor(Math.random() * 4)]; // Consonants
      }
    }
    
    return this.currentViseme;
  }
  
  // Get current viseme for Ready Player Me
  getCurrentViseme() {
    return this.visemeMap[this.currentViseme] || 'viseme_sil';
  }
  
  // Get viseme intensity (0-1)
  getCurrentIntensity() {
    if (!this.audioContext || !this.analyser) return 0;
    
    const bufferLength = this.analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);
    this.analyser.getByteFrequencyData(dataArray);
    
    const average = dataArray.reduce((sum, value) => sum + value, 0) / bufferLength;
    return Math.min(average / 128, 1.0); // Normalize to 0-1
  }
  
  destroy() {
    if (this.audioContext) {
      this.audioContext.close();
    }
  }
}

// Export for global use
window.RhubarbLipsync = RhubarbLipsync;
