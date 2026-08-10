// assets/app.js
import './styles/app.css';

// 1. Імпортуємо стилі Quill (вони потрібні обов'язково)
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import 'emoji-picker-element';
window.Quill = Quill;

// 2. Імпортуємо Alpine.js
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

window.Alpine = Alpine;
Alpine.plugin(persist);
Alpine.start();


console.log('Webpack Encore з Alpine.js та Symfony UX Quill працює!');