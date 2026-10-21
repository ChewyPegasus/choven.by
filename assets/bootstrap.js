import { startStimulusApp } from '@symfony/stimulus-bundle';
import './controllers/csrf_protection_controller.js';

// Controllers in assets/controllers/ are registered automatically (lazy ones are loaded on first use).
startStimulusApp();
