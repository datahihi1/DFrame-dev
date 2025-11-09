<?php
namespace DFrame\Command;

class Search
{
    public function handle(array $args, bool $debug = false)
    {
        $query = $args[0] ?? '';
        if (!$query) {
            echo "Please provide a search query.\n";
            return;
        }

        if ($debug) {
            echo "[DEBUG] Searching for '$query'\n";
        }

        // Ví dụ dữ liệu JSON nhỏ
        $data = json_decode(file_get_contents(__DIR__ . '/../data.json'), true);

        $results = array_filter($data, fn($item) => stripos($item['title'], $query) !== false);

        if ($results) {
            foreach ($results as $item) {
                echo $item['title'] . PHP_EOL;
            }
        } else {
            echo "No results found for '$query'.\n";
        }
    }
}
