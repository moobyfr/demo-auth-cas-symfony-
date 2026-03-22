<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class CasLogoutListener
{
    public function __construct(
        private readonly string $casLogoutUrl,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (null !== $event->getResponse()) {
            return;
        }

        $serviceUrl = $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $separator = str_contains($this->casLogoutUrl, '?') ? '&' : '?';

        $event->setResponse(new RedirectResponse($this->casLogoutUrl.$separator.'service='.urlencode($serviceUrl)));
    }
}
