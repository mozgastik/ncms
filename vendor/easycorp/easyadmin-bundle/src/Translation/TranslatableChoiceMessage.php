<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Translation;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Jakub Caban <kuba.iluvatar@gmail.com>
 *
 * @internal
 */
final readonly class TranslatableChoiceMessage implements \Stringable, TranslatableInterface
{
    public function __construct(
        /** @var TranslatableMessage $message */
        private TranslatableInterface $message,
        private ?string $variant = null,
    ) {
    }

    public function getMessage(): TranslatableInterface
    {
        return $this->message;
    }

    /**
     * The badge variant (e.g. 'secondary', 'warning') or null when not rendered as a badge.
     * The badge markup itself is rendered by the <twig:ea:Badge> component in the template.
     */
    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->message->trans($translator, $locale);
    }

    public function __toString(): string
    {
        return $this->message->getMessage();
    }
}
