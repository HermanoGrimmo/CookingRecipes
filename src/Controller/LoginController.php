<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller für Login und Logout.
 * Das eigentliche Authentifizierungs-Handling übernimmt die Symfony Security-Komponente.
 */
class LoginController extends AbstractController
{
    #[Route('/anmelden', name: 'security_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Bereits eingeloggte Benutzer zur Startseite weiterleiten
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('recipe_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Logout wird von Symfony automatisch abgefangen.
     * Diese Methode wird nie ausgeführt.
     */
    #[Route('/abmelden', name: 'security_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Diese Methode wird von der Symfony Security Firewall abgefangen.');
    }
}
