<?php
// src/Controller/Api/WeatherController.php

namespace App\Controller\Api;

use App\Service\WeatherService;
use App\Service\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class WeatherController extends AbstractController
{
    #[Route('/api/weather/city', name: 'api_weather_city', methods: ['GET'])]
    public function getWeatherByCity(Request $request, WeatherService $weatherService, LoggerInterface $logger): JsonResponse
    {
        $city = $request->query->get('city', 'Kyiv');
        
        $logger->info('Weather API request for city: ' . $city);
        
        try {
            $data = $weatherService->getWeatherByCity($city);
            
            if ($data) {
                return $this->json([
                    'success' => true,
                    'data' => $data,
                    'message' => 'Погода отримана успішно'
                ]);
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Не вдалося отримати дані погоди для міста: ' . $city
            ], 404);
        } catch (\Exception $e) {
            $logger->error('Weather API error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/weather/coordinates', name: 'api_weather_coordinates', methods: ['GET'])]
    public function getWeatherByCoordinates(Request $request, WeatherService $weatherService, LoggerInterface $logger): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');

        if (!$lat || !$lon) {
            return $this->json([
                'success' => false,
                'message' => 'Не вказані координати (lat, lon)'
            ], 400);
        }

        try {
            $data = $weatherService->getWeatherByCoordinates((float)$lat, (float)$lon);
            
            if ($data) {
                return $this->json([
                    'success' => true,
                    'data' => $data,
                    'message' => 'Погода отримана успішно'
                ]);
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Не вдалося отримати дані погоди за координатами'
            ], 404);
        } catch (\Exception $e) {
            $logger->error('Weather API error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/weather/forecast', name: 'api_weather_forecast', methods: ['GET'])]
    public function getForecast(Request $request, WeatherService $weatherService, LoggerInterface $logger): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');

        if (!$lat || !$lon) {
            return $this->json([
                'success' => false,
                'message' => 'Не вказані координати (lat, lon)'
            ], 400);
        }

        try {
            $data = $weatherService->getWeatherByCoordinates((float)$lat, (float)$lon);
            
            if ($data && isset($data['forecast'])) {
                return $this->json([
                    'success' => true,
                    'data' => $data['forecast'],
                    'message' => 'Прогноз отримано успішно'
                ]);
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Не вдалося отримати прогноз'
            ], 404);
        } catch (\Exception $e) {
            $logger->error('Weather API error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }
}