<?php

namespace App\EventSubscriber;

use App\Controller\AbstractSocieteController;
use App\Controller\DashboardController;
use App\Security\SocieteCourante;
use App\Security\SocieteRequiseException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redirige vers le choix de société quand une page de l'espace société
 * est demandée sans société courante (avant même le contrôle des permissions,
 * qui sinon répondrait « accès refusé »).
 */
class SocieteRequiseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SocieteCourante $societeCourante,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
            KernelEvents::EXCEPTION => ['onException', 10],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $instance = \is_array($controller) ? $controller[0] : $controller;

        // Le tableau de bord gère lui-même le cas (vue plateforme du super-admin).
        if (!$instance instanceof AbstractSocieteController || $instance instanceof DashboardController) {
            return;
        }

        if (null === $this->societeCourante->get()) {
            $event->setController(fn () => $this->redirection());
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->getThrowable() instanceof SocieteRequiseException) {
            $event->setResponse($this->redirection());
        }
    }

    private function redirection(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_societe_choisir'));
    }
}
