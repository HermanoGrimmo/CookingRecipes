<?php

declare(strict_types=1);

namespace App\Validator;

use App\Import\RecipeImportService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Prüft, ob für die eingegebene URL ein Importer registriert ist.
 *
 * Fängt Tippfehler und nicht unterstützte Seiten ab, bevor überhaupt ein
 * HTTP-Request rausgeht.
 */
final class SupportedRecipeSourceValidator extends ConstraintValidator
{
    public function __construct(private readonly RecipeImportService $importService)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SupportedRecipeSource) {
            throw new UnexpectedTypeException($constraint, SupportedRecipeSource::class);
        }

        // Leere Werte prüft NotBlank – hier nicht doppelt melden.
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ($this->importService->supports($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ sources }}', implode(', ', $this->importService->getSupportedSourceNames()))
            ->addViolation();
    }
}
