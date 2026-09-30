

import Alpine from 'alpinejs';
import estimateBuilder from '../../modules/AutoServe/resources/js/estimate-builder';
import loanSimulator from '../../modules/Finance/resources/js/loan-simulator';
import recipeBuilder from '../../modules/Resto/resources/js/recipe-builder';
import kitchenBoard from '../../modules/Resto/resources/js/kitchen-board';
import posCashier from '../../modules/Resto/resources/js/pos-cashier';

window.Alpine = Alpine;

Alpine.data('estimateBuilder', estimateBuilder);
Alpine.data('loanSimulator', loanSimulator);
Alpine.data('recipeBuilder', recipeBuilder);
Alpine.data('kitchenBoard', kitchenBoard);
Alpine.data('posCashier', posCashier);

Alpine.start();
