<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

/**
 * Controller für den Passwort-Reset-Prozess.
 * Verwendet den ResetPasswordControllerTrait des symfonycasts/reset-password-bundle.
 */
#[Route('/passwort')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Zeigt das Formular zur Eingabe der E-Mail-Adresse und sendet bei Erfolg den Reset-Link.
     */
    #[Route('/vergessen', name: 'app_forgot_password_request')]
    public function request(Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneByEmail($email);

            if (null !== $user) {
                try {
                    $resetToken = $this->passwordResetService->generateResetToken($user);

                    // Reset-URL mit absolutem Pfad generieren
                    $resetUrl = $this->generateUrl(
                        'app_reset_password_token',
                        ['token' => $resetToken->getToken()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );

                    $this->passwordResetService->sendPasswordResetEmail($user, $resetUrl, $resetToken);
                } catch (ResetPasswordExceptionInterface) {
                    // Rate-Limit überschritten – trotzdem weiterleiten, um keine
                    // Informationen über registrierte E-Mail-Adressen preiszugeben.
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Informationsseite nach dem Absenden der Reset-Anfrage.
     */
    #[Route('/vergessen/check', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    /**
     * Nimmt den Token aus der URL entgegen, speichert ihn sicher in der Session
     * und leitet auf die Token-freie URL weiter (verhindert Token im Browser-Verlauf).
     */
    #[Route('/zuruecksetzen/{token}', name: 'app_reset_password_token')]
    public function reset(string $token): Response
    {
        $this->storeTokenInSession($token);

        return $this->redirectToRoute('app_reset_password');
    }

    /**
     * Verarbeitet das neue Passwort nach der Token-Validierung.
     */
    #[Route('/zuruecksetzen', name: 'app_reset_password')]
    public function resetPassword(Request $request): Response
    {
        $token = $this->getTokenFromSession();

        if (null === $token) {
            $this->addFlash('reset_password_error', 'Kein Reset-Token gefunden. Bitte starte den Prozess neu.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            /** @var User $user */
            $user = $this->passwordResetService->getResetPasswordHelper()->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', \sprintf(
                'Der Reset-Link ist ungültig oder abgelaufen: %s',
                $e->getReason()
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Token verbrauchen und aus der Datenbank löschen
            $this->passwordResetService->getResetPasswordHelper()->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $this->passwordResetService->resetPassword($user, $plainPassword);

            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Passwort erfolgreich geändert. Du kannst dich jetzt anmelden.');

            return $this->redirectToRoute('security_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'form' => $form,
        ]);
    }
}
