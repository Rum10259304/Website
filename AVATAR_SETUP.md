# VerzTec AI Assistant Avatar Integration

This document explains how to set up and use the 3D avatar integration with lipsync and ElevenLabs voice synthesis in the VerzTec chatbot.

## Features

✅ **3D Avatar Integration**
- Ready Player Me avatar support
- Realistic facial animations
- Automatic lip-sync with speech
- Idle animations and blinking
- Interactive camera controls

✅ **Voice Synthesis**
- ElevenLabs API integration
- Multiple voice options (Adam, Rachel, Domi, Bella)
- Real-time speech generation
- Synchronized mouth movements

✅ **Lip Sync Technology**
- Real-time audio analysis using Rhubarb lipsync algorithm
- Advanced viseme detection and mapping
- Smooth morph target animations
- More accurate mouth movements than basic lipsync

## Setup Instructions

### 1. ElevenLabs API Setup

1. Sign up for an [ElevenLabs account](https://elevenlabs.io/)
2. Get your API key from the ElevenLabs dashboard
3. In the chatbot interface:
   - Click the ⚙️ **Settings** button in the top-right of the avatar area
   - Enter your ElevenLabs API key
   - Select your preferred voice
   - Click **Save**

### 2. Avatar Customization

#### Using Local Assets (Default)
The system comes with pre-configured local avatar and animation files:
- **Avatar Model**: `assets/avatars/models/64f1a714fe61576b46f27ca2.glb`
- **Animations Bundle**: `assets/avatars/models/animations.glb`
- **Individual Animations**: 
  - `assets/avatars/animations/Idle.fbx`
  - `assets/avatars/animations/Talking.fbx`
  - `assets/avatars/animations/Thinking.fbx`

The system automatically uses these local files and falls back to individual FBX files if the GLB animations don't load.

#### Using Custom Ready Player Me Avatar
1. Go to [Ready Player Me](https://readyplayer.me/)
2. Create your custom avatar
3. Copy the GLB model URL (ends with `.glb`)
4. In avatar settings, paste the URL in the "Avatar URL" field
5. Click **Save** (the page will reload with your new avatar)

#### Adding Your Own Local Avatar
1. Place your avatar GLB file in `assets/avatars/models/`
2. Place animation files in `assets/avatars/animations/` (supports both GLB and FBX)
3. Update the avatar URL in settings to point to your local file
4. Ensure animation files are named appropriately (Idle, Talking, Thinking)

### Recent Improvements

✅ **Enhanced Lipsync**: Switched to Rhubarb lipsync algorithm for more accurate mouth movements  
✅ **Hardcoded API Key**: ElevenLabs API key is now built-in for seamless operation  
✅ **Larger Avatar**: Avatar is now 50% bigger for better visibility  
✅ **Improved Controls**: Camera controls optimized for the larger avatar scale  

### 3. Voice Control

- **Toggle Voice**: Click the 🔊 **Voice On/Off** button to enable/disable speech
- **Status Updates**: The avatar status shows current activity (Ready, Speaking, Thinking, etc.)

## Avatar Features

### Facial Animations
- **Lip Sync**: Mouth movements synchronized with speech
- **Blinking**: Natural eye blinking every 2-6 seconds
- **Idle Expressions**: Subtle random expressions to appear lifelike
- **Smooth Transitions**: All animations use smooth interpolation

### Camera Controls
- **Mouse Drag**: Click and drag to orbit around the avatar
- **Mouse Wheel**: Scroll to zoom in/out
- **Auto-Focus**: Camera always looks at the avatar's face

### Audio Processing
- **Real-time Analysis**: Audio is processed for frequency content
- **Viseme Detection**: 15 different mouth shapes based on speech sounds
- **Volume Sensitivity**: Stronger sounds create more pronounced mouth movements

## File Structure

The avatar system uses the following file organization:

```
assets/
└── avatars/
    ├── models/
    │   ├── 64f1a714fe61576b46f27ca2.glb  # Main avatar model
    │   ├── animations.glb                  # Combined animations
    │   └── rpm.fbx                        # Alternative avatar format
    └── animations/
        ├── Idle.fbx                       # Idle animation
        ├── Talking.fbx                    # Talking animation
        └── Thinking.fbx                   # Thinking animation

js/
├── rhubarb-lipsync.js                     # Rhubarb-based audio analysis and viseme detection
├── avatar-manager.js                      # 3D scene and avatar control
└── ready-player-me-avatar.js             # Morph target mapping
```

## Technical Implementation

### Core Components

1. **Rhubarb Lipsync Engine** (`js/rhubarb-lipsync.js`)
   - Advanced audio frequency analysis
   - Rhubarb-style viseme detection algorithm
   - Real-time processing with better accuracy

2. **Avatar Manager** (`js/avatar-manager.js`)
   - Three.js scene management
   - Animation control
   - ElevenLabs integration (hardcoded API key)
   - Local asset loading with FBX fallback
   - Larger avatar scaling for better visibility

3. **Ready Player Me Integration** (`js/ready-player-me-avatar.js`)
   - Morph target mapping
   - Expression control
   - Facial animation system

### Supported Visemes
- **Silence**: `sil` - Mouth closed/neutral
- **Consonants**: `PP`, `FF`, `TH`, `DD`, `kk`, `CH`, `SS`, `nn`, `RR`
- **Vowels**: `aa`, `E`, `I`, `O`, `U`

### Performance Optimization
- Efficient audio processing (2048 FFT size)
- Smooth animation interpolation
- Automatic cleanup and memory management
- Background loading of 3D models

## Troubleshooting

### Common Issues

**Avatar not loading:**
- Check internet connection
- Verify Ready Player Me URL is valid
- Try refreshing the page

**No voice synthesis:**
- Ensure ElevenLabs API key is entered correctly
- Check API key has sufficient credits
- Verify voice selection is valid

**Poor lip sync:**
- Check microphone permissions if using live audio
- Ensure audio is playing through the correct output device
- Try different ElevenLabs voices

**Performance issues:**
- Close other browser tabs
- Ensure hardware acceleration is enabled
- Try reducing browser zoom level

### Browser Compatibility
- **Chrome**: ✅ Fully supported
- **Firefox**: ✅ Fully supported
- **Safari**: ⚠️ May have audio context issues
- **Edge**: ✅ Fully supported

### Required Browser Features
- WebGL support
- Web Audio API
- ES6+ JavaScript support
- CORS-enabled fetch API

## API Documentation

### AvatarManager Class

```javascript
// Initialize avatar
const avatarManager = new AvatarManager('container-id', {
  elevenlabsApiKey: 'your-api-key',
  voice: 'voice-id',
  avatarUrl: 'ready-player-me-url'
});

// Speak text
await avatarManager.speak('Hello, how can I help you?');

// Update settings
avatarManager.setElevenlabsApiKey('new-api-key');
```

### Customization Options

```javascript
const options = {
  elevenlabsApiKey: 'your-elevenlabs-api-key',
  voice: 'pNInz6obpgDQGcFmaJgB', // Voice ID
  avatarUrl: 'https://models.readyplayer.me/your-avatar.glb',
  // Animation settings
  idleIntensity: 0.2,
  blinkFrequency: 3000,
  lipsyncSensitivity: 0.8
};
```

## Credits

- **Three.js**: 3D graphics library
- **wawa-lipsync**: Lip sync technology by Wawa Sensei
- **Ready Player Me**: Avatar platform
- **ElevenLabs**: Voice synthesis API
- **VerzTec**: Implementation and integration

## Support

For technical support or questions about the avatar integration, please contact the VerzTec development team or refer to the main project documentation.
