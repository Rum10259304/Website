// Simplified Lipsync library for VerzTec Chatbot
// Based on wawa-lipsync but adapted for vanilla JavaScript

const VISEMES = {
  sil: "viseme_sil",
  PP: "viseme_PP",
  FF: "viseme_FF",
  TH: "viseme_TH",
  DD: "viseme_DD",
  kk: "viseme_kk",
  CH: "viseme_CH",
  SS: "viseme_SS",
  nn: "viseme_nn",
  RR: "viseme_RR",
  aa: "viseme_aa",
  E: "viseme_E",
  I: "viseme_I",
  O: "viseme_O",
  U: "viseme_U"
};

const FSMStates = {
  silence: "silence",
  vowel: "vowel",
  plosive: "plosive",
  fricative: "fricative"
};

const VISEMES_STATES = {
  [VISEMES.sil]: FSMStates.silence,
  [VISEMES.PP]: FSMStates.plosive,
  [VISEMES.FF]: FSMStates.fricative,
  [VISEMES.TH]: FSMStates.fricative,
  [VISEMES.DD]: FSMStates.plosive,
  [VISEMES.kk]: FSMStates.plosive,
  [VISEMES.CH]: FSMStates.fricative,
  [VISEMES.SS]: FSMStates.fricative,
  [VISEMES.nn]: FSMStates.plosive,
  [VISEMES.RR]: FSMStates.fricative,
  [VISEMES.aa]: FSMStates.vowel,
  [VISEMES.E]: FSMStates.vowel,
  [VISEMES.I]: FSMStates.vowel,
  [VISEMES.O]: FSMStates.vowel,
  [VISEMES.U]: FSMStates.vowel
};

function average(arr) {
  return arr.reduce((sum, val) => sum + val, 0) / arr.length;
}

class Lipsync {
  constructor(params = {}) {
    const { fftSize = 2048, historySize = 10 } = params;
    
    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    this.analyser = this.audioContext.createAnalyser();
    this.analyser.fftSize = fftSize;
    this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
    this.history = [];
    this.historySize = historySize;
    this.sampleRate = this.audioContext.sampleRate;
    this.binWidth = this.sampleRate / fftSize;
    
    this.features = null;
    this.viseme = VISEMES.sil;
    this.state = FSMStates.silence;
    this.audioSource = null;
    
    // Define frequency bands (in Hz)
    this.bands = [
      { start: 50, end: 200 },    // Band 1: Low energy
      { start: 200, end: 400 },   // Band 2: F1 lower
      { start: 400, end: 800 },   // Band 3: F1 mid
      { start: 800, end: 1500 },  // Band 4: F2 front
      { start: 1500, end: 2500 }, // Band 5: F2/F3
      { start: 2500, end: 4000 }, // Band 6: Fricatives
      { start: 4000, end: 8000 }  // Band 7: High fricatives
    ];
  }
  
  connectAudio(audio) {
    this.audioContext.resume();
    this.history = [];
    this.features = null;
    this.state = FSMStates.silence;
    
    if (this.audioSource === audio) {
      return;
    }
    
    this.audioSource = audio;
    if (!audio.src) {
      console.warn("An audio source must be set before connecting");
      return;
    }
    
    const source = this.audioContext.createMediaElementSource(audio);
    source.connect(this.analyser);
    this.analyser.connect(this.audioContext.destination);
  }
  
  extractFeatures() {
    this.analyser.getByteFrequencyData(this.dataArray);
    
    // Convert frequency ranges to bin indices
    const bandEnergies = this.bands.map(({ start, end }) => {
      const startBin = Math.round(start / this.binWidth);
      const endBin = Math.min(Math.round(end / this.binWidth), this.dataArray.length - 1);
      return average(Array.from(this.dataArray.slice(startBin, endBin))) / 255;
    });
    
    // Compute spectral centroid
    let sumAmplitude = 0;
    let weightedSum = 0;
    for (let i = 0; i < this.dataArray.length; i++) {
      const freq = i * this.binWidth;
      const amp = this.dataArray[i] / 255;
      sumAmplitude += amp;
      weightedSum += freq * amp;
    }
    
    const centroid = sumAmplitude > 0 ? weightedSum / sumAmplitude : 0;
    const volume = average(bandEnergies);
    
    const deltaBands = bandEnergies.map((energy, index) => {
      if (this.history.length < 2) return 0;
      const previousEnergy = this.history[this.history.length - 2].bands[index];
      return energy - previousEnergy;
    });
    
    const features = {
      bands: bandEnergies,
      deltaBands: deltaBands,
      volume,
      centroid
    };
    
    // Update history
    if (sumAmplitude > 0) {
      this.history.push(features);
      if (this.history.length > this.historySize) {
        this.history.shift();
      }
    }
    
    return features;
  }
  
  getAveragedFeatures() {
    const len = this.history.length;
    const sum = {
      volume: 0,
      centroid: 0,
      bands: Array(this.bands.length).fill(0)
    };
    
    for (const f of this.history) {
      sum.volume += f.volume;
      sum.centroid += f.centroid;
      f.bands.forEach((b, i) => (sum.bands[i] += b));
    }
    
    const bands = sum.bands.map(b => b / len);
    return {
      volume: sum.volume / len,
      centroid: sum.centroid / len,
      bands,
      deltaBands: bands
    };
  }
  
  computeVisemeScores(current, avg, dVolume, dCentroid) {
    const scores = {
      [VISEMES.sil]: 0,
      [VISEMES.PP]: 0,
      [VISEMES.FF]: 0,
      [VISEMES.TH]: 0,
      [VISEMES.DD]: 0,
      [VISEMES.kk]: 0,
      [VISEMES.CH]: 0,
      [VISEMES.SS]: 0,
      [VISEMES.nn]: 0,
      [VISEMES.RR]: 0,
      [VISEMES.aa]: 0,
      [VISEMES.E]: 0,
      [VISEMES.I]: 0,
      [VISEMES.O]: 0,
      [VISEMES.U]: 0
    };
    
    // Silence
    if (avg.volume < 0.2 && current.volume < 0.2) {
      scores[VISEMES.sil] = 1.0;
    }
    
    // Simple viseme detection based on frequency analysis
    if (avg.volume > 0.1) {
      const [b1, b2, b3, b4, b5] = avg.bands;
      
      // Vowel detection
      if (b3 > 0.1 || b4 > 0.1) {
        if (b4 > b3) {
          scores[VISEMES.aa] = 0.8;
        }
        if (b3 > b2 && b3 > b4) {
          scores[VISEMES.I] = 0.7;
        }
        if (b3 > 0.25 && b5 > 0.25) {
          scores[VISEMES.O] = 0.7;
        }
        if (b3 < 0.15 && b5 < 0.15) {
          scores[VISEMES.U] = 0.7;
        }
        if (b2 > b3 && b3 > b4) {
          scores[VISEMES.E] = 1;
        }
      }
      
      // Consonant detection
      if (current.centroid > 4000) {
        scores[VISEMES.SS] += 0.6;
        scores[VISEMES.FF] += 0.5;
      }
      
      if (dVolume > 0.3) {
        scores[VISEMES.PP] += 0.8;
        scores[VISEMES.DD] += 0.7;
        scores[VISEMES.kk] += 0.6;
      }
    }
    
    return scores;
  }
  
  adjustScoresForConsistency(scores) {
    const adjustedScores = { ...scores };
    
    if (this.viseme && this.state) {
      for (const viseme in adjustedScores) {
        const isCurrentViseme = viseme === this.viseme;
        if (isCurrentViseme) {
          adjustedScores[viseme] *= 1.3;
        }
      }
    }
    
    return adjustedScores;
  }
  
  detectState() {
    const current = this.history[this.history.length - 1];
    if (!current) {
      this.state = FSMStates.silence;
      this.viseme = VISEMES.sil;
      return;
    }
    
    const avg = this.getAveragedFeatures();
    const dVolume = current.volume - avg.volume;
    const dCentroid = current.centroid - avg.centroid;
    
    const visemeScores = this.computeVisemeScores(current, avg, dVolume, dCentroid);
    const adjustedScores = this.adjustScoresForConsistency(visemeScores);
    
    let maxScore = -Infinity;
    let topViseme = VISEMES.sil;
    
    for (const viseme in adjustedScores) {
      if (adjustedScores[viseme] > maxScore) {
        maxScore = adjustedScores[viseme];
        topViseme = viseme;
      }
    }
    
    this.state = VISEMES_STATES[topViseme];
    this.viseme = topViseme;
  }
  
  processAudio() {
    this.features = this.extractFeatures();
    this.detectState();
  }
}

// Export for use in other scripts
window.Lipsync = Lipsync;
window.VISEMES = VISEMES;
window.FSMStates = FSMStates;
