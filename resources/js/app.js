import Alpine from 'alpinejs';
import { initReveal, initReadingProgress } from './reveal';

window.Alpine = Alpine;
Alpine.start();

initReveal();
initReadingProgress();
