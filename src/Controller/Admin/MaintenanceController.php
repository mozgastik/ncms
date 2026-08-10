<?php
// src/Controller/Admin/MaintenanceController.php

namespace App\Controller\Admin;

use App\Form\MaintenanceSettingsType;
use App\Service\MaintenanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/maintenance')]
#[IsGranted('ROLE_ADMIN')]
class MaintenanceController extends AbstractController
{
    #[Route('/', name: 'admin_maintenance_index', methods: ['GET', 'POST'])]
    public function index(Request $request, MaintenanceService $maintenanceService): Response
    {
        $settings = $maintenanceService->getSettings();
        
        $form = $this->createForm(MaintenanceSettingsType::class, $settings);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $request->request->all('maintenance_settings');
            if (isset($submittedData['enabled'])) {
                $settings->setEnabled((bool) $submittedData['enabled']);
            }
            
            $maintenanceService->updateSettings($settings);
            
            if ($settings->isEnabled()) {
                $this->addFlash('success', 'Режим обслуговування УВІМКНЕНО! Сайт недоступний для звичайних користувачів.');
            } else {
                $this->addFlash('success', 'Режим обслуговування ВИМКНЕНО! Сайт знову доступний.');
            }
            
            return $this->redirectToRoute('admin_maintenance_index');
        }
        
        $statistics = $maintenanceService->getStatistics();
        
        return $this->render('admin/maintenance/index.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings,
            'statistics' => $statistics,
            'remainingTime' => $settings->getRemainingTime(),
        ]);
    }
    
    #[Route('/toggle', name: 'admin_maintenance_toggle', methods: ['POST'])]
    public function toggle(Request $request, MaintenanceService $maintenanceService): JsonResponse|Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            $enabled = $data['enabled'] ?? null;
            
            if ($enabled === null) {
                return $this->json([
                    'success' => false,
                    'message' => 'Не вказано статус'
                ], 400);
            }
            
            try {
                $maintenanceService->setEnabled((bool) $enabled);
                
                return $this->json([
                    'success' => true,
                    'enabled' => (bool) $enabled,
                    'message' => $enabled ? 'Режим обслуговування увімкнено' : 'Режим обслуговування вимкнено'
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }
        
        $submittedToken = $request->request->get('_token');
        
        if (!$this->isCsrfTokenValid('maintenance_toggle', $submittedToken)) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_maintenance_index');
        }
        
        try {
            $isEnabled = $maintenanceService->toggle();
            
            if ($isEnabled) {
                $this->addFlash('success', 'Режим обслуговування УВІМКНЕНО!');
            } else {
                $this->addFlash('success', 'Режим обслуговування ВИМКНЕНО!');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Помилка: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('admin_maintenance_index');
    }
    
    #[Route('/save', name: 'admin_maintenance_save', methods: ['POST'])]
    public function save(Request $request, MaintenanceService $maintenanceService): JsonResponse|Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Не отримано даних'
                ], 400);
            }
            
            try {
                $settings = $maintenanceService->getSettings();
                
                // Оновлюємо налаштування з перетворенням дат
                if (isset($data['enabled'])) {
                    $settings->setEnabled((bool) $data['enabled']);
                }
                if (isset($data['allowAdminAccess'])) {
                    $settings->setAllowAdminAccess((bool) $data['allowAdminAccess']);
                }
                if (isset($data['title'])) {
                    $settings->setTitle($data['title']);
                }
                if (isset($data['message'])) {
                    $settings->setMessage($data['message']);
                }
                
                // ВАЖЛИВО: перетворюємо рядки в DateTimeImmutable
                if (isset($data['startAt'])) {
                    $settings->setStartAt($data['startAt'] ? new \DateTimeImmutable($data['startAt']) : null);
                }
                if (isset($data['endAt'])) {
                    $settings->setEndAt($data['endAt'] ? new \DateTimeImmutable($data['endAt']) : null);
                }
                
                if (isset($data['backgroundColor'])) {
                    $settings->setBackgroundColor($data['backgroundColor']);
                }
                if (isset($data['textColor'])) {
                    $settings->setTextColor($data['textColor']);
                }
                if (isset($data['accentColor'])) {
                    $settings->setAccentColor($data['accentColor']);
                }
                
                $maintenanceService->updateSettings($settings);
                
                return $this->json([
                    'success' => true,
                    'message' => 'Налаштування збережено',
                    'settings' => [
                        'enabled' => $settings->isEnabled(),
                        'allowAdminAccess' => $settings->isAllowAdminAccess(),
                        'title' => $settings->getTitle(),
                        'message' => $settings->getMessage(),
                        'startAt' => $settings->getStartAt()?->format('Y-m-d\TH:i'),
                        'endAt' => $settings->getEndAt()?->format('Y-m-d\TH:i'),
                        'backgroundColor' => $settings->getBackgroundColor(),
                        'textColor' => $settings->getTextColor(),
                        'accentColor' => $settings->getAccentColor(),
                    ]
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }
        
        return $this->redirectToRoute('admin_maintenance_index');
    }
    
    #[Route('/enable', name: 'admin_maintenance_enable', methods: ['POST'])]
    public function enable(Request $request, MaintenanceService $maintenanceService): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_enable', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_maintenance_index');
        }
        
        $maintenanceService->enable();
        $this->addFlash('success', 'Режим обслуговування увімкнено!');
        
        return $this->redirectToRoute('admin_maintenance_index');
    }
    
    #[Route('/disable', name: 'admin_maintenance_disable', methods: ['POST'])]
    public function disable(Request $request, MaintenanceService $maintenanceService): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_disable', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_maintenance_index');
        }
        
        $maintenanceService->disable();
        $this->addFlash('success', 'Режим обслуговування вимкнено!');
        
        return $this->redirectToRoute('admin_maintenance_index');
    }
    
    #[Route('/preview', name: 'admin_maintenance_preview', methods: ['GET'])]
public function preview(MaintenanceService $maintenanceService): Response
{
    $settings = $maintenanceService->getSettings();
    
    if (!$settings->isEnabled()) {
        $this->addFlash('warning', 'Режим обслуговування вимкнено. Увімкніть його, щоб побачити сторінку.');
        return $this->redirectToRoute('admin_maintenance_index');
    }
    
    // ============================================
    // РОЗРАХУНОК ЧАСУ ДЛЯ ШАБЛОНУ
    // ============================================
    
    $remaining = null;
    $endAt = $settings->getEndAt();
    
    if ($endAt) {
        $now = new \DateTimeImmutable();
        if ($endAt > $now) {
            $remaining = $endAt->getTimestamp() - $now->getTimestamp();
        }
    }
    
    return $this->render('components/maintenance.html.twig', [
        'settings' => $settings,
        'isPreview' => true,
        'remaining' => $remaining, // ← Передаємо час
        'currentTime' => (new \DateTime())->format('H:i'),
    ]);
}
 
}