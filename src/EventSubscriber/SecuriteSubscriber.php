<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\SocieteCourante;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * - Enregistre la date de dernière connexion et réinitialise la société courante.
 * - Oblige à changer un mot de passe provisoire avant d'utiliser l'application.
 */
class SecuriteSubscriber implements EventSubscriberInterface
{
    private const ROUTES_LIBRES = ['app_compte_mot_de_passe', 'app_logout'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly SocieteCourante $societeCourante,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::REQUEST => ['onRequest', 0],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setDerniereConnexion(new \DateTimeImmutable());
        $this->em->flush();
        $this->societeCourante->oublier();
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isDoitChangerMotDePasse()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (null === $route || str_starts_with($route, '_') || in_array($route, self::ROUTES_LIBRES, true)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_compte_mot_de_passe')));
    }
}
