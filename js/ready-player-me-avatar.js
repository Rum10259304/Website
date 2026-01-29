// Ready Player Me Avatar Integration for VerzTec
// This file handles the specific requirements for Ready Player Me avatars

class ReadyPlayerMeAvatar {
  constructor(avatarManager) {
    this.avatarManager = avatarManager;
    this.morphTargetMappings = this.createMorphTargetMappings();
    this.expressionController = null;
    this.blinkController = null;
    this.idleAnimations = [];
  }
  
  createMorphTargetMappings() {
    // Comprehensive mapping for Ready Player Me morph targets to visemes
    return {
      'viseme_sil': {
        targets: ['mouthClose', 'mouthNeutral', 'neutral'],
        weight: 1.0
      },
      'viseme_PP': {
        targets: ['mouthPucker', 'mouthFunnel', 'mouthKiss'],
        weight: 0.9
      },
      'viseme_FF': {
        targets: ['mouthShrugLower', 'mouthLowerDown'],
        weight: 0.8
      },
      'viseme_TH': {
        targets: ['mouthShrugLower', 'mouthLowerDown'],
        weight: 0.7
      },
      'viseme_DD': {
        targets: ['mouthOpen', 'jawOpen', 'mouthAh'],
        weight: 0.8
      },
      'viseme_kk': {
        targets: ['mouthOpen', 'jawOpen'],
        weight: 0.6
      },
      'viseme_CH': {
        targets: ['mouthShrugUpper', 'mouthUpperUp'],
        weight: 0.7
      },
      'viseme_SS': {
        targets: ['mouthFrown', 'mouthSad'],
        weight: 0.8
      },
      'viseme_nn': {
        targets: ['mouthClose', 'mouthPress'],
        weight: 0.9
      },
      'viseme_RR': {
        targets: ['mouthPucker', 'mouthRoll'],
        weight: 0.8
      },
      'viseme_aa': {
        targets: ['mouthOpen', 'jawOpen', 'mouthAh'],
        weight: 1.0
      },
      'viseme_E': {
        targets: ['mouthSmile', 'mouthHappy', 'mouthEh'],
        weight: 0.9
      },
      'viseme_I': {
        targets: ['mouthSmile', 'mouthEe'],
        weight: 0.8
      },
      'viseme_O': {
        targets: ['mouthFunnel', 'mouthPucker', 'mouthOh'],
        weight: 0.9
      },
      'viseme_U': {
        targets: ['mouthPucker', 'mouthFunnel', 'mouthOoh'],
        weight: 0.9
      }
    };
  }
  
  findMorphTargets(avatar) {
    const foundTargets = {};
    
    avatar.traverse((child) => {
      if (child.isMesh && child.morphTargetDictionary) {
        console.log('Found mesh with morph targets:', child.name, Object.keys(child.morphTargetDictionary));
        
        // Log all available morph targets for debugging
        const allTargets = Object.keys(child.morphTargetDictionary);
        console.log('Available morph targets:', allTargets);
        
        // Look for viseme mappings with more flexible matching
        for (const [viseme, mapping] of Object.entries(this.morphTargetMappings)) {
          for (const targetName of mapping.targets) {
            const variations = [
              targetName,
              targetName.toLowerCase(),
              targetName.charAt(0).toUpperCase() + targetName.slice(1),
              targetName.charAt(0).toLowerCase() + targetName.slice(1),
              `blend_${targetName}`,
              `ARKit_${targetName}`,
              `${targetName}_L`,
              `${targetName}_R`,
              targetName.replace(/([A-Z])/g, '_$1').toLowerCase(),
              targetName.replace(/_/g, ''),
              // Ready Player Me specific variations
              `Wolf3D_${targetName}`,
              targetName.replace('mouth', 'Mouth'),
              targetName.replace('eye', 'Eye'),
              targetName.replace('jaw', 'Jaw')
            ];
            
            for (const variation of variations) {
              if (child.morphTargetDictionary[variation] !== undefined) {
                foundTargets[viseme] = {
                  mesh: child,
                  index: child.morphTargetDictionary[variation],
                  weight: mapping.weight,
                  targetName: variation
                };
                console.log(`✓ Mapped ${viseme} to ${variation} (index: ${child.morphTargetDictionary[variation]})`);
                break;
              }
            }
            
            if (foundTargets[viseme]) break;
          }
          
          // If no exact match found, try partial matching
          if (!foundTargets[viseme]) {
            const partialMatches = allTargets.filter(target => 
              mapping.targets.some(mappingTarget => 
                target.toLowerCase().includes(mappingTarget.toLowerCase()) ||
                mappingTarget.toLowerCase().includes(target.toLowerCase())
              )
            );
            
            if (partialMatches.length > 0) {
              const bestMatch = partialMatches[0];
              foundTargets[viseme] = {
                mesh: child,
                index: child.morphTargetDictionary[bestMatch],
                weight: mapping.weight * 0.8, // Reduce weight for partial matches
                targetName: bestMatch
              };
              console.log(`~ Partial match for ${viseme}: ${bestMatch}`);
            }
          }
        }
        
        // Look for eye blink controls with extended search
        const eyeBlinkTargets = [
          'eyeBlinkLeft', 'eyeBlinkRight', 'blink_L', 'blink_R',
          'EyeBlinkLeft', 'EyeBlinkRight', 'BlinkLeft', 'BlinkRight',
          'Wolf3D_Eye_Blink_Left', 'Wolf3D_Eye_Blink_Right',
          'eyeBlink_L', 'eyeBlink_R', 'eye_blink_left', 'eye_blink_right'
        ];
        
        for (const eyeTarget of eyeBlinkTargets) {
          if (child.morphTargetDictionary[eyeTarget] !== undefined) {
            foundTargets[eyeTarget] = {
              mesh: child,
              index: child.morphTargetDictionary[eyeTarget],
              weight: 1.0,
              targetName: eyeTarget
            };
            console.log(`✓ Found eye control: ${eyeTarget}`);
          }
        }
        
        // Look for additional expression controls
        const expressionTargets = [
          'mouthSmile', 'mouthFrown', 'eyeSquintLeft', 'eyeSquintRight',
          'browDownLeft', 'browDownRight', 'browInnerUp', 'cheekPuff',
          'MouthSmile', 'MouthFrown', 'EyeSquintLeft', 'EyeSquintRight'
        ];
        
        for (const exprTarget of expressionTargets) {
          if (child.morphTargetDictionary[exprTarget] !== undefined) {
            foundTargets[exprTarget] = {
              mesh: child,
              index: child.morphTargetDictionary[exprTarget],
              weight: 0.5,
              targetName: exprTarget
            };
          }
        }
      }
    });
    
    console.log('Total morph targets mapped:', Object.keys(foundTargets).length);
    console.log('Mapped targets:', Object.keys(foundTargets));
    
    return foundTargets;
  }
  
  setupExpressionController(avatar, morphTargets) {
    this.morphTargets = morphTargets;
    this.setupBlinking();
    this.setupIdleExpressions();
    this.setupBreathingMouth();
  }
  
  setupBreathingMouth() {
    // Natural breathing mouth animation for idle and thinking states
    let breathingInterval;
    let currentBreathingCycle = 0;
    
    const breathe = () => {
      // Only apply breathing animation during idle or thinking states (not speaking)
      if (this.avatarManager && !this.avatarManager.isSpeaking) {
        // Get mouth targets for breathing animation
        const silTarget = this.morphTargets['viseme_sil'];
        const aaTarget = this.morphTargets['viseme_aa'];
        const jawTarget = this.morphTargets['jawOpen'] || this.morphTargets['JawOpen'];
        
        if (silTarget) {
          currentBreathingCycle++;
          
          // Create a natural breathing pattern with cycles
          const cyclePhase = currentBreathingCycle % 8;
          
          if (cyclePhase <= 2) {
            // Inhale phase: very subtle mouth opening
            const intensity = 0.03 + Math.sin(cyclePhase * Math.PI / 2) * 0.08; // 3-11%
            this.lerpMorphTarget(silTarget, intensity, 0.015);
            
            if (jawTarget) {
              this.lerpMorphTarget(jawTarget, intensity * 0.25, 0.015);
            }
          } else if (cyclePhase <= 4) {
            // Hold breath phase: maintain slight opening
            const intensity = 0.08 + Math.random() * 0.03; // 8-11%
            this.lerpMorphTarget(silTarget, intensity, 0.01);
            
            if (jawTarget) {
              this.lerpMorphTarget(jawTarget, intensity * 0.3, 0.01);
            }
          } else if (cyclePhase <= 6) {
            // Exhale phase: gradual closing
            const intensity = 0.08 - Math.sin((cyclePhase - 4) * Math.PI / 2) * 0.06; // 8% down to 2%
            this.lerpMorphTarget(silTarget, intensity, 0.02);
            
            if (jawTarget) {
              this.lerpMorphTarget(jawTarget, intensity * 0.2, 0.02);
            }
          } else {
            // Rest phase: nearly closed but not completely
            const intensity = 0.02 + Math.random() * 0.02; // 2-4%
            this.lerpMorphTarget(silTarget, intensity, 0.025);
            
            if (jawTarget) {
              this.lerpMorphTarget(jawTarget, intensity * 0.15, 0.025);
            }
          }
        }
      }
      
      // Schedule next breathing update - faster for smoother animation
      const nextBreath = 800 + Math.random() * 400; // 0.8-1.2 seconds
      breathingInterval = setTimeout(breathe, nextBreath);
    };
    
    // Start breathing animation after a delay
    setTimeout(breathe, 2000);
    
    this.breathingController = () => clearTimeout(breathingInterval);
  }
  
  setupBlinking() {
    let blinkInterval;
    
    const blink = () => {
      const leftEye = this.morphTargets['eyeBlinkLeft'] || this.morphTargets['blink_L'];
      const rightEye = this.morphTargets['eyeBlinkRight'] || this.morphTargets['blink_R'];
      
      if (leftEye && rightEye) {
        // Quick blink animation
        const duration = 150;
        const startTime = Date.now();
        
        const animateBlink = () => {
          const elapsed = Date.now() - startTime;
          const progress = Math.min(elapsed / duration, 1);
          const blinkValue = Math.sin(progress * Math.PI);
          
          leftEye.mesh.morphTargetInfluences[leftEye.index] = blinkValue;
          rightEye.mesh.morphTargetInfluences[rightEye.index] = blinkValue;
          
          if (progress < 1) {
            requestAnimationFrame(animateBlink);
          }
        };
        
        animateBlink();
      }
      
      // Schedule next blink
      const nextBlink = 2000 + Math.random() * 4000; // 2-6 seconds
      blinkInterval = setTimeout(blink, nextBlink);
    };
    
    // Start blinking
    setTimeout(blink, 2000);
    
    this.blinkController = () => clearTimeout(blinkInterval);
  }
  
  setupIdleExpressions() {
    // Add subtle idle expressions to make the avatar more lifelike
    const expressions = ['mouthSmile', 'eyeSquintLeft', 'eyeSquintRight'];
    let currentExpression = null;
    
    const cycleExpressions = () => {
      // Check if avatar is in thinking or speaking state and skip expression changes
      if (this.avatarManager && (this.avatarManager.isThinking || this.avatarManager.isSpeaking)) {
        // Don't change expressions during thinking or speaking
        setTimeout(cycleExpressions, 2000); // Check again in 2 seconds
        return;
      }
      
      if (currentExpression) {
        // Reset current expression
        const target = this.morphTargets[currentExpression];
        if (target) {
          this.lerpMorphTarget(target, 0, 0.02);
        }
      }
      
      // Randomly choose new expression or stay neutral (only during idle)
      if (Math.random() > 0.8) { // Less frequent expressions
        const availableExpressions = expressions.filter(expr => this.morphTargets[expr]);
        if (availableExpressions.length > 0) {
          currentExpression = availableExpressions[Math.floor(Math.random() * availableExpressions.length)];
          const target = this.morphTargets[currentExpression];
          this.lerpMorphTarget(target, 0.15, 0.008); // Reduced intensity and speed
        }
      } else {
        currentExpression = null;
      }
      
      setTimeout(cycleExpressions, 4000 + Math.random() * 6000); // Longer intervals
    };
    
    setTimeout(cycleExpressions, 8000); // Start later to avoid conflicts
  }
  
  updateLipsync(viseme, intensity = 0.8) {
    // Only reset viseme targets when actively speaking or transitioning
    if (this.avatarManager && this.avatarManager.isSpeaking) {
      // Reset all viseme targets to 0 first for clean transitions during speech
      for (const [key, target] of Object.entries(this.morphTargets)) {
        if (key.startsWith('viseme_')) {
          if (target && target.mesh && target.index !== undefined) {
            this.lerpMorphTarget(target, 0, 0.5);
          }
        }
      }
    }
    
    // Apply current viseme with smooth transition
    const target = this.morphTargets[viseme];
    if (target && target.mesh && target.index !== undefined) {
      const finalIntensity = Math.max(0, Math.min(1, intensity * target.weight));
      
      if (this.avatarManager && this.avatarManager.isSpeaking) {
        // Active speech - use full intensity
        this.lerpMorphTarget(target, finalIntensity, 0.6);
        
        // Add jaw movement for open mouth visemes during speech
        if (viseme === 'viseme_aa' || viseme === 'viseme_E' || viseme === 'viseme_O') {
          const jawTarget = this.morphTargets['jawOpen'] || this.morphTargets['JawOpen'];
          if (jawTarget && intensity > 0.3) {
            this.lerpMorphTarget(jawTarget, finalIntensity * 0.3, 0.6);
          }
        }
      } else {
        // Idle or thinking state - use gentler morphing that works with breathing
        if (viseme === 'viseme_sil') {
          // For silent viseme, don't force complete closure - let breathing animation handle it
          const breathingIntensity = Math.max(0.02, finalIntensity * 0.3); // Minimum slight opening
          this.lerpMorphTarget(target, breathingIntensity, 0.3);
        } else {
          // For other visemes in non-speaking states, apply very gently
          this.lerpMorphTarget(target, finalIntensity * 0.4, 0.3);
        }
      }
    }
    
    // Handle complete mouth reset only when explicitly transitioning to speaking
    if (viseme === 'viseme_sil' && this.avatarManager && this.avatarManager.isSpeaking) {
      // Reset jaw position for neutral state during speech
      const jawTarget = this.morphTargets['jawOpen'] || this.morphTargets['JawOpen'];
      if (jawTarget) {
        this.lerpMorphTarget(jawTarget, 0, 0.5);
      }
      
      // Reset other mouth morphs that might interfere during speech
      const neutralMorphs = ['mouthOpen', 'mouthAh', 'mouthSmile', 'mouthFrown'];
      for (const morphName of neutralMorphs) {
        const morphTarget = this.morphTargets[morphName];
        if (morphTarget) {
          this.lerpMorphTarget(morphTarget, 0, 0.5);
        }
      }
    }
  }
  
  lerpMorphTarget(target, value, speed) {
    if (!target || !target.mesh || target.index === undefined) return;
    
    const current = target.mesh.morphTargetInfluences[target.index] || 0;
    target.mesh.morphTargetInfluences[target.index] = 
      THREE.MathUtils.lerp(current, value, speed);
  }
  
  setExpression(expression, intensity = 1.0, duration = 1000) {
    const target = this.morphTargets[expression];
    if (!target) return;
    
    const startValue = target.mesh.morphTargetInfluences[target.index] || 0;
    const startTime = Date.now();
    
    const animate = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const easeProgress = 1 - Math.pow(1 - progress, 3); // Ease out cubic
      
      const currentValue = startValue + (intensity - startValue) * easeProgress;
      target.mesh.morphTargetInfluences[target.index] = currentValue;
      
      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };
    
    animate();
  }
  
  destroy() {
    if (this.blinkController) {
      this.blinkController();
    }
    if (this.breathingController) {
      this.breathingController();
    }
  }
}

// Export for use in avatar manager
window.ReadyPlayerMeAvatar = ReadyPlayerMeAvatar;
