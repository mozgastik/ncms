<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
enum ButtonVariant: string
{
    case Default = 'default';
    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';

    public function asCssClass(): string
    {
        // the 'default' variant renders Bootstrap's secondary button style
        return self::Default === $this ? 'btn-secondary' : 'btn-'.$this->value;
    }
}
