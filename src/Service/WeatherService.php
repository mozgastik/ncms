<?php
// src/Service/WeatherService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class WeatherService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger
    ) {}

    public function getWeatherByCity(string $city): ?array
    {
        $provider = $this->settingsManager->getWeatherProvider();
        $apiKey = $this->settingsManager->getWeatherApiKey();
        
        if (!$apiKey) {
            $this->logger->error('Weather API key not configured in database');
            return null;
        }

        $this->logger->info('Weather request: city=' . $city . ', provider=' . $provider);

        return match ($provider) {
            'google' => $this->getWeatherGoogle($city, $apiKey),
            'openweathermap' => $this->getWeatherOpenWeatherMap($city, $apiKey),
            default => $this->getWeatherOpenWeatherMap($city, $apiKey),
        };
    }

    public function getWeatherByCoordinates(float $lat, float $lon): ?array
    {
        $provider = $this->settingsManager->getWeatherProvider();
        $apiKey = $this->settingsManager->getWeatherApiKey();
        
        if (!$apiKey) {
            $this->logger->error('Weather API key not configured in database');
            return null;
        }

        $this->logger->info('Weather request: coordinates=(' . $lat . ', ' . $lon . '), provider=' . $provider);

        return match ($provider) {
            'google' => $this->getWeatherGoogleByCoords($lat, $lon, $apiKey),
            'openweathermap' => $this->getWeatherOpenWeatherMapByCoords($lat, $lon, $apiKey),
            default => $this->getWeatherOpenWeatherMapByCoords($lat, $lon, $apiKey),
        };
    }

    // ============================================
    // GOOGLE WEATHER API
    // ============================================

    private function getWeatherGoogle(string $city, string $apiKey): ?array
    {
        try {
            // 1. Geocoding - отримуємо координати через Google
            $geoData = $this->getCoordinatesFromGoogle($city, $apiKey);
            if (!$geoData) {
                $this->logger->error('Could not get coordinates for city: ' . $city);
                return null;
            }

            return $this->getWeatherFromGoogleAPI($geoData['lat'], $geoData['lng'], $apiKey, $geoData['formatted_address'] ?? $city);
        } catch (\Exception $e) {
            $this->logger->error('Google Weather error: ' . $e->getMessage());
            return null;
        }
    }

    private function getWeatherGoogleByCoords(float $lat, float $lon, string $apiKey): ?array
    {
        try {
            return $this->getWeatherFromGoogleAPI($lat, $lon, $apiKey);
        } catch (\Exception $e) {
            $this->logger->error('Google Weather error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Google Weather API (основний метод)
     */
    private function getWeatherFromGoogleAPI(float $lat, float $lon, string $apiKey, ?string $cityName = null): ?array
    {
        try {
            // Google Weather API
            $response = $this->httpClient->request('GET', 'https://weather.googleapis.com/v1/currentConditions', [
                'query' => [
                    'location' => $lat . ',' . $lon,
                    'key' => $apiKey,
                    'languageCode' => 'uk',
                    'units' => 'metric',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->formatGoogleWeatherData($data, $cityName);
            } else {
                $this->logger->error('Google Weather API error: ' . $response->getStatusCode());
                $this->logger->error('Response: ' . $response->getContent(false));
            }
        } catch (\Exception $e) {
            $this->logger->error('Google Weather API error: ' . $e->getMessage());
        }

        // Fallback: використовуємо Open-Meteo
        $this->logger->info('Falling back to Open-Meteo API');
        return $this->getWeatherFromOpenMeteo($lat, $lon, $cityName ?? 'Невідомо');
    }

    private function getCoordinatesFromGoogle(string $city, string $apiKey): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $city,
                    'key' => $apiKey,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    return [
                        'lat' => $location['lat'],
                        'lng' => $location['lng'],
                        'formatted_address' => $data['results'][0]['formatted_address'] ?? $city,
                        'place_id' => $data['results'][0]['place_id'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Google Geocoding error: ' . $e->getMessage());
        }

        return null;
    }

    private function getCityFromGoogleByCoords(float $lat, float $lon, string $apiKey): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'latlng' => $lat . ',' . $lon,
                    'key' => $apiKey,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (isset($data['status']) && $data['status'] === 'OK' && !empty($data['results'])) {
                    foreach ($data['results'] as $result) {
                        foreach ($result['address_components'] as $component) {
                            if (in_array('locality', $component['types']) || 
                                in_array('administrative_area_level_1', $component['types'])) {
                                return $component['long_name'];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Google Geocoding error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Форматування даних з Google Weather API
     */
    private function formatGoogleWeatherData(array $data, ?string $cityName = null): array
    {
        $current = $data['currentConditions'] ?? [];
        $forecast = $data['forecast'] ?? [];
        
        return [
            'city' => $cityName ?? $data['location']['name'] ?? 'Невідомо',
            'country' => $data['location']['country'] ?? '',
            'temperature' => round($current['temperature'] ?? 0),
            'temperature_unit' => '°C',
            'feels_like' => round($current['feelsLikeTemperature'] ?? 0),
            'humidity' => $current['humidity'] ?? 0,
            'pressure' => $current['pressure'] ?? 0,
            'wind_speed' => round(($current['windSpeed'] ?? 0) * 3.6, 1),
            'description' => $current['condition']['text'] ?? '',
            'icon' => $this->getGoogleWeatherIcon($current['condition']['code'] ?? ''),
            'icon_url' => $this->getGoogleWeatherIconUrl($current['condition']['code'] ?? ''),
            'uv_index' => $current['uvIndex'] ?? null,
            'air_quality' => $current['airQuality'] ?? null,
            'precipitation' => $current['precipitation'] ?? 0,
            'forecast' => $this->formatGoogleForecast($forecast),
            'provider' => 'google',
        ];
    }

    private function formatGoogleForecast(array $forecast): array
    {
        $result = [];
        $days = $forecast['daily'] ?? [];
        
        foreach ($days as $day) {
            $result[] = [
                'date' => $day['date'] ?? '',
                'max_temp' => round($day['temperatureMax'] ?? 0),
                'min_temp' => round($day['temperatureMin'] ?? 0),
                'description' => $day['condition']['text'] ?? '',
                'icon' => $this->getGoogleWeatherIcon($day['condition']['code'] ?? ''),
                'precipitation' => $day['precipitation'] ?? 0,
            ];
        }
        
        return $result;
    }

    private function getGoogleWeatherIcon(string $code): string
    {
        $icons = [
            'clear' => '01d',
            'cloudy' => '04d',
            'rain' => '10d',
            'snow' => '13d',
            'thunderstorm' => '11d',
            'fog' => '50d',
            'partly_cloudy' => '02d',
            'overcast' => '04d',
        ];

        return $icons[$code] ?? '01d';
    }

    private function getGoogleWeatherIconUrl(string $code): string
    {
        $icon = $this->getGoogleWeatherIcon($code);
        return "https://openweathermap.org/img/wn/{$icon}@2x.png";
    }

    // ============================================
    // OPEN-METEO (безкоштовний, як fallback)
    // ============================================

    private function getWeatherFromOpenMeteo(float $lat, float $lon, string $cityName): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m,pressure_msl',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                    'timezone' => 'auto',
                    'forecast_days' => 3,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->formatOpenMeteoData($data, $cityName);
            }
        } catch (\Exception $e) {
            $this->logger->error('Open-Meteo error: ' . $e->getMessage());
        }

        return null;
    }

    private function formatOpenMeteoData(array $data, string $city): array
    {
        $current = $data['current'] ?? [];
        $daily = $data['daily'] ?? [];
        
        $weatherIcons = [
            0 => ['icon' => '01d', 'description' => 'Ясно'],
            1 => ['icon' => '02d', 'description' => 'Майже ясно'],
            2 => ['icon' => '03d', 'description' => 'Мінлива хмарність'],
            3 => ['icon' => '04d', 'description' => 'Хмарно'],
            45 => ['icon' => '50d', 'description' => 'Туман'],
            48 => ['icon' => '50d', 'description' => 'Туман'],
            51 => ['icon' => '10d', 'description' => 'Мряка'],
            53 => ['icon' => '10d', 'description' => 'Мряка'],
            55 => ['icon' => '10d', 'description' => 'Мряка'],
            56 => ['icon' => '09d', 'description' => 'Крижана мряка'],
            57 => ['icon' => '09d', 'description' => 'Крижана мряка'],
            61 => ['icon' => '10d', 'description' => 'Невеликий дощ'],
            63 => ['icon' => '10d', 'description' => 'Дощ'],
            65 => ['icon' => '10d', 'description' => 'Сильний дощ'],
            66 => ['icon' => '09d', 'description' => 'Крижаний дощ'],
            67 => ['icon' => '09d', 'description' => 'Крижаний дощ'],
            71 => ['icon' => '13d', 'description' => 'Невеликий сніг'],
            73 => ['icon' => '13d', 'description' => 'Сніг'],
            75 => ['icon' => '13d', 'description' => 'Сильний сніг'],
            77 => ['icon' => '13d', 'description' => 'Снігові зерна'],
            80 => ['icon' => '09d', 'description' => 'Невелика злива'],
            81 => ['icon' => '09d', 'description' => 'Злива'],
            82 => ['icon' => '09d', 'description' => 'Сильна злива'],
            85 => ['icon' => '13d', 'description' => 'Невеликий снігопад'],
            86 => ['icon' => '13d', 'description' => 'Снігопад'],
            95 => ['icon' => '11d', 'description' => 'Гроза'],
            96 => ['icon' => '11d', 'description' => 'Гроза з градом'],
            99 => ['icon' => '11d', 'description' => 'Гроза з градом'],
        ];

        $weatherCode = $current['weather_code'] ?? 0;
        $weatherInfo = $weatherIcons[$weatherCode] ?? ['icon' => '01d', 'description' => 'Невідомо'];

        $forecast = [];
        if (isset($daily['time'])) {
            for ($i = 0; $i < min(3, count($daily['time'])); $i++) {
                $code = $daily['weather_code'][$i] ?? 0;
                $info = $weatherIcons[$code] ?? ['icon' => '01d', 'description' => 'Невідомо'];
                $forecast[] = [
                    'date' => $daily['time'][$i] ?? '',
                    'max_temp' => round($daily['temperature_2m_max'][$i] ?? 0),
                    'min_temp' => round($daily['temperature_2m_min'][$i] ?? 0),
                    'description' => $info['description'],
                    'icon' => $info['icon'],
                ];
            }
        }

        return [
            'city' => $city,
            'country' => 'UA',
            'temperature' => round($current['temperature_2m'] ?? 0),
            'temperature_unit' => '°C',
            'feels_like' => round($current['apparent_temperature'] ?? 0),
            'humidity' => $current['relative_humidity_2m'] ?? 0,
            'pressure' => round(($current['pressure_msl'] ?? 0) * 0.750062, 1),
            'wind_speed' => round(($current['wind_speed_10m'] ?? 0) * 3.6, 1),
            'description' => $weatherInfo['description'],
            'icon' => $weatherInfo['icon'],
            'icon_url' => "https://openweathermap.org/img/wn/{$weatherInfo['icon']}@2x.png",
            'precipitation' => $current['precipitation'] ?? 0,
            'forecast' => $forecast,
            'provider' => 'open-meteo',
        ];
    }

    // ============================================
    // OPENWEATHERMAP
    // ============================================

    private function getWeatherOpenWeatherMap(string $city, string $apiKey): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'q' => $city,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'uk',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->formatOpenWeatherMapData($data);
            } else {
                $this->logger->error('OpenWeatherMap error: ' . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            $this->logger->error('OpenWeatherMap error: ' . $e->getMessage());
        }

        return null;
    }

    private function getWeatherOpenWeatherMapByCoords(float $lat, float $lon, string $apiKey): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'uk',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->formatOpenWeatherMapData($data);
            }
        } catch (\Exception $e) {
            $this->logger->error('OpenWeatherMap error: ' . $e->getMessage());
        }

        return null;
    }

    private function formatOpenWeatherMapData(array $data): array
    {
        return [
            'city' => $data['name'] ?? 'Невідомо',
            'country' => $data['sys']['country'] ?? '',
            'temperature' => round($data['main']['temp'] ?? 0),
            'temperature_unit' => '°C',
            'feels_like' => round($data['main']['feels_like'] ?? 0),
            'humidity' => $data['main']['humidity'] ?? 0,
            'pressure' => $data['main']['pressure'] ?? 0,
            'wind_speed' => round(($data['wind']['speed'] ?? 0) * 3.6, 1),
            'description' => $data['weather'][0]['description'] ?? '',
            'icon' => $data['weather'][0]['icon'] ?? '01d',
            'icon_url' => "https://openweathermap.org/img/wn/{$data['weather'][0]['icon']}@2x.png",
            'provider' => 'openweathermap',
        ];
    }
}