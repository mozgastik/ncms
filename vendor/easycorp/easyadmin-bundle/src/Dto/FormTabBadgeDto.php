<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Dto;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class FormTabBadgeDto
{
    // these are the names of the predefined styles used by Bootstrap 5
    private const PREDEFINED_STYLES = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    /**
     * @param array<string, mixed> $htmlAttributes
     */
    public function __construct(private readonly mixed $content, private readonly string $style, private readonly array $htmlAttributes = [])
    {
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function getCssClass(): string
    {
        return \in_array($this->style, self::PREDEFINED_STYLES, true) ? 'badge-'.$this->style : '';
    }

    public function getHtmlStyle(): string
    {
        return \in_array($this->style, self::PREDEFINED_STYLES, true) ? '' : $this->style;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHtmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    /**
     * Tells if the badge HTML element should be displayed or not. This is used
     * e.g. to not display a badge when its value is null or false.
     */
    public function hasVisibleContent(): bool
    {
        // the number 0 is not included here because it's valid to display a badge with the value 0
        return null !== $this->content && false !== $this->content && '' !== $this->content;
    }
}
