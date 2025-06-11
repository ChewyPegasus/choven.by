<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // Проверяем, что локаль допустима
        if (!in_array($locale, ['ru', 'be', 'en'])) {
            $locale = 'ru';
        }
        
        // Получаем предыдущий URL
        $referer = $request->headers->get('referer');
        
        if ($referer) {
            // Если есть referer, сохраняем его
            $url = $referer;
            
            // Разбираем URL
            $parts = parse_url($url);
            
            if (isset($parts['path'])) {
                $path = $parts['path'];
                
                // Находим текущую локаль в URL и заменяем на новую
                $pattern = '~^/(' . implode('|', ['ru', 'be', 'en']) . ')/~';
                if (preg_match($pattern, $path, $matches)) {
                    $newPath = preg_replace($pattern, '/' . $locale . '/', $path);
                    $parts['path'] = $newPath;
                    
                    // Собираем URL обратно
                    $url = $this->buildUrl($parts);
                    
                    return $this->redirect($url);
                }
            }
        }
        
        // Если не удалось определить URL для редиректа, перенаправляем на главную с новой локалью
        return $this->redirectToRoute('app_main', ['_locale' => $locale]);
    }
    
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        
        return sprintf('%s%s%s%s%s%s%s%s', 
        $scheme, 
        $user, 
        $pass, 
        $host, 
        $port, 
        $path, 
        $query, 
        $fragment);
        #return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
