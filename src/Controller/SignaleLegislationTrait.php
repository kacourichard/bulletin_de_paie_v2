<?php

namespace App\Controller;

use App\Entity\Societe;

/** Message affiché après un changement de législation de paie d'une société. */
trait SignaleLegislationTrait
{
    /** @param list<string> $nonRepris avantages sans rubrique équivalente dans le nouveau pays */
    private function signalerLegislation(Societe $societe, array $nonRepris): void
    {
        $this->addFlash('info', sprintf('La paie de %s suit maintenant les paramètres de %s. Les avantages des salariés ont été rattachés aux rubriques de même code.', $societe->getRaisonSociale(), $societe->getPaysPaie()->getNom()));
        if ($nonRepris) {
            $this->addFlash('warning', sprintf('Sans rubrique équivalente en %s, à reprendre sur la fiche du salarié : %s.', $societe->getPaysPaie()->getNom(), implode(' ; ', $nonRepris)));
        }
    }
}
