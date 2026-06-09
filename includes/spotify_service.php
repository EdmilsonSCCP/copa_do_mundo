<?php
declare(strict_types=1);

function spotify_http_get(string $url, int $timeout = 20): string
{
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => $timeout,
        ],
    ]);

    $contents = @file_get_contents($url, false, $context);
    if ($contents === false) {
        throw new RuntimeException("Não foi possível baixar: {$url}");
    }

    return $contents;
}

function spotify_read_json(string $path, $fallback)
{
    if (!file_exists($path)) {
        return $fallback;
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : $fallback;
}

function spotify_write_json(string $path, $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException("Nao foi possivel gerar JSON para: {$path}");
    }

    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException("Nao foi possivel gravar o arquivo: {$path}. Verifique permissoes.");
    }
}

function spotify_fetch_top30(string $root): array
{
    $html = spotify_http_get('https://kworb.net/spotify/listeners.html');

    preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rows);

    $top30 = [];
    foreach ($rows[1] ?? [] as $rowHtml) {
        if (!preg_match('/<a\s+href="artist\/[^"]+">([^<]+)<\/a>/i', $rowHtml, $artistMatch)) {
            continue;
        }

        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $cells);
        $cells = $cells[1] ?? [];

        if (count($cells) < 3) {
            continue;
        }

        $rank = trim(strip_tags($cells[0]));
        $listeners = trim(strip_tags($cells[2]));

        if (!ctype_digit($rank) || !preg_match('/^\d{1,3}(,\d{3})+$/', $listeners)) {
            continue;
        }

        $top30[] = [
            $rank,
            html_entity_decode(trim($artistMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $listeners,
        ];

        if (count($top30) === 30) {
            break;
        }
    }

    if (!$top30) {
        throw new RuntimeException('O ranking baixou, mas nenhum artista foi encontrado.');
    }

    return array_map(static fn(array $artist): array => [
        'posicao' => trim((string)$artist[0]),
        'nome' => trim((string)$artist[1]),
        'ouvintes' => trim((string)$artist[2]),
    ], $top30);
}

function spotify_attach_images(array $artists, string $root): array
{
    $result = [];
    $cachedArtists = spotify_read_json($root . '/top30_com_imagem.json', []);
    $cachedImages = [];

    foreach ($cachedArtists as $cachedArtist) {
        if (!empty($cachedArtist['nome']) && !empty($cachedArtist['imagem'])) {
            $cachedImages[$cachedArtist['nome']] = $cachedArtist['imagem'];
        }
    }

    foreach ($artists as $artist) {
        $name = $artist['nome'];
        $image = $cachedImages[$name] ?? null;

        if (!$image) {
            $query = urlencode($name);

            try {
                $response = spotify_http_get("https://api.deezer.com/search/artist?q={$query}&limit=1", 6);
                $data = json_decode($response, true);
                $image = $data['data'][0]['picture_xl'] ?? $data['data'][0]['picture_big'] ?? null;
            } catch (Throwable) {
                $image = null;
            }

            usleep(180000);
        }

        $result[] = [
            'posicao' => $artist['posicao'],
            'nome' => $name,
            'ouvintes' => $artist['ouvintes'],
            'imagem' => $image,
        ];
    }

    return $result;
}

function spotify_process_history(string $root, array $artists): array
{
    $historyPath = $root . '/historico_artistas.json';
    $history = spotify_read_json($historyPath, []);
    $currentWeek = date('o-W');

    $previousPositions = [];
    foreach ($history as $artistName => $artistData) {
        if (isset($artistData['current_rank'])) {
            $previousPositions[$artistName] = (int)$artistData['current_rank'];
        }
    }

    $newHistory = [];
    $display = [];

    foreach ($artists as $artist) {
        $rank = (int)$artist['posicao'];
        $name = $artist['nome'];
        $old = $history[$name] ?? null;

        if ($old) {
            $registeredWeek = $old['ultima_atualizacao_semana'] ?? null;
            $weeks = ($currentWeek !== $registeredWeek) ? ((int)$old['weeks'] + 1) : (int)$old['weeks'];
            $peak = min((int)$old['peak'], $rank);
            $lastWeek = $previousPositions[$name] ?? null;
            $isNew = false;
        } else {
            $weeks = 1;
            $peak = $rank;
            $lastWeek = null;
            $isNew = true;
        }

        $newHistory[$name] = [
            'peak' => $peak,
            'weeks' => $weeks,
            'current_rank' => $rank,
            'ultima_atualizacao_semana' => $currentWeek,
        ];

        $display[] = [
            'rank' => $rank,
            'artist' => $name,
            'image' => $artist['imagem'],
            'listeners' => $artist['ouvintes'],
            'lw' => $lastWeek,
            'peak' => $peak,
            'weeks' => $weeks,
            'is_new' => $isNew,
        ];
    }

    spotify_write_json($historyPath, $newHistory);
    spotify_write_json($root . '/top30_display.json', $display);

    return $display;
}

function spotify_refresh_ranking(string $root): array
{
    $top30 = spotify_fetch_top30($root);
    spotify_write_json($root . '/top30.json', $top30);

    $withImages = spotify_attach_images($top30, $root);
    spotify_write_json($root . '/top30_com_imagem.json', $withImages);

    return spotify_process_history($root, $withImages);
}
