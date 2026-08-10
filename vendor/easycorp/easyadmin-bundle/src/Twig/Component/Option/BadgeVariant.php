<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option;

enum BadgeVariant: string
{
    // these match the badge styles defined in the EasyAdmin theme
    // see assets/css/easyadmin-theme/badges.css
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Light = 'light';
    case Dark = 'dark';
    case Outline = 'outline';

    public function asCssClass(): string
    {
        return 'badge-'.$this->value;
    }
}
