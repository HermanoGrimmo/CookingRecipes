<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller für die Benutzerregistrierung.
 */
class RegistrationController extends AbstractController
{
    #[Route('/registrieren', name: 'security_register')]
    public function register(Request $request, RegistrationService $registrationService): Response
    {
        // Bereits eingeloggte Benutzer zur Startseite weiterleiten
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('recipe_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $registrationService->registerUser($user, $plainPassword);

            $this->addFlash('success', 'Konto erfolgreich erstellt! Du kannst dich jetzt anmelden.');

            return $this->redirectToRoute('security_login');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
