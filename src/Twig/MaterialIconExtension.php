<?php
// src/Twig/MaterialIconExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MaterialIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('material_icon', [$this, 'renderIcon'], ['is_safe' => ['html']]),
        ];
    }
    
    public function renderIcon(string $icon, string $size = '24px', array $options = []): string
    {
        $class = $options['class'] ?? '';
        $color = $options['color'] ?? '';
        $style = $options['style'] ?? '';
        
        if ($color) {
            $style .= " color: {$color};";
        }
        
        return sprintf(
            '<span class="material-icons %s" style="font-size: %s; %s">%s</span>',
            $class,
            $size,
            $style,
            $icon
        );
    }
}