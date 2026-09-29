

import Alpine from 'alpinejs';
import estimateBuilder from '../../modules/AutoServe/resources/js/estimate-builder';
import loanSimulator from '../../modules/Finance/resources/js/loan-simulator';

window.Alpine = Alpine;

Alpine.data('estimateBuilder', estimateBuilder);
Alpine.data('loanSimulator', loanSimulator);

Alpine.start();
