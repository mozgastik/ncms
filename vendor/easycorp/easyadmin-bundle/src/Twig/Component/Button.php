<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component;

use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonElement;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonType;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonVariant;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\Size;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Renders a button as a <button>, <a> or <form> element, with optional
 * leading/trailing icons and a label.
 */
class Button
{
    public ?ButtonVariant $variant = ButtonVariant::Default;
    public bool $isInvisible = false;
    public bool $isBlock = false;
    public Size $size = Size::Medium;
    public ?string $icon = null;
    public bool $withTrailingIcon = false;
    public bool $inactive = false;
    public ButtonElement $htmlElement = ButtonElement::Button;
    public ?string $href = null;
    public ?string $action = null;
    public string $method = 'POST';
    public ButtonType $type = ButtonType::Submit;
    public ?string $name = null;
    public ?string $value = null;
    private ?string $customVariant = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * BC layer: transparently migrates the deprecated 'htmlAttributes' prop into
     * the native attributes system (remove in EasyAdmin 6.0).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function migrateHtmlAttributes(array $data): array
    {
        if (!\array_key_exists('htmlAttributes', $data)) {
            return $data;
        }

        trigger_deprecation('easycorp/easyadmin-bundle', '5.2.0', 'The "htmlAttributes" prop of the <twig:ea:Button> component is deprecated, pass the attributes directly on the component (or use the "{{ ... }}" spread syntax for dynamic maps) instead.');

        $htmlAttributes = $data['htmlAttributes'];
        unset($data['htmlAttributes']);

        // the old template silently ignored non-iterable values, keep that behavior
        if (!is_iterable($htmlAttributes)) {
            return $data;
        }

        foreach ($htmlAttributes as $attrName => $attrValue) {
            // attributes passed directly on the component win over the map ones
            if (\array_key_exists($attrName, $data)) {
                continue;
            }

            if ($attrValue instanceof TranslatableInterface) {
                $attrValue = $attrValue->trans($this->translator);
            }

            $data[$attrName] = $attrValue ?? '';
        }

        return $data;
    }

    public function mount(string|ButtonVariant|null $variant = null, string|ButtonElement|null $htmlElement = null, string|ButtonType|null $type = null, ?string $method = null, string|Size $size = Size::Medium): void
    {
        if ($variant instanceof ButtonVariant) {
            $this->variant = $variant;
        } elseif (null === $variant || '' === $variant) {
            $this->variant = ButtonVariant::Default;
        } else {
            $resolved = ButtonVariant::tryFrom($variant);
            $this->variant = $resolved;
            if (null === $resolved) {
                // unknown variants are passed through as a custom 'btn-{variant}' CSS class
                $this->customVariant = $variant;
            }
        }

        if ($htmlElement instanceof ButtonElement) {
            $this->htmlElement = $htmlElement;
        } else {
            $this->htmlElement = ButtonElement::tryFrom($htmlElement ?? '') ?? ButtonElement::Button;
        }

        if ($type instanceof ButtonType) {
            $this->type = $type;
        } else {
            $this->type = ButtonType::tryFrom($type ?? '') ?? ButtonType::Submit;
        }

        $this->method = null === $method || '' === $method ? 'POST' : strtoupper($method);

        // unknown sizes fall back to the base 'md' size, which emits no CSS class
        $this->size = \is_string($size) ? (Size::tryFrom($size) ?? Size::Medium) : $size;
    }

    public function getDefaultCssClass(): string
    {
        $cssClasses = ['btn'];

        if (null !== $this->variant) {
            $cssClasses[] = $this->variant->asCssClass();
        } elseif (null !== $this->customVariant) {
            $cssClasses[] = 'btn-'.$this->customVariant;
        }

        if (Size::Medium !== $this->size) {
            $cssClasses[] = 'btn-'.$this->size->value;
        }

        if ($this->isInvisible) {
            $cssClasses[] = 'btn-invisible';
        }

        if ($this->isBlock) {
            $cssClasses[] = 'btn-block';
        }

        if ($this->inactive) {
            $cssClasses[] = 'disabled';
        }

        return implode(' ', $cssClasses);
    }

    // HTML forms only support GET and POST methods; other methods (PUT, DELETE, etc.)
    // require submitting a POST form with the real method in a '_method' hidden field
    public function usesMethodOverride(): bool
    {
        return !\in_array($this->method, ['GET', 'POST'], true);
    }

    public function getFormMethod(): string
    {
        return $this->usesMethodOverride() ? 'POST' : $this->method;
    }
}
