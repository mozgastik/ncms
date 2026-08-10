<?php
// src/EventListener/MaintenanceListener.php

namespace App\EventListener;

use App\Service\MaintenanceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class MaintenanceListener implements EventSubscriberInterface
{
    public function __construct(
        private MaintenanceService $maintenanceService,
        private Environment $twig
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Пропускаємо статичні файли та адмінку
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/_') || 
            str_starts_with($path, '/css') || 
            str_starts_with($path, '/js') || 
            str_starts_with($path, '/images') ||
            str_starts_with($path, '/build') ||
            str_starts_with($path, '/admin')) {
            return;
        }

        if ($this->maintenanceService->shouldShowMaintenance($request)) {
            $settings = $this->maintenanceService->getSettings();
            
            try {
                $content = $this->twig->render('components/maintenance.html.twig', [
                    'settings' => $settings,
                    'remainingTime' => $settings->getRemainingTime(),
                ]);

                $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
                $response->headers->set('Retry-After', '3600');
                $event->setResponse($response);
            } catch (\Exception $e) {
                $content = $this->getFallbackPage($settings);
                $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
                $event->setResponse($response);
            }
        }
    }

    private function getFallbackPage($settings): string
    {
        $title = htmlspecialchars($settings->getTitle());
        $message = htmlspecialchars($settings->getMessage());
        $bgColor = $settings->getBackgroundColor();
        $textColor = $settings->getTextColor();
        $accentColor = $settings->getAccentColor();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, sans-serif;
            background: {$bgColor};
            color: {$textColor};
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 2rem;
        }
        h1 { color: {$accentColor}; font-size: 2rem; margin-bottom: 1rem; }
        p { opacity: 0.8; line-height: 1.6; max-width: 500px; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div>
        <div class="icon">🔧</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
    </div>
</body>
</html>
HTML;
    }
}