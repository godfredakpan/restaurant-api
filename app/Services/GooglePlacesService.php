<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_PLACES_API_KEY');
    }

        public function searchBusinesses(
        string $query, 
        string $type = null, 
        array $location = null, 
        int $radius = 5000,
        string $textLocation = null
    ) {
        $endpoint = $this->baseUrl . '/textsearch/json';
        
        $params = [
            'query' => $query . ($type ? ' ' . $type : ''),
            'key' => $this->apiKey,
            'region' => 'NG',
        ];
        
        // Handle coordinate location
        if ($location && is_array($location)) {
            $params['location'] = implode(',', $location);
            $params['radius'] = $radius;
        }
        // Handle text location
        elseif ($textLocation) {
            $params['location'] = $textLocation;
        }
        
        $response = Http::get($endpoint, $params);
        
        if ($response->successful()) {
            return $this->formatResults($response->json()['results']);
        }
        
        return [];
    }
    
    public function getBusinessDetails(string $placeId)
    {
        $endpoint = $this->baseUrl . '/details/json';
        
        $response = Http::get($endpoint, [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,formatted_phone_number,website,photos,opening_hours',
            'key' => $this->apiKey,
        ]);
        
        if ($response->successful()) {
            return $this->formatDetails($response->json()['result']);
        }
        
        return null;
    }
    
   
    protected function formatResults(array $results): array
    {
        return array_map(function ($result) {
            return [
                'place_id' => $result['place_id'],
                'name' => $result['name'],
                'address' => $result['formatted_address'],
                'photo' => isset($result['photos']) ? $this->getPhotoUrl($result['photos'][0]['photo_reference']) : null,
                'rating' => $result['rating'] ?? null,
                'geometry' => $result['geometry'] ?? null, // Add geometry data
            ];
        }, $results);
    }
    
    protected function formatDetails(array $details): array
    {
        return [
            'name' => $details['name'],
            'address' => $details['formatted_address'],
            'phone' => $details['formatted_phone_number'] ?? null,
            'website' => $details['website'] ?? null,
            'photos' => array_map(function ($photo) {
                return $this->getPhotoUrl($photo['photo_reference']);
            }, $details['photos'] ?? []),
            'opening_hours' => $details['opening_hours']['weekday_text'] ?? null,
        ];
    }
    
    protected function getPhotoUrl(string $reference, int $maxWidth = 400): string
    {
        return $this->baseUrl . '/photo?' . http_build_query([
            'maxwidth' => $maxWidth,
            'photoreference' => $reference,
            'key' => $this->apiKey,
        ]);
    }
}