import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';

/*
 * Liste déroulante où l'on peut taper pour filtrer (rubriques, salariés…) :
 *   <select data-controller="liste-recherche">
 * Ajouté automatiquement à toutes les listes déroulantes de l'application (voir app.js) ;
 * <select data-recherche="non"> pour s'en passer.
 * On tape un code (« 0063 ») ou une partie du libellé (« fin d'année ») ; les groupes (optgroup) sont conservés.
 * Le select d'origine reste dans le formulaire et reçoit l'événement « change » (envoi d'un filtre, traitement fiscal…).
 */
export default class extends Controller {
    connect() {
        if (this.element.tomselect) return;
        const vide = this.element.querySelector('option[value=""]');
        // Champ facultatif (filtre « Toutes les succursales »…) : l'option vide reste sélectionnable.
        const videChoisissable = Boolean(vide) && !this.element.required;
        const style = this.element.getAttribute('style');
        this.select = new TomSelect(this.element, {
            allowEmptyOption: videChoisissable,
            placeholder: vide ? vide.textContent.trim() : 'Taper pour rechercher…',
            maxOptions: null,
            searchField: ['text'],
            plugins: this.element.multiple ? ['remove_button'] : [],
            render: {
                no_results: () => '<div class="no-results">Aucun résultat</div>',
            },
            onInitialize() {
                // Pas de valeur affichée tant que rien n'est choisi : le texte d'aide sert d'invite.
                if (!videChoisissable && '' === this.getValue()) this.clear(true);
            },
        });
        // Largeur prévue sur le select (max-width, min-width…) reportée sur la liste.
        if (style) this.select.wrapper.style.cssText += ';' + style;
    }

    disconnect() {
        // Le select n'est pas déplacé par Tom Select : on ne détruit que s'il quitte vraiment la page.
        if (this.select && !this.element.isConnected) {
            this.select.destroy();
            this.select = null;
        }
    }
}
