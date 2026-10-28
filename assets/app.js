import './stimulus_bootstrap.js';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import './styles/app.css';
import * as bootstrap from 'bootstrap';

// Thème clair / sombre (le choix initial est appliqué dans <head> pour éviter un flash).
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('[data-theme-toggle]');
    if (!bouton) return;
    const theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', theme);
    try { localStorage.setItem('bp-theme', theme); } catch (_) { /* stockage indisponible */ }
});

// Menu latéral sur mobile.
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-toggle]') || e.target.closest('.app-backdrop')) {
        document.body.classList.toggle('sidebar-ouverte');
    }
});

// Copie dans le presse-papiers (mot de passe provisoire...).
document.addEventListener('click', async (e) => {
    const bouton = e.target.closest('[data-copy]');
    if (!bouton) return;
    try {
        await navigator.clipboard.writeText(bouton.dataset.copy);
        const icone = bouton.querySelector('i');
        icone?.classList.replace('bi-clipboard', 'bi-check2');
        setTimeout(() => icone?.classList.replace('bi-check2', 'bi-clipboard'), 1500);
    } catch (_) { /* presse-papiers indisponible */ }
});

// Confirmation avant une action sensible : <form data-confirm="Message ?">.
document.addEventListener('submit', (e) => {
    const message = e.target.dataset.confirm;
    if (message && !window.confirm(message)) {
        e.preventDefault();
    }
});

// Collections de formulaire (ex. avantages à l'embauche) :
// <div data-collection data-prototype="..." data-index="N"> + bouton [data-collection-add] + boutons [data-collection-remove].
document.addEventListener('click', (e) => {
    const ajout = e.target.closest('[data-collection-add]');
    if (ajout) {
        const conteneur = document.querySelector(ajout.dataset.collectionAdd);
        const index = Number(conteneur.dataset.index || 0);
        const gabarit = document.createElement('template');
        gabarit.innerHTML = conteneur.dataset.prototype.replace(/__name__/g, String(index)).trim();
        conteneur.appendChild(gabarit.content);
        conteneur.dataset.index = String(index + 1);
        conteneur.querySelector('[data-collection-vide]')?.remove();
        conteneur.lastElementChild?.querySelector('input:not([type=hidden]), select')?.focus();
        return;
    }
    const retrait = e.target.closest('[data-collection-remove]');
    if (retrait) {
        retrait.closest('[data-collection-item]')?.remove();
    }
});

// Aperçu immédiat d'une image choisie : <input type="file" data-apercu="#cible">.
document.addEventListener('change', (e) => {
    const champ = e.target.closest('input[type=file][data-apercu]');
    const fichier = champ?.files?.[0];
    const cible = champ && document.querySelector(champ.dataset.apercu);
    if (!fichier || !cible || !fichier.type.startsWith('image/')) return;
    const img = document.createElement('img');
    img.alt = 'Aperçu du logo';
    img.src = URL.createObjectURL(fichier);
    cible.replaceChildren(img);
});

// Traitement social / fiscal de la rubrique choisie : <select data-rubrique-info="#cible">.
const afficherRubrique = (select) => {
    const cible = document.querySelector(select.dataset.rubriqueInfo);
    const option = select.selectedOptions[0];
    if (!cible) return;
    cible.textContent = option?.dataset.fiscal
        ? `Cotisations sociales : ${option.dataset.social} · Impôt : ${option.dataset.fiscal}`
        : '';
};
document.addEventListener('change', (e) => {
    if (e.target.matches('select[data-rubrique-info]')) afficherRubrique(e.target);
});
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('select[data-rubrique-info]').forEach(afficherRubrique));

// Lignes de détail repliables : [data-detail-toggle="id"] ; tout déplier / replier : [data-details-tous="ouvrir|fermer"].
const basculerDetail = (bouton, ouvrir) => {
    const cible = bouton.dataset.detailToggle;
    const ligne = document.getElementById(cible);
    if (!ligne) return;
    ligne.hidden = !ouvrir;
    // Tous les boutons qui pilotent la même ligne (flèche, « Détails ») restent synchronisés.
    document.querySelectorAll(`[data-detail-toggle="${CSS.escape(cible)}"]`).forEach((b) => b.setAttribute('aria-expanded', String(ouvrir)));
};
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('[data-detail-toggle]');
    if (bouton) {
        basculerDetail(bouton, bouton.getAttribute('aria-expanded') !== 'true');
        return;
    }
    const tous = e.target.closest('[data-details-tous]');
    if (tous) {
        const ouvrir = tous.dataset.detailsTous === 'ouvrir';
        // Les tableaux DataTables gèrent leurs propres lignes de détail (toutes pages comprises).
        document.querySelectorAll('[data-detail-toggle]').forEach((b) => b.closest('.dt-container') || basculerDetail(b, ouvrir));
        document.dispatchEvent(new CustomEvent('details:tous', { detail: { ouvrir } }));
    }
});

// Onglets : ouvre celui désigné par l'ancre de l'URL (#remuneration...) et mémorise le choix dans l'URL.
document.addEventListener('shown.bs.tab', (e) => {
    const cible = e.target.dataset.bsTarget;
    if (cible?.startsWith('#')) history.replaceState(null, '', cible);
});

// Recherche instantanée dans des lignes regroupées : <input data-filtre-lignes="#cible"> filtre les [data-filtre-ligne]
// de la cible et masque les [data-filtre-groupe] devenus vides ([data-filtre-aucun] s'affiche si rien ne correspond).
const sansAccents = (texte) => texte.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
document.addEventListener('input', (e) => {
    const champ = e.target.closest('[data-filtre-lignes]');
    const cible = champ && document.querySelector(champ.dataset.filtreLignes);
    if (!cible) return;
    const recherche = sansAccents(champ.value.trim());
    cible.querySelectorAll('[data-filtre-ligne]').forEach((ligne) => { ligne.hidden = !sansAccents(ligne.textContent).includes(recherche); });
    let visibles = 0;
    cible.querySelectorAll('[data-filtre-groupe]').forEach((groupe) => {
        groupe.hidden = !groupe.querySelector('[data-filtre-ligne]:not([hidden])');
        visibles += groupe.hidden ? 0 : 1;
    });
    const aucun = cible.querySelector('[data-filtre-aucun]');
    if (aucun) aucun.hidden = visibles > 0;
});

// Toutes les listes déroulantes acceptent la saisie pour retrouver vite un élément (contrôleur liste-recherche),
// y compris celles ajoutées après coup (collections de formulaire). Exclues : listes DataTables et [data-recherche="non"].
const rendreSaisissables = (racine) => {
    const selects = racine.matches?.('select') ? [racine] : racine.querySelectorAll?.('select') ?? [];
    selects.forEach((select) => {
        if (select.dataset.recherche === 'non' || select.classList.contains('dt-input') || select.closest('.dt-container .dt-length')) return;
        const controleurs = (select.dataset.controller || '').split(/\s+/).filter(Boolean);
        if (!controleurs.includes('liste-recherche')) select.dataset.controller = [...controleurs, 'liste-recherche'].join(' ');
    });
};
new MutationObserver((mutations) => mutations.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && rendreSaisissables(n))))
    .observe(document.documentElement, { childList: true, subtree: true });

const init = () => {
    rendreSaisissables(document);
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
    if (location.hash) {
        const onglet = document.querySelector(`[data-bs-toggle="tab"][data-bs-target="${CSS.escape(location.hash)}"]`);
        if (onglet) bootstrap.Tab.getOrCreateInstance(onglet).show();
    }
};
document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
