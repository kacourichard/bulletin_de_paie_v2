<?php

namespace App\Service;

use App\Entity\Affectation;
use App\Entity\EnvoiBulletin;
use App\Entity\PeriodePaie;
use App\Entity\User;
use App\Repository\AffectationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

/**
 * Bulletins de paie : rendu HTML / PDF (un salarié ou toute la société) et envoi par e-mail.
 */
class BulletinPaie
{
    public function __construct(
        private readonly GestionPaie $paie,
        private readonly AffectationRepository $affectations,
        private readonly LogoSociete $logos,
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] private readonly string $racine,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $expediteur,
    ) {
    }

    /** @return list<array{affectation: Affectation, recap: array}> */
    public function donnees(PeriodePaie $periode, ?Affectation $seul = null): array
    {
        $liste = $seul ? [$seul] : $this->affectations->presentesSurPeriode($periode->getSociete(), $periode->getDebut(), $periode->getFin());

        return array_map(function (Affectation $a) use ($periode) {
            $recap = $this->paie->recapitulatif($a, $periode);

            // Définitif si le mois est validé, ou si le solde de tout compte du salarié est arrêté.
            return ['affectation' => $a, 'recap' => $recap, 'cumuls' => $this->paie->cumuls($a, $periode, $recap), 'provisoire' => !$periode->isCloturee() && null === $recap['soldeArrete']];
        }, $liste);
    }

    public function html(PeriodePaie $periode, array $bulletins, bool $ecran = false, ?string $lienPdf = null): string
    {
        return $this->twig->render('bulletin/document.html.twig', [
            'periode' => $periode,
            'bulletins' => $bulletins,
            'provisoire' => [] !== array_filter($bulletins, static fn (array $b) => $b['provisoire']),
            'ecran' => $ecran,
            'lienPdf' => $lienPdf,
            'logoSociete' => $this->dataUri($this->logos->cheminAbsolu($periode->getSociete())),
            'logoCipaie' => $this->dataUri($this->racine.'/assets/images/cipaie-logo.jpg'),
        ]);
    }

    public function pdf(PeriodePaie $periode, array $bulletins): string
    {
        $options = (new Options())->setDefaultFont('DejaVu Sans')->setIsRemoteEnabled(false)->setChroot($this->racine);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($periode, $bulletins));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function nomFichier(PeriodePaie $periode, ?Affectation $affectation = null): string
    {
        $slug = new AsciiSlugger();

        return $affectation
            ? sprintf('bulletin-%s-%s-%s.pdf', $periode->getCode(), $affectation->getMatricule(), $slug->slug($affectation->getSalarie()->getNomComplet())->lower())
            : sprintf('bulletins-%s-%s.pdf', $periode->getCode(), $slug->slug($periode->getSociete()->getRaisonSociale())->lower());
    }

    /**
     * Envoie le bulletin au salarié (PDF joint). L'envoi est historisé, réussi ou non.
     *
     * @throws \DomainException
     */
    public function envoyer(PeriodePaie $periode, Affectation $affectation, ?User $auteur): void
    {
        $donnees = $this->donnees($periode, $affectation);
        if ($donnees[0]['provisoire']) {
            throw new \DomainException('Validez la paie (ou arrêtez le solde de tout compte du salarié) avant d\'envoyer le bulletin.');
        }
        $email = $affectation->getSalarie()->getEmail();
        if (!$email) {
            throw new \DomainException(sprintf('%s n\'a pas d\'adresse e-mail.', $affectation->getSalarie()->getNomComplet()));
        }

        $societe = $periode->getSociete();
        $message = (new Email())
            ->from(new Address($this->expediteur, $societe->getRaisonSociale().' via Côte d\'Ivoire Paie'))
            ->to(new Address($email, $affectation->getSalarie()->getNomComplet()))
            ->subject(sprintf('Votre bulletin de paie%s — %s — %s', $donnees[0]['recap']['soldeToutCompte'] ? ' (solde de tout compte)' : '', $periode->getLibelle(), $societe->getRaisonSociale()))
            ->text(sprintf(
                "Bonjour %s,\n\nVeuillez trouver ci-joint votre bulletin de paie de %s%s.\n\nCordialement,\n%s\n\n—\nMessage envoyé automatiquement par la plateforme Côte d'Ivoire Paie. Merci de ne pas y répondre.",
                $affectation->getSalarie()->getPrenoms(),
                mb_strtolower($periode->getLibelle()),
                $donnees[0]['recap']['soldeToutCompte'] ? ' (solde de tout compte)' : '',
                $societe->getRaisonSociale(),
            ))
            ->attach($this->pdf($periode, $donnees), $this->nomFichier($periode, $affectation), 'application/pdf');
        if ($societe->getEmail()) {
            $message->replyTo($societe->getEmail());
        }

        try {
            $this->mailer->send($message);
            $this->em->persist(new EnvoiBulletin($periode, $affectation, $email, $auteur));
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->em->persist(new EnvoiBulletin($periode, $affectation, $email, $auteur, false, $e->getMessage()));
            $this->em->flush();
            throw new \DomainException(sprintf('Échec de l\'envoi à %s : %s', $email, $e->getMessage()));
        }
    }

    /** @return array<int, EnvoiBulletin> dernier envoi par affectation pour la période */
    public function derniersEnvois(PeriodePaie $periode): array
    {
        $envois = [];
        foreach ($this->em->getRepository(EnvoiBulletin::class)->findBy(['periode' => $periode], ['envoyeLe' => 'ASC']) as $envoi) {
            $envois[$envoi->getAffectation()->getId()] = $envoi;
        }

        return $envois;
    }

    private function dataUri(?string $chemin): ?string
    {
        if (!$chemin || !is_file($chemin)) {
            return null;
        }
        $type = mime_content_type($chemin) ?: 'image/png';

        return 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($chemin));
    }
}
