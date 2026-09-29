

import Alpine from 'alpinejs';
import estimateBuilder from '../../modules/AutoServe/resources/js/estimate-builder';

window.Alpine = Alpine;

Alpine.data('estimateBuilder', estimateBuilder);

Alpine.start();
