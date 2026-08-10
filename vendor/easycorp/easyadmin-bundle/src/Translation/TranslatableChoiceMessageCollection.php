<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Translation;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Jakub Caban <kuba.iluvatar@gmail.com>
 *
 * @internal
 *
 * @implements \IteratorAggregate<int, TranslatableChoiceMessage>
 */
final readonly class TranslatableChoiceMessageCollection implements \Stringable, \IteratorAggregate, TranslatableInterface
{
    public function __construct(
        /** @var TranslatableChoiceMessage[] */
        private array $choices,
        private bool $isRenderedAsBadge,
    ) {
    }

    public function isRenderedAsBadge(): bool
    {
        return $this->isRenderedAsBadge;
    }

    /**
     * @return \Traversable<int, TranslatableChoiceMessage>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->choices);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return implode(
            ', ',
            array_map(
                static fn (TranslatableChoiceMessage $message) => $message->trans($translator, $locale),
                $this->choices
            )
        );
    }

    public function __toString(): string
    {
        return implode(', ', $this->choices);
    }
}
