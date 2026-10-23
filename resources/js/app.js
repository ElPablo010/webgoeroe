import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { initReveal, initReadingProgress } from './reveal';
import { initAnchors } from './anchors';

Alpine.plugin(collapse);

window.Alpine = Alpine;
Alpine.start();

initReveal();
initReadingProgress();
initAnchors();
