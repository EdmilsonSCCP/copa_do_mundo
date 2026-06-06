<?php
declare(strict_types=1);

function spotify_http_get(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 20,
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
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function spotify_fetch_top30(string $root): array
{
    $html = spotify_http_get('https://kworb.net/spotify/listeners.html');

    $pattern = '/<tr>\s*<td>(\d+)<\/td>\s*<td class="text">\s*<div>\s*<a href="artist\/[^"]+">([^<]+)<\/a>\s*<\/div>\s*<\/td>\s*<td>([\d,]+)<\/td>/i';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    $top30 = array_slice($matches, 0, 30);
    if (!$top30) {
        throw new RuntimeException('O ranking baixou, mas nenhum artista foi encontrado.');
    }

    return array_map(static fn(array $artist): array => [
        'posicao' => trim($artist[1]),
        'nome' => html_entity_decode(trim($artist[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'ouvintes' => trim($artist[3]),
    ], $top30);
}

function spotify_attach_images(array $artists): array
{
    $result = [];

    foreach ($artists as $artist) {
        $query = urlencode($artist['nome']);
        $image = null;

        try {
            $response = spotify_http_get("https://api.deezer.com/search/artist?q={$query}&limit=1");
            $data = json_decode($response, true);
            $image = $data['data'][0]['picture_xl'] ?? $data['data'][0]['picture_big'] ?? null;
        } catch (Throwable) {
            $image = null;
        }

        $result[] = [
            'posicao' => $artist['posicao'],
            'nome' => $artist['nome'],
            'ouvintes' => $artist['ouvintes'],
            'imagem' => $image,
        ];

        usleep(180000);
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

    $withImages = spotify_attach_images($top30);
    spotify_write_json($root . '/top30_com_imagem.json', $withImages);

    return spotify_process_history($root, $withImages);
}
