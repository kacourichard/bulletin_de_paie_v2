import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

const FRANCAIS = {
    emptyTable: 'Aucune donnée',
    info: 'Lignes _START_ à _END_ sur _TOTAL_',
    infoEmpty: 'Aucune ligne',
    infoFiltered: '(filtrées parmi _MAX_)',
    lengthMenu: '_MENU_ lignes par page',
    loadingRecords: 'Chargement…',
    processing: 'Traitement…',
    search: 'Rechercher :',
    zeroRecords: 'Aucune ligne ne correspond à la recherche',
    thousands: ' ',
    decimal: ',',
    paginate: { first: '«', last: '»', next: '›', previous: '‹' },
    aria: { orderable: 'Trier par cette colonne', orderableReverse: 'Inverser le tri' },
};

/*
 * Tableau interactif (recherche, tri, pagination) pour les listes volumineuses :
 *   <table data-controller="datatable" data-datatable-longueur-value="25" data-datatable-recherche-value="…">
 *
 * - th[data-dt-tri="non"], ou en-tête vide : colonne ni triable ni recherchée (boutons, actions) ;
 * - td[data-order="…"] : valeur de tri de la cellule (dates AAAA-MM-JJ, montants bruts) ;
 * - lignes de détail <tr class="paie-detail" id="…"> placées juste après leur ligne : elles deviennent
 *   des lignes enfants, ouvertes / fermées par [data-detail-toggle="id"] et par « Tout déplier / replier » ;
 * - tableau vide (une seule ligne en colspan) : laissé tel quel avec son message.
 */
export default class extends Controller {
    static values = { longueur: { type: Number, default: 25 }, recherche: String };

    connect() {
        // DataTables déplace le tableau dans son conteneur : Stimulus le reconnecte, il est déjà prêt.
        if (this.table) return;
        const corps = this.element.tBodies[0];
        const lignes = corps ? [...corps.rows] : [];
        if (lignes.length === 0 || lignes.every((tr) => tr.cells.length === 1 && tr.cells[0].colSpan > 1)) {
            return;
        }

        // Lignes de détail : sorties du tableau et rattachées à la ligne qui les précède.
        this.details = new Map();
        lignes.forEach((tr) => {
            if (tr.classList.contains('paie-detail') && tr.id) {
                this.details.set(tr.id, { tr, principal: tr.previousElementSibling, ouvert: !tr.hidden });
                tr.hidden = false;
                tr.remove();
            }
        });

        const entetes = [...this.element.tHead.rows].at(-1).cells;
        const nonTriables = [...entetes]
            .map((th, i) => (th.dataset.dtTri === 'non' || th.textContent.trim() === '' ? i : null))
            .filter((i) => i !== null);

        this.table = new DataTable(this.element, {
            language: FRANCAIS,
            pageLength: this.longueurValue,
            lengthMenu: [10, 25, 50, 100, { label: 'Tous', value: -1 }],
            order: [],
            search: { search: this.rechercheValue },
            autoWidth: false,
            columnDefs: [{ targets: nonTriables, orderable: false, searchable: false }],
        });

        this.details.forEach((detail, id) => detail.ouvert && this.basculer(id, true));

        this.surClic = (e) => {
            const bouton = e.target.closest('[data-detail-toggle]');
            if (!bouton || !this.details.has(bouton.dataset.detailToggle)) return;
            e.stopPropagation();
            this.basculer(bouton.dataset.detailToggle, bouton.getAttribute('aria-expanded') !== 'true');
        };
        this.surTous = (e) => this.details.forEach((_, id) => this.basculer(id, e.detail.ouvrir));
        this.element.addEventListener('click', this.surClic);
        document.addEventListener('details:tous', this.surTous);
    }

    disconnect() {
        // Simple déplacement par DataTables (toujours dans la page) : on ne détruit rien, sinon boucle sans fin.
        if (!this.table || this.element.isConnected) return;
        this.element.removeEventListener('click', this.surClic);
        document.removeEventListener('details:tous', this.surTous);
        this.table.destroy();
        this.table = null;
    }

    basculer(id, ouvrir) {
        const detail = this.details.get(id);
        const ligne = this.table.row(detail.principal);
        if (ouvrir) {
            ligne.child(detail.tr).show();
        } else {
            ligne.child.hide();
        }
        detail.ouvert = ouvrir;
        detail.principal.querySelectorAll('[data-detail-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(ouvrir)));
    }
}
