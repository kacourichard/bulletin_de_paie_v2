<?php

namespace App\Controller\Plateforme;

use App\Entity\Pays;
use App\Entity\Salarie;
use App\Entity\ZoneTransport;
use App\Form\ZoneTransportType;
use App\Repository\RubriqueRepository;
use App\Repository\ZoneTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Zones de résidence d'un pays et indemnité de transport de chacune (table v1 prime_transport). */
#[Route('/plateforme/pays/{pays}/transport')]
class ZoneTransportController extends AbstractController
{
    #[Route('', name: 'app_plateforme_zones_transport')]
    public function index(#[MapEntity(id: 'pays')] Pays $pays, ZoneTransportRepository $zones, EntityManagerInterface $em): Response
    {
        $salaries = [];
        foreach ($em->getRepository(Salarie::class)->createQueryBuilder('s')->select('IDENTITY(s.zoneTransport) AS z, COUNT(s.id) AS n')
            ->where('s.zoneTransport IS NOT NULL')->groupBy('s.zoneTransport')->getQuery()->getArrayResult() as $l) {
            $salaries[(int) $l['z']] = (int) $l['n'];
        }

        return $this->render('plateforme/zone_transport/index.html.twig', ['pays' => $pays, 'zones' => $zones->duPays($pays), 'salaries' => $salaries]);
    }

    #[Route('/nouvelle', name: 'app_plateforme_zone_transport_nouvelle')]
    public function nouvelle(#[MapEntity(id: 'pays')] Pays $pays, Request $request, EntityManagerInterface $em, RubriqueRepository $rubriques): Response
    {
        $zone = (new ZoneTransport())->setPays($pays)
            ->setRubrique($rubriques->findOneBy(['pays' => $pays, 'code' => '645']));

        return $this->formulaire($request, $em, $pays, $zone);
    }

    #[Route('/{id}/modifier', name: 'app_plateforme_zone_transport_modifier')]
    public function modifier(#[MapEntity(id: 'pays')] Pays $pays, ZoneTransport $zone, Request $request, EntityManagerInterface $em): Response
    {
        if ($zone->getPays() !== $pays) {
            throw $this->createNotFoundException();
        }

        return $this->formulaire($request, $em, $pays, $zone);
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Pays $pays, ZoneTransport $zone): Response
    {
        $form = $this->createForm(ZoneTransportType::class, $zone);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($zone);
            $em->flush();
            $this->addFlash('success', sprintf('Zone « %s » enregistrée : l\'indemnité s\'applique dès les bulletins non validés.', $zone->getLibelle()));

            return $this->redirectToRoute('app_plateforme_zones_transport', ['pays' => $pays->getId()]);
        }

        return $this->render('plateforme/zone_transport/form.html.twig', ['form' => $form, 'pays' => $pays, 'zone' => $zone]);
    }
}
