<?php
namespace App\Infrastructure\Facebook;

use App\Infrastructure\Facebook\FacebookServiceInterface;
use Illuminate\Support\Facades\Http;

class FacebookService implements FacebookServiceInterface
{
    public function getProfile(string $url): string
    {
        if (str_contains($url, 'facebook.com/share/')) {
            $resolved = Http::withHeaders([
                'User-Agent' => 'curl/7.68.0'
            ])->withOptions([
                        'allow_redirects' => [
                            'max' => 10,
                            'protocols' => ['http', 'https'],
                            'track_redirects' => true,
                        ],
                        'connect_timeout' => 5,
                    ])->get($url)->effectiveUri();

            $url = $resolved === null ? $url : (string) $resolved;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if ($path === '/profile.php') {
            return $this->getProfileID($query) ?? throw new \InvalidArgumentException('Invalid Facebook profile URL');
        }

        if (str_contains($path, '/people/')) {
            return $this->getProfilePeopleUsername($path) ?? throw new \InvalidArgumentException('Invalid Facebook profile URL');
        }

        return trim($path, '/');
    }

    private function getProfileID(string $query): ?string {
        parse_str($query, $params);
        
        if (!isset($params['id'])) {
            return null;
        }

        return 'profile.php?id=' . $params['id'];
    }

    private function getProfilePeopleUsername(string $path): ?string {
        if (preg_match('/^\/people\/[^\/]+\/([^\/]+)/', $path, $matches)) {
            return ltrim(urldecode($matches[0]), '/');
        }

        return null;
    }

}