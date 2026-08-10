<?php

namespace App\Controller\Admin;

use App\Entity\Admin\Setting;
use App\Form\SettingType;
use App\Repository\SettingRepository;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/setting')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager
    ) {}

    #[Route('/', name: 'admin_settings_index', methods: ['GET'])]
    public function index(SettingRepository $repository): Response
    {
        $groupedSettings = $repository->getGroupedSettings();
        $groups = $this->settingsManager->getGroups();

        return $this->render('admin/settings/index.html.twig', [
            'grouped_settings' => $groupedSettings,
            'groups' => $groups,
        ]);
    }

    #[Route('/edit/{id}', name: 'admin_settings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Setting $setting): Response
    {
        if ($setting->isReadonly()) {
            $this->addFlash('error', 'Це налаштування доступне тільки для читання');
            return $this->redirectToRoute('admin_settings_index');
        }

        $form = $this->createForm(SettingType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->settingsManager->clearCache();
            
            $this->addFlash('success', 'Налаштування успішно оновлено');
            return $this->redirectToRoute('admin_settings_index');
        }

        return $this->render('admin/settings/edit.html.twig', [
            'setting' => $setting,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/batch-update', name: 'admin_settings_batch_update', methods: ['POST'])]
    public function batchUpdate(Request $request): Response
    {
        $data = $request->request->all('settings');
        $this->settingsManager->setMultiple($data);
        
        $this->addFlash('success', 'Налаштування успішно оновлено');
        return $this->redirectToRoute('admin_settings_index');
    }

    #[Route('/create', name: 'admin_settings_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $setting = new Setting();
        $form = $this->createForm(SettingType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($setting);
            $this->entityManager->flush();
            $this->settingsManager->clearCache();
            
            $this->addFlash('success', 'Налаштування успішно створено');
            return $this->redirectToRoute('admin_settings_index');
        }

        return $this->render('admin/settings/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_settings_delete', methods: ['POST'])]
    public function delete(Request $request, Setting $setting): Response
    {
        if ($setting->isSystem()) {
            $this->addFlash('error', 'Системні налаштування не можна видаляти');
            return $this->redirectToRoute('admin_settings_index');
        }

        if ($this->isCsrfTokenValid('delete' . $setting->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
            $this->settingsManager->clearCache();
            
            $this->addFlash('success', 'Налаштування видалено');
        }

        return $this->redirectToRoute('admin_settings_index');
    }

    #[Route('/api/public', name: 'api_settings_public', methods: ['GET'])]
    public function publicApi(): JsonResponse
    {
        return $this->json($this->settingsManager->getPublic());
    }

    #[Route('/api/{key}', name: 'api_settings_get', methods: ['GET'])]
    public function getApi(string $key): JsonResponse
    {
        $setting = $this->settingsManager->getEntity($key);
        
        if (!$setting || !$setting->isPublic()) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json([
            'key' => $setting->getSettingKey(),
            'value' => $setting->getNormalizedValue(),
            'label' => $setting->getLabel(),
            'type' => $setting->getType(),
        ]);
    }

    #[Route('/clear-cache', name: 'admin_settings_clear_cache', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        $this->settingsManager->clearCache();
        
        if ($this->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'message' => 'Кеш очищено']);
        }
        
        $this->addFlash('success', 'Кеш налаштувань очищено');
        return $this->redirectToRoute('admin_settings_index');
    }

    private function isXmlHttpRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}