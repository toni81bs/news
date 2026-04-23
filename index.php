<?php

header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: frame-ancestors 'self' https://tdn-test.free.bg;");
header('Access-Control-Allow-Origin', "*");    
header('Access-Control-Allow-Headers', '*');    
header("Access-Control-Allow-Methods", "*");
// Или ако искаш да си още по-сигурен за сигурността:
header("Content-Security-Policy: frame-ancestors *;");
    
date_default_timezone_set('Europe/Sofia');

// --- 1. НАСТРОЙКИ ---
$api_key = 'a660e3424fde9892f9b1c973e930c63e26f8965166114558395a73f67ca62db8'; // <--- СЛОЖИ КЛЮЧА СИ ТУК
$cache_file = 'cache_news.json';

$current_hour = (int)date('H'); // Вземаме текущия час (0-23)
if ($current_hour >= 8 && $current_hour <= 23) {
    $cache_time = 10800; // 3 часа в секунди
} else {
    $cache_time = 43200; // 12 часа (практически не обновява през нощта)
}

$query = "България";

$data = null;

$needs_update = false;

// --- 2. ЛОГИКА ЗА КЕШИРАНЕ ---
if (!file_exists($cache_file) || (time() - filemtime($cache_file) > $cache_time)) {
    $needs_update = true;
}

if (!$needs_update && file_exists($cache_file)) {
    // Използваме стария кеш
    $data = json_decode(file_get_contents($cache_file), true);
} else {
    $params = [
        "engine" => "google_news",
        "q" => $query,
        "gl" => "bg",
        "hl" => "bg",
        "api_key" => $api_key
    ];
    $url = "https://serpapi.com/search.json?" . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        file_put_contents($cache_file, $response);
    } else if (file_exists($cache_file)) {
        // Ако SerpApi не отговори или има грешка, ползваме кеша като резерва
        $data = json_decode(file_get_contents($cache_file), true);
    }


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        file_put_contents($cache_file, $response);
    } elseif (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
    }
}

// --- 3. ГЛАВЕН CSS СТИЛ ---
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новини от България</title>
    <style>
        :root { --main-blue: #1a0dab; --main-red: #d93025; --bg: #f5f5f5; }
        body { background-color: var(--bg); font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        
        .container { max-width: 1100px; margin: 10px auto; padding: 0 10px; }
        
        .header-box { 
            background: #fff; padding: 10px; border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; 
            margin-bottom: 10px; border: 1px solid #eee;
        }
        .header-box img { margin: 0; color: var(--main-blue); font-size: 24px; }

        .news-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }

        .card { 
            background: #fff; border-radius: 15px; overflow: hidden; 
            display: flex; flex-direction: column; text-decoration: none; 
            color: #333; transition: 0.3s; border: 1px solid #efefef;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }

        .img-wrapper { width: 100%; height: 110px; background: #eee; }
        .img-wrapper img { width: 100%; height: 100%; object-fit: cover; }

        .content { padding: 10px; display: flex; flex-direction: column; flex-grow: 1; }
        .source { color: var(--main-red); font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; }
        .title { margin: 0; font-size: 14px; line-height: 1.4; font-weight: 600; flex-grow: 1; }
        .meta { margin-top: 10px; font-size: 10px; color: #777; border-top: 1px solid #f5f5f5; padding-top: 10px; }
        
        .logo { width: 120px; height: auto; }
        
        @media (max-width: 900px) { .news-grid { grid-template-columns: repeat(2, 1fr);
      }
      }

        @media (max-width: 600px) { .news-grid { grid-template-columns: 1fr;
      }
      }
    </style>
</head>
<body>

<div class="container">
    <div class="header-box">
        <img src="google-news.png" class="logo" alt="Google News">
    </div>

    <div class="news-grid">
        <?php
        if (isset($data['news_results'])) {
            foreach ($data['news_results'] as $news) {
                // Първоначални данни
                $title = $news['title'] ?? '';
                $link = $news['link'] ?? '#';
                $source = $news['source']['name'] ?? 'Медия';
                $thumb = $news['thumbnail'] ?? '';

                // 1. ПРОВЕРКА ЗА FACEBOOK И ПОПРАВКА НА ПРАЗНИ ЗАГЛАВИЯ
                $is_fb = (stripos($source, 'facebook') !== false || stripos($link, 'facebook.com') !== false);

                // Ако е Facebook или няма заглавие, ровим в stories
                if (($is_fb || empty($title)) && !empty($news['stories'])) {
                    foreach ($news['stories'] as $story) {
                        if (stripos($story['source']['name'], 'facebook') === false) {
                            $title = $story['title'];
                            $link = $story['link'];
                            $source = $story['source']['name'];
                            $thumb = !empty($thumb) ? $thumb : ($story['thumbnail'] ?? '');
                            $is_fb = false; // Намерихме истинска новина
                            break;
                        }
                    }
                }

                // Ако след проверката все още е Facebook или няма заглавие - прескачаме
                if ($is_fb || empty($title)) continue;

                // Показване на картата
                ?>
                <a href="<?php echo $link; ?>" target="_blank" class="card">
                    <div class="img-wrapper">
                        <img src="<?php echo $thumb ?: 'https://placehold.co/400x250?text=Новини'; ?>" alt="img" onerror="this.src='https://placehold.co/400x250?text=Новини';">
                    </div>
                    <div class="content">
                        <div class="source"><?php echo htmlspecialchars($source); ?></div>
                        <h3 class="title"><?php echo htmlspecialchars($title); ?></h3>
                    </div>
                </a>
                <?php
            }
        } else {
            echo "<p style='grid-column: 1/-1; text-align:center;'>Няма данни. Моля, проверете API ключа.</p>";
        }
        ?>
    </div>
</div>
        
</body>
</html>