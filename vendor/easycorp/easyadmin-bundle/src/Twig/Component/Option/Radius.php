<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option;

/**
 * Shared border-radius token for EasyAdmin components, modeled on the shadcn/ui radius
 * scale. Components expose a "radius" option using this enum; when none is set, they
 * inherit the project's default border-radius. The matching CSS utility classes are
 * defined in assets/css/easyadmin-theme/utilities.css.
 */
enum Radius: string
{
    case None = 'none';
    case Small = 'sm';
    case Medium = 'md';
    case Large = 'lg';
    case Full = 'full';

    public function cssClass(): string
    {
        return 'ea-rounded-'.$this->value;
    }
}
